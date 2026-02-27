<?php
/**
 * Etapa 3: Processa aceitar ou recusar oferta do funil.
 * GET: payment_id, step, action=accept|decline
 * Valida igual funnel_offer; registra decisão em funnel_events; redirect.
 */
require __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/funnel_config.php';

$payment_id_raw = isset($_GET['payment_id']) ? trim((string) $_GET['payment_id']) : '';
$payment_id = $payment_id_raw !== '' ? preg_replace('/[^a-zA-Z0-9_\-]/', '', substr($payment_id_raw, 0, 128)) : '';
$payment_id = $payment_id !== '' ? $payment_id : null;
$step = isset($_GET['step']) ? strtolower(trim((string) $_GET['step'])) : 'upsell';
if (!in_array($step, ['upsell', 'downsell'], true)) $step = 'upsell';
$action = isset($_GET['action']) ? strtolower(trim((string) $_GET['action'])) : '';
if (!in_array($action, ['accept', 'decline'], true)) $action = '';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $protocol . '://' . $host . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$obrigado_base = $base . '/obrigado';
$funnel_offer_path = $base . '/funnel_offer.php';

$funnel_status_approved = ['approved', 'paid', 'APROVADO', 'Paid', 'Approved'];

if (!$payment_id || !$action) {
    error_log('[FUNNEL] funnel_action: payment_id ou action ausente');
    header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id ?? ''));
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT v.transacao_id, v.produto_id, v.community_id, v.comprador_nome, v.comprador_email, v.comprador_cpf, v.comprador_telefone, v.status_pagamento
        FROM vendas v
        WHERE v.transacao_id = ?
        LIMIT 1
    ");
    $stmt->execute([$payment_id]);
    $sale_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale_details) {
        error_log('[FUNNEL] funnel_action: payment_id não encontrado');
        header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id));
        exit;
    }

    $status = isset($sale_details['status_pagamento']) ? trim((string) $sale_details['status_pagamento']) : '';
    $status_ok = in_array($status, $funnel_status_approved, true)
        || in_array(strtolower($status), array_map('strtolower', $funnel_status_approved), true);
    if (!$status_ok) {
        error_log('[FUNNEL] funnel_action: venda não aprovada');
        header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id));
        exit;
    }

    $main_product_id = (int) $sale_details['produto_id'];
    $stmt_f = $pdo->prepare("SELECT * FROM product_funnels WHERE main_product_id = ? AND is_active = 1 LIMIT 1");
    $stmt_f->execute([$main_product_id]);
    $funnel = $stmt_f->fetch(PDO::FETCH_ASSOC);

    if (!$funnel) {
        error_log('[FUNNEL] funnel_action: funil não encontrado');
        header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id));
        exit;
    }

    $offer_product_id = $step === 'upsell' ? $funnel['upsell_product_id'] : $funnel['downsell_product_id'];
    if (!$offer_product_id) {
        header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id));
        exit;
    }

    $stmt_p = $pdo->prepare("SELECT id, nome, preco, checkout_hash FROM produtos WHERE id = ?");
    $stmt_p->execute([$offer_product_id]);
    $offer_product = $stmt_p->fetch(PDO::FETCH_ASSOC);
    if (!$offer_product || empty($offer_product['checkout_hash'])) {
        header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id));
        exit;
    }

    $community_id = isset($sale_details['community_id']) ? (int) $sale_details['community_id'] : 1;
    $next_step_url = $step === 'upsell'
        ? ($funnel['downsell_product_id'] ? $funnel_offer_path . '?payment_id=' . urlencode($payment_id) . '&step=downsell' : $obrigado_base . '?payment_id=' . urlencode($payment_id))
        : $obrigado_base . '?payment_id=' . urlencode($payment_id);

    try {
        $stmt_up = $pdo->prepare("
            INSERT INTO funnel_events (community_id, main_payment_id, step, offer_product_id, decision)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE decision = VALUES(decision), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt_up->execute([$community_id, $payment_id, $step, $offer_product_id, $action === 'accept' ? 'accepted' : 'declined']);
    } catch (Exception $e) {
        error_log('[FUNNEL] funnel_action funnel_events: ' . $e->getMessage());
    }

    if ($action === 'decline') {
        header('Location: ' . $next_step_url);
        exit;
    }

    // action === accept: redirecionar para checkout (Etapa 4: com prefill_token)
    $checkout_url = $base . '/checkout?p=' . $offer_product['checkout_hash'] . '&funnel_main=' . urlencode($payment_id) . '&funnel_step=' . urlencode($step);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $prefill_token = bin2hex(random_bytes(16));
    $_SESSION['checkout_prefill'][$prefill_token] = [
        'nome'       => $sale_details['comprador_nome'] ?? '',
        'email'      => $sale_details['comprador_email'] ?? '',
        'telefone'   => $sale_details['comprador_telefone'] ?? '',
        'cpf'        => $sale_details['comprador_cpf'] ?? '',
        'main_payment_id' => $payment_id,
        'step'       => $step,
        'offer_product_id' => $offer_product_id,
        'community_id' => $community_id,
        'created_at' => time(),
    ];
    $checkout_url .= '&prefill_token=' . $prefill_token;

    header('Location: ' . $checkout_url);
    exit;

} catch (Exception $e) {
    error_log('[FUNNEL] funnel_action: ' . $e->getMessage());
    header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id ?? ''));
    exit;
}
