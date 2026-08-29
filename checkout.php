<?php
require __DIR__ . '/config/config.php';
include __DIR__ . '/config/load_settings.php';

// i18n: ?lang=es|fr|en|pt (padrão pt)
$checkout_lang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : 'pt';
$valid_langs = ['pt', 'es', 'fr', 'en'];
if (!in_array($checkout_lang, $valid_langs, true)) $checkout_lang = 'pt';
$lang_file = __DIR__ . '/lang/' . $checkout_lang . '.php';
$fallback_file = __DIR__ . '/lang/pt.php';
$CHECKOUT_LANG_STRINGS = file_exists($lang_file) ? (require $lang_file) : [];
$CHECKOUT_LANG_FALLBACK = file_exists($fallback_file) ? (require $fallback_file) : [];
require_once __DIR__ . '/helpers/i18n_helper.php';

// Se vier payment_id, redireciona para obrigado (não confundir com funnel_main que é o payment_id da compra principal no funil)
$payment_id = $_GET['payment_id'] ?? null;
if ($payment_id) {
    header('Location: /obrigado?payment_id=' . urlencode($payment_id));
    exit;
}

$checkout_hash = $_GET['p'] ?? null;
// Etapa 4: parâmetros do funil (prefill + redirect pós-pagamento)
$prefill_token_raw = isset($_GET['prefill_token']) ? trim((string) $_GET['prefill_token']) : '';
$prefill_token = preg_match('/^[a-f0-9]{32}$/', $prefill_token_raw) ? $prefill_token_raw : null;
$funnel_main_get = isset($_GET['funnel_main']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', substr(trim((string) $_GET['funnel_main']), 0, 128)) : '';
$funnel_step_get = isset($_GET['funnel_step']) ? strtolower(trim((string) $_GET['funnel_step'])) : '';
if (!in_array($funnel_step_get, ['upsell', 'downsell'], true)) $funnel_step_get = '';
$prefill_name = '';
$prefill_email = '';
$prefill_phone = '';
$prefill_cpf = '';
$funnel_main_payment_id = $funnel_main_get !== '' ? $funnel_main_get : null;
$funnel_step_param = $funnel_step_get !== '' ? $funnel_step_get : null;
$oferta_hash = $_GET['oferta'] ?? null;

if (!$checkout_hash) {
    die("Produto não encontrado.");
}

try {
    $community_id = function_exists('getCommunityId') ? getCommunityId() : 1;
    // Checkout é acessado por hash único: não filtrar por comunidade para o link funcionar em qualquer subdomínio (core/club)
    $stmt_prod = $pdo->prepare("SELECT * FROM produtos WHERE checkout_hash = ?");
    $stmt_prod->execute([$checkout_hash]);
    $produto = $stmt_prod->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        die("Produto inválido ou não existe mais.");
    }
    
    // Verifica se o produto é gratuito
    $is_free_product = !empty($produto['is_free']) && $produto['is_free'] == 1;
    
    // Verificar se há uma oferta específica
    $oferta_ativa = null;
    $preco_original = $produto['preco'];
    $tipo_acesso = 'vitalicio'; // Padrão é vitalício
    
    if ($oferta_hash) {
        try {
            $stmt_oferta = $pdo->prepare("SELECT * FROM produto_ofertas WHERE hash = ? AND produto_id = ? AND ativo = 1");
            $stmt_oferta->execute([$oferta_hash, $produto['id']]);
            $oferta_ativa = $stmt_oferta->fetch(PDO::FETCH_ASSOC);
            
            if ($oferta_ativa) {
                // Usar o preço da oferta
                $produto['preco'] = $oferta_ativa['preco'];
                $produto['oferta_id'] = $oferta_ativa['id'];
                $produto['oferta_nome'] = $oferta_ativa['nome'];
                $tipo_acesso = $oferta_ativa['tipo_acesso'] ?? 'vitalicio';
            }
        } catch (PDOException $e) {
            // Tabela pode não existir ainda, ignorar
        }
    }
    
    // Labels e cores para o tipo de acesso (i18n)
    $tipo_acesso_labels = [
        'vitalicio' => ['label' => checkout_t('access_lifetime'), 'color' => 'bg-green-100 text-green-700'],
        'mensal' => ['label' => checkout_t('access_monthly'), 'color' => 'bg-blue-100 text-blue-700'],
        'semestral' => ['label' => checkout_t('access_semiannual'), 'color' => 'bg-purple-100 text-purple-700'],
        'anual' => ['label' => checkout_t('access_annual'), 'color' => 'bg-yellow-100 text-yellow-700']
    ];
    $tipo_acesso_info = $tipo_acesso_labels[$tipo_acesso] ?? $tipo_acesso_labels['vitalicio'];
    
    // Define o gateway (padrão mercadopago se estiver vazio)
    $gateway = $produto['gateway'] ?? 'mercadopago';

    // 2. Busca os order bumps
    $stmt_ob = $pdo->prepare("
        SELECT 
            ob.*, 
            p.id as ob_id,
            p.nome as ob_nome, 
            p.preco as ob_preco,
            p.preco_order_bump as ob_preco_order_bump,
            p.preco_anterior as ob_preco_anterior,
            p.foto as ob_foto 
        FROM order_bumps as ob
        JOIN produtos as p ON ob.offer_product_id = p.id
        WHERE ob.main_product_id = ? AND ob.is_active = 1
        ORDER BY ob.ordem ASC
    ");
    $stmt_ob->execute([$produto['id']]);
    $order_bumps = $stmt_ob->fetchAll(PDO::FETCH_ASSOC);

    // Preço efetivo no contexto Order Bump: preco_order_bump > 0 ? preco_order_bump : preco
    foreach ($order_bumps as &$bump_row) {
        $ob_preco_normal = floatval($bump_row['ob_preco'] ?? 0);
        $ob_preco_secundario = isset($bump_row['ob_preco_order_bump']) && $bump_row['ob_preco_order_bump'] !== null && $bump_row['ob_preco_order_bump'] !== ''
            ? floatval($bump_row['ob_preco_order_bump'])
            : 0.0;
        $bump_row['preco_efetivo_order_bump'] = ($ob_preco_secundario > 0) ? $ob_preco_secundario : $ob_preco_normal;
    }
    unset($bump_row);

    $checkout_config = json_decode($produto['checkout_config'] ?? '{}', true);
    if (!is_array($checkout_config)) { $checkout_config = []; }

    // Verifica se o link principal está desativado
    $disable_main_link = $checkout_config['disable_main_link'] ?? false;
    if ($disable_main_link && !$oferta_hash) {
        die("<div style='font-family: sans-serif; text-align: center; padding: 50px;'><h1 style='color: #ef4444;'>Link Expirado ou Inativo</h1><p style='color: #6b7280;'>Este link de checkout principal foi desativado pelo vendedor. Utilize um link de oferta específico.</p></div>");
    }

    // LÓGICA DE RASTREAMENTO
    $tracking_config = $checkout_config['tracking'] ?? [];
    if (empty($tracking_config['facebookPixelId']) && !empty($checkout_config['facebookPixelId'])) {
        $tracking_config['facebookPixelId'] = $checkout_config['facebookPixelId'];
    }
    $fbPixelId = $tracking_config['facebookPixelId'] ?? '';
    $gaId = $tracking_config['googleAnalyticsId'] ?? '';
    $gAdsId = $tracking_config['googleAdsId'] ?? '';
    $tracking_events = $tracking_config['events'] ?? [];
    $fb_events_enabled = $tracking_events['facebook'] ?? [];
    $gg_events_enabled = $tracking_events['google'] ?? [];

    $infoprodutor_id = $produto['usuario_id'];
    
    // Busca o nome do vendedor e as public keys (MP, Stripe, Beehive, Hypercash e Efí)
    $stmt_vendedor = $pdo->prepare("SELECT nome, mp_public_key, stripe_publishable_key, beehive_public_key, hypercash_public_key, efi_payee_code FROM usuarios WHERE id = ?");
    $stmt_vendedor->execute([$infoprodutor_id]);
    $vendedor_data = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);
    $public_key = $vendedor_data['mp_public_key'] ?? '';
    $stripe_publishable_key = $vendedor_data['stripe_publishable_key'] ?? '';
    $beehive_public_key = $vendedor_data['beehive_public_key'] ?? '';
    $hypercash_public_key = $vendedor_data['hypercash_public_key'] ?? '';
    $efi_payee_code = $vendedor_data['efi_payee_code'] ?? '';
    $vendedor_nome = $vendedor_data['nome'] ?? 'Vendedor';

} catch (PDOException $e) {
    die("Erro de banco de dados: " . $e->getMessage());
}

// Etapa 4: prefill a partir do funil (uso único do token)
if ($prefill_token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['checkout_prefill'][$prefill_token])) {
        $data = $_SESSION['checkout_prefill'][$prefill_token];
        $prefill_name = isset($data['nome']) ? (string) $data['nome'] : '';
        $prefill_email = isset($data['email']) ? (string) $data['email'] : '';
        $prefill_phone = isset($data['telefone']) ? (string) $data['telefone'] : '';
        $prefill_cpf = isset($data['cpf']) ? (string) $data['cpf'] : '';
        unset($_SESSION['checkout_prefill'][$prefill_token]);
    }
}

$orderbump_active = !empty($order_bumps) && !$is_free_product; // Desabilita order bumps para produtos grátis

// Configurações de Estilo e Funcionalidade
$backgroundColor = $checkout_config['backgroundColor'] ?? '#E3E3E3';
$accentColor = $checkout_config['accentColor'] ?? '#7427F1';
$banners = $checkout_config['banners'] ?? [];
if (empty($banners) && !empty($checkout_config['bannerUrl'])) { $banners = [$checkout_config['bannerUrl']]; }
$sideBanners = $checkout_config['sideBanners'] ?? [];
if (empty($sideBanners) && !empty($checkout_config['sideBannerUrl'])) { $sideBanners = [$checkout_config['sideBannerUrl']]; }
$youtubeUrl = $checkout_config['youtubeUrl'] ?? null;
$timerConfig = $checkout_config['timer'] ?? ['enabled' => false, 'minutes' => 15, 'text' => 'Esta oferta expira em:', 'bgcolor' => '#000000', 'textcolor' => '#FFFFFF', 'sticky' => true];
$salesNotificationConfig = $checkout_config['salesNotification'] ?? ['enabled' => false, 'names' => '', 'product' => '', 'tempo_exibicao' => 5, 'intervalo_notificacao' => 10];
$backRedirectConfig = $checkout_config['backRedirect'] ?? ['enabled' => false, 'url' => ''];
$redirectUrlConfig = $checkout_config['redirectUrl'] ?? '';
// Ler paymentMethods com retrocompatibilidade
$payment_methods_config = $checkout_config['paymentMethods'] ?? [];
if (empty($payment_methods_config) || !isset($payment_methods_config['pix']['gateway'])) {
    // Estrutura antiga - migrar para nova estrutura (respeitar gateway do produto)
    $old_payment_methods = $checkout_config['paymentMethods'] ?? ['credit_card' => true, 'pix' => true, 'ticket' => true];
    $pix_gw = in_array($gateway, ['pushinpay', 'efi', 'stripe', 'pagarme']) ? $gateway : 'mercadopago';
    $card_gw = in_array($gateway, ['stripe', 'paypal', 'pagarme', 'beehive', 'hypercash', 'efi']) ? $gateway : 'mercadopago';
    $payment_methods_config = [
        'pix' => [
            'gateway' => $pix_gw,
            'enabled' => $old_payment_methods['pix'] ?? true
        ],
        'credit_card' => [
            'gateway' => $card_gw,
            'enabled' => $old_payment_methods['credit_card'] ?? true
        ],
        'ticket' => [
            'gateway' => 'mercadopago',
            'enabled' => $old_payment_methods['ticket'] ?? true
        ]
    ];
}

$customer_fields_config = $checkout_config['customer_fields'] ?? ['enable_cpf' => true, 'enable_phone' => true];
// Para checkouts internacionais (lang != pt), ocultar CPF automaticamente - não é usado fora do Brasil
if ($checkout_lang !== 'pt') {
    $customer_fields_config['enable_cpf'] = false;
}

// Calcular variáveis de métodos de pagamento habilitados no escopo global
// (necessário para inicialização do JavaScript do Mercado Pago, Beehive e Hypercash)
$pix_pushinpay_enabled = false;
$pix_mercadopago_enabled = false;
$pix_efi_enabled = false;
$credit_card_enabled = false;
$credit_card_beehive_enabled = false;
$credit_card_hypercash_enabled = false;
$credit_card_mercadopago_enabled = false;
$credit_card_efi_enabled = false;
$ticket_enabled = false;

$credit_card_stripe_enabled = false;
$credit_card_pagarme_enabled = false;
$credit_card_paypal_enabled = false;
$pix_stripe_enabled = false;
$pix_pagarme_enabled = false;

// Ler nova estrutura com gateway por método
if (isset($payment_methods_config['pix']['gateway'])) {
    if ($payment_methods_config['pix']['gateway'] === 'pushinpay' && ($payment_methods_config['pix']['enabled'] ?? false)) {
        $pix_pushinpay_enabled = true;
    } elseif ($payment_methods_config['pix']['gateway'] === 'mercadopago' && ($payment_methods_config['pix']['enabled'] ?? false)) {
        $pix_mercadopago_enabled = true;
    } elseif ($payment_methods_config['pix']['gateway'] === 'efi' && ($payment_methods_config['pix']['enabled'] ?? false)) {
        $pix_efi_enabled = true;
    } elseif ($payment_methods_config['pix']['gateway'] === 'stripe' && ($payment_methods_config['pix']['enabled'] ?? false)) {
        $pix_stripe_enabled = true;
    } elseif ($payment_methods_config['pix']['gateway'] === 'pagarme' && ($payment_methods_config['pix']['enabled'] ?? false)) {
        $pix_pagarme_enabled = true;
    }
}
if (isset($payment_methods_config['credit_card']['enabled']) && $payment_methods_config['credit_card']['enabled']) {
    $credit_card_enabled = true;
    // Verificar qual gateway está configurado para cartão
    $credit_card_gateway = $payment_methods_config['credit_card']['gateway'] ?? 'mercadopago';
    if ($credit_card_gateway === 'stripe') {
        $credit_card_stripe_enabled = true;
    } elseif ($credit_card_gateway === 'paypal') {
        $credit_card_paypal_enabled = true;
    } elseif ($credit_card_gateway === 'pagarme') {
        $credit_card_pagarme_enabled = true;
    } elseif ($credit_card_gateway === 'hypercash') {
        $credit_card_hypercash_enabled = true;
    } elseif ($credit_card_gateway === 'beehive') {
        $credit_card_beehive_enabled = true;
    } elseif ($credit_card_gateway === 'efi') {
        $credit_card_efi_enabled = true;
    } else {
        $credit_card_mercadopago_enabled = true;
    }
}

$ticket_pagarme_enabled = false;
if (isset($payment_methods_config['ticket']['enabled']) && $payment_methods_config['ticket']['enabled']) {
    $ticket_enabled = true;
    $ticket_gateway = $payment_methods_config['ticket']['gateway'] ?? 'mercadopago';
    $ticket_pagarme_enabled = ($ticket_gateway === 'pagarme');
}

// Variáveis de Resumo
$main_price = floatval($produto['preco']);
$preco_base = floatval($preco_original ?: $produto['preco']);
$main_price_usd = (!empty($produto['price_usd']) && $produto['price_usd'] > 0) ? floatval($produto['price_usd']) : null;
if ($main_price_usd !== null && $preco_base > 0 && $main_price != $preco_base) {
    $main_price_usd = $main_price_usd * ($main_price / $preco_base); // Escala para oferta
}
$main_name = !empty($checkout_config['summary']['product_name']) ? $checkout_config['summary']['product_name'] : $produto['nome'];
$checkout_description_raw = $produto['checkout_description'] ?? '';
$checkout_description_html = (is_string($checkout_description_raw) && trim($checkout_description_raw) !== '') ? trim($checkout_description_raw) : '';
$main_image = resolve_product_image_url($produto['foto'] ?? '', 'uploads/') ?: '/uploads/placeholder.png';
$checkout_gallery_images = [];
foreach (['foto', 'foto_2', 'foto_3'] as $_gk) {
    $_gv = $produto[$_gk] ?? '';
    if ($_gv === '' || $_gv === null) {
        continue;
    }
    $_gu = resolve_product_image_url($_gv, 'uploads/');
    if ($_gu !== '') {
        $checkout_gallery_images[] = $_gu;
    }
}
unset($_gk, $_gu);
$formattedMainPrice = $is_free_product ? 'Grátis' : 'R$ ' . number_format($main_price, 2, ',', '.');
$formattedMainPriceUsd = ($main_price_usd !== null && !$is_free_product) ? 'US$ ' . number_format($main_price_usd, 2, ',', '.') : null;
$preco_anterior_raw = !empty($produto['preco_anterior']) ? floatval($produto['preco_anterior']) : null;
$formattedPrecoAnterior = ($preco_anterior_raw && !$is_free_product) ? 'R$ ' . number_format($preco_anterior_raw, 2, ',', '.') : null;
// Preço anterior em USD (proporcional) quando produto tem price_usd
$formattedPrecoAnteriorUsd = null;
if ($preco_anterior_raw && !$is_free_product && $main_price_usd !== null && $main_price > 0) {
    $preco_anterior_usd = $preco_anterior_raw * ($main_price_usd / $main_price);
    $formattedPrecoAnteriorUsd = 'US$ ' . number_format($preco_anterior_usd, 2, ',', '.');
}
$discount_text = $checkout_config['summary']['discount_text'] ?? '';

// Badges de benefício no resumo (desktop + mobile via JS)
ob_start();
if ($is_free_product) {
    echo '<span class="summary-benefit-badge bg-green-100 text-green-700 text-xs font-bold uppercase px-2 py-0.5 rounded-full inline-flex items-center gap-1"><i data-lucide="gift" class="w-3 h-3"></i>' . htmlspecialchars(checkout_t('free_product')) . '</span>';
} else {
    echo '<span class="summary-benefit-badge ' . htmlspecialchars($tipo_acesso_info['color']) . ' text-xs font-bold uppercase px-2 py-0.5 rounded-full inline-flex items-center gap-1"><i data-lucide="' . htmlspecialchars($tipo_acesso === 'vitalicio' ? 'infinity' : 'calendar') . '" class="w-3 h-3"></i>' . htmlspecialchars($tipo_acesso_info['label']) . '</span>';
}
echo '<span class="summary-benefit-badge bg-emerald-50 text-emerald-800 text-xs font-bold uppercase px-2 py-0.5 rounded-full inline-flex items-center gap-1 border border-emerald-200/70"><i data-lucide="zap" class="w-3 h-3"></i>' . htmlspecialchars(checkout_t('summary_badge_immediate')) . '</span>';
$summary_benefit_badges_html = ob_get_clean();

// Desconto Pix
$pix_discount_config = $payment_methods_config['pix_discount'] ?? ['enabled' => false, 'type' => 'percentage', 'value' => 0];
$pix_discount_enabled = $pix_discount_config['enabled'] ?? false;
$pix_discount_type = $pix_discount_config['type'] ?? 'percentage';
$pix_discount_value = floatval($pix_discount_config['value'] ?? 0);

// --- Funções de Renderização ---

function render_timer($timerConfig) {
    if (!($timerConfig['enabled'] ?? false)) return '';
    $text = htmlspecialchars($timerConfig['text'] ?? 'Esta oferta expira em:');
    $minutes = intval($timerConfig['minutes'] ?? 15);
    $bgcolor = htmlspecialchars($timerConfig['bgcolor'] ?? '#000000');
    $textcolor = htmlspecialchars($timerConfig['textcolor'] ?? '#FFFFFF');
    $is_sticky = $timerConfig['sticky'] ?? true;
    $transparent_bgcolor = $bgcolor . '99'; 
    $sticky_style = $is_sticky ? 'position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: background-color 0.3s ease, backdrop-filter 0.3s ease;' : 'position: relative;';
    $storage_key = 'checkoutTimer_' . htmlspecialchars($_GET['p'] ?? 'default');
    
    $js_script = "<script>
        document.addEventListener('DOMContentLoaded', () => {
            const timerData = { minutes: {$minutes}, storageKey: '{$storage_key}' };
            const timerElement = document.getElementById('timer-countdown-display');
            if (!timerElement) return;
            let endTime = localStorage.getItem(timerData.storageKey);
            if (!endTime || isNaN(endTime)) {
                endTime = new Date().getTime() + (timerData.minutes * 60 * 1000);
                localStorage.setItem(timerData.storageKey, endTime);
            }
            const interval = setInterval(() => {
                const now = new Date().getTime();
                const distance = endTime - now;
                if (distance < 0) {
                    clearInterval(interval);
                    timerElement.innerHTML = '00:00';
                    localStorage.removeItem(timerData.storageKey);
                    return;
                }
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                timerElement.innerHTML = (minutes < 10 ? '0' + minutes : minutes) + ':' + (seconds < 10 ? '0' + seconds : seconds);
            }, 1000);
            const timerBar = document.getElementById('timer-bar');
            if (timerBar && {$is_sticky}) {
                const solidColor = '{$bgcolor}';
                const transparentColor = '{$transparent_bgcolor}';
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 0) {
                        timerBar.style.backgroundColor = transparentColor;
                        timerBar.style.backdropFilter = 'blur(8px)';
                        timerBar.style.webkitBackdropFilter = 'blur(8px)';
                    } else {
                        timerBar.style.backgroundColor = solidColor;
                        timerBar.style.backdropFilter = 'none';
                        timerBar.style.webkitBackdropFilter = 'none';
                    }
                });
            }
        });
        </script>";
    return "<div id='timer-bar' style='background-color: {$bgcolor}; color: {$textcolor}; {$sticky_style}'><div class='flex items-center justify-center p-3 text-center w-full'><i data-lucide='clock' class='w-5 h-5 mr-3 flex-shrink-0'></i><p class='font-semibold'>{$text}</p><span id='timer-countdown-display' class='font-bold text-lg ml-2 font-mono w-14'>{$minutes}:00</span></div></div>{$js_script}";
}

function render_youtube_video($youtubeUrl) {
    if (!$youtubeUrl) return '';
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $youtubeUrl, $match);
    $youtube_id = $match[1] ?? null;
    if(!$youtube_id) return '';
    return "<div data-id='youtube_video' class='mb-6'><div class='aspect-video rounded-lg overflow-hidden shadow-md'><iframe src='https://www.youtube.com/embed/{$youtube_id}' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen class='w-full h-full'></iframe></div></div>";
}

function render_order_bumps_section($order_bumps_array) {
    if (empty($order_bumps_array)) return '';
    $html = "<div data-id='order_bump' class='space-y-6'>";
    foreach($order_bumps_array as $index => $bump) {
        $ob_image = resolve_product_image_url($bump['ob_foto'] ?? '', 'uploads/') ?: '/uploads/placeholder.png';
        $ob_headline = htmlspecialchars($bump['headline']);
        $ob_description = htmlspecialchars($bump['description']);
        $ob_name = htmlspecialchars($bump['ob_nome']);
        $ob_price_raw = floatval($bump['preco_efetivo_order_bump'] ?? $bump['ob_preco']);
        $ob_price_formatted = 'R$ ' . number_format($ob_price_raw, 2, ',', '.');
        $ob_id = intval($bump['ob_id']);

        $html .= "<div class='order-bump-wrapper'>";
        $html .= "<input type='checkbox' id='orderbump-checkbox-{$ob_id}' data-product-id='{$ob_id}' data-price='{$ob_price_raw}' data-name='{$ob_name}' class='orderbump-checkbox sr-only'>";
        $html .= "<label for='orderbump-checkbox-{$ob_id}' class='order-bump-block'>"; 
        $ob_offer = htmlspecialchars(checkout_t('offer_special'));
        $ob_yes = htmlspecialchars(checkout_t('yes_want_offer'));
        $html .= "<div class='offer-badge'>{$ob_offer}</div>";
        $html .= "<div class='flex items-start gap-4'><img src='{$ob_image}' class='w-16 h-16 rounded-md object-cover border shadow-sm flex-shrink-0' onerror=\"this.src='https://placehold.co/64x64/e2e8f0/334155?text=Produto'\"/><div class='flex-1'><h4 class='text-lg font-bold text-gray-800'>{$ob_headline}</h4><p class='text-sm text-gray-600 mt-1'>{$ob_description}</p></div></div>";
        $html .= "<hr class='my-3 border-dashed border-gray-300'>";
        $html .= "<div class='flex justify-between items-center'><div class='flex items-center gap-2'><div class='custom-checkbox flex-shrink-0'><i data-lucide='check' class='checkmark'></i></div><span class='font-semibold text-gray-800 text-sm sm:text-base'>{$ob_yes}</span></div><p class='font-bold text-green-600 text-lg'>+{$ob_price_formatted}</p></div>";
        $html .= "</label></div>";
    }
    $html .= "</div>";
    return $html;
}

