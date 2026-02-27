<?php
/**
 * Processa "compras" de produtos gratuitos
 * Não requer pagamento, apenas coleta dados do cliente e libera acesso
 */

// Desabilita exibição de erros no output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/process_free_log.txt');
error_reporting(E_ALL);

// Inicia output buffering para capturar qualquer output indesejado
ob_start();

function returnJsonError($message, $code = 400) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

function returnJsonSuccess($data) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function log_free($msg) {
    $log_file = __DIR__ . '/process_free_log.txt';
    @file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

log_free("=== INÍCIO DO PROCESSAMENTO ===");

// Carrega configuração
$config_path = __DIR__ . '/config/config.php';
if (!file_exists($config_path)) {
    log_free("ERRO: Config não encontrado em: $config_path");
    returnJsonError('Arquivo de configuração não encontrado.', 500);
}

try {
    require_once $config_path;
    log_free("Config carregado com sucesso");
} catch (Exception $e) {
    log_free("ERRO ao carregar config: " . $e->getMessage());
    returnJsonError('Erro ao carregar configuração.', 500);
}

// Limpa qualquer output do config
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Inclui helpers
$utmfy_path = __DIR__ . '/helpers/utmfy_helper.php';
if (file_exists($utmfy_path)) {
    require_once $utmfy_path;
}

$evolution_path = __DIR__ . '/helpers/evolution_helper.php';
if (file_exists($evolution_path)) {
    require_once $evolution_path;
}

log_free("Iniciando processamento de produto grátis");

$raw_post_data = file_get_contents('php://input');
log_free("Dados recebidos: " . $raw_post_data);

$data = json_decode($raw_post_data, true);

if (!$data) {
    log_free("ERRO: Dados JSON inválidos");
    returnJsonError('Dados inválidos.', 400);
}

// Campos obrigatórios
$required_fields = ['email', 'name', 'product_id'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        log_free("ERRO: Campo obrigatório ausente: $field");
        returnJsonError("Campo obrigatório ausente: $field", 400);
    }
}

$product_id = (int)$data['product_id'];
$email = trim($data['email']);
$name = trim($data['name']);
$cpf = isset($data['cpf']) ? preg_replace('/[^0-9]/', '', $data['cpf']) : '';
$phone = isset($data['phone']) ? preg_replace('/[^0-9]/', '', $data['phone']) : '';
$oferta_id = isset($data['oferta_id']) ? (int)$data['oferta_id'] : null;

