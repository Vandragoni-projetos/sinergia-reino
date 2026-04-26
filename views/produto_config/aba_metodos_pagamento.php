<?php
// Aba Métodos de Pagamento - Grid visual para configurar métodos por gateway

// Buscar credenciais do usuário para verificar se estão configuradas
$usuario_id = $_SESSION['id'] ?? 0;
try {
    $stmt_credenciais = $pdo->prepare("SELECT mp_access_token, pushinpay_token, efi_client_id, efi_client_secret, efi_certificate_path, efi_pix_key, efi_payee_code, beehive_secret_key, beehive_public_key, hypercash_secret_key, hypercash_public_key, pagarme_api_key, pagarme_api_secret, paypal_client_id, paypal_client_secret, stripe_publishable_key, stripe_secret_key FROM usuarios WHERE id = ?");
    $stmt_credenciais->execute([$usuario_id]);
    $credenciais = $stmt_credenciais->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback se colunas não existirem
    try {
        $stmt_credenciais = $pdo->prepare("SELECT mp_access_token, pushinpay_token FROM usuarios WHERE id = ?");
        $stmt_credenciais->execute([$usuario_id]);
        $credenciais = $stmt_credenciais->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $credenciais = ['mp_access_token' => null, 'pushinpay_token' => null];
    }
    $credenciais['efi_client_id'] = $credenciais['efi_client_id'] ?? null;
    $credenciais['efi_client_secret'] = $credenciais['efi_client_secret'] ?? null;
    $credenciais['efi_certificate_path'] = $credenciais['efi_certificate_path'] ?? null;
    $credenciais['efi_pix_key'] = $credenciais['efi_pix_key'] ?? null;
    $credenciais['efi_payee_code'] = $credenciais['efi_payee_code'] ?? null;
    $credenciais['beehive_secret_key'] = $credenciais['beehive_secret_key'] ?? null;
    $credenciais['beehive_public_key'] = $credenciais['beehive_public_key'] ?? null;
    $credenciais['hypercash_secret_key'] = $credenciais['hypercash_secret_key'] ?? null;
    $credenciais['hypercash_public_key'] = $credenciais['hypercash_public_key'] ?? null;
}

// Verificar quais gateways estão configurados
$mp_configured = !empty($credenciais['mp_access_token'] ?? '');
$pp_configured = !empty($credenciais['pushinpay_token'] ?? '');
$efi_configured = !empty($credenciais['efi_client_id'] ?? '') && !empty($credenciais['efi_client_secret'] ?? '') && !empty($credenciais['efi_certificate_path'] ?? '') && !empty($credenciais['efi_pix_key'] ?? '');
$efi_card_configured = $efi_configured && !empty($credenciais['efi_payee_code'] ?? '');
$beehive_configured = !empty($credenciais['beehive_secret_key'] ?? '') && !empty($credenciais['beehive_public_key'] ?? '');
$hypercash_configured = !empty($credenciais['hypercash_secret_key'] ?? '') && !empty($credenciais['hypercash_public_key'] ?? '');
$pagarme_configured = !empty($credenciais['pagarme_api_key'] ?? '') && !empty($credenciais['pagarme_api_secret'] ?? '');
$paypal_configured = !empty($credenciais['paypal_client_id'] ?? '') && !empty($credenciais['paypal_client_secret'] ?? '');
$stripe_configured = !empty($credenciais['stripe_publishable_key'] ?? '') && !empty($credenciais['stripe_secret_key'] ?? '');

$pushinpay_enabled = false;
$efi_enabled = false;
$mercadopago_enabled = false;
$beehive_enabled = false;
$hypercash_enabled = false;
$pagarme_enabled = false;
$paypal_enabled = false;
$stripe_enabled = false;

// Verificar quais gateways estão habilitados baseado nos métodos de pagamento
if (isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'pushinpay' && ($payment_methods_config['pix']['enabled'] ?? false)) {
    $pushinpay_enabled = true;
}
if (isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'efi' && ($payment_methods_config['pix']['enabled'] ?? false)) {
    $efi_enabled = true;
}

$credit_card_gateway = $payment_methods_config['credit_card']['gateway'] ?? null;
$mercadopago_enabled = (($payment_methods_config['credit_card']['enabled'] ?? false) && ($credit_card_gateway === 'mercadopago' || $credit_card_gateway === null)) || 
                       ($payment_methods_config['ticket']['enabled'] ?? false) || 
                       (isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'mercadopago' && ($payment_methods_config['pix']['enabled'] ?? false));

if (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'beehive' && ($payment_methods_config['credit_card']['enabled'] ?? false)) {
    $beehive_enabled = true;
}
if (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'hypercash' && ($payment_methods_config['credit_card']['enabled'] ?? false)) {
    $hypercash_enabled = true;
}
if ((isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'pagarme' && ($payment_methods_config['pix']['enabled'] ?? false)) ||
    (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'pagarme' && ($payment_methods_config['credit_card']['enabled'] ?? false)) ||
    (isset($payment_methods_config['ticket']['gateway']) && $payment_methods_config['ticket']['gateway'] === 'pagarme' && ($payment_methods_config['ticket']['enabled'] ?? false))) {
    $pagarme_enabled = true;
}
if (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'paypal' && ($payment_methods_config['credit_card']['enabled'] ?? false)) {
    $paypal_enabled = true;
}
if ((isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'stripe' && ($payment_methods_config['pix']['enabled'] ?? false)) ||
    (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'stripe' && ($payment_methods_config['credit_card']['enabled'] ?? false))) {
    $stripe_enabled = true;
}

