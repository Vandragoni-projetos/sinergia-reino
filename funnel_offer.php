<?php
/**
 * Página de oferta do funil (Upsell ou Downsell).
 * Recebe: payment_id (transação da compra principal), step=upsell|downsell
 * Exibe o produto ofertado e botões de CTA e "Não" (próximo passo ou obrigado).
 */
require __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/funnel_config.php';

// Sanitização de GET (segurança)
$payment_id_raw = isset($_GET['payment_id']) ? trim((string) $_GET['payment_id']) : '';
$payment_id = $payment_id_raw !== '' ? preg_replace('/[^a-zA-Z0-9_\-]/', '', substr($payment_id_raw, 0, 128)) : '';
$payment_id = $payment_id !== '' ? $payment_id : null;
$step = isset($_GET['step']) ? strtolower(trim((string) $_GET['step'])) : 'upsell';
if (!in_array($step, ['upsell', 'downsell'], true)) {
    $step = 'upsell';
}
// Parâmetros DEV (nunca imprimir token em HTML)
$dev_mode_active = defined('FUNNEL_DEV_MODE') && FUNNEL_DEV_MODE
    && isset($_GET['dev']) && $_GET['dev'] === '1'
    && defined('FUNNEL_DEV_TOKEN') && FUNNEL_DEV_TOKEN !== ''
    && isset($_GET['token']) && hash_equals(FUNNEL_DEV_TOKEN, (string) $_GET['token']);
$autostep_delay = $dev_mode_active && isset($_GET['autostep']) && $_GET['autostep'] === '1' ? 1200 : 0;
// Etapa 6: modo teste — com test=1 e dev ativo, não exige status aprovado (permite testar com venda pendente/fake)
$test_mode_bypass_status = $dev_mode_active && isset($_GET['test']) && $_GET['test'] === '1';

$sale_details = null;
$funnel = null;
$offer_product = null;
$main_product_id = null;
$obrigado_url = null;
$next_step_url = null;
$checkout_url = null;
$error = null;
$custom = [];
$theme = [];

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $protocol . '://' . $host . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$self_path = $base . '/' . ltrim(basename($_SERVER['SCRIPT_NAME'] ?? 'funnel_offer.php'), '/');
$obrigado_base = $base . '/obrigado';

// Status considerados "aprovado" na tabela vendas (projeto SinergIA Core)
$funnel_status_approved = ['approved', 'paid', 'APROVADO', 'Paid', 'Approved'];