function render_payment_methods_selector($pix_pushinpay_enabled, $pix_mercadopago_enabled, $pix_efi_enabled, $credit_card_enabled, $ticket_enabled, $accentColor, $credit_card_beehive_enabled = false, $credit_card_mercadopago_enabled = false, $credit_card_hypercash_enabled = false, $credit_card_efi_enabled = false, $pix_pagarme_enabled = false, $pix_stripe_enabled = false, $credit_card_pagarme_enabled = false, $credit_card_paypal_enabled = false, $credit_card_stripe_enabled = false, $ticket_pagarme_enabled = false) {
    $available_methods = [];
    
    // Pix - prioridade PushinPay > Efí > Pagar.me > Stripe > Mercado Pago
    $pix_name = checkout_t('pix');
    if ($pix_pushinpay_enabled) {
        $available_methods[] = ['type' => 'pix_pushinpay', 'name' => $pix_name, 'icon' => 'qr-code', 'gateway' => 'pushinpay'];
    } elseif ($pix_efi_enabled) {
        $available_methods[] = ['type' => 'pix_efi', 'name' => $pix_name, 'icon' => 'qr-code', 'gateway' => 'efi'];
    } elseif ($pix_pagarme_enabled) {
        $available_methods[] = ['type' => 'pix_pagarme', 'name' => $pix_name, 'icon' => 'qr-code', 'gateway' => 'pagarme'];
    } elseif ($pix_stripe_enabled) {
        $available_methods[] = ['type' => 'pix_stripe', 'name' => $pix_name, 'icon' => 'qr-code', 'gateway' => 'stripe'];
    } elseif ($pix_mercadopago_enabled) {
        $available_methods[] = ['type' => 'pix_mercadopago', 'name' => $pix_name, 'icon' => 'qr-code', 'gateway' => 'mercadopago'];
    }
    
    // Cartão de Crédito - prioridade Stripe > PayPal > Pagar.me > Hypercash > Beehive > Efí > Mercado Pago (i18n)
    if ($credit_card_stripe_enabled) {
        $available_methods[] = ['type' => 'credit_card_stripe', 'name' => checkout_t('credit_card_stripe'), 'icon' => 'credit-card', 'gateway' => 'stripe'];
    } elseif ($credit_card_paypal_enabled) {
        $available_methods[] = ['type' => 'credit_card_paypal', 'name' => checkout_t('credit_card_paypal'), 'icon' => 'credit-card', 'gateway' => 'paypal'];
    } elseif ($credit_card_pagarme_enabled) {
        $available_methods[] = ['type' => 'credit_card_pagarme', 'name' => checkout_t('credit_card_pagarme'), 'icon' => 'credit-card', 'gateway' => 'pagarme'];
    } elseif ($credit_card_hypercash_enabled) {
        $available_methods[] = ['type' => 'credit_card_hypercash', 'name' => checkout_t('credit_card'), 'icon' => 'credit-card', 'gateway' => 'hypercash'];
    } elseif ($credit_card_beehive_enabled) {
        $available_methods[] = ['type' => 'credit_card_beehive', 'name' => checkout_t('credit_card'), 'icon' => 'credit-card', 'gateway' => 'beehive'];
    } elseif ($credit_card_efi_enabled) {
        $available_methods[] = ['type' => 'credit_card_efi', 'name' => checkout_t('credit_card'), 'icon' => 'credit-card', 'gateway' => 'efi'];
    } elseif ($credit_card_mercadopago_enabled) {
        $available_methods[] = ['type' => 'credit_card', 'name' => checkout_t('credit_card'), 'icon' => 'credit-card', 'gateway' => 'mercadopago'];
    } elseif ($credit_card_enabled) {
        $available_methods[] = ['type' => 'credit_card', 'name' => checkout_t('credit_card'), 'icon' => 'credit-card', 'gateway' => 'mercadopago'];
    }
    
    if ($ticket_enabled) {
        $ticket_name = $ticket_pagarme_enabled ? checkout_t('ticket_pagarme') : checkout_t('ticket');
        $available_methods[] = ['type' => $ticket_pagarme_enabled ? 'ticket_pagarme' : 'ticket', 'name' => $ticket_name, 'icon' => 'file-text', 'gateway' => $ticket_pagarme_enabled ? 'pagarme' : 'mercadopago'];
    }
    
    if (empty($available_methods)) {
        return '';
    }
    
    $accentColorEscaped = htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8');
    
    $html = "<div class='mb-6'>";
    $html .= "<h3 class='text-lg font-semibold mb-4 text-gray-800 flex items-center'><i data-lucide='wallet' class='w-5 h-5 mr-2'></i>" . htmlspecialchars(checkout_t('payment_choose')) . "</h3>";
    $html .= "<div class='grid grid-cols-2 lg:grid-cols-3 gap-4' id='payment-methods-selector'>";
    
    foreach ($available_methods as $method) {
        $methodType = htmlspecialchars($method['type'], ENT_QUOTES, 'UTF-8');
        $methodName = htmlspecialchars($method['name'], ENT_QUOTES, 'UTF-8');
        $methodIcon = htmlspecialchars($method['icon'], ENT_QUOTES, 'UTF-8');
        
        $html .= "<div class='payment-method-card bg-white rounded-lg border-2 border-gray-200 p-4 cursor-pointer transition-all hover:shadow-lg flex flex-col items-center justify-center text-center min-h-[120px]' data-payment-method='{$methodType}' style='border-color: #e5e7eb;'>";
        
        // Se for Pix, usar logo oficial ao invés de ícone
        if ($methodType === 'pix_pushinpay' || $methodType === 'pix_mercadopago' || $methodType === 'pix_efi' || $methodType === 'pix_pagarme' || $methodType === 'pix_stripe') {
            $html .= "<svg width='32' height='32' viewBox='0 0 16 16' xmlns='http://www.w3.org/2000/svg' class='mb-2' style='color: {$accentColorEscaped};' fill='currentColor'><path d='M11.917 11.71a2.046 2.046 0 0 1-1.454-.602l-2.1-2.1a.4.4 0 0 0-.551 0l-2.108 2.108a2.044 2.044 0 0 1-1.454.602h-.414l2.66 2.66c.83.83 2.177.83 3.007 0l2.667-2.668h-.253zM4.25 4.282c.55 0 1.066.214 1.454.602l2.108 2.108a.39.39 0 0 0 .552 0l2.1-2.1a2.044 2.044 0 0 1 1.453-.602h.253L9.503 1.623a2.127 2.127 0 0 0-3.007 0l-2.66 2.66h.414z'/><path d='m14.377 6.496-1.612-1.612a.307.307 0 0 1-.114.023h-.733c-.379 0-.75.154-1.017.422l-2.1 2.1a1.005 1.005 0 0 1-1.425 0L5.268 5.32a1.448 1.448 0 0 0-1.018-.422h-.9a.306.306 0 0 1-.109-.021L1.623 6.496c-.83.83-.83 2.177 0 3.008l1.618 1.618a.305.305 0 0 1 .108-.022h.901c.38 0 .75-.153 1.018-.421L7.375 8.57a1.034 1.034 0 0 1 1.426 0l2.1 2.1c.267.268.638.421 1.017.421h.733c.04 0 .079.01.114.024l1.612-1.612c.83-.83.83-2.178 0-3.008z'/></svg>";
        } else {
            $html .= "<i data-lucide='{$methodIcon}' class='w-8 h-8 mb-2' style='color: {$accentColorEscaped};'></i>";
        }
        
        $html .= "<span class='font-semibold text-gray-800 text-sm'>{$methodName}</span>";
        $html .= "</div>";
    }
    
    $html .= "</div>";
    $html .= "</div>";
    
    return $html;
}

function render_free_product_section($accentColor) {
    $html = "<div data-id='payment'>";
    $html .= "<div id='payment_section_wrapper'>";
    $html .= "<div class='mb-6'>";
    $html .= "<h3 class='text-lg font-semibold mb-4 text-gray-800 flex items-center'><i data-lucide='gift' class='w-5 h-5 mr-2' style='color: {$accentColor};'></i>Produto Gratuito</h3>";
    $html .= "<div class='bg-green-50 border border-green-200 rounded-lg p-4 mb-4'>";
    $html .= "<div class='flex items-center gap-3'>";
    $html .= "<div class='w-10 h-10 bg-green-100 rounded-full flex items-center justify-center'><i data-lucide='check-circle' class='w-6 h-6 text-green-600'></i></div>";
    $html .= "<div>";
    $html .= "<p class='font-semibold text-green-800'>Este produto é 100% gratuito!</p>";
    $html .= "<p class='text-sm text-green-600'>Preencha seus dados acima e clique no botão para receber seu acesso.</p>";
    $html .= "</div>";
    $html .= "</div>";
    $html .= "</div>";
    $html .= "<button id='btn-get-free-product' class='w-full text-white font-bold py-4 rounded-lg transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform active:scale-95' style='background-color: {$accentColor};' onmouseover=\"this.style.opacity='0.9'\" onmouseout=\"this.style.opacity='1'\">";
    $html .= "<i data-lucide='download' class='w-6 h-6'></i> QUERO MEU ACESSO GRÁTIS";
    $html .= "</button>";
    $html .= "</div>";
    $html .= "</div>";
    $html .= "</div>";
    return $html;
}

function render_payment_section($gateway, $accentColor, $payment_methods_config, $pix_pushinpay_enabled = null, $pix_mercadopago_enabled = null, $pix_efi_enabled = null, $credit_card_enabled = null, $ticket_enabled = null, $credit_card_beehive_enabled = null, $credit_card_mercadopago_enabled = null, $credit_card_hypercash_enabled = null, $credit_card_efi_enabled = null, $pix_pagarme_enabled = null, $pix_stripe_enabled = null, $credit_card_pagarme_enabled = null, $credit_card_paypal_enabled = null, $credit_card_stripe_enabled = null, $ticket_pagarme_enabled = null, $mp_configured = true, $stripe_configured = true) {
    $html = "<div data-id='payment'>";
    $html .= "<div id='payment_section_wrapper'>";
    
    // Se as variáveis não foram passadas, calcular (retrocompatibilidade)
    if ($pix_pushinpay_enabled === null || $pix_mercadopago_enabled === null || $pix_efi_enabled === null || $credit_card_enabled === null || $ticket_enabled === null) {
        $pix_pushinpay_enabled = false;
        $pix_mercadopago_enabled = false;
        $pix_efi_enabled = false;
        $credit_card_enabled = false;
        $credit_card_beehive_enabled = false;
        $credit_card_hypercash_enabled = false;
        $credit_card_mercadopago_enabled = false;
        $credit_card_efi_enabled = false;
        $credit_card_pagarme_enabled = false;
        $credit_card_paypal_enabled = false;
        $credit_card_stripe_enabled = false;
        $pix_pagarme_enabled = false;
        $pix_stripe_enabled = false;
        $ticket_enabled = false;
        $ticket_pagarme_enabled = false;
        
        // Ler nova estrutura com gateway por método
        if (isset($payment_methods_config['pix']['gateway'])) {
            if ($payment_methods_config['pix']['gateway'] === 'pushinpay' && ($payment_methods_config['pix']['enabled'] ?? false)) {
                $pix_pushinpay_enabled = true;
            } elseif ($payment_methods_config['pix']['gateway'] === 'mercadopago' && ($payment_methods_config['pix']['enabled'] ?? false)) {
                $pix_mercadopago_enabled = true;
            } elseif ($payment_methods_config['pix']['gateway'] === 'efi' && ($payment_methods_config['pix']['enabled'] ?? false)) {
                $pix_efi_enabled = true;
            } elseif ($payment_methods_config['pix']['gateway'] === 'pagarme' && ($payment_methods_config['pix']['enabled'] ?? false)) {
                $pix_pagarme_enabled = true;
            } elseif ($payment_methods_config['pix']['gateway'] === 'stripe' && ($payment_methods_config['pix']['enabled'] ?? false)) {
                $pix_stripe_enabled = true;
            }
        }
        
        if (isset($payment_methods_config['credit_card']['enabled']) && $payment_methods_config['credit_card']['enabled']) {
            $credit_card_enabled = true;
            $credit_card_gateway = $payment_methods_config['credit_card']['gateway'] ?? 'mercadopago';
            if ($credit_card_gateway === 'beehive') {
                $credit_card_beehive_enabled = true;
            } elseif ($credit_card_gateway === 'hypercash') {
                $credit_card_hypercash_enabled = true;
            } elseif ($credit_card_gateway === 'efi') {
                $credit_card_efi_enabled = true;
            } elseif ($credit_card_gateway === 'pagarme') {
                $credit_card_pagarme_enabled = true;
            } elseif ($credit_card_gateway === 'paypal') {
                $credit_card_paypal_enabled = true;
            } elseif ($credit_card_gateway === 'stripe') {
                $credit_card_stripe_enabled = true;
            } else {
                $credit_card_mercadopago_enabled = true;
            }
        }
        
        if (isset($payment_methods_config['ticket']['enabled']) && $payment_methods_config['ticket']['enabled']) {
            $ticket_enabled = true;
            $ticket_gateway = $payment_methods_config['ticket']['gateway'] ?? 'mercadopago';
            $ticket_pagarme_enabled = ($ticket_gateway === 'pagarme');
        }
    } else {
        // Valores passados do escopo global - garantir defaults para os que podem ser null
        $pix_pagarme_enabled = $pix_pagarme_enabled ?? false;
        $pix_stripe_enabled = $pix_stripe_enabled ?? false;
        $credit_card_pagarme_enabled = $credit_card_pagarme_enabled ?? false;
        $credit_card_paypal_enabled = $credit_card_paypal_enabled ?? false;
        $credit_card_stripe_enabled = $credit_card_stripe_enabled ?? false;
        $ticket_pagarme_enabled = $ticket_pagarme_enabled ?? false;
    }
    
    // Forçar Stripe quando config diz stripe (evita bug de variáveis não passadas)
    if (($payment_methods_config['pix']['gateway'] ?? '') === 'stripe' && ($payment_methods_config['pix']['enabled'] ?? false)) {
        $pix_stripe_enabled = true;
    }
    if (($payment_methods_config['credit_card']['gateway'] ?? '') === 'stripe' && ($payment_methods_config['credit_card']['enabled'] ?? false)) {
        $credit_card_stripe_enabled = true;
        $credit_card_mercadopago_enabled = false;
    }
    
    // Renderizar seletor de métodos de pagamento
    $html .= render_payment_methods_selector($pix_pushinpay_enabled, $pix_mercadopago_enabled, $pix_efi_enabled, $credit_card_enabled, $ticket_enabled, $accentColor, $credit_card_beehive_enabled, $credit_card_mercadopago_enabled, $credit_card_hypercash_enabled, $credit_card_efi_enabled ?? false, $pix_pagarme_enabled ?? false, $pix_stripe_enabled ?? false, $credit_card_pagarme_enabled ?? false, $credit_card_paypal_enabled ?? false, $credit_card_stripe_enabled ?? false, $ticket_pagarme_enabled ?? false);
    
    // Container PushinPay Pix
    if ($pix_pushinpay_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='pix_pushinpay'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-green-500 bg-green-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'>";
            $html .= "<div class='flex items-center gap-3'>";
                $html .= "<img src='/assets/pix.svg' class='h-6 w-auto' alt='Pix' style='filter: brightness(0) saturate(100%) invert(60%) sepia(95%) saturate(1200%) hue-rotate(140deg) brightness(0.9) contrast(0.9);'>";
                $html .= "<span class='font-bold text-gray-800'>Pix</span>";
            $html .= "</div>";
            $html .= "<div class='w-5 h-5 rounded-full border-4 border-green-500'></div>";
        $html .= "</div>";
        
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'>";
            $html .= "<p>• " . htmlspecialchars(checkout_t('pix_immediate_release')) . "</p>";
            $html .= "<p>• " . htmlspecialchars(checkout_t('pix_simple_bank')) . "</p>";
        $html .= "</div>";
        
        $html .= "<button id='btn-pagar-pushinpay' class='w-full bg-green-600 text-white font-bold py-4 rounded-lg hover:bg-green-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform active:scale-95'>";
            $html .= "<i data-lucide='qr-code' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_generate_pix'));
        $html .= "</button>";
        
        $html .= "</div>";
        $html .= "</div>";
    }
    
    // Container Mercado Pago Pix
    if ($pix_mercadopago_enabled) {
        $enabled_payment_methods = ['bankTransfer' => 'all'];
        $json_config = htmlspecialchars(json_encode($enabled_payment_methods), ENT_QUOTES, 'UTF-8');
        
        $html .= "<div class='payment-method-container hidden' data-method-type='pix_mercadopago'>";
        $html .= "<div id='payment_container_wrapper_pix_mp' class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm' data-mp-config='{$json_config}'>";
        $html .= "<div id='loading_spinner_pix_mp' class='flex flex-col items-center justify-center py-12 text-gray-500'><svg class='animate-spin h-8 w-8' style='color: {$accentColor};' xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24'><circle class='opacity-25' cx='12' cy='12' r='10' stroke='currentColor' stroke-width='4'></circle><path class='opacity-75' fill='currentColor' d='M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z'></path></svg><p class='mt-4 font-medium'>" . htmlspecialchars(checkout_t('loading_payment')) . "</p></div>";
        $html .= "<div id='paymentBrick_container_pix_mp'></div>";
        $html .= "</div>";
        $html .= "</div>";
    }
    
    // Container Efí Pix
    if ($pix_efi_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='pix_efi'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-green-500 bg-green-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'>";
            $html .= "<div class='flex items-center gap-3'>";
                $html .= "<img src='/assets/pix.svg' class='h-6 w-auto' alt='Pix' style='filter: brightness(0) saturate(100%) invert(60%) sepia(95%) saturate(1200%) hue-rotate(140deg) brightness(0.9) contrast(0.9);'>";
                $html .= "<span class='font-bold text-gray-800'>Pix</span>";
            $html .= "</div>";
            $html .= "<div class='w-5 h-5 rounded-full border-4 border-green-500'></div>";
        $html .= "</div>";
        
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'>";
            $html .= "<p>• " . htmlspecialchars(checkout_t('pix_immediate_release')) . "</p>";
            $html .= "<p>• " . htmlspecialchars(checkout_t('pix_simple_bank')) . "</p>";
        $html .= "</div>";
        
        $html .= "<button id='btn-pagar-efi' class='w-full bg-green-600 text-white font-bold py-4 rounded-lg hover:bg-green-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform active:scale-95'>";
            $html .= "<i data-lucide='qr-code' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_generate_pix'));
        $html .= "</button>";
        
        $html .= "</div>";
        $html .= "</div>";
    }
    
    // Container Pix Pagar.me
    if (isset($pix_pagarme_enabled) && $pix_pagarme_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='pix_pagarme'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-teal-500 bg-teal-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'><div class='flex items-center gap-3'><img src='/assets/pix.svg' class='h-6 w-auto' alt='Pix'><span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('pix')) . " (Pagar.me)</span></div><div class='w-5 h-5 rounded-full border-4 border-teal-500'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• " . htmlspecialchars(checkout_t('pix_immediate_release')) . "</p><p>• " . htmlspecialchars(checkout_t('pix_pay_bank_app')) . "</p></div>";
        $html .= "<button id='btn-pagar-pagarme-pix' class='w-full bg-teal-600 text-white font-bold py-4 rounded-lg hover:bg-teal-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg'><i data-lucide='qr-code' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_generate_pix')) . "</button>";
        $html .= "</div></div>";
    }
    
    // Container Pix Stripe
    if ($pix_stripe_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='pix_stripe'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-indigo-500 bg-indigo-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'><div class='flex items-center gap-3'><img src='/assets/pix.svg' class='h-6 w-auto' alt='Pix'><span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('pix')) . " (Stripe)</span></div><div class='w-5 h-5 rounded-full border-4 border-indigo-500'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• " . htmlspecialchars(checkout_t('pix_immediate_release')) . "</p><p>• " . htmlspecialchars(checkout_t('pix_pay_bank_app')) . "</p></div>";
        $html .= "<button id='btn-pagar-stripe-pix' class='w-full bg-indigo-600 text-white font-bold py-4 rounded-lg hover:bg-indigo-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg'><i data-lucide='qr-code' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_generate_pix')) . "</button>";
        $html .= "</div></div>";
    }
    
    // Container Cartão de Crédito Mercado Pago (NUNCA renderizar se Stripe/PayPal/Pagar.me estiverem no config)
    $card_gw_from_config = $payment_methods_config['credit_card']['gateway'] ?? 'mercadopago';
    $has_other_card_gateway = (isset($credit_card_beehive_enabled) && $credit_card_beehive_enabled) || 
                              (isset($credit_card_hypercash_enabled) && $credit_card_hypercash_enabled) || 
                              (isset($credit_card_efi_enabled) && $credit_card_efi_enabled) ||
                              (isset($credit_card_pagarme_enabled) && $credit_card_pagarme_enabled) ||
                              (isset($credit_card_paypal_enabled) && $credit_card_paypal_enabled) ||
                              (isset($credit_card_stripe_enabled) && $credit_card_stripe_enabled);
    $is_mp_card_config = ($card_gw_from_config === 'mercadopago' || $card_gw_from_config === null);
    $render_mp_card = $is_mp_card_config && ($credit_card_mercadopago_enabled || ($credit_card_enabled && !$has_other_card_gateway));
    if ($render_mp_card) {
        $enabled_payment_methods = ['creditCard' => 'all'];
        $json_config = htmlspecialchars(json_encode($enabled_payment_methods), ENT_QUOTES, 'UTF-8');
        
        $html .= "<div class='payment-method-container hidden' data-method-type='credit_card'>";
        $html .= "<div id='payment_container_wrapper_credit' class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm' data-mp-config='{$json_config}'>";
        $html .= "<div id='loading_spinner_credit' class='flex flex-col items-center justify-center py-12 text-gray-500'><svg class='animate-spin h-8 w-8' style='color: {$accentColor};' xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24'><circle class='opacity-25' cx='12' cy='12' r='10' stroke='currentColor' stroke-width='4'></circle><path class='opacity-75' fill='currentColor' d='M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z'></path></svg><p class='mt-4 font-medium'>" . htmlspecialchars(checkout_t('loading_payment')) . "</p></div>";
        $html .= "<div id='paymentBrick_container_credit'></div>";
        $html .= "</div>";
        $html .= "</div>";
    }
    
    // Container Cartão de Crédito Beehive
    if ($credit_card_beehive_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='credit_card_beehive'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-yellow-500 bg-yellow-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'>";
            $html .= "<div class='flex items-center gap-3'>";
                $html .= "<i data-lucide='credit-card' class='w-6 h-6 text-yellow-600'></i>";
                $html .= "<span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('credit_card')) . "</span>";
            $html .= "</div>";
            $html .= "<div class='w-5 h-5 rounded-full border-4 border-yellow-500'></div>";
        $html .= "</div>";
        
        $html .= "<form id='beehive-card-form' class='space-y-4'>";
        $html .= "<div><label class='block text-sm font-medium text-gray-700 mb-2'>Número do Cartão</label><input type='text' id='beehive-card-number' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500' placeholder='0000 0000 0000 0000' maxlength='19'></div>";
        $html .= "<div><label class='block text-sm font-medium text-gray-700 mb-2'>Nome no Cartão</label><input type='text' id='beehive-card-holder' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500' placeholder='NOME COMPLETO'></div>";
        $html .= "<div class='grid grid-cols-2 gap-4'><div><label class='block text-sm font-medium text-gray-700 mb-2'>Validade</label><input type='text' id='beehive-card-expiry' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500' placeholder='MM/AA' maxlength='5'></div><div><label class='block text-sm font-medium text-gray-700 mb-2'>CVV</label><input type='text' id='beehive-card-cvv' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500' placeholder='123' maxlength='4'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• Aprovação imediata do acesso.</p><p>• 100% Seguro e criptografado.</p></div>";
        $html .= "<button type='button' id='btn-pagar-beehive' class='w-full bg-yellow-600 text-white font-bold py-4 rounded-lg hover:bg-yellow-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform active:scale-95'><i data-lucide='credit-card' class='w-6 h-6'></i> FINALIZAR PAGAMENTO</button>";
        $html .= "</form></div></div>";
    }
    
    // Container Cartão de Crédito Hypercash
    if ($credit_card_hypercash_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='credit_card_hypercash'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-indigo-500 bg-indigo-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'>";
            $html .= "<div class='flex items-center gap-3'>";
                $html .= "<i data-lucide='credit-card' class='w-6 h-6 text-indigo-600'></i>";
                $html .= "<span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('credit_card')) . "</span>";
            $html .= "</div>";
            $html .= "<div class='w-5 h-5 rounded-full border-4 border-indigo-500'></div>";
        $html .= "</div>";
        
        $html .= "<form id='hypercash-card-form' class='space-y-4'>";
        $html .= "<div><label class='block text-sm font-medium text-gray-700 mb-2'>Número do Cartão</label><input type='text' id='hypercash-card-number' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' placeholder='0000 0000 0000 0000' maxlength='19'></div>";
        $html .= "<div><label class='block text-sm font-medium text-gray-700 mb-2'>Nome no Cartão</label><input type='text' id='hypercash-card-holder' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' placeholder='NOME COMPLETO'></div>";
        $html .= "<div class='grid grid-cols-2 gap-4'><div><label class='block text-sm font-medium text-gray-700 mb-2'>Validade</label><input type='text' id='hypercash-card-expiry' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' placeholder='MM/AA' maxlength='5'></div><div><label class='block text-sm font-medium text-gray-700 mb-2'>CVV</label><input type='text' id='hypercash-card-cvv' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500' placeholder='123' maxlength='4'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• " . htmlspecialchars(checkout_t('card_immediate_approval')) . "</p><p>• " . htmlspecialchars(checkout_t('card_secure_encrypted')) . "</p></div>";
        $html .= "<button type='button' id='btn-pagar-hypercash' class='w-full bg-indigo-600 text-white font-bold py-4 rounded-lg hover:bg-indigo-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform active:scale-95'><i data-lucide='credit-card' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_finish_payment')) . "</button>";
        $html .= "</form></div></div>";
    }
    
    // Container Cartão de Crédito Efí
    if (isset($credit_card_efi_enabled) && $credit_card_efi_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='credit_card_efi'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-green-500 bg-green-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'>";
            $html .= "<div class='flex items-center gap-3'>";
                $html .= "<i data-lucide='credit-card' class='w-6 h-6 text-green-600'></i>";
                $html .= "<span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('credit_card')) . "</span>";
            $html .= "</div>";
            $html .= "<div class='w-5 h-5 rounded-full border-4 border-green-500'></div>";
        $html .= "</div>";
        
        $html .= "<form id='efi-card-form' class='space-y-4'>";
        $html .= "<div><label class='block text-sm font-medium text-gray-700 mb-2'>Número do Cartão</label><input type='text' id='efi-card-number' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500' placeholder='0000 0000 0000 0000' maxlength='19'></div>";
        $html .= "<div><label class='block text-sm font-medium text-gray-700 mb-2'>Nome no Cartão</label><input type='text' id='efi-card-holder' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500' placeholder='NOME COMPLETO'></div>";
        $html .= "<div class='grid grid-cols-2 gap-4'><div><label class='block text-sm font-medium text-gray-700 mb-2'>Validade</label><input type='text' id='efi-card-expiry' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500' placeholder='MM/AA' maxlength='5'></div><div><label class='block text-sm font-medium text-gray-700 mb-2'>CVV</label><input type='text' id='efi-card-cvv' class='w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500' placeholder='123' maxlength='4'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• " . htmlspecialchars(checkout_t('card_immediate_approval')) . "</p><p>• " . htmlspecialchars(checkout_t('card_secure_encrypted')) . "</p></div>";
        $html .= "<button type='button' id='btn-pagar-efi-card' class='w-full bg-green-600 text-white font-bold py-4 rounded-lg hover:bg-green-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform active:scale-95'><i data-lucide='credit-card' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_finish_payment')) . "</button>";
        $html .= "</form></div></div>";
    }
    
    // Container Cartão Pagar.me
    if (isset($credit_card_pagarme_enabled) && $credit_card_pagarme_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='credit_card_pagarme'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-teal-500 bg-teal-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'><div class='flex items-center gap-3'><i data-lucide='credit-card' class='w-6 h-6 text-teal-600'></i><span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('credit_card_pagarme')) . "</span></div><div class='w-5 h-5 rounded-full border-4 border-teal-500'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• " . htmlspecialchars(checkout_t('card_immediate_approval_short')) . "</p><p>• " . htmlspecialchars(checkout_t('card_secure_encrypted_short')) . "</p></div>";
        $html .= "<button type='button' id='btn-pagar-pagarme-card' class='w-full bg-teal-600 text-white font-bold py-4 rounded-lg hover:bg-teal-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg'><i data-lucide='credit-card' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_finish_payment')) . "</button>";
        $html .= "</div></div>";
    }
    
    // Container Cartão PayPal
    if (isset($credit_card_paypal_enabled) && $credit_card_paypal_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='credit_card_paypal'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-blue-500 bg-blue-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'><div class='flex items-center gap-3'><i data-lucide='credit-card' class='w-6 h-6 text-blue-600'></i><span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('credit_card_paypal')) . "</span></div><div class='w-5 h-5 rounded-full border-4 border-blue-500'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• " . htmlspecialchars(checkout_t('paypal_pay_card')) . "</p><p>• " . htmlspecialchars(checkout_t('card_100_secure')) . "</p></div>";
        $html .= "<button type='button' id='btn-pagar-paypal' class='w-full bg-blue-600 text-white font-bold py-4 rounded-lg hover:bg-blue-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg'><i data-lucide='credit-card' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_finish_payment')) . "</button>";
        $html .= "</div></div>";
    }
    
    // Container Cartão Stripe (usa Stripe Checkout Session - redirecionamento)
    if ($credit_card_stripe_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='credit_card_stripe'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-indigo-500 bg-indigo-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'><div class='flex items-center gap-3'><i data-lucide='credit-card' class='w-6 h-6 text-indigo-600'></i><span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('credit_card_stripe')) . "</span></div><div class='w-5 h-5 rounded-full border-4 border-indigo-500'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• " . htmlspecialchars(checkout_t('card_immediate_approval_short')) . "</p><p>• " . htmlspecialchars(checkout_t('card_secure_encrypted_short')) . "</p><p class='mt-2 text-indigo-600 font-medium'>" . htmlspecialchars(checkout_t('stripe_redirect_hint')) . "</p></div>";
        $html .= "<button type='button' id='btn-pagar-stripe-card' class='w-full bg-indigo-600 text-white font-bold py-4 rounded-lg hover:bg-indigo-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg'><i data-lucide='credit-card' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_finish_payment')) . "</button>";
        $html .= "</div></div>";
    }
    
    // Container Boleto Pagar.me
    if (isset($ticket_pagarme_enabled) && $ticket_pagarme_enabled) {
        $html .= "<div class='payment-method-container hidden' data-method-type='ticket_pagarme'>";
        $html .= "<div class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm'>";
        $html .= "<div class='border-2 border-teal-500 bg-teal-50 rounded-lg p-4 flex items-center justify-between cursor-default mb-4'><div class='flex items-center gap-3'><i data-lucide='file-text' class='w-6 h-6 text-teal-600'></i><span class='font-bold text-gray-800'>" . htmlspecialchars(checkout_t('ticket_pagarme')) . "</span></div><div class='w-5 h-5 rounded-full border-4 border-teal-500'></div></div>";
        $html .= "<div class='text-sm text-gray-600 bg-gray-50 p-3 rounded border border-gray-200 mb-4'><p>• " . htmlspecialchars(checkout_t('ticket_release_days')) . "</p><p>• " . htmlspecialchars(checkout_t('ticket_pay_anywhere')) . "</p></div>";
        $html .= "<button id='btn-pagar-pagarme-ticket' class='w-full bg-teal-600 text-white font-bold py-4 rounded-lg hover:bg-teal-700 transition duration-300 text-lg flex items-center justify-center gap-2 shadow-lg'><i data-lucide='file-text' class='w-6 h-6'></i> " . htmlspecialchars(checkout_t('btn_generate_ticket')) . "</button>";
        $html .= "</div></div>";
    }
    
    // Container Boleto Mercado Pago
    if ($ticket_enabled && !(isset($ticket_pagarme_enabled) && $ticket_pagarme_enabled)) {
        $enabled_payment_methods = ['ticket' => 'all'];
        $json_config = htmlspecialchars(json_encode($enabled_payment_methods), ENT_QUOTES, 'UTF-8');
        
        $html .= "<div class='payment-method-container hidden' data-method-type='ticket'>";
        $html .= "<div id='payment_container_wrapper_ticket' class='bg-white rounded-lg border border-gray-200 p-5 shadow-sm' data-mp-config='{$json_config}'>";
        $html .= "<div id='loading_spinner_ticket' class='flex flex-col items-center justify-center py-12 text-gray-500'><svg class='animate-spin h-8 w-8' style='color: {$accentColor};' xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24'><circle class='opacity-25' cx='12' cy='12' r='10' stroke='currentColor' stroke-width='4'></circle><path class='opacity-75' fill='currentColor' d='M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z'></path></svg><p class='mt-4 font-medium'>" . htmlspecialchars(checkout_t('loading_payment')) . "</p></div>";
        $html .= "<div id='paymentBrick_container_ticket'></div>";
        $html .= "</div>";
        $html .= "</div>";
    }
    
    $html .= "</div></div>";
    return $html;
}

