<?php
require __DIR__ . '/config/config.php';

// Importa helper de acesso para respeitar tipo_acesso da oferta
if (file_exists(__DIR__ . '/helpers/acesso_helper.php')) {
    require_once __DIR__ . '/helpers/acesso_helper.php';
}

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$phpmailer_path = __DIR__ . '/PHPMailer/src/';
if (file_exists($phpmailer_path . 'Exception.php')) { 
    require_once $phpmailer_path . 'Exception.php'; 
    require_once $phpmailer_path . 'PHPMailer.php'; 
    require_once $phpmailer_path . 'SMTP.php'; 
}

// Inclui apenas as funções necessárias (sem executar o código principal)
if (!function_exists('process_single_product_delivery')) {
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
                error_log("Obrigado.php: Acesso concedido via helper: email={$customer_email}, produto={$product_data['produto_id']}, oferta=" . ($oferta_id ?? 'padrão'));
            } else {
                // Fallback se o helper não estiver disponível
                $pdo->prepare("INSERT IGNORE INTO alunos_acessos (aluno_email, produto_id, oferta_id) VALUES (?, ?, ?)")->execute([$customer_email, $product_data['produto_id'], $oferta_id]);
                error_log("Obrigado.php: Acesso concedido via INSERT direto: email={$customer_email}, produto={$product_data['produto_id']}, oferta=" . ($oferta_id ?? 'NULL'));
            }
            return ['success' => true, 'product_name' => $product_data['produto_nome'], 'content_type' => 'area_membros', 'content_value' => null];
        }
        return ['success' => false, 'message' => 'Tipo desconhecido ou vazio'];
    }
}