if (!$payment_id) {
    $error = 'Parâmetro payment_id é obrigatório.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT v.transacao_id, v.produto_id, v.community_id, v.comprador_nome, v.comprador_email, v.status_pagamento, p.nome as produto_nome
            FROM vendas v
            JOIN produtos p ON v.produto_id = p.id
            WHERE v.transacao_id = ?
            LIMIT 1
        ");
        $stmt->execute([$payment_id]);
        $sale_details = $stmt->fetch(PDO::FETCH_ASSOC);

        // Etapa 1: validações de segurança — falha = redirect obrigado (sem vazar detalhes)
        if (!$sale_details) {
            error_log('[FUNNEL] payment_id não encontrado em vendas: ' . substr($payment_id, 0, 20) . '...');
            header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id));
            exit;
        }
        $status = isset($sale_details['status_pagamento']) ? trim((string) $sale_details['status_pagamento']) : '';
        $status_ok = in_array($status, $funnel_status_approved, true)
            || in_array(strtolower($status), array_map('strtolower', $funnel_status_approved), true);
        if (!$status_ok && !$test_mode_bypass_status) {
            error_log('[FUNNEL] Venda não aprovada para funil. transacao_id=' . substr($payment_id, 0, 20) . ', status=' . $status);
            header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id));
            exit;
        }

        $main_product_id = (int) $sale_details['produto_id'];
        $stmt_f = $pdo->prepare("
            SELECT * FROM product_funnels
            WHERE main_product_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt_f->execute([$main_product_id]);
        $funnel = $stmt_f->fetch(PDO::FETCH_ASSOC);

        if (!$funnel) {
            error_log('[FUNNEL] Funil inativo ou inexistente para main_product_id=' . $main_product_id);
            header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id));
            exit;
        }

        if ($funnel && !empty($funnel['offer_theme'])) {
            $t = json_decode($funnel['offer_theme'], true);
            if (is_array($t)) $theme = $t;
        }

        $offer_product_id = $step === 'upsell' ? $funnel['upsell_product_id'] : $funnel['downsell_product_id'];
                if (!$offer_product_id) {
                    if ($step === 'upsell') {
                        $error = 'Upsell não configurado.';
                    } else {
                        $next_step_url = $obrigado_base . '?payment_id=' . urlencode($payment_id);
                    }
                } else {
                    $stmt_p = $pdo->prepare("SELECT id, nome, preco, foto, checkout_hash FROM produtos WHERE id = ?");
                    $stmt_p->execute([$offer_product_id]);
                    $offer_product = $stmt_p->fetch(PDO::FETCH_ASSOC);
                    if (!$offer_product || empty($offer_product['checkout_hash'])) {
                        $error = 'Produto da oferta não encontrado ou sem checkout.';
                    } else {
                        $checkout_url = $base . '/checkout?p=' . $offer_product['checkout_hash'];
                        if ($step === 'upsell') {
                            $next_step_url = $funnel['downsell_product_id']
                                ? $self_path . '?payment_id=' . urlencode($payment_id) . '&step=downsell'
                                : $obrigado_base . '?payment_id=' . urlencode($payment_id);
                        } else {
                            $next_step_url = $obrigado_base . '?payment_id=' . urlencode($payment_id);
                        }
                        // Personalização da oferta (banner, descrição, capa)
                        $custom = [];
                        if ($step === 'upsell' && !empty($funnel['upsell_custom_config'])) {
                            $dec = json_decode($funnel['upsell_custom_config'], true);
                            if (is_array($dec)) $custom = $dec;
                        }
                        if ($step === 'downsell' && !empty($funnel['downsell_custom_config'])) {
                            $dec = json_decode($funnel['downsell_custom_config'], true);
                            if (is_array($dec)) $custom = $dec;
                        }
                        // Etapa 2: se já decidiu (accepted/declined/skipped), não reexibir oferta
                        try {
                            $stmt_ev = $pdo->prepare("
                                SELECT decision FROM funnel_events
                                WHERE main_payment_id = ? AND step = ? AND offer_product_id = ?
                                AND decision IN ('accepted','declined','skipped')
                                LIMIT 1
                            ");
                            $stmt_ev->execute([$payment_id, $step, $offer_product_id]);
                            if ($stmt_ev->fetchColumn()) {
                                $offer_product = null;
                                error_log('[FUNNEL] Oferta já decidida, pulando para próximo step. main_payment_id=' . substr($payment_id, 0, 20) . ', step=' . $step);
                            }
                        } catch (Exception $e) {
                            error_log('[FUNNEL] funnel_events check: ' . $e->getMessage());
                        }
                        // Registrar "shown" ao exibir a oferta (UPSERT)
                        if ($offer_product) {
                            try {
                                $community_id = isset($sale_details['community_id']) ? (int) $sale_details['community_id'] : 1;
                                $stmt_ins = $pdo->prepare("
                                    INSERT INTO funnel_events (community_id, main_payment_id, step, offer_product_id, decision)
                                    VALUES (?, ?, ?, ?, 'shown')
                                    ON DUPLICATE KEY UPDATE decision = 'shown', updated_at = CURRENT_TIMESTAMP
                                ");
                                $stmt_ins->execute([$community_id, $payment_id, $step, $offer_product_id]);
                            } catch (Exception $e) {
                                error_log('[FUNNEL] funnel_events insert: ' . $e->getMessage());
                            }
                        }
                    }
                }
    } catch (Exception $e) {
        error_log('[FUNNEL] ' . $e->getMessage());
        header('Location: ' . $obrigado_base . '?payment_id=' . urlencode($payment_id ?? ''));
        exit;
    }
}