function render_security_info($vendedor_nome, $privacy_url = '', $terms_url = '') {
    global $logo_checkout_url, $nome_plataforma;
    
    // Buscar selo de segurança
    $security_seal_url = getSystemSetting('security_seal_url', '');
    if (!empty($security_seal_url) && strpos($security_seal_url, 'http') !== 0) {
        $security_seal_url = '/' . ltrim($security_seal_url, '/');
    }
    
    $vendedor_nome_html = htmlspecialchars($vendedor_nome);
    $logo_html = htmlspecialchars($logo_checkout_url);
    $nome_plataforma_html = htmlspecialchars($nome_plataforma);
    $html = "<div data-id='security_info' class='text-center text-xs text-gray-500 space-y-4'>"; 
    $html .= "<img src='{$logo_html}' alt='Logo {$nome_plataforma_html}' class='h-10 mx-auto mb-4'>";
    $html .= "<p><strong>{$nome_plataforma_html}</strong> " . htmlspecialchars(checkout_t('security_info')) . " <strong>{$vendedor_nome_html}</strong>.</p>";
    
    // Se tiver selo de segurança, exibe a imagem; senão, exibe o texto padrão
    if (!empty($security_seal_url)) {
        $html .= "<div class='flex items-center justify-center'><img src='" . htmlspecialchars($security_seal_url) . "' alt='Selo de Segurança' class='max-h-16 mx-auto'></div>";
    } else {
        $html .= "<div class='flex items-center justify-center space-x-4'><div class='flex items-center space-x-1.5'><i data-lucide='shield-check' class='w-4 h-4 text-gray-400'></i><span>" . htmlspecialchars(checkout_t('security_100_secure')) . "</span></div></div>";
    }
    
    // Links de Política e Termos
    $privacy_link = !empty($privacy_url) ? "<a href='" . htmlspecialchars($privacy_url) . "' target='_blank' class='underline hover:text-gray-700'>" . htmlspecialchars(checkout_t('privacy_policy')) . "</a>" : "<a href='#' class='underline hover:text-gray-700'>" . htmlspecialchars(checkout_t('privacy_policy')) . "</a>";
    $terms_link = !empty($terms_url) ? "<a href='" . htmlspecialchars($terms_url) . "' target='_blank' class='underline hover:text-gray-700'>" . htmlspecialchars(checkout_t('terms_of_service')) . "</a>" : "<a href='#' class='underline hover:text-gray-700'>" . htmlspecialchars(checkout_t('terms_of_service')) . "</a>";
    
    $html .= "<p>" . htmlspecialchars(checkout_t('recaptcha_notice')) . "<br>{$privacy_link} " . htmlspecialchars(checkout_t('and_connector')) . " {$terms_link}.</p>";
    $html .= "<p class='pt-4 text-gray-400'>Copyright &copy; " . date("Y") . ". " . htmlspecialchars(checkout_t('copyright_all_rights')) . "</p>";
    $html .= "</div>";
    return $html;
}

