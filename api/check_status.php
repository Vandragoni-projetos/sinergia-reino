<?php
// Registra handler de erro fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Erro fatal no servidor: ' . $error['message'] . ' em ' . $error['file'] . ':' . $error['line']
        ]);
        exit;
    }
});

// Inicia buffer de saída
ob_start();

// Desabilita exibição de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Define header JSON imediatamente
header('Content-Type: application/json');

// Função para retornar erro JSON
function returnJsonError($message, $code = 500) {
    ob_clean();
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// Função para retornar sucesso JSON
function returnJsonSuccess($data) {
    ob_clean();
    http_response_code(200);
    echo json_encode($data);
    exit;
}

/**
 * Responde JSON ao cliente imediatamente e continua o PHP (entrega/e-mail).
 * Evita o checkout ficar em "Aguardando pagamento..." enquanto SMTP/webhook rodam.
 */
function returnJsonSuccessThen(array $data, callable $after) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    header('Content-Type: application/json');
    header('Connection: close');
    $payload = json_encode($data);
    header('Content-Length: ' . strlen($payload));
    echo $payload;

    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } else {
        @flush();
    }

    ignore_user_abort(true);
    @set_time_limit(120);

    try {
        $after();
    } catch (Throwable $e) {
        log_check_status('Erro pós-resposta (entrega): ' . $e->getMessage());
    }
    exit;
}

function trigger_internal_notification_webhook($payment_id) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $webhook_url = ($https ? 'https' : 'http') . '://' . $host . '/notification.php';

    $webhook_payload = json_encode([
        'pix' => [['txid' => $payment_id]],
        'status' => 'paid'
    ]);

    log_check_status("Efí: Disparando webhook interno para: " . $webhook_url);

    $ch_webhook = curl_init($webhook_url);
    curl_setopt($ch_webhook, CURLOPT_POST, true);
    curl_setopt($ch_webhook, CURLOPT_POSTFIELDS, $webhook_payload);
    curl_setopt($ch_webhook, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch_webhook, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_webhook, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch_webhook, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch_webhook, CURLOPT_SSL_VERIFYPEER, false);
    $webhook_result = curl_exec($ch_webhook);
    $webhook_error = curl_error($ch_webhook);
    $webhook_http = (int)curl_getinfo($ch_webhook, CURLINFO_HTTP_CODE);
    curl_close($ch_webhook);

    if ($webhook_error) {
        log_check_status("Efí: Erro webhook interno: " . $webhook_error);
    } else {
        log_check_status("Efí: Webhook interno HTTP $webhook_http — " . substr((string)$webhook_result, 0, 200));
    }
}

// Função para log de check_status
function log_check_status($message) {
    $log_file = __DIR__ . '/../check_status_log.txt';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Tenta carregar config
$config_paths = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../config.php'
];

$config_loaded = false;
$config_error = null;
foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        try {
            ob_start();
            // Captura qualquer output do config
            $old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$config_error) {
                $config_error = $errstr;
                return true; // Suprime o erro
            });
            
            require $config_path;
            
            // Restaura o error handler
            if ($old_error_handler !== null) {
                set_error_handler($old_error_handler);
            } else {
                restore_error_handler();
            }
            
            ob_end_clean();
            
            // Verifica se $pdo foi criado
            if (isset($pdo) && $pdo instanceof PDO) {
                $config_loaded = true;
                break;
            } else {
                $config_error = 'Variável $pdo não foi definida no arquivo de configuração.';
            }
        } catch (Exception $e) {
            ob_end_clean();
            $config_error = 'Erro ao carregar configuração: ' . $e->getMessage();
        } catch (Error $e) {
            ob_end_clean();
            $config_error = 'Erro fatal ao carregar configuração: ' . $e->getMessage();
        } catch (Throwable $e) {
            ob_end_clean();
            $config_error = 'Erro ao carregar configuração: ' . $e->getMessage();
        }
    }
}

if (!$config_loaded) {
    $error_msg = $config_error ?: 'Arquivo de configuração não encontrado ou inválido.';
    returnJsonError($error_msg, 500);
}

// Verifica se $pdo foi definido
if (!isset($pdo) || !$pdo) {
    returnJsonError('Conexão com banco de dados não configurada.', 500);
}

// Limpa o buffer inicial
ob_end_clean();

// Recebe ID, ID do Vendedor e o Gateway usado
$payment_id = $_GET['id'] ?? null;
$seller_id = $_GET['seller_id'] ?? null;
$gateway = $_GET['gateway'] ?? 'mercadopago'; // Padrão para retrocompatibilidade

