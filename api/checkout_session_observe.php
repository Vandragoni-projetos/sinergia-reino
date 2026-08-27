<?php
/**
 * Observação de jornada de checkout (camada paralela).
 *
 * URL: POST /api/checkout_session_observe
 * Escreve SOMENTE em checkout_sessions e checkout_session_events.
 * NÃO escreve em vendas, NÃO dispara mensagens, NÃO altera pagamento.
 *
 * Fail-open: qualquer falha responde 204 e o checkout/pagamento segue independente.
 */

ob_start();
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('checkout_observe fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(204);
    }
});

function checkout_observe_quiet_exit() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(204);
    exit;
}

function checkout_observe_log($message) {
    error_log('checkout_observe: ' . $message);
}

$max_body = 8192;
$content_length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($content_length > $max_body) {
    checkout_observe_quiet_exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    checkout_observe_quiet_exit();
}
if ($method !== 'POST') {
    checkout_observe_quiet_exit();
}

$raw = file_get_contents('php://input', false, null, 0, $max_body + 1);
if ($raw === false || strlen($raw) > $max_body) {
    checkout_observe_quiet_exit();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rate_file = sys_get_temp_dir() . '/rl_checkout_observe_' . md5($ip) . '.json';
$now = time();
$rate = ['count' => 0, 'window_start' => $now];
if (is_file($rate_file)) {
    $decoded_rate = json_decode((string) @file_get_contents($rate_file), true);
    if (is_array($decoded_rate)) {
        $rate = $decoded_rate;
    }
    if ($now - (int) ($rate['window_start'] ?? 0) > 60) {
        $rate = ['count' => 0, 'window_start' => $now];
    }
}
$rate['count'] = (int) ($rate['count'] ?? 0) + 1;
@file_put_contents($rate_file, json_encode($rate), LOCK_EX);
if ($rate['count'] > 60) {
    checkout_observe_quiet_exit();
}

$input = json_decode($raw, true);
if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
    checkout_observe_quiet_exit();
}

$env_loader = __DIR__ . '/../config/env_loader.php';
if (!is_file($env_loader)) {
    checkout_observe_quiet_exit();
}

try {
    require_once $env_loader;
} catch (Throwable $e) {
    checkout_observe_log('env_loader: ' . $e->getMessage());
    checkout_observe_quiet_exit();
}

try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_NAME', 'checkout') . ';charset=utf8mb4',
        env('DB_USER', ''),
        env('DB_PASS', ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    checkout_observe_log('db connect failed');
    checkout_observe_quiet_exit();
}

$observe_flag = '0';
try {
    $stmt_flag = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
    $stmt_flag->execute(['checkout_recovery_observe']);
    $row_flag = $stmt_flag->fetch();
    if ($row_flag && $row_flag['valor'] !== null && $row_flag['valor'] !== '') {
        $observe_flag = (string) $row_flag['valor'];
    }
} catch (Throwable $e) {
    checkout_observe_quiet_exit();
}

if ($observe_flag !== '1') {
    checkout_observe_quiet_exit();
}

$allowed_events = [
    'opened' => true,
    'customer_info' => true,
    'payment_method_selected' => true,
    'payment_attempted' => true,
    'pix_seen' => true,
];

$event_name = isset($input['event_name']) ? strtolower(trim((string) $input['event_name'])) : '';
if ($event_name === '' || !isset($allowed_events[$event_name])) {
    checkout_observe_quiet_exit();
}

$produto_id = filter_var($input['produto_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($produto_id === false) {
    checkout_observe_quiet_exit();
}

$browser_uuid = isset($input['browser_uuid']) ? trim((string) $input['browser_uuid']) : '';
if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $browser_uuid)) {
    checkout_observe_quiet_exit();
}

$produto = null;
try {
    $stmt_prod = $pdo->prepare('SELECT id, usuario_id, COALESCE(community_id, 1) AS community_id FROM produtos WHERE id = ? LIMIT 1');
    $stmt_prod->execute([$produto_id]);
    $produto = $stmt_prod->fetch();
} catch (Throwable $e) {
    checkout_observe_log('produto lookup failed');
    checkout_observe_quiet_exit();
}