function render_sales_notification($config, $produto_nome_fallback) {
    if (!($config['enabled'] ?? false) || empty($config['names'])) return '';
    $notification_product_display = !empty($config['product']) ? $config['product'] : $produto_nome_fallback;
    $suffix = htmlspecialchars(checkout_t('sales_notification_suffix'));
    return "<div id='sales-notification' class='fixed lg:bottom-4 left-4 w-80 bg-white/95 backdrop-blur-sm border border-gray-200 rounded-lg shadow-lg p-4 flex items-center space-x-4 transform translate-y-full opacity-0 transition-all duration-500 z-[9999]'><div class='bg-blue-100 text-blue-600 p-2 rounded-full'><i data-lucide='shopping-cart'></i></div><div><p class='text-sm font-semibold text-gray-900'><span id='notification-name'></span> {$suffix}</p><p class='text-xs text-gray-600' id='notification-product' data-fallback-product-name='".htmlspecialchars($notification_product_display)."'></p></div></div>";
}
?>
<!DOCTYPE html>
<html lang="<?php echo $checkout_lang === 'pt' ? 'pt-BR' : ($checkout_lang === 'en' ? 'en' : $checkout_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — <?php echo htmlspecialchars($produto['nome']); ?></title>
    <?php
    // Adiciona favicon se configurado
    require_once __DIR__ . '/config/config.php';
    $favicon_url_raw = getSystemSetting('favicon_url', '');
    if (!empty($favicon_url_raw)) {
        $favicon_url = ltrim($favicon_url_raw, '/');
        if (strpos($favicon_url, 'http') !== 0) {
            if (strpos($favicon_url, 'uploads/') === 0) {
                $favicon_url = '/' . $favicon_url;
            } else {
                $favicon_url = '/' . $favicon_url;
            }
        }
        $favicon_ext = strtolower(pathinfo($favicon_url, PATHINFO_EXTENSION));
        $favicon_type = 'image/x-icon';
        if ($favicon_ext === 'png') {
            $favicon_type = 'image/png';
        } elseif ($favicon_ext === 'svg') {
            $favicon_type = 'image/svg+xml';
        }
        echo '<link rel="icon" type="' . htmlspecialchars($favicon_type) . '" href="' . htmlspecialchars($favicon_url) . '">' . "\n";
    }
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { 'mono': ['"Roboto Mono"', 'monospace'], }, aspectRatio: { '1/1': '1 / 1', '16/9': '16 / 9' } } },
            plugins: [],
        }
    </script>
    
    <?php 
    // Carregar Mercado Pago SDK APENAS se houver métodos do MP habilitados E tiver public_key
    $has_mp_methods_for_script = ($pix_mercadopago_enabled || $credit_card_mercadopago_enabled || $ticket_enabled);
    $should_load_mp_script = $has_mp_methods_for_script && !empty($public_key) && !isset($_GET['preview']);
    if ($should_load_mp_script): ?>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <?php endif; ?>
    
    <?php 
    // Carregar Stripe v3 APENAS se houver métodos Stripe habilitados E tiver publishable_key
    $has_stripe_methods_for_script = (($credit_card_stripe_enabled ?? false) || ($pix_stripe_enabled ?? false));
    $should_load_stripe_script = $has_stripe_methods_for_script && !empty($stripe_publishable_key) && !isset($_GET['preview']);
    if ($should_load_stripe_script): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>

    <?php 
    // Carregar Beehive SDK APENAS se houver método Beehive habilitado E tiver public_key
    $should_load_beehive_script = $credit_card_beehive_enabled && !empty($beehive_public_key) && !isset($_GET['preview']);
    if ($should_load_beehive_script): ?>
    <script src="https://api.conta.paybeehive.com.br/v1/js"></script>
    <?php endif; ?>
    
    <?php 
    // Carregar FastSoft SDK (Hypercash) APENAS se houver método Hypercash habilitado E tiver public_key
    $should_load_hypercash_script = (isset($credit_card_hypercash_enabled) && $credit_card_hypercash_enabled) && !empty($hypercash_public_key) && !isset($_GET['preview']);
    if ($should_load_hypercash_script): ?>
    <script src="https://js.fastsoftbrasil.com/security.js"></script>
    <?php endif; ?>
    
    <?php 
    // Carregar Efí Payment Token SDK APENAS se houver método Efí Cartão habilitado E tiver payee_code
    $should_load_efi_script = (isset($credit_card_efi_enabled) && $credit_card_efi_enabled) && !empty($efi_payee_code) && !isset($_GET['preview']);
    if ($should_load_efi_script): ?>
    <script src="https://cdn.jsdelivr.net/gh/efipay/js-payment-token-efi/dist/payment-token-efi-umd.min.js"></script>
    <?php endif; ?>

    <!-- Rastreamento (Pixel, Analytics) -->
    <?php if (!empty($fbPixelId) && !isset($_GET['preview'])): ?>
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '<?php echo htmlspecialchars($fbPixelId); ?>');
    fbq('track', 'PageView');
    <?php if (!empty($fb_events_enabled['initiate_checkout'])) { echo "fbq('track', 'InitiateCheckout');"; } ?>
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($fbPixelId); ?>&ev=PageView&noscript=1"/></noscript>
    <?php endif; ?>
    <!-- Fim Rastreamento -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: <?php echo htmlspecialchars($backgroundColor); ?>; }
        .custom-alert { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background-color: #ef4444; color: white; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
        .custom-alert.show { opacity: 1; visibility: visible; }
        #pix-modal-overlay { transition: opacity 0.3s ease-in-out; }
        #pix-modal-content { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
        .order-bump-block { display: block; cursor: pointer; background-color: #f3f4f6; border: 2px dashed #d1d5db; border-radius: 12px; padding: 1rem; position: relative; transition: all 0.3s ease-in-out; }
        .custom-checkbox { width: 24px; height: 24px; border: 2px solid #9ca3af; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease-in-out; }
        .custom-checkbox .checkmark { opacity: 0; transform: scale(0.5); color: white; transition: all 0.2s ease-in-out; width: 16px; height: 16px; }
        .offer-badge { position: absolute; top: -12px; right: 16px; background-color: #ef4444; color: white; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 2px solid white; }
        .order-bump-wrapper input:checked + .order-bump-block { background-color: #f0fdf4; border-color: #22c55e; border-style: dashed; }
        .order-bump-wrapper input:checked + .order-bump-block .custom-checkbox { background-color: #22c55e; border-color: #22c55e; }
        .order-bump-wrapper input:checked + .order-bump-block .custom-checkbox .checkmark { opacity: 1; transform: scale(1); }
        .order-bumps-progressive { opacity: 0; max-height: 0; overflow: hidden; margin: 0; padding: 0; border-width: 0; pointer-events: none; visibility: hidden; transform: translateY(-8px); transition: opacity 250ms ease, max-height 250ms ease, transform 250ms ease, visibility 250ms ease, overflow 0s linear 0s; }
        .order-bumps-progressive.is-revealed { opacity: 1; max-height: 2000px; overflow: visible; pointer-events: auto; visibility: visible; transform: none; transition: opacity 250ms ease, max-height 250ms ease, transform 250ms ease, visibility 250ms ease, overflow 0s linear 250ms; }
        section.order-bumps-progressive.is-revealed { padding-top: 14px; }
        hr.order-bumps-progressive.is-revealed { border-width: 1px 0 0; }
        #sales-notification { visibility: hidden; }
        #sales-notification.show { visibility: visible; transform: translateY(0); opacity: 1; }
        #sales-notification.hide { visibility: hidden; transform: translateY(100%); opacity: 0; }
        .checkout-input { transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .checkout-input:focus { border-color: <?php echo htmlspecialchars($accentColor); ?>; box-shadow: 0 0 0 2px <?php echo htmlspecialchars($accentColor); ?>40; outline: none; }
        .product-checkout-description {
            font-size: 14px;
            color: #6b7280;
            margin-top: 6px;
            margin-bottom: 0;
            line-height: 1.4;
            max-width: 100%;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        @media (max-width: 1023px) {
            .product-checkout-description {
                -webkit-line-clamp: 2;
            }
        }
        .product-summary-title {
            font-size: 14px;
            font-weight: 500;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.35;
            margin: 0;
            color: #111827;
        }
        .product-summary-subline {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.35;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .summary-price-old {
            font-size: 0.75rem;
            color: #9ca3af;
            font-weight: 500;
        }
        .summary-price-final {
            font-size: 1.125rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .product-summary-total-row span:first-child {
            font-size: 1.05rem;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }
        .payment-method-card { transition: all 0.3s ease-in-out; }
        .payment-method-card:hover { transform: translateY(-2px); }
        
        /* Ajustes para Payment Brick no mobile */
        @media (max-width: 1023px) {
            #payment_container_wrapper_credit,
            #payment_container_wrapper_ticket,
            #payment_container_wrapper_pix_mp {
                padding: 0.75rem !important;
                margin-left: -1.5rem;
                margin-right: -1.5rem;
                width: calc(100% + 3rem);
                max-width: calc(100% + 3rem);
                box-sizing: border-box;
            }
            
            #paymentBrick_container_credit,
            #paymentBrick_container_ticket,
            #paymentBrick_container_pix_mp {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            #paymentBrick_container_credit iframe,
            #paymentBrick_container_ticket iframe,
            #paymentBrick_container_pix_mp iframe {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 100% !important;
            }
            
            /* Garantir que inputs dentro do Payment Brick tenham largura completa */
            #payment_container_wrapper_credit *,
            #payment_container_wrapper_ticket *,
            #payment_container_wrapper_pix_mp * {
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            
            /* Ajustar o container pai para dar mais espaço */
            .payment-method-container {
                margin-left: -1rem;
                margin-right: -1rem;
                width: calc(100% + 2rem);
                max-width: calc(100% + 2rem);
            }
        }
    </style>
</head>
<body>
    
    <?php echo render_timer($timerConfig); ?>
    <div id="custom-alert-box" class="custom-alert"></div>
    
    <div class="mx-auto max-w-6xl p-4">
        <?php if (!empty($banners)): ?>
        <div data-id="banner" class="mb-4 space-y-4">
            <?php foreach ($banners as $banner_url): ?>
            <img src="<?php echo htmlspecialchars($banner_url); ?>" alt="Banner do Produto" class="w-full h-auto md:h-[300px] object-cover rounded-lg shadow-md">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php echo render_youtube_video($youtubeUrl); ?>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Coluna Principal -->
            <div class="w-full lg:w-2/3">
                <div class="bg-white rounded-lg shadow-lg p-6 md:p-8 space-y-6">
                    <section data-id="summary" class="flex flex-col gap-3">
                        <div class="flex flex-row items-start gap-4">
                        <img id="checkout-product-main-img" src="<?php echo htmlspecialchars($main_image); ?>" alt="Imagem de <?php echo htmlspecialchars($main_name); ?>" class="w-24 h-24 sm:w-28 sm:h-28 object-cover rounded-lg shadow-md border border-gray-200 flex-shrink-0" onerror="this.src='https://placehold.co/96x96/e2e8f0/334155?text=Produto'">
                        <div class="flex-1">
                            <h1 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($main_name); ?></h1>
                            <?php if ($checkout_description_html !== ''): ?>
                            <p class="product-checkout-description"><?php echo nl2br(htmlspecialchars($checkout_description_html, ENT_QUOTES, 'UTF-8')); ?></p>
                            <?php endif; ?>
                            <div class="flex items-baseline flex-wrap gap-x-3 gap-y-1 mt-2">
                                <span id="hero-main-price" class="text-2xl font-bold" style="color: <?php echo htmlspecialchars($accentColor); ?>;" data-price-brl="<?php echo htmlspecialchars($formattedMainPrice); ?>" data-price-usd="<?php echo $formattedMainPriceUsd ? htmlspecialchars($formattedMainPriceUsd) : ''; ?>"><?php echo $formattedMainPriceUsd ?: $formattedMainPrice; ?></span>
                                <?php if ($formattedPrecoAnterior): ?><span id="hero-preco-anterior" class="text-lg text-gray-400 line-through" data-price-brl="<?php echo htmlspecialchars($formattedPrecoAnterior); ?>" data-price-usd="<?php echo $formattedPrecoAnteriorUsd ? htmlspecialchars($formattedPrecoAnteriorUsd) : ''; ?>"><?php echo $formattedPrecoAnteriorUsd ?: $formattedPrecoAnterior; ?></span><?php endif; ?>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <?php if ($is_free_product): ?>
                                <span class="bg-green-100 text-green-700 text-xs font-bold uppercase px-3 py-1 rounded-full inline-flex items-center gap-1">
                                    <i data-lucide="gift" class="w-3 h-3"></i>
                                    Produto Grátis
                                </span>
                                <?php else: ?>
                                <span class="<?php echo $tipo_acesso_info['color']; ?> text-xs font-bold uppercase px-3 py-1 rounded-full inline-flex items-center gap-1">
                                    <i data-lucide="<?php echo $tipo_acesso === 'vitalicio' ? 'infinity' : 'calendar'; ?>" class="w-3 h-3"></i>
                                    <?php echo $tipo_acesso_info['label']; ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($discount_text)): ?><span class="bg-red-100 text-red-700 text-xs font-bold uppercase px-3 py-1 rounded-full inline-block"><?php echo htmlspecialchars($discount_text); ?></span><?php endif; ?>
                            </div>
                        </div>
                        </div>
                        <?php if (count($checkout_gallery_images) > 1): ?>
                        <div class="flex flex-wrap gap-2 pt-1" id="checkout-product-gallery" role="tablist" aria-label="Galeria do produto">
                            <?php foreach ($checkout_gallery_images as $gi => $gurl): ?>
                            <button type="button" class="checkout-gallery-thumb rounded-lg border-2 overflow-hidden focus:outline-none focus:ring-2 focus:ring-offset-1 <?php echo $gi === 0 ? 'border-green-500 ring-1 ring-green-500/30' : 'border-gray-200 opacity-80 hover:opacity-100'; ?>"
                                    data-src="<?php echo htmlspecialchars($gurl, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="Ver imagem <?php echo (int)($gi + 1); ?>">
                                <img src="<?php echo htmlspecialchars($gurl); ?>" alt="" class="w-14 h-14 object-cover" loading="lazy">
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <script>
                        (function() {
                            var main = document.getElementById('checkout-product-main-img');
                            var wrap = document.getElementById('checkout-product-gallery');
                            if (!main || !wrap) return;
                            wrap.querySelectorAll('.checkout-gallery-thumb').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    var src = btn.getAttribute('data-src');
                                    if (src) main.src = src;
                                    wrap.querySelectorAll('.checkout-gallery-thumb').forEach(function(b) {
                                        b.classList.remove('border-green-500', 'ring-1', 'ring-green-500/30');
                                        b.classList.add('border-gray-200', 'opacity-80');
                                    });
                                    btn.classList.add('border-green-500', 'ring-1', 'ring-green-500/30');
                                    btn.classList.remove('border-gray-200', 'opacity-80');
                                });
                            });
                        })();
                        </script>
                        <?php endif; ?>
                    </section>
                    <hr class="border-gray-200">
                    <section data-id="customer_info">
                        <div class="flex items-center gap-2.5 mb-4"><i data-lucide="clipboard-list" class="w-6 h-6 text-gray-700"></i><h2 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars(checkout_t('section_your_data')); ?></h2></div>
                        <div class="space-y-4">
                            <div><label for="name" class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars(checkout_t('label_name')); ?></label><div class="relative mt-1 rounded-lg shadow-sm"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="user" class="w-5 h-5 text-gray-400"></i></div><input type="text" id="name" name="name" required class="checkout-input mt-1 block w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 text-base" placeholder="<?php echo htmlspecialchars(checkout_t('placeholder_name')); ?>" value="<?php echo htmlspecialchars($prefill_name); ?>"></div></div>
                            <div><label for="email" class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars(checkout_t('label_email')); ?></label><div class="relative mt-1 rounded-lg shadow-sm"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="mail" class="w-5 h-5 text-gray-400"></i></div><input type="email" id="email" name="email" required class="checkout-input mt-1 block w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 text-base" placeholder="<?php echo htmlspecialchars(checkout_t('placeholder_email')); ?>" value="<?php echo htmlspecialchars($prefill_email); ?>"></div></div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php if (($customer_fields_config['enable_phone'] ?? true)): ?>
                                <div><label for="phone" class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars(checkout_t('label_phone')); ?></label><div class="relative mt-1 rounded-lg shadow-sm"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="smartphone" class="w-5 h-5 text-gray-400"></i></div><input type="tel" id="phone" name="phone" required maxlength="15" inputmode="numeric" autocomplete="tel-national" class="checkout-input mt-1 block w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 text-base" placeholder="<?php echo htmlspecialchars(checkout_t('placeholder_phone')); ?>" value="<?php echo htmlspecialchars($prefill_phone); ?>"></div></div>
                                <?php else: ?><input type="hidden" id="phone" name="phone" value="<?php echo htmlspecialchars($prefill_phone !== '' ? $prefill_phone : '(00) 00000-0000'); ?>"><?php endif; ?>
                                <?php if (($customer_fields_config['enable_cpf'] ?? true)): ?>
                                <div><label for="cpf" class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars(checkout_t('label_cpf')); ?></label><div class="relative mt-1 rounded-lg shadow-sm"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="file-text" class="w-5 h-5 text-gray-400"></i></div><input type="text" id="cpf" name="cpf" required maxlength="18" inputmode="numeric" autocomplete="off" class="checkout-input mt-1 block w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 text-base" placeholder="<?php echo htmlspecialchars(checkout_t('placeholder_cpf')); ?>" value="<?php echo htmlspecialchars($prefill_cpf); ?>"></div></div>
                                <?php else: ?><input type="hidden" id="cpf" name="cpf" value="000.000.000-00"><?php endif; ?>
                            </div>
                            <div class="text-left">
                                <button type="button" onclick="openWhyDataModal()" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                                    <?php echo htmlspecialchars(checkout_t('why_data_link')); ?>
                                </button>
                            </div>
                        </div>
                    </section>
                    <?php if ($orderbump_active): ?>
                        <hr class="border-gray-200 order-bumps-progressive">
                        <section data-id="order_bump" class="order-bumps-progressive"><?php echo render_order_bumps_section($order_bumps); ?></section>
                    <?php endif; ?>
                    <?php if (!$is_free_product): ?>
                    <hr class="border-gray-200">
                    <section data-id="coupon" class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700"><?php echo htmlspecialchars(checkout_t('label_coupon')); ?></label>
                        <div class="flex gap-2">
                            <input type="text" id="coupon-input" placeholder="<?php echo htmlspecialchars(checkout_t('placeholder_coupon')); ?>" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 checkout-input">
                            <button type="button" id="btn-aplicar-cupom" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium rounded-lg transition"><?php echo htmlspecialchars(checkout_t('btn_apply_coupon')); ?></button>
                        </div>
                        <div id="coupon-message" class="text-xs hidden"></div>
                    </section>
                    <?php endif; ?>
                    <hr class="border-gray-200">
                    <!-- Renderiza Pagamento ou Produto Grátis -->
                    <section data-id="payment">
                        <?php if ($is_free_product): ?>
                            <?php echo render_free_product_section($accentColor); ?>
                        <?php else: ?>
                            <?php echo render_payment_section($gateway, $accentColor, $payment_methods_config, $pix_pushinpay_enabled, $pix_mercadopago_enabled, $pix_efi_enabled, $credit_card_enabled, $ticket_enabled, $credit_card_beehive_enabled, $credit_card_mercadopago_enabled, $credit_card_hypercash_enabled, $credit_card_efi_enabled, $pix_pagarme_enabled, $pix_stripe_enabled, $credit_card_pagarme_enabled, $credit_card_paypal_enabled, $credit_card_stripe_enabled, $ticket_pagarme_enabled, !empty($public_key), !empty($stripe_publishable_key)); ?>
                        <?php endif; ?>
                    </section>
                    <hr class="border-gray-200">
                    <section data-id="security_info"><?php echo render_security_info($vendedor_nome, $checkout_config['legalLinks']['privacyUrl'] ?? '', $checkout_config['legalLinks']['termsUrl'] ?? ''); ?></section>
                </div>
            </div>

            <!-- Coluna Lateral: Resumo -->
            <aside class="w-full lg:w-1/3 hidden lg:block">
                <div class="sticky top-6 space-y-6">
                    <div class="bg-white rounded-lg shadow-lg p-6 space-y-4" data-id="final_summary">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900 tracking-tight"><?php echo htmlspecialchars(checkout_t('summary_title')); ?></h2>
                        <div class="product-summary-main-row flex gap-3 items-start border-b border-gray-100 pb-3">
                            <div class="min-w-0 flex-1 space-y-1">
                                <p class="product-summary-title"><?php echo htmlspecialchars($main_name); ?></p>
                                <?php if ($checkout_description_html !== ''): ?>
                                <p class="product-summary-subline"><?php echo htmlspecialchars($checkout_description_html, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <?php echo $summary_benefit_badges_html; ?>
                                </div>
                            </div>
                            <div class="product-summary-prices flex-shrink-0 text-right pl-2">
                                <div class="flex flex-col items-end gap-0.5">
                                    <?php if ($formattedPrecoAnterior): ?><span id="summary-preco-anterior" class="summary-price-old line-through" data-price-brl="<?php echo htmlspecialchars($formattedPrecoAnterior); ?>" data-price-usd="<?php echo $formattedPrecoAnteriorUsd ? htmlspecialchars($formattedPrecoAnteriorUsd) : ''; ?>"><?php echo $formattedPrecoAnteriorUsd ?: $formattedPrecoAnterior; ?></span><?php endif; ?>
                                    <span id="summary-main-price" class="summary-price-final" style="color: <?php echo htmlspecialchars($accentColor); ?>;" data-price-brl="<?php echo htmlspecialchars($formattedMainPrice); ?>" data-price-usd="<?php echo $formattedMainPriceUsd ? htmlspecialchars($formattedMainPriceUsd) : ''; ?>"><?php echo $formattedMainPriceUsd ?: $formattedMainPrice; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <?php foreach ($order_bumps as $bump) {
                                $ob_id = intval($bump['ob_id']); $ob_name = htmlspecialchars($bump['ob_nome']); $ob_price = floatval($bump['preco_efetivo_order_bump'] ?? $bump['ob_preco']);
                                $ob_usd = ($main_price_usd !== null && $main_price > 0) ? $ob_price * $main_price_usd / $main_price : null;
                                $ob_brl = 'R$ ' . number_format($ob_price, 2, ',', '.');
                                $ob_usd_str = $ob_usd !== null ? 'US$ ' . number_format($ob_usd, 2, ',', '.') : '';
                                echo "<div id='orderbump-summary-{$ob_id}' class='orderbump-summary-item flex justify-between text-gray-700' style='display: none;' data-price-brl='".htmlspecialchars($ob_brl)."' data-price-usd='".htmlspecialchars($ob_usd_str)."'><span>".htmlspecialchars($ob_name)."</span><span class='ob-price'>".$ob_brl."</span></div>";
                            } ?>
                        </div>
                        <?php if (!$is_free_product): ?>
                        <div id="coupon-discount-row" class="flex justify-between items-center" style="display: none;">
                            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1"><i data-lucide="ticket" class="w-3 h-3"></i><?php echo htmlspecialchars(checkout_t('coupon_label')); ?></span>
                            <span id="coupon-discount-value" class="font-medium text-amber-600">- R$ 0,00</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($pix_discount_enabled && $pix_discount_value > 0 && !$is_free_product): ?>
                        <div id="pix-discount-row" class="pix-discount-summary-row flex justify-between items-center gap-3 py-2.5 px-3 rounded-lg bg-emerald-50/90 border border-emerald-100" style="display: none;">
                            <span class="text-sm font-semibold text-emerald-900 flex items-center gap-1.5 min-w-0">
                                <span class="flex-shrink-0" aria-hidden="true">💰</span>
                                <span><?php echo htmlspecialchars(checkout_t('pix_discount_applied')); ?></span>
                            </span>
                            <span class="pix-discount-value font-bold text-emerald-700 whitespace-nowrap">- R$ 0,00</span>
                        </div>
                        <?php endif; ?>
                        <hr class="border-gray-200">
                        <div class="product-summary-total-row flex justify-between items-end gap-3 pt-1"><span><?php echo htmlspecialchars(checkout_t('total_today')); ?></span><span id="final-total-price" class="text-2xl font-extrabold tracking-tight" style="color: <?php echo htmlspecialchars($accentColor); ?>;" data-price-brl="<?php echo htmlspecialchars($formattedMainPrice); ?>" data-price-usd="<?php echo $formattedMainPriceUsd ? htmlspecialchars($formattedMainPriceUsd) : ''; ?>"><?php echo $formattedMainPriceUsd ?: $formattedMainPrice; ?></span></div>
                        <div class="text-center text-gray-500 text-sm mt-4"><?php echo htmlspecialchars(checkout_t('secure_purchase')); ?></div>
                    </div>
                    <?php if (!empty($sideBanners)): ?>
                    <div class="space-y-4"><?php foreach ($sideBanners as $side_banner_url): ?><img src="<?php echo htmlspecialchars($side_banner_url); ?>" alt="Banner Lateral" class="w-full h-auto object-cover rounded-lg shadow-md"><?php endforeach; ?></div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
        <?php if (!empty($sideBanners)): ?><div class="mt-6 lg:hidden space-y-4"><?php foreach ($sideBanners as $side_banner_url): ?><img src="<?php echo htmlspecialchars($side_banner_url); ?>" alt="Banner Lateral" class="w-full h-auto object-cover rounded-lg shadow-md"><?php endforeach; ?></div><?php endif; ?>
    </div>

    <!-- Footer Mobile -->
    <footer id="mobile-footer" class="lg:hidden fixed bottom-0 left-0 right-0 bg-white shadow-lg p-4 border-t border-gray-200">
        <div id="mobile-summary-items" class="mb-2 text-sm text-gray-700 space-y-1 max-h-20 overflow-y-auto pr-2"></div>
        <div id="coupon-discount-row-mobile" class="flex justify-between items-center mb-2" style="display: none;">
            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-1 rounded-full flex items-center gap-1"><i data-lucide="ticket" class="w-3 h-3"></i><?php echo htmlspecialchars(checkout_t('coupon_label')); ?></span>
            <span id="coupon-discount-value-mobile" class="font-medium text-amber-600 text-sm">- R$ 0,00</span>
        </div>
        <?php if ($pix_discount_enabled && $pix_discount_value > 0): ?>
        <div id="pix-discount-row-mobile" class="pix-discount-summary-row flex justify-between items-center gap-2 mb-2 py-2 px-2.5 rounded-lg bg-emerald-50/90 border border-emerald-100" style="display: none;">
            <span class="text-xs sm:text-sm font-semibold text-emerald-900 flex items-center gap-1 min-w-0">
                <span class="flex-shrink-0" aria-hidden="true">💰</span>
                <span><?php echo htmlspecialchars(checkout_t('pix_discount_applied')); ?></span>
            </span>
            <span class="pix-discount-value font-bold text-emerald-700 text-sm whitespace-nowrap">- R$ 0,00</span>
        </div>
        <?php endif; ?>
        <div class="product-summary-total-row flex justify-between items-end gap-2 mb-2 pt-2 border-t"><span class="text-base font-extrabold text-gray-900"><?php echo htmlspecialchars(checkout_t('total_today')); ?></span><span id="final-total-price-mobile" class="text-2xl font-extrabold tracking-tight" style="color: <?php echo htmlspecialchars($accentColor); ?>;" data-price-brl="<?php echo htmlspecialchars($formattedMainPrice); ?>" data-price-usd="<?php echo $formattedMainPriceUsd ? htmlspecialchars($formattedMainPriceUsd) : ''; ?>"><?php echo $formattedMainPriceUsd ?: $formattedMainPrice; ?></span></div>
        <p class="text-center text-[11px] sm:text-xs text-gray-500 leading-snug px-1"><span class="block"><?php echo htmlspecialchars(checkout_t('secure_purchase')); ?></span><span class="block opacity-80 mt-0.5"><?php echo htmlspecialchars(checkout_t('footer_secure')); ?> <?php echo strtoupper(htmlspecialchars($nome_plataforma)); ?></span></p>
    </footer>
    <div id="mobile-footer-spacer" class="lg:hidden" style="height: 128px;"></div>

    <?php echo render_sales_notification($salesNotificationConfig, $produto['nome']); ?>

    <!-- Modal Sobre CPF/CNPJ -->
    <div id="why-data-modal-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-[10000] flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-300">
        <div id="why-data-modal-content" class="bg-white rounded-xl shadow-2xl w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars(checkout_t('modal_why_cpf_title')); ?></h2>
                <p class="text-gray-600 text-sm leading-relaxed">
                    <?php echo htmlspecialchars(checkout_t('modal_why_cpf_text')); ?>
                </p>
            </div>
            <div class="px-6 pb-6">
                <button type="button" onclick="closeWhyDataModal()" class="w-full text-white font-semibold py-3 rounded-lg transition-colors" style="background-color: #2DD05E;">
                    <?php echo htmlspecialchars(checkout_t('modal_ok')); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal do PIX -->
    <div id="pix-modal-overlay" class="fixed inset-0 bg-black bg-opacity-70 z-[10000] flex items-center justify-center p-4 hidden opacity-0 overflow-y-auto">
        <div id="pix-modal-content" class="bg-white rounded-xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 my-4 max-h-[90vh] overflow-y-auto">
            <div id="pix-waiting-state" class="p-4 sm:p-6 text-center">
                <img src="<?php echo htmlspecialchars($logo_checkout_url); ?>" alt="Logo" class="h-8 sm:h-10 mx-auto mb-4">
                <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars(checkout_t('pix_scan_title')); ?></h2>
                <p class="text-xs sm:text-sm text-gray-600 mb-4"><?php echo htmlspecialchars(checkout_t('pix_scan_desc')); ?></p>
                <div class="w-full max-w-[220px] sm:max-w-[260px] mx-auto mb-4">
                    <div class="aspect-square p-1.5 sm:p-2 bg-white border-4 rounded-lg shadow-lg" style="border-color: <?php echo htmlspecialchars($accentColor); ?>;">
                        <img id="pix-qr-code-img" src="" alt="PIX QR Code" class="w-full h-full object-contain rounded-sm" style="image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges; filter: none;">
                    </div>
                </div>
                <p class="text-center text-xs sm:text-sm text-gray-600 mb-2"><?php echo htmlspecialchars(checkout_t('pix_copy_paste')); ?></p>
                <div class="relative max-w-sm mx-auto mb-4">
                    <input type="text" id="pix-code-input" readonly class="w-full bg-gray-100 p-2.5 sm:p-3 rounded-lg text-xs sm:text-sm text-gray-800 pr-16 sm:pr-20 border border-gray-300">
                    <button id="copy-pix-code-btn" class="absolute right-1 top-1/2 -translate-y-1/2 text-white px-2 sm:px-2.5 py-1 sm:py-1.5 rounded-md text-xs sm:text-sm font-semibold transition-colors" style="background-color: <?php echo htmlspecialchars($accentColor); ?>;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'"><?php echo htmlspecialchars(checkout_t('btn_copy')); ?></button>
                </div>
                <div class="mt-4 flex items-center justify-center gap-2 sm:gap-3 text-gray-500">
                    <svg class="animate-spin h-5 w-5 sm:h-6 sm:w-6" style="color: <?php echo htmlspecialchars($accentColor); ?>;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path></svg>
                    <span class="font-semibold text-sm sm:text-base"><?php echo htmlspecialchars(checkout_t('pix_waiting')); ?></span>
                </div>
            </div>
            <div id="pix-approved-state" class="hidden p-4 sm:p-6 text-center">
                 <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: <?php echo htmlspecialchars($accentColor); ?>20;"><i data-lucide="check" class="w-10 h-10 sm:w-12 sm:h-12" style="color: <?php echo htmlspecialchars($accentColor); ?>;"></i></div>
                 <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars(checkout_t('pix_approved_title')); ?></h2>
                 <p class="text-sm sm:text-base text-gray-600"><?php echo htmlspecialchars(checkout_t('pix_approved_desc')); ?></p>
            </div>
            <div class="bg-gray-50 p-3 sm:p-4 border-t border-gray-200 rounded-b-xl text-center">
                <p class="text-xs text-gray-600"><?php echo htmlspecialchars(checkout_t('payment_processed_for')); ?> <strong class="font-semibold"><?php echo htmlspecialchars($vendedor_nome); ?></strong>.</p>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso do Cartão -->
    <div id="card-success-modal-overlay" class="fixed inset-0 bg-black bg-opacity-70 z-[10000] flex items-center justify-center p-4 hidden opacity-0 overflow-y-auto">
        <div id="card-success-modal-content" class="bg-white rounded-xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 my-4">
            <div class="p-6 sm:p-8 text-center">
                <!-- Animação de Sucesso -->
                <div class="relative w-24 h-24 sm:w-28 sm:h-28 mx-auto mb-6">
                    <div class="absolute inset-0 rounded-full animate-ping opacity-25" style="background-color: <?php echo htmlspecialchars($accentColor); ?>;"></div>
                    <div class="relative w-full h-full rounded-full flex items-center justify-center" style="background-color: <?php echo htmlspecialchars($accentColor); ?>20;">
                        <svg class="w-12 h-12 sm:w-14 sm:h-14 text-green-500 checkmark-animation" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" class="checkmark-path"></path>
                        </svg>
                    </div>
                </div>
                
                <!-- Título -->
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-3">Pagamento Aprovado!</h2>
                
                <!-- Subtítulo -->
                <p class="text-base sm:text-lg text-gray-600 mb-6">Sua compra foi processada com sucesso.</p>
                
                <!-- Informações do Produto -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" style="background-color: <?php echo htmlspecialchars($accentColor); ?>20;">
                            <i data-lucide="package" class="w-5 h-5" style="color: <?php echo htmlspecialchars($accentColor); ?>;"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($main_name); ?></p>
                            <p class="text-sm text-gray-500">Produto adquirido</p>
                        </div>
                    </div>
                </div>
                
                <!-- Aviso sobre E-mail -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i data-lucide="mail" class="w-4 h-4 text-blue-600"></i>
                        </div>
                        <div class="text-left">
                            <p class="font-semibold text-blue-800 text-sm">Verifique seu e-mail</p>
                            <p class="text-xs sm:text-sm text-blue-700 mt-1">Enviamos os detalhes da sua compra para o e-mail cadastrado. <strong>Não esqueça de verificar a caixa de spam!</strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- Contador de Redirecionamento -->
                <div class="flex items-center justify-center gap-2 text-gray-500 mb-4">
                    <svg class="animate-spin h-5 w-5" style="color: <?php echo htmlspecialchars($accentColor); ?>;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
                    </svg>
                    <span class="text-sm">Redirecionando em <span id="card-redirect-countdown" class="font-bold">5</span> segundos...</span>
                </div>
                
                <!-- Botão de Acesso Imediato -->
                <button id="card-success-redirect-btn" class="w-full text-white font-bold py-3 px-6 rounded-lg transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-xl flex items-center justify-center gap-2" style="background-color: <?php echo htmlspecialchars($accentColor); ?>;">
                    <i data-lucide="arrow-right" class="w-5 h-5"></i>
                    Acessar Agora
                </button>
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-50 p-4 border-t border-gray-200 rounded-b-xl">
                <div class="flex items-center justify-center gap-4 text-xs text-gray-500">
                    <div class="flex items-center gap-1">
                        <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                        <span>Compra segura</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <i data-lucide="credit-card" class="w-4 h-4 text-gray-400"></i>
                        <span>Cartão de crédito</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Pagamento Pendente -->
    <div id="pending-modal-overlay" class="fixed inset-0 bg-black bg-opacity-70 z-[10000] flex items-center justify-center p-4 hidden opacity-0 overflow-y-auto">
        <div id="pending-modal-content" class="bg-white rounded-xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 my-4">
            <div class="p-6 sm:p-8 text-center">
                <!-- Ícone de Pendente -->
                <div class="relative w-24 h-24 sm:w-28 sm:h-28 mx-auto mb-6">
                    <div class="absolute inset-0 rounded-full animate-pulse opacity-25 bg-yellow-500"></div>
                    <div class="relative w-full h-full rounded-full bg-yellow-100 flex items-center justify-center">
                        <i data-lucide="clock" class="w-12 h-12 sm:w-14 sm:h-14 text-yellow-600"></i>
                    </div>
                </div>
                
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-3">Pagamento em Análise</h2>
                <p class="text-base sm:text-lg text-gray-600 mb-6"><?php echo htmlspecialchars(checkout_t('card_success_desc')); ?></p>
                
                <!-- Informações -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i data-lucide="info" class="w-4 h-4 text-yellow-600"></i>
                        </div>
                        <div class="text-left">
                            <p class="font-semibold text-yellow-800 text-sm">O que acontece agora?</p>
                            <p class="text-xs sm:text-sm text-yellow-700 mt-1">A operadora do cartão está analisando sua compra. Você receberá um e-mail assim que o pagamento for confirmado. <strong>Verifique também a caixa de spam!</strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- Tempo estimado -->
                <div id="pending-checking-status" class="flex items-center justify-center gap-2 text-gray-500 mb-4">
                    <i data-lucide="loader-2" class="w-5 h-5 text-yellow-600 animate-spin"></i>
                    <span class="text-sm">Verificando status do pagamento, aguarde...</span>
                </div>
                
                <button id="pending-modal-close-btn" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 px-6 rounded-lg transition-all duration-300 flex items-center justify-center gap-2 hidden">
                    <i data-lucide="check" class="w-5 h-5"></i>
                    Entendi
                </button>
            </div>
            
            <div class="bg-gray-50 p-4 border-t border-gray-200 rounded-b-xl">
                <div class="flex items-center justify-center gap-4 text-xs text-gray-500">
                    <div class="flex items-center gap-1">
                        <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                        <span>Compra segura</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <i data-lucide="mail" class="w-4 h-4 text-gray-400"></i>
                        <span>Notificação por e-mail</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Pagamento Recusado -->
    <div id="rejected-modal-overlay" class="fixed inset-0 bg-black bg-opacity-70 z-[10000] flex items-center justify-center p-4 hidden opacity-0 overflow-y-auto">
        <div id="rejected-modal-content" class="bg-white rounded-xl shadow-2xl w-full max-w-md transform scale-95 opacity-0 my-4">
            <div class="p-6 sm:p-8 text-center">
                <!-- Ícone de Erro -->
                <div class="relative w-24 h-24 sm:w-28 sm:h-28 mx-auto mb-6">
                    <div class="absolute inset-0 rounded-full animate-pulse opacity-25 bg-red-500"></div>
                    <div class="relative w-full h-full rounded-full bg-red-100 flex items-center justify-center">
                        <i data-lucide="x-circle" class="w-12 h-12 sm:w-14 sm:h-14 text-red-600"></i>
                    </div>
                </div>
                
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-3">Pagamento Recusado</h2>
                <p id="rejected-modal-message" class="text-base sm:text-lg text-gray-600 mb-6"><?php echo htmlspecialchars(checkout_t('rejected_message')); ?></p>
                
                <!-- Sugestões -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="text-left">
                        <p class="font-semibold text-red-800 text-sm mb-2 flex items-center gap-2">
                            <i data-lucide="lightbulb" class="w-4 h-4"></i>
                            O que você pode fazer:
                        </p>
                        <ul class="text-xs sm:text-sm text-red-700 space-y-1.5 ml-6">
                            <li class="flex items-start gap-2">
                                <span class="text-red-400">•</span>
                                <span>Verifique se os dados do cartão estão corretos</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-red-400">•</span>
                                <span>Confirme se há limite disponível</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-red-400">•</span>
                                <span>Tente outro cartão ou método de pagamento</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-red-400">•</span>
                                <span>Entre em contato com seu banco</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <button id="rejected-modal-close-btn" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-lg transition-all duration-300 flex items-center justify-center gap-2">
                    <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    Tentar Novamente
                </button>
            </div>
            
            <div class="bg-gray-50 p-4 border-t border-gray-200 rounded-b-xl">
                <div class="flex items-center justify-center gap-4 text-xs text-gray-500">
                    <div class="flex items-center gap-1">
                        <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                        <span>Seus dados estão seguros</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <i data-lucide="credit-card" class="w-4 h-4 text-gray-400"></i>
                        <span>Tente outro cartão</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* Animação do checkmark */
        .checkmark-path {
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: checkmark-draw 0.6s ease-out 0.3s forwards;
        }
        
        @keyframes checkmark-draw {
            to {
                stroke-dashoffset: 0;
            }
        }
        
        /* Animação de entrada do modal */
        #card-success-modal-content,
        #pending-modal-content,
        #rejected-modal-content {
            animation: modal-bounce-in 0.5s ease-out forwards;
        }
        
        @keyframes modal-bounce-in {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }
            50% {
                transform: scale(1.02);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>

    <script>
        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
        let checkoutSessionUUID = generateUUID();
        localStorage.setItem('GatewayPro_checkout_session_uuid', checkoutSessionUUID);

        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            
            // Funções do Modal "Porque pedimos esse dado?"
            window.openWhyDataModal = function() {
                const modalOverlay = document.getElementById('why-data-modal-overlay');
                const modalContent = document.getElementById('why-data-modal-content');
                if (modalOverlay && modalContent) {
                    modalOverlay.classList.remove('hidden');
                    setTimeout(() => {
                        modalOverlay.classList.remove('opacity-0');
                        modalContent.classList.remove('scale-95', 'opacity-0');
                    }, 10);
                }
            };
            
            window.closeWhyDataModal = function() {
                const modalOverlay = document.getElementById('why-data-modal-overlay');
                const modalContent = document.getElementById('why-data-modal-content');
                if (modalOverlay && modalContent) {
                    modalOverlay.classList.add('opacity-0');
                    modalContent.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => {
                        modalOverlay.classList.add('hidden');
                    }, 300);
                }
            };
            
            // Fechar modal ao clicar fora
            document.getElementById('why-data-modal-overlay')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeWhyDataModal();
                }
            });
            
            let paymentCheckInterval;
            let notificationTimer;
            let customRedirectUrl = '<?php echo htmlspecialchars($redirectUrlConfig ?? '', ENT_QUOTES, 'UTF-8'); ?>'; // URL de redirecionamento personalizada
            
            const getCouponPayload = () => couponApplied ? { cupom_id: couponApplied.cupom_id, valor_desconto: couponApplied.valor_desconto } : {};
            const pixModalOverlay = document.getElementById('pix-modal-overlay');
            const pixModalContent = document.getElementById('pix-modal-content');
            const mainProductPrice = <?php echo (float)$main_price; ?>;
            const mainProductPriceUsd = <?php echo $main_price_usd !== null ? (float)$main_price_usd : 'null'; ?>;
            const productCurrency = '<?php echo (!empty($produto['price_usd']) && $produto['price_usd'] > 0) ? 'usd' : 'brl'; ?>';
            const mainPrecoAnteriorBrl = <?php echo $formattedPrecoAnterior ? json_encode($formattedPrecoAnterior) : 'null'; ?>;
            const mainPrecoAnteriorUsd = <?php echo $formattedPrecoAnteriorUsd ? json_encode($formattedPrecoAnteriorUsd) : 'null'; ?>;
            const checkoutHash = '<?php echo htmlspecialchars($checkout_hash ?? '', ENT_QUOTES, 'UTF-8'); ?>';
            const infoprodutorId = <?php echo (int)$infoprodutor_id; ?>;
            const mainProductId = <?php echo (int)$produto['id']; ?>;
            const CHECKOUT_RECOVERY_OBSERVE = <?php echo getSystemSetting('checkout_recovery_observe', '0') === '1' ? 'true' : 'false'; ?>;
            const summaryMainName = <?php echo json_encode($main_name, JSON_UNESCAPED_UNICODE); ?>;
            const summarySublineRaw = <?php echo $checkout_description_html !== '' ? json_encode($checkout_description_html, JSON_UNESCAPED_UNICODE) : 'null'; ?>;
            const summaryBadgesHtml = <?php echo json_encode($summary_benefit_badges_html, JSON_UNESCAPED_UNICODE); ?>;
            const summaryAccentColor = <?php echo json_encode($accentColor, JSON_UNESCAPED_UNICODE); ?>;
            const ofertaId = <?php echo isset($produto['oferta_id']) ? (int)$produto['oferta_id'] : 'null'; ?>;
            const funnelMainPaymentId = <?php echo $funnel_main_payment_id ? json_encode($funnel_main_payment_id) : 'null'; ?>;
            const funnelStepParam = <?php echo $funnel_step_param ? json_encode($funnel_step_param) : 'null'; ?>;
            const activeGateway = '<?php echo $gateway; ?>';
            let currentAmount = mainProductPrice;
            let acceptedOrderBumps = [];
            let couponApplied = null; // { cupom_id, valor_desconto }
            let selectedPaymentMethod = null; // Declarado aqui para evitar erro de referência
            // Stripe usa Checkout Session (redirecionamento) - Stripe.js carregado apenas para pix_stripe se necessário
            
            // Configuração de desconto Pix
            // i18n: labels para JS (validação, erros, Pix)
            window.checkoutLabels = <?php echo json_encode([
                'alert_fill_cpf' => checkout_t('alert_fill_cpf'),
                'alert_valid_cpf' => checkout_t('alert_valid_cpf'),
                'alert_fill_name_email' => checkout_t('alert_fill_name_email'),
                'alert_fill_phone' => checkout_t('alert_fill_phone'),
                'btn_generate_pix' => checkout_t('btn_generate_pix'),
                'loading_generating_pix' => checkout_t('loading_generating_pix'),
                'error_rejected' => checkout_t('error_payment_rejected'),
                'error_cancelled' => checkout_t('error_payment_cancelled'),
                'error_generic' => checkout_t('error_payment_generic'),
                'try_other_method' => checkout_t('try_other_method'),
                'pending' => checkout_t('status_pending'),
                'in_process' => checkout_t('status_in_process'),
                'refunded' => checkout_t('status_refunded'),
                'charged_back' => checkout_t('status_charged_back'),
            ]); ?>;
            
            const pixDiscountConfig = {
                enabled: <?php echo $pix_discount_enabled ? 'true' : 'false'; ?>,
                type: '<?php echo $pix_discount_type; ?>',
                value: <?php echo $pix_discount_value; ?>
            };
            
            const finalTotalElement = document.getElementById('final-total-price');
            const finalTotalMobileElement = document.getElementById('final-total-price-mobile');
            // Exibir USD no checkout quando produto tem price_usd e método NÃO é Pix BR (melhor conversão internacional)
            const isBrazilianPixMethod = (m) => ['pix_efi', 'pix_pushinpay', 'pix_pagarme'].includes(m || '');
            const shouldDisplayUsd = () => productCurrency === 'usd' && mainProductPriceUsd && !isBrazilianPixMethod(selectedPaymentMethod);
            const mobileSummaryItemsContainer = document.getElementById('mobile-summary-items');
            const orderbumpCheckboxes = document.querySelectorAll('.orderbump-checkbox');
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const phoneInput = document.getElementById('phone');
            const cpfInput = document.getElementById('cpf');

            // --- Observação de jornada (paralela ao pagamento; fail-open; flag off = zero requests) ---
            const checkoutObserveNativeFetch = (typeof window.fetch === 'function') ? window.fetch.bind(window) : null;
            let checkoutObserveLastMethod = null;
            let checkoutObserveLastCustomerAt = 0;
            let checkoutObserveLastCustomerKey = '';
            let checkoutObserveLastPixKey = '';

            const checkoutObserveBrowserUuid = generateUUID();
            try {
                sessionStorage.setItem('GatewayPro_observe_sid', checkoutObserveBrowserUuid);
            } catch (e) {}

            function checkoutObserveGetBrowserUuid() {
                return checkoutObserveBrowserUuid;
            }

            function checkoutObserveIsProcessPaymentRequest(input) {
                let raw = '';
                if (typeof input === 'string') {
                    raw = input;
                } else if (typeof URL !== 'undefined' && input instanceof URL) {
                    raw = input.href;
                } else if (input && typeof input.url === 'string') {
                    raw = input.url;
                } else {
                    return false;
                }
                let path = '';
                try {
                    if (/^https?:\/\//i.test(raw)) {
                        path = new URL(raw).pathname;
                    } else {
                        const rel = raw.split('?')[0].split('#')[0];
                        path = rel.charAt(0) === '/' ? rel : new URL(raw, window.location.href).pathname;
                    }
                } catch (e) {
                    return false;
                }
                path = path.replace(/\/+$/, '') || '/';
                return path === '/process_payment' || path === '/process_payment.php';
            }

            function observeCheckoutEvent(eventName, extra) {
                if (!CHECKOUT_RECOVERY_OBSERVE || !checkoutObserveNativeFetch) return;
                try {
                    const body = {
                        event_name: eventName,
                        produto_id: mainProductId,
                        browser_uuid: checkoutObserveGetBrowserUuid()
                    };
                    if (ofertaId) body.oferta_id = ofertaId;
                    if (extra && typeof extra === 'object') {
                        if (extra.nome) body.nome = extra.nome;
                        if (extra.email) body.email = extra.email;
                        if (extra.telefone) body.telefone = extra.telefone;
                        if (extra.payment_method) body.payment_method = extra.payment_method;
                        if (Array.isArray(extra.order_bump_product_ids) && extra.order_bump_product_ids.length) {
                            body.order_bump_product_ids = extra.order_bump_product_ids;
                        }
                        if (extra.coupon_code) body.coupon_code = extra.coupon_code;
                        if (extra.transacao_id) body.transacao_id = extra.transacao_id;
                    }
                    const controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                    const timer = controller ? setTimeout(function() { try { controller.abort(); } catch (e) {} }, 2000) : null;
                    const opts = {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body)
                    };
                    if (controller) opts.signal = controller.signal;
                    const pending = checkoutObserveNativeFetch('/api/checkout_session_observe', opts);
                    if (pending && typeof pending.catch === 'function') {
                        pending.catch(function() {});
                    }
                    if (pending && typeof pending.finally === 'function') {
                        pending.finally(function() { if (timer) clearTimeout(timer); });
                    } else if (timer) {
                        setTimeout(function() { clearTimeout(timer); }, 2000);
                    }
                } catch (e) {}
            }

            if (CHECKOUT_RECOVERY_OBSERVE) {
                try {
                    observeCheckoutEvent('opened');
                } catch (e) {}

                const checkoutObserveOriginalFetch = window.fetch;
                window.fetch = function() {
                    try {
                        if (checkoutObserveIsProcessPaymentRequest(arguments[0])) {
                            const extra = {};
                            if (selectedPaymentMethod) extra.payment_method = selectedPaymentMethod;
                            if (Array.isArray(acceptedOrderBumps) && acceptedOrderBumps.length) {
                                extra.order_bump_product_ids = acceptedOrderBumps.slice();
                            }
                            const couponEl = document.getElementById('coupon-input');
                            if (couponApplied && couponEl && couponEl.value) extra.coupon_code = couponEl.value.trim();
                            observeCheckoutEvent('payment_attempted', extra);
                        }
                    } catch (e) {}
                    return checkoutObserveOriginalFetch.apply(this, arguments);
                };

                const checkoutObserveCustomerBlur = function() {
                    try {
                        const nome = nameInput ? String(nameInput.value || '').trim() : '';
                        const email = emailInput ? String(emailInput.value || '').trim() : '';
                        if (!nome || !email) return;
                        const phoneEl = document.getElementById('phone');
                        let telefone = phoneEl ? String(phoneEl.value || '').replace(/\D+/g, '') : '';
                        if (!telefone || telefone.replace(/0/g, '') === '' || telefone.length < 8) telefone = '';
                        const key = nome + '|' + email + '|' + telefone;
                        const now = Date.now();
                        if (key === checkoutObserveLastCustomerKey && (now - checkoutObserveLastCustomerAt) < 2000) return;
                        checkoutObserveLastCustomerKey = key;
                        checkoutObserveLastCustomerAt = now;
                        const extra = { nome: nome, email: email };
                        if (telefone) extra.telefone = telefone;
                        observeCheckoutEvent('customer_info', extra);
                    } catch (e) {}
                };
                if (nameInput) nameInput.addEventListener('blur', checkoutObserveCustomerBlur);
                if (emailInput) emailInput.addEventListener('blur', checkoutObserveCustomerBlur);
                if (phoneInput) phoneInput.addEventListener('blur', checkoutObserveCustomerBlur);
            }
            const customerFieldsConfig = <?php echo json_encode($customer_fields_config); ?>;
            let emailAlreadyExists = false; // Flag para indicar se o e-mail já existe no sistema

            // Função para aplicar máscara dinâmica de CPF/CNPJ
            function formatCpfCnpj(value) {
                // Remove tudo que não é número
                const numbers = value.replace(/\D/g, '');
                
                if (numbers.length <= 11) {
                    // Formato CPF: 000.000.000-00
                    return numbers
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                } else {
                    // Formato CNPJ: 00.000.000/0000-00
                    return numbers
                        .substring(0, 14)
                        .replace(/(\d{2})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d)/, '$1/$2')
                        .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                }
            }

            // Aplica máscara no campo CPF/CNPJ
            if (cpfInput) {
                cpfInput.addEventListener('input', function(e) {
                    const cursorPos = e.target.selectionStart;
                    const oldLength = e.target.value.length;
                    e.target.value = formatCpfCnpj(e.target.value);
                    const newLength = e.target.value.length;
                    // Ajusta posição do cursor
                    const newCursorPos = cursorPos + (newLength - oldLength);
                    e.target.setSelectionRange(newCursorPos, newCursorPos);
                });
            }

            // Função para verificar se o e-mail já está cadastrado
            async function checkEmailExists(email) {
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
                
                try {
                    const response = await fetch('/api/check_email.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: email })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success && result.exists) {
                        emailAlreadyExists = true;
                        showEmailExistsAlert();
                    } else {
                        emailAlreadyExists = false;
                        hideEmailExistsAlert();
                    }
                } catch (e) {
                    console.error('Erro ao verificar e-mail:', e);
                }
            }
            
            // Função para mostrar alerta de e-mail existente
            function showEmailExistsAlert() {
                // Remove alerta anterior se existir
                hideEmailExistsAlert();
                
                const emailField = document.getElementById('email');
                if (!emailField) return;
                
                const alertDiv = document.createElement('div');
                alertDiv.id = 'email-exists-alert';
                alertDiv.className = 'mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-start gap-2';
                alertDiv.innerHTML = `
                    <i data-lucide="info" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                    <div>
                        <p class="text-sm text-blue-800 font-medium">E-mail já cadastrado!</p>
                        <p class="text-xs text-blue-600 mt-1">Sua senha de acesso à área de membros será a mesma utilizada anteriormente. Caso não lembre, utilize a opção "Esqueci minha senha" na tela de login.</p>
                    </div>
                `;
                
                // Insere após o campo de e-mail
                emailField.parentElement.parentElement.appendChild(alertDiv);
                lucide.createIcons();
            }
            
            // Função para esconder alerta de e-mail existente
            function hideEmailExistsAlert() {
                const existingAlert = document.getElementById('email-exists-alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
            }
            
            // Listener para verificar e-mail quando o usuário sair do campo
            let emailCheckTimeout;
            emailInput.addEventListener('blur', () => {
                const email = emailInput.value.trim();
                if (email) {
                    checkEmailExists(email);
                }
            });
            
            // Também verifica enquanto digita (com debounce)
            emailInput.addEventListener('input', () => {
                clearTimeout(emailCheckTimeout);
                emailCheckTimeout = setTimeout(() => {
                    const email = emailInput.value.trim();
                    if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        checkEmailExists(email);
                    } else {
                        hideEmailExistsAlert();
                    }
                }, 800); // Aguarda 800ms após parar de digitar
            });

            function updateMobileLayout() {
                const footer = document.getElementById('mobile-footer');
                const spacer = document.getElementById('mobile-footer-spacer');
                const notification = document.getElementById('sales-notification');
                if (footer && spacer && window.innerWidth < 1024) {
                    const footerHeight = footer.offsetHeight;
                    spacer.style.height = footerHeight + 'px';
                    if (notification) notification.style.bottom = (footerHeight + 16) + 'px';
                } else if (spacer) {
                    spacer.style.height = '0px';
                    if (notification) notification.style.bottom = '';
                }
            }
            window.addEventListener('resize', updateMobileLayout);
            
            function getUrlUtmParameters() {
                const urlParams = new URLSearchParams(window.location.search);
                const utmParams = {};
                ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'src', 'sck'].forEach(key => { utmParams[key] = urlParams.get(key); });
                return utmParams;
            }
            const utmParameters = getUrlUtmParameters();

            function checkoutDigitsOnly(s) {
                return String(s || '').replace(/\D/g, '');
            }

            /** Máscara (00) 0000-0000 ou (00) 00000-0000 — compatível com validação Efí (DDD + 8 ou 9 dígitos). */
            function applyBrazilPhoneMask(input) {
                if (!input || input.type === 'hidden') return;
                const format = () => {
                    let d = checkoutDigitsOnly(input.value);
                    if (d.length > 11) d = d.slice(0, 11);
                    if (d.length === 0) { input.value = ''; return; }
                    if (d.length <= 2) input.value = '(' + d;
                    else if (d.length <= 6) input.value = '(' + d.slice(0, 2) + ') ' + d.slice(2);
                    else if (d.length <= 10) input.value = '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
                    else input.value = '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7, 11);
                };
                input.addEventListener('input', format);
                input.addEventListener('blur', format);
                if (input.value) format();
            }

            /** CPF 000.000.000-00 ou CNPJ 00.000.000/0000-00 conforme quantidade de dígitos. */
            function applyCpfCnpjMask(input) {
                if (!input || input.type === 'hidden') return;
                const format = () => {
                    let d = checkoutDigitsOnly(input.value);
                    if (d.length > 14) d = d.slice(0, 14);
                    if (d.length <= 11) {
                        let out = d.slice(0, 3);
                        if (d.length > 3) out += '.' + d.slice(3, 6);
                        if (d.length > 6) out += '.' + d.slice(6, 9);
                        if (d.length > 9) out += '-' + d.slice(9, 11);
                        input.value = out;
                    } else {
                        let out = d.slice(0, 2);
                        if (d.length > 2) out += '.' + d.slice(2, 5);
                        if (d.length > 5) out += '.' + d.slice(5, 8);
                        if (d.length > 8) out += '/' + d.slice(8, 12);
                        if (d.length > 12) out += '-' + d.slice(12, 14);
                        input.value = out;
                    }
                };
                input.addEventListener('input', format);
                input.addEventListener('blur', format);
                if (input.value) format();
            }

            applyBrazilPhoneMask(document.getElementById('phone'));
            applyCpfCnpjMask(document.getElementById('cpf'));

            // Order bumps progressivos: ocultos até dados básicos válidos (não altera validateForm / totais / pagamento)
            (function initProgressiveOrderBumps() {
                if (!orderbumpCheckboxes || orderbumpCheckboxes.length === 0) return;

                const progressiveEls = document.querySelectorAll('.order-bumps-progressive');
                if (!progressiveEls.length) return;

                let orderBumpsRevealed = false;

                function revealOrderBumps() {
                    if (orderBumpsRevealed) return;
                    orderBumpsRevealed = true;
                    progressiveEls.forEach(function(el) { el.classList.add('is-revealed'); });
                }

                function basicCustomerDataReady() {
                    const name = nameInput ? String(nameInput.value || '').trim() : '';
                    if (!name) return false;

                    const email = emailInput ? String(emailInput.value || '').trim() : '';
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return false;

                    const phoneEl = document.getElementById('phone');
                    if (phoneEl && phoneEl.type !== 'hidden') {
                        const phoneDigits = checkoutDigitsOnly(phoneEl.value);
                        if (phoneDigits.length !== 10 && phoneDigits.length !== 11) return false;
                    }

                    const cpfEl = document.getElementById('cpf');
                    if (cpfEl && cpfEl.type !== 'hidden') {
                        const cpfDigits = checkoutDigitsOnly(cpfEl.value);
                        if (cpfDigits.length !== 11 && cpfDigits.length !== 14) return false;
                    }

                    return true;
                }

                function maybeRevealOrderBumps() {
                    if (orderBumpsRevealed) return;
                    let anyChecked = false;
                    orderbumpCheckboxes.forEach(function(cb) {
                        if (cb.checked) anyChecked = true;
                    });
                    if (anyChecked || basicCustomerDataReady()) {
                        revealOrderBumps();
                    }
                }

                ['input', 'change', 'blur'].forEach(function(evt) {
                    if (nameInput) nameInput.addEventListener(evt, maybeRevealOrderBumps);
                    if (emailInput) emailInput.addEventListener(evt, maybeRevealOrderBumps);
                    if (phoneInput) phoneInput.addEventListener(evt, maybeRevealOrderBumps);
                    if (cpfInput) cpfInput.addEventListener(evt, maybeRevealOrderBumps);
                });

                window.addEventListener('pageshow', maybeRevealOrderBumps);
                maybeRevealOrderBumps();
                setTimeout(maybeRevealOrderBumps, 400);
            })();
            
            // Função para calcular desconto Pix
            function calculatePixDiscount(amount) {
                if (!pixDiscountConfig.enabled || pixDiscountConfig.value <= 0) return 0;
                if (pixDiscountConfig.type === 'percentage') {
                    return amount * (pixDiscountConfig.value / 100);
                } else {
                    return Math.min(pixDiscountConfig.value, amount); // Não pode ser maior que o valor
                }
            }

            const updateDisplayCurrency = () => {
                const useUsd = shouldDisplayUsd();
                const heroPrice = document.getElementById('hero-main-price');
                const heroPrev = document.getElementById('hero-preco-anterior');
                const summaryPrice = document.getElementById('summary-main-price');
                const summaryPrev = document.getElementById('summary-preco-anterior');
                if (heroPrice) heroPrice.textContent = useUsd && heroPrice.dataset.priceUsd ? heroPrice.dataset.priceUsd : heroPrice.dataset.priceBrl;
                if (heroPrev) {
                    if (useUsd && heroPrev.dataset.priceUsd) heroPrev.textContent = heroPrev.dataset.priceUsd;
                    else if (heroPrev.dataset.priceBrl) heroPrev.textContent = heroPrev.dataset.priceBrl;
                }
                if (summaryPrice) summaryPrice.textContent = useUsd && summaryPrice.dataset.priceUsd ? summaryPrice.dataset.priceUsd : summaryPrice.dataset.priceBrl;
                if (summaryPrev) {
                    if (useUsd && summaryPrev.dataset.priceUsd) summaryPrev.textContent = summaryPrev.dataset.priceUsd;
                    else if (summaryPrev.dataset.priceBrl) summaryPrev.textContent = summaryPrev.dataset.priceBrl;
                }
                document.querySelectorAll('.orderbump-summary-item .ob-price').forEach(el => {
                    const row = el.closest('.orderbump-summary-item');
                    if (row && row.dataset.priceUsd) el.textContent = useUsd ? row.dataset.priceUsd : row.dataset.priceBrl;
                });
            };

            const updateSummaryAndTotal = () => {
                updateDisplayCurrency();
                currentAmount = mainProductPrice;
                acceptedOrderBumps = [];
                document.querySelectorAll('.orderbump-summary-item').forEach(item => item.style.display = 'none');
                if (mobileSummaryItemsContainer) {
                    mobileSummaryItemsContainer.innerHTML = '';
                    const mainPriceDisplay = shouldDisplayUsd() && mainProductPriceUsd ? `US$ ${mainProductPriceUsd.toFixed(2).replace('.', ',')}` : `R$ ${mainProductPrice.toFixed(2).replace('.', ',')}`;
                    const mainPrecoAnteriorDisplay = (mainPrecoAnteriorBrl || mainPrecoAnteriorUsd) ? (shouldDisplayUsd() && mainPrecoAnteriorUsd ? mainPrecoAnteriorUsd : mainPrecoAnteriorBrl) : null;
                    const root = document.createElement('div');
                    root.className = 'product-summary-mobile-root';
                    const row = document.createElement('div');
                    row.className = 'flex gap-3 items-start';
                    const left = document.createElement('div');
                    left.className = 'min-w-0 flex-1 space-y-1';
                    const titleP = document.createElement('p');
                    titleP.className = 'product-summary-title';
                    titleP.textContent = summaryMainName;
                    left.appendChild(titleP);
                    if (summarySublineRaw) {
                        const subP = document.createElement('p');
                        subP.className = 'product-summary-subline';
                        subP.textContent = summarySublineRaw;
                        left.appendChild(subP);
                    }
                    const badgeWrap = document.createElement('div');
                    badgeWrap.className = 'flex flex-wrap gap-1.5 pt-0.5';
                    badgeWrap.innerHTML = summaryBadgesHtml;
                    left.appendChild(badgeWrap);
                    const right = document.createElement('div');
                    right.className = 'product-summary-prices flex-shrink-0 text-right pl-2';
                    const priceCol = document.createElement('div');
                    priceCol.className = 'flex flex-col items-end gap-0.5';
                    if (mainPrecoAnteriorDisplay) {
                        const prev = document.createElement('span');
                        prev.className = 'summary-price-old line-through';
                        prev.textContent = mainPrecoAnteriorDisplay;
                        priceCol.appendChild(prev);
                    }
                    const cur = document.createElement('span');
                    cur.className = 'summary-price-final text-base';
                    cur.style.color = summaryAccentColor || '';
                    cur.textContent = mainPriceDisplay;
                    priceCol.appendChild(cur);
                    right.appendChild(priceCol);
                    row.appendChild(left);
                    row.appendChild(right);
                    root.appendChild(row);
                    mobileSummaryItemsContainer.appendChild(root);
                }
                orderbumpCheckboxes.forEach(checkbox => {
                    const productId = parseInt(checkbox.dataset.productId);
                    const summaryItem = document.getElementById(`orderbump-summary-${productId}`);
                    if (checkbox.checked) {
                        const price = parseFloat(checkbox.dataset.price);
                        const name = checkbox.dataset.name;
                        currentAmount += price;
                        acceptedOrderBumps.push(productId);
                        if(summaryItem) summaryItem.style.display = 'flex';
                        if (mobileSummaryItemsContainer && name) {
                            const itemEl = document.createElement('div');
                            itemEl.className = 'flex justify-between';
                            const bumpDisplay = shouldDisplayUsd() && mainProductPrice > 0 ? `US$ ${(price * mainProductPriceUsd / mainProductPrice).toFixed(2).replace('.', ',')}` : `R$ ${price.toFixed(2).replace('.', ',')}`;
                            itemEl.innerHTML = `<span>${name}</span><span class="font-medium">${bumpDisplay}</span>`;
                            mobileSummaryItemsContainer.appendChild(itemEl);
                        }
                    }
                });
                
                // Aplicar desconto cupom
                let couponDiscountAmount = couponApplied ? (couponApplied.valor_desconto || 0) : 0;
                let displayAmount = currentAmount - couponDiscountAmount;
                if (displayAmount < 0) displayAmount = 0;

                // Mostrar/ocultar linha de desconto cupom
                const couponDiscountRow = document.getElementById('coupon-discount-row');
                const couponDiscountRowMobile = document.getElementById('coupon-discount-row-mobile');
                const couponValEl = document.getElementById('coupon-discount-value');
                const couponValMobile = document.getElementById('coupon-discount-value-mobile');
                const couponValStr = '- R$ ' + couponDiscountAmount.toFixed(2).replace('.', ',');
                if (couponDiscountAmount > 0) {
                    if (couponDiscountRow) { couponDiscountRow.style.display = 'flex'; }
                    if (couponValEl) couponValEl.textContent = couponValStr;
                    if (couponDiscountRowMobile) { couponDiscountRowMobile.style.display = 'flex'; }
                    if (couponValMobile) couponValMobile.textContent = couponValStr;
                } else {
                    if (couponDiscountRow) couponDiscountRow.style.display = 'none';
                    if (couponDiscountRowMobile) couponDiscountRowMobile.style.display = 'none';
                }

                // Aplicar desconto Pix se método Pix estiver selecionado (sobre o valor já com cupom)
                let pixDiscountAmount = 0;
                const isPixSelected = selectedPaymentMethod === 'pix' || selectedPaymentMethod === 'pix_mercadopago' || selectedPaymentMethod === 'pix_pushinpay' || selectedPaymentMethod === 'pix_efi' || selectedPaymentMethod === 'pix_pagarme' || selectedPaymentMethod === 'pix_stripe';
                
                if (isPixSelected && pixDiscountConfig.enabled) {
                    pixDiscountAmount = calculatePixDiscount(displayAmount);
                    displayAmount = displayAmount - pixDiscountAmount;
                }
                
                // Mostrar/ocultar linha de desconto Pix
                const pixDiscountRow = document.getElementById('pix-discount-row');
                const pixDiscountRowMobile = document.getElementById('pix-discount-row-mobile');
                if (pixDiscountRow) {
                    if (isPixSelected && pixDiscountAmount > 0) {
                        pixDiscountRow.style.display = 'flex';
                        pixDiscountRow.querySelector('.pix-discount-value').textContent = `- R$ ${pixDiscountAmount.toFixed(2).replace('.', ',')}`;
                    } else {
                        pixDiscountRow.style.display = 'none';
                    }
                }
                if (pixDiscountRowMobile) {
                    if (isPixSelected && pixDiscountAmount > 0) {
                        pixDiscountRowMobile.style.display = 'flex';
                        pixDiscountRowMobile.querySelector('.pix-discount-value').textContent = `- R$ ${pixDiscountAmount.toFixed(2).replace('.', ',')}`;
                    } else {
                        pixDiscountRowMobile.style.display = 'none';
                    }
                }
                
                let totalText;
                if (shouldDisplayUsd()) {
                    const rate = mainProductPrice > 0 ? mainProductPriceUsd / mainProductPrice : 1;
                    const totalUsd = mainProductPriceUsd + (currentAmount - mainProductPrice) * rate;
                    totalText = `US$ ${totalUsd.toFixed(2).replace('.', ',')}`;
                } else {
                    totalText = `R$ ${displayAmount.toFixed(2).replace('.', ',')}`;
                }
                if (finalTotalElement) finalTotalElement.textContent = totalText;
                if (finalTotalMobileElement) finalTotalMobileElement.textContent = totalText;
                
                // Atualizar currentAmount para o valor final (cupom + pix) para envio ao backend
                currentAmount = displayAmount;
                
                updateMobileLayout();
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            };
            
            // Cupom: aplicar
            const couponInput = document.getElementById('coupon-input');
            const btnAplicarCupom = document.getElementById('btn-aplicar-cupom');
            const couponMessage = document.getElementById('coupon-message');
            if (btnAplicarCupom && couponInput) {
                btnAplicarCupom.addEventListener('click', async () => {
                    const codigo = couponInput.value.trim();
                    if (!codigo) {
                        if (couponMessage) { couponMessage.textContent = 'Digite o código do cupom'; couponMessage.classList.remove('hidden'); couponMessage.classList.add('text-red-600'); }
                        return;
                    }
                    btnAplicarCupom.disabled = true;
                    try {
                        let baseTotal = mainProductPrice;
                        orderbumpCheckboxes.forEach(cb => {
                            if (cb.checked) baseTotal += parseFloat(cb.dataset.price || 0);
                        });
                        const r = await fetch('/api/api.php?action=validate_coupon', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ codigo, produto_id: mainProductId, valor_total: baseTotal })
                        });
                        const data = await r.json();
                        if (data.valid && data.cupom_id && data.valor_desconto >= 0) {
                            couponApplied = { cupom_id: data.cupom_id, valor_desconto: data.valor_desconto };
                            if (couponMessage) { couponMessage.textContent = data.mensagem || 'Cupom aplicado!'; couponMessage.classList.remove('text-red-600'); couponMessage.classList.add('text-green-600'); couponMessage.classList.remove('hidden'); }
                            couponInput.disabled = true;
                            btnAplicarCupom.textContent = 'Aplicado';
                        } else {
                            couponApplied = null;
                            if (couponMessage) { couponMessage.textContent = data.error || data.mensagem || 'Cupom inválido'; couponMessage.classList.add('text-red-600'); couponMessage.classList.remove('text-green-600'); couponMessage.classList.remove('hidden'); }
                        }
                        updateSummaryAndTotal();
                        if (typeof initializePaymentBrickForMethod === 'function' && (selectedPaymentMethod === 'credit_card' || selectedPaymentMethod === 'ticket' || selectedPaymentMethod === 'pix_mercadopago')) {
                            initializePaymentBrickForMethod(selectedPaymentMethod, emailInput?.value || null, currentAmount);
                        }
                    } catch (e) {
                        if (couponMessage) { couponMessage.textContent = 'Erro ao validar cupom'; couponMessage.classList.add('text-red-600'); couponMessage.classList.remove('hidden'); }
                    }
                    btnAplicarCupom.disabled = false;
                });
            }

            orderbumpCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    updateSummaryAndTotal();
                    // Atualizar Payment Brick se método MP estiver selecionado
                    if (selectedPaymentMethod === 'credit_card' || selectedPaymentMethod === 'ticket' || selectedPaymentMethod === 'pix_mercadopago') {
                        if (typeof initializePaymentBrickForMethod === 'function') {
                            const currentEmail = emailInput.value;
                            if (currentEmail && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
                                initializePaymentBrickForMethod(selectedPaymentMethod, currentEmail, currentAmount);
                            } else {
                                initializePaymentBrickForMethod(selectedPaymentMethod, null, currentAmount);
                            }
                        }
                    }
                });
            });
            updateSummaryAndTotal();
            updateMobileLayout();

            // Função auxiliar para mensagens de erro por status
            function getStatusErrorMessage(status) {
                const L = window.checkoutLabels || {};
                const statusMsg = {
                    'pending': L.pending || 'Pagamento pendente. Aguarde a confirmação.',
                    'in_process': L.in_process || 'Pagamento em processamento. Aguarde a confirmação.',
                    'rejected': L.error_rejected || 'Pagamento recusado. Verifique os dados do cartão ou tente outro método de pagamento.',
                    'cancelled': L.error_cancelled || 'Pagamento cancelado. Tente novamente ou escolha outro método de pagamento.',
                    'refunded': L.refunded || 'Pagamento reembolsado.',
                    'charged_back': L.charged_back || 'Pagamento contestado. Entre em contato com o suporte.'
                };
                return statusMsg[status] || null;
            }

            function showAlert(message) {
                const alertBox = document.getElementById('custom-alert-box');
                alertBox.textContent = message;
                alertBox.classList.add('show');
                setTimeout(() => { alertBox.classList.remove('show'); }, 3000);
            }

            // --- Lógica de Validação Comum ---
            function validateForm() {
                const cpfEl = document.getElementById('cpf');
                const phoneEl = document.getElementById('phone');
                
                // A lógica abaixo confia no DOM: 
                // Se o PHP renderizou um campo 'hidden', é porque foi desabilitado no backend.
                // Logo, não validamos e pegamos o valor padrão do hidden (ex: "000.000.000-00").
                const isCpfActive = cpfEl && cpfEl.type !== 'hidden';
                const isPhoneActive = phoneEl && phoneEl.type !== 'hidden';

                const payerData = {
                    name: nameInput.value,
                    email: emailInput.value,
                    phone: phoneEl ? phoneEl.value : '',
                    cpf: cpfEl ? cpfEl.value : '',
                    product_id: mainProductId,
                    oferta_id: ofertaId,
                    checkout_session_uuid: checkoutSessionUUID,
                    funnel_main_payment_id: funnelMainPaymentId,
                    funnel_step: funnelStepParam,
                    lang: '<?php echo htmlspecialchars($checkout_lang); ?>'
                };

                if (!payerData.name || !payerData.email) { showAlert((window.checkoutLabels || {}).alert_fill_name_email || 'Por favor, preencha o nome e o e-mail.'); return null; }
                
                // Validação condicional baseada no estado VISUAL do campo
                if (isPhoneActive && checkoutDigitsOnly(payerData.phone).length < 10) { showAlert((window.checkoutLabels || {}).alert_fill_phone || 'Por favor, preencha o telefone com DDD e número (10 ou 11 dígitos).'); return null; }
                if (isCpfActive && !payerData.cpf) { showAlert((window.checkoutLabels || {}).alert_fill_cpf || 'Por favor, preencha o CPF/CNPJ.'); return null; }
                
                // Recuperação de carrinho: registra lead (fire-and-forget)
                if (typeof recordCheckoutActivity === 'function') recordCheckoutActivity(payerData);
                
                return payerData;
            }

            function recordCheckoutActivity(payerData) {
                if (!checkoutSessionUUID || !mainProductId || !payerData?.name || !payerData?.email) return;
                fetch('/api/api.php?action=record_checkout_activity', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        checkout_session_uuid: checkoutSessionUUID,
                        product_id: mainProductId,
                        comprador_nome: payerData.name,
                        comprador_email: payerData.email,
                        comprador_telefone: payerData.phone || '',
                        comprador_cpf: payerData.cpf || '',
                        utm_parameters: typeof utmParameters !== 'undefined' ? utmParameters : {}
                    })
                }).catch(function() {});
            }

            // --- LÓGICA DE SELEÇÃO DE MÉTODOS DE PAGAMENTO ---
            let paymentBrickControllers = {};
            
            function selectPaymentMethod(methodType) {
                const accentColor = '<?php echo htmlspecialchars($accentColor); ?>';
                
                // Remover classe active de todos os cards
                document.querySelectorAll('.payment-method-card').forEach(card => {
                    card.style.borderColor = '#e5e7eb';
                    card.style.backgroundColor = '#ffffff';
                });
                
                // Adicionar classe active ao card selecionado
                const selectedCard = document.querySelector(`[data-payment-method="${methodType}"]`);
                if (selectedCard) {
                    selectedCard.style.borderColor = accentColor;
                    // Converter hex para rgba com 5% de opacidade
                    const hex = accentColor.replace('#', '');
                    const r = parseInt(hex.substr(0, 2), 16);
                    const g = parseInt(hex.substr(2, 2), 16);
                    const b = parseInt(hex.substr(4, 2), 16);
                    selectedCard.style.backgroundColor = `rgba(${r}, ${g}, ${b}, 0.05)`;
                }
                
                // Oculta todos os containers de métodos
                document.querySelectorAll('.payment-method-container').forEach(container => {
                    container.classList.add('hidden');
                });
                
                // Mostra apenas o container do método selecionado
                const selectedContainer = document.querySelector(`[data-method-type="${methodType}"]`);
                if (selectedContainer) {
                    selectedContainer.classList.remove('hidden');
                }
                
                selectedPaymentMethod = methodType;
                
                // Atualizar total com desconto Pix se aplicável
                updateSummaryAndTotal();
                
                // Se for método do Mercado Pago, inicializar Payment Brick (com proteção)
                if (methodType === 'credit_card' || methodType === 'ticket' || methodType === 'pix_mercadopago') {
                    if (typeof initializePaymentBrickForMethod === 'function') {
                        const currentEmail = emailInput.value;
                        if (currentEmail && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
                            initializePaymentBrickForMethod(methodType, currentEmail, currentAmount);
                        } else {
                            initializePaymentBrickForMethod(methodType, null, currentAmount);
                        }
                    } else {
                        console.warn('initializePaymentBrickForMethod não disponível.');
                    }
                }

                // Stripe cartão usa Checkout Session (redirecionamento) - não precisa de Elements
                
                // Recriar ícones Lucide
                lucide.createIcons();

                if (CHECKOUT_RECOVERY_OBSERVE && methodType && methodType !== checkoutObserveLastMethod) {
                    checkoutObserveLastMethod = methodType;
                    try { observeCheckoutEvent('payment_method_selected', { payment_method: methodType }); } catch (e) {}
                }
            }
            
            // Event listeners nos cards do grid
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.addEventListener('click', () => {
                    const methodType = card.getAttribute('data-payment-method');
                    selectPaymentMethod(methodType);
                });
            });
            
            // Seleção padrão: Pix (prioridade PushinPay)
            // --- LÓGICA PUSHINPAY ---
            const btnPagarPushin = document.getElementById('btn-pagar-pushinpay');
            if (btnPagarPushin) {
                btnPagarPushin.addEventListener('click', async () => {
                    const payerData = validateForm();
                    if (!payerData) return;

                    btnPagarPushin.disabled = true;
                    btnPagarPushin.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> ' + ((window.checkoutLabels || {}).loading_generating_pix || 'Gerando Pix...');
                    lucide.createIcons();

                    try {
                        const response = await fetch('/process_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                ...payerData,
                                product_id: mainProductId,
                                payment_method_id: 'pix', // Força Pix para PushinPay
                                transaction_amount: parseFloat(currentAmount).toFixed(2),
                                order_bump_product_ids: acceptedOrderBumps,
                                utm_parameters: utmParameters,
                                gateway: 'pushinpay', // Flag para o backend
                                ...getCouponPayload()
                            })
                        });
                        
                        // Verifica se a resposta é JSON válido
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            console.error('Resposta não é JSON:', text);
                            showAlert('Erro: Resposta inválida do servidor. Tente novamente.');
                            return;
                        }
                        
                        const result = await response.json();

                        if (response.ok && result.status === 'pix_created') {
                            showPixModal(result.pix_data.qr_code_base64, result.pix_data.qr_code, result.pix_data.payment_id, 'pushinpay', result.redirect_url_after_approval || null);
                        } else {
                            showRejectedModal(result.error || 'Erro ao gerar Pix.');
                        }
                    } catch (e) {
                        console.error('Erro ao processar pagamento:', e);
                        if (e instanceof SyntaxError) {
                            showRejectedModal('Erro: Resposta inválida do servidor. Verifique o console para mais detalhes.');
                        } else {
                            showRejectedModal('Erro de conexão. Verifique sua internet e tente novamente.');
                        }
                    } finally {
                        btnPagarPushin.disabled = false;
                        btnPagarPushin.innerHTML = '<i data-lucide="qr-code" class="w-6 h-6"></i> ' + ((window.checkoutLabels || {}).btn_generate_pix || 'GERAR PIX AGORA');
                        lucide.createIcons();
                    }
                });
            }

            // --- LÓGICA EFÍ PIX ---
            const btnPagarEfi = document.getElementById('btn-pagar-efi');
            if (btnPagarEfi) {
                btnPagarEfi.addEventListener('click', async () => {
                    const payerData = validateForm();
                    if (!payerData) return;

                    btnPagarEfi.disabled = true;
                    btnPagarEfi.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> ' + ((window.checkoutLabels || {}).loading_generating_pix || 'Gerando Pix...');
                    lucide.createIcons();

                    try {
                        const response = await fetch('/process_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                ...payerData,
                                product_id: mainProductId,
                                payment_method_id: 'pix',
                                transaction_amount: parseFloat(currentAmount).toFixed(2),
                                order_bump_product_ids: acceptedOrderBumps,
                                utm_parameters: utmParameters,
                                gateway: 'efi',
                                ...getCouponPayload()
                            })
                        });
                        
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            console.error('Resposta não é JSON:', text);
                            showAlert('Erro: Resposta inválida do servidor. Tente novamente.');
                            return;
                        }
                        
                        const result = await response.json();

                        if (response.ok && result.status === 'pix_created') {
                            showPixModal(result.pix_data.qr_code_base64, result.pix_data.qr_code, result.pix_data.payment_id, 'efi', result.redirect_url_after_approval || null);
                        } else {
                            showAlert(result.error || 'Erro ao gerar Pix. Tente novamente mais tarde.');
                        }
                    } catch (e) {
                        console.error('Erro ao processar pagamento:', e);
                        showAlert('Erro de conexão. Verifique sua internet e tente novamente.');
                    } finally {
                        btnPagarEfi.disabled = false;
                        btnPagarEfi.innerHTML = '<i data-lucide="qr-code" class="w-6 h-6"></i> ' + ((window.checkoutLabels || {}).btn_generate_pix || 'GERAR PIX AGORA');
                        lucide.createIcons();
                    }
                });
            }

            // --- LÓGICA PAGAR.ME PIX ---
            const btnPagarPagarmePix = document.getElementById('btn-pagar-pagarme-pix');
            if (btnPagarPagarmePix) {
                btnPagarPagarmePix.addEventListener('click', async () => {
                    const payerData = validateForm();
                    if (!payerData) return;
                    btnPagarPagarmePix.disabled = true;
                    btnPagarPagarmePix.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> ' + ((window.checkoutLabels || {}).loading_generating_pix || 'Gerando Pix...');
                    lucide.createIcons();
                    try {
                        const response = await fetch('/process_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ ...payerData, product_id: mainProductId, payment_method_id: 'pix', transaction_amount: parseFloat(currentAmount).toFixed(2), order_bump_product_ids: acceptedOrderBumps, utm_parameters: utmParameters, gateway: 'pagarme', ...getCouponPayload() })
                        });
                        const result = await response.json();
                        if (response.ok && result.status === 'pix_created') {
                            showPixModal(result.pix_data.qr_code_base64, result.pix_data.qr_code, result.pix_data.payment_id, 'pagarme', result.redirect_url_after_approval || null);
                        } else {
                            showRejectedModal(result.error || 'Erro ao gerar Pix.');
                        }
                    } catch (e) { showRejectedModal('Erro de conexão. Tente novamente.'); }
                    finally { btnPagarPagarmePix.disabled = false; btnPagarPagarmePix.innerHTML = '<i data-lucide="qr-code" class="w-6 h-6"></i> ' + ((window.checkoutLabels || {}).btn_generate_pix || 'GERAR PIX AGORA'); lucide.createIcons(); }
                });
            }

            // --- LÓGICA STRIPE PIX ---
            const btnPagarStripePix = document.getElementById('btn-pagar-stripe-pix');
            if (btnPagarStripePix) {
                btnPagarStripePix.addEventListener('click', async () => {
                    const payerData = validateForm();
                    if (!payerData) return;
                    btnPagarStripePix.disabled = true;
                    btnPagarStripePix.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> ' + ((window.checkoutLabels || {}).loading_generating_pix || 'Gerando Pix...');
                    lucide.createIcons();
                    try {
                        const response = await fetch('/process_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ ...payerData, product_id: mainProductId, payment_method_id: 'pix', transaction_amount: parseFloat(productCurrency === 'usd' && mainProductPriceUsd ? mainProductPriceUsd : currentAmount).toFixed(2), order_bump_product_ids: acceptedOrderBumps, utm_parameters: utmParameters, gateway: 'stripe', currency: productCurrency, checkout_hash: checkoutHash, ...getCouponPayload() })
                        });
                        const result = await response.json();
                        if (response.ok && result.status === 'pix_created') {
                            showPixModal(result.pix_data.qr_code_base64, result.pix_data.qr_code, result.pix_data.payment_id, 'stripe', result.redirect_url_after_approval || null);
                        } else if (response.ok && result.checkout_url) {
                            window.location.href = result.checkout_url;
                        } else {
                            showRejectedModal(result.error || 'Erro ao gerar Pix.');
                        }
                    } catch (e) { showRejectedModal('Erro de conexão. Tente novamente.'); }
                    finally { btnPagarStripePix.disabled = false; btnPagarStripePix.innerHTML = '<i data-lucide="qr-code" class="w-6 h-6"></i> ' + ((window.checkoutLabels || {}).btn_generate_pix || 'GERAR PIX AGORA'); lucide.createIcons(); }
                });
            }

            // --- LÓGICA PAGAR.ME CARTÃO / BOLETO / PAYPAL / STRIPE CARTÃO (redirect flow) ---
            ['btn-pagar-pagarme-card','btn-pagar-paypal','btn-pagar-stripe-card','btn-pagar-pagarme-ticket'].forEach(btnId => {
                const btn = document.getElementById(btnId);
                if (btn) {
                    btn.addEventListener('click', async () => {
                        const payerData = validateForm();
                        if (!payerData) return;
                        const gwMap = {'btn-pagar-pagarme-card':'pagarme_card','btn-pagar-paypal':'paypal','btn-pagar-stripe-card':'stripe_card','btn-pagar-pagarme-ticket':'pagarme_ticket'};
                        const gateway = gwMap[btnId];
                        btn.disabled = true;
                        const origHtml = btn.innerHTML;
                        btn.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> Processando...';
                        lucide.createIcons();
                        try {
                            const response = await fetch('/process_payment', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ ...payerData, product_id: mainProductId, payment_method_id: gateway.includes('ticket') ? 'ticket' : 'credit_card', transaction_amount: parseFloat((gateway === 'stripe_card' || gateway === 'stripe' || gateway === 'paypal') && productCurrency === 'usd' && mainProductPriceUsd ? mainProductPriceUsd : currentAmount).toFixed(2), order_bump_product_ids: acceptedOrderBumps, utm_parameters: utmParameters, gateway: gateway, currency: (gateway === 'stripe_card' || gateway === 'stripe' || gateway === 'paypal') ? productCurrency : undefined, checkout_hash: (gateway === 'stripe_card' || gateway === 'stripe' || gateway === 'paypal') ? checkoutHash : undefined, ...getCouponPayload() })
                            });
                            const contentType = response.headers.get('content-type') || '';
                            let result = {};
                            try {
                                result = contentType.includes('application/json') ? await response.json() : {};
                            } catch (_) { result = {}; }
                            if (response.ok && result.checkout_url) {
                                window.location.href = result.checkout_url;
                                return;
                            }
                            if (response.ok && result.redirect_url) {
                                window.location.href = result.redirect_url;
                                return;
                            }
                            showRejectedModal(result.error || 'Erro ao processar pagamento.');
                        } catch (e) {
                            console.error('Checkout fetch error:', e);
                            showRejectedModal('Erro de conexão. Tente novamente.');
                        } finally {
                            btn.disabled = false;
                            btn.innerHTML = origHtml;
                            lucide.createIcons();
                        }
                    });
                }
            });

            // --- LÓGICA BEEHIVE ---
            <?php if ($should_load_beehive_script): ?>
            const beehivePublicKey = '<?php echo htmlspecialchars($beehive_public_key, ENT_QUOTES); ?>';
            const btnPagarBeehive = document.getElementById('btn-pagar-beehive');
            
            if (btnPagarBeehive && typeof BeehivePay !== 'undefined') {
                BeehivePay.setPublicKey(beehivePublicKey);
                BeehivePay.setTestMode(false);
                
                // Máscaras para os campos Beehive
                const beehiveCardNumberInput = document.getElementById('beehive-card-number');
                const beehiveCardExpiryInput = document.getElementById('beehive-card-expiry');
                const beehiveCardCvvInput = document.getElementById('beehive-card-cvv');
                
                if (beehiveCardNumberInput) {
                    beehiveCardNumberInput.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
                        value = value.replace(/(\d{4})/g, '$1 ').trim();
                        e.target.value = value;
                    });
                }
                
                if (beehiveCardExpiryInput) {
                    beehiveCardExpiryInput.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        if (value.length >= 2) {
                            value = value.substring(0, 2) + '/' + value.substring(2, 4);
                        }
                        e.target.value = value;
                    });
                }
                
                if (beehiveCardCvvInput) {
                    beehiveCardCvvInput.addEventListener('input', function(e) {
                        e.target.value = e.target.value.replace(/\D/g, '');
                    });
                }
                
                btnPagarBeehive.addEventListener('click', async function() {
                    const payerData = validateForm();
                    if (!payerData) return;
                    
                    const cardNumber = beehiveCardNumberInput.value.replace(/\s/g, '');
                    const cardHolder = document.getElementById('beehive-card-holder').value.trim();
                    const cardExpiry = beehiveCardExpiryInput.value;
                    const cardCvv = beehiveCardCvvInput.value;
                    
                    if (!cardNumber || cardNumber.length < 13) { showAlert('Por favor, informe o número do cartão corretamente.'); return; }
                    if (!cardHolder || cardHolder.length < 3) { showAlert('Por favor, informe o nome no cartão.'); return; }
                    if (!cardExpiry || cardExpiry.length !== 5) { showAlert('Por favor, informe a validade do cartão (MM/AA).'); return; }
                    if (!cardCvv || cardCvv.length < 3) { showAlert('Por favor, informe o CVV do cartão.'); return; }
                    
                    const [month, year] = cardExpiry.split('/');
                    if (!month || !year || month.length !== 2 || year.length !== 2) { showAlert('Por favor, informe a validade no formato MM/AA.'); return; }
                    
                    btnPagarBeehive.disabled = true;
                    btnPagarBeehive.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> Processando...';
                    lucide.createIcons();
                    
                    try {
                        const tokenResult = await BeehivePay.encrypt({
                            number: cardNumber,
                            holderName: cardHolder,
                            expMonth: parseInt(month),
                            expYear: 2000 + parseInt(year),
                            cvv: cardCvv
                        });
                        
                        const cardToken = typeof tokenResult === 'string' ? tokenResult : (tokenResult.token || null);
                        if (!cardToken) throw new Error('Erro ao tokenizar cartão.');
                        
                        const response = await fetch('/process_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                ...payerData,
                                product_id: mainProductId,
                                card_token: cardToken,
                                transaction_amount: parseFloat(currentAmount).toFixed(2),
                                order_bump_product_ids: acceptedOrderBumps,
                                utm_parameters: utmParameters,
                                gateway: 'beehive',
                                ...getCouponPayload()
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (response.ok && result.status === 'approved') {
                            const defaultRedirectUrl = '/obrigado?payment_id=' + result.payment_id;
                            window.location.href = result.redirect_url || customRedirectUrl || defaultRedirectUrl;
                        } else if (response.ok && result.status === 'pending') {
                            showPendingModal();
                        } else {
                            showRejectedModal(result.error || 'Erro ao processar pagamento.');
                        }
                    } catch (e) {
                        console.error('Beehive Error:', e);
                        showRejectedModal(e.message || 'Erro ao processar pagamento.');
                    } finally {
                        btnPagarBeehive.disabled = false;
                        btnPagarBeehive.innerHTML = '<i data-lucide="credit-card" class="w-6 h-6"></i> FINALIZAR PAGAMENTO';
                        lucide.createIcons();
                    }
                });
            }
            <?php endif; ?>

            // --- LÓGICA HYPERCASH ---
            <?php if ($should_load_hypercash_script): ?>
            const btnPagarHypercash = document.getElementById('btn-pagar-hypercash');
            const hypercashPublicKey = '<?php echo htmlspecialchars($hypercash_public_key, ENT_QUOTES, 'UTF-8'); ?>';
            
            if (btnPagarHypercash && typeof FastSoft !== 'undefined') {
                FastSoft.setPublicKey(hypercashPublicKey);
                
                // Máscaras para os campos Hypercash
                const hypercashCardNumberInput = document.getElementById('hypercash-card-number');
                const hypercashCardExpiryInput = document.getElementById('hypercash-card-expiry');
                const hypercashCardCvvInput = document.getElementById('hypercash-card-cvv');
                
                if (hypercashCardNumberInput) {
                    hypercashCardNumberInput.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
                        value = value.replace(/(\d{4})/g, '$1 ').trim();
                        e.target.value = value;
                    });
                }
                
                if (hypercashCardExpiryInput) {
                    hypercashCardExpiryInput.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        if (value.length >= 2) {
                            value = value.substring(0, 2) + '/' + value.substring(2, 4);
                        }
                        e.target.value = value;
                    });
                }
                
                if (hypercashCardCvvInput) {
                    hypercashCardCvvInput.addEventListener('input', function(e) {
                        e.target.value = e.target.value.replace(/\D/g, '');
                    });
                }
                
                btnPagarHypercash.addEventListener('click', async function() {
                    const payerData = validateForm();
                    if (!payerData) return;
                    
                    const cardNumber = hypercashCardNumberInput?.value.replace(/\s/g, '') || '';
                    const cardHolder = document.getElementById('hypercash-card-holder')?.value.trim() || '';
                    const cardExpiry = hypercashCardExpiryInput?.value || '';
                    const cardCvv = hypercashCardCvvInput?.value || '';
                    
                    if (!cardNumber || cardNumber.length < 13) { showAlert('Por favor, informe o número do cartão.'); return; }
                    if (!cardHolder || cardHolder.length < 3) { showAlert('Por favor, informe o nome no cartão.'); return; }
                    if (!cardExpiry || cardExpiry.length !== 5) { showAlert('Por favor, informe a validade do cartão (MM/AA).'); return; }
                    if (!cardCvv || cardCvv.length < 3) { showAlert('Por favor, informe o CVV do cartão.'); return; }
                    
                    const [month, year] = cardExpiry.split('/');
                    if (!month || !year || month.length !== 2 || year.length !== 2) { showAlert('Formato de validade inválido. Use MM/AA.'); return; }
                    
                    btnPagarHypercash.disabled = true;
                    btnPagarHypercash.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> Processando...';
                    lucide.createIcons();
                    
                    try {
                        const cardData = { number: cardNumber, holderName: cardHolder, expMonth: parseInt(month), expYear: 2000 + parseInt(year), cvv: cardCvv };
                        const tokenResult = await FastSoft.encrypt(cardData);
                        
                        let cardToken;
                        if (typeof tokenResult === 'string') cardToken = tokenResult;
                        else if (tokenResult && tokenResult.token) cardToken = tokenResult.token;
                        else if (tokenResult && tokenResult.error) throw new Error(tokenResult.error.message || tokenResult.error);
                        else throw new Error('Erro ao tokenizar cartão.');
                        
                        const cardDataForApi = { number: cardNumber, holderName: cardHolder.trim().substring(0, 100), expirationMonth: parseInt(month), expirationYear: 2000 + parseInt(year), cvv: cardCvv.replace(/\D/g, '').substring(0, 4) };
                        
                        const response = await fetch('/process_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                ...payerData,
                                product_id: mainProductId,
                                card_token: cardToken,
                                card_data: cardDataForApi,
                                transaction_amount: parseFloat(currentAmount).toFixed(2),
                                order_bump_product_ids: acceptedOrderBumps,
                                utm_parameters: utmParameters,
                                gateway: 'hypercash',
                                ...getCouponPayload()
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (response.ok && result.status === 'approved') {
                            const defaultRedirectUrl = '/obrigado?payment_id=' + result.payment_id;
                            window.location.href = result.redirect_url || customRedirectUrl || defaultRedirectUrl;
                        } else if (response.ok && result.status === 'pending') {
                            showPendingModal();
                        } else {
                            showRejectedModal(result.error || result.message || 'Erro ao processar pagamento.');
                        }
                    } catch (e) {
                        console.error('Hypercash Error:', e);
                        showRejectedModal(e.message || 'Erro ao processar pagamento.');
                    } finally {
                        btnPagarHypercash.disabled = false;
                        btnPagarHypercash.innerHTML = '<i data-lucide="credit-card" class="w-6 h-6"></i> FINALIZAR PAGAMENTO';
                        lucide.createIcons();
                    }
                });
            }
            <?php endif; ?>

            // --- LÓGICA EFÍ CARTÃO ---
            <?php 
            $should_init_efi_card = (isset($credit_card_efi_enabled) && $credit_card_efi_enabled) && !empty($efi_payee_code) && !isset($_GET['preview']);
            if ($should_init_efi_card): ?>
            const btnPagarEfiCard = document.getElementById('btn-pagar-efi-card');
            const efiPayeeCode = <?php echo json_encode($efi_payee_code); ?>;
            
            function waitForEfiPay(callback, maxAttempts = 50) {
                let attempts = 0;
                const checkEfiPay = setInterval(() => {
                    attempts++;
                    const efiPay = window.EfiPay || window.efiPay || (typeof EfiPay !== 'undefined' ? EfiPay : null);
                    if (efiPay && efiPay.CreditCard && typeof efiPay.CreditCard.setAccount === 'function') {
                        clearInterval(checkEfiPay);
                        callback(efiPay);
                    } else if (attempts >= maxAttempts) {
                        clearInterval(checkEfiPay);
                        console.error('Efí: EfiPay não carregou após ' + maxAttempts + ' tentativas');
                    }
                }, 100);
            }
            
            if (btnPagarEfiCard) {
                waitForEfiPay((EfiPay) => {
                    // Máscaras para os campos Efí
                    const efiCardNumberInput = document.getElementById('efi-card-number');
                    const efiCardExpiryInput = document.getElementById('efi-card-expiry');
                    const efiCardCvvInput = document.getElementById('efi-card-cvv');
                    
                    if (efiCardNumberInput) {
                        efiCardNumberInput.addEventListener('input', function(e) {
                            let d = e.target.value.replace(/\D/g, '').slice(0, 16);
                            const parts = [];
                            for (let i = 0; i < d.length; i += 4) parts.push(d.slice(i, i + 4));
                            e.target.value = parts.join(' ');
                        });
                    }
                    
                    if (efiCardExpiryInput) {
                        efiCardExpiryInput.addEventListener('input', function(e) {
                            let value = e.target.value.replace(/\D/g, '').slice(0, 4);
                            if (value.length >= 2) value = value.slice(0, 2) + '/' + value.slice(2);
                            e.target.value = value;
                        });
                    }
                    
                    if (efiCardCvvInput) {
                        efiCardCvvInput.addEventListener('input', function(e) {
                            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
                        });
                    }
                    
                    btnPagarEfiCard.addEventListener('click', async function() {
                        const payerData = validateForm();
                        if (!payerData) return;
                        
                        const cardNumber = efiCardNumberInput?.value.replace(/\s/g, '') || '';
                        const cardHolder = document.getElementById('efi-card-holder')?.value.trim() || '';
                        const cardExpiry = efiCardExpiryInput?.value || '';
                        const cardCvv = efiCardCvvInput?.value || '';
                        
                        if (!cardNumber || cardNumber.length < 13) { showAlert('Por favor, informe o número do cartão.'); return; }
                        if (!cardHolder || cardHolder.length < 3) { showAlert('Por favor, informe o nome no cartão.'); return; }
                        if (cardHolder.trim().split(/\s+/).filter(Boolean).length < 2) {
                            showAlert('Informe nome e sobrenome no cartão (como impresso). Ex.: Ivan Souza.');
                            return;
                        }
                        if (!cardExpiry || cardExpiry.length < 5) { showAlert('Por favor, informe a validade do cartão.'); return; }
                        if (!cardCvv || cardCvv.length < 3) { showAlert('Por favor, informe o CVV do cartão.'); return; }
                        
                        const [month, year] = cardExpiry.split('/');
                        if (!month || !year || month.length !== 2 || year.length !== 2) { showAlert('Por favor, informe a validade no formato MM/AA.'); return; }
                        
                        const cpfClean = payerData.cpf ? payerData.cpf.replace(/\D/g, '') : '';
                        if (cpfClean.length !== 11) {
                            showAlert('Pagamento com cartão Efí exige CPF do titular (11 dígitos), sem CNPJ.');
                            return;
                        }
                        
                        // Identificar bandeira
                        let brand = 'visa';
                        const firstDigit = cardNumber.charAt(0);
                        if (firstDigit === '4') brand = 'visa';
                        else if (firstDigit === '5' || firstDigit === '2') brand = 'mastercard';
                        else if (firstDigit === '3') brand = 'amex';
                        else if (firstDigit === '6') brand = 'elo';
                        
                        btnPagarEfiCard.disabled = true;
                        btnPagarEfiCard.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> Processando...';
                        lucide.createIcons();
                        
                        try {
                            const paymentTokenResult = await EfiPay.CreditCard
                                .setAccount(efiPayeeCode)
                                .setEnvironment('production')
                                .setCreditCardData({
                                    brand: brand,
                                    number: cardNumber,
                                    cvv: cardCvv,
                                    expirationMonth: month,
                                    expirationYear: '20' + year,
                                    holderName: cardHolder,
                                    holderDocument: cpfClean,
                                    reuse: false
                                })
                                .getPaymentToken();
                            
                            const paymentTokenValue = paymentTokenResult.payment_token || paymentTokenResult;
                            if (!paymentTokenValue) throw new Error('Erro ao tokenizar cartão.');
                            
                            const response = await fetch('/process_payment', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    ...payerData,
                                    product_id: mainProductId,
                                    payment_token: paymentTokenValue,
                                    card_holder_name: cardHolder,
                                    transaction_amount: parseFloat(currentAmount).toFixed(2),
                                    installments: 1,
                                    order_bump_product_ids: acceptedOrderBumps,
                                    utm_parameters: utmParameters,
                                    gateway: 'efi_card',
                                    ...getCouponPayload()
                                })
                            });
                            
                            const result = await response.json();
                            
                            if (response.ok && result.status === 'approved') {
                                const defaultRedirectUrl = '/obrigado?payment_id=' + result.payment_id;
                                window.location.href = result.redirect_url || customRedirectUrl || defaultRedirectUrl;
                            } else if (response.ok && result.status === 'pending') {
                                // Inicia polling para verificar quando o pagamento for aprovado
                                if (result.payment_id) {
                                    startPaymentCheck(result.payment_id, infoprodutorId, 'efi_card');
                                }
                                showPendingModal();
                            } else if (response.ok && result.status === 'rejected') {
                                showRejectedModal(result.message || result.error || 'Pagamento recusado. Verifique os dados do cartão, o limite ou tente outro cartão.');
                            } else {
                                showRejectedModal(result.message || result.error || 'Erro ao processar pagamento.');
                            }
                        } catch (e) {
                            console.error('Efí Card Error:', e);
                            showRejectedModal(e.message || 'Erro ao processar pagamento.');
                        } finally {
                            btnPagarEfiCard.disabled = false;
                            btnPagarEfiCard.innerHTML = '<i data-lucide="credit-card" class="w-6 h-6"></i> FINALIZAR PAGAMENTO';
                            lucide.createIcons();
                        }
                    });
                });
            }
            <?php endif; ?>

            // --- LÓGICA MERCADO PAGO ---
            <?php 
            // Inicializar Mercado Pago APENAS se houver métodos do MP habilitados E tiver public_key
            $has_mp_methods = ($pix_mercadopago_enabled || $credit_card_mercadopago_enabled || $ticket_enabled);
            $should_init_mp = $has_mp_methods && !empty($public_key) && !isset($_GET['preview']);
            if ($should_init_mp): ?>
            let mp; // Declara mp antes de usar
            const checkoutLocale = '<?php echo $checkout_lang === "pt" ? "pt-BR" : ($checkout_lang === "es" ? "es-ES" : ($checkout_lang === "fr" ? "fr-FR" : "en-US")); ?>';
            try {
                mp = new MercadoPago('<?php echo $public_key; ?>', { locale: checkoutLocale });
            } catch (error) {
                console.error('Erro ao inicializar Mercado Pago:', error);
            }
            
            async function initializePaymentBrickForMethod(methodType, payerEmail = null, amount = mainProductPrice) {
                // Verifica se mp está inicializado
                if (typeof mp === 'undefined') {
                    console.error('Mercado Pago não foi inicializado ainda. Aguardando...');
                    return;
                }
                
                let containerId, loadingSpinnerId, configWrapperId;
                
                // Determinar IDs baseado no método
                if (methodType === 'credit_card') {
                    containerId = 'paymentBrick_container_credit';
                    loadingSpinnerId = 'loading_spinner_credit';
                    configWrapperId = 'payment_container_wrapper_credit';
                } else if (methodType === 'ticket') {
                    containerId = 'paymentBrick_container_ticket';
                    loadingSpinnerId = 'loading_spinner_ticket';
                    configWrapperId = 'payment_container_wrapper_ticket';
                } else if (methodType === 'pix_mercadopago') {
                    containerId = 'paymentBrick_container_pix_mp';
                    loadingSpinnerId = 'loading_spinner_pix_mp';
                    configWrapperId = 'payment_container_wrapper_pix_mp';
                } else {
                    return; // Método não suportado
                }
                
                // Verificar se o container existe
                let container = document.getElementById(containerId);
                if (!container) {
                    console.error(`Container ${containerId} não encontrado`);
                    return;
                }
                
                // Desmontar controller anterior se existir
                if (paymentBrickControllers[methodType]) {
                    try { 
                        await paymentBrickControllers[methodType].unmount(); 
                    } catch(e) {
                        console.error('Erro ao desmontar Payment Brick:', e);
                    }
                }
                
                // Garantir que o container está limpo
                const newContainer = document.createElement('div');
                newContainer.id = containerId;
                if (container.parentNode) {
                    container.parentNode.replaceChild(newContainer, container);
                }
                container = newContainer;
                
                const loadingSpinner = document.getElementById(loadingSpinnerId);
                if (loadingSpinner) {
                    loadingSpinner.classList.remove('hidden');
                }
                
                // Timeout: se o Payment Brick não carregar em 8s, esconde loading e mostra mensagem
                const loadTimeout = setTimeout(() => {
                    if (loadingSpinner && !loadingSpinner.classList.contains('hidden')) {
                        loadingSpinner.classList.add('hidden');
                        const errDiv = document.createElement('div');
                        errDiv.className = 'p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm';
                        errDiv.innerHTML = '<p class="font-medium">Não foi possível carregar o formulário de pagamento.</p><p class="mt-1">Verifique se o Mercado Pago está configurado nas integrações do produto. Se quiser usar Stripe, altere o gateway de cartão para Stripe na configuração do produto.</p>';
                        const wrapper = document.getElementById(configWrapperId);
                        if (wrapper) wrapper.appendChild(errDiv);
                    }
                }, 8000);
                
                // Recupera config do HTML
                const configEl = document.getElementById(configWrapperId);
                const paymentMethods = configEl ? JSON.parse(configEl.dataset.mpConfig || '{}') : {};
                
                console.log(`Inicializando Payment Brick para ${methodType} com métodos:`, paymentMethods);

                try {
                    paymentBrickControllers[methodType] = await mp.bricks().create("payment", containerId, {
                        initialization: { amount: parseFloat(amount), ...(payerEmail && { payer: { email: payerEmail } }) },
                        customization: {
                            paymentMethods: paymentMethods,
                            visual: { style: { theme: 'flat', borderRadius: '8px', verticalPadding: '26px', primaryColor: '<?php echo htmlspecialchars($accentColor); ?>' } },
                        },
                        callbacks: {
                            onReady: () => { 
                                clearTimeout(loadTimeout);
                                if (loadingSpinner) {
                                    loadingSpinner.classList.add('hidden');
                                }
                                
                                // Quando apenas um método está configurado, o Payment Brick já mostra apenas esse método
                                // Tentamos expandir automaticamente o formulário após um pequeno delay
                                setTimeout(() => {
                                    const container = document.getElementById(containerId);
                                    if (container) {
                                        // Tentar encontrar e clicar no primeiro elemento clicável relacionado ao método
                                        // O Payment Brick pode ter elementos em shadow DOM ou iframe, então tentamos múltiplas abordagens
                                        
                                        // Abordagem 1: Tentar clicar em elementos com role="button" ou labels
                                        const clickableElements = container.querySelectorAll('[role="button"], label, button, .payment-option, [class*="payment"], [class*="method"]');
                                        
                                        if (clickableElements.length > 0) {
                                            // Se houver apenas um método configurado, tentar clicar no primeiro elemento
                                            // Isso deve expandir o formulário automaticamente
                                            const firstClickable = clickableElements[0];
                                            if (firstClickable) {
                                                // Usar um pequeno delay adicional para garantir que o DOM está totalmente renderizado
                                                setTimeout(() => {
                                                    try {
                                                        firstClickable.click();
                                                    } catch (e) {
                                                        // Se não conseguir clicar, não é crítico
                                                        console.log('Não foi possível auto-expandir o método de pagamento');
                                                    }
                                                }, 200);
                                            }
                                        }
                                        
                                        // Abordagem 2: Tentar focar no primeiro input do formulário
                                        setTimeout(() => {
                                            const inputs = container.querySelectorAll('input, select, textarea');
                                            if (inputs.length > 0 && inputs[0]) {
                                                try {
                                                    inputs[0].focus();
                                                } catch (e) {
                                                    // Input pode estar em iframe/shadow DOM
                                                }
                                            }
                                        }, 300);
                                    }
                                }, 600);
                            },
                            onSubmit: async ({ formData }) => {
                                const payerData = validateForm();
                                if (!payerData) return; // Validação já feita na função comum

                                const response = await fetch('/process_payment', {
                                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ 
                                        ...formData, 
                                        ...payerData,
                                        product_id: mainProductId,
                                        transaction_amount: parseFloat(currentAmount).toFixed(2),
                                        order_bump_product_ids: acceptedOrderBumps,
                                        utm_parameters: utmParameters,
                                        gateway: 'mercadopago',
                                        ...getCouponPayload()
                                    })
                                });
                                const result = await response.json();

                                // Remove loading spinner em todos os casos
                                if (loadingSpinner) {
                                    loadingSpinner.classList.add('hidden');
                                }

                                // PRIORIDADE 1: Status de erro (rejected, cancelled, etc.) - verificar ANTES de pending
                                if (response.ok && (result.status === 'rejected' || result.status === 'cancelled' || result.status === 'refunded' || result.status === 'charged_back')) {
                                    // Pagamento recusado/rejeitado - mostra modal personalizado
                                    const errorMsg = result.error || result.message || getStatusErrorMessage(result.status) || 'Pagamento não aprovado. Tente outro método de pagamento.';
                                    showRejectedModal(errorMsg);
                                } 
                                // PRIORIDADE 2: PIX criado
                                else if (response.ok && result.status === 'pix_created') {
                                    showPixModal(result.pix_data.qr_code_base64, result.pix_data.qr_code, result.pix_data.payment_id, 'mercadopago', result.redirect_url_after_approval || null);
                                } 
                                // PRIORIDADE 3: Pagamento aprovado
                                else if (response.ok && result.status === 'approved') {
                                    // Pagamento aprovado - mostra modal de sucesso antes de redirecionar
                                    const defaultRedirectUrl = `/obrigado?payment_id=${result.payment_id || result.id || ''}`;
                                    const finalRedirectUrl = result.redirect_url || customRedirectUrl || defaultRedirectUrl;
                                    
                                    // Mostra modal de sucesso do cartão
                                    showCardSuccessModal(finalRedirectUrl);
                                } 
                                // PRIORIDADE 4: Redirect URL (para casos especiais)
                                else if (response.ok && result.redirect_url) {
                                    window.location.href = result.redirect_url;
                                } 
                                // PRIORIDADE 5: Status pendente/em processamento
                                else if (response.ok && (result.status === 'pending' || result.status === 'in_process')) {
                                    // Verifica se é Pix (mostra modal do Pix) ou Cartão (mostra modal de pendente)
                                    const isPix = formData.payment_method_id === 'pix' || selectedPaymentMethod === 'pix_mercadopago';
                                    
                                    if (isPix && result.payment_id) {
                                        // Para Pix, mostra modal do Pix com polling (usa redirect do backend se existir)
                                        startPaymentCheck(result.payment_id, infoprodutorId, 'mercadopago', result.redirect_url_after_approval || null);
                                        showPixModal(null, null, result.payment_id, 'mercadopago', result.redirect_url_after_approval || null);
                                    } else {
                                        // Para Cartão, mostra modal de pagamento pendente
                                        showPendingModal();
                                    }
                                } 
                                // PRIORIDADE 6: Outros status conhecidos
                                else if (response.ok && result.status) {
                                    const msg = getStatusErrorMessage(result.status) || result.message || result.error || 'Status do pagamento: ' + result.status + '. Aguarde ou tente novamente.';
                                    
                                    // Se tiver payment_id e status pendente, mostra modal de pendente
                                    if (result.payment_id && (result.status === 'pending' || result.status === 'in_process')) {
                                        showPendingModal();
                                    } else {
                                        // Para outros status, mostra modal de erro
                                        showRejectedModal(msg);
                                    }
                                } 
                                // PRIORIDADE 7: Erro genérico ou resposta inválida
                                else {
                                    console.error('Resposta inesperada do servidor:', result);
                                    const errorMsg = result.error || result.message || 'Ocorreu um erro ao processar o pagamento. Tente novamente ou escolha outro método de pagamento.';
                                    showRejectedModal(errorMsg);
                                }
                            },
                            onError: (error) => { 
                                clearTimeout(loadTimeout);
                                console.error('Erro no Payment Brick:', error);
                                if (loadingSpinner) {
                                    loadingSpinner.classList.add('hidden');
                                }
                                
                                // Mensagens de erro mais específicas
                                let errorMessage = 'Erro ao processar pagamento.';
                                if (error && error.message) {
                                    if (error.message.includes('rejected') || error.message.includes('recusado')) {
                                        errorMessage = 'Pagamento recusado. Verifique os dados do cartão ou tente outro método de pagamento.';
                                    } else if (error.message.includes('insufficient') || error.message.includes('insuficiente')) {
                                        errorMessage = 'Saldo insuficiente. Tente outro cartão ou método de pagamento.';
                                    } else if (error.message.includes('security') || error.message.includes('CVV') || error.message.includes('código')) {
                                        errorMessage = 'Código de segurança (CVV) incorreto. Verifique e tente novamente.';
                                    } else if (error.message.includes('expired') || error.message.includes('vencido')) {
                                        errorMessage = 'Cartão vencido. Verifique a data de validade e tente novamente.';
                                    } else {
                                        errorMessage = 'Erro no Mercado Pago: ' + error.message + '. Tente outro método de pagamento.';
                                    }
                                } else {
                                    errorMessage = 'Erro ao processar pagamento. Tente novamente ou escolha outro método de pagamento.';
                                }
                                
                                showRejectedModal(errorMessage);
                            },
                        },
                    });
                } catch (error) {
                    clearTimeout(loadTimeout);
                    console.error('Erro ao criar Payment Brick:', error);
                    if (loadingSpinner) {
                        loadingSpinner.classList.add('hidden');
                    }
                    showAlert("Erro ao inicializar pagamento: " + (error.message || 'Erro desconhecido'));
                }
            }
            
            // Listener para atualizar Payment Brick quando email mudar (apenas para métodos MP)
            emailInput.addEventListener('blur', () => {
                const currentEmail = emailInput.value;
                if (currentEmail && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(currentEmail)) {
                    if (selectedPaymentMethod === 'credit_card' || selectedPaymentMethod === 'ticket' || selectedPaymentMethod === 'pix_mercadopago') {
                        if (typeof initializePaymentBrickForMethod === 'function') {
                            initializePaymentBrickForMethod(selectedPaymentMethod, currentEmail, currentAmount);
                        }
                    }
                }
            }); 
            
            // Seleciona método padrão APÓS inicializar Mercado Pago
            selectDefaultPaymentMethod();
            <?php endif; ?>
            
            // Função para selecionar método de pagamento padrão (funciona para todos os gateways)
            // Quando produto tem price_usd, prioriza Stripe para clientes internacionais verem USD
            function selectDefaultPaymentMethod() {
                const pixPushinpayCard = document.querySelector('[data-payment-method="pix_pushinpay"]');
                const pixEfiCard = document.querySelector('[data-payment-method="pix_efi"]');
                const pixStripeCard = document.querySelector('[data-payment-method="pix_stripe"]');
                const pixMercadopagoCard = document.querySelector('[data-payment-method="pix_mercadopago"]');
                const creditCardStripeCard = document.querySelector('[data-payment-method="credit_card_stripe"]');
                const creditCardCard = document.querySelector('[data-payment-method="credit_card"]');
                const creditCardEfiCard = document.querySelector('[data-payment-method="credit_card_efi"]');
                const creditCardHypercashCard = document.querySelector('[data-payment-method="credit_card_hypercash"]');
                const creditCardBeehiveCard = document.querySelector('[data-payment-method="credit_card_beehive"]');
                const preferStripe = productCurrency === 'usd' && mainProductPriceUsd;
                if (preferStripe && (pixStripeCard || creditCardStripeCard)) {
                    selectPaymentMethod(pixStripeCard ? 'pix_stripe' : 'credit_card_stripe');
                } else if (pixPushinpayCard) {
                    selectPaymentMethod('pix_pushinpay');
                } else if (pixEfiCard) {
                    selectPaymentMethod('pix_efi');
                } else if (pixStripeCard) {
                    selectPaymentMethod('pix_stripe');
                } else if (pixMercadopagoCard) {
                    selectPaymentMethod('pix_mercadopago');
                } else if (creditCardStripeCard) {
                    selectPaymentMethod('credit_card_stripe');
                } else if (creditCardCard) {
                    selectPaymentMethod('credit_card');
                } else if (creditCardEfiCard) {
                    selectPaymentMethod('credit_card_efi');
                } else if (creditCardHypercashCard) {
                    selectPaymentMethod('credit_card_hypercash');
                } else if (creditCardBeehiveCard) {
                    selectPaymentMethod('credit_card_beehive');
                } else {
                    const firstCard = document.querySelector('.payment-method-card');
                    if (firstCard) {
                        const methodType = firstCard.getAttribute('data-payment-method');
                        selectPaymentMethod(methodType);
                    }
                }
            }
            
            // Chama a seleção padrão se Mercado Pago não estiver inicializado
            <?php if (!$should_init_mp): ?>
            selectDefaultPaymentMethod();
            <?php endif; ?>

            // --- Funções Auxiliares de Pix e Status ---
            document.getElementById('copy-pix-code-btn')?.addEventListener('click', (e) => {
                const input = document.getElementById('pix-code-input');
                input.select();
                document.execCommand('copy');
                e.target.textContent = 'Copiado!';
                setTimeout(() => { e.target.textContent = 'Copiar'; }, 2000);
            });

            function showPixModal(qrCodeBase64, pixCode, paymentId, gatewayUsed, redirectUrlAfterApproval) {
                if (notificationTimer) clearInterval(notificationTimer);
                document.getElementById('sales-notification')?.classList.remove('show');
                
                // Se tiver QR code, configura a imagem
                if (qrCodeBase64) {
                    // Detecta o formato da imagem ou usa PNG como padrão (formato correto para QR codes)
                    let imageSrc = qrCodeBase64;
                    if (!qrCodeBase64.startsWith('data:')) {
                        // Se não tem prefixo data:, adiciona como PNG (formato padrão para QR codes)
                        imageSrc = `data:image/png;base64,${qrCodeBase64}`;
                    } else if (qrCodeBase64.includes('data:image/jpeg')) {
                        // Se for JPEG, converte para PNG (QR codes devem ser PNG)
                        imageSrc = qrCodeBase64.replace('data:image/jpeg', 'data:image/png');
                    }
                    
                    const qrCodeImg = document.getElementById('pix-qr-code-img');
                    if (qrCodeImg) {
                        qrCodeImg.src = imageSrc;
                        // Garante que a imagem seja renderizada corretamente sem filtros
                        qrCodeImg.style.imageRendering = 'pixelated';
                        qrCodeImg.style.filter = 'none';
                    }
                }
                
                // Se tiver código PIX, configura o input
                if (pixCode) {
                    const pixCodeInput = document.getElementById('pix-code-input');
                    if (pixCodeInput) {
                        pixCodeInput.value = pixCode;
                    }
                }
                
                // Mostra estado de aguardando pagamento
                const waitingState = document.getElementById('pix-waiting-state');
                const approvedState = document.getElementById('pix-approved-state');
                if (waitingState) waitingState.classList.remove('hidden');
                if (approvedState) approvedState.classList.add('hidden');
                
                // Mostra o modal
                pixModalOverlay.classList.remove('hidden');
                setTimeout(() => { 
                    pixModalOverlay.classList.remove('opacity-0'); 
                    pixModalContent.classList.remove('opacity-0', 'scale-95'); 
                    lucide.createIcons(); 
                }, 10);
                
                // Inicia verificação de status se tiver payment_id (usa URL do funil se enviada pelo backend)
                if (paymentId) {
                    startPaymentCheck(paymentId, infoprodutorId, gatewayUsed, redirectUrlAfterApproval);
                }

                if (CHECKOUT_RECOVERY_OBSERVE) {
                    try {
                        const gw = String(gatewayUsed || '');
                        const pid = (paymentId !== undefined && paymentId !== null) ? String(paymentId) : '';
                        const pixSeenTxidGateways = { pushinpay: true, efi: true, mercadopago: true };
                        if (pixSeenTxidGateways[gw] && pid !== '' && pid !== 'undefined' && pid !== 'null') {
                            const pixKey = gw + '|' + pid;
                            if (pixKey !== checkoutObserveLastPixKey) {
                                checkoutObserveLastPixKey = pixKey;
                                observeCheckoutEvent('pix_seen', { transacao_id: pid });
                            }
                        }
                    } catch (e) {}
                }
            }

            // Função para mostrar modal de sucesso do cartão
            function showCardSuccessModal(redirectUrl) {
                if (notificationTimer) clearInterval(notificationTimer);
                document.getElementById('sales-notification')?.classList.remove('show');
                
                const modalOverlay = document.getElementById('card-success-modal-overlay');
                const modalContent = document.getElementById('card-success-modal-content');
                const countdownEl = document.getElementById('card-redirect-countdown');
                const redirectBtn = document.getElementById('card-success-redirect-btn');
                
                if (!modalOverlay || !modalContent) {
                    // Fallback: redireciona direto se modal não existir
                    window.location.href = redirectUrl;
                    return;
                }
                
                // Mostra o modal
                modalOverlay.classList.remove('hidden');
                setTimeout(() => { 
                    modalOverlay.classList.remove('opacity-0');
                    lucide.createIcons(); 
                }, 10);
                
                // Configura o botão de redirecionamento
                if (redirectBtn) {
                    redirectBtn.onclick = () => {
                        window.location.href = redirectUrl;
                    };
                }
                
                // Inicia countdown de 5 segundos
                let countdown = 5;
                if (countdownEl) {
                    countdownEl.textContent = countdown;
                }
                
                const countdownInterval = setInterval(() => {
                    countdown--;
                    if (countdownEl) {
                        countdownEl.textContent = countdown;
                    }
                    
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = redirectUrl;
                    }
                }, 1000);
            }

            // Função para mostrar modal de pagamento pendente
            function showPendingModal() {
                if (notificationTimer) clearInterval(notificationTimer);
                document.getElementById('sales-notification')?.classList.remove('show');
                
                const modalOverlay = document.getElementById('pending-modal-overlay');
                const closeBtn = document.getElementById('pending-modal-close-btn');
                
                if (!modalOverlay) {
                    showAlert('Pagamento em análise. Você receberá um e-mail quando for confirmado.');
                    return;
                }
                
                // Esconde o botão inicialmente
                if (closeBtn) {
                    closeBtn.classList.add('hidden');
                }
                
                // Mostra o modal
                modalOverlay.classList.remove('hidden');
                setTimeout(() => { 
                    modalOverlay.classList.remove('opacity-0');
                    lucide.createIcons(); 
                }, 10);
                
                // Mostra o botão após 10 segundos
                setTimeout(() => {
                    if (closeBtn) {
                        closeBtn.classList.remove('hidden');
                    }
                }, 10000);
                
                // Configura o botão de fechar
                if (closeBtn) {
                    closeBtn.onclick = () => {
                        modalOverlay.classList.add('opacity-0');
                        setTimeout(() => {
                            modalOverlay.classList.add('hidden');
                        }, 300);
                    };
                }
                
                // Fecha ao clicar fora do modal
                modalOverlay.onclick = (e) => {
                    if (e.target === modalOverlay) {
                        modalOverlay.classList.add('opacity-0');
                        setTimeout(() => {
                            modalOverlay.classList.add('hidden');
                        }, 300);
                    }
                };
            }

            // Função para mostrar modal de pagamento recusado
            function showRejectedModal(errorMessage = null) {
                if (notificationTimer) clearInterval(notificationTimer);
                document.getElementById('sales-notification')?.classList.remove('show');
                
                const modalOverlay = document.getElementById('rejected-modal-overlay');
                const closeBtn = document.getElementById('rejected-modal-close-btn');
                const messageEl = document.getElementById('rejected-modal-message');
                
                if (!modalOverlay) {
                    showAlert(errorMessage || 'Pagamento recusado. Tente outro método de pagamento.');
                    return;
                }
                
                // Atualiza a mensagem se fornecida
                if (messageEl && errorMessage) {
                    messageEl.textContent = errorMessage;
                }
                
                // Mostra o modal
                modalOverlay.classList.remove('hidden');
                setTimeout(() => { 
                    modalOverlay.classList.remove('opacity-0');
                    lucide.createIcons(); 
                }, 10);
                
                // Configura o botão de fechar
                if (closeBtn) {
                    closeBtn.onclick = () => {
                        modalOverlay.classList.add('opacity-0');
                        setTimeout(() => {
                            modalOverlay.classList.add('hidden');
                            // Reseta a mensagem para o padrão
                            if (messageEl) {
                                messageEl.textContent = 'Não foi possível processar seu pagamento.';
                            }
                        }, 300);
                    };
                }
                
                // Fecha ao clicar fora do modal
                modalOverlay.onclick = (e) => {
                    if (e.target === modalOverlay) {
                        modalOverlay.classList.add('opacity-0');
                        setTimeout(() => {
                            modalOverlay.classList.add('hidden');
                            if (messageEl) {
                                messageEl.textContent = 'Não foi possível processar seu pagamento.';
                            }
                        }, 300);
                    }
                };
            }

            function startPaymentCheck(paymentId, sellerId, gatewayUsed, redirectUrlAfterApproval) {
                if (paymentCheckInterval) clearInterval(paymentCheckInterval);
                let attempts = 0;
                paymentCheckInterval = setInterval(async () => {
                    attempts++;
                    if (attempts > 120) { 
                        clearInterval(paymentCheckInterval); 
                        showAlert("Tempo expirou. Verifique o status do pagamento manualmente."); 
                        return; 
                    }
                    try {
                        // Passa o gateway para o check_status.php
                        const response = await fetch(`/check_status?id=${paymentId}&seller_id=${sellerId}&gateway=${gatewayUsed}`);
                        
                        // Se a resposta não for OK, tenta ler o texto para debug
                        if (!response.ok) {
                            const text = await response.text();
                            if (text) {
                                try {
                                    const errorResult = JSON.parse(text);
                                    console.warn('Erro ao verificar status:', errorResult.message || 'Erro desconhecido');
                                } catch (e) {
                                    console.error('Resposta de erro não é JSON válido:', text.substring(0, 200));
                                }
                            } else {
                                console.error('Resposta vazia do servidor (HTTP ' + response.status + ')');
                            }
                            // Continua tentando
                            return;
                        }
                        
                        // Verifica se a resposta é JSON válido
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            console.error('Resposta não é JSON:', text.substring(0, 200));
                            // Não para o intervalo, tenta novamente na próxima iteração
                            return;
                        }
                        
                        const text = await response.text();
                        if (!text || text.trim() === '') {
                            console.error('Resposta vazia do servidor');
                            return;
                        }
                        
                        let result;
                        try {
                            result = JSON.parse(text);
                        } catch (e) {
                            console.error('Erro ao fazer parse do JSON:', e, 'Resposta:', text.substring(0, 200));
                            return;
                        }
                        
                        if (result.status === 'approved' || result.status === 'paid') {
                            clearInterval(paymentCheckInterval);
                            
                            // Fecha modal de pendente (cartão) se estiver aberto
                            const pendingModal = document.getElementById('pending-modal-overlay');
                            if (pendingModal && !pendingModal.classList.contains('hidden')) {
                                pendingModal.classList.add('hidden');
                            }
                            
                            // Fecha modal Pix se estiver aberto
                            const pixWaiting = document.getElementById('pix-waiting-state');
                            const pixApproved = document.getElementById('pix-approved-state');
                            if (pixWaiting && !pixWaiting.classList.contains('hidden')) {
                                pixWaiting.classList.add('hidden');
                                if (pixApproved) pixApproved.classList.remove('hidden');
                            }
                            
                            lucide.createIcons();
                            
                            // Prioridade: URL do backend (funil), depois customRedirectUrl, depois obrigado (backend já envia URL com payment_id quando é funil)
                            const redirectTo = redirectUrlAfterApproval
                                || (customRedirectUrl ? (customRedirectUrl.includes('?') ? `${customRedirectUrl}&payment_id=${paymentId}` : `${customRedirectUrl}?payment_id=${paymentId}`) : null)
                                || `/obrigado?payment_id=${paymentId}`;
                            setTimeout(() => { window.location.href = redirectTo; }, 2000);
                        } else if (result.status === 'error') {
                            // Se houver erro, loga mas continua tentando
                            console.warn('Erro ao verificar status:', result.message || 'Erro desconhecido');
                        } else if (result.status === 'pending') {
                            // Status ainda pendente, continua verificando
                            // Não faz nada, apenas continua o loop
                        }
                    } catch (error) { 
                        console.error('Erro ao verificar status do pagamento:', error);
                        // Não para o intervalo, continua tentando
                    }
                }, 5000);
            }

            // --- LÓGICA PRODUTO GRÁTIS ---
            const btnGetFreeProduct = document.getElementById('btn-get-free-product');
            if (btnGetFreeProduct) {
                btnGetFreeProduct.addEventListener('click', async () => {
                    const payerData = validateForm();
                    if (!payerData) return;

                    btnGetFreeProduct.disabled = true;
                    btnGetFreeProduct.innerHTML = '<i class="animate-spin h-6 w-6 mr-2" data-lucide="loader-2"></i> Processando...';
                    lucide.createIcons();

                    try {
                        const response = await fetch('/process_free.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                ...payerData,
                                utm_parameters: utmParameters
                            })
                        });
                        
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            console.error('Resposta não é JSON:', text);
                            showAlert('Erro: Resposta inválida do servidor. Tente novamente.');
                            return;
                        }
                        
                        const result = await response.json();

                        if (response.ok && result.status === 'approved') {
                            // Redireciona para página de obrigado
                            window.location.href = result.redirect_url || '/obrigado?payment_id=' + result.payment_id;
                        } else {
                            showAlert(result.error || 'Erro ao processar. Tente novamente.');
                        }
                    } catch (e) {
                        console.error('Erro ao processar produto grátis:', e);
                        showAlert('Erro de conexão. Verifique sua internet e tente novamente.');
                    } finally {
                        btnGetFreeProduct.disabled = false;
                        btnGetFreeProduct.innerHTML = '<i data-lucide="download" class="w-6 h-6"></i> QUERO MEU ACESSO GRÁTIS';
                        lucide.createIcons();
                    }
                });
            }
        });
    </script>
</body>
</html>