try {
    // Verifica se o produto existe e é gratuito
    $stmt = $pdo->prepare("SELECT p.*, u.id as usuario_id FROM produtos p JOIN usuarios u ON p.usuario_id = u.id WHERE p.id = ? AND p.is_free = 1");
    $stmt->execute([$product_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        returnJsonError('Produto não encontrado ou não é gratuito.', 404);
    }
    
    $usuario_id = $produto['usuario_id'];
    $product_name = $produto['nome'];
    
    // Gera um ID único para a "transação" gratuita
    $transaction_id = 'FREE_' . uniqid() . '_' . bin2hex(random_bytes(4));
    $checkout_session_uuid = 'free_' . uniqid() . bin2hex(random_bytes(8));
    
    // UTMs
    $utm_parameters = $data['utm_parameters'] ?? [];
    $utm_source = $utm_parameters['utm_source'] ?? null;
    $utm_campaign = $utm_parameters['utm_campaign'] ?? null;
    $utm_medium = $utm_parameters['utm_medium'] ?? null;
    $utm_content = $utm_parameters['utm_content'] ?? null;
    $utm_term = $utm_parameters['utm_term'] ?? null;
    $src = $utm_parameters['src'] ?? null;
    $sck = $utm_parameters['sck'] ?? null;
    
    $pdo->beginTransaction();
    
    // community_id do produto (multi-tenant por subdomínio)
    $community_id = 1;
    try {
        $st = $pdo->prepare("SELECT COALESCE(community_id, 1) FROM produtos WHERE id = ?");
        $st->execute([$product_id]);
        if ($row = $st->fetchColumn()) $community_id = (int)$row;
    } catch (PDOException $e) {}

    // Insere a venda com status approved (já que é grátis)
    $stmt_insert = $pdo->prepare("
        INSERT INTO vendas (
            produto_id, community_id, oferta_id, comprador_nome, comprador_email, comprador_cpf, 
            comprador_telefone, valor, status_pagamento, transacao_id, metodo_pagamento, 
            checkout_session_uuid, email_entrega_enviado,
            utm_source, utm_campaign, utm_medium, utm_content, utm_term, src, sck
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'approved', ?, 'Grátis', ?, 0, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt_insert->execute([
        $product_id, $community_id, $oferta_id, $name, $email, $cpf, $phone,
        $transaction_id, $checkout_session_uuid,
        $utm_source, $utm_campaign, $utm_medium, $utm_content, $utm_term, $src, $sck
    ]);
    
    $venda_id = $pdo->lastInsertId();
    
    $pdo->commit();
    
    log_free("Venda gratuita criada: ID=$venda_id, Produto=$product_name, Email=$email");
    
    // Dispara UTMfy se disponível
    if (function_exists('trigger_utmfy_integrations')) {
        $event_data_utmfy = [
            'transacao_id' => $transaction_id,
            'valor_total_compra' => 0,
            'comprador' => [
                'nome' => $name,
                'email' => $email,
                'telefone' => $phone,
                'cpf' => $cpf
            ],
            'metodo_pagamento' => 'Grátis',
            'produtos_comprados' => [[
                'produto_id' => $product_id,
                'nome' => $product_name,
                'valor' => 0
            ]],
            'utm_parameters' => $utm_parameters,
            'data_venda' => date('Y-m-d H:i:s')
        ];
        trigger_utmfy_integrations($usuario_id, $event_data_utmfy, 'approved', $product_id);
    }
    
    // Dispara Evolution API (WhatsApp) se disponível
    if (function_exists('process_evolution_messages')) {
        $sale_data_evolution = [
            'id' => $venda_id,
            'produto_id' => $product_id,
            'comprador_nome' => $name,
            'comprador_email' => $email,
            'comprador_telefone' => $phone,
            'valor' => 0,
            'transacao_id' => $transaction_id,
            'data_venda' => date('Y-m-d H:i:s')
        ];
        process_evolution_messages($pdo, $sale_data_evolution, 'approved');
        log_free("Evolution API: Disparo para evento 'approved' - Produto Grátis");
    }
    
    // URL de redirecionamento (sempre HTTPS em produção)
    $domainName = $_SERVER['HTTP_HOST'];
    // Força HTTPS se estiver atrás de proxy/load balancer ou se X-Forwarded-Proto indicar https
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $is_https ? 'https://' : 'https://'; // Força HTTPS sempre
    $base_url = $protocol . $domainName . '/';
    $default_redirect = $base_url . 'obrigado?payment_id=' . urlencode($transaction_id);
    $checkout_config = json_decode($produto['checkout_config'] ?? '{}', true);
    if (!empty($checkout_config['redirectUrl'])) {
        $default_redirect = rtrim($checkout_config['redirectUrl'], '?&') . '?payment_id=' . urlencode($transaction_id);
    }
    if (file_exists(__DIR__ . '/helpers/funnel_helper.php')) {
        require_once __DIR__ . '/helpers/funnel_helper.php';
        $redirect_url = build_final_redirect_url($pdo, (int)$product_id, $transaction_id, $default_redirect, $base_url);
    } else {
        $redirect_url = $default_redirect;
    }
    
    returnJsonSuccess([
        'status' => 'approved',
        'payment_id' => $transaction_id,
        'redirect_url' => $redirect_url,
        'message' => 'Acesso liberado com sucesso!'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_free("Erro PDO: " . $e->getMessage());
    returnJsonError('Erro ao processar: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_free("Erro: " . $e->getMessage());
    returnJsonError('Erro ao processar: ' . $e->getMessage(), 500);
}