if (!function_exists('send_delivery_email_consolidated')) {
    
    function send_delivery_email_consolidated($to_email, $customer_name, $products, $pass, $login_url) {
        global $pdo;
        error_log("send_delivery_email_consolidated chamada para: " . $to_email);
        
        $mail = new PHPMailer(true);
        try {
            // Busca configurações SMTP e TEMPLATE do banco
            $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'email_template_delivery_subject', 'email_template_delivery_html')");
            $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            error_log("Configuracoes SMTP carregadas. Host: " . ($config['smtp_host'] ?? 'NÃO CONFIGURADO'));
            
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
            error_log("Logo URL final: " . ($logo_url_final ?: 'NÃO CONFIGURADA'));
            
            // Configuração de Remetente
            $default_from = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $fromEmail = !empty($config['smtp_from_email']) ? $config['smtp_from_email'] : ($config['smtp_username'] ?? $default_from);
            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) $fromEmail = $default_from;

            if (empty($config['smtp_host'])) {
                $mail->isMail();
                error_log("Usando mail() nativo do PHP");
            } else {
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'];
                $mail->Port = $config['smtp_port'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_username'];
                $mail->Password = $config['smtp_password'];
                $mail->SMTPSecure = $config['smtp_encryption'] == 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                error_log("Usando SMTP: " . $config['smtp_host'] . ":" . $config['smtp_port']);
            }
            
            $mail->setFrom($fromEmail, $config['smtp_from_name'] ?? 'Hub SinergIA');
            $mail->addAddress($to_email, $customer_name);
            
            // Usa o assunto do banco ou um padrão
            $mail->Subject = $config['email_template_delivery_subject'] ?? 'Seu acesso chegou!';
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';

            // Usa o template do banco ou um padrão
            $template = $config['email_template_delivery_html'] ?? '<p>Olá {CLIENT_NAME}, aqui estão seus produtos:</p><!-- LOOP_PRODUCTS_START --><p>{PRODUCT_NAME}</p><!-- LOOP_PRODUCTS_END -->';

            // Substitui variáveis, incluindo {LOGO_URL}
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
                     
                     // Limpa tags condicionais
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

            error_log("Tentando enviar email...");
            $mail->send();
            error_log("Email enviado com sucesso!");
            return true;
        } catch (Exception $e) {
            error_log("ERRO ao enviar email: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
}

$payment_id = $_GET['payment_id'] ?? null;
$sale_details = null;
$tracking_config = [];
$fb_events_enabled = [];
$gg_events_enabled = [];

// NEW: Variables for GatewayPro Track
$GatewayPro_track_endpoint = null;
$GatewayPro_tracking_id_hash = null;
$GatewayPro_checkout_session_uuid = null; // To get from DB, if it exists

if ($payment_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                v.*,
                p.id as produto_id,
                p.nome as produto_nome,
                p.tipo_entrega,
                p.conteudo_entrega,
                p.checkout_config
            FROM vendas v
            JOIN produtos p ON v.produto_id = p.id
            WHERE v.transacao_id = ?
            LIMIT 1
        ");
        $stmt->execute([$payment_id]);
        $sale_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se a venda existe mas o email ainda não foi enviado, verifica status e processa entrega
        if ($sale_details && $sale_details['email_entrega_enviado'] == 0) {
            // Se o status no BD não está aprovado, verifica na API do gateway
            $status_aprovado = false;
            if ($sale_details['status_pagamento'] === 'approved') {
                $status_aprovado = true;
            } else {
                // Verifica status real na API (pode estar desatualizado no BD)
                error_log("Obrigado.php: Status no BD é '" . $sale_details['status_pagamento'] . "'. Verificando na API...");
                
                // Busca token do gateway
                $stmt_gateway = $pdo->prepare("SELECT u.mp_access_token, u.pushinpay_token, u.paypal_client_id, u.paypal_client_secret, u.efi_client_id, u.efi_client_secret, u.efi_certificate_path, p.gateway FROM produtos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE p.id = ?");
                $stmt_gateway->execute([$sale_details['produto_id']]);
                $gateway_info = $stmt_gateway->fetch(PDO::FETCH_ASSOC);
                
                if ($gateway_info) {
                    $payment_id = $sale_details['transacao_id'];
                    $has_efi = !empty($gateway_info['efi_client_id'])
                        && !empty($gateway_info['efi_client_secret'])
                        && !empty($gateway_info['efi_certificate_path']);
                    // txid Pix Efí: 26–35 chars alfanuméricos (padrão Bacen/Efí)
                    $looks_like_efi_txid = $has_efi
                        && (bool)preg_match('/^[a-zA-Z0-9]{26,35}$/', (string)$payment_id);

                    // Verifica Efí Pix primeiro quando o ID é txid (evita cair no MP por token antigo)
                    if ($looks_like_efi_txid || ($has_efi && ($gateway_info['gateway'] ?? '') === 'efi')) {
                        require_once __DIR__ . '/gateways/efi.php';
                        $full_cert_path = __DIR__ . '/' . ltrim(str_replace('\\', '/', $gateway_info['efi_certificate_path']), '/');
                        if (file_exists($full_cert_path)) {
                            $token_data = efi_get_access_token($gateway_info['efi_client_id'], $gateway_info['efi_client_secret'], $full_cert_path);
                            if ($token_data && !empty($token_data['access_token'])) {
                                $status_data = efi_get_payment_status($token_data['access_token'], $payment_id, $full_cert_path);
                                error_log("Obrigado.php: Efí status: " . json_encode($status_data));
                                if ($status_data && in_array(($status_data['status'] ?? ''), ['approved', 'paid'], true)) {
                                    $status_aprovado = true;
                                    $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE transacao_id = ?")->execute([$payment_id]);
                                    error_log("Obrigado.php: Status atualizado para 'approved' no BD após verificação na API Efí");
                                }
                            } else {
                                error_log("Obrigado.php: Efí - falha ao obter access token");
                            }
                        } else {
                            error_log("Obrigado.php: Efí - certificado não encontrado: " . $full_cert_path);
                        }
                    }
                    // Verifica Mercado Pago
                    elseif (!empty($gateway_info['mp_access_token']) && ($gateway_info['gateway'] === 'mercadopago' || empty($gateway_info['gateway']))) {
                        $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $payment_id);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $gateway_info['mp_access_token']]);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        $mp_res = json_decode(curl_exec($ch), true);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($http_code == 200 && isset($mp_res['status'])) {
                            $status_real = strtolower($mp_res['status']);
                            if ($status_real === 'approved' || $status_real === 'paid' || $status_real === 'completed') {
                                $status_aprovado = true;
                                // Atualiza status no BD
                                $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE transacao_id = ?")->execute([$payment_id]);
                                error_log("Obrigado.php: Status atualizado para 'approved' no BD após verificação na API Mercado Pago");
                            }
                        }
                    }
                    // Verifica PushinPay
                    elseif (!empty($gateway_info['pushinpay_token']) && $gateway_info['gateway'] === 'pushinpay') {
                        $endpoints = [
                            'https://api.pushinpay.com.br/api/transactions/' . $payment_id,
                            'https://api.pushinpay.com.br/api/pix/transactions/' . $payment_id
                        ];
                        
                        foreach ($endpoints as $endpoint) {
                            $ch = curl_init($endpoint);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Authorization: Bearer ' . $gateway_info['pushinpay_token'],
                                'Accept: application/json'
                            ]);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                            $pp_res = json_decode(curl_exec($ch), true);
                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            if ($http_code >= 200 && $http_code < 300 && isset($pp_res['status'])) {
                                $status_real = strtolower($pp_res['status']);
                                if ($status_real === 'approved' || $status_real === 'paid' || $status_real === 'completed') {
                                    $status_aprovado = true;
                                    // Atualiza status no BD
                                    $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE transacao_id = ?")->execute([$payment_id]);
                                    error_log("Obrigado.php: Status atualizado para 'approved' no BD após verificação na API PushinPay");
                                    break;
                                }
                            }
                        }
                    }
                    // Verifica PayPal - captura a ordem se ainda pendente
                    elseif (!empty($gateway_info['paypal_client_id']) && !empty($gateway_info['paypal_client_secret']) && stripos($sale_details['metodo_pagamento'], 'PayPal') !== false) {
                        require_once __DIR__ . '/gateways/paypal.php';
                        $sandbox = (strpos($gateway_info['paypal_client_id'], 'sb') !== false);
                        $capture = capture_paypal_order($payment_id, $gateway_info['paypal_client_id'], $gateway_info['paypal_client_secret'], $sandbox);
                        if ($capture && ($capture['status'] === 'COMPLETED' || $capture['status'] === 'completed')) {
                            $status_aprovado = true;
                            $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE transacao_id = ?")->execute([$payment_id]);
                            error_log("Obrigado.php: PayPal capturado e status atualizado para approved");
                        }
                    }
                }
            }
            
            // Processa entrega apenas se estiver aprovado
            if ($status_aprovado) {
                error_log("Obrigado.php: Processando entrega diretamente para transacao: " . $payment_id);
            
            // Busca todas as vendas desta transação
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
                
                // Processa produtos
                $processed_prods = [];
                $pass = null;
                error_log("Obrigado.php: Total de vendas para processar: " . count($all_sales));
                
                foreach ($all_sales as $s) {
                    error_log("Obrigado.php: Processando produto - Nome: " . $s['produto_nome'] . ", Tipo entrega: " . ($s['tipo_entrega'] ?? 'NÃO CONFIGURADO') . ", Conteudo: " . ($s['conteudo_entrega'] ?? 'VAZIO'));
                    $res = process_single_product_delivery($s, $s['comprador_email']);
                    error_log("Obrigado.php: Resultado processamento - Success: " . ($res['success'] ? 'SIM' : 'NÃO') . ", Message: " . ($res['message'] ?? 'OK'));
                    
                    if ($res['success']) {
                        if ($res['content_type'] == 'area_membros') {
                            $pass_raw = bin2hex(random_bytes(8));
                            $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
                            $exists = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
                            $exists->execute([$s['comprador_email']]);
                            if ($u = $exists->fetch()) {
                                // Cliente já existe - NÃO atualiza a senha, mantém a existente
                                error_log("Obrigado.php: Usuario existente encontrado, senha mantida");
                                // Envia mensagem informativa no e-mail
                                if (!$pass) $pass = '(use a mesma senha cadastrada anteriormente)';
                            } else {
                                $pdo->prepare("INSERT INTO usuarios (usuario, nome, senha, tipo) VALUES (?, ?, ?, 'usuario')")->execute([$s['comprador_email'], $s['comprador_nome'], $pass_hash]);
                                error_log("Obrigado.php: Usuario criado");
                                if (!$pass) $pass = $pass_raw;
                            }
                        }
                        $processed_prods[] = $res;
                        error_log("Obrigado.php: Produto adicionado à lista de processados");
                    } else {
                        error_log("Obrigado.php: Produto NÃO foi processado - " . ($res['message'] ?? 'Erro desconhecido'));
                    }
                }
                
                error_log("Obrigado.php: Total de produtos processados com sucesso: " . count($processed_prods));
                
                // Notificação no sininho do infoprodutor (painel)
                if (!empty($main_sale['usuario_id'])) {
                    try {
                        $valor_total = array_sum(array_map(function ($s) { return (float)($s['valor'] ?? 0); }, $all_sales));
                        $msg_notif = "Venda Aprovada! R$ " . number_format($valor_total, 2, ',', '.');
                        $link_notif = "/index?pagina=vendas&id=" . (int)$main_sale['id'];

                        // Evita duplicar se o webhook já criou a notificação desta venda
                        $stmt_dup = $pdo->prepare("SELECT id FROM notificacoes WHERE usuario_id = ? AND venda_id_fk = ? AND tipo = 'Compra Aprovada' LIMIT 1");
                        $stmt_dup->execute([(int)$main_sale['usuario_id'], (int)$main_sale['id']]);
                        if (!$stmt_dup->fetch()) {
                            $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, valor, link_acao, venda_id_fk, metodo_pagamento) VALUES (?, 'Compra Aprovada', ?, ?, ?, ?, ?)")
                                ->execute([
                                    (int)$main_sale['usuario_id'],
                                    $msg_notif,
                                    $valor_total,
                                    $link_notif,
                                    (int)$main_sale['id'],
                                    $main_sale['metodo_pagamento'] ?? null
                                ]);
                            error_log("Obrigado.php: Notificação 'Compra Aprovada' criada para usuario_id=" . $main_sale['usuario_id']);
                        } else {
                            error_log("Obrigado.php: Notificação já existia para venda_id=" . $main_sale['id']);
                        }
                    } catch (Throwable $e) {
                        error_log("Obrigado.php: Erro ao criar notificação: " . $e->getMessage());
                    }
                }

                // Envia email se houver produtos processados
                if (!empty($processed_prods)) {
                    $login_url_stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'member_area_login_url'");
                    $login_url = $login_url_stmt->fetchColumn() ?: '#';
                    
                    error_log("Obrigado.php: Preparando envio de email para: " . $main_sale['comprador_email']);
                    error_log("Obrigado.php: Login URL: " . $login_url);
                    error_log("Obrigado.php: Senha gerada: " . ($pass ? 'SIM' : 'NÃO'));
                    
                    $email_sent = send_delivery_email_consolidated($main_sale['comprador_email'], $main_sale['comprador_nome'], $processed_prods, $pass, $login_url);
                    
                    if ($email_sent) {
                        $pdo->prepare("UPDATE vendas SET email_entrega_enviado = 1 WHERE checkout_session_uuid = ?")->execute([$main_sale['checkout_session_uuid']]);
                        error_log("Obrigado.php: Email enviado com sucesso! Flag atualizada.");
                    } else {
                        error_log("Obrigado.php: ERRO ao enviar email! Verifique os logs de erro do PHP.");
                    }
                } else {
                    error_log("Obrigado.php: AVISO - Nenhum produto foi processado. Verifique se o produto tem tipo_entrega configurado.");
                }
            } else {
                error_log("Obrigado.php: Pagamento ainda não foi aprovado. Status atual: " . ($sale_details['status_pagamento'] ?? 'N/A'));
            }
            }
        }

        if ($sale_details) {
            $checkout_config = json_decode($sale_details['checkout_config'] ?? '{}', true);
            $tracking_config = $checkout_config['tracking'] ?? [];
            
            // Retrocompatibilidade para o pixel antigo
            if (empty($tracking_config['facebookPixelId']) && !empty($checkout_config['facebookPixelId'])) {
                $tracking_config['facebookPixelId'] = $checkout_config['facebookPixelId'];
            }
            
            $tracking_events = $tracking_config['events'] ?? [];
            $fb_events_enabled = $tracking_events['facebook'] ?? [];
            $gg_events_enabled = $tracking_events['google'] ?? [];

            // NEW: Fetch GatewayPro Track info
            $GatewayPro_checkout_session_uuid = $sale_details['checkout_session_uuid'];
            $stmt_get_GatewayPro_tracking = $pdo->prepare("SELECT stp.tracking_id FROM gatewaypro_tracking_products stp JOIN produtos p ON stp.produto_id = p.id WHERE p.id = ?");
            $stmt_get_GatewayPro_tracking->execute([$sale_details['produto_id']]);
            $GatewayPro_tracking_id_hash = $stmt_get_GatewayPro_tracking->fetchColumn();

            if ($GatewayPro_tracking_id_hash) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $domainName = $_SERVER['HTTP_HOST'];
                $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $GatewayPro_track_endpoint = $protocol . $domainName . $basePath . '/track.php';
            }
        }
    } catch (PDOException $e) {
        // Em um ambiente de produção, é melhor logar este erro do que exibi-lo.
        error_log("Erro ao buscar detalhes da venda para rastreamento: " . $e->getMessage());
    }
}

