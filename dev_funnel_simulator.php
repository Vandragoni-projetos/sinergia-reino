<?php
/**
 * Simulador de venda para testar o funil (Upsell/Downsell) sem pagamento real.
 * Só funciona com FUNNEL_DEV_MODE=true e token correto na URL.
 * Uso: dev_funnel_simulator.php?main_product_id=67&token=SEU_TOKEN&autostep=1 (opcional)
 */
require __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/funnel_config.php';

$token_sent = isset($_GET['token']) ? (string) $_GET['token'] : '';
$dev_ok = defined('FUNNEL_DEV_MODE') && FUNNEL_DEV_MODE
    && defined('FUNNEL_DEV_TOKEN') && FUNNEL_DEV_TOKEN !== ''
    && hash_equals(FUNNEL_DEV_TOKEN, $token_sent);

if (!$dev_ok) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Simulador desativado</title></head><body>';
    echo '<p><strong>Simulador de funil não disponível.</strong></p>';
    echo '<p>Ative FUNNEL_DEV_MODE e defina FUNNEL_DEV_TOKEN no .env e use a URL com <code>token=</code> correto.</p>';
    echo '</body></html>';
    exit;
}

$main_product_id = isset($_GET['main_product_id']) ? (int) $_GET['main_product_id'] : 0;
if ($main_product_id <= 0) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Parâmetro obrigatório</title></head><body>';
    echo '<p><strong>Parâmetro obrigatório:</strong> <code>main_product_id</code> (ID do produto principal do funil).</p>';
    echo '<p>Exemplo: dev_funnel_simulator.php?main_product_id=67&token=SEU_TOKEN</p>';
    echo '</body></html>';
    exit;
}

$autostep = isset($_GET['autostep']) && $_GET['autostep'] === '1' ? '1' : '0';

try {
    $pdo->beginTransaction();

    $community_id = 1;
    $stmt_c = $pdo->prepare("SELECT COALESCE(community_id, 1) FROM produtos WHERE id = ? LIMIT 1");
    $stmt_c->execute([$main_product_id]);
    $cid = $stmt_c->fetchColumn();
    if ($cid !== false && $cid !== null) {
        $community_id = (int) $cid ?: 1;
    }

    $transacao_id = 'DEV-' . uniqid('', true);
    $comprador_nome = 'Teste Dev Funil';
    $comprador_email = 'dev-funnel@teste.local';

    $stmt = $pdo->prepare("
        INSERT INTO vendas (
            produto_id, community_id, oferta_id, comprador_nome, comprador_email, comprador_cpf,
            comprador_telefone, valor, status_pagamento, transacao_id, metodo_pagamento,
            checkout_session_uuid, email_entrega_enviado,
            utm_source, utm_campaign, utm_medium, utm_content, utm_term, src, sck
        ) VALUES (?, ?, NULL, ?, ?, '', '', 0, 'approved', ?, 'Simulador DEV', '', 0, '', '', '', '', '', '', '')
    ");
    $stmt->execute([
        $main_product_id,
        $community_id,
        $comprador_nome,
        $comprador_email,
        $transacao_id
    ]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Erro no simulador</title></head><body>';
    echo '<p><strong>Erro ao criar venda de teste.</strong></p>';
    echo '<p>A tabela <code>vendas</code> ou as colunas necessárias podem não existir ou ter estrutura diferente.</p>';
    echo '<p>Detalhe (não exibir em produção): ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</body></html>';
    exit;
}

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
$funnel_url = $base . '/funnel_offer.php?payment_id=' . rawurlencode($transacao_id) . '&step=upsell&dev=1&token=' . rawurlencode($token_sent);
if ($autostep === '1') {
    $funnel_url .= '&autostep=1';
}
header('Location: ' . $funnel_url, true, 302);
exit;
