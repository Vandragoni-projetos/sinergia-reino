<?php
// Carrega autoload do Composer (Stripe SDK e demais dependências)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Inicia buffer de saída para capturar qualquer output indesejado
ob_start();

// Desabilita exibição de erros antes de qualquer output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/process_payment_log.txt');
error_reporting(E_ALL);

// Função para retornar erro JSON de forma segura
function returnJsonError($message, $code = 500) {
    ob_clean(); // Limpa qualquer output anterior
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

// Função para retornar sucesso JSON
function returnJsonSuccess($data) {
    ob_clean(); // Limpa qualquer output anterior
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Tenta carregar config.php (pode estar na raiz ou em config/)
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/config/config.php',
    dirname(__DIR__) . '/config/config.php'
];

$config_loaded = false;
foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        try {
            // Captura qualquer output do config.php
            ob_start();
            require $config_path;
            ob_end_clean();
            $config_loaded = true;
            break;
        } catch (Exception $e) {
            ob_end_clean();
            returnJsonError('Erro ao carregar configuração: ' . $e->getMessage(), 500);
        } catch (Error $e) {
            ob_end_clean();
            returnJsonError('Erro fatal ao carregar configuração: ' . $e->getMessage(), 500);
        }
    }
}

if (!$config_loaded) {
    returnJsonError('Arquivo de configuração não encontrado.', 500);
}

// Limpa o buffer inicial
ob_end_clean();

// Define header JSON
header('Content-Type: application/json');

// Inclui o helper da UTMfy
$utmfy_paths = [
    __DIR__ . '/helpers/utmfy_helper.php',
    dirname(__DIR__) . '/helpers/utmfy_helper.php',
    __DIR__ . '/utmfy_helper.php'
];

foreach ($utmfy_paths as $utmfy_path) {
    if (file_exists($utmfy_path)) {
        try {
            ob_start();
            require_once $utmfy_path;
            ob_end_clean();
            break;
        } catch (Exception $e) {
            ob_end_clean();
            error_log('Erro ao carregar utmfy_helper: ' . $e->getMessage());
        } catch (Error $e) {
            ob_end_clean();
            error_log('Erro fatal ao carregar utmfy_helper: ' . $e->getMessage());
        }
    }
}

// Inclui o helper da Evolution API (WhatsApp)
$evolution_paths = [
    __DIR__ . '/helpers/evolution_helper.php',
    dirname(__DIR__) . '/helpers/evolution_helper.php'
];

$evolution_loaded = false;
foreach ($evolution_paths as $evolution_path) {
    if (file_exists($evolution_path)) {
        try {
            ob_start();
            require_once $evolution_path;
            ob_end_clean();
            $evolution_loaded = true;
            break;
        } catch (Exception $e) {
            ob_end_clean();
            error_log('Erro ao carregar evolution_helper: ' . $e->getMessage());
        } catch (Error $e) {
            ob_end_clean();
            error_log('Erro fatal ao carregar evolution_helper: ' . $e->getMessage());
        }
    }
}

function log_process($msg) {
    $log_file = __DIR__ . '/process_payment_log.txt';
    @file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

log_process("INÍCIO DO PROCESSAMENTO");
log_process("Evolution Helper carregado: " . (function_exists('process_evolution_messages') ? 'SIM' : 'NÃO'));

$raw_post_data = file_get_contents('php://input');
$data = json_decode($raw_post_data, true);

if (!$data) {
    returnJsonError('Dados inválidos.', 400);
}

// Campos comuns (cpf opcional quando lang != pt - checkout internacional)
$checkout_lang = $data['lang'] ?? 'pt';
$required_fields = ['transaction_amount', 'email', 'name', 'phone', 'product_id'];
if ($checkout_lang === 'pt') {
    $required_fields[] = 'cpf';
}
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        returnJsonError("Campo obrigatório ausente: $field", 400);
    }
}

// 1. Descobrir Gateway e Credenciais
$main_product_id = $data['product_id'];
$gateway_choice = $data['gateway'] ?? 'mercadopago';

