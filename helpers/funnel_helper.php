<?php
/**
 * Funil de vendas (Upsell/Downsell): define para onde redirecionar após pagamento aprovado.
 *
 * Fluxo (Etapa 0):
 * - Pagamento aprovado -> redirect para /funnel_offer.php?payment_id=TRANSACTION_ID&step=upsell
 *   quando existir funil ativo (product_funnels.is_active=1) e upsell_product_id configurado.
 * - Se não tiver funil ou upsell -> redirect para /obrigado?payment_id=... (default).
 *
 * Se o produto principal tiver funil ativo com upsell, retorna URL da página de oferta (upsell).
 * Caso contrário retorna null para usar o redirecionamento padrão (obrigado ou redirectUrl).
 *
 * @param PDO $pdo
 * @param int $main_product_id ID do produto que foi comprado
 * @param string $payment_id transacao_id (payment_id) da venda aprovada
 * @param string $base_url URL base do site (ex: https://dominio.com/)
 * @return string|null URL completa para funnel_offer?payment_id=X&step=upsell ou null
 */
function get_funnel_redirect_url_after_approval($pdo, $main_product_id, $payment_id, $base_url) {
    $base_url = rtrim($base_url, '/');
    try {
        $stmt = $pdo->prepare("
            SELECT pf.upsell_product_id, pf.downsell_product_id
            FROM product_funnels pf
            WHERE pf.main_product_id = ? AND pf.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$main_product_id]);
        $funnel = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$funnel || empty($funnel['upsell_product_id'])) {
            return null;
        }
        return $base_url . '/funnel_offer.php?payment_id=' . urlencode($payment_id) . '&step=upsell';
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Retorna a URL final de redirecionamento após pagamento aprovado:
 * se houver funil ativo com upsell, retorna a página de oferta; senão retorna a URL padrão (obrigado).
 *
 * @param PDO $pdo
 * @param int $main_product_id
 * @param string $payment_id
 * @param string $default_redirect_url URL completa padrão (ex: https://site.com/obrigado?payment_id=xxx)
 * @param string $base_url URL base do site (ex: https://site.com/)
 * @return string
 */
function build_final_redirect_url($pdo, $main_product_id, $payment_id, $default_redirect_url, $base_url) {
    $funnel_url = get_funnel_redirect_url_after_approval($pdo, $main_product_id, $payment_id, rtrim($base_url, '/'));
    return $funnel_url !== null ? $funnel_url : $default_redirect_url;
}

/**
 * Etapa 5: Redirect quando o pagamento aprovado veio do checkout do funil (upsell/downsell).
 * Retorna URL para próxima etapa do funil ou obrigado.
 *
 * @param PDO $pdo
 * @param string $funnel_main_payment_id transacao_id da compra principal
 * @param string $funnel_step 'upsell' ou 'downsell' (etapa que acabou de ser paga)
 * @param string $base_url URL base (ex: https://site.com/)
 * @param string $obrigado_url URL completa do obrigado com payment_id (ex: https://site.com/obrigado?payment_id=xxx)
 * @return string|null URL para redirect ou null se não for fluxo de funil
 */
function build_funnel_redirect_after_offer_payment($pdo, $funnel_main_payment_id, $funnel_step, $base_url, $obrigado_url) {
    $base_url = rtrim($base_url, '/');
    if ($funnel_main_payment_id === '' || !in_array($funnel_step, ['upsell', 'downsell'], true)) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT produto_id FROM vendas WHERE transacao_id = ? LIMIT 1");
        $stmt->execute([$funnel_main_payment_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $main_product_id = (int) $row['produto_id'];
        $stmt_f = $pdo->prepare("SELECT downsell_product_id FROM product_funnels WHERE main_product_id = ? AND is_active = 1 LIMIT 1");
        $stmt_f->execute([$main_product_id]);
        $funnel = $stmt_f->fetch(PDO::FETCH_ASSOC);
        if ($funnel_step === 'downsell' || !$funnel || empty($funnel['downsell_product_id'])) {
            return $base_url . '/obrigado?payment_id=' . urlencode($funnel_main_payment_id);
        }
        return $base_url . '/funnel_offer.php?payment_id=' . urlencode($funnel_main_payment_id) . '&step=downsell';
    } catch (Exception $e) {
        return null;
    }
}
