<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/json');

// Importa helper de acesso para respeitar tipo_acesso da oferta
if (file_exists(__DIR__ . '/helpers/acesso_helper.php')) {
    require_once __DIR__ . '/helpers/acesso_helper.php';
}

function log_action($msg) {
    file_put_contents(__DIR__ . '/vendas_actions_log.txt', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

// Verifica autenticação
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    log_action("Erro: Não autorizado.");
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

// --- CARREGA O HELPER PARA ENVIAR O EVENTO ---
if (file_exists(__DIR__ . '/helpers/utmfy_helper.php')) {
    require_once __DIR__ . '/helpers/utmfy_helper.php';
} else {
    log_action("ERRO: utmfy_helper.php não encontrado.");
}

// PHPMailer imports...
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$phpmailer_path = __DIR__ . '/PHPMailer/src/';
if (file_exists($phpmailer_path . 'Exception.php')) { require_once $phpmailer_path . 'Exception.php'; require_once $phpmailer_path . 'PHPMailer.php'; require_once $phpmailer_path . 'SMTP.php'; }

// --- FUNÇÃO PROCESSAR ENTREGA ---
function process_single_product_delivery($product_data, $customer_email) {
    global $pdo;
    $type = $product_data['tipo_entrega']; 
    $content = $product_data['conteudo_entrega'];
    $oferta_id = $product_data['oferta_id'] ?? null; // Pega oferta_id da venda
    
    if ($type === 'link') {
        return ['success' => !empty($content), 'product_name' => $product_data['produto_nome'], 'content_type' => 'link', 'content_value' => $content];
    }
    if ($type === 'email_pdf') {
        return ['success' => file_exists('uploads/'.$content), 'product_name' => $product_data['produto_nome'], 'content_type' => 'pdf', 'content_value' => 'uploads/'.$content];
    }
    if ($type === 'area_membros') {
        // Usa a função helper que respeita o tipo_acesso da oferta (mensal, semestral, anual, vitalicio)
        if (function_exists('conceder_acesso_aluno')) {
            $acesso_concedido = conceder_acesso_aluno($pdo, $customer_email, $product_data['produto_id'], $oferta_id);
            log_action("Acesso concedido via helper: email={$customer_email}, produto={$product_data['produto_id']}, oferta=" . ($oferta_id ?? 'padrão'));
        } else {
            // Fallback se o helper não estiver disponível
            $pdo->prepare("INSERT IGNORE INTO alunos_acessos (aluno_email, produto_id, oferta_id) VALUES (?, ?, ?)")->execute([$customer_email, $product_data['produto_id'], $oferta_id]);
            log_action("Acesso concedido via INSERT direto: email={$customer_email}, produto={$product_data['produto_id']}, oferta=" . ($oferta_id ?? 'NULL'));
        }
        return ['success' => true, 'product_name' => $product_data['produto_nome'], 'content_type' => 'area_membros', 'content_value' => null];
    }
    return ['success' => false, 'message' => 'Tipo desconhecido'];
}

// --- FUNÇÃO DE ENVIO DE E-MAIL ---
function manual_send_email($to_email, $customer_name, $products, $password, $login_url) {
    global $pdo;
    $mail = new PHPMailer(true);
    try {
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'email_template_delivery_subject', 'email_template_delivery_html')");
        $conf = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $default_from = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        
        $fromEmail = !empty($conf['smtp_from_email']) ? $conf['smtp_from_email'] : ($conf['smtp_username'] ?? $default_from);
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) $fromEmail = $default_from;

        if (empty($conf['smtp_host'])) {
            $mail->isMail();
        } else {
            $mail->isSMTP();
            $mail->Host = $conf['smtp_host'];
            $mail->Port = $conf['smtp_port'];
            $mail->SMTPAuth = true;
            $mail->Username = $conf['smtp_username'];
            $mail->Password = $conf['smtp_password'];
            $mail->SMTPSecure = ($conf['smtp_encryption'] == 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->setFrom($fromEmail, $conf['smtp_from_name'] ?? 'GatewayPro');
        $mail->CharSet = 'UTF-8';
        $mail->addAddress($to_email, $customer_name); 
        $mail->Subject = $conf['email_template_delivery_subject'] ?? 'Seu acesso chegou!';
        $mail->isHTML(true);

        $template = $conf['email_template_delivery_html'] ?? '<p>Olá {CLIENT_NAME}, seus produtos:</p><!-- LOOP_PRODUCTS_START --><p>{PRODUCT_NAME}: <a href="{PRODUCT_LINK}">{PRODUCT_LINK}</a></p><!-- LOOP_PRODUCTS_END -->';
        $body = str_replace(['{CLIENT_NAME}', '{CLIENT_EMAIL}', '{MEMBER_AREA_PASSWORD}', '{MEMBER_AREA_LOGIN_URL}'], [$customer_name, $to_email, $password ?? 'N/A', $login_url], $template);
        
        $loop_start = '<!-- LOOP_PRODUCTS_START -->'; $loop_end = '<!-- LOOP_PRODUCTS_END -->';
        if (strpos($body, $loop_start) !== false) {
            $part = substr($body, strpos($body, $loop_start) + strlen($loop_start));
            $part = substr($part, 0, strpos($part, $loop_end));
            $html_prods = '';
            foreach ($products as $p) {
                $item = str_replace('{PRODUCT_NAME}', $p['product_name'], $part);
                $item = str_replace('{PRODUCT_LINK}', ($p['content_type']=='link' ? $p['content_value'] : ''), $item);
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
        
        foreach ($products as $p) {
            if ($p['content_type'] == 'pdf' && file_exists($p['content_value'])) {
                $mail->addAttachment($p['content_value'], basename($p['content_value']));
            }
        }

        $mail->send();
        log_action("E-mail enviado com sucesso para: $to_email");
        return ['success' => true];
    } catch (Exception $e) { 
        $errorMsg = "Erro PHPMailer: " . $e->getMessage();
        log_action($errorMsg);
        return ['success' => false, 'error' => $errorMsg]; 
    }
}

// --- PROCESSAMENTO ---
$action = $_POST['action'] ?? '';
$venda_id = $_POST['venda_id'] ?? 0;
$usuario_id_logado = $_SESSION['id'];

if (!$venda_id) { 
    echo json_encode(['success'=>false, 'message'=>'ID inválido.']); exit; 
}

// Valida se a venda pertence ao usuário logado
// IMPORTANTE: Buscamos p.nome as produto_nome para usar no email e no UTMfy
$stmt = $pdo->prepare("SELECT v.*, p.nome as produto_nome, p.tipo_entrega, p.conteudo_entrega, p.usuario_id FROM vendas v JOIN produtos p ON v.produto_id = p.id WHERE v.id = ?");
$stmt->execute([$venda_id]);
$venda_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venda_data || $venda_data['usuario_id'] != $usuario_id_logado) {
    echo json_encode(['success'=>false, 'message'=>'Venda não encontrada.']);
    exit;
}

// Recupera todas as vendas da mesma sessão
$stmt_session = $pdo->prepare("SELECT v.*, p.nome as produto_nome, p.tipo_entrega, p.conteudo_entrega, p.id as pid FROM vendas v JOIN produtos p ON v.produto_id = p.id WHERE v.checkout_session_uuid = ?");
$stmt_session->execute([$venda_data['checkout_session_uuid']]);
$todas_vendas = $stmt_session->fetchAll(PDO::FETCH_ASSOC);

$email_destino = $venda_data['comprador_email'];
$nome_destino = $venda_data['comprador_nome'];

if ($action === 'approve' || $action === 'resend') {
    
    if ($action === 'resend' && !empty($_POST['email'])) {
        $new_email = $_POST['email'];
        if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $email_destino = $new_email;
            $pdo->prepare("UPDATE vendas SET comprador_email = ? WHERE checkout_session_uuid = ?")->execute([$new_email, $venda_data['checkout_session_uuid']]);
            log_action("E-mail atualizado para: $new_email");
        }
    }

    // --- AÇÃO DE APROVAR ---
    if ($action === 'approve') {
        $pdo->prepare("UPDATE vendas SET status_pagamento = 'approved' WHERE checkout_session_uuid = ?")->execute([$venda_data['checkout_session_uuid']]);
        
        $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem, valor, venda_id_fk, metodo_pagamento) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$usuario_id_logado, 'Compra Aprovada (Manual)', 'Venda aprovada manualmente.', $venda_data['valor'], $venda_id, 'Manual']);
        
        // --- DISPARO EVOLUTION API (WhatsApp) ---
        if (file_exists(__DIR__ . '/helpers/evolution_helper.php')) {
            require_once __DIR__ . '/helpers/evolution_helper.php';
            log_action("Disparando Evolution API para Venda ID: $venda_id");
            process_evolution_messages($pdo, $venda_data, 'approved');
        }
        
        // --- DISPARO UTMFY MANUAL ---
        if (function_exists('trigger_utmfy_integrations')) {
            log_action("Disparando UTMfy Manual para Venda ID: $venda_id");
            
            // Prepara dados para o Helper
            $event_data = [
                'transacao_id' => $venda_data['transacao_id'],
                'valor_total_compra' => $venda_data['valor'],
                'data_venda' => $venda_data['data_venda'],
                'comprador' => [
                    'nome' => $venda_data['comprador_nome'],
                    'email' => $venda_data['comprador_email'],
                    'cpf' => $venda_data['comprador_cpf'],
                    'telefone' => $venda_data['comprador_telefone']
                ],
                'metodo_pagamento' => 'Manual/Aprovado',
                'utm_parameters' => [
                    'src' => $venda_data['src'] ?? null,
                    'sck' => $venda_data['sck'] ?? null,
                    'utm_source' => $venda_data['utm_source'] ?? null,
                    'utm_campaign' => $venda_data['utm_campaign'] ?? null,
                    'utm_medium' => $venda_data['utm_medium'] ?? null,
                    'utm_content' => $venda_data['utm_content'] ?? null,
                    'utm_term' => $venda_data['utm_term'] ?? null
                ],
                // Monta o produto explicitamente com 'nome'
                'produtos_comprados' => [
                    [
                        'produto_id' => $venda_data['produto_id'],
                        'nome' => $venda_data['produto_nome'], // Garante que o nome seja passado
                        'valor' => $venda_data['valor']
                    ]
                ]
            ];

            // Chama o helper passando 'approved' (que vira paid)
            trigger_utmfy_integrations($usuario_id_logado, $event_data, 'approved', $venda_data['produto_id']);
        }
        
        log_action("Venda aprovada manualmente: $venda_id");
    }

    // Processa entrega
    $delivered_prods = [];
    $member_pass = null;
    
    foreach ($todas_vendas as $v) {
        $res = process_single_product_delivery($v, $email_destino);

        if ($res['success']) {
            if ($res['content_type'] === 'area_membros' && !$member_pass) {
                $pass_raw = bin2hex(random_bytes(8));
                $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
                
                $exists = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
                $exists->execute([$email_destino]);
                
                if ($u = $exists->fetch()) {
                    // Cliente já existe - NÃO atualiza a senha, mantém a existente
                    // Envia mensagem informativa no e-mail
                    if(!$member_pass) $member_pass = '(use a mesma senha cadastrada anteriormente)';
                } else {
                    $pdo->prepare("INSERT INTO usuarios (usuario, nome, senha, tipo) VALUES (?, ?, ?, 'usuario')")->execute([$email_destino, $nome_destino, $pass_hash]);
                    if(!$member_pass) $member_pass = $pass_raw;
                }
            }
            $delivered_prods[] = $res;
        }
    }

    $login_url_stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'member_area_login_url'");
    $login_url = $login_url_stmt->fetchColumn() ?: '#';

    $send_result = manual_send_email($email_destino, $nome_destino, $delivered_prods, $member_pass, $login_url);

    if ($send_result['success']) {
        $pdo->prepare("UPDATE vendas SET email_entrega_enviado = 1 WHERE checkout_session_uuid = ?")->execute([$venda_data['checkout_session_uuid']]);
        echo json_encode(['success' => true, 'message' => 'Ação realizada com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação processada, mas falha no envio do e-mail: ' . $send_result['error']]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
}
?>