try {
    $stmt_prod = $pdo->prepare("SELECT usuario_id, nome, preco, price_usd FROM produtos WHERE id = ?");
    $stmt_prod->execute([$main_product_id]);
    $product_info = $stmt_prod->fetch(PDO::FETCH_ASSOC);
    if (!$product_info) throw new Exception("Produto não encontrado.");
    
    $usuario_id = $product_info['usuario_id'];
    $main_product_name = $product_info['nome'];

    try {
        $stmt_user = $pdo->prepare("SELECT mp_access_token, pushinpay_token, efi_client_id, efi_client_secret, efi_certificate_path, efi_pix_key, efi_payee_code, beehive_secret_key, beehive_public_key, hypercash_secret_key, hypercash_public_key, pagarme_api_key, pagarme_api_secret, paypal_client_id, paypal_client_secret, stripe_publishable_key, stripe_secret_key, stripe_webhook_secret FROM usuarios WHERE id = ?");
        $stmt_user->execute([$usuario_id]);
        $credentials = $stmt_user->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt_user = $pdo->prepare("SELECT mp_access_token, pushinpay_token, efi_client_id, efi_client_secret, efi_certificate_path, efi_pix_key, efi_payee_code, beehive_secret_key, beehive_public_key, hypercash_secret_key, hypercash_public_key FROM usuarios WHERE id = ?");
        $stmt_user->execute([$usuario_id]);
        $credentials = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $credentials['pagarme_api_key'] = $credentials['pagarme_api_secret'] = $credentials['paypal_client_id'] = $credentials['paypal_client_secret'] = $credentials['stripe_publishable_key'] = $credentials['stripe_secret_key'] = $credentials['stripe_webhook_secret'] = null;
    }
    
    // URL Webhook
    $domainName = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['PHP_SELF']);
    $path = rtrim(str_replace('\\', '/', $scriptDir), '/');
    
    // URL Obrigado (ou funil de vendas se ativo)
    $stmt_prod_conf = $pdo->prepare("SELECT checkout_config FROM produtos WHERE id = ?");
    $stmt_prod_conf->execute([$main_product_id]);
    $p_conf = $stmt_prod_conf->fetch(PDO::FETCH_ASSOC);
    $checkout_config = json_decode($p_conf['checkout_config'] ?? '{}', true);
    // Base URL sem barras duplas (Stripe exige URLs absolutas válidas)
    $path_clean = $path ? '/' . trim($path, '/') : '';
    $base_url_funnel = 'https://' . $domainName . ($path_clean ? $path_clean . '/' : '/');
    $redirect_url_raw = trim($checkout_config['redirectUrl'] ?? '');
    $redirect_url_after_approval = ($redirect_url_raw !== '' && filter_var($redirect_url_raw, FILTER_VALIDATE_URL))
        ? $redirect_url_raw
        : ($base_url_funnel . 'obrigado');
    if (file_exists(__DIR__ . '/helpers/funnel_helper.php')) {
        require_once __DIR__ . '/helpers/funnel_helper.php';
    }
    // Etapa 5: redirect pós-pagamento quando compra veio do funil (upsell/downsell)
    $funnel_main_payment_id = isset($data['funnel_main_payment_id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', substr(trim((string)$data['funnel_main_payment_id']), 0, 128)) : '';
    $funnel_step_param = isset($data['funnel_step']) ? strtolower(trim((string)$data['funnel_step'])) : '';
    if (!in_array($funnel_step_param, ['upsell', 'downsell'], true)) $funnel_step_param = '';
    $redirect_url_computed = function($payment_id) use ($pdo, $main_product_id, $redirect_url_after_approval, $base_url_funnel, $funnel_main_payment_id, $funnel_step_param) {
        if ($funnel_main_payment_id !== '' && $funnel_step_param !== '' && function_exists('build_funnel_redirect_after_offer_payment')) {
            $obrigado_default = $redirect_url_after_approval . '?payment_id=' . urlencode($funnel_main_payment_id);
            $url = build_funnel_redirect_after_offer_payment($pdo, $funnel_main_payment_id, $funnel_step_param, rtrim($base_url_funnel, '/'), $obrigado_default);
            if ($url !== null) return $url;
        }
        return function_exists('build_final_redirect_url') ? build_final_redirect_url($pdo, $main_product_id, $payment_id, $redirect_url_after_approval . '?payment_id=' . $payment_id, $base_url_funnel) : ($redirect_url_after_approval . '?payment_id=' . $payment_id);
    };

    log_process("Webhook URL gerada: " . $webhook_url);
    $checkout_session_uuid = uniqid('checkout_') . bin2hex(random_bytes(8));
    
    // UTMs
    $utm_parameters = $data['utm_parameters'] ?? [];

    // ==========================================================
    // FLUXO PUSHINPAY
    // ==========================================================
    if ($gateway_choice === 'pushinpay') {
        
        $token = $credentials['pushinpay_token'] ?? '';
        if (empty($token)) throw new Exception("Token PushinPay não configurado.");

        $amount_cents = (int)(round((float)$data['transaction_amount'], 2) * 100);
        $payload = [
            "value" => $amount_cents,
            "webhook_url" => $webhook_url,
            "payer" => [
                 "name" => $data['name'],
                 "document" => preg_replace('/[^0-9]/', '', $data['cpf']),
                 "email" => $data['email']
            ]
        ];

        $ch = curl_init('https://api.pushinpay.com.br/api/pix/cashIn');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        log_process("PushinPay Response HTTP Code: $http_code");
        log_process("PushinPay Response: " . substr($response, 0, 500));
        
        if ($curl_error) {
            log_process("PushinPay cURL Error: " . $curl_error);
            throw new Exception("Erro de conexão com PushinPay: " . $curl_error);
        }
        
        $res_data = json_decode($response, true);
        
        if ($http_code >= 200 && $http_code < 300 && isset($res_data['qr_code_base64'])) {
            $payment_id = $res_data['id'] ?? null;
            if (!$payment_id) {
                log_process("PushinPay: Resposta sem ID de pagamento");
                throw new Exception("Resposta inválida da API PushinPay: ID não encontrado");
            }
            
            $status = 'pending';
            
            // Salva Venda
            save_sales($pdo, $data, $main_product_id, $payment_id, $status, 'Pix', $checkout_session_uuid, $utm_parameters);

            // --- DISPARO IMEDIATO PARA UTMFY (Status: Waiting Payment) ---
            if (function_exists('trigger_utmfy_integrations')) {
                // Monta estrutura de evento compatível
                $event_data_utmfy = [
                    'transacao_id' => $payment_id,
                    'valor_total_compra' => $data['transaction_amount'],
                    'comprador' => [
                        'nome' => $data['name'], 'email' => $data['email'], 
                        'telefone' => $data['phone'], 'cpf' => $data['cpf']
                    ],
                    'metodo_pagamento' => 'Pix',
                    'produtos_comprados' => [[
                        'produto_id' => $main_product_id, 'nome' => $main_product_name, 'valor' => $data['transaction_amount']
                    ]],
                    'utm_parameters' => $utm_parameters,
                    'data_venda' => date('Y-m-d H:i:s')
                ];
                trigger_utmfy_integrations($usuario_id, $event_data_utmfy, 'pending', $main_product_id);
            }
            // -------------------------------------------------------------

            // --- DISPARO EVOLUTION API (WhatsApp) - Pix Gerado ---
            if (function_exists('process_evolution_messages')) {
                $sale_data_evolution = [
                    'id' => $pdo->lastInsertId(),
                    'produto_id' => $main_product_id,
                    'comprador_nome' => $data['name'],
                    'comprador_email' => $data['email'],
                    'comprador_telefone' => $data['phone'],
                    'valor' => $data['transaction_amount'],
                    'transacao_id' => $payment_id,
                    'data_venda' => date('Y-m-d H:i:s')
                ];
                process_evolution_messages($pdo, $sale_data_evolution, 'pending');
                log_process("Evolution API: Disparo para evento 'pending' (Pix gerado) - PushinPay");
            }
            // -------------------------------------------------------------

            returnJsonSuccess([
                'status' => 'pix_created',
                'pix_data' => [
                    'qr_code_base64' => $res_data['qr_code_base64'],
                    'qr_code' => $res_data['qr_code'] ?? '',
                    'payment_id' => $payment_id
                ],
                'redirect_url_after_approval' => $redirect_url_computed($payment_id)
            ]);

        } else {
            $error_msg = "Erro ao processar pagamento";
            if (isset($res_data['message'])) {
                $error_msg = $res_data['message'];
            } elseif (isset($res_data['error'])) {
                $error_msg = is_array($res_data['error']) ? implode(', ', $res_data['error']) : $res_data['error'];
            } elseif (!empty($response)) {
                $error_msg = "Resposta inesperada: " . substr($response, 0, 200);
            }
            
            log_process("PushinPay Error ($http_code): " . $error_msg);
            throw new Exception("PushinPay Error ($http_code): " . $error_msg);
        }

    // ==========================================================
    // FLUXO EFÍ (PIX)
    // ==========================================================
    } elseif ($gateway_choice === 'efi') {
        require_once __DIR__ . '/gateways/efi.php';
        
        $client_id = trim($credentials['efi_client_id'] ?? '');
        $client_secret = trim($credentials['efi_client_secret'] ?? '');
        $certificate_path = trim($credentials['efi_certificate_path'] ?? '');
        $pix_key = trim($credentials['efi_pix_key'] ?? '');
        
        if (empty($client_id) || empty($client_secret) || empty($certificate_path) || empty($pix_key)) {
            throw new Exception("Credenciais Efí não configuradas completamente.");
        }
        
        $certificate_path_normalized = ltrim(str_replace('\\', '/', $certificate_path), '/');
        $full_cert_path = __DIR__ . '/' . $certificate_path_normalized;
        
        if (!file_exists($full_cert_path)) {
            log_process("Efí Pix: Certificado não encontrado. Caminho verificado: " . realpath(__DIR__) . '/' . $certificate_path_normalized);
            $msg = "Certificado Efí não encontrado. Verifique se o arquivo existe em uploads/certificados/ e se foi enviado em Integrações.";
            throw new Exception($msg);
        }
        
        $token_data = efi_get_access_token($client_id, $client_secret, $full_cert_path);
        if (!$token_data) {
            throw new Exception("Erro ao obter token de acesso Efí.");
        }
        
        $payer_data = [
            'name' => $data['name'],
            'cpf' => $data['cpf'],
            'email' => $data['email']
        ];
        
        $pix_result = efi_create_pix_charge(
            $token_data['access_token'],
            (float)$data['transaction_amount'],
            $pix_key,
            $payer_data,
            'Compra: ' . $main_product_name,
            60,
            $full_cert_path
        );
        
        if (!$pix_result || !isset($pix_result['txid'])) {
            $error_msg = $pix_result['message'] ?? 'Erro ao criar cobrança Pix na Efí.';
            throw new Exception($error_msg);
        }
        
        $payment_id = $pix_result['txid'];
        $status = 'pending';
        
        save_sales($pdo, $data, $main_product_id, $payment_id, $status, 'Pix', $checkout_session_uuid, $utm_parameters);
        
        if (function_exists('trigger_utmfy_integrations')) {
            $event_data_utmfy = [
                'transacao_id' => $payment_id,
                'valor_total_compra' => $data['transaction_amount'],
                'comprador' => ['nome' => $data['name'], 'email' => $data['email'], 'telefone' => $data['phone'], 'cpf' => $data['cpf']],
                'metodo_pagamento' => 'Pix',
                'produtos_comprados' => [['produto_id' => $main_product_id, 'nome' => $main_product_name, 'valor' => $data['transaction_amount']]],
                'utm_parameters' => $utm_parameters,
                'data_venda' => date('Y-m-d H:i:s')
            ];
            trigger_utmfy_integrations($usuario_id, $event_data_utmfy, 'pending', $main_product_id);
        }
        
        // --- DISPARO EVOLUTION API (WhatsApp) - Pix Gerado ---
        if (function_exists('process_evolution_messages')) {
            log_process("Evolution API: Preparando disparo para Pix Efí - Telefone: " . ($data['phone'] ?? 'NÃO INFORMADO'));
            $sale_data_evolution = [
                'id' => $pdo->lastInsertId(),
                'produto_id' => $main_product_id,
                'comprador_nome' => $data['name'],
                'comprador_email' => $data['email'],
                'comprador_telefone' => $data['phone'],
                'valor' => $data['transaction_amount'],
                'transacao_id' => $payment_id,
                'data_venda' => date('Y-m-d H:i:s')
            ];
            process_evolution_messages($pdo, $sale_data_evolution, 'pending');
            log_process("Evolution API: Disparo para evento 'pending' (Pix gerado) - Efí");
        } else {
            log_process("Evolution API: Função process_evolution_messages NÃO EXISTE");
        }
        // -------------------------------------------------------------
        
        returnJsonSuccess([
            'status' => 'pix_created',
            'pix_data' => [
                'qr_code_base64' => $pix_result['qr_code_base64'] ?? null,
                'qr_code' => $pix_result['qr_code'] ?? '',
                'payment_id' => $payment_id
            ],
            'redirect_url_after_approval' => $redirect_url_computed($payment_id)
        ]);

    // ==========================================================
    // FLUXO BEEHIVE (CARTÃO)
    // ==========================================================
    } elseif ($gateway_choice === 'beehive') {
        require_once __DIR__ . '/gateways/beehive.php';
        
        $secret_key = $credentials['beehive_secret_key'] ?? '';
        $public_key = $credentials['beehive_public_key'] ?? '';
        
        if (empty($secret_key) || empty($public_key)) {
            throw new Exception("Credenciais Beehive não configuradas.");
        }
        
        if (empty($data['card_token'])) {
            throw new Exception("Token do cartão não fornecido.");
        }
        
        $cpf = preg_replace('/[^0-9]/', '', $data['cpf'] ?? '');
        if (strlen($cpf) !== 11) {
            throw new Exception("CPF inválido.");
        }
        
        $card_data = $data['card_data'] ?? null;
        $client_ip = function_exists('get_client_ip') ? get_client_ip() : null;
        
        $payment_result = beehive_create_payment(
            $secret_key,
            $public_key,
            (float)$data['transaction_amount'],
            $data['card_token'],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'cpf' => $cpf,
                'phone' => preg_replace('/[^0-9]/', '', $data['phone'] ?? '')
            ],
            'Compra: ' . $main_product_name,
            $webhook_url,
            $card_data,
            $client_ip
        );
        
        if (!$payment_result || (isset($payment_result['error']) && $payment_result['error'])) {
            throw new Exception($payment_result['message'] ?? 'Erro ao processar pagamento Beehive.');
        }
        
        $status = $payment_result['status'];
        $payment_id = $payment_result['payment_id'];
        
        save_sales($pdo, $data, $main_product_id, $payment_id, $status, 'Cartão de crédito', $checkout_session_uuid, $utm_parameters);
        
        if (function_exists('trigger_utmfy_integrations')) {
            $event_data_utmfy = [
                'transacao_id' => $payment_id,
                'valor_total_compra' => $data['transaction_amount'],
                'comprador' => ['nome' => $data['name'], 'email' => $data['email'], 'telefone' => $data['phone'], 'cpf' => $data['cpf']],
                'metodo_pagamento' => 'Cartão de crédito',
                'produtos_comprados' => [['produto_id' => $main_product_id, 'nome' => $main_product_name, 'valor' => $data['transaction_amount']]],
                'utm_parameters' => $utm_parameters,
                'data_venda' => date('Y-m-d H:i:s')
            ];
            trigger_utmfy_integrations($usuario_id, $event_data_utmfy, $status === 'approved' ? 'approved' : 'pending', $main_product_id);
        }
        
        $response_data = ['status' => $status, 'payment_id' => $payment_id];
        if ($status === 'approved') {
            $response_data['redirect_url'] = $redirect_url_computed($payment_id);
        }
        returnJsonSuccess($response_data);

    // ==========================================================
    // FLUXO HYPERCASH (CARTÃO)
    // ==========================================================
    } elseif ($gateway_choice === 'hypercash') {
        require_once __DIR__ . '/gateways/hypercash.php';
        
        $secret_key = $credentials['hypercash_secret_key'] ?? '';
        $public_key = $credentials['hypercash_public_key'] ?? '';
        
        if (empty($secret_key) || empty($public_key)) {
            throw new Exception("Credenciais Hypercash não configuradas.");
        }
        
        if (empty($data['card_token'])) {
            throw new Exception("Token do cartão não fornecido.");
        }
        
        $cpf = preg_replace('/[^0-9]/', '', $data['cpf'] ?? '');
        if (strlen($cpf) !== 11) {
            throw new Exception("CPF inválido.");
        }
        
        $card_data = $data['card_data'] ?? null;
        $client_ip = function_exists('hypercash_get_client_ip') ? hypercash_get_client_ip() : null;
        
        $payment_result = hypercash_create_payment(
            $secret_key,
            $public_key,
            (float)$data['transaction_amount'],
            $data['card_token'],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'cpf' => $cpf,
                'phone' => preg_replace('/[^0-9]/', '', $data['phone'] ?? '')
            ],
            'Compra: ' . $main_product_name,
            $webhook_url,
            $card_data,
            $client_ip
        );
        
        if (!$payment_result || (isset($payment_result['error']) && $payment_result['error'])) {
            throw new Exception($payment_result['message'] ?? 'Erro ao processar pagamento Hypercash.');
        }
        
        $status = $payment_result['status'];
        $payment_id = $payment_result['payment_id'];
        
        save_sales($pdo, $data, $main_product_id, $payment_id, $status, 'Cartão de crédito', $checkout_session_uuid, $utm_parameters);
        
        if (function_exists('trigger_utmfy_integrations')) {
            $event_data_utmfy = [
                'transacao_id' => $payment_id,
                'valor_total_compra' => $data['transaction_amount'],
                'comprador' => ['nome' => $data['name'], 'email' => $data['email'], 'telefone' => $data['phone'], 'cpf' => $data['cpf']],
                'metodo_pagamento' => 'Cartão de crédito',
                'produtos_comprados' => [['produto_id' => $main_product_id, 'nome' => $main_product_name, 'valor' => $data['transaction_amount']]],
                'utm_parameters' => $utm_parameters,
                'data_venda' => date('Y-m-d H:i:s')
            ];
            trigger_utmfy_integrations($usuario_id, $event_data_utmfy, $status === 'approved' ? 'approved' : 'pending', $main_product_id);
        }
        
        $response_data = ['status' => $status, 'payment_id' => $payment_id];
        if ($status === 'approved') {
            $response_data['redirect_url'] = $redirect_url_computed($payment_id);
        }
        returnJsonSuccess($response_data);

    // ==========================================================
    // FLUXO EFÍ CARTÃO
    // ==========================================================
    } elseif ($gateway_choice === 'efi_card') {
        log_process("Efí Cartão: Iniciando processamento");
        require_once __DIR__ . '/gateways/efi.php';
        
        $client_id = trim($credentials['efi_client_id'] ?? '');
        $client_secret = trim($credentials['efi_client_secret'] ?? '');
        $certificate_path = trim($credentials['efi_certificate_path'] ?? '');
        
        if (empty($client_id) || empty($client_secret) || empty($certificate_path)) {
            throw new Exception("Credenciais Efí não configuradas completamente.");
        }
        
        if (empty($data['payment_token'])) {
            throw new Exception("Payment token não fornecido.");
        }
        
        $cpf = preg_replace('/[^0-9]/', '', $data['cpf'] ?? '');
        if (strlen($cpf) !== 11) {
            throw new Exception("CPF inválido.");
        }
        
        $amount = (float)($data['transaction_amount'] ?? 0);
        if ($amount <= 0) {
            throw new Exception("Valor inválido.");
        }
        
        $certificate_path_normalized = ltrim(str_replace('\\', '/', $certificate_path), '/');
        $full_cert_path = __DIR__ . '/' . $certificate_path_normalized;
        if (!file_exists($full_cert_path)) {
            log_process("Efí Cartão: Certificado não encontrado. Caminho: " . realpath(__DIR__) . '/' . $certificate_path_normalized);
            throw new Exception("Certificado Efí não encontrado. Verifique se o arquivo existe em uploads/certificados/ e se foi enviado em Integrações.");
        }
        
        $token_data = efi_get_charges_access_token($client_id, $client_secret, $full_cert_path);
        if (!$token_data || !isset($token_data['access_token'])) {
            throw new Exception("Erro ao autenticar com Efí.");
        }
        
        $installments = (int)($data['installments'] ?? 1);
        if ($installments < 1 || $installments > 12) {
            $installments = 1;
        }
        
        $payment_result = efi_create_card_charge(
            $token_data['access_token'],
            $amount,
            $data['payment_token'],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'cpf' => $cpf,
                'phone' => $data['phone']
            ],
            'Compra: ' . $main_product_name,
            $webhook_url,
            $full_cert_path,
            $installments
        );
        
        if (!$payment_result || (isset($payment_result['error']) && $payment_result['error'])) {
            log_process("Efí Cartão: Erro retornado - " . json_encode($payment_result));
            $err_msg = $payment_result['message'] ?? 'Erro ao processar pagamento Efí.';
            if (is_array($err_msg)) {
                $err_msg = implode(', ', array_map(function ($v) {
                    return is_array($v) && isset($v['message']) ? $v['message'] : (is_string($v) ? $v : json_encode($v));
                }, isset($err_msg[0]) ? $err_msg : [$err_msg])) ?: 'Erro ao processar pagamento Efí.';
            }
            throw new Exception($err_msg);
        }
        
        $status = $payment_result['status'];
        $payment_id = $payment_result['charge_id'];
        
        log_process("Efí Cartão: Pagamento processado - status: $status, charge_id: $payment_id");
        
        save_sales($pdo, $data, $main_product_id, $payment_id, $status, 'Cartão de crédito', $checkout_session_uuid, $utm_parameters);
        
        if (function_exists('trigger_utmfy_integrations')) {
            $event_data_utmfy = [
                'transacao_id' => $payment_id,
                'valor_total_compra' => $amount,
                'comprador' => ['nome' => $data['name'], 'email' => $data['email'], 'telefone' => $data['phone'], 'cpf' => $data['cpf']],
                'metodo_pagamento' => 'Cartão de crédito',
                'produtos_comprados' => [['produto_id' => $main_product_id, 'nome' => $main_product_name, 'valor' => $amount]],
                'utm_parameters' => $utm_parameters,
                'data_venda' => date('Y-m-d H:i:s')
            ];
            trigger_utmfy_integrations($usuario_id, $event_data_utmfy, $status === 'approved' ? 'approved' : 'pending', $main_product_id);
        }
        
        // --- DISPARO EVOLUTION API (WhatsApp) - Efí Cartão ---
        if (function_exists('process_evolution_messages')) {
            $sale_data_evolution = [
                'id' => $pdo->lastInsertId(),
                'produto_id' => $main_product_id,
                'comprador_nome' => $data['name'],
                'comprador_email' => $data['email'],
                'comprador_telefone' => $data['phone'],
                'valor' => $amount,
                'transacao_id' => $payment_id,
                'data_venda' => date('Y-m-d H:i:s')
            ];
            // Mapeia status para evento Evolution
            $evolution_event = 'pending';
            if ($status === 'approved') {
                $evolution_event = 'approved';
            } elseif ($status === 'rejected') {
                $evolution_event = 'rejected';
            }
            process_evolution_messages($pdo, $sale_data_evolution, $evolution_event);
            log_process("Evolution API: Disparo para evento '$evolution_event' (status: $status) - Efí Cartão");
        }
        // ------------------------------------
        
        $response_data = ['status' => $status, 'payment_id' => $payment_id];
        if ($status === 'approved') {
            $response_data['redirect_url'] = $redirect_url_computed($payment_id);
        }
        returnJsonSuccess($response_data);

    // ==========================================================
    // FLUXO STRIPE CHECKOUT
    // ==========================================================
    } elseif (in_array($gateway_choice, ['stripe', 'stripe_card'])) {
        $stripe_secret = trim($credentials['stripe_secret_key'] ?? '');
        if (empty($stripe_secret)) {
            throw new Exception("Credenciais Stripe não configuradas. Configure em Integrações.");
        }
        require_once __DIR__ . '/gateways/stripe.php';

        $currency = strtolower($data['currency'] ?? 'brl');
        $amount = (float)($data['transaction_amount'] ?? 0);
        if ($amount <= 0) {
            throw new Exception("Valor inválido para pagamento.");
        }

        if ($currency === 'usd') {
            $currency = 'usd';
        } else {
            $currency = 'brl';
        }

        $obrigado_base = rtrim($redirect_url_after_approval, '?');
        $success_url = $obrigado_base . (strpos($obrigado_base, '?') !== false ? '&' : '?') . 'payment_id={CHECKOUT_SESSION_ID}';
        $cancel_hash = $data['checkout_hash'] ?? '';
        $cancel_url = $base_url_funnel . 'checkout' . ($cancel_hash ? '?p=' . urlencode($cancel_hash) . '&' : '?') . 'canceled=1';
        // Garantir URLs absolutas válidas (Stripe rejeita URLs relativas ou malformadas)
        if (empty($obrigado_base) || strpos($obrigado_base, 'http') !== 0 || !filter_var($cancel_url, FILTER_VALIDATE_URL)) {
            log_process("Stripe URLs inválidas - success_base: " . $obrigado_base . " cancel: " . $cancel_url);
            throw new Exception("Configuração de URL inválida. Verifique a URL de redirecionamento do checkout.");
        }

        $checkout_lang = $data['lang'] ?? 'pt';
        if (!in_array($checkout_lang, ['pt', 'es', 'fr', 'en'], true)) $checkout_lang = 'pt';

        $stripe_params = [
            'secret_key' => $stripe_secret,
            'amount' => $amount,
            'currency' => $currency,
            'product_name' => $main_product_name,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'customer_email' => $data['email'],
            'metadata' => [
                'produto_id' => (string)$main_product_id,
                'email_cliente' => $data['email'],
                'checkout_session_uuid' => $checkout_session_uuid,
            ],
            'test_mode' => (strpos($stripe_secret, 'sk_test_') === 0),
            'locale' => $checkout_lang,
        ];

        $stripe_result = create_stripe_checkout_session($stripe_params);
        if (!$stripe_result || empty($stripe_result['checkout_url'])) {
            log_process("Stripe: Falha ao criar sessão - " . json_encode($stripe_result));
            throw new Exception("Erro ao criar sessão de pagamento Stripe. Tente novamente.");
        }

        $stripe_session_id = $stripe_result['session_id'];
        save_sales($pdo, $data, $main_product_id, $stripe_session_id, 'pending', 'Cartão Stripe', $checkout_session_uuid, $utm_parameters);

        if (function_exists('trigger_utmfy_integrations')) {
            $event_data_utmfy = [
                'transacao_id' => $stripe_session_id,
                'valor_total_compra' => $amount,
                'comprador' => ['nome' => $data['name'], 'email' => $data['email'], 'telefone' => $data['phone'], 'cpf' => $data['cpf']],
                'metodo_pagamento' => 'Cartão Stripe',
                'produtos_comprados' => [['produto_id' => $main_product_id, 'nome' => $main_product_name, 'valor' => $amount]],
                'utm_parameters' => $utm_parameters,
                'data_venda' => date('Y-m-d H:i:s')
            ];
            trigger_utmfy_integrations($usuario_id, $event_data_utmfy, 'pending', $main_product_id);
        }

        returnJsonSuccess([
            'checkout_url' => $stripe_result['checkout_url'],
            'session_id' => $stripe_session_id,
        ]);

    // ==========================================================
    // FLUXO PAYPAL
    // ==========================================================
    } elseif ($gateway_choice === 'paypal') {
        $paypal_client = trim($credentials['paypal_client_id'] ?? '');
        $paypal_secret = trim($credentials['paypal_client_secret'] ?? '');
        if (empty($paypal_client) || empty($paypal_secret)) {
            throw new Exception("Credenciais PayPal não configuradas. Configure em Integrações.");
        }
        require_once __DIR__ . '/gateways/paypal.php';

        $currency = strtoupper($data['currency'] ?? 'brl');
        $amount = (float)($data['transaction_amount'] ?? 0);
        if ($amount <= 0) throw new Exception("Valor inválido para pagamento.");

        if ($currency !== 'USD') $currency = 'BRL';

        $base_url = 'https://' . $domainName . ($path ? $path . '/' : '/');
        $return_url = $base_url . 'paypal_return.php';
        $cancel_hash = $data['checkout_hash'] ?? '';
        $cancel_url = $base_url . 'checkout' . ($cancel_hash ? '?p=' . urlencode($cancel_hash) . '&' : '?') . 'canceled=1';

        $paypal_lang = $data['lang'] ?? 'pt';
        if (!in_array($paypal_lang, ['pt', 'es', 'fr', 'en'], true)) $paypal_lang = 'pt';
        $paypal_locale = ['pt' => 'pt-BR', 'es' => 'es-ES', 'fr' => 'fr-FR', 'en' => 'en-US'][$paypal_lang];

        $paypal_params = [
            'client_id' => $paypal_client,
            'client_secret' => $paypal_secret,
            'amount' => $amount,
            'currency' => $currency,
            'locale' => $paypal_locale,
            'product_name' => $main_product_name,
            'return_url' => $return_url,
            'cancel_url' => $cancel_url,
            'metadata' => ['checkout_session_uuid' => $checkout_session_uuid],
            'sandbox' => (strpos($paypal_client, 'sb') !== false || strpos($paypal_client, 'sandbox') !== false),
        ];

        $paypal_result = create_paypal_order($paypal_params);
        if (!$paypal_result || empty($paypal_result['approval_url'])) {
            log_process("PayPal: Falha ao criar ordem");
            throw new Exception("Erro ao criar ordem PayPal. Tente novamente.");
        }

        $order_id = $paypal_result['order_id'];
        save_sales($pdo, $data, $main_product_id, $order_id, 'pending', 'PayPal', $checkout_session_uuid, $utm_parameters);

        if (function_exists('trigger_utmfy_integrations')) {
            $event_data_utmfy = [
                'transacao_id' => $order_id,
                'valor_total_compra' => $amount,
                'comprador' => ['nome' => $data['name'], 'email' => $data['email'], 'telefone' => $data['phone'], 'cpf' => $data['cpf']],
                'metodo_pagamento' => 'PayPal',
                'produtos_comprados' => [['produto_id' => $main_product_id, 'nome' => $main_product_name, 'valor' => $amount]],
                'utm_parameters' => $utm_parameters,
                'data_venda' => date('Y-m-d H:i:s')
            ];
            trigger_utmfy_integrations($usuario_id, $event_data_utmfy, 'pending', $main_product_id);
        }

        returnJsonSuccess([
            'checkout_url' => $paypal_result['approval_url'],
            'order_id' => $order_id,
        ]);

    // ==========================================================
    // FLUXO PAGAR.ME (em desenvolvimento)
    // ==========================================================
    } elseif (in_array($gateway_choice, ['pagarme', 'pagarme_card', 'pagarme_ticket'])) {
        $creds_ok = !empty($credentials['pagarme_api_key'] ?? '') && !empty($credentials['pagarme_api_secret'] ?? '');
        if (!$creds_ok) {
            throw new Exception("Credenciais Pagar.me não configuradas. Configure em Integrações.");
        }
        throw new Exception("Gateway Pagar.me em desenvolvimento. Use Mercado Pago, Efí, PushinPay, Stripe ou PayPal.");

    // ==========================================================
    // FLUXO MERCADO PAGO
    // ==========================================================
    } else {
        $token = $credentials['mp_access_token'] ?? '';
        if (empty($token)) throw new Exception("Token Mercado Pago não configurado.");
        
        $payment_data = [
            'transaction_amount' => (float)$data['transaction_amount'],
            'description' => 'Compra: ' . $main_product_name,
            'payment_method_id' => $data['payment_method_id'],
            'payer' => [
                'email' => $data['email'],
                'first_name' => explode(' ', $data['name'])[0],
                'last_name' => substr(strstr($data['name'], ' '), 1) ?: '',
                'identification' => ['type' => 'CPF', 'number' => preg_replace('/[^0-9]/', '', $data['cpf'])],
            ],
            'external_reference' => $checkout_session_uuid,
            'notification_url' => $webhook_url
        ];

        if (isset($data['token'])) $payment_data['token'] = $data['token'];
        if (isset($data['installments'])) $payment_data['installments'] = (int)$data['installments'];
        if (isset($data['issuer_id'])) $payment_data['issuer_id'] = (int)$data['issuer_id'];

        $ch = curl_init('https://api.mercadopago.com/v1/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'X-Idempotency-Key: ' . $checkout_session_uuid
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res_data = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300 && isset($res_data['status'])) {
            $status = $res_data['status'];
            $payment_id = $res_data['id'];
            $metodo = ($data['payment_method_id'] === 'pix') ? 'Pix' : (($data['payment_method_id'] === 'ticket') ? 'Boleto' : 'Cartão de crédito');

            save_sales($pdo, $data, $main_product_id, $payment_id, $status, $metodo, $checkout_session_uuid, $utm_parameters);

            // --- DISPARO IMEDIATO PARA UTMFY ---
            if (function_exists('trigger_utmfy_integrations')) {
                $event_data_utmfy = [
                    'transacao_id' => $payment_id,
                    'valor_total_compra' => $data['transaction_amount'],
                    'comprador' => [
                        'nome' => $data['name'], 'email' => $data['email'], 
                        'telefone' => $data['phone'], 'cpf' => $data['cpf']
                    ],
                    'metodo_pagamento' => $metodo,
                    'produtos_comprados' => [[
                        'produto_id' => $main_product_id, 'nome' => $main_product_name, 'valor' => $data['transaction_amount']
                    ]],
                    'utm_parameters' => $utm_parameters,
                    'data_venda' => date('Y-m-d H:i:s')
                ];
                // Se for aprovado instantaneamente (Cartão), manda approved, senão pending
                $trigger_status = ($status === 'approved') ? 'approved' : 'pending';
                trigger_utmfy_integrations($usuario_id, $event_data_utmfy, $trigger_status, $main_product_id);
            }
            // ------------------------------------

            // --- DISPARO EVOLUTION API (WhatsApp) ---
            if (function_exists('process_evolution_messages')) {
                $sale_data_evolution = [
                    'id' => $pdo->lastInsertId(),
                    'produto_id' => $main_product_id,
                    'comprador_nome' => $data['name'],
                    'comprador_email' => $data['email'],
                    'comprador_telefone' => $data['phone'],
                    'valor' => $data['transaction_amount'],
                    'transacao_id' => $payment_id,
                    'data_venda' => date('Y-m-d H:i:s')
                ];
                // Mapeia status para evento Evolution
                $evolution_event = 'pending';
                if ($status === 'approved') {
                    $evolution_event = 'approved';
                } elseif (in_array($status, ['rejected', 'cancelled'])) {
                    $evolution_event = 'rejected';
                }
                process_evolution_messages($pdo, $sale_data_evolution, $evolution_event);
                log_process("Evolution API: Disparo para evento '$evolution_event' (status: $status) - Mercado Pago ($metodo)");
            }
            // ------------------------------------

            if ($status == 'pending' && $data['payment_method_id'] == 'pix') {
                returnJsonSuccess([
                    'status' => 'pix_created',
                    'pix_data' => [
                        'qr_code_base64' => $res_data['point_of_interaction']['transaction_data']['qr_code_base64'],
                        'qr_code' => $res_data['point_of_interaction']['transaction_data']['qr_code'],
                        'payment_id' => $payment_id
                    ],
                    'redirect_url_after_approval' => $redirect_url_computed($payment_id)
                ]);
            }

            $response_front = ['status' => $status, 'message' => 'Processado.'];
            if ($status == 'approved') $response_front['redirect_url'] = $redirect_url_computed($payment_id);
            returnJsonSuccess($response_front);

        } else {
            throw new Exception("Mercado Pago Error");
        }
    }

} catch (Exception $e) {
    log_process("Erro Exception: " . $e->getMessage());
    log_process("Stack trace: " . $e->getTraceAsString());
    returnJsonError($e->getMessage(), 500);
} catch (Error $e) {
    log_process("Erro Fatal: " . $e->getMessage());
    log_process("Stack trace: " . $e->getTraceAsString());
    $msg = trim($e->getMessage());
    // Exibe mensagem real para diagnóstico (evita caminhos absolutos)
    $msg = preg_replace('#[A-Za-z]:[/\\\\][^\s]+#', '[path]', $msg);
    returnJsonError($msg ?: 'Erro interno do servidor', 500);
}