// Se não há configuração, usar gateway do produto como padrão
if (!$pushinpay_enabled && !$efi_enabled && !$mercadopago_enabled && !$beehive_enabled && !$hypercash_enabled && !$pagarme_enabled && !$paypal_enabled && !$stripe_enabled) {
    if ($current_gateway === 'pushinpay') {
        $pushinpay_enabled = true;
    } elseif ($current_gateway === 'efi') {
        $efi_enabled = true;
    } elseif ($current_gateway === 'beehive') {
        $beehive_enabled = true;
    } elseif ($current_gateway === 'hypercash') {
        $hypercash_enabled = true;
    } elseif ($current_gateway === 'pagarme') {
        $pagarme_enabled = true;
    } elseif ($current_gateway === 'paypal') {
        $paypal_enabled = true;
    } elseif ($current_gateway === 'stripe') {
        $stripe_enabled = true;
    } else {
        $mercadopago_enabled = true;
    }
}
?>

<div class="space-y-6">
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="credit-card" class="w-5 h-5 text-[#32e768]"></i>
            Métodos de Pagamento
        </h2>
        
        <div class="bg-blue-900/20 border-l-4 border-blue-500 p-4 mb-6 rounded">
            <!-- <p class="text-sm text-blue-300">
                <strong class="text-blue-200">Dica:</strong> Você pode usar PushinPay, Efí ou Mercado Pago para Pix. Para Cartão de Crédito, use Mercado Pago, Efí, Beehive ou Hypercash. 
                Habilite os gateways abaixo e configure os métodos desejados.
            </p> -->
            <p class="text-sm text-blue-300">
                <strong class="text-blue-200">Dica:</strong> Para Pix: PushinPay, Efí, Pagar.me, Stripe ou Mercado Pago. Para Cartão: Mercado Pago, Efí, Pagar.me, PayPal ou Stripe. 
                Para Boleto: Mercado Pago ou Pagar.me. Configure em Integrações e habilite abaixo.
            </p>
        </div>

        <!-- Habilitar Gateways -->
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border mb-6">
            <h3 class="text-lg font-semibold text-white mb-4">Habilitar Gateways</h3>
            <div class="space-y-3">
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg <?php echo $pp_configured ? 'cursor-pointer transition-all hover:border-[#32e768]' : 'opacity-50 cursor-not-allowed'; ?>">
                    <input type="checkbox" id="gateway_pushinpay_enabled_visual" class="form-checkbox" <?php echo $pushinpay_enabled ? 'checked' : ''; ?> <?php echo !$pp_configured ? 'disabled' : ''; ?> onchange="updatePaymentMethods()">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="block text-sm font-medium text-white">PushinPay</span>
                            <?php if (!$pp_configured): ?>
                                <span class="bg-orange-900/30 text-orange-400 text-xs font-bold px-2 py-0.5 rounded">Não Configurado</span>
                                <a href="/index?pagina=integracoes" class="text-orange-400 hover:text-orange-300"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">Gateway para pagamentos via Pix</span>
                    </div>
                </label>
                
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg <?php echo $efi_configured ? 'cursor-pointer transition-all hover:border-[#32e768]' : 'opacity-50 cursor-not-allowed'; ?>">
                    <input type="checkbox" id="gateway_efi_enabled_visual" class="form-checkbox" <?php echo $efi_enabled ? 'checked' : ''; ?> <?php echo !$efi_configured ? 'disabled' : ''; ?> onchange="updatePaymentMethods()">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="block text-sm font-medium text-white">Efí</span>
                            <?php if (!$efi_configured): ?>
                                <span class="bg-orange-900/30 text-orange-400 text-xs font-bold px-2 py-0.5 rounded">Não Configurado</span>
                                <a href="/index?pagina=integracoes" class="text-orange-400 hover:text-orange-300"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">Gateway para Pix e Cartão de Crédito</span>
                    </div>
                </label>
                
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg <?php echo $mp_configured ? 'cursor-pointer transition-all hover:border-[#32e768]' : 'opacity-50 cursor-not-allowed'; ?>">
                    <input type="checkbox" id="gateway_mercadopago_enabled_visual" class="form-checkbox" <?php echo $mercadopago_enabled ? 'checked' : ''; ?> <?php echo !$mp_configured ? 'disabled' : ''; ?> onchange="updatePaymentMethods()">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="block text-sm font-medium text-white">Mercado Pago</span>
                            <?php if (!$mp_configured): ?>
                                <span class="bg-orange-900/30 text-orange-400 text-xs font-bold px-2 py-0.5 rounded">Não Configurado</span>
                                <a href="/index?pagina=integracoes" class="text-orange-400 hover:text-orange-300"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">Suporta Pix, Cartão de Crédito e Boleto</span>
                    </div>
                </label>
                
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg <?php echo $pagarme_configured ? 'cursor-pointer transition-all hover:border-[#32e768]' : 'opacity-50 cursor-not-allowed'; ?>">
                    <input type="checkbox" id="gateway_pagarme_enabled_visual" class="form-checkbox" <?php echo $pagarme_enabled ? 'checked' : ''; ?> <?php echo !$pagarme_configured ? 'disabled' : ''; ?> onchange="updatePaymentMethods()">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="block text-sm font-medium text-white">Pagar.me</span>
                            <?php if (!$pagarme_configured): ?>
                                <span class="bg-orange-900/30 text-orange-400 text-xs font-bold px-2 py-0.5 rounded">Não Configurado</span>
                                <a href="/index?pagina=integracoes" class="text-orange-400 hover:text-orange-300"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">Pix, Cartão de Crédito e Boleto</span>
                    </div>
                </label>
                
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg <?php echo $paypal_configured ? 'cursor-pointer transition-all hover:border-[#32e768]' : 'opacity-50 cursor-not-allowed'; ?>">
                    <input type="checkbox" id="gateway_paypal_enabled_visual" class="form-checkbox" <?php echo $paypal_enabled ? 'checked' : ''; ?> <?php echo !$paypal_configured ? 'disabled' : ''; ?> onchange="updatePaymentMethods()">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="block text-sm font-medium text-white">PayPal</span>
                            <?php if (!$paypal_configured): ?>
                                <span class="bg-orange-900/30 text-orange-400 text-xs font-bold px-2 py-0.5 rounded">Não Configurado</span>
                                <a href="/index?pagina=integracoes" class="text-orange-400 hover:text-orange-300"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">Cartão de Crédito e conta PayPal</span>
                    </div>
                </label>
                
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg <?php echo $stripe_configured ? 'cursor-pointer transition-all hover:border-[#32e768]' : 'opacity-50 cursor-not-allowed'; ?>">
                    <input type="checkbox" id="gateway_stripe_enabled_visual" class="form-checkbox" <?php echo $stripe_enabled ? 'checked' : ''; ?> <?php echo !$stripe_configured ? 'disabled' : ''; ?> onchange="updatePaymentMethods()">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="block text-sm font-medium text-white">Stripe</span>
                            <?php if (!$stripe_configured): ?>
                                <span class="bg-orange-900/30 text-orange-400 text-xs font-bold px-2 py-0.5 rounded">Não Configurado</span>
                                <a href="/index?pagina=integracoes" class="text-orange-400 hover:text-orange-300"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">Cartão de Crédito e Pix</span>
                    </div>
                </label>
                
                <!-- Beehive - DESABILITADO TEMPORARIAMENTE
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg <?php echo $beehive_configured ? 'cursor-pointer transition-all hover:border-[#32e768]' : 'opacity-50 cursor-not-allowed'; ?>">
                    <input type="checkbox" id="gateway_beehive_enabled_visual" class="form-checkbox" <?php echo $beehive_enabled ? 'checked' : ''; ?> <?php echo !$beehive_configured ? 'disabled' : ''; ?> onchange="updatePaymentMethods()">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="block text-sm font-medium text-white">Beehive</span>
                            <?php if (!$beehive_configured): ?>
                                <span class="bg-orange-900/30 text-orange-400 text-xs font-bold px-2 py-0.5 rounded">Não Configurado</span>
                                <a href="/index?pagina=integracoes" class="text-orange-400 hover:text-orange-300"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">Gateway para Cartão de Crédito</span>
                    </div>
                </label>
                -->
                
                <!-- Hypercash - DESABILITADO TEMPORARIAMENTE
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg <?php echo $hypercash_configured ? 'cursor-pointer transition-all hover:border-[#32e768]' : 'opacity-50 cursor-not-allowed'; ?>">
                    <input type="checkbox" id="gateway_hypercash_enabled_visual" class="form-checkbox" <?php echo $hypercash_enabled ? 'checked' : ''; ?> <?php echo !$hypercash_configured ? 'disabled' : ''; ?> onchange="updatePaymentMethods()">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="block text-sm font-medium text-white">Hypercash</span>
                            <?php if (!$hypercash_configured): ?>
                                <span class="bg-orange-900/30 text-orange-400 text-xs font-bold px-2 py-0.5 rounded">Não Configurado</span>
                                <a href="/index?pagina=integracoes" class="text-orange-400 hover:text-orange-300"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">Gateway para Cartão de Crédito</span>
                    </div>
                </label>
                -->
            </div>
        </div>

        <!-- Grid de Métodos de Pagamento -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="payment-methods-grid">
            <!-- PushinPay Card -->
            <div id="card-pushinpay" class="bg-dark-elevated p-6 rounded-lg border border-dark-border <?php echo (!$pushinpay_enabled || !$pp_configured) ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i data-lucide="zap" class="w-5 h-5 text-green-400"></i>
                        PushinPay
                    </h3>
                    <?php if ($pushinpay_enabled && $pp_configured): ?>
                        <span class="bg-green-900/30 text-green-400 text-xs font-bold px-2 py-1 rounded border border-green-500/50">Ativo</span>
                    <?php else: ?>
                        <span class="bg-gray-900/30 text-gray-400 text-xs font-bold px-2 py-1 rounded border border-gray-500/50">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-green-500">
                        <input type="checkbox" id="cb_payment_pix_pushinpay" data-target="hidden_payment_pix_pushinpay" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'pushinpay' && ($payment_methods_config['pix']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$pushinpay_enabled || !$pp_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-white">Pix</span>
                                <span class="bg-green-900/30 text-green-400 text-xs font-bold px-2 py-0.5 rounded">Aprovação Imediata</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Pagamento instantâneo via Pix</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Efí Card -->
            <div id="card-efi" class="bg-dark-elevated p-6 rounded-lg border border-dark-border <?php echo (!$efi_enabled || !$efi_configured) ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i data-lucide="wallet" class="w-5 h-5 text-purple-400"></i>
                        Efí
                    </h3>
                    <?php if ($efi_enabled && $efi_configured): ?>
                        <span class="bg-purple-900/30 text-purple-400 text-xs font-bold px-2 py-1 rounded border border-purple-500/50">Ativo</span>
                    <?php else: ?>
                        <span class="bg-gray-900/30 text-gray-400 text-xs font-bold px-2 py-1 rounded border border-gray-500/50">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-purple-500">
                        <input type="checkbox" id="cb_payment_pix_efi" data-target="hidden_payment_pix_efi" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'efi' && ($payment_methods_config['pix']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$efi_enabled || !$efi_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-white">Pix</span>
                                <span class="bg-purple-900/30 text-purple-400 text-xs font-bold px-2 py-0.5 rounded">Aprovação Imediata</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Pagamento instantâneo via Pix</p>
                        </div>
                    </label>
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-purple-500">
                        <input type="checkbox" id="cb_payment_credit_card_efi" data-target="hidden_payment_credit_card_efi" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'efi' && ($payment_methods_config['credit_card']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$efi_configured || !$efi_card_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Cartão de Crédito</span>
                            <p class="text-xs text-gray-400 mt-1">Visa, Mastercard, Elo, Amex</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Mercado Pago Card -->
            <div id="card-mercadopago" class="bg-dark-elevated p-6 rounded-lg border border-dark-border <?php echo (!$mercadopago_enabled || !$mp_configured) ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-5 h-5 text-blue-400"></i>
                        Mercado Pago
                    </h3>
                    <?php if ($mercadopago_enabled && $mp_configured): ?>
                        <span class="bg-blue-900/30 text-blue-400 text-xs font-bold px-2 py-1 rounded border border-blue-500/50">Ativo</span>
                    <?php else: ?>
                        <span class="bg-gray-900/30 text-gray-400 text-xs font-bold px-2 py-1 rounded border border-gray-500/50">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-blue-500">
                        <input type="checkbox" id="cb_payment_pix_enabled" data-target="hidden_payment_pix_enabled" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'mercadopago' && ($payment_methods_config['pix']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$mercadopago_enabled || !$mp_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Pix</span>
                            <p class="text-xs text-gray-400 mt-1">Pagamento via Pix do Mercado Pago</p>
                        </div>
                    </label>
                    
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-blue-500">
                        <input type="checkbox" id="cb_payment_credit_card_mercadopago" data-target="hidden_payment_credit_card_mercadopago" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'mercadopago' && ($payment_methods_config['credit_card']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$mercadopago_enabled || !$mp_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Cartão de Crédito</span>
                            <p class="text-xs text-gray-400 mt-1">Visa, Mastercard, Elo, etc.</p>
                        </div>
                    </label>
                    
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-blue-500">
                        <input type="checkbox" id="cb_payment_ticket_enabled" data-target="hidden_payment_ticket_enabled" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo ($payment_methods_config['ticket']['enabled'] ?? false) ? 'checked' : ''; ?>
                               <?php echo (!$mercadopago_enabled || !$mp_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Boleto</span>
                            <p class="text-xs text-gray-400 mt-1">Boleto bancário</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Pagar.me Card -->
            <div id="card-pagarme" class="bg-dark-elevated p-6 rounded-lg border border-dark-border <?php echo (!$pagarme_enabled || !$pagarme_configured) ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i data-lucide="building-2" class="w-5 h-5 text-teal-400"></i>
                        Pagar.me
                    </h3>
                    <?php if ($pagarme_enabled && $pagarme_configured): ?>
                        <span class="bg-teal-900/30 text-teal-400 text-xs font-bold px-2 py-1 rounded border border-teal-500/50">Ativo</span>
                    <?php else: ?>
                        <span class="bg-gray-900/30 text-gray-400 text-xs font-bold px-2 py-1 rounded border border-gray-500/50">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-teal-500">
                        <input type="checkbox" id="cb_payment_pix_pagarme" data-target="hidden_payment_pix_pagarme" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'pagarme' && ($payment_methods_config['pix']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$pagarme_enabled || !$pagarme_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Pix</span>
                            <p class="text-xs text-gray-400 mt-1">Pagamento instantâneo via Pix</p>
                        </div>
                    </label>
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-teal-500">
                        <input type="checkbox" id="cb_payment_credit_card_pagarme" data-target="hidden_payment_credit_card_pagarme" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'pagarme' && ($payment_methods_config['credit_card']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$pagarme_enabled || !$pagarme_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Cartão de Crédito</span>
                            <p class="text-xs text-gray-400 mt-1">Visa, Mastercard, Elo, etc.</p>
                        </div>
                    </label>
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-teal-500">
                        <input type="checkbox" id="cb_payment_ticket_pagarme" data-target="hidden_payment_ticket_pagarme" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['ticket']['gateway']) && $payment_methods_config['ticket']['gateway'] === 'pagarme' && ($payment_methods_config['ticket']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$pagarme_enabled || !$pagarme_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Boleto</span>
                            <p class="text-xs text-gray-400 mt-1">Boleto bancário</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- PayPal Card -->
            <div id="card-paypal" class="bg-dark-elevated p-6 rounded-lg border border-dark-border <?php echo (!$paypal_enabled || !$paypal_configured) ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i data-lucide="globe" class="w-5 h-5 text-blue-400"></i>
                        PayPal
                    </h3>
                    <?php if ($paypal_enabled && $paypal_configured): ?>
                        <span class="bg-blue-900/30 text-blue-400 text-xs font-bold px-2 py-1 rounded border border-blue-500/50">Ativo</span>
                    <?php else: ?>
                        <span class="bg-gray-900/30 text-gray-400 text-xs font-bold px-2 py-1 rounded border border-gray-500/50">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-blue-500">
                        <input type="checkbox" id="cb_payment_credit_card_paypal" data-target="hidden_payment_credit_card_paypal" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'paypal' && ($payment_methods_config['credit_card']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$paypal_enabled || !$paypal_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Cartão / Conta PayPal</span>
                            <p class="text-xs text-gray-400 mt-1">Visa, Mastercard ou conta PayPal</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Stripe Card -->
            <div id="card-stripe" class="bg-dark-elevated p-6 rounded-lg border border-dark-border <?php echo (!$stripe_enabled || !$stripe_configured) ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i data-lucide="diamond" class="w-5 h-5 text-indigo-400"></i>
                        Stripe
                    </h3>
                    <?php if ($stripe_enabled && $stripe_configured): ?>
                        <span class="bg-indigo-900/30 text-indigo-400 text-xs font-bold px-2 py-1 rounded border border-indigo-500/50">Ativo</span>
                    <?php else: ?>
                        <span class="bg-gray-900/30 text-gray-400 text-xs font-bold px-2 py-1 rounded border border-gray-500/50">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-indigo-500">
                        <input type="checkbox" id="cb_payment_pix_stripe" data-target="hidden_payment_pix_stripe" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['pix']['gateway']) && $payment_methods_config['pix']['gateway'] === 'stripe' && ($payment_methods_config['pix']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$stripe_enabled || !$stripe_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Pix</span>
                            <p class="text-xs text-gray-400 mt-1">Pagamento instantâneo via Pix</p>
                        </div>
                    </label>
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-indigo-500">
                        <input type="checkbox" id="cb_payment_credit_card_stripe" data-target="hidden_payment_credit_card_stripe" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'stripe' && ($payment_methods_config['credit_card']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$stripe_enabled || !$stripe_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <span class="text-sm font-medium text-white">Cartão de Crédito</span>
                            <p class="text-xs text-gray-400 mt-1">Visa, Mastercard, etc.</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Beehive Card - DESABILITADO TEMPORARIAMENTE
            <div id="card-beehive" class="bg-dark-elevated p-6 rounded-lg border border-dark-border <?php echo (!$beehive_enabled || !$beehive_configured) ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i data-lucide="hexagon" class="w-5 h-5 text-amber-400"></i>
                        Beehive
                    </h3>
                    <?php if ($beehive_enabled && $beehive_configured): ?>
                        <span class="bg-amber-900/30 text-amber-400 text-xs font-bold px-2 py-1 rounded border border-amber-500/50">Ativo</span>
                    <?php else: ?>
                        <span class="bg-gray-900/30 text-gray-400 text-xs font-bold px-2 py-1 rounded border border-gray-500/50">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-amber-500">
                        <input type="checkbox" id="cb_payment_credit_card_beehive" data-target="hidden_payment_credit_card_beehive" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'beehive' && ($payment_methods_config['credit_card']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$beehive_enabled || !$beehive_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-white">Cartão de Crédito</span>
                                <span class="bg-amber-900/30 text-amber-400 text-xs font-bold px-2 py-0.5 rounded">Aprovação Imediata</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Visa, Mastercard, Elo, etc.</p>
                        </div>
                    </label>
                </div>
            </div>
            -->

            <!-- Hypercash Card - DESABILITADO TEMPORARIAMENTE
            <div id="card-hypercash" class="bg-dark-elevated p-6 rounded-lg border border-dark-border <?php echo (!$hypercash_enabled || !$hypercash_configured) ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-5 h-5 text-indigo-400"></i>
                        Hypercash
                    </h3>
                    <?php if ($hypercash_enabled && $hypercash_configured): ?>
                        <span class="bg-indigo-900/30 text-indigo-400 text-xs font-bold px-2 py-1 rounded border border-indigo-500/50">Ativo</span>
                    <?php else: ?>
                        <span class="bg-gray-900/30 text-gray-400 text-xs font-bold px-2 py-1 rounded border border-gray-500/50">Inativo</span>
                    <?php endif; ?>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start p-3 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-indigo-500">
                        <input type="checkbox" id="cb_payment_credit_card_hypercash" data-target="hidden_payment_credit_card_hypercash" value="1" class="form-checkbox mt-1 payment-checkbox" 
                               <?php echo (isset($payment_methods_config['credit_card']['gateway']) && $payment_methods_config['credit_card']['gateway'] === 'hypercash' && ($payment_methods_config['credit_card']['enabled'] ?? false)) ? 'checked' : ''; ?>
                               <?php echo (!$hypercash_enabled || !$hypercash_configured) ? 'disabled' : ''; ?>>
                        <div class="ml-3 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-white">Cartão de Crédito</span>
                                <span class="bg-indigo-900/30 text-indigo-400 text-xs font-bold px-2 py-0.5 rounded">Aprovação Imediata</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Visa, Mastercard, Elo, etc.</p>
                        </div>
                    </label>
                </div>
            </div>
            -->
        </div>

        <!-- Desconto Pix -->
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border mt-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <i data-lucide="percent" class="w-5 h-5 text-green-400"></i>
                Desconto no Pix
            </h3>
            <p class="text-sm text-gray-400 mb-4">Ofereça um desconto especial para clientes que pagarem via Pix.</p>
            
            <div class="space-y-4">
                <label class="flex items-center p-4 bg-dark-card border border-dark-border rounded-lg cursor-pointer transition-all hover:border-green-500">
                    <input type="checkbox" name="pix_discount_enabled" value="1" class="form-checkbox" 
                           <?php echo ($payment_methods_config['pix_discount']['enabled'] ?? false) ? 'checked' : ''; ?>
                           onchange="togglePixDiscount()">
                    <div class="ml-3 flex-1">
                        <span class="block text-sm font-medium text-white">Habilitar desconto no Pix</span>
                        <span class="block text-xs text-gray-400">O desconto será aplicado automaticamente quando o cliente selecionar Pix</span>
                    </div>
                </label>
                
                <div id="pix-discount-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4 <?php echo ($payment_methods_config['pix_discount']['enabled'] ?? false) ? '' : 'hidden'; ?>">
                    <div>
                        <label class="block text-gray-300 text-sm font-semibold mb-2">Tipo de Desconto</label>
                        <select name="pix_discount_type" class="form-input">
                            <option value="percentage" <?php echo (($payment_methods_config['pix_discount']['type'] ?? 'percentage') === 'percentage') ? 'selected' : ''; ?>>Porcentagem (%)</option>
                            <option value="fixed" <?php echo (($payment_methods_config['pix_discount']['type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>Valor Fixo (R$)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-sm font-semibold mb-2">Valor do Desconto</label>
                        <input type="number" step="0.01" min="0" name="pix_discount_value" class="form-input" 
                               placeholder="Ex: 10" 
                               value="<?php echo htmlspecialchars($payment_methods_config['pix_discount']['value'] ?? ''); ?>">
                        <p class="text-xs text-gray-400 mt-1">Ex: 10 para 10% ou R$ 10,00</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumo Visual -->
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border mt-6">
            <h3 class="text-lg font-semibold text-white mb-4">Resumo da Configuração</h3>
            <div id="payment-summary" class="space-y-2 text-sm text-gray-300">
                <p>Carregando...</p>
            </div>
        </div>
        
        <!-- Campos hidden para garantir que valores sejam enviados -->
        <input type="hidden" id="hidden_gateway_pushinpay_enabled" name="gateway_pushinpay_enabled" value="0">
        <input type="hidden" id="hidden_gateway_efi_enabled" name="gateway_efi_enabled" value="0">
        <input type="hidden" id="hidden_gateway_mercadopago_enabled" name="gateway_mercadopago_enabled" value="0">
        <input type="hidden" id="hidden_gateway_pagarme_enabled" name="gateway_pagarme_enabled" value="0">
        <input type="hidden" id="hidden_gateway_paypal_enabled" name="gateway_paypal_enabled" value="0">
        <input type="hidden" id="hidden_gateway_stripe_enabled" name="gateway_stripe_enabled" value="0">
        <input type="hidden" id="hidden_gateway_beehive_enabled" name="gateway_beehive_enabled" value="0">
        <input type="hidden" id="hidden_gateway_hypercash_enabled" name="gateway_hypercash_enabled" value="0">
        <input type="hidden" id="hidden_payment_pix_pushinpay" name="payment_pix_pushinpay" value="0">
        <input type="hidden" id="hidden_payment_pix_pagarme" name="payment_pix_pagarme" value="0">
        <input type="hidden" id="hidden_payment_pix_stripe" name="payment_pix_stripe" value="0">
        <input type="hidden" id="hidden_payment_pix_efi" name="payment_pix_efi" value="0">
        <input type="hidden" id="hidden_payment_pix_enabled" name="payment_pix_enabled" value="0">
        <input type="hidden" id="hidden_payment_credit_card_mercadopago" name="payment_credit_card_mercadopago" value="0">
        <input type="hidden" id="hidden_payment_credit_card_efi" name="payment_credit_card_efi" value="0">
        <input type="hidden" id="hidden_payment_credit_card_pagarme" name="payment_credit_card_pagarme" value="0">
        <input type="hidden" id="hidden_payment_credit_card_paypal" name="payment_credit_card_paypal" value="0">
        <input type="hidden" id="hidden_payment_credit_card_stripe" name="payment_credit_card_stripe" value="0">
        <input type="hidden" id="hidden_payment_credit_card_beehive" name="payment_credit_card_beehive" value="0">
        <input type="hidden" id="hidden_payment_credit_card_hypercash" name="payment_credit_card_hypercash" value="0">
        <input type="hidden" id="hidden_payment_ticket_enabled" name="payment_ticket_enabled" value="0">
        <input type="hidden" id="hidden_payment_ticket_pagarme" name="payment_ticket_pagarme" value="0">
    </div>
</div>

<script>
function togglePixDiscount() {
    const enabled = document.querySelector('input[name="pix_discount_enabled"]')?.checked || false;
    document.getElementById('pix-discount-fields')?.classList.toggle('hidden', !enabled);
    updateSummary();
}

function syncAllHiddenFields() {
    // Sincronizar gateways
    ['pushinpay', 'efi', 'mercadopago', 'pagarme', 'paypal', 'stripe', 'beehive', 'hypercash'].forEach(gw => {
        const cb = document.getElementById(`gateway_${gw}_enabled_visual`);
        const hid = document.getElementById(`hidden_gateway_${gw}_enabled`);
        if (cb && hid) {
            hid.value = (cb.checked && !cb.disabled) ? '1' : '0';
        }
    });
    
    // Sincronizar métodos de pagamento usando data-target
    document.querySelectorAll('.payment-checkbox').forEach(cb => {
        const targetId = cb.getAttribute('data-target');
        const hid = document.getElementById(targetId);
        if (hid) {
            hid.value = (cb.checked && !cb.disabled) ? '1' : '0';
        }
    });
}

function updatePaymentMethods() {
    ['pushinpay', 'efi', 'mercadopago', 'pagarme', 'paypal', 'stripe', 'beehive', 'hypercash'].forEach(gw => {
        const gwCheckbox = document.getElementById(`gateway_${gw}_enabled_visual`);
        const card = document.getElementById(`card-${gw}`);
        
        if (!gwCheckbox || !card) return;
        
        const isGatewayEnabled = gwCheckbox.checked && !gwCheckbox.disabled;
        
        // Atualizar visual do card
        card.classList.toggle('opacity-50', !isGatewayEnabled);
        
        // Habilitar/desabilitar checkboxes de pagamento dentro do card
        card.querySelectorAll('.payment-checkbox').forEach(cb => {
            if (!gwCheckbox.disabled) {
                cb.disabled = !isGatewayEnabled;
                if (!isGatewayEnabled) {
                    cb.checked = false;
                }
            }
        });
    });
    
    syncAllHiddenFields();
    updateSummary();
}

function handlePixExclusivity(changedInput) {
    if (!changedInput.checked) {
        syncAllHiddenFields();
        updateSummary();
        return;
    }
    
    ['cb_payment_pix_pushinpay', 'cb_payment_pix_efi', 'cb_payment_pix_pagarme', 'cb_payment_pix_stripe', 'cb_payment_pix_enabled'].forEach(id => {
        const cb = document.getElementById(id);
        if (cb && cb !== changedInput) {
            cb.checked = false;
        }
    });
    syncAllHiddenFields();
    updateSummary();
}

function handleCreditCardExclusivity(changedInput) {
    if (!changedInput.checked) {
        syncAllHiddenFields();
        updateSummary();
        return;
    }
    
    ['cb_payment_credit_card_mercadopago', 'cb_payment_credit_card_efi', 'cb_payment_credit_card_pagarme', 'cb_payment_credit_card_paypal', 'cb_payment_credit_card_stripe', 'cb_payment_credit_card_beehive', 'cb_payment_credit_card_hypercash'].forEach(id => {
        const cb = document.getElementById(id);
        if (cb && cb !== changedInput) {
            cb.checked = false;
        }
    });
    syncAllHiddenFields();
    updateSummary();
}

function updateSummary() {
    const summary = document.getElementById('payment-summary');
    if (!summary) return;
    
    let html = '';
    
    const check = (id) => {
        const cb = document.getElementById(id);
        return cb?.checked && !cb?.disabled;
    };
    
    if (check('cb_payment_pix_pushinpay')) html += '<p class="text-green-400">✓ Pix via <strong>PushinPay</strong></p>';
    if (check('cb_payment_pix_efi')) html += '<p class="text-purple-400">✓ Pix via <strong>Efí</strong></p>';
    if (check('cb_payment_pix_enabled')) html += '<p class="text-blue-400">✓ Pix via <strong>Mercado Pago</strong></p>';
    if (check('cb_payment_pix_pagarme')) html += '<p class="text-teal-400">✓ Pix via <strong>Pagar.me</strong></p>';
    if (check('cb_payment_pix_stripe')) html += '<p class="text-indigo-400">✓ Pix via <strong>Stripe</strong></p>';
    if (check('cb_payment_credit_card_mercadopago')) html += '<p class="text-blue-400">✓ Cartão via <strong>Mercado Pago</strong></p>';
    if (check('cb_payment_credit_card_efi')) html += '<p class="text-purple-400">✓ Cartão via <strong>Efí</strong></p>';
    if (check('cb_payment_credit_card_pagarme')) html += '<p class="text-teal-400">✓ Cartão via <strong>Pagar.me</strong></p>';
    if (check('cb_payment_credit_card_paypal')) html += '<p class="text-blue-400">✓ Cartão via <strong>PayPal</strong></p>';
    if (check('cb_payment_credit_card_stripe')) html += '<p class="text-indigo-400">✓ Cartão via <strong>Stripe</strong></p>';
    if (check('cb_payment_credit_card_beehive')) html += '<p class="text-amber-400">✓ Cartão via <strong>Beehive</strong></p>';
    if (check('cb_payment_credit_card_hypercash')) html += '<p class="text-indigo-400">✓ Cartão via <strong>Hypercash</strong></p>';
    if (check('cb_payment_ticket_enabled')) html += '<p class="text-blue-400">✓ Boleto via <strong>Mercado Pago</strong></p>';
    if (check('cb_payment_ticket_pagarme')) html += '<p class="text-teal-400">✓ Boleto via <strong>Pagar.me</strong></p>';
    
    const pixDiscountEnabled = document.querySelector('input[name="pix_discount_enabled"]')?.checked;
    const pixDiscountValue = document.querySelector('input[name="pix_discount_value"]')?.value;
    const pixDiscountType = document.querySelector('select[name="pix_discount_type"]')?.value;
    const hasPix = check('cb_payment_pix_pushinpay') || check('cb_payment_pix_efi') || check('cb_payment_pix_enabled') || check('cb_payment_pix_pagarme') || check('cb_payment_pix_stripe');
    
    if (pixDiscountEnabled && pixDiscountValue && hasPix) {
        const discountText = pixDiscountType === 'percentage' ? `${pixDiscountValue}%` : `R$ ${pixDiscountValue}`;
        html += `<p class="text-yellow-400">💰 Desconto no Pix: <strong>${discountText}</strong></p>`;
    }
    
    summary.innerHTML = html || '<p class="text-gray-400">Nenhum método de pagamento habilitado</p>';
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    // Listeners para gateways
    ['pushinpay', 'efi', 'mercadopago', 'pagarme', 'paypal', 'stripe', 'beehive', 'hypercash'].forEach(gw => {
        const cb = document.getElementById(`gateway_${gw}_enabled_visual`);
        if (cb) cb.addEventListener('change', updatePaymentMethods);
    });
    
    // Listeners para Pix (exclusividade)
    ['cb_payment_pix_pushinpay', 'cb_payment_pix_efi', 'cb_payment_pix_enabled', 'cb_payment_pix_pagarme', 'cb_payment_pix_stripe'].forEach(id => {
        const cb = document.getElementById(id);
        if (cb) cb.addEventListener('change', function() { handlePixExclusivity(this); });
    });
    
    // Listeners para Cartão (exclusividade)
    ['cb_payment_credit_card_mercadopago', 'cb_payment_credit_card_efi', 'cb_payment_credit_card_pagarme', 'cb_payment_credit_card_paypal', 'cb_payment_credit_card_stripe', 'cb_payment_credit_card_beehive', 'cb_payment_credit_card_hypercash'].forEach(id => {
        const cb = document.getElementById(id);
        if (cb) cb.addEventListener('change', function() { handleCreditCardExclusivity(this); });
    });
    
    // Listeners para boleto
    const ticketCb = document.getElementById('cb_payment_ticket_enabled');
    if (ticketCb) ticketCb.addEventListener('change', () => { syncAllHiddenFields(); updateSummary(); });
    const ticketPagarmeCb = document.getElementById('cb_payment_ticket_pagarme');
    if (ticketPagarmeCb) ticketPagarmeCb.addEventListener('change', () => { syncAllHiddenFields(); updateSummary(); });
    
    // Interceptar submit do form
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            syncAllHiddenFields();
        });
    }
    
    // Inicializar estado
    syncAllHiddenFields();
    updatePaymentMethods();
});
</script>
