<?php
/**
 * Retorno do PayPal - Captura o pagamento e redireciona para obrigado
 * PayPal redireciona para esta URL com ?token=ORDER_ID após aprovação do usuário
 */
$config_paths = [__DIR__ . '/config/config.php', __DIR__ . '/config.php'];
foreach ($config_paths as $p) { if (file_exists($p)) { require_once $p; break; } }
require_once __DIR__ . '/gateways/paypal.php';

$token = $_GET['token'] ?? null;
if (!$token) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("SELECT v.*, p.usuario_id FROM vendas v JOIN produtos p ON v.produto_id = p.id WHERE v.transacao_id = ? AND v.metodo_pagamento LIKE '%PayPal%' LIMIT 1");
$stmt->execute([$token]);
$venda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venda) {
    header('Location: /obrigado?payment_id=' . urlencode($token));
    exit;
}

$stmt_creds = $pdo->prepare("SELECT paypal_client_id, paypal_client_secret FROM usuarios WHERE id = ?");
$stmt_creds->execute([$venda['usuario_id']]);
$creds = $stmt_creds->fetch(PDO::FETCH_ASSOC);

if (empty($creds['paypal_client_id']) || empty($creds['paypal_client_secret'])) {
    header('Location: /obrigado?payment_id=' . urlencode($token) . '&error=config');
    exit;
}

$sandbox = (strpos($creds['paypal_client_id'], 'sb') !== false || strpos($creds['paypal_client_id'], 'sandbox') !== false);
$result = capture_paypal_order($token, $creds['paypal_client_id'], $creds['paypal_client_secret'], $sandbox);

if ($result && ($result['status'] === 'COMPLETED' || $result['status'] === 'completed')) {
    $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE transacao_id = ?")->execute([$token]);
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = dirname($_SERVER['PHP_SELF'] ?? '');
$path = rtrim(str_replace('\\', '/', $script_dir), '/');
$base_url = $protocol . '://' . $host . ($path ? $path . '/' : '/');
header('Location: ' . $base_url . 'obrigado?payment_id=' . urlencode($token));
exit;