function save_sales($pdo, $data, $main_id, $payment_id, $status, $metodo, $uuid, $utm_params) {
    // Verifica limitações via hooks (SaaS) - antes de criar venda
    $hooks_paths = [
        __DIR__ . '/helpers/plugin_hooks.php',
        dirname(__DIR__) . '/helpers/plugin_hooks.php'
    ];
    
    foreach ($hooks_paths as $hooks_path) {
        if (file_exists($hooks_path)) {
            try {
                ob_start();
                require_once $hooks_path;
                ob_end_clean();
                break;
            } catch (Exception $e) {
                ob_end_clean();
                error_log("Erro ao carregar plugin_hooks: " . $e->getMessage());
            }
        }
    }
    
    if (function_exists('do_action')) {
        $limit_check = do_action('before_create_venda', $data['product_id'] ?? 0);
        if ($limit_check && isset($limit_check['allowed']) && !$limit_check['allowed']) {
            throw new Exception($limit_check['message'] ?? 'Limite de pedidos atingido');
        }
    }
    
    // Extrai UTMs
    $utm_source = $utm_params['utm_source'] ?? null;
    $utm_campaign = $utm_params['utm_campaign'] ?? null;
    $utm_medium = $utm_params['utm_medium'] ?? null;
    $utm_content = $utm_params['utm_content'] ?? null;
    $utm_term = $utm_params['utm_term'] ?? null;
    $src = $utm_params['src'] ?? null;
    $sck = $utm_params['sck'] ?? null;
    
    // Extrai oferta_id (apenas para o produto principal)
    $oferta_id = isset($data['oferta_id']) && $data['oferta_id'] ? (int)$data['oferta_id'] : null;
    $cupom_id = isset($data['cupom_id']) && $data['cupom_id'] ? (int)$data['cupom_id'] : null;
    $valor_desconto = isset($data['valor_desconto']) ? (float)$data['valor_desconto'] : 0.0;

    $pdo->beginTransaction();
    try {
        $products = [$main_id];
        $order_bump_ids = [];
        if (isset($data['order_bump_product_ids']) && is_array($data['order_bump_product_ids'])) {
            $order_bump_ids = $data['order_bump_product_ids'];
            $products = array_merge($products, $order_bump_ids);
        }
        
        $placeholders = implode(',', array_fill(0, count($products), '?'));
        $stmt_info = $pdo->prepare("SELECT id, preco, COALESCE(community_id, 1) AS community_id FROM produtos WHERE id IN ($placeholders)");
        $stmt_info->execute($products);
        $prod_map = $stmt_info->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

        $stmt_insert = $pdo->prepare("INSERT INTO vendas (produto_id, community_id, oferta_id, comprador_nome, comprador_email, comprador_cpf, comprador_telefone, valor, status_pagamento, transacao_id, metodo_pagamento, checkout_session_uuid, cupom_id, valor_desconto, email_entrega_enviado, utm_source, utm_campaign, utm_medium, utm_content, utm_term, src, sck) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)");

        // Calcular o valor total dos order bumps para determinar o valor do produto principal
        $order_bumps_total = 0;
        foreach ($order_bump_ids as $ob_id) {
            if (isset($prod_map[$ob_id])) {
                $order_bumps_total += (float)$prod_map[$ob_id]['preco'];
            }
        }
        
        // O transaction_amount inclui produto principal (com desconto Pix se aplicável) + order bumps
        // Valor do produto principal = transaction_amount - total dos order bumps
        $transaction_amount = isset($data['transaction_amount']) ? (float)$data['transaction_amount'] : 0;
        $main_product_value = $transaction_amount - $order_bumps_total;
        
        // Se o cálculo resultar em valor negativo ou zero, usar o preço original do produto
        if ($main_product_value <= 0 && isset($prod_map[$main_id])) {
            $main_product_value = (float)$prod_map[$main_id]['preco'];
        }

        foreach ($products as $pid) {
            if (isset($prod_map[$pid])) {
                // Produto principal usa o valor calculado (com desconto Pix/cupom se aplicável)
                // Order bumps usam o preço original e não têm oferta_id nem cupom
                $val = ($pid == $main_id) ? $main_product_value : $prod_map[$pid]['preco'];
                $current_oferta_id = ($pid == $main_id) ? $oferta_id : null;
                $current_cupom_id = ($pid == $main_id) ? $cupom_id : null;
                $current_valor_desconto = ($pid == $main_id) ? $valor_desconto : 0.0;
                $cid = (int)($prod_map[$pid]['community_id'] ?? 1);
                $stmt_insert->execute([
                    $pid, $cid, $current_oferta_id, $data['name'], $data['email'], 
                    preg_replace('/[^0-9]/', '', $data['cpf']), 
                    preg_replace('/[^0-9]/', '', $data['phone']), 
                    $val, $status, $payment_id, $metodo, $uuid,
                    $current_cupom_id, $current_valor_desconto,
                    $utm_source, $utm_campaign, $utm_medium, $utm_content, $utm_term, $src, $sck
                ]);
            }
        }
        $pdo->commit();

        if ($cupom_id && function_exists('incrementarUsoCupom')) {
            incrementarUsoCupom($cupom_id);
        }
        
        // Incrementa contador de pedidos mensais (SaaS)
        if (function_exists('do_action')) {
            do_action('after_create_venda', $main_id);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao salvar vendas: " . $e->getMessage());
    }
}
?>