if (!$produto || (int) $produto['usuario_id'] <= 0) {
    checkout_observe_quiet_exit();
}

$usuario_id = (int) $produto['usuario_id'];
$community_id = (int) $produto['community_id'];

$oferta_id = null;
if (isset($input['oferta_id']) && $input['oferta_id'] !== '' && $input['oferta_id'] !== null) {
    $oferta_candidate = filter_var($input['oferta_id'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($oferta_candidate !== false) {
        try {
            $stmt_oferta = $pdo->prepare('SELECT id FROM produto_ofertas WHERE id = ? AND produto_id = ? AND ativo = 1 LIMIT 1');
            $stmt_oferta->execute([$oferta_candidate, $produto_id]);
            if ($stmt_oferta->fetch()) {
                $oferta_id = $oferta_candidate;
            }
        } catch (Throwable $e) {
            $oferta_id = null;
        }
    }
}

$cliente_nome = null;
$cliente_email = null;
$cliente_telefone = null;
$payment_method = null;
$order_bumps_json = null;
$coupon_code = null;
$transacao_id = null;
$payload = [];

if ($event_name === 'opened') {
    $payload['produto_id'] = $produto_id;
    $payload['browser_uuid'] = $browser_uuid;
    if ($oferta_id !== null) {
        $payload['oferta_id'] = $oferta_id;
    }
}

if ($event_name === 'customer_info') {
    $nome_raw = isset($input['nome']) ? trim(strip_tags((string) $input['nome'])) : '';
    $nome_raw = preg_replace('/[\r\n]+/', ' ', $nome_raw);
    if ($nome_raw !== '' && strlen($nome_raw) <= 255) {
        $cliente_nome = $nome_raw;
        $payload['nome'] = $nome_raw;
    }
    $email_raw = isset($input['email']) ? trim((string) $input['email']) : '';
    if ($email_raw !== '' && filter_var($email_raw, FILTER_VALIDATE_EMAIL) && strlen($email_raw) <= 255) {
        $cliente_email = $email_raw;
        $payload['email'] = $email_raw;
    }
    $phone_raw = isset($input['telefone']) ? preg_replace('/\D+/', '', (string) $input['telefone']) : '';
    if ($phone_raw !== '' && strlen($phone_raw) >= 8 && strlen($phone_raw) <= 20) {
        $cliente_telefone = $phone_raw;
        $payload['telefone'] = $phone_raw;
    }
    if ($cliente_nome === null && $cliente_email === null && $cliente_telefone === null) {
        checkout_observe_quiet_exit();
    }
}

if ($event_name === 'payment_method_selected' || $event_name === 'payment_attempted') {
    $method_raw = isset($input['payment_method']) ? strtolower(trim((string) $input['payment_method'])) : '';
    $method_raw = preg_replace('/[^a-z0-9_]/', '', $method_raw);
    if ($method_raw !== '' && strlen($method_raw) <= 50 && strpos($method_raw, 'token') === false) {
        $payment_method = $method_raw;
        $payload['payment_method'] = $method_raw;
    } elseif ($event_name === 'payment_method_selected') {
        checkout_observe_quiet_exit();
    }
}

if ($event_name === 'payment_attempted') {
    $bump_ids = [];
    if (isset($input['order_bump_product_ids']) && is_array($input['order_bump_product_ids'])) {
        $seen = [];
        foreach (array_slice($input['order_bump_product_ids'], 0, 20) as $bid) {
            $id_int = filter_var($bid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id_int !== false && !isset($seen[$id_int])) {
                $seen[$id_int] = true;
                $bump_ids[] = $id_int;
            }
        }
    }
    if ($bump_ids) {
        $order_bumps_json = json_encode($bump_ids);
        $payload['order_bump_product_ids'] = $bump_ids;
    }
    $coupon_raw = isset($input['coupon_code']) ? strtoupper(trim((string) $input['coupon_code'])) : '';
    $coupon_raw = preg_replace('/[^A-Z0-9_\-]/', '', $coupon_raw);
    if ($coupon_raw !== '' && strlen($coupon_raw) <= 50) {
        $coupon_code = $coupon_raw;
        $payload['coupon_code'] = $coupon_raw;
    }
}

if ($event_name === 'pix_seen') {
    $txid = isset($input['transacao_id']) ? (string) $input['transacao_id'] : '';
    if ($txid === '' && isset($input['payment_id'])) {
        $txid = (string) $input['payment_id'];
    }
    $txid = preg_replace('/[^a-zA-Z0-9_\-]/', '', $txid);
    if ($txid === '' || strlen($txid) > 128) {
        checkout_observe_quiet_exit();
    }
    $transacao_id = $txid;
    $payload['transacao_id'] = $txid;
}

$status = 'open';
if ($event_name === 'customer_info' || $event_name === 'payment_method_selected') {
    $status = 'lead';
}
if ($event_name === 'payment_attempted' || $event_name === 'pix_seen') {
    $status = 'payment_attempted';
}

$last_stage = $event_name;

try {
    $sql_upsert = 'INSERT INTO checkout_sessions (
            usuario_id, produto_id, community_id, browser_uuid, oferta_id,
            cliente_nome, cliente_email, cliente_telefone, payment_method,
            order_bumps_json, coupon_code, transacao_id, last_stage, status, last_event_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, NOW()
        )
        ON DUPLICATE KEY UPDATE
            oferta_id = COALESCE(VALUES(oferta_id), oferta_id),
            cliente_nome = COALESCE(VALUES(cliente_nome), cliente_nome),
            cliente_email = COALESCE(VALUES(cliente_email), cliente_email),
            cliente_telefone = COALESCE(VALUES(cliente_telefone), cliente_telefone),
            payment_method = COALESCE(VALUES(payment_method), payment_method),
            order_bumps_json = COALESCE(VALUES(order_bumps_json), order_bumps_json),
            coupon_code = COALESCE(VALUES(coupon_code), coupon_code),
            transacao_id = COALESCE(VALUES(transacao_id), transacao_id),
            last_stage = VALUES(last_stage),
            status = CASE
                WHEN VALUES(status) = \'payment_attempted\' THEN \'payment_attempted\'
                WHEN status = \'payment_attempted\' THEN status
                WHEN VALUES(status) = \'lead\' THEN \'lead\'
                WHEN status = \'lead\' THEN status
                ELSE VALUES(status)
            END,
            last_event_at = VALUES(last_event_at)';

    $stmt_up = $pdo->prepare($sql_upsert);
    $stmt_up->execute([
        $usuario_id,
        $produto_id,
        $community_id,
        $browser_uuid,
        $oferta_id,
        $cliente_nome,
        $cliente_email,
        $cliente_telefone,
        $payment_method,
        $order_bumps_json,
        $coupon_code,
        $transacao_id,
        $last_stage,
        $status,
    ]);

    $stmt_sid = $pdo->prepare('SELECT id FROM checkout_sessions WHERE usuario_id = ? AND browser_uuid = ? LIMIT 1');
    $stmt_sid->execute([$usuario_id, $browser_uuid]);
    $session_row = $stmt_sid->fetch();
    $session_id = $session_row ? (int) $session_row['id'] : 0;
    if ($session_id <= 0) {
        checkout_observe_quiet_exit();
    }

    $skip_event = false;
    if ($event_name === 'opened') {
        $stmt_dup = $pdo->prepare('SELECT id FROM checkout_session_events WHERE session_id = ? AND event_name = ? LIMIT 1');
        $stmt_dup->execute([$session_id, 'opened']);
        if ($stmt_dup->fetch()) {
            $skip_event = true;
        }
    }

    if (!$skip_event) {
        $payload_json = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $stmt_ev = $pdo->prepare(
            'INSERT INTO checkout_session_events (session_id, usuario_id, event_name, payload_json)
             VALUES (?, ?, ?, ?)'
        );
        $stmt_ev->execute([$session_id, $usuario_id, $event_name, $payload_json]);
    }
} catch (Throwable $e) {
    checkout_observe_log('write failed');
    checkout_observe_quiet_exit();
}

checkout_observe_quiet_exit();
