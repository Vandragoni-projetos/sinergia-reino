<?php
// Carrega .env da raiz do projeto (se existir)
require_once __DIR__ . '/env_loader.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'gatewaypro'));
define('DB_PASS', env('DB_PASS', 'gatewaypro_secret_2024'));
define('DB_NAME', env('DB_NAME', 'checkout'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));

date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '-03:00';");
} catch (PDOException $e) {
    // Em produção, não exponha detalhes da conexão ao usuário final
    error_log("DB CONNECTION ERROR: " . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        die("ERRO: Não foi possível conectar ao banco de dados.\n");
    }
    die("ERRO: Não foi possível conectar ao banco de dados. Por favor, tente novamente mais tarde.");
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getSystemSetting($chave, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ?");
        $stmt->execute([$chave]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['valor'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function setSystemSetting($chave, $valor) {
    global $pdo;
    if (!$pdo) return false;
    try {
        $stmt_check = $pdo->prepare("SELECT id FROM configuracoes_sistema WHERE chave = ?");
        $stmt_check->execute([$chave]);
        $exists = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = ?");
            $stmt->execute([$valor, $chave]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?)");
            $stmt->execute([$chave, $valor]);
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function getAllSystemSettings() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes_sistema");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['chave']] = $row['valor'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

require_once __DIR__ . '/../helpers/plugin_hooks.php';
require_once __DIR__ . '/../helpers/plugin_loader.php';
require_once __DIR__ . '/../helpers/community_helper.php';
require_once __DIR__ . '/../helpers/security_helper.php';
require_once __DIR__ . '/../helpers/image_helper.php';
require_once __DIR__ . '/../helpers/product_helper.php';
require_once __DIR__ . '/../helpers/product_taxonomy_helper.php';
require_once __DIR__ . '/../helpers/coupon_helper.php';

$redirect_session_error = function ($error_key, $message_pt) {
    $req_uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_api = (strpos($req_uri, '/api/') !== false);
    if ($is_api && !headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => $error_key, 'message' => $message_pt]);
        exit;
    }
    header('Location: /login?' . $error_key . '=1');
    exit;
};

// Endpoints públicos de pagamento/webhook não devem ser redirecionados para login
$public_payment_scripts = [
    'notification.php',
    'process_payment.php',
    'check_status.php',
    'checkout.php',
    'obrigado.php',
];
$current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$request_path = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$public_payment_paths = ['notification', 'process_payment', 'check_status'];
$is_public_payment_endpoint = in_array($current_script, $public_payment_scripts, true)
    || in_array($request_path, $public_payment_paths, true);

// 1) Sessão única: valida antes do timeout (evita renovar last_activity em sessão inválida)
if (!$is_public_payment_endpoint && !enforce_single_session()) {
    $redirect_session_error('session_replaced', 'Sessão encerrada. Você entrou em outro navegador ou dispositivo.');
}

// 2) Timeout por inatividade: só atualiza last_activity se sessão passou nas validações acima
// Regra: mínimo 60 min, padrão 120 min (configuracoes_sistema.session_timeout_minutes)
if (!$is_public_payment_endpoint && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $timeout_min = (int) (getSystemSetting('session_timeout_minutes', 120) ?: 120);
    $timeout_min = max(60, $timeout_min);
    $timeout_sec = $timeout_min * 60;
    if (!check_session_timeout($timeout_sec)) {
        $redirect_session_error('session_timeout', 'Sessão expirada por inatividade. Faça login novamente.');
    }
}
?>
