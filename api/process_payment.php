<?php
header('Content-Type: application/json');
require __DIR__ . '/../config/config.php';
// Inclui o helper da UTMfy
if (file_exists(__DIR__ . '/../helpers/utmfy_helper.php')) {
    require_once __DIR__ . '/../helpers/utmfy_helper.php';
}

// Ativa o log de erros detalhado
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../process_payment_log.txt');

function log_process($msg) {
    file_put_contents(__DIR__ . '/../process_payment_log.txt', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

// Função para traduzir mensagens de erro do Mercado Pago
function getMercadoPagoErrorMessage($status, $status_detail, $custom_message = null) {
    if ($custom_message) {
        return $custom_message;
    }
    
    $messages = [
        'rejected' => [
            'cc_rejected_insufficient_amount' => 'Saldo insuficiente no cartão. Tente outro cartão ou método de pagamento.',
            'cc_rejected_bad_filled_security_code' => 'Código de segurança (CVV) incorreto. Verifique e tente novamente.',
            'cc_rejected_bad_filled_date' => 'Data de validade do cartão incorreta. Verifique e tente novamente.',
            'cc_rejected_bad_filled_card_number' => 'Número do cartão incorreto. Verifique e tente novamente.',
            'cc_rejected_high_risk' => 'Pagamento recusado por medidas de segurança. Tente outro cartão ou método de pagamento.',
            'cc_rejected_blacklist' => 'Cartão não autorizado. Tente outro cartão ou método de pagamento.',
            'cc_rejected_other_reason' => 'Pagamento recusado. Tente outro cartão ou método de pagamento.',
        ],
        'cancelled' => 'Pagamento cancelado. Você pode tentar novamente ou escolher outro método de pagamento.',
        'refunded' => 'Pagamento reembolsado.',
        'charged_back' => 'Pagamento contestado.',
    ];
    
    if ($status === 'rejected' && $status_detail && isset($messages['rejected'][$status_detail])) {
        return $messages['rejected'][$status_detail];
    }
    
    if (isset($messages[$status])) {
        return is_string($messages[$status]) ? $messages[$status] : 'Pagamento recusado. Tente outro método de pagamento.';
    }
    
    return 'Pagamento não aprovado. Tente outro método de pagamento.';
}

log_process("INÍCIO DO PROCESSAMENTO");

$raw_post_data = file_get_contents('php://input');
$data = json_decode($raw_post_data, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

// Campos comuns
$required_fields = ['transaction_amount', 'email', 'cpf', 'name', 'phone', 'product_id'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Campo obrigatório ausente: $field"]);
        exit;
    }
}

// 1. Descobrir Gateway e Credenciais
$main_product_id = $data['product_id'];
$gateway_choice = $data['gateway'] ?? 'mercadopago';

try {
    $stmt_prod = $pdo->prepare("SELECT usuario_id, nome FROM produtos WHERE id = ?");
    $stmt_prod->execute([$main_product_id]);
    $product_info = $stmt_prod->fetch(PDO::FETCH_ASSOC);
    if (!$product_info) throw new Exception("Produto não encontrado.");
    
    $usuario_id = $product_info['usuario_id'];
    $main_product_name = $product_info['nome'];

    $stmt_user = $pdo->prepare("SELECT mp_access_token, pushinpay_token FROM usuarios WHERE id = ?");
    $stmt_user->execute([$usuario_id]);
    $credentials = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    // URL Webhook
    $domainName = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['PHP_SELF']);
    $path = rtrim(str_replace('\\', '/', $scriptDir), '/');
    $webhook_url = "https://" . $domainName . $path . '/notification.php';
    
    // URL Obrigado (ou funil se ativo)
    $stmt_prod_conf = $pdo->prepare("SELECT checkout_config FROM produtos WHERE id = ?");
    $stmt_prod_conf->execute([$main_product_id]);
    $p_conf = $stmt_prod_conf->fetch(PDO::FETCH_ASSOC);
    $checkout_config = json_decode($p_conf['checkout_config'] ?? '{}', true);
    $redirect_url_after_approval = $checkout_config['redirectUrl'] ?? ("https://" . $domainName . $path . '/obrigado.php');
    $base_url_funnel = 'https://' . $domainName . '/';
    if (file_exists(dirname(__DIR__) . '/helpers/funnel_helper.php')) {
        require_once dirname(__DIR__) . '/helpers/funnel_helper.php';
    }
    // Etapa 5: redirect pós-pagamento quando compra veio do funil
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

            echo json_encode([
                'status' => 'pix_created',
                'pix_data' => [
                    'qr_code_base64' => $res_data['qr_code_base64'],
                    'qr_code' => $res_data['qr_code'] ?? '',
                    'payment_id' => $payment_id
                ],
                'redirect_url_after_approval' => $redirect_url_computed($payment_id)
            ]);
            exit;

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
            
            // Extrai mensagem de erro se o pagamento foi recusado/rejeitado
            $error_message = null;
            $status_detail = null;
            if (in_array($status, ['rejected', 'cancelled', 'refunded', 'charged_back'])) {
                // Tenta extrair mensagem de erro do Mercado Pago
                if (isset($res_data['status_detail'])) {
                    $status_detail = $res_data['status_detail'];
                }
                if (isset($res_data['cause']) && is_array($res_data['cause'])) {
                    $error_codes = array_column($res_data['cause'], 'code');
                    $error_messages = array_column($res_data['cause'], 'description');
                    if (!empty($error_messages)) {
                        $error_message = implode('. ', $error_messages);
                    }
                }
                if (!$error_message && isset($res_data['message'])) {
                    $error_message = $res_data['message'];
                }
            }

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

            if ($status == 'pending' && $data['payment_method_id'] == 'pix') {
                echo json_encode([
                    'status' => 'pix_created',
                    'pix_data' => [
                        'qr_code_base64' => $res_data['point_of_interaction']['transaction_data']['qr_code_base64'],
                        'qr_code' => $res_data['point_of_interaction']['transaction_data']['qr_code'],
                        'payment_id' => $payment_id
                    ],
                    'redirect_url_after_approval' => $redirect_url_computed($payment_id)
                ]);
                exit;
            }

            $response_front = ['status' => $status, 'message' => 'Processado.', 'payment_id' => $payment_id];
            
            // Se o pagamento foi recusado/rejeitado, inclui mensagem de erro
            if (in_array($status, ['rejected', 'rejected', 'refunded', 'charged_back'])) {
                $response_front['error'] = getMercadoPagoErrorMessage($status, $status_detail, $error_message);
                $response_front['status_detail'] = $status_detail;
            }
            
            // Se o pagamento foi aprovado, inclui URL de redirecionamento
            if ($status == 'approved') {
                $response_front['redirect_url'] = $redirect_url_computed($payment_id);
            }
            
            // Se o pagamento está pendente ou em processamento, inclui informação para polling
            if (in_array($status, ['pending', 'in_process'])) {
                $response_front['message'] = 'Pagamento em processamento. Aguarde a confirmação.';
            }
            
            echo json_encode($response_front);

        } else {
            // Extrai mensagem de erro da resposta do Mercado Pago
            $error_msg = "Erro ao processar pagamento";
            if (isset($res_data['message'])) {
                $error_msg = $res_data['message'];
            } elseif (isset($res_data['error'])) {
                $error_msg = is_array($res_data['error']) ? implode(', ', $res_data['error']) : $res_data['error'];
            } elseif (isset($res_data['cause']) && is_array($res_data['cause'])) {
                $error_descriptions = array_column($res_data['cause'], 'description');
                if (!empty($error_descriptions)) {
                    $error_msg = implode('. ', $error_descriptions);
                }
            }
            log_process("Mercado Pago Error ($http_code): " . $error_msg);
            throw new Exception($error_msg);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    log_process("Erro Exception: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}

function save_sales($pdo, $data, $main_id, $payment_id, $status, $metodo, $uuid, $utm_params) {
    // Extrai UTMs
    $utm_source = $utm_params['utm_source'] ?? null;
    $utm_campaign = $utm_params['utm_campaign'] ?? null;
    $utm_medium = $utm_params['utm_medium'] ?? null;
    $utm_content = $utm_params['utm_content'] ?? null;
    $utm_term = $utm_params['utm_term'] ?? null;
    $src = $utm_params['src'] ?? null;
    $sck = $utm_params['sck'] ?? null;

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

        $stmt_insert = $pdo->prepare("INSERT INTO vendas (produto_id, community_id, comprador_nome, comprador_email, comprador_cpf, comprador_telefone, valor, status_pagamento, transacao_id, metodo_pagamento, checkout_session_uuid, email_entrega_enviado, utm_source, utm_campaign, utm_medium, utm_content, utm_term, src, sck) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)");

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
                // Produto principal usa o valor calculado (com desconto Pix se aplicável)
                // Order bumps usam o preço original
                $val = ($pid == $main_id) ? $main_product_value : $prod_map[$pid]['preco'];
                $cid = (int)($prod_map[$pid]['community_id'] ?? 1);
                $stmt_insert->execute([
                    $pid, $cid, $data['name'], $data['email'], 
                    preg_replace('/[^0-9]/', '', $data['cpf']), 
                    preg_replace('/[^0-9]/', '', $data['phone']), 
                    $val, $status, $payment_id, $metodo, $uuid,
                    $utm_source, $utm_campaign, $utm_medium, $utm_content, $utm_term, $src, $sck
                ]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao salvar vendas: " . $e->getMessage());
    }
}
?>