if (!$payment_id || !$seller_id) {
    returnJsonError('Dados insuficientes. ID do pagamento e ID do vendedor são obrigatórios.', 400);
}

try {
    // Busca tokens do vendedor
    $stmt = $pdo->prepare("SELECT mp_access_token, pushinpay_token, efi_client_id, efi_client_secret, efi_certificate_path FROM usuarios WHERE id = ?");
    $stmt->execute([$seller_id]);
    $tokens = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokens) {
        returnJsonError('Vendedor não encontrado.', 404);
    }

    if ($gateway === 'efi' || $gateway === 'efi_card') {
        // --- Lógica Efí (Pix e Cartão) ---
        require_once __DIR__ . '/../gateways/efi.php';
        
        $client_id = $tokens['efi_client_id'] ?? '';
        $client_secret = $tokens['efi_client_secret'] ?? '';
        $certificate_path = $tokens['efi_certificate_path'] ?? '';
        
        if (empty($client_id) || empty($client_secret) || empty($certificate_path)) {
            returnJsonError('Credenciais Efí não configuradas para este vendedor.', 400);
        }
        
        $full_cert_path = dirname(__DIR__) . '/' . $certificate_path;
        $full_cert_path = str_replace('\\', '/', $full_cert_path);
        if (!file_exists($full_cert_path)) {
            returnJsonError('Certificado Efí não encontrado.', 400);
        }
        
        // Determinar se é cartão ou Pix baseado no gateway ou no formato do payment_id
        // Cartão usa charge_id numérico, Pix usa txid alfanumérico
        $is_card = ($gateway === 'efi_card') || (is_numeric($payment_id) && strlen($payment_id) <= 15);
        
        log_check_status("Efí: Gateway=$gateway, payment_id=$payment_id, is_card=" . ($is_card ? 'true' : 'false'));
        
        if ($is_card) {
            // --- Efí Cartão: usar API de Cobranças ---
            log_check_status("Efí Cartão: Consultando status da cobrança - charge_id: " . $payment_id);
            
            // Obter access token da API de Cobranças (diferente da API Pix)
            $token_data = efi_get_charges_access_token($client_id, $client_secret, $full_cert_path);
            if (!$token_data) {
                log_check_status("Efí Cartão: ERRO - Falha ao obter token de acesso.");
                returnJsonError('Erro ao obter token de acesso Efí.', 500);
            }
            
            // Consultar status usando função específica de cartão
            $status_data = efi_get_card_charge_status($token_data['access_token'], $payment_id, $full_cert_path);
            
            log_check_status("Efí Cartão: Resposta da função efi_get_card_charge_status: " . json_encode($status_data));
        } else {
            // --- Efí Pix: usar API Pix ---
            log_check_status("Efí Pix: Consultando status do pagamento - txid: " . $payment_id);
            
            // Obter access token da API Pix
            $token_data = efi_get_access_token($client_id, $client_secret, $full_cert_path);
            if (!$token_data) {
                log_check_status("Efí Pix: ERRO - Falha ao obter token de acesso.");
                returnJsonError('Erro ao obter token de acesso Efí.', 500);
            }
            
            // Consultar status usando função de Pix
            $status_data = efi_get_payment_status($token_data['access_token'], $payment_id, $full_cert_path);
            
            log_check_status("Efí Pix: Resposta da função efi_get_payment_status: " . json_encode($status_data));
        }
        
        if ($status_data === false) {
            log_check_status("Efí: ERRO - Função de status retornou false.");
            returnJsonSuccess(['status' => 'pending', 'message' => 'Status não disponível']);
        }
        
        if ($status_data && isset($status_data['status'])) {
            $status = ($status_data['status'] === 'approved' || $status_data['status'] === 'paid') ? 'approved' : $status_data['status'];
            log_check_status("Efí: Status obtido: " . $status . " (raw: " . ($status_data['status_raw'] ?? 'n/a') . ")");
            
            // Se o status for aprovado, atualiza no banco, responde ao checkout e entrega depois
            if ($status === 'approved') {
                $needs_delivery = false;
                try {
                    $stmt_check = $pdo->prepare("SELECT status_pagamento, email_entrega_enviado FROM vendas WHERE transacao_id = ? LIMIT 1");
                    $stmt_check->execute([$payment_id]);
                    $venda_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

                    if ($venda_check) {
                        if ($venda_check['status_pagamento'] !== 'approved') {
                            log_check_status("Efí: Status 'approved' detectado via polling. Atualizando BD.");
                            $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE transacao_id = ?")->execute([$payment_id]);
                            $needs_delivery = true;
                        } elseif ((int)($venda_check['email_entrega_enviado'] ?? 0) === 0) {
                            log_check_status("Efí: Venda já approved, mas email_entrega_enviado=0. Reprocessando entrega.");
                            $needs_delivery = true;
                        }
                    } else {
                        log_check_status("Efí: AVISO - Nenhuma venda com transacao_id=$payment_id ao aprovar.");
                    }
                } catch (Exception $e) {
                    log_check_status("Efí: Erro ao atualizar status: " . $e->getMessage());
                }

                if ($needs_delivery) {
                    $pid = $payment_id;
                    returnJsonSuccessThen(['status' => 'approved'], function () use ($pid) {
                        trigger_internal_notification_webhook($pid);
                    });
                }

                returnJsonSuccess(['status' => 'approved']);
            }
            
            returnJsonSuccess(['status' => $status]);
        } else {
            log_check_status("Efí: Não foi possível obter status - retornando pending");
            returnJsonSuccess(['status' => 'pending', 'message' => 'Status não disponível']);
        }

    } elseif ($gateway === 'pushinpay') {
        // --- Lógica PushinPay ---
        $token = $tokens['pushinpay_token'] ?? '';
        if (!$token) {
            returnJsonError('Token PushinPay não configurado para este vendedor.', 400);
        }

        // Tenta diferentes endpoints possíveis
        $endpoints = [
            'https://api.pushinpay.com.br/api/transactions/' . $payment_id,
            'https://api.pushinpay.com.br/api/pix/transactions/' . $payment_id,
            'https://api.pushinpay.com.br/api/pix/' . $payment_id
        ];
        
        $response = null;
        $http_code = 0;
        $curl_error = null;
        $data = null;
        
        foreach ($endpoints as $endpoint) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Se não houver erro e o código for 200-299, tenta processar
            if (!$curl_error && $http_code >= 200 && $http_code < 300) {
                $data = json_decode($response, true);
                if ($data && isset($data['status'])) {
                    break; // Endpoint correto encontrado
                }
            }
        }
        
        if ($curl_error) {
            returnJsonError('Erro de conexão com PushinPay: ' . $curl_error, 500);
        }
        
        if ($data && isset($data['status'])) {
            // Normaliza o status para 'approved' se for 'paid'
            $status = ($data['status'] === 'paid' || $data['status'] === 'approved' || $data['status'] === 'completed') ? 'approved' : $data['status'];
            
            // Se o status for aprovado, atualiza no banco e dispara webhook interno para processar entrega
            if ($status === 'approved') {
                try {
                    // Verifica se o status no banco já está aprovado
                    $stmt_check = $pdo->prepare("SELECT status_pagamento FROM vendas WHERE transacao_id = ? LIMIT 1");
                    $stmt_check->execute([$payment_id]);
                    $venda_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    // Se ainda não estiver aprovado, atualiza e dispara webhook
                    if ($venda_check && $venda_check['status_pagamento'] !== 'approved') {
                        // Atualiza status no banco
                        $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE transacao_id = ?")->execute([$payment_id]);
                        
                        // Dispara webhook interno para processar entrega (chama notification.php)
                        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];
                        $script_path = dirname(dirname($_SERVER['PHP_SELF']));
                        $webhook_url = $protocol . '://' . $host . rtrim($script_path, '/') . '/notification.php';
                        
                        error_log("Check Status: Disparando webhook interno para: " . $webhook_url);
                        
                        $webhook_payload = [
                            'id' => $payment_id,
                            'status' => 'paid',
                            'event' => 'payment.paid'
                        ];
                        
                        // Chama webhook de forma assíncrona (não bloqueia)
                        $ch_webhook = curl_init($webhook_url);
                        curl_setopt($ch_webhook, CURLOPT_POST, true);
                        curl_setopt($ch_webhook, CURLOPT_POSTFIELDS, json_encode($webhook_payload));
                        curl_setopt($ch_webhook, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch_webhook, CURLOPT_RETURNTRANSFER, false);
                        curl_setopt($ch_webhook, CURLOPT_TIMEOUT, 2);
                        curl_setopt($ch_webhook, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_setopt($ch_webhook, CURLOPT_NOSIGNAL, 1);
                        $webhook_result = @curl_exec($ch_webhook);
                        $webhook_error = curl_error($ch_webhook);
                        curl_close($ch_webhook);
                        
                        if ($webhook_error) {
                            error_log("Erro ao chamar webhook interno: " . $webhook_error);
                        } else {
                            error_log("Webhook interno chamado com sucesso");
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erro ao atualizar status via polling: " . $e->getMessage());
                }
            }
            
            returnJsonSuccess(['status' => $status]);
        } else {
            // Se não conseguiu obter status, retorna pending para continuar verificando
            // Isso evita que o sistema pare de verificar se houver um problema temporário
            $error_msg = 'Status não disponível';
            if (isset($data['message'])) {
                $error_msg = $data['message'];
            } elseif ($response) {
                $error_msg = 'Resposta inesperada: ' . substr($response, 0, 100);
            }
            // Retorna pending ao invés de error para continuar verificando
            returnJsonSuccess(['status' => 'pending', 'message' => $error_msg]);
        }

    } else {
        // --- Lógica Mercado Pago ---
        $token = $tokens['mp_access_token'] ?? '';
        if (!$token) {
            returnJsonError('Token Mercado Pago não configurado para este vendedor.', 400);
        }

        $ch = curl_init('https://api.mercadopago.com/v1/payments/' . $payment_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curl_error) {
            returnJsonError('Erro de conexão com Mercado Pago: ' . $curl_error, 500);
        }
        
        $data = json_decode($response, true);

        if ($http_code == 200 && isset($data['status'])) {
            // Normaliza status do Mercado Pago: 'approved' ou 'paid' vira 'approved'
            $status = strtolower($data['status']);
            $normalized_status = ($status === 'approved' || $status === 'paid' || $status === 'completed') ? 'approved' : $status;
            
            log_check_status("Status Mercado Pago: " . $data['status'] . " -> Normalizado: " . $normalized_status);
            
            // Se o status for aprovado, atualiza no banco e dispara webhook interno
            if ($normalized_status === 'approved') {
                try {
                    // Verifica se o status no banco já está aprovado
                    $stmt_check = $pdo->prepare("SELECT status_pagamento FROM vendas WHERE transacao_id = ? LIMIT 1");
                    $stmt_check->execute([$payment_id]);
                    $venda_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    // Se ainda não estiver aprovado, atualiza e dispara webhook
                    if ($venda_check && $venda_check['status_pagamento'] !== 'approved') {
                        log_check_status("Status 'approved' detectado via polling para transação Mercado Pago " . $payment_id . ". Atualizando BD e disparando webhook interno.");
                        // Atualiza status no banco
                        $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE transacao_id = ?")->execute([$payment_id]);
                        
                        // Dispara webhook interno para processar entrega
                        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'];
                        $script_path = dirname(dirname($_SERVER['PHP_SELF']));
                        $webhook_url = $protocol . '://' . $host . rtrim($script_path, '/') . '/notification.php';
                        
                        $webhook_payload = [
                            'type' => 'payment',
                            'data' => ['id' => $payment_id],
                            'action' => 'payment.updated'
                        ];
                        
                        // Chama webhook de forma assíncrona (não bloqueia)
                        $ch_webhook = curl_init($webhook_url);
                        curl_setopt($ch_webhook, CURLOPT_POST, true);
                        curl_setopt($ch_webhook, CURLOPT_POSTFIELDS, json_encode($webhook_payload));
                        curl_setopt($ch_webhook, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch_webhook, CURLOPT_RETURNTRANSFER, false);
                        curl_setopt($ch_webhook, CURLOPT_TIMEOUT, 1);
                        curl_setopt($ch_webhook, CURLOPT_CONNECTTIMEOUT, 1);
                        curl_exec($ch_webhook);
                        curl_close($ch_webhook);
                        log_check_status("Webhook interno disparado para Mercado Pago: " . $webhook_url);
                    }
                } catch (Exception $e) {
                    log_check_status("Erro ao atualizar status no BD ou disparar webhook interno (Mercado Pago): " . $e->getMessage());
                }
            }
            
            returnJsonSuccess(['status' => $normalized_status]);
        } else {
            $error_msg = isset($data['message']) ? $data['message'] : 'Erro ao consultar status no Mercado Pago';
            returnJsonError($error_msg, $http_code ?: 500);
        }
    }

} catch (PDOException $e) {
    returnJsonError('Erro de banco de dados: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    returnJsonError('Erro ao verificar status: ' . $e->getMessage(), 500);
} catch (Error $e) {
    returnJsonError('Erro fatal: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    returnJsonError('Erro desconhecido: ' . $e->getMessage(), 500);
}
?>