$fbPixelId = $tracking_config['facebookPixelId'] ?? '';
$gaId = $tracking_config['googleAnalyticsId'] ?? '';
$gAdsId = $tracking_config['googleAdsId'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obrigado pela sua compra!</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>

    <!-- Scripts de Rastreamento de Compra Aprovada -->
    <?php if ($sale_details): ?>

        <?php // Facebook Pixel Purchase Event ?>
        <?php if (!empty($fbPixelId) && !empty($fb_events_enabled['purchase'])): ?>
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo htmlspecialchars($fbPixelId); ?>');
        fbq('track', 'PageView');
        fbq('track', 'Purchase', {
            value: <?php echo (float)$sale_details['valor']; ?>,
            currency: 'BRL'
        });
        </script>
        <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($fbPixelId); ?>&ev=Purchase&cd[value]=<?php echo (float)$sale_details['valor']; ?>&cd[currency]=BRL"
        /></noscript>
        <?php endif; ?>

        <?php // Google Analytics & Ads Purchase Event ?>
        <?php if ((!empty($gaId) || !empty($gAdsId)) && !empty($gg_events_enabled['purchase'])): 
            $google_primary_id = !empty($gAdsId) ? $gAdsId : $gaId;
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($google_primary_id); ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          <?php if (!empty($gAdsId)): ?>
          gtag('config', '<?php echo htmlspecialchars($gAdsId); ?>');
          <?php endif; ?>
          <?php if (!empty($gaId)): ?>
          gtag('config', '<?php echo htmlspecialchars($gaId); ?>');
          <?php endif; ?>

          gtag('event', 'purchase', {
            "transaction_id": "<?php echo htmlspecialchars($sale_details['transacao_id']); ?>",
            "value": <?php echo (float)$sale_details['valor']; ?>,
            "currency": "BRL",
            "items": [{
              "item_id": "<?php echo htmlspecialchars($sale_details['produto_id']); ?>",
              "item_name": "<?php echo htmlspecialchars($sale_details['produto_nome']); ?>",
              "price": <?php echo (float)$sale_details['valor']; ?>,
              "quantity": 1
            }]
          });
        </script>
        <?php endif; ?>

        <!-- NEW: GatewayPro TRACK - Purchase Event -->
        <?php if ($GatewayPro_track_endpoint && $GatewayPro_tracking_id_hash && $GatewayPro_checkout_session_uuid): ?>
        <script>
            (function() {
                const GatewayPro_TRACK_ID_HASH = '<?php echo htmlspecialchars($GatewayPro_tracking_id_hash); ?>';
                const TRACK_ENDPOINT = '<?php echo $GatewayPro_track_endpoint; ?>';
                const CHECKOUT_SESSION_UUID = '<?php echo htmlspecialchars($GatewayPro_checkout_session_uuid); ?>';
                const PRODUCT_ID = <?php echo (int)$sale_details['produto_id']; ?>;
                const PURCHASE_VALUE = <?php echo (float)$sale_details['valor']; ?>;

                // Send event to tracking endpoint
                function sendGatewayProTrackEvent(eventType, eventData = {}) {
                    const payload = {
                        tracking_id: GatewayPro_TRACK_ID_HASH,
                        session_id: CHECKOUT_SESSION_UUID, // Use the checkout session UUID as the session_id for this event
                        event_type: eventType,
                        event_data: {
                            ...eventData,
                            url: window.location.href,
                            referrer: document.referrer,
                            transaction_id: '<?php echo htmlspecialchars($sale_details['transacao_id']); ?>',
                            product_id: PRODUCT_ID,
                            value: PURCHASE_VALUE,
                            currency: 'BRL'
                        }
                    };

                    fetch(TRACK_ENDPOINT, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(response => {
                        if (!response.ok) {
                            console.error('GatewayPro Track: Erro ao enviar evento ' + eventType + ':', response.statusText);
                        } else {
                            console.log('GatewayPro Track: Evento ' + eventType + ' enviado com sucesso.');
                        }
                    }).catch(error => {
                        console.error('GatewayPro Track: Erro de rede ao enviar evento ' + eventType + ':', error);
                    });
                }

                // Track Purchase on page load
                sendGatewayProTrackEvent('purchase');
            })();
        </script>
        <?php endif; ?>
        <!-- Fim GatewayPro TRACK -->
        
    <?php endif; ?>
    <!-- Fim dos Scripts de Rastreamento -->

</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <?php 
    // Detecta se é produto grátis
    $is_free_product = $sale_details && (
        $sale_details['metodo_pagamento'] === 'Grátis' || 
        (float)$sale_details['valor'] == 0 ||
        strpos($sale_details['transacao_id'], 'FREE_') === 0
    );
    ?>
    
    <div class="w-full max-w-lg p-6 sm:p-8 bg-white rounded-2xl shadow-lg text-center">
        <?php if ($is_free_product): ?>
        <!-- Mensagem para Produto Grátis -->
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
            <i data-lucide="gift" class="h-12 w-12 text-green-600"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800">Acesso Liberado!</h1>
        <p class="text-gray-600 mt-4 text-lg">
            Parabéns! Seu acesso gratuito foi liberado com sucesso.
        </p>
        
        <?php if($sale_details && $sale_details['produto_nome']): ?>
            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm text-green-600">Produto liberado:</p>
                <p class="font-semibold text-green-800 text-lg"><?php echo htmlspecialchars($sale_details['produto_nome']); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg text-left">
            <h3 class="font-semibold text-blue-800 flex items-center gap-2 mb-3">
                <i data-lucide="mail" class="w-5 h-5"></i>
                Próximos passos
            </h3>
            <ul class="text-sm text-blue-700 space-y-2">
                <li class="flex items-start gap-2">
                    <i data-lucide="check" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                    <span>Enviamos um e-mail para <strong><?php echo htmlspecialchars($sale_details['comprador_email'] ?? ''); ?></strong> com as instruções de acesso.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="check" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                    <span>Verifique também sua caixa de spam ou lixo eletrônico.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="check" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                    <span>Se o produto for da área de membros, use o e-mail cadastrado para fazer login.</span>
                </li>
            </ul>
        </div>
        
        <?php else: ?>
        <!-- Mensagem para Produto Pago (original) -->
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
            <i data-lucide="check-circle-2" class="h-12 w-12 text-green-600"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800">Pagamento Recebido!</h1>
        <p class="text-gray-600 mt-4 text-lg">
            Obrigado pela sua compra! Em breve você receberá um e-mail com todos os detalhes e o acesso ao seu produto.
        </p>
        
        <?php if($sale_details && $sale_details['produto_nome']): ?>
            <div class="mt-8 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <p class="text-sm text-gray-500">Produto adquirido:</p>
                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($sale_details['produto_nome']); ?></p>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="mt-8">
            <a href="/member_login" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                <i data-lucide="log-in" class="w-5 h-5"></i>
                Acessar Área de Membros
            </a>
        </div>
        <?php if($payment_id): ?>
            <p class="text-xs text-gray-400 mt-10">ID da transação: <?php echo htmlspecialchars($payment_id); ?></p>
        <?php endif; ?>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
