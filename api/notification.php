<?php
// Inicia o buffer de saída IMEDIATAMENTE.
ob_start();

// Verifica se o arquivo config existe
if (!file_exists(__DIR__ . '/../config/config.php')) {
    ob_clean();
    http_response_code(500);
    die(json_encode(['error' => 'Config não encontrado']));
}

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Importa Helper da UTMfy
if (file_exists(__DIR__ . '/../helpers/utmfy_helper.php')) require_once __DIR__ . '/../helpers/utmfy_helper.php';
// Importa helper de acesso para respeitar tipo_acesso da oferta
if (file_exists(__DIR__ . '/../helpers/acesso_helper.php')) require_once __DIR__ . '/../helpers/acesso_helper.php';

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

$phpmailer_path = __DIR__ . '/../PHPMailer/src/';
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
            conceder_acesso_aluno($pdo, $customer_email, $product_data['produto_id'], $oferta_id);
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
    $mail = new PHPMailer(true);
    try {
        // Busca configurações SMTP e TEMPLATE do banco
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'email_template_delivery_subject', 'email_template_delivery_html')");
        $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
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
        
        $mail->setFrom($fromEmail, $config['smtp_from_name'] ?? 'GatewayPro');
        $mail->addAddress($to_email, $customer_name);
        
        // Usa o assunto do banco ou um padrão
        $mail->Subject = $config['email_template_delivery_subject'] ?? 'Seu acesso chegou!';
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        // Usa o template do banco ou um padrão
        $template = $config['email_template_delivery_html'] ?? '<p>Olá {CLIENT_NAME}, aqui estão seus produtos:</p><!-- LOOP_PRODUCTS_START --><p>{PRODUCT_NAME}</p><!-- LOOP_PRODUCTS_END -->';

        // Substitui variáveis, incluindo {CLIENT_EMAIL} que estava faltando
        $body = str_replace(
            ['{CLIENT_NAME}', '{CLIENT_EMAIL}', '{MEMBER_AREA_PASSWORD}', '{MEMBER_AREA_LOGIN_URL}'], 
            [$customer_name, $to_email, $pass ?? 'N/A', $login_url ?? '#'], 
            $template
        );
        
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

        $mail->send();
        return true;
    } catch (Exception $e) {
        log_webhook("Erro Email: " . $e->getMessage());
        return false;
    }
}

function create_notification($usuario_id, $tipo, $mensagem, $valor, $venda_id_fk = null, $metodo = null) {
    global $pdo;
    if (!$usuario_id) return;
    try {
        $link = $venda_id_fk ? "/index?pagina=vendas&id={$venda_id_fk}" : null;
        $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, valor, link_acao, venda_id_fk, metodo_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$usuario_id, $tipo, $mensagem, $valor, $link, $venda_id_fk, $metodo]);
    } catch (Exception $e) {
        log_webhook("Erro notificacao: " . $e->getMessage());
    }
}

