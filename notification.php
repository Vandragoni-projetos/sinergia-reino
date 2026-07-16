<?php
// Registra handler de erro fatal ANTES de qualquer coisa
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Erro fatal no servidor: ' . $error['message'] . ' em ' . $error['file'] . ':' . $error['line']
        ]);
        exit;
    }
});

// Inicia o buffer de saída IMEDIATAMENTE.
ob_start();

// Desabilita exibição de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Verifica se o arquivo config existe
$config_paths = [
    __DIR__ . '/config/config.php',
    __DIR__ . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        require_once $config_path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Config não encontrado']));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Importa Helper da UTMfy
if (file_exists(__DIR__ . '/helpers/utmfy_helper.php')) require_once __DIR__ . '/helpers/utmfy_helper.php';
if (file_exists(__DIR__ . '/helpers/acesso_helper.php')) require_once __DIR__ . '/helpers/acesso_helper.php';

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

ini_set('display_errors', 0); 
ini_set('log_errors', 1); 
ini_set('error_log', __DIR__ . '/notification_api_errors.log'); 

function log_webhook($message) {
    file_put_contents(__DIR__ . '/webhook_log.txt', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

$phpmailer_path = __DIR__ . '/PHPMailer/src/';
if (file_exists($phpmailer_path . 'Exception.php')) { require_once $phpmailer_path . 'Exception.php'; require_once $phpmailer_path . 'PHPMailer.php'; require_once $phpmailer_path . 'SMTP.php'; }

// --- FUNÇÕES AUXILIARES ---

function sendFacebookConversionEvent($pixel_id, $api_token, $event_name, $sale_details, $event_source_url) {
    if (empty($pixel_id) || empty($api_token)) return;
    $url = "https://graph.facebook.com/v19.0/" . $pixel_id . "/events?access_token=" . $api_token;
    
    $user_data = [
        'em' => [hash('sha256', strtolower($sale_details['comprador_email']))],
        'ph' => [hash('sha256', preg_replace('/[^0-9]/', '', $sale_details['comprador_telefone']))],
    ];
    $name_parts = explode(' ', $sale_details['comprador_nome'], 2);
    $user_data['fn'] = [hash('sha256', strtolower($name_parts[0]))];
    if (isset($name_parts[1])) $user_data['ln'] = [hash('sha256', strtolower($name_parts[1]))];

    $payload = [
        'data' => [[
            'event_name' => $event_name,
            'event_time' => time(),
            'event_source_url' => $event_source_url,
            'user_data' => $user_data,
            'custom_data' => ['currency' => 'BRL', 'value' => (float)$sale_details['valor']],
            'action_source' => 'website',
        ]]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
}

function handle_tracking_events($status, $sale_details, $checkout_config) {
    $tracking_config = $checkout_config['tracking'] ?? [];
    if (empty($tracking_config)) return;
    
    $status = strtolower($status);

    $event_map = [
        'approved' => ['key' => 'purchase', 'fb_name' => 'Purchase'],
        'paid' => ['key' => 'purchase', 'fb_name' => 'Purchase'],
        'pix_created' => ['key' => 'pending', 'fb_name' => 'PaymentPending'], 
        'pending' => ['key' => 'pending', 'fb_name' => 'PaymentPending'],
        'rejected' => ['key' => 'rejected', 'fb_name' => 'PaymentRejected'],
        'refunded' => ['key' => 'refund', 'fb_name' => 'Refund'],
        'charged_back' => ['key' => 'chargeback', 'fb_name' => 'Chargeback']
    ];
    
    if (!isset($event_map[$status])) return;
    $event_info = $event_map[$status];
    
    if (!empty($tracking_config['events']['facebook'][$event_info['key']])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $checkout_url = $protocol . $_SERVER['HTTP_HOST'] . '/checkout?p=' . $sale_details['checkout_hash'];
        sendFacebookConversionEvent($tracking_config['facebookPixelId'] ?? '', $tracking_config['facebookApiToken'] ?? '', $event_info['fb_name'], $sale_details, $checkout_url);
    }
}

function trigger_webhooks($usuario_id, $event_data, $trigger_event, $produto_id = null) {
    global $pdo;
    $trigger_event = strtolower($trigger_event);
    $event_field = 'event_' . $trigger_event;
    
    if (in_array($trigger_event, ['approved', 'paid'])) $event_field = 'event_approved';
    if ($trigger_event == 'pix_created') $event_field = 'event_pending';

    log_webhook("WEBHOOKS: Verificando webhooks para evento '$trigger_event' (campo: $event_field, usuario_id: $usuario_id, produto_id: " . ($produto_id ?? 'NULL') . ")");

    $stmt = $pdo->prepare("SELECT url FROM webhooks WHERE usuario_id = :uid AND {$event_field} = 1 AND (produto_id IS NULL OR produto_id = :pid)");
    $stmt->execute([':uid' => $usuario_id, ':pid' => $produto_id]);
    $webhooks = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($webhooks)) {
        log_webhook("WEBHOOKS: Nenhum webhook encontrado para o evento '$trigger_event'.");
        return;
    }
    
    $json_payload = json_encode(['event' => $trigger_event, 'timestamp' => date('Y-m-d H:i:s'), 'data' => $event_data]);

    foreach ($webhooks as $url) {
        log_webhook("WEBHOOKS: Enviando payload para URL: $url");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-GatewayPro-Event: ' . $trigger_event]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            log_webhook("WEBHOOKS: ERRO cURL para $url: $curl_error");
        } else if ($http_code >= 200 && $http_code < 300) {
            log_webhook("WEBHOOKS: Sucesso! HTTP $http_code para $url");
        } else {
            log_webhook("WEBHOOKS: FALHA! HTTP $http_code para $url");
        }
    }
}

function process_single_product_delivery($product_data, $customer_email) {
    global $pdo;
    $type = $product_data['tipo_entrega'];
    $content = $product_data['conteudo_entrega'];
    $oferta_id = $product_data['oferta_id'] ?? null; // Pega oferta_id da venda
    
    if ($type === 'link' && !empty($content)) return ['success' => true, 'product_name' => $product_data['produto_nome'], 'content_type' => 'link', 'content_value' => $content];
    if ($type === 'email_pdf' && !empty($content) && file_exists('uploads/' . $content)) return ['success' => true, 'product_name' => $product_data['produto_nome'], 'content_type' => 'pdf', 'content_value' => 'uploads/' . $content];
    if ($type === 'area_membros') {
        // Usa a função helper que respeita o tipo_acesso da oferta (mensal, semestral, anual, vitalicio)
        if (function_exists('conceder_acesso_aluno')) {
            $acesso_concedido = conceder_acesso_aluno($pdo, $customer_email, $product_data['produto_id'], $oferta_id);
            log_webhook("Acesso concedido via helper: email={$customer_email}, produto={$product_data['produto_id']}, oferta=" . ($oferta_id ?? 'padrão'));
        } else {
            // Fallback se o helper não estiver disponível
            $pdo->prepare("INSERT IGNORE INTO alunos_acessos (aluno_email, produto_id, oferta_id) VALUES (?, ?, ?)")->execute([$customer_email, $product_data['produto_id'], $oferta_id]);
            log_webhook("Acesso concedido via INSERT direto: email={$customer_email}, produto={$product_data['produto_id']}, oferta=" . ($oferta_id ?? 'NULL'));
        }
        return ['success' => true, 'product_name' => $product_data['produto_nome'], 'content_type' => 'area_membros', 'content_value' => null];
    }
    return ['success' => false, 'message' => 'Tipo desconhecido ou vazio'];
}

// --- FUNÇÃO DE ENVIO DE E-MAIL (ATUALIZADA PARA USAR O TEMPLATE DO BANCO) ---
function send_delivery_email_consolidated($to_email, $customer_name, $products, $pass, $login_url) {
    global $pdo;
    log_webhook("send_delivery_email_consolidated chamada para: " . $to_email);
    
    $mail = new PHPMailer(true);
    try {
        // Busca configurações SMTP e TEMPLATE do banco
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'email_template_delivery_subject', 'email_template_delivery_html')");
        $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        log_webhook("Configuracoes SMTP carregadas. Host: " . ($config['smtp_host'] ?? 'NÃO CONFIGURADO'));
        
        // Busca logo configurada da tabela configuracoes_sistema
        $logo_url_raw = '';
        if (function_exists('getSystemSetting')) {
            $logo_url_raw = getSystemSetting('logo_url', '');
        } else {
            $stmt_logo = $pdo->query("SELECT valor FROM configuracoes_sistema WHERE chave = 'logo_url' LIMIT 1");
            $logo_result = $stmt_logo->fetch(PDO::FETCH_ASSOC);
            $logo_url_raw = $logo_result ? $logo_result['valor'] : '';
        }
        
        // Constrói URL absoluta da logo
        $logo_url_final = '';
        if (!empty($logo_url_raw)) {
            if (strpos($logo_url_raw, 'http') === 0) {
                // Já é uma URL absoluta
                $logo_url_final = $logo_url_raw;
            } else {
                // É um caminho relativo, constrói URL absoluta
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $logo_path = ltrim($logo_url_raw, '/');
                $logo_url_final = $protocol . '://' . $host . '/' . $logo_path;
            }
        }
        log_webhook("Logo URL final: " . ($logo_url_final ?: 'NÃO CONFIGURADA'));
        
        // Configuração de Remetente
        $default_from = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $fromEmail = !empty($config['smtp_from_email']) ? $config['smtp_from_email'] : ($config['smtp_username'] ?? $default_from);
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) $fromEmail = $default_from;

        if (empty($config['smtp_host'])) {
            $mail->isMail();
        } else {
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->Port = $config['smtp_port'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_username'];
            $mail->Password = $config['smtp_password'];
            $mail->SMTPSecure = $config['smtp_encryption'] == 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->setFrom($fromEmail, $config['smtp_from_name'] ?? 'Hub SinergIA');
        $mail->addAddress($to_email, $customer_name);
        
        // Usa o assunto do banco ou um padrão
        $mail->Subject = $config['email_template_delivery_subject'] ?? 'Seu acesso chegou!';
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        // Usa o template do banco ou um padrão
        $template = $config['email_template_delivery_html'] ?? '<p>Olá {CLIENT_NAME}, aqui estão seus produtos:</p><!-- LOOP_PRODUCTS_START --><p>{PRODUCT_NAME}</p><!-- LOOP_PRODUCTS_END -->';

        // Substitui variáveis, incluindo {CLIENT_EMAIL} e {LOGO_URL}
        $body = str_replace(
            ['{CLIENT_NAME}', '{CLIENT_EMAIL}', '{MEMBER_AREA_PASSWORD}', '{MEMBER_AREA_LOGIN_URL}', '{LOGO_URL}'], 
            [$customer_name, $to_email, $pass ?? 'N/A', $login_url ?? '#', $logo_url_final], 
            $template
        );
        
        // Também substitui URLs de imagens quebradas ou genéricas pela logo configurada
        if (!empty($logo_url_final)) {
            // Substitui URLs comuns de imagens quebradas ou placeholders
            $body = preg_replace('/src=["\']https?:\/\/[^"\']*imgbb\.com[^"\']*["\']/i', 'src="' . $logo_url_final . '"', $body);
            $body = preg_replace('/src=["\']https?:\/\/[^"\']*ibb\.co[^"\']*["\']/i', 'src="' . $logo_url_final . '"', $body);
            // Substitui qualquer src vazio ou quebrado no início do template
            $body = preg_replace('/<img[^>]*src=["\'](?!https?:\/\/)[^"\']*["\'][^>]*>/i', '<img src="' . $logo_url_final . '" alt="Logo" style="max-width: 200px; height: auto;">', $body, 1);
        }
        
        // Processa o Loop de Produtos
        $loop_start = '<!-- LOOP_PRODUCTS_START -->'; 
        $loop_end = '<!-- LOOP_PRODUCTS_END -->';
        if (strpos($body, $loop_start) !== false) {
             $part = substr($body, strpos($body, $loop_start) + strlen($loop_start));
             $part = substr($part, 0, strpos($part, $loop_end));
             $html_prods = '';
             foreach ($products as $p) {
                 $item = str_replace('{PRODUCT_NAME}', $p['product_name'], $part);
                 $item = str_replace('{PRODUCT_LINK}', ($p['content_type']=='link' ? $p['content_value'] : ''), $item);
                 
                 // Limpa tags condicionais (ex: <!-- IF_PRODUCT_TYPE_LINK -->)
                 $types = ['link', 'pdf', 'area_membros'];
                 foreach($types as $t) {
                     $tag = 'PRODUCT_TYPE_'.strtoupper($t == 'area_membros' ? 'MEMBER_AREA' : $t);
                     if ($p['content_type'] == $t) $item = str_replace(["<!-- IF_$tag -->", "<!-- END_IF_$tag -->"], '', $item);
                     else $item = preg_replace("/<!-- IF_$tag -->.*?<!-- END_IF_$tag -->/s", "", $item);
                 }
                 $html_prods .= $item;
             }
             $body = str_replace($loop_start . $part . $loop_end, $html_prods, $body);
        }

        $mail->Body = $body;
        
        // Anexos
        foreach ($products as $p) {
            if ($p['content_type'] == 'pdf' && file_exists($p['content_value'])) {
                $mail->addAttachment($p['content_value'], basename($p['content_value']));
            }
        }

        log_webhook("Tentando enviar email...");
        $mail->send();
        log_webhook("Email enviado com sucesso!");
        return true;
    } catch (Exception $e) {
        log_webhook("ERRO ao enviar email: " . $e->getMessage());
        log_webhook("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function create_notification($usuario_id, $tipo, $mensagem, $valor, $venda_id_fk = null, $metodo = null) {
    global $pdo;
    if (!$usuario_id) {
        log_webhook("create_notification: usuario_id vazio — notificação ignorada.");
        return;
    }
    try {
        // Evita duplicatas da mesma venda/tipo
        if ($venda_id_fk) {
            $stmt_dup = $pdo->prepare("SELECT id FROM notificacoes WHERE usuario_id = ? AND venda_id_fk = ? AND tipo = ? LIMIT 1");
            $stmt_dup->execute([(int)$usuario_id, (int)$venda_id_fk, $tipo]);
            if ($stmt_dup->fetch()) {
                log_webhook("create_notification: já existe notificação tipo={$tipo} para venda_id={$venda_id_fk}");
                return;
            }
        }
        $link = $venda_id_fk ? "/index?pagina=vendas&id={$venda_id_fk}" : null;
        $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, valor, link_acao, venda_id_fk, metodo_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$usuario_id, $tipo, $mensagem, $valor, $link, $venda_id_fk, $metodo]);
        log_webhook("create_notification: OK usuario_id={$usuario_id}, tipo={$tipo}, venda_id=" . ($venda_id_fk ?? 'null'));
    } catch (Exception $e) {
        log_webhook("Erro notificacao: " . $e->getMessage());
    }
}

// -----------------------------------------------------------------------------
// EXECUÇÃO DA LÓGICA
// -----------------------------------------------------------------------------

try {
    // Verifica se $pdo está disponível antes de processar qualquer requisição
    if (!isset($pdo)) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        error_log("Erro: \$pdo não está definido em notification.php");
        echo json_encode(['error' => 'Erro de configuração do servidor - banco de dados não conectado']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw_input = file_get_contents('php://input');
        $is_json = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
        $data = [];
        $action = $_GET['action'] ?? '';

        if ($is_json) {
            $data = json_decode($raw_input, true) ?? [];
        } else {
            $data = $_POST;
            if (empty($data) && !empty($raw_input)) {
                parse_str($raw_input, $data);
            }
        }

        $payment_id = null;
        $gateway_detected = null;

        // --- WEBHOOK STRIPE (prioridade - requer verificação de assinatura) ---
        $stripe_sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        if (!empty($stripe_sig) && !empty($raw_input) && !empty($data)) {
            require_once __DIR__ . '/gateways/stripe.php';
            $stmt_usuarios = $pdo->query("SELECT id, stripe_webhook_secret FROM usuarios WHERE stripe_webhook_secret IS NOT NULL AND stripe_webhook_secret != ''");
            while ($row = $stmt_usuarios->fetch(PDO::FETCH_ASSOC)) {
                $event_id = verify_stripe_webhook($raw_input, $stripe_sig, $row['stripe_webhook_secret']);
                if ($event_id) {
                    $event_obj = (object)[
                        'type' => $data['type'] ?? '',
                        'data' => (object)['object' => (object)($data['data']['object'] ?? [])]
                    ];
                    $extracted = stripe_extract_payment_id($event_obj);
                    if ($extracted && in_array($data['type'] ?? '', ['checkout.session.completed', 'payment_intent.succeeded'])) {
                        log_webhook("Stripe webhook verificado. Event: " . ($data['type'] ?? '') . ", payment_id: " . $extracted);
                        $payment_id = $extracted;
                        $gateway_detected = 'stripe';
                        break;
                    }
                }
            }
        }

        // --- WEBHOOK PAYPAL (event_type no payload, com verificação de assinatura) ---
        if (!$payment_id && !empty($data['event_type']) && strpos($data['event_type'], 'PAYMENT') !== false) {
            require_once __DIR__ . '/gateways/paypal.php';
            $resource = $data['resource'] ?? [];
            $order_id = $resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? $data['id'] ?? null;
            if ($order_id && in_array($data['event_type'], ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'])) {
                $stmt_paypal = $pdo->prepare("SELECT v.id, p.usuario_id FROM vendas v JOIN produtos p ON v.produto_id = p.id WHERE v.transacao_id = ? AND v.metodo_pagamento LIKE '%PayPal%' LIMIT 1");
                $stmt_paypal->execute([$order_id]);
                $paypal_sale = $stmt_paypal->fetch(PDO::FETCH_ASSOC);
                if ($paypal_sale) {
                    $stmt_creds = $pdo->prepare("SELECT paypal_client_id, paypal_client_secret, paypal_webhook_secret FROM usuarios WHERE id = ?");
                    $stmt_creds->execute([$paypal_sale['usuario_id'] ?? 0]);
                    $paypal_creds = $stmt_creds->fetch(PDO::FETCH_ASSOC);
                    $webhook_id = $paypal_creds['paypal_webhook_secret'] ?? '';
                    $verified = false;
                    if (!empty($webhook_id) && !empty($paypal_creds['paypal_client_id']) && !empty($paypal_creds['paypal_client_secret'])) {
                        $headers = function_exists('getallheaders') ? getallheaders() : [];
                        $sandbox = (strpos($paypal_creds['paypal_client_id'], 'sb') !== false);
                        $verified = verify_paypal_webhook($headers, $raw_input, $webhook_id, $paypal_creds['paypal_client_id'], $paypal_creds['paypal_client_secret'], $sandbox);
                    }
                    if ($verified || empty($webhook_id)) {
                        $payment_id = $order_id;
                        $gateway_detected = 'paypal';
                        log_webhook("PayPal webhook " . ($verified ? "verificado" : "sem verificação") . ". Event: " . ($data['event_type'] ?? '') . ", order_id: " . $order_id);
                    } else {
                        log_webhook("PayPal webhook REJEITADO: assinatura inválida");
                    }
                }
            }
        }

        // --- API Actions do Frontend ---
        if ($action === 'mark_all_as_read' || $action === 'mark_as_displayed_live') {
             if (!isset($_SESSION['id'])) { echo json_encode(['error' => 'Auth required']); exit; }
             if ($action === 'mark_all_as_read') {
                 $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE usuario_id = ?")->execute([$_SESSION['id']]);
             } else {
                 $notif_id = $data['notification_id'] ?? ($_POST['notification_id'] ?? null);
                 if ($notif_id) $pdo->prepare("UPDATE notificacoes SET displayed_live = 1 WHERE id = ? AND usuario_id = ?")->execute([$notif_id, $_SESSION['id']]);
             }
             echo json_encode(['success' => true]);
             exit;
        }

        // --- WEBHOOK (Gateway) ---
        header('Content-Type: application/json');
        ob_clean();
        echo json_encode(['status' => 'success']);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

        // Log do webhook recebido para debug
        log_webhook("Webhook recebido: " . json_encode($data));
        
        // Tenta extrair payment_id de diferentes formatos (Stripe já definido acima; Mercado Pago, PushinPay, Efí, Beehive, Hypercash)
        if (!$payment_id) {
        
        // Formato Efí Cartão - charge_id (prioridade para detectar gateway de cartão)
        if (isset($data['data']['charge_id'])) {
            $payment_id = $data['data']['charge_id'];
            $gateway_detected = 'efi_card';
            log_webhook("Payment ID extraído do formato Efí Cartão (data.charge_id): " . $payment_id);
        } elseif (isset($data['charge_id'])) {
            $payment_id = $data['charge_id'];
            $gateway_detected = 'efi_card';
            log_webhook("Payment ID extraído do formato Efí Cartão (charge_id): " . $payment_id);
        }
        
        // Formato Efí Pix - txid (prioridade para detectar gateway)
        if (!$payment_id && isset($data['txid'])) {
            $payment_id = $data['txid'];
            $gateway_detected = 'efi';
            log_webhook("Payment ID extraído do formato Efí (txid): " . $payment_id);
        } elseif (!$payment_id && isset($data['pix'][0]['txid'])) {
            $payment_id = $data['pix'][0]['txid'];
            $gateway_detected = 'efi';
            log_webhook("Payment ID extraído do formato Efí (pix[0].txid): " . $payment_id);
        }
        
        // Formato Mercado Pago - webhook padrão
        if (!$payment_id) {
            if (isset($data['type']) && $data['type'] === 'payment' && isset($data['data']['id'])) {
                $payment_id = $data['data']['id'];
                $gateway_detected = 'mercadopago';
                log_webhook("Payment ID extraído do formato Mercado Pago (type=payment): " . $payment_id);
            } elseif (isset($data['data']['id'])) {
                $payment_id = $data['data']['id'];
                log_webhook("Payment ID extraído de data.id: " . $payment_id);
            } elseif (isset($data['id'])) {
                $payment_id = $data['id'];
                log_webhook("Payment ID extraído de id: " . $payment_id);
            }
        }
        
        // Formato PushinPay (pode vir em diferentes campos)
        if (!$payment_id) {
            if (isset($data['transaction_id'])) {
                $payment_id = $data['transaction_id'];
                $gateway_detected = 'pushinpay';
                log_webhook("Payment ID extraído de transaction_id: " . $payment_id);
            } elseif (isset($data['transaction']['id'])) {
                $payment_id = $data['transaction']['id'];
                $gateway_detected = 'pushinpay';
                log_webhook("Payment ID extraído de transaction.id: " . $payment_id);
            } elseif (isset($data['payment_id'])) {
                $payment_id = $data['payment_id'];
                log_webhook("Payment ID extraído de payment_id: " . $payment_id);
            }
        }
        
        // Formato Beehive/Hypercash (webhook genérico)
        if (!$payment_id && isset($data['external_reference'])) {
            $payment_id = $data['external_reference'];
            log_webhook("Payment ID extraído de external_reference: " . $payment_id);
        }
        
        // Tenta extrair de resource (Mercado Pago - formato alternativo)
        $resource = $data['resource'] ?? null;
        if (!$payment_id && $resource) {
            if (preg_match('/\/payments\/(\d+)/', $resource, $matches)) {
                $payment_id = $matches[1];
                $gateway_detected = 'mercadopago';
                log_webhook("Payment ID extraído de resource (URL): " . $payment_id);
            } else {
                $payment_id = preg_replace('/[^0-9]/', '', $resource);
                if (!empty($payment_id)) {
                    log_webhook("Payment ID extraído de resource (limpo): " . $payment_id);
                }
            }
        }
        } // fim if (!$payment_id) - extração para gateways não-Stripe
        
        log_webhook("Payment ID final extraído: " . ($payment_id ?: 'NÃO ENCONTRADO') . " | Gateway detectado: " . ($gateway_detected ?: 'NÃO DETECTADO'));

        if ($payment_id) {
            log_webhook("Buscando venda com transacao_id: " . $payment_id);
            $stmt = $pdo->prepare("SELECT v.*, p.usuario_id, u.mp_access_token, u.pushinpay_token, u.efi_client_id, u.efi_client_secret, u.efi_certificate_path, u.beehive_secret_key, u.hypercash_secret_key FROM vendas v JOIN produtos p ON v.produto_id = p.id LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE v.transacao_id = ? LIMIT 1");
            $stmt->execute([$payment_id]);
            $venda = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$venda) {
                log_webhook("ERRO: Venda não encontrada para transacao_id: " . $payment_id);
            } else {
                log_webhook("Venda encontrada. ID: " . $venda['id'] . ", Status atual: " . $venda['status_pagamento'] . ", Email enviado: " . ($venda['email_entrega_enviado'] ?? 0));
            }

            if ($venda) {
                log_webhook("Venda encontrada no BD. Transacao ID: " . $payment_id . ", Status atual: " . $venda['status_pagamento']);
                
                // Tenta extrair status do webhook (diferentes formatos)
                $new_status = null;
                
                // Formato Stripe - checkout.session.completed e payment_intent.succeeded = pagamento aprovado
                if ($gateway_detected === 'stripe') {
                    $new_status = 'approved';
                    log_webhook("Stripe: Status definido como approved");
                }
                
                // Formato PayPal - PAYMENT.CAPTURE.COMPLETED = pagamento aprovado
                if ($gateway_detected === 'paypal') {
                    $new_status = 'approved';
                    log_webhook("PayPal: Status definido como approved");
                }
                
                // Formato Mercado Pago
                // O webhook do Mercado Pago NÃO envia o status, apenas o ID do pagamento
                // Precisamos buscar da API
                if (isset($data['type']) && $data['type'] === 'payment') {
                    // Webhook do Mercado Pago - não tem status, precisa buscar da API
                    log_webhook("Webhook do Mercado Pago detectado (type=payment). Status será buscado da API.");
                    $new_status = null; // Será buscado da API abaixo
                } elseif (isset($data['action']) && $data['action'] === 'payment.updated') {
                    // Formato alternativo do Mercado Pago
                    $new_status = $data['data']['status'] ?? null;
                } elseif (isset($data['status'])) {
                    $new_status = $data['status'];
                }
                
                // Formato PushinPay
                if (!$new_status) {
                    if (isset($data['transaction']['status'])) {
                        $new_status = $data['transaction']['status'];
                    } elseif (isset($data['status'])) {
                        $new_status = $data['status'];
                    } elseif (isset($data['event']) && $data['event'] === 'payment.paid') {
                        $new_status = 'paid';
                    }
                }
                
                // Formato Efí Pix - webhook indica pagamento recebido
                if (!$new_status && ($gateway_detected === 'efi' || isset($data['txid']) || isset($data['pix']))) {
                    if (isset($data['txid']) || isset($data['pix'])) {
                        $new_status = 'paid';
                        log_webhook("Efí Pix: Webhook recebido com txid, marcando como paid");
                        if (!$gateway_detected) {
                            $gateway_detected = 'efi';
                        }
                    }
                }
                
                // Formato Efí Cartão - webhook indica status da cobrança
                if (!$new_status && $gateway_detected === 'efi_card') {
                    if (isset($data['data']['status'])) {
                        $new_status = $data['data']['status'];
                    } elseif (isset($data['status'])) {
                        $new_status = $data['status'];
                    }
                    log_webhook("Efí Cartão: Status extraído do webhook: " . ($new_status ?: 'NÃO ENCONTRADO'));
                }
                
                // Formato Beehive/Hypercash
                if (!$new_status && isset($data['payment_status'])) {
                    $new_status = $data['payment_status'];
                    log_webhook("Beehive/Hypercash: Status extraído de payment_status: " . $new_status);
                }
                
                log_webhook("Status extraído do webhook: " . ($new_status ?: 'NÃO ENCONTRADO'));
                
                // Se não veio status no webhook, tenta buscar da API
                // IMPORTANTE: O webhook do Mercado Pago NÃO envia o status, sempre precisa buscar da API
                if (!$new_status || (isset($data['type']) && $data['type'] === 'payment')) {
                    // Verifica se é PushinPay (se tem pushinpay_token e método é Pix)
                    if (!empty($venda['pushinpay_token']) && stripos($venda['metodo_pagamento'], 'pix') !== false) {
                        log_webhook("Buscando status do PushinPay via API...");
                        // Tenta diferentes endpoints
                        $endpoints = [
                            'https://api.pushinpay.com.br/api/transactions/' . $payment_id,
                            'https://api.pushinpay.com.br/api/pix/transactions/' . $payment_id,
                            'https://api.pushinpay.com.br/api/pix/' . $payment_id
                        ];
                        
                        foreach ($endpoints as $endpoint) {
                            $ch = curl_init($endpoint);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Authorization: Bearer ' . $venda['pushinpay_token'],
                                'Accept: application/json'
                            ]);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                            $pp_res = json_decode(curl_exec($ch), true);
                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            if ($http_code >= 200 && $http_code < 300 && isset($pp_res['status'])) {
                                $new_status = $pp_res['status'];
                                log_webhook("Status obtido da API PushinPay: " . $new_status);
                                break;
                            }
                        }
                    } elseif ($venda['mp_access_token']) {
                        log_webhook("Buscando status do Mercado Pago via API (webhook não envia status)...");
                        // Busca status do Mercado Pago - SEMPRE necessário pois webhook não envia status
                        $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $payment_id);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $venda['mp_access_token']]);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                        $mp_res = json_decode(curl_exec($ch), true);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($http_code == 200 && isset($mp_res['status'])) {
                            $new_status = $mp_res['status'];
                            log_webhook("Status obtido da API Mercado Pago: " . $new_status);
                        } else {
                            log_webhook("ERRO ao buscar status do Mercado Pago. HTTP Code: " . $http_code . ", Resposta: " . substr(json_encode($mp_res), 0, 200));
                        }
                    }
                    // Verifica se é Efí (se tem credenciais Efí)
                    elseif (!empty($venda['efi_client_id']) && !empty($venda['efi_certificate_path']) && $gateway_detected === 'efi') {
                        log_webhook("Buscando status do Efí Pix via API...");
                        if (file_exists(__DIR__ . '/gateways/efi.php')) {
                            require_once __DIR__ . '/gateways/efi.php';
                            // A API Efí pode ser consultada aqui se necessário
                            // Por enquanto, se o webhook chegou com txid, já marcamos como paid acima
                        }
                    }
                }
                
                // Se não veio status no webhook mas a venda já está aprovada, força processamento de entrega
                $should_process_delivery = false;
                if (!$new_status && $venda['status_pagamento'] === 'approved' && ($venda['email_entrega_enviado'] ?? 0) == 0) {
                    log_webhook("Status não veio no webhook, mas venda já está aprovada e email não foi enviado. Forçando processamento de entrega.");
                    $db_status = 'approved';
                    $new_status = 'paid'; // Força processamento
                    $should_process_delivery = true;
                }
                
                if ($new_status || $should_process_delivery) {
                    if ($new_status) {
                        $new_status = strtolower($new_status); 
                        $db_status = ($new_status === 'paid' || $new_status === 'completed' || $new_status === 'approved') ? 'approved' : $new_status;
                    } else {
                        $db_status = 'approved';
                    }
                    
                    log_webhook("Status normalizado para BD: " . $db_status);

                    // Atualiza status apenas se mudou
                    if ($venda['status_pagamento'] !== $db_status) {
                        $pdo->prepare("UPDATE vendas SET status_pagamento = ? WHERE transacao_id = ?")->execute([$db_status, $payment_id]);
                        log_webhook("Status atualizado no BD de '" . $venda['status_pagamento'] . "' para '" . $db_status . "'");
                    } else {
                        log_webhook("Status já está como '" . $db_status . "' no BD, não precisa atualizar");
                    }
                    
                    $stmt_all = $pdo->prepare("
                        SELECT v.*, p.usuario_id, p.nome as produto_nome, p.tipo_entrega, p.conteudo_entrega, p.checkout_config, p.checkout_hash 
                        FROM vendas v 
                        JOIN produtos p ON v.produto_id = p.id 
                        WHERE v.transacao_id = ?
                    ");
                    $stmt_all->execute([$payment_id]);
                    $all_sales = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($all_sales)) {
                        $main_sale = $all_sales[0];
                        $config = json_decode($main_sale['checkout_config'] ?? '{}', true);
                        
                        handle_tracking_events($db_status, $main_sale, $config);

                        $webhook_payload = [
                            'transacao_id' => $payment_id,
                            'status_pagamento' => $db_status,
                            'valor_total_compra' => array_sum(array_column($all_sales, 'valor')),
                            'comprador' => ['email' => $main_sale['comprador_email'], 'nome' => $main_sale['comprador_nome']],
                            'metodo_pagamento' => $main_sale['metodo_pagamento'],
                            'produtos_comprados' => $all_sales,
                            'utm_parameters' => [
                                'utm_source' => $main_sale['utm_source'], 'utm_campaign' => $main_sale['utm_campaign'],
                                'utm_medium' => $main_sale['utm_medium'], 'src' => $main_sale['src'], 'sck' => $main_sale['sck']
                            ]
                        ];
                        
                        // Dispara webhooks globais (sem produto_id)
                        trigger_webhooks($main_sale['usuario_id'], $webhook_payload, $db_status);
                        
                        // Dispara webhooks específicos do produto principal
                        trigger_webhooks($main_sale['usuario_id'], $webhook_payload, $db_status, $main_sale['produto_id']);
                        
                        // Dispara webhooks para cada order bump (produtos adicionais)
                        foreach ($all_sales as $sale_item) {
                            if ($sale_item['produto_id'] != $main_sale['produto_id']) {
                                trigger_webhooks($main_sale['usuario_id'], $webhook_payload, $db_status, $sale_item['produto_id']);
                            }
                        }

                        if (function_exists('trigger_utmfy_integrations')) {
                            $webhook_payload['data_venda'] = $main_sale['data_venda'];
                            $webhook_payload['comprador']['cpf'] = $main_sale['comprador_cpf'];
                            $webhook_payload['comprador']['telefone'] = $main_sale['comprador_telefone'];
                            trigger_utmfy_integrations($main_sale['usuario_id'], $webhook_payload, $db_status, $main_sale['produto_id']);
                        }

                        $msg = "Venda atualizada: " . ucfirst($db_status);
                        if ($db_status == 'approved') $msg = "Venda Aprovada! R$ " . number_format($webhook_payload['valor_total_compra'], 2, ',', '.');
                        if ($db_status == 'pix_created' || ($db_status == 'pending' && stripos($main_sale['metodo_pagamento'], 'pix') !== false)) $msg = "Pix Gerado. Aguardando.";
                        
                        create_notification($main_sale['usuario_id'], ($db_status == 'approved' ? 'Compra Aprovada' : 'Atualização'), $msg, $webhook_payload['valor_total_compra'], $main_sale['id'], $main_sale['metodo_pagamento']);

                        // Revogar acesso automaticamente em reembolso ou chargeback (apenas acessos vindos de compra, não manuais)
                        if (in_array($db_status, ['refunded', 'charged_back'])) {
                            foreach ($all_sales as $sale_item) {
                                if (($sale_item['tipo_entrega'] ?? '') !== 'area_membros') continue;
                                $aluno_email = trim($sale_item['comprador_email'] ?? '');
                                $produto_id = (int)($sale_item['produto_id'] ?? 0);
                                if (empty($aluno_email) || $produto_id <= 0) continue;
                                try {
                                    $stmt_revoke = $pdo->prepare("DELETE FROM alunos_acessos WHERE LOWER(TRIM(aluno_email)) = LOWER(?) AND produto_id = ? AND (criado_manualmente = 0 OR criado_manualmente IS NULL)");
                                    $stmt_revoke->execute([$aluno_email, $produto_id]);
                                    if ($stmt_revoke->rowCount() > 0) {
                                        log_webhook("Acesso revogado automaticamente ({$db_status}): {$aluno_email}, produto {$produto_id}");
                                    }
                                } catch (Throwable $e) {
                                    log_webhook("Erro ao revogar acesso ({$db_status}): " . $e->getMessage());
                                }
                            }
                        }

                        // Dispara mensagens via Evolution API (WhatsApp)
                        if (file_exists(__DIR__ . '/helpers/evolution_helper.php')) {
                            require_once __DIR__ . '/helpers/evolution_helper.php';
                            $evolution_event = map_payment_status_to_evolution_event($db_status);
                            if ($evolution_event) {
                                process_evolution_messages($pdo, $main_sale, $evolution_event);
                            }
                        }

                        // Processa webhook de planos SaaS (se for pagamento de plano)
                        if (plugin_active('saas')) {
                            require_once __DIR__ . '/plugins/saas/includes/notifications.php';
                            $stmt_plano = $pdo->prepare("
                                SELECT sa.*, sp.periodo 
                                FROM saas_assinaturas sa
                                JOIN saas_planos sp ON sa.plano_id = sp.id
                                WHERE sa.transacao_id = ?
                            ");
                            $stmt_plano->execute([$payment_id]);
                            $assinatura_plano = $stmt_plano->fetch(PDO::FETCH_ASSOC);
                            
                            if ($assinatura_plano) {
                                if ($db_status === 'approved' || $db_status === 'paid') {
                                    // Atualiza assinatura para ativo e renova vencimento
                                    $periodo_dias = $assinatura_plano['periodo'] === 'anual' ? 365 : 30;
                                    $novo_vencimento = date('Y-m-d H:i:s', strtotime("+{$periodo_dias} days"));
                                    
                                    $stmt_update = $pdo->prepare("
                                        UPDATE saas_assinaturas 
                                        SET status = 'ativo', data_vencimento = ?, notificado_vencimento = 0, notificado_expirado = 0
                                        WHERE transacao_id = ?
                                    ");
                                    $stmt_update->execute([$novo_vencimento, $payment_id]);
                                    
                                    // Cria notificação
                                    $stmt_plano_info = $pdo->prepare("SELECT nome FROM saas_planos WHERE id = ?");
                                    $stmt_plano_info->execute([$assinatura_plano['plano_id']]);
                                    $plano_info = $stmt_plano_info->fetch(PDO::FETCH_ASSOC);
                                    
                                    create_notification(
                                        $assinatura_plano['usuario_id'], 
                                        'Plano Ativado', 
                                        "Seu plano '{$plano_info['nome']}' foi ativado com sucesso!",
                                        $assinatura_plano['plano_id'],
                                        null,
                                        null
                                    );
                                }
                            }
                        }
                        
                        if ($db_status === 'approved' && $main_sale['email_entrega_enviado'] == 0) {
                            log_webhook("Iniciando processamento de entrega para transacao: " . $payment_id);
                            log_webhook("Email entrega enviado: " . $main_sale['email_entrega_enviado']);
                            
                            // Gera senha para área de membros
                            $processed_prods = [];
                            $pass = null; 
                            foreach ($all_sales as $s) {
                                log_webhook("Processando produto: " . $s['produto_nome'] . " - Tipo: " . $s['tipo_entrega']);
                                $res = process_single_product_delivery($s, $s['comprador_email']);
                                if ($res['success']) {
                                    log_webhook("Produto processado com sucesso: " . $res['product_name'] . " - Tipo: " . $res['content_type']);
                                    if ($res['content_type'] == 'area_membros') {
                                        // Garante senha se for area de membros
                                        $pass_raw = bin2hex(random_bytes(8));
                                        $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
                                        
                                        // Verifica usuário
                                        $exists = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
                                        $exists->execute([$s['comprador_email']]);
                                        if ($u = $exists->fetch()) {
                                            // Cliente já existe - NÃO atualiza a senha, mantém a existente
                                            log_webhook("Usuario existente encontrado, senha mantida: " . $s['comprador_email']);
                                            // Envia mensagem informativa no e-mail
                                            if(!$pass) $pass = '(use a mesma senha cadastrada anteriormente)';
                                        } else {
                                            $pdo->prepare("INSERT INTO usuarios (usuario, nome, senha, tipo) VALUES (?, ?, ?, 'usuario')")->execute([$s['comprador_email'], $s['comprador_nome'], $pass_hash]);
                                            log_webhook("Usuario criado: " . $s['comprador_email']);
                                            if(!$pass) $pass = $pass_raw;
                                        }
                                    }
                                    $processed_prods[] = $res;
                                } else {
                                    log_webhook("Erro ao processar produto: " . ($res['message'] ?? 'Erro desconhecido'));
                                }
                            }
                            
                            log_webhook("Total de produtos processados: " . count($processed_prods));
                            
                            if (!empty($processed_prods)) {
                                // Busca Link de Login
                                $login_url_stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'member_area_login_url'");
                                $login_url = $login_url_stmt->fetchColumn() ?: '#';
                                
                                log_webhook("Preparando envio de email para: " . $main_sale['comprador_email']);
                                log_webhook("Login URL: " . $login_url);
                                log_webhook("Senha gerada: " . ($pass ? 'SIM' : 'NÃO'));

                                // Envia E-mail (Agora passando todas as variáveis corretas)
                                $email_sent = send_delivery_email_consolidated($main_sale['comprador_email'], $main_sale['comprador_nome'], $processed_prods, $pass, $login_url);
                                
                                if ($email_sent) {
                                    log_webhook("Email enviado com sucesso para: " . $main_sale['comprador_email']);
                                    $pdo->prepare("UPDATE vendas SET email_entrega_enviado = 1 WHERE checkout_session_uuid = ?")->execute([$main_sale['checkout_session_uuid']]);
                                    log_webhook("Flag email_entrega_enviado atualizada para 1");
                                } else {
                                    log_webhook("ERRO: Falha ao enviar email para: " . $main_sale['comprador_email']);
                                }
                            } else {
                                log_webhook("AVISO: Nenhum produto foi processado, email não será enviado");
                            }
                        } else {
                            if ($db_status !== 'approved') {
                                log_webhook("Status não é approved: " . $db_status);
                            }
                            if ($main_sale['email_entrega_enviado'] != 0) {
                                log_webhook("Email já foi enviado anteriormente (email_entrega_enviado = " . $main_sale['email_entrega_enviado'] . ")");
                            }
                        }
                    }
                }
            }
        }
        exit;
    }
    
    // ... GET Actions ...
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        ob_clean();
        header('Content-Type: application/json');
        
        // Verifica se $pdo está disponível
        if (!isset($pdo)) {
            http_response_code(500);
            error_log("Erro: \$pdo não está definido em notification.php");
            echo json_encode(['error' => 'Erro de configuração do servidor']);
            exit;
        }
        
        if (!isset($_SESSION['id'])) { 
            http_response_code(401);
            echo json_encode(['error' => 'Auth required']); 
            exit; 
        }
        
        $uid = $_SESSION['id'];
        $action = $_GET['action'] ?? '';

        if ($action === 'get_unread_count') {
            try {
                $c = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
                $c->execute([$uid]);
                ob_clean();
                echo json_encode(['success' => true, 'count' => (int)$c->fetchColumn()]);
            } catch (PDOException $e) {
                ob_clean();
                http_response_code(500);
                error_log("Erro get_unread_count (PDO): " . $e->getMessage());
                echo json_encode(['error' => 'Erro ao buscar contagem']);
            } catch (Exception $e) {
                ob_clean();
                http_response_code(500);
                error_log("Erro get_unread_count: " . $e->getMessage());
                echo json_encode(['error' => 'Erro ao buscar contagem']);
            }
            exit;
        }
        
        if ($action === 'get_recent_notifications') {
            try {
                $s = $pdo->prepare("SELECT id, tipo, mensagem, valor, DATE_FORMAT(data_notificacao, '%Y-%m-%dT%H:%i:%s') as data_notificacao, lida, link_acao FROM notificacoes WHERE usuario_id = ? ORDER BY data_notificacao DESC LIMIT 10");
                $s->execute([$uid]);
                ob_clean();
                echo json_encode(['success' => true, 'notifications' => $s->fetchAll(PDO::FETCH_ASSOC)]);
            } catch (PDOException $e) {
                ob_clean();
                http_response_code(500);
                error_log("Erro get_recent_notifications (PDO): " . $e->getMessage());
                echo json_encode(['error' => 'Erro ao buscar notificações']);
            } catch (Exception $e) {
                ob_clean();
                http_response_code(500);
                error_log("Erro get_recent_notifications: " . $e->getMessage());
                echo json_encode(['error' => 'Erro ao buscar notificações']);
            }
            exit;
        }
        
        if ($action === 'get_live_notifications') {
            try {
                $s = $pdo->prepare("SELECT n.id, n.tipo, n.mensagem, n.valor, n.metodo_pagamento, p.nome as produto_nome, p.foto as produto_foto FROM notificacoes n LEFT JOIN vendas v ON n.venda_id_fk = v.id LEFT JOIN produtos p ON v.produto_id = p.id WHERE n.usuario_id = ? AND n.displayed_live = 0 ORDER BY n.data_notificacao ASC LIMIT 5");
                $s->execute([$uid]);
                ob_clean();
                echo json_encode(['success' => true, 'live_notifications' => $s->fetchAll(PDO::FETCH_ASSOC)]);
            } catch (PDOException $e) {
                ob_clean();
                http_response_code(500);
                error_log("Erro get_live_notifications (PDO): " . $e->getMessage());
                echo json_encode(['error' => 'Erro ao buscar notificações ao vivo']);
            } catch (Exception $e) {
                ob_clean();
                http_response_code(500);
                error_log("Erro get_live_notifications: " . $e->getMessage());
                echo json_encode(['error' => 'Erro ao buscar notificações ao vivo']);
            }
            exit;
        }
        
        // Se não for nenhuma ação conhecida, retorna erro
        ob_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Ação não reconhecida']);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Erro Fatal Notification: " . $e->getMessage());
    ob_clean();
    echo json_encode(['error' => 'Erro interno']);
}
?>