$step_label = $step === 'upsell' ? ($theme['header_label_upsell'] ?? 'Oferta especial') : ($theme['header_label_downsell'] ?? 'Última chance');
$headline = $step === 'upsell' ? ($theme['header_headline_upsell'] ?? 'Quer levar isso também?') : ($theme['header_headline_downsell'] ?? 'Última chance com desconto');
$page_title = $step_label ? $step_label . ' — ' . $headline : ($step === 'upsell' ? 'Oferta especial para você' : 'Última chance');
$obrigado_url_final = $obrigado_base . '?payment_id=' . urlencode($payment_id);
// Etapa 3: botões apontam para funnel_action.php (accept/decline) para controle e logs
$action_accept_url = $base . '/funnel_action.php?payment_id=' . urlencode($payment_id) . '&step=' . urlencode($step) . '&action=accept';
$action_decline_url = $base . '/funnel_action.php?payment_id=' . urlencode($payment_id) . '&step=' . urlencode($step) . '&action=decline';
$theme_primary = $theme['primary_color'] ?? '#6366f1';
$theme_secondary = $theme['secondary_color'] ?? '#4f46e5';
$theme_page_bg = $theme['page_bg'] ?? '#f1f5f9';
$theme_logo_url = $theme['logo_url'] ?? '';
$img_src = function($path) {
    if (empty($path)) return '';
    if (strpos($path, 'http') === 0) return $path;
    return '/' . ltrim(str_replace('\\', '/', $path), '/');
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .funnel-card { box-shadow: 0 25px 50px -12px rgba(0,0,0,.15); }
        .btn-primary:hover { filter: brightness(1.05); }
        .btn-secondary:hover { background-color: #e5e7eb; }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 sm:p-6" style="background: <?php echo htmlspecialchars($theme_page_bg); ?>;">
    <div class="w-full max-w-md mx-auto">
        <?php if ($error): ?>
            <div class="bg-white rounded-2xl funnel-card p-8 text-center">
                <p class="text-slate-700 font-medium"><?php echo htmlspecialchars($error); ?></p>
                <a href="<?php echo htmlspecialchars($obrigado_base . '?payment_id=' . urlencode($payment_id ?? '')); ?>" class="inline-block mt-6 px-6 py-3 bg-emerald-600 text-white font-medium rounded-xl hover:bg-emerald-700 transition">Ir para página de obrigado</a>
            </div>
        <?php elseif (!$offer_product && $next_step_url): ?>
            <div class="bg-white rounded-2xl funnel-card p-8 text-center">
                <p class="text-slate-600 mb-6">Nenhuma oferta nesta etapa.</p>
                <a href="<?php echo htmlspecialchars($next_step_url); ?>" class="inline-block px-8 py-3 bg-slate-800 text-white font-medium rounded-xl hover:bg-slate-900 transition">Continuar para meu acesso</a>
            </div>
        <?php elseif ($offer_product):
            $cover = !empty($custom['cover_image']) ? $img_src($custom['cover_image']) : null;
            if (!$cover && !empty($offer_product['foto'])) {
                $cover = resolve_product_image_url($offer_product['foto'], 'uploads/');
                if ($cover && !filter_var($offer_product['foto'], FILTER_VALIDATE_URL) && !file_exists(__DIR__ . '/' . ltrim($cover, '/'))) {
                    $cover = null;
                }
            }
            $has_side = !empty($custom['banner_side']);
        ?>
            <div class="bg-white rounded-2xl funnel-card overflow-hidden border border-slate-200/80">
                <?php if (!empty($custom['banner_header'])): ?>
                    <div class="w-full">
                        <img src="<?php echo htmlspecialchars($img_src($custom['banner_header'])); ?>" alt="" class="w-full object-cover object-top" style="max-height: 180px;">
                    </div>
                <?php endif; ?>
                <div class="px-6 py-5 text-white text-center" style="background: linear-gradient(to right, <?php echo htmlspecialchars($theme_primary); ?>, <?php echo htmlspecialchars($theme_secondary); ?>);">
                    <?php if ($theme_logo_url): ?>
                        <img src="<?php echo htmlspecialchars($img_src($theme_logo_url)); ?>" alt="" class="mx-auto max-h-12 mb-3 object-contain">
                    <?php endif; ?>
                    <p class="text-white/90 text-sm font-medium uppercase tracking-wider"><?php echo htmlspecialchars($step_label); ?></p>
                    <h1 class="text-xl sm:text-2xl font-bold mt-1 leading-tight"><?php echo htmlspecialchars($headline); ?></h1>
                </div>
                <div class="p-6 sm:p-8 flex flex-col sm:flex-row gap-6">
                    <div class="flex-1 min-w-0">
                        <div class="flex gap-5 items-start">
                            <?php if ($cover): ?>
                                <img src="<?php echo htmlspecialchars($cover); ?>" alt="" class="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded-xl flex-shrink-0 ring-2 ring-slate-100">
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <h2 class="font-bold text-slate-900 text-lg leading-snug"><?php echo htmlspecialchars($offer_product['nome']); ?></h2>
                                <p class="text-2xl font-bold mt-2" style="color: <?php echo htmlspecialchars($theme_primary); ?>">R$ <?php echo number_format((float)$offer_product['preco'], 2, ',', '.'); ?></p>
                                <p class="text-xs text-amber-600 mt-1 font-medium">🔥 Oferta disponível somente nesta página</p>
                            </div>
                        </div>
                        <?php if (!empty($custom['description'])): ?>
                            <div class="text-slate-600 text-sm mt-5 leading-relaxed prose prose-sm max-w-none"><?php echo $custom['description']; ?></div>
                        <?php else: ?>
                            <p class="text-slate-600 text-sm mt-5 leading-relaxed">
                                <?php echo $step === 'upsell'
                                    ? 'Esta oferta está disponível só para você agora. Aproveite o melhor preço.'
                                    : 'Uma oferta alternativa com valor especial para você.'; ?>
                            </p>
                        <?php endif; ?>
                        <div class="mt-2 flex items-center gap-2 text-slate-500 text-xs">
                            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>Compra 100% segura</span>
                        </div>
                        <div class="mt-8 flex flex-col gap-3">
                            <a href="<?php echo htmlspecialchars($action_accept_url); ?>" id="funnel-cta-primary" class="btn-primary w-full inline-flex justify-center items-center px-6 py-4 text-white font-semibold rounded-xl transition" style="background: <?php echo htmlspecialchars($theme_primary); ?>; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);"<?php echo $autostep_delay > 0 ? ' data-autostep-ms="' . (int)$autostep_delay . '"' : ''; ?>>
                                🔓 Sim, quero desbloquear agora — R$ <?php echo number_format((float)$offer_product['preco'], 2, ',', '.'); ?>
                            </a>
                            <a href="<?php echo htmlspecialchars($action_decline_url); ?>" class="btn-secondary w-full inline-flex justify-center items-center px-6 py-3.5 text-slate-600 font-medium rounded-xl border border-slate-200 bg-slate-50 transition">
                                <?php echo $step === 'downsell' ? 'Não, ir para meu acesso' : 'Não agora, continuar para meu acesso'; ?>
                            </a>
                            <?php if ($step === 'upsell'): ?>
                                <p class="text-center text-xs text-slate-500 mt-1">Sem problemas — você ainda vai receber seu acesso normalmente.</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($step === 'downsell'): ?>
                            <p class="mt-4 text-center text-xs text-slate-400">Você será redirecionado para a página de confirmação e acesso ao seu produto.</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($has_side): ?>
                        <div class="sm:w-36 flex-shrink-0">
                            <img src="<?php echo htmlspecialchars($img_src($custom['banner_side'])); ?>" alt="" class="w-full rounded-lg object-cover" style="max-height: 280px;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl funnel-card p-8 text-center">
                <p class="text-slate-600">Redirecionando...</p>
                <?php if ($next_step_url): ?>
                    <script> window.location.href = <?php echo json_encode($next_step_url); ?>; </script>
                    <a href="<?php echo htmlspecialchars($next_step_url); ?>" class="inline-block mt-4 text-indigo-600 font-medium">Clique aqui se não for redirecionado</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($autostep_delay > 0): ?>
    <script>
    (function() {
        var el = document.getElementById('funnel-cta-primary');
        if (!el) return;
        var ms = parseInt(el.getAttribute('data-autostep-ms'), 10) || 1200;
        setTimeout(function() { el.click(); }, ms);
    })();
    </script>
    <noscript><p class="text-center text-sm text-slate-500 mt-2">Clique no botão acima para continuar.</p></noscript>
    <?php endif; ?>
</body>
</html>