// -----------------------------------------------------------------------------
// EXECUÇÃO DA LÓGICA
// -----------------------------------------------------------------------------

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $is_json = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
        $data = [];
        $action = $_GET['action'] ?? '';

        if ($is_json) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
        } else {
            $data = $_POST;
            if (empty($data)) {
                $input = file_get_contents('php://input');
                parse_str($input, $data);
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

        $payment_id = $data['data']['id'] ?? ($data['id'] ?? null); 
        $resource = $data['resource'] ?? null;
        if (!$payment_id && $resource) $payment_id = preg_replace('/[^0-9]/', '', $resource);

        if ($payment_id) {
            $stmt = $pdo->prepare("SELECT v.*, p.usuario_id, u.mp_access_token FROM vendas v JOIN produtos p ON v.produto_id = p.id LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE v.transacao_id = ? LIMIT 1");
            $stmt->execute([$payment_id]);
            $venda = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($venda) {
                $new_status = $data['status'] ?? null; 
                if (!$new_status && $venda['mp_access_token']) {
                     $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $payment_id);
                     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                     curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $venda['mp_access_token']]);
                     $mp_res = json_decode(curl_exec($ch), true);
                     curl_close($ch);
                     if (isset($mp_res['status'])) $new_status = $mp_res['status'];
                }
                
                if ($new_status) {
                    $new_status = strtolower($new_status); 
                    $db_status = ($new_status === 'paid' || $new_status === 'completed') ? 'approved' : $new_status;

                    $pdo->prepare("UPDATE vendas SET status_pagamento = ? WHERE transacao_id = ?")->execute([$db_status, $payment_id]);
                    
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

                        if ($db_status === 'approved' && $main_sale['email_entrega_enviado'] == 0) {
                            // Gera senha para área de membros
                            $processed_prods = [];
                            $pass = null; 
                            foreach ($all_sales as $s) {
                                $res = process_single_product_delivery($s, $s['comprador_email']);
                                if ($res['success']) {
                                    if ($res['content_type'] == 'area_membros') {
                                        // Garante senha se for area de membros
                                        $pass_raw = bin2hex(random_bytes(8));
                                        $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
                                        
                                        // Verifica usuário
                                        $exists = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
                                        $exists->execute([$s['comprador_email']]);
                                        if ($u = $exists->fetch()) {
                                            // Cliente já existe - NÃO atualiza a senha, mantém a existente
                                            // Envia mensagem informativa no e-mail
                                            if(!$pass) $pass = '(use a mesma senha cadastrada anteriormente)';
                                        } else {
                                            $pdo->prepare("INSERT INTO usuarios (usuario, nome, senha, tipo) VALUES (?, ?, ?, 'usuario')")->execute([$s['comprador_email'], $s['comprador_nome'], $pass_hash]);
                                            if(!$pass) $pass = $pass_raw;
                                        }
                                    }
                                    $processed_prods[] = $res;
                                }
                            }
                            
                            if (!empty($processed_prods)) {
                                // Busca Link de Login
                                $login_url_stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'member_area_login_url'");
                                $login_url = $login_url_stmt->fetchColumn() ?: '#';

                                // Envia E-mail (Agora passando todas as variáveis corretas)
                                send_delivery_email_consolidated($main_sale['comprador_email'], $main_sale['comprador_nome'], $processed_prods, $pass, $login_url);
                                
                                $pdo->prepare("UPDATE vendas SET email_entrega_enviado = 1 WHERE checkout_session_uuid = ?")->execute([$main_sale['checkout_session_uuid']]);
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
        header('Content-Type: application/json');
        ob_clean();
        if (!isset($_SESSION['id'])) { echo json_encode(['error' => 'Auth required']); exit; }
        $uid = $_SESSION['id'];
        $action = $_GET['action'] ?? '';

        if ($action === 'get_unread_count') {
            $c = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
            $c->execute([$uid]);
            echo json_encode(['success' => true, 'count' => $c->fetchColumn()]);
            exit;
        }
        if ($action === 'get_recent_notifications') {
            $s = $pdo->prepare("SELECT id, tipo, mensagem, valor, DATE_FORMAT(data_notificacao, '%Y-%m-%dT%H:%i:%s') as data_notificacao, lida, link_acao FROM notificacoes WHERE usuario_id = ? ORDER BY data_notificacao DESC LIMIT 10");
            $s->execute([$uid]);
            echo json_encode(['success' => true, 'notifications' => $s->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }
        if ($action === 'get_live_notifications') {
            $s = $pdo->prepare("SELECT n.id, n.tipo, n.mensagem, n.valor, n.metodo_pagamento, p.nome as produto_nome, p.foto as produto_foto FROM notificacoes n LEFT JOIN vendas v ON n.venda_id_fk = v.id LEFT JOIN produtos p ON v.produto_id = p.id WHERE n.usuario_id = ? AND n.displayed_live = 0 ORDER BY n.data_notificacao ASC LIMIT 5");
            $s->execute([$uid]);
            echo json_encode(['success' => true, 'live_notifications' => $s->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }
    }

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Erro Fatal Notification: " . $e->getMessage());
    ob_clean();
    echo json_encode(['error' => 'Erro interno']);
}
?>