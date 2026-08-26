<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
ob_start(); // Inicia o buffer de saída para evitar problemas com headers já enviados

// CRÍTICO: Desabilitar exibição de erros HTML ANTES de qualquer coisa
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../api_errors.log');

// Handler customizado para converter erros PHP em JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("API Error [$errno]: $errstr in $errfile on line $errline");
    // Não exibe nada, apenas loga
    return true; // Previne o handler padrão do PHP
});

// Handler para erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        error_log("API Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        echo json_encode(['success' => false, 'error' => 'Erro interno do servidor. Verifique os logs.']);
    }
});

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if (defined('APP_DEBUG') && APP_DEBUG) {
    error_log("API: Script iniciado."); // Log para o início do script (apenas em modo debug)
}

// Incluir os arquivos do PHPMailer
$phpmailer_path = __DIR__ . '/../PHPMailer/src/';
if (file_exists($phpmailer_path . 'Exception.php')) {
    require_once $phpmailer_path . 'Exception.php';
    error_log("API: PHPMailer Exception.php carregado com sucesso.");
} else {
    error_log("API: ERRO: PHPMailer Exception.php não encontrado em " . $phpmailer_path . 'Exception.php');
}
if (file_exists($phpmailer_path . 'PHPMailer.php')) {
    require_once $phpmailer_path . 'PHPMailer.php';
    error_log("API: PHPMailer PHPMailer.php carregado com sucesso.");
} else {
    error_log("API: ERRO: PHPMailer PHPMailer.php não encontrado em " . $phpmailer_path . 'PHPMailer.php');
}
if (file_exists($phpmailer_path . 'SMTP.php')) {
    require_once $phpmailer_path . 'SMTP.php';
    error_log("API: PHPMailer SMTP.php carregado com sucesso.");
} else {
    error_log("API: ERRO: PHPMailer SMTP.php não encontrado em " . $phpmailer_path . 'SMTP.php');
}


/**
 * Função para registrar mensagens em um arquivo de log.
 * @param string $message A mensagem a ser registrada.
 */
function log_webhook($message) {
    file_put_contents(__DIR__ . '/../webhook_log.txt', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

/**
 * Processa a entrega de um único produto, registrando acessos ou coletando dados para e-mail.
 *
 * @param array $product_data Detalhes do produto e da venda (`vendas` JOIN `produtos`).
 * @param string $customer_email E-mail do comprador.
 * @return array Um array com 'success' (bool), 'product_name' (string), 'content_type' (string: 'link', 'pdf', 'area_membros'),
 * 'content_value' (string: URL, path_to_pdf, ou null para área de membros), e 'message' (string).
 */
function process_single_product_delivery($product_data, $customer_email) {
    global $pdo;

    $delivery_type = $product_data['tipo_entrega'];
    $delivery_content = $product_data['conteudo_entrega'];
    $product_name = $product_data['produto_nome'];
    $product_id_for_area_membros = $product_data['produto_id'];
    $oferta_id = $product_data['oferta_id'] ?? null;

    error_log("  Iniciando processamento de entrega para produto '$product_name'. Tipo: '$delivery_type'.");
    
    switch ($delivery_type) {
        case 'link':
            if (!empty($delivery_content)) {
                return ['success' => true, 'product_name' => $product_name, 'content_type' => 'link', 'content_value' => $delivery_content];
            } else {
                return ['success' => false, 'message' => "Conteúdo de entrega (link) vazio para o produto '$product_name'."];
            }
        case 'email_pdf':
            if (!empty($delivery_content)) {
                $pdf_path = 'uploads/' . $delivery_content;
                if (file_exists($pdf_path) && is_readable($pdf_path)) {
                    return ['success' => true, 'product_name' => $product_name, 'content_type' => 'pdf', 'content_value' => $pdf_path];
                } else {
                    return ['success' => false, 'message' => "Arquivo PDF não encontrado ou ilegível em: " . $pdf_path];
                }
            } else {
                return ['success' => false, 'message' => "Conteúdo de entrega (PDF) vazio para o produto '$product_name'."];
            }
        case 'area_membros':
            if (!empty($customer_email) && !empty($product_id_for_area_membros)) {
                // Usar o helper de acesso para conceder acesso com data de expiração
                if (function_exists('conceder_acesso_aluno')) {
                    $acesso_concedido = conceder_acesso_aluno($pdo, $customer_email, $product_id_for_area_membros, $oferta_id);
                    if ($acesso_concedido) {
                        error_log("    SUCESSO DE ENTREGA (Área de Membros): Acesso concedido para " . $customer_email . " ao produto ID " . $product_id_for_area_membros . " (oferta: " . ($oferta_id ?? 'padrão') . ")");
                        return ['success' => true, 'product_name' => $product_name, 'content_type' => 'area_membros', 'content_value' => null];
                    } else {
                        return ['success' => false, 'message' => "Erro ao conceder acesso à área de membros para o produto '$product_name'."];
                    }
                } else {
                    // Fallback para o método antigo se o helper não estiver disponível
                    $stmt_grant_access = $pdo->prepare("INSERT IGNORE INTO alunos_acessos (aluno_email, produto_id, oferta_id) VALUES (?, ?, ?)");
                    $stmt_grant_access->execute([$customer_email, $product_id_for_area_membros, $oferta_id]);

                    if ($stmt_grant_access->rowCount() > 0) {
                        error_log("    SUCESSO DE ENTREGA (Área de Membros): Acesso concedido para " . $customer_email . " ao produto ID " . $product_id_for_area_membros);
                        return ['success' => true, 'product_name' => $product_name, 'content_type' => 'area_membros', 'content_value' => null];
                    } else {
                        error_log("    INFO DE ENTREGA (Área de Membros): Acesso para " . $customer_email . " ao produto ID " . $product_id_for_area_membros . " já existia ou falhou (IGNORADO).");
                        return ['success' => true, 'product_name' => $product_name, 'content_type' => 'area_membros', 'content_value' => null, 'message' => 'Acesso já concedido.'];
                    }
                }
            } else {
                return ['success' => false, 'message' => "E-mail do comprador ou ID do produto ausente para a área de membros do produto '$product_name'."];
            }
        default:
            return ['success' => false, 'message' => "Tipo de entrega desconhecido ('$delivery_type') para o produto '$product_name'."];
    }
}


/**
 * Função para enviar e-mail de entrega consolidado do produto.
 * Utiliza as configurações SMTP do administrador e um template personalizável.
 *
 * @param string $to_email E-mail do destinatário.
 * @param string $customer_name Nome do cliente.
 * @param array $processed_products_for_email Array de produtos com detalhes de entrega formatados.
 * @param string|null $member_area_password Senha gerada para a área de membros, se houver.
 * @param string|null $member_area_login_url URL de login da área de membros.
 * @param string $email_subject Assunto do e-mail (do admin config).
 * @param string $email_html_template Template HTML do e-mail (do admin config).
 * @return bool True se o e-mail foi enviado com sucesso, false caso contrário.
 */
function send_delivery_email_consolidated($to_email, $customer_name, $processed_products_for_email, $member_area_password, $member_area_login_url, $email_subject, $email_html_template) {
    global $pdo;

    $mail = new PHPMailer(true); // Habilita exceções

    try {
        // Obter configurações SMTP da tabela `configuracoes`
        $stmt_smtp_configs = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name')");
        $stmt_smtp_configs->execute();
        $smtp_configs_raw = $stmt_smtp_configs->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $smtp_config = [
            'host' => $smtp_configs_raw['smtp_host'] ?? '',
            'port' => (int)($smtp_configs_raw['smtp_port'] ?? 587),
            'username' => $smtp_configs_raw['smtp_username'] ?? '',
            'password' => $smtp_configs_raw['smtp_password'] ?? '',
            'encryption' => $smtp_configs_raw['smtp_encryption'] ?? 'tls',
            'from_email' => $smtp_configs_raw['smtp_from_email'] ?? '',
            'from_name' => $smtp_configs_raw['smtp_from_name'] ?? 'Hub SinergIA'
        ];

        // Adiciona logging das configurações SMTP
        error_log("EMAIL_DELIVERY: Configurações SMTP obtidas para envio: " . print_r($smtp_config, true));

        // Configurar PHPMailer para usar SMTP ou PHP Mailer padrão
        if (empty($smtp_config['host']) || empty($smtp_config['username']) || empty($smtp_config['password'])) {
            error_log("SMTP: Credenciais não configuradas. Tentando usar a função mail() padrão.");
            $mail->isMail();
            $default_from_email = $smtp_config['from_email'] ?: ("nao-responda@" . parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST));
            $mail->setFrom($default_from_email, $smtp_config['from_name']);
        } else {
            $mail->isSMTP();
            $mail->Host = $smtp_config['host'];
            $mail->Port = $smtp_config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_config['username'];
            $mail->Password = $smtp_config['password'];
            
            // SMTPOptions para aceitar certificados autoassinados (cuidado em produção)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            if ($smtp_config['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtp_config['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }
            // CORREÇÃO: Usar o username como 'From' address para evitar "Sender address rejected"
            $mail->setFrom($smtp_config['username'], $smtp_config['from_name']);
            error_log("SMTP: Usando configurações: Host=" . $smtp_config['host'] . ", User=" . $smtp_config['username'] . ", Port=" . $smtp_config['port'] . ", Enc=" . $smtp_config['encryption']);
        }

        $mail->CharSet = 'UTF-8';
        $mail->addAddress($to_email, $customer_name);
        $mail->Subject = $email_subject;
        $mail->isHTML(true);

        // Substituições de placeholders globais
        $html_body = str_replace(
            ['{CLIENT_NAME}', '{CLIENT_EMAIL}', '{MEMBER_AREA_PASSWORD}', '{MEMBER_AREA_LOGIN_URL}'],
            [
                htmlspecialchars($customer_name ?? ''), // Adicionado null coalescing para evitar deprecation warning
                htmlspecialchars($to_email ?? ''),       // Adicionado null coalescing para evitar deprecation warning
                htmlspecialchars($member_area_password ?? 'Não aplicável'),
                htmlspecialchars($member_area_login_url ?? '#')
            ],
            $email_html_template
        );

        // Processar blocos de produtos dentro do template
        $products_html_blocks = [];
        $product_loop_start_tag = '<!-- LOOP_PRODUCTS_START -->';
        $product_loop_end_tag = '<!-- LOOP_PRODUCTS_END -->';

        if (strpos($html_body, $product_loop_start_tag) !== false && strpos($html_body, $product_loop_end_tag) !== false) {
            $loop_template = substr(
                $html_body,
                strpos($html_body, $product_loop_start_tag) + strlen($product_loop_start_tag),
                strpos($html_body, $product_loop_end_tag) - (strpos($html_body, $product_loop_start_tag) + strlen($product_loop_start_tag))
            );

            foreach ($processed_products_for_email as $product) {
                $current_product_block = $loop_template;
                $current_product_block = str_replace('{PRODUCT_NAME}', htmlspecialchars($product['product_name']), $current_product_block);

                // Handle conditional content based on product type
                $product_type_markers = [
                    'link'        => ['<!-- IF_PRODUCT_TYPE_LINK -->', '<!-- END_IF_PRODUCT_TYPE_LINK -->'],
                    'pdf'         => ['<!-- IF_PRODUCT_TYPE_PDF -->', '<!-- END_IF_PRODUCT_TYPE_PDF -->'],
                    'area_membros' => ['<!-- IF_PRODUCT_TYPE_MEMBER_AREA -->', '<!-- END_IF_PRODUCT_TYPE_MEMBER_AREA -->']
                ];

                // Passo 1: Processar blocos condicionais (IFs)
                foreach ($product_type_markers as $type => $markers) {
                    $start = strpos($current_product_block, $markers[0]);
                    $end = strpos($current_product_block, $markers[1]);

                    if ($start !== false && $end !== false) {
                        $block_content = substr($current_product_block, $start + strlen($markers[0]), $end - ($start + strlen($markers[0])));
                        if ($product['content_type'] === $type) {
                            // MANTÉM O BLOCO

                            // Substituições específicas da área de membros (se estiverem dentro do bloco)
                            $block_content = str_replace('{MEMBER_AREA_PASSWORD}', htmlspecialchars($member_area_password ?? ''), $block_content);
                            $block_content = str_replace('{MEMBER_AREA_LOGIN_URL}', htmlspecialchars($member_area_login_url ?? ''), $block_content);
                            
                            $current_product_block = str_replace($markers[0] . $block_content . $markers[1], $block_content, $current_product_block);
                        } else {
                            // Remove this block (tipo não corresponde)
                            $current_product_block = str_replace($markers[0] . $block_content . $markers[1], '', $current_product_block);
                        }
                    }
                }
                
                // Passo 2: Substituir placeholders de conteúdo (link, etc.) DEPOIS de processar os blocos IF
                // Isso permite que {PRODUCT_LINK} seja usado dentro ou fora dos blocos IF
                
                $product_link_value = ''; // Inicia vazio
                if ($product['content_type'] === 'link' && !empty($product['content_value'])) {
                    $product_link_value = $product['content_value'];
                }
                
                // CORREÇÃO: Não usar htmlspecialchars em URLs para o {PRODUCT_LINK}
                // $product_link_value é o link real apenas se o tipo for 'link'.
                $current_product_block = str_replace('{PRODUCT_LINK}', $product_link_value, $current_product_block);

                // Substituições de área de membros (caso estejam fora do bloco IF)
                $current_product_block = str_replace('{MEMBER_AREA_PASSWORD}', htmlspecialchars($member_area_password ?? 'Não aplicável'), $current_product_block);
                $current_product_block = str_replace('{MEMBER_AREA_LOGIN_URL}', htmlspecialchars($member_area_login_url ?? '#'), $current_product_block);

                
                $products_html_blocks[] = $current_product_block;
            }
            $html_body = str_replace($product_loop_start_tag . $loop_template . $product_loop_end_tag, implode('', $products_html_blocks), $html_body);
        }

        $mail->Body = $html_body;
        $mail->AltBody = strip_tags($html_body); // Fallback para clientes de e-mail que não suportam HTML

        // Adicionar anexos PDF
        foreach ($processed_products_for_email as $product) {
            if ($product['content_type'] === 'pdf' && file_exists($product['content_value'])) {
                $mail->addAttachment($product['content_value'], basename($product['content_value']));
            }
        }
        
        $mail->send();
        error_log("SUCESSO DE ENTREGA: E-mail consolidado enviado para " . $to_email);
        return true;
    } catch (Exception $e) {
        error_log("FALHA DE ENTREGA (E-mail): O e-mail para " . $to_email . " não pôde ser enviado. Erro: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
        return false;
    }
}


try {
    require_once __DIR__ . '/../config/config.php';
    error_log("API: config.php carregado com sucesso.");

    $action = $_GET['action'] ?? '';
    if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $action;
    }

    // Verificação de segurança: Apenas usuários LOGADOS podem acessar esta API.
    // Exceções: record_checkout_activity (checkout), validate_coupon (checkout - valida cupom)
    if ((!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['id'])) && !in_array($action, ['record_checkout_activity', 'validate_coupon'])) {
        http_response_code(403);
        ob_clean();
        echo json_encode(['error' => 'Acesso não autorizado']);
        exit;
    }

    $usuario_id_logado = $_SESSION['id'] ?? 0;

    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log("API: Ação recebida: " . $action);
    }

    // --- Heartbeat sessão única: verificação leve para o frontend detectar logout em outro dispositivo ---
    if ($action === 'check_session') {
        ob_clean();
        echo json_encode(['success' => true, 'valid' => true]);
        exit;
    }

    // --- Validar cupom (checkout público - sem login) ---
    if ($action === 'validate_coupon') {
        $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
        $codigo = trim($input['codigo'] ?? $input['code'] ?? '');
        $produto_id = (int)($input['produto_id'] ?? $input['product_id'] ?? 0);
        $valor_total = (float)($input['valor_total'] ?? $input['transaction_amount'] ?? 0);
        if (empty($codigo) || $produto_id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'valid' => false, 'error' => 'Dados inválidos']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT usuario_id FROM produtos WHERE id = ?");
            $stmt->execute([$produto_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                ob_clean();
                echo json_encode(['success' => false, 'valid' => false, 'error' => 'Produto não encontrado']);
                exit;
            }
            $result = validarCupom($codigo, $produto_id, $valor_total, $row['usuario_id']);
            ob_clean();
            echo json_encode([
                'success' => true,
                'valid' => $result['valid'],
                'cupom_id' => $result['cupom_id'],
                'valor_desconto' => (float)$result['valor_desconto'],
                'mensagem' => $result['mensagem'],
                'error' => $result['valid'] ? null : $result['mensagem']
            ]);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['success' => false, 'valid' => false, 'error' => 'Erro ao validar cupom']);
        }
        exit;
    }

    // --- PWA Push: chave VAPID pública (para inscrição no cliente) ---
    if ($action === 'get_pwa_vapid_public') {
        try {
            $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'pwa_activated' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['valor'] != '1') {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'PWA não ativado']);
                exit;
            }
            $stmt = $pdo->query("SELECT push_enabled FROM pwa_config ORDER BY id DESC LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$config || empty($config['push_enabled'])) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Push não habilitado']);
                exit;
            }
            if (!file_exists(__DIR__ . '/../pwa/api/web_push_helper.php')) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Módulo PWA não encontrado']);
                exit;
            }
            require_once __DIR__ . '/../pwa/api/web_push_helper.php';
            $keys = pwa_get_or_generate_vapid_keys();
            if (!$keys || empty($keys['publicKey'])) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Chaves VAPID indisponíveis']);
                exit;
            }
            ob_clean();
            echo json_encode(['success' => true, 'publicKey' => $keys['publicKey']]);
            exit;
        } catch (Exception $e) {
            error_log("API PWA get_pwa_vapid_public: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao obter chave']);
            exit;
        }
    }

    // --- PWA Push: registrar subscription (usuário logado) ---
    if ($action === 'register_pwa_push' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $subscription = $input['subscription'] ?? null;
        if (!$subscription || empty($subscription['endpoint']) || empty($subscription['keys']['p256dh']) || empty($subscription['keys']['auth'])) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados de inscrição inválidos']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'pwa_activated' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['valor'] != '1') {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'PWA não ativado']);
                exit;
            }
            if (!file_exists(__DIR__ . '/../pwa/api/web_push_helper.php')) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Módulo PWA não encontrado']);
                exit;
            }
            require_once __DIR__ . '/../pwa/api/web_push_helper.php';
            $ok = pwa_register_subscription($usuario_id_logado, $subscription);
            ob_clean();
            echo json_encode($ok ? ['success' => true] : ['success' => false, 'error' => 'Falha ao registrar']);
            exit;
        } catch (Exception $e) {
            error_log("API PWA register_pwa_push: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao registrar']);
            exit;
        }
    }

    // Atualizar perfil do infoprodutor (Editar Perfil)
    if ($action === 'update_infoprodutor_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_SESSION['tipo'] ?? '') !== 'infoprodutor') {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Acesso não autorizado.']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $nome = trim($input['nome'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha_atual = $input['senha_atual'] ?? '';
        $nova_senha = $input['nova_senha'] ?? '';
        $user_id = (int)($_SESSION['id'] ?? 0);
        if (empty($nome)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Nome é obrigatório.']);
            exit;
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Email inválido.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT id, usuario, nome, senha FROM usuarios WHERE id = ? AND tipo = 'infoprodutor'");
            $stmt->execute([$user_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Usuário não encontrado.']);
                exit;
            }
            $email_changed = ($email !== $usuario['usuario']);
            $senha_changed = !empty($nova_senha);
            $nome_changed = ($nome !== $usuario['nome']);
            if (($email_changed || $senha_changed) && empty($senha_atual)) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Digite sua senha atual para confirmar as alterações.']);
                exit;
            }
            if (!empty($senha_atual) && !password_verify($senha_atual, $usuario['senha'])) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Senha atual incorreta.']);
                exit;
            }
            if ($email_changed) {
                $chk = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
                $chk->execute([$email, $usuario['id']]);
                if ($chk->fetch()) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Este email já está em uso.']);
                    exit;
                }
            }
            if ($senha_changed && strlen($nova_senha) < 6) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres.']);
                exit;
            }
            $updates = [];
            $params = [];
            if ($nome_changed) { $updates[] = "nome = ?"; $params[] = $nome; }
            if ($email_changed) { $updates[] = "usuario = ?"; $params[] = $email; }
            if ($senha_changed) { $updates[] = "senha = ?"; $params[] = password_hash($nova_senha, PASSWORD_DEFAULT); }
            if (empty($updates)) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Nenhuma alteração.', 'nome' => $nome]);
                exit;
            }
            $params[] = $usuario['id'];
            $pdo->prepare("UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id = ?")->execute($params);
            if ($nome_changed) $_SESSION['nome'] = $nome;
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso!', 'nome' => $nome, 'email_changed' => $email_changed]);
            exit;
        } catch (PDOException $e) {
            error_log("API update_infoprodutor_profile: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar perfil.']);
            exit;
        }
    }

    // Ação para obter dados do dashboard do usuário
    if ($action == 'get_dashboard_data') {
        $response = [
            'kpis' => [],
            'chart' => []
        ];

        $period = $_GET['period'] ?? 'today';
        $date_filter_sql = '';
        $chart_labels = [];
        $chart_data_template = [];
        $chart_group_by_clause = '';

        // O fuso horário da sessão do MySQL já é definido em config.php
        // $pdo->exec("SET time_zone = 'America/Sao_Paulo';"); // Removido para evitar redundância e erro "Unknown or incorrect time zone" se DB não tiver tabelas de TZ

        switch ($period) {
            case 'today':
                $date_filter_sql = "AND DATE(v.data_venda) = CURDATE()";
                $chart_group_by_clause = "DATE_FORMAT(v.data_venda, '%Y-%m-%d %H:00')"; // Agrupa por hora
                for ($i = 0; $i < 24; $i++) {
                    $hour_label = sprintf('%02d:00', $i);
                    $date_hour_key = date('Y-m-d') . ' ' . $hour_label; // Chave com 'YYYY-M-D HH:00'
                    $chart_labels[] = $hour_label;
                    $chart_data_template[$date_hour_key] = 0;
                }
                break;
            case 'yesterday':
                $date_filter_sql = "AND DATE(v.data_venda) = CURDATE() - INTERVAL 1 DAY";
                $chart_group_by_clause = "DATE_FORMAT(v.data_venda, '%Y-%m-%d %H:00')"; // Agrupa por hora
                $yesterday_date = date('Y-m-d', strtotime('-1 day'));
                for ($i = 0; $i < 24; $i++) {
                    $hour_label = sprintf('%02d:00', $i);
                    $date_hour_key = $yesterday_date . ' ' . $hour_label; // Chave com 'YYYY-M-D HH:00'
                    $chart_labels[] = $hour_label;
                    $chart_data_template[$date_hour_key] = 0;
                }
                break;
            case '7days':
                $date_filter_sql = "AND v.data_venda >= CURDATE() - INTERVAL 6 DAY";
                $chart_group_by_clause = "DATE_FORMAT(v.data_venda, '%Y-%m-%d')";
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $chart_labels[] = date('d/m', strtotime($date));
                    $chart_data_template[$date] = 0;
                }
                break;
            case 'month':
                $date_filter_sql = "AND MONTH(v.data_venda) = MONTH(CURDATE()) AND YEAR(v.data_venda) = YEAR(CURDATE())";
                $chart_group_by_clause = "DATE_FORMAT(v.data_venda, '%Y-%m-%d')";
                $days_in_month = date('t'); // Number of days in the current month
                $first_day_of_month = date('Y-m-01');
                for ($i = 0; $i < $days_in_month; $i++) {
                    $date = date('Y-m-d', strtotime("+$i days", strtotime($first_day_of_month)));
                    $chart_labels[] = date('d/m', strtotime($date));
                    $chart_data_template[$date] = 0;
                }
                break;
            case 'year':
                $date_filter_sql = "AND YEAR(v.data_venda) = YEAR(CURDATE())";
                $chart_group_by_clause = "DATE_FORMAT(v.data_venda, '%Y-%m')";
                // Gera os rótulos para os últimos 12 meses, incluindo o atual
                for ($i = 11; $i >= 0; $i--) { // Começa 11 meses atrás até o mês atual
                    $month_ts = strtotime("-$i months", strtotime(date('Y-m-01')));
                    $month_key = date('Y-m', $month_ts);
                    $chart_labels[] = date('m/Y', $month_ts);
                    $chart_data_template[$month_key] = 0;
                }
                // O array chart_labels e chart_data_template já está na ordem correta (mais antigo para mais novo)
                break;
            case 'all': // For 'all', chart should still show recent data (e.g., last 30 days), as lifetime is in KPI
            default:
                $date_filter_sql = "AND v.data_venda >= CURDATE() - INTERVAL 29 DAY";
                $chart_group_by_clause = "DATE_FORMAT(v.data_venda, '%Y-%m-%d')";
                for ($i = 29; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $chart_labels[] = date('d/m', strtotime($date));
                    $chart_data_template[$date] = 0;
                }
                break;
        }

        // --- Geração de KPIs ---
        $query_base = "
            SELECT 
                SUM(CASE WHEN v.status_pagamento = 'approved' THEN v.valor ELSE 0 END) AS vendas_totais,
                COUNT(CASE WHEN v.status_pagamento = 'approved' THEN v.id ELSE NULL END) AS quantidade_vendas,
                SUM(CASE WHEN v.status_pagamento = 'refunded' THEN v.valor ELSE 0 END) AS reembolsos,
                SUM(CASE WHEN v.status_pagamento = 'charged_back' THEN v.valor ELSE 0 END) AS chargebacks,
                -- MODIFICADO: Inclui 'cancelled' e 'info_filled' nos carrinhos abandonados gerais
                COUNT(CASE WHEN v.status_pagamento IN ('pending', 'in_process', 'cancelled', 'info_filled') THEN v.id ELSE NULL END) AS abandono_carrinho,
                -- NOVO: Vendas Pendentes (agora inclui 'cancelled')
                SUM(CASE WHEN v.status_pagamento IN ('pending', 'in_process', 'cancelled') THEN v.valor ELSE 0 END) AS vendas_pendentes_valor,
                COUNT(CASE WHEN v.status_pagamento IN ('pending', 'in_process', 'cancelled') THEN v.id ELSE NULL END) AS vendas_pendentes_quantidade,
                SUM(CASE WHEN v.metodo_pagamento = 'Pix' AND v.status_pagamento = 'approved' THEN v.valor ELSE 0 END) AS pix_vendas_valor,
                COUNT(CASE WHEN v.metodo_pagamento = 'Pix' AND v.status_pagamento = 'approved' THEN v.id ELSE NULL END) AS pix_vendas_count,
                COUNT(CASE WHEN v.metodo_pagamento = 'Pix' THEN v.id ELSE NULL END) AS pix_iniciadas_count,
                SUM(CASE WHEN v.metodo_pagamento = 'Boleto' AND v.status_pagamento = 'approved' THEN v.valor ELSE 0 END) AS boleto_vendas_valor,
                COUNT(CASE WHEN v.metodo_pagamento = 'Boleto' AND v.status_pagamento = 'approved' THEN v.id ELSE NULL END) AS boleto_vendas_count,
                COUNT(CASE WHEN v.metodo_pagamento = 'Boleto' THEN v.id ELSE NULL END) AS boleto_iniciadas_count,
                SUM(CASE WHEN v.metodo_pagamento = 'Cartão de crédito' AND v.status_pagamento = 'approved' THEN v.valor ELSE 0 END) AS cartao_vendas_valor,
                COUNT(CASE WHEN v.metodo_pagamento = 'Cartão de crédito' AND v.status_pagamento = 'approved' THEN v.id ELSE NULL END) AS cartao_vendas_count,
                COUNT(CASE WHEN v.metodo_pagamento = 'Cartão de crédito' THEN v.id ELSE NULL END) AS cartao_iniciadas_count,
                COUNT(v.id) AS total_iniciadas_count,
                -- NOVO: Adicionado para calcular a taxa de conversão geral excluindo 'info_filled'
                COUNT(CASE WHEN v.status_pagamento NOT IN ('info_filled') THEN v.id ELSE NULL END) AS total_iniciadas_para_conversao_count,
                SUM(CASE WHEN v.status_pagamento = 'approved' THEN v.valor ELSE 0 END) AS total_faturamento_lifetime_current_period
            FROM vendas v
            JOIN produtos p ON v.produto_id = p.id
            WHERE p.usuario_id = :usuario_id
            {$date_filter_sql}
        ";

        $stmt_kpis = $pdo->prepare($query_base);
        $stmt_kpis->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_kpis->execute();
        $kpis_data = $stmt_kpis->fetch(PDO::FETCH_ASSOC);

        $response['kpis']['vendas_totais'] = number_format($kpis_data['vendas_totais'] ?? 0, 2, ',', '.');
        $response['kpis']['quantidade_vendas'] = $kpis_data['quantidade_vendas'] ?? 0;
        $response['kpis']['ticket_medio'] = ($kpis_data['quantidade_vendas'] > 0) ? number_format($kpis_data['vendas_totais'] / $kpis_data['quantidade_vendas'], 2, ',', '.') : '0,00';
        
        $stmt_total_produtos = $pdo->prepare("SELECT COUNT(id) FROM produtos WHERE usuario_id = :usuario_id");
        $stmt_total_produtos->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_total_produtos->execute();
        $response['kpis']['total_produtos'] = $stmt_total_produtos->fetchColumn();

        // NOVO: KPIs para vendas pendentes (incluindo canceladas)
        $response['kpis']['vendas_pendentes_valor'] = number_format($kpis_data['vendas_pendentes_valor'] ?? 0, 2, ',', '.');
        $response['kpis']['vendas_pendentes_quantidade'] = $kpis_data['vendas_pendentes_quantidade'] ?? 0;

        $response['kpis']['abandono_carrinho'] = $kpis_data['abandono_carrinho'] ?? 0;
        $response['kpis']['reembolsos'] = number_format($kpis_data['reembolsos'] ?? 0, 2, ',', '.');
        $response['kpis']['chargebacks'] = number_format($kpis_data['chargebacks'] ?? 0, 2, ',', '.');
        
        // Faturamento Lifetime (sempre o total aprovado, sem filtro de data)
        $stmt_lifetime_faturamento = $pdo->prepare("SELECT SUM(valor) FROM vendas v JOIN produtos p ON v.produto_id = p.id WHERE p.usuario_id = :usuario_id AND v.status_pagamento = 'approved'");
        $stmt_lifetime_faturamento->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_lifetime_faturamento->execute();
        $response['kpis']['total_faturamento_lifetime'] = (float)($stmt_lifetime_faturamento->fetchColumn() ?? 0);

        // Taxas de Conversão
        $total_aprovadas_current_period = $kpis_data['quantidade_vendas'];
        // MODIFICADO: Usa a nova contagem para o denominador da taxa de conversão geral
        $total_iniciadas_current_period_for_conversion = $kpis_data['total_iniciadas_para_conversao_count']; 

        $response['kpis']['taxa_conversao_geral'] = ($total_iniciadas_current_period_for_conversion > 0) ? number_format(($total_aprovadas_current_period / $total_iniciadas_current_period_for_conversion) * 100, 2, ',', '.') . '%' : '0%';

        $response['kpis']['pix_vendas_valor'] = number_format($kpis_data['pix_vendas_valor'] ?? 0, 2, ',', '.');
        $response['kpis']['pix_vendas_percentual'] = ($kpis_data['pix_iniciadas_count'] > 0) ? number_format(($kpis_data['pix_vendas_count'] / $kpis_data['pix_iniciadas_count']) * 100, 2, ',', '.') . '%' : '0%';

        $response['kpis']['boleto_vendas_valor'] = number_format($kpis_data['boleto_vendas_valor'] ?? 0, 2, ',', '.');
        $response['kpis']['boleto_vendas_percentual'] = ($kpis_data['boleto_iniciadas_count'] > 0) ? number_format(($kpis_data['boleto_vendas_count'] / $kpis_data['boleto_iniciadas_count']) * 100, 2, ',', '.') . '%' : '0%';
        
        $response['kpis']['cartao_vendas_valor'] = number_format($kpis_data['cartao_vendas_valor'] ?? 0, 2, ',', '.');
        $response['kpis']['cartao_vendas_percentual'] = ($kpis_data['cartao_iniciadas_count'] > 0) ? number_format(($kpis_data['cartao_vendas_count'] / $kpis_data['cartao_iniciadas_count']) * 100, 2, ',', '.') . '%' : '0%';

        // --- Dados do Gráfico ---
        $sql_chart = "
            SELECT {$chart_group_by_clause} as period_label, SUM(v.valor) as total_period
            FROM vendas v
            JOIN produtos p ON v.produto_id = p.id
            WHERE p.usuario_id = :usuario_id AND v.status_pagamento = 'approved'
            {$date_filter_sql}
            GROUP BY period_label ORDER BY period_label ASC
        ";

        $stmt_chart = $pdo->prepare($sql_chart);
        $stmt_chart->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_chart->execute();
        $vendas_chart_data = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($chart_data_template as $period_label => $default_value) {
            $chart_data_template[$period_label] = (float)($vendas_chart_data[$period_label] ?? 0);
        }

        $response['chart']['labels'] = $chart_labels;
        $response['chart']['data'] = array_values($chart_data_template);

        ob_clean(); // Limpa o buffer antes de enviar o JSON
        echo json_encode($response);
        exit;
    }

    // NOVO: Ação para obter dados para a Jornada GatewayPro
    if ($action == 'get_jornada_GatewayPro_data') {
        $response = ['total_faturamento_lifetime' => 0];

        try {
            // Faturamento Lifetime (sempre o total aprovado, sem filtro de data)
            $stmt_lifetime_faturamento = $pdo->prepare("SELECT SUM(valor) FROM vendas v JOIN produtos p ON v.produto_id = p.id WHERE p.usuario_id = :usuario_id AND v.status_pagamento = 'approved'");
            $stmt_lifetime_faturamento->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_lifetime_faturamento->execute();
            $response['total_faturamento_lifetime'] = (float)($stmt_lifetime_faturamento->fetchColumn() ?? 0);

            ob_clean();
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar dados da Jornada GatewayPro (get_jornada_GatewayPro_data): " . $e->getMessage());
            echo json_encode(['error' => 'Erro ao buscar dados da Jornada GatewayPro.']);
            exit;
        }
    }


    // Ações de gerenciamento de vendas (para index.php?pagina=vendas)
    if ($action == 'get_vendas') {
        $status_filter = $_GET['status'] ?? 'all';
        $search_query = $_GET['search'] ?? '';
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // Novos filtros avançados
        $produto_id = $_GET['produto_id'] ?? '';
        $metodo_pagamento = $_GET['metodo_pagamento'] ?? '';
        $data_inicio = $_GET['data_inicio'] ?? '';
        $data_fim = $_GET['data_fim'] ?? '';
        $telefone = $_GET['telefone'] ?? '';
        $valor_min = $_GET['valor_min'] ?? '';
        $valor_max = $_GET['valor_max'] ?? '';

        $where_clauses = ["p.usuario_id = :usuario_id"];
        $params = [':usuario_id' => $usuario_id_logado];

        // MODIFICADO: Lógica para os novos filtros de carrinho abandonado
        if ($status_filter === 'abandoned_all') {
            $where_clauses[] = "v.status_pagamento IN ('pending', 'cancelled', 'info_filled')";
        } elseif ($status_filter === 'info_filled') {
            $where_clauses[] = "v.status_pagamento = 'info_filled'";
        } elseif ($status_filter !== 'all') {
            $where_clauses[] = "v.status_pagamento = :status_filter";
            $params[':status_filter'] = $status_filter;
        }

        // Busca geral (nome, email, telefone, ID)
        if (!empty($search_query)) {
            $where_clauses[] = "(v.comprador_nome LIKE :search_query OR v.comprador_email LIKE :search_query OR v.comprador_telefone LIKE :search_query OR v.id = :search_id)";
            $params[':search_query'] = '%' . $search_query . '%';
            $params[':search_id'] = is_numeric($search_query) ? (int)$search_query : 0;
        }
        
        // Filtro por produto
        if (!empty($produto_id) && is_numeric($produto_id)) {
            $where_clauses[] = "v.produto_id = :produto_id";
            $params[':produto_id'] = (int)$produto_id;
        }
        
        // Filtro por método de pagamento
        if (!empty($metodo_pagamento)) {
            $where_clauses[] = "v.metodo_pagamento = :metodo_pagamento";
            $params[':metodo_pagamento'] = $metodo_pagamento;
        }
        
        // Filtro por data início
        if (!empty($data_inicio)) {
            $where_clauses[] = "DATE(v.data_venda) >= :data_inicio";
            $params[':data_inicio'] = $data_inicio;
        }
        
        // Filtro por data fim
        if (!empty($data_fim)) {
            $where_clauses[] = "DATE(v.data_venda) <= :data_fim";
            $params[':data_fim'] = $data_fim;
        }
        
        // Filtro por telefone
        if (!empty($telefone)) {
            // Remove caracteres não numéricos para busca
            $telefone_limpo = preg_replace('/\D/', '', $telefone);
            $where_clauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(v.comprador_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :telefone";
            $params[':telefone'] = '%' . $telefone_limpo . '%';
        }
        
        // Filtro por valor mínimo
        if (!empty($valor_min) && is_numeric($valor_min)) {
            $where_clauses[] = "v.valor >= :valor_min";
            $params[':valor_min'] = (float)$valor_min;
        }
        
        // Filtro por valor máximo
        if (!empty($valor_max) && is_numeric($valor_max)) {
            $where_clauses[] = "v.valor <= :valor_max";
            $params[':valor_max'] = (float)$valor_max;
        }

        $where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        // Contar total de vendas para paginação
        $stmt_count = $pdo->prepare("SELECT COUNT(v.id) FROM vendas v JOIN produtos p ON v.produto_id = p.id {$where_sql}");
        $stmt_count->execute($params);
        $total_records = $stmt_count->fetchColumn();
        $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

        // Fetch vendas
        $sql_vendas = "
            SELECT 
                v.id, v.valor, v.status_pagamento, v.data_venda, 
                v.comprador_email, v.comprador_nome, v.comprador_cpf, v.comprador_telefone, 
                v.metodo_pagamento, p.nome AS produto_nome, p.tipo_entrega,
                0 AS criado_manualmente
            FROM vendas v
            JOIN produtos p ON v.produto_id = p.id
            {$where_sql}
            ORDER BY v.data_venda DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt_vendas = $pdo->prepare($sql_vendas);
        foreach ($params as $key => $val) {
            $stmt_vendas->bindValue($key, $val);
        }
        $stmt_vendas->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt_vendas->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_vendas->execute();
        $vendas = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar acessos manuais (apenas se não houver filtro de status específico ou se for 'all' ou 'approved' ou 'manual')
        $include_manual = ($status_filter === 'all' || $status_filter === 'approved' || $status_filter === 'manual');
        
        if ($include_manual) {
            $manual_where = ["p.usuario_id = :usuario_id_manual", "aa.criado_manualmente = 1"];
            $manual_params = [':usuario_id_manual' => $usuario_id_logado];
            
            // Aplicar filtros de busca aos acessos manuais
            if (!empty($search_query)) {
                $manual_where[] = "(u.nome LIKE :search_manual OR aa.aluno_email LIKE :search_manual)";
                $manual_params[':search_manual'] = '%' . $search_query . '%';
            }
            
            if (!empty($produto_id) && is_numeric($produto_id)) {
                $manual_where[] = "aa.produto_id = :produto_id_manual";
                $manual_params[':produto_id_manual'] = (int)$produto_id;
            }
            
            if (!empty($data_inicio)) {
                $manual_where[] = "DATE(aa.data_concessao) >= :data_inicio_manual";
                $manual_params[':data_inicio_manual'] = $data_inicio;
            }
            
            if (!empty($data_fim)) {
                $manual_where[] = "DATE(aa.data_concessao) <= :data_fim_manual";
                $manual_params[':data_fim_manual'] = $data_fim;
            }
            
            $manual_where_sql = 'WHERE ' . implode(' AND ', $manual_where);
            
            $sql_manual = "
                SELECT 
                    CONCAT('M', aa.id) AS id,
                    0.00 AS valor,
                    'approved' AS status_pagamento,
                    aa.data_concessao AS data_venda,
                    aa.aluno_email AS comprador_email,
                    COALESCE(u.nome, aa.aluno_email) AS comprador_nome,
                    '' AS comprador_cpf,
                    '' AS comprador_telefone,
                    'Manual' AS metodo_pagamento,
                    p.nome AS produto_nome,
                    p.tipo_entrega,
                    1 AS criado_manualmente
                FROM alunos_acessos aa
                JOIN produtos p ON aa.produto_id = p.id
                LEFT JOIN usuarios u ON u.usuario = aa.aluno_email AND u.tipo = 'usuario'
                {$manual_where_sql}
                ORDER BY aa.data_concessao DESC
            ";
            
            $stmt_manual = $pdo->prepare($sql_manual);
            foreach ($manual_params as $key => $val) {
                $stmt_manual->bindValue($key, $val);
            }
            $stmt_manual->execute();
            $acessos_manuais = $stmt_manual->fetchAll(PDO::FETCH_ASSOC);
            
            // Mesclar vendas com acessos manuais e ordenar por data
            $vendas = array_merge($vendas, $acessos_manuais);
            usort($vendas, function($a, $b) {
                return strtotime($b['data_venda']) - strtotime($a['data_venda']);
            });
            
            // Aplicar paginação após merge
            $vendas = array_slice($vendas, 0, $limit);
        }

        // Fetch métricas para os cards
        $stmt_metrics = $pdo->prepare("
            SELECT
                COUNT(v.id) AS all_count,
                COUNT(CASE WHEN v.status_pagamento = 'approved' THEN v.id ELSE NULL END) AS approved_count,
                COUNT(CASE WHEN v.status_pagamento IN ('pending', 'cancelled', 'info_filled') THEN v.id ELSE NULL END) AS abandoned_all_count, -- NOVO: Todos os abandonados
                COUNT(CASE WHEN v.status_pagamento = 'info_filled' THEN v.id ELSE NULL END) AS info_filled_count, -- NOVO: Abandonados com info preenchida
                COUNT(CASE WHEN v.status_pagamento = 'refunded' THEN v.id ELSE NULL END) AS refunded_count,
                COUNT(CASE WHEN v.status_pagamento = 'charged_back' THEN v.id ELSE NULL END) AS charged_back_count
            FROM vendas v
            JOIN produtos p ON v.produto_id = p.id
            WHERE p.usuario_id = :usuario_id
        ");
        $stmt_metrics->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_metrics->execute();
        $metrics_data = $stmt_metrics->fetch(PDO::FETCH_ASSOC);
        
        // Contar acessos manuais
        $stmt_manual_count = $pdo->prepare("
            SELECT COUNT(*) AS manual_count
            FROM alunos_acessos aa
            JOIN produtos p ON aa.produto_id = p.id
            WHERE p.usuario_id = :usuario_id AND aa.criado_manualmente = 1
        ");
        $stmt_manual_count->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_manual_count->execute();
        $manual_count = $stmt_manual_count->fetchColumn();

        ob_clean();
        echo json_encode([
            'vendas' => $vendas,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $total_pages,
                'totalRecords' => $total_records + ($include_manual ? $manual_count : 0)
            ],
            'metrics' => [
                'all' => $metrics_data['all_count'] + $manual_count,
                'approved' => $metrics_data['approved_count'] + $manual_count,
                'abandoned_all' => $metrics_data['abandoned_all_count'], // NOVO
                'info_filled' => $metrics_data['info_filled_count'],   // NOVO
                'refunded' => $metrics_data['refunded_count'],
                'charged_back' => $metrics_data['charged_back_count'],
                'manual' => $manual_count
            ]
        ]);
        exit;
    }

    // Exportar vendas em CSV (Excel)
    if ($action == 'export_vendas_excel') {
        $status_filter = $_GET['status'] ?? 'all';
        $search_query = $_GET['search'] ?? '';
        $produto_id = $_GET['produto_id'] ?? '';
        $metodo_pagamento = $_GET['metodo_pagamento'] ?? '';
        $data_inicio = $_GET['data_inicio'] ?? '';
        $data_fim = $_GET['data_fim'] ?? '';
        $telefone = $_GET['telefone'] ?? '';
        $valor_min = $_GET['valor_min'] ?? '';
        $valor_max = $_GET['valor_max'] ?? '';

        $where_clauses = ["p.usuario_id = :usuario_id"];
        $params = [':usuario_id' => $usuario_id_logado];
        if ($status_filter === 'abandoned_all') {
            $where_clauses[] = "v.status_pagamento IN ('pending', 'cancelled', 'info_filled')";
        } elseif ($status_filter === 'info_filled') {
            $where_clauses[] = "v.status_pagamento = 'info_filled'";
        } elseif ($status_filter !== 'all') {
            $where_clauses[] = "v.status_pagamento = :status_filter";
            $params[':status_filter'] = $status_filter;
        }
        if (!empty($search_query)) {
            $where_clauses[] = "(v.comprador_nome LIKE :search_query OR v.comprador_email LIKE :search_query OR v.comprador_telefone LIKE :search_query OR v.id = :search_id)";
            $params[':search_query'] = '%' . $search_query . '%';
            $params[':search_id'] = is_numeric($search_query) ? (int)$search_query : 0;
        }
        if (!empty($produto_id) && is_numeric($produto_id)) { $where_clauses[] = "v.produto_id = :produto_id"; $params[':produto_id'] = (int)$produto_id; }
        if (!empty($metodo_pagamento)) { $where_clauses[] = "v.metodo_pagamento = :metodo_pagamento"; $params[':metodo_pagamento'] = $metodo_pagamento; }
        if (!empty($data_inicio)) { $where_clauses[] = "DATE(v.data_venda) >= :data_inicio"; $params[':data_inicio'] = $data_inicio; }
        if (!empty($data_fim)) { $where_clauses[] = "DATE(v.data_venda) <= :data_fim"; $params[':data_fim'] = $data_fim; }
        if (!empty($telefone)) {
            $telefone_limpo = preg_replace('/\D/', '', $telefone);
            $where_clauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(v.comprador_telefone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :telefone";
            $params[':telefone'] = '%' . $telefone_limpo . '%';
        }
        if (!empty($valor_min) && is_numeric($valor_min)) { $where_clauses[] = "v.valor >= :valor_min"; $params[':valor_min'] = (float)$valor_min; }
        if (!empty($valor_max) && is_numeric($valor_max)) { $where_clauses[] = "v.valor <= :valor_max"; $params[':valor_max'] = (float)$valor_max; }
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        $sql_export = "
            SELECT v.id, v.comprador_nome AS Cliente, v.comprador_email AS Email, v.comprador_telefone AS Telefone,
                   p.nome AS Produto, v.valor AS Valor, v.metodo_pagamento AS Metodo,
                   v.data_venda AS Data, v.status_pagamento AS Status
            FROM vendas v
            JOIN produtos p ON v.produto_id = p.id
            {$where_sql}
            ORDER BY v.data_venda DESC
        ";
        $stmt_export = $pdo->prepare($sql_export);
        foreach ($params as $k => $v) $stmt_export->bindValue($k, $v);
        $stmt_export->execute();
        $rows = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

        // Incluir acessos manuais se status all/approved/manual
        $include_manual = ($status_filter === 'all' || $status_filter === 'approved' || $status_filter === 'manual');
        if ($include_manual) {
            $manual_where = ["p.usuario_id = :uid", "aa.criado_manualmente = 1"];
            $manual_params = [':uid' => $usuario_id_logado];
            if (!empty($search_query)) { $manual_where[] = "(u.nome LIKE :sm OR aa.aluno_email LIKE :sm)"; $manual_params[':sm'] = '%' . $search_query . '%'; }
            if (!empty($produto_id) && is_numeric($produto_id)) { $manual_where[] = "aa.produto_id = :pid"; $manual_params[':pid'] = (int)$produto_id; }
            if (!empty($data_inicio)) { $manual_where[] = "DATE(aa.data_concessao) >= :di"; $manual_params[':di'] = $data_inicio; }
            if (!empty($data_fim)) { $manual_where[] = "DATE(aa.data_concessao) <= :df"; $manual_params[':df'] = $data_fim; }
            $mw_sql = 'WHERE ' . implode(' AND ', $manual_where);
            $sql_manual = "SELECT CONCAT('M', aa.id) AS id, COALESCE(u.nome, aa.aluno_email) AS Cliente, aa.aluno_email AS Email, '' AS Telefone, p.nome AS Produto, 0 AS Valor, 'Manual' AS Metodo, aa.data_concessao AS Data, 'approved' AS Status FROM alunos_acessos aa JOIN produtos p ON aa.produto_id = p.id LEFT JOIN usuarios u ON u.usuario = aa.aluno_email AND u.tipo = 'usuario' {$mw_sql} ORDER BY aa.data_concessao DESC";
            $stmt_m = $pdo->prepare($sql_manual);
            foreach ($manual_params as $k => $v) $stmt_m->bindValue($k, $v);
            $stmt_m->execute();
            $manuais = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
            $rows = array_merge($rows, $manuais);
            usort($rows, function($a, $b) { return strtotime($b['Data']) - strtotime($a['Data']); });
        }

        ob_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="vendas_' . date('Y-m-d_H-i') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        if (count($rows) > 0) {
            fputcsv($out, array_keys($rows[0]), ';');
            foreach ($rows as $r) {
                $r['Valor'] = number_format((float)$r['Valor'], 2, ',', '.');
                $r['Data'] = date('d/m/Y H:i', strtotime($r['Data']));
                fputcsv($out, $r, ';');
            }
        } else {
            fputcsv($out, ['Cliente','Email','Telefone','Produto','Valor','Metodo','Data','Status'], ';');
        }
        fclose($out);
        exit;
    }
    
    // NOVO: Ação para reenviar e-mail de acesso
    if ($action == 'resend_access_email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $venda_id = $input['venda_id'] ?? null;

        if (!$venda_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID da venda é obrigatório.']);
            exit;
        }

        try {
            // 1. Obter detalhes da venda principal e da sessão de checkout
            $stmt_main_sale = $pdo->prepare("
                SELECT 
                    v.id, v.produto_id, v.comprador_email, v.comprador_nome, v.checkout_session_uuid, v.metodo_pagamento, v.status_pagamento, 
                    p.usuario_id, p.nome AS produto_nome, p.checkout_config
                FROM vendas v
                JOIN produtos p ON v.produto_id = p.id
                WHERE v.id = :venda_id AND p.usuario_id = :usuario_id
            ");
            $stmt_main_sale->bindParam(':venda_id', $venda_id, PDO::PARAM_INT);
            $stmt_main_sale->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_main_sale->execute();
            $main_sale_details = $stmt_main_sale->fetch(PDO::FETCH_ASSOC);

            if (!$main_sale_details) {
                http_response_code(404);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Venda não encontrada ou não pertence a você.']);
                exit;
            }

            // Apenas reenviar se o status for aprovado
            if ($main_sale_details['status_pagamento'] !== 'approved') {
                http_response_code(400);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'O reenvio de acesso é permitido apenas para vendas aprovadas.']);
                exit;
            }

            $customer_email = $main_sale_details['comprador_email'];
            $customer_name = $main_sale_details['comprador_nome'];
            $checkout_session_uuid = $main_sale_details['checkout_session_uuid'];

            // 2. Recuperar TODOS os produtos associados a esta checkout_session_uuid
            $stmt_all_products_in_session = $pdo->prepare("
                SELECT 
                    v.id, v.produto_id, v.valor, p.nome AS produto_nome, 
                    p.tipo_entrega, p.conteudo_entrega
                FROM vendas v
                JOIN produtos p ON v.produto_id = p.id
                WHERE v.checkout_session_uuid = :checkout_session_uuid AND p.usuario_id = :usuario_id
            ");
            $stmt_all_products_in_session->bindParam(':checkout_session_uuid', $checkout_session_uuid, PDO::PARAM_STR);
            $stmt_all_products_in_session->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_all_products_in_session->execute();
            $all_products_for_delivery = $stmt_all_products_in_session->fetchAll(PDO::FETCH_ASSOC);

            if (empty($all_products_for_delivery)) {
                http_response_code(404);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Nenhum produto encontrado para esta sessão de checkout.']);
                exit;
            }

            $processed_products_for_email = [];
            $member_area_password_for_delivery = null;
            $member_area_login_url = null;

            // Fetch email template, subject and member area login URL from global config
            $stmt_email_config = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('email_template_delivery_subject', 'email_template_delivery_html', 'member_area_login_url')");
            $email_configs = $stmt_email_config->fetchAll(PDO::FETCH_KEY_PAIR);
            $email_subject = $email_configs['email_template_delivery_subject'] ?? 'Seus Acessos Foram Liberados!';
            $email_html_template = $email_configs['email_template_delivery_html'] ?? '';
            $member_area_login_url_config = $email_configs['member_area_login_url'] ?? '';

            // Get existing password or generate new for member area products
            $stmt_get_user_pass = $pdo->prepare("SELECT senha FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
            $stmt_get_user_pass->execute([$customer_email]);
            $existing_user_password_hash = $stmt_get_user_pass->fetchColumn();

            // In an ideal world, we'd recover the *original* plaintext password.
            // But since we only store hashes, we must either generate a new one
            // or inform the user to use "forgot password". For re-send access,
            // generating a new temporary one is more user-friendly.
            $new_password_for_delivery = bin2hex(random_bytes(8));
            $hashed_new_password = password_hash($new_password_for_delivery, PASSWORD_DEFAULT);

            // Update user's password in `usuarios` table, or create if not exists
            $stmt_check_user = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
            $stmt_check_user->execute([$customer_email]);
            $existing_user = $stmt_check_user->fetch(PDO::FETCH_ASSOC);

            if ($existing_user) {
                $stmt_update_user = $pdo->prepare("UPDATE usuarios SET senha = ?, nome = ? WHERE id = ?");
                $stmt_update_user->execute([$hashed_new_password, $customer_name, $existing_user['id']]);
                error_log("API: Reenviar Acesso (Área de Membros): Senha atualizada para usuário existente: " . $customer_email);
            } else {
                $stmt_insert_user = $pdo->prepare("INSERT INTO usuarios (usuario, nome, senha, tipo) VALUES (?, ?, ?, 'usuario')");
                $stmt_insert_user->execute([$customer_email, $customer_name, $hashed_new_password]);
                error_log("API: Reenviar Acesso (Área de Membros): Novo usuário criado: " . $customer_email);
            }
            $member_area_password_for_delivery = $new_password_for_delivery;
            $member_area_login_url = $member_area_login_url_config;

            // Process each product for delivery content
            foreach ($all_products_for_delivery as $product) {
                $delivery_result = process_single_product_delivery($product, $customer_email); // This will re-grant access if it's an area_membros product

                if ($delivery_result['success']) {
                    $processed_products_for_email[] = $delivery_result;
                } else {
                    error_log("API: Reenviar Acesso: Entrega falhou para o produto '{$product['produto_nome']}': {$delivery_result['message']}");
                }
            }

            // Send consolidated delivery email
            if (!empty($processed_products_for_email)) {
                $email_sent = send_delivery_email_consolidated(
                    $customer_email,
                    $customer_name,
                    $processed_products_for_email,
                    $member_area_password_for_delivery, // This will be passed to the template
                    $member_area_login_url,
                    $email_subject,
                    $email_html_template
                );

                if ($email_sent) {
                    // Mark as email sent to prevent duplicate automatic emails, but for manual re-send, we don't change this flag.
                    // This is just to ensure no new emails are sent by automatic webhooks for this specific session.
                    // $stmt_mark_sent = $pdo->prepare("UPDATE vendas SET email_entrega_enviado = 1 WHERE checkout_session_uuid = ?");
                    // $stmt_mark_sent->execute([$checkout_session_uuid]);
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'E-mail de acesso reenviado com sucesso!']);
                } else {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Falha ao reenviar e-mail. Verifique as configurações SMTP.']);
                }
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Nenhum conteúdo de produto para reenviar no e-mail.']);
            }

        } catch (Exception $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao reenviar e-mail de acesso: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao reenviar acesso: ' . $e->getMessage()]);
        }
        exit;
    }

    // NOVO: Ação para registrar atividade no checkout (carrinho abandonado - informações preenchidas)
    if ($action == 'record_checkout_activity' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $checkout_session_uuid = $input['checkout_session_uuid'] ?? null;
        $product_id = $input['product_id'] ?? null;
        $comprador_nome = $input['comprador_nome'] ?? null;
        $comprador_email = $input['comprador_email'] ?? null;
        $comprador_telefone = $input['comprador_telefone'] ?? null;
        $comprador_cpf = $input['comprador_cpf'] ?? null;
        $utm_parameters = $input['utm_parameters'] ?? [];

        error_log("API: record_checkout_activity - UUID: " . $checkout_session_uuid . ", Product ID: " . $product_id . ", Email: " . $comprador_email);

        if (!$checkout_session_uuid || !$product_id || !$comprador_email || !$comprador_nome) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados de preenchimento do checkout incompletos.']);
            exit;
        }

        try {
            // 1. Verificar se o produto existe (checkout público - comprador não precisa estar logado)
            $stmt_check_product = $pdo->prepare("SELECT id, preco FROM produtos WHERE id = :product_id");
            $stmt_check_product->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_check_product->execute();
            $product_info = $stmt_check_product->fetch(PDO::FETCH_ASSOC);

            if (!$product_info) {
                http_response_code(404);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado.']);
                exit;
            }
            // MODIFICADO: Remove a linha que pega o preço, pois o valor agora será 0.00
            // $product_price = $product_info['preco']; // Get the actual price of the main product

            // 2. Tentar encontrar um registro de venda para esta sessão e produto
            $stmt_find_sale = $pdo->prepare("SELECT id, status_pagamento FROM vendas WHERE checkout_session_uuid = :checkout_session_uuid AND produto_id = :product_id");
            $stmt_find_sale->bindParam(':checkout_session_uuid', $checkout_session_uuid, PDO::PARAM_STR);
            $stmt_find_sale->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_find_sale->execute();
            $existing_sale = $stmt_find_sale->fetch(PDO::FETCH_ASSOC);

            // Define UTM parameters
            $utm_source = $utm_parameters['utm_source'] ?? null;
            $utm_campaign = $utm_parameters['utm_campaign'] ?? null;
            $utm_medium = $utm_parameters['utm_medium'] ?? null;
            $utm_content = $utm_parameters['utm_content'] ?? null;
            $utm_term = $utm_parameters['utm_term'] ?? null;
            $src = $utm_parameters['src'] ?? null;
            $sck = $utm_parameters['sck'] ?? null;


            if ($existing_sale) {
                // Se já existe um registro, verificar o status atual para evitar sobrescrever com "info_filled"
                // se já estiver em um estado de pagamento mais avançado (pending, approved, etc.)
                $current_status = $existing_sale['status_pagamento'];
                // Only update if current status is 'info_filled', 'pending', 'cancelled' or empty.
                // Do NOT downgrade an 'approved' status.
                // $allowed_to_update_status_to_info_filled = in_array($current_status, [null, '', 'info_filled', 'pending', 'cancelled']); // Original logic, slightly redundant with CASE

                $sql_update = "UPDATE vendas SET 
                                    comprador_nome = :comprador_nome, 
                                    comprador_email = :comprador_email, 
                                    comprador_telefone = :comprador_telefone, 
                                    comprador_cpf = :comprador_cpf,
                                    -- Apenas atualiza para 'info_filled' se o status atual permitir.
                                    -- Caso contrário, mantém o status existente (ex: 'approved', 'in_process').
                                    status_pagamento = CASE 
                                                        WHEN status_pagamento IN ('approved', 'in_process') THEN status_pagamento 
                                                        ELSE 'info_filled' 
                                                       END,
                                    -- MODIFICADO: Sempre define o valor como 0.00 se não for 'approved' ou 'in_process'
                                    valor = CASE 
                                            WHEN status_pagamento IN ('approved', 'in_process') THEN valor 
                                            ELSE 0.00 
                                            END,
                                    utm_source = :utm_source,
                                    utm_campaign = :utm_campaign,
                                    utm_medium = :utm_medium,
                                    utm_content = :utm_content,
                                    utm_term = :utm_term,
                                    src = :src,
                                    sck = :sck
                                WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':comprador_nome', $comprador_nome, PDO::PARAM_STR);
                $stmt_update->bindParam(':comprador_email', $comprador_email, PDO::PARAM_STR);
                $stmt_update->bindParam(':comprador_telefone', $comprador_telefone, PDO::PARAM_STR);
                $stmt_update->bindParam(':comprador_cpf', $comprador_cpf, PDO::PARAM_STR);
                // MODIFICADO: Removido o bind para :product_price_update, pois o valor é fixo em 0.00
                $stmt_update->bindParam(':utm_source', $utm_source, PDO::PARAM_STR);
                $stmt_update->bindParam(':utm_campaign', $utm_campaign, PDO::PARAM_STR);
                $stmt_update->bindParam(':utm_medium', $utm_medium, PDO::PARAM_STR);
                $stmt_update->bindParam(':utm_content', $utm_content, PDO::PARAM_STR);
                $stmt_update->bindParam(':utm_term', $utm_term, PDO::PARAM_STR);
                $stmt_update->bindParam(':src', $src, PDO::PARAM_STR);
                $stmt_update->bindParam(':sck', $sck, PDO::PARAM_STR);
                $stmt_update->bindParam(':id', $existing_sale['id'], PDO::PARAM_INT);
                $stmt_update->execute();
                error_log("API: record_checkout_activity - Venda existente atualizada (ID " . $existing_sale['id'] . "). Novo status: " . $current_status . " -> " . (in_array($current_status, ['approved', 'in_process']) ? $current_status : 'info_filled') . ", Valor: 0.00");
                
            } else {
                // community_id do produto (multi-tenant)
                $community_id = 1;
                try {
                    $st_c = $pdo->prepare("SELECT COALESCE(community_id, 1) FROM produtos WHERE id = ?");
                    $st_c->execute([$product_id]);
                    $cid_row = $st_c->fetchColumn();
                    if ($cid_row !== false && $cid_row !== null) $community_id = (int)$cid_row;
                } catch (PDOException $e) {}
                // Criar um novo registro de venda com status 'info_filled'
                $sql_insert = "INSERT INTO vendas (
                                produto_id, community_id, valor, status_pagamento, data_venda, 
                                comprador_email, comprador_nome, comprador_cpf, comprador_telefone, 
                                checkout_session_uuid,
                                utm_source, utm_campaign, utm_medium, utm_content, utm_term, src, sck
                                ) VALUES (
                                :product_id, :community_id, :valor, 'info_filled', NOW(), 
                                :comprador_email, :comprador_nome, :comprador_cpf, :comprador_telefone, 
                                :checkout_session_uuid,
                                :utm_source, :utm_campaign, :utm_medium, :utm_content, :utm_term, :src, :sck
                                )";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $stmt_insert->bindParam(':community_id', $community_id, PDO::PARAM_INT);
                // MODIFICADO: O valor para novos carrinhos abandonados é 0.00
                $stmt_insert->bindValue(':valor', 0.00, PDO::PARAM_STR); 
                $stmt_insert->bindParam(':comprador_email', $comprador_email, PDO::PARAM_STR);
                $stmt_insert->bindParam(':comprador_nome', $comprador_nome, PDO::PARAM_STR);
                $stmt_insert->bindParam(':comprador_telefone', $comprador_telefone, PDO::PARAM_STR);
                $stmt_insert->bindParam(':comprador_cpf', $comprador_cpf, PDO::PARAM_STR);
                $stmt_insert->bindParam(':checkout_session_uuid', $checkout_session_uuid, PDO::PARAM_STR);
                $stmt_insert->bindParam(':utm_source', $utm_source, PDO::PARAM_STR);
                $stmt_insert->bindParam(':utm_campaign', $utm_campaign, PDO::PARAM_STR);
                $stmt_insert->bindParam(':utm_medium', $utm_medium, PDO::PARAM_STR);
                $stmt_insert->bindParam(':utm_content', $utm_content, PDO::PARAM_STR);
                $stmt_insert->bindParam(':utm_term', $utm_term, PDO::PARAM_STR);
                $stmt_insert->bindParam(':src', $src, PDO::PARAM_STR);
                $stmt_insert->bindParam(':sck', $sck, PDO::PARAM_STR);
                $stmt_insert->execute();
                error_log("API: record_checkout_activity - Nova venda com status 'info_filled' criada: ID " . $pdo->lastInsertId() . ", Valor: 0.00");
            }

            // Recuperação de carrinho: disparar Evolution API (info_filled) se tiver telefone
            $sale_id = isset($existing_sale['id']) ? (int)$existing_sale['id'] : (int)$pdo->lastInsertId();
            if ($sale_id > 0 && !empty($comprador_telefone) && function_exists('process_evolution_messages')) {
                try {
                    require_once __DIR__ . '/../helpers/evolution_helper.php';
                    $stmt_ch = $pdo->prepare("SELECT checkout_hash FROM produtos WHERE id = ?");
                    $stmt_ch->execute([$product_id]);
                    $checkout_hash = $stmt_ch->fetchColumn() ?: '';
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? '';
                    $link_checkout = ($checkout_hash && $host) ? ($protocol . '://' . $host . '/checkout?p=' . $checkout_hash) : '';
                    $sale_data = [
                        'id' => $sale_id,
                        'produto_id' => $product_id,
                        'comprador_nome' => $comprador_nome,
                        'comprador_email' => $comprador_email,
                        'comprador_telefone' => $comprador_telefone,
                        'comprador_cpf' => $comprador_cpf ?? '',
                        'valor' => 0.00,
                        'transacao_id' => '',
                        'data_venda' => date('Y-m-d H:i:s'),
                        'checkout_hash' => $checkout_hash,
                        'link_checkout' => $link_checkout
                    ];
                    process_evolution_messages($pdo, $sale_data, 'info_filled');
                    error_log("API: record_checkout_activity - Evolution disparada para info_filled (venda ID: $sale_id)");
                } catch (Throwable $e) {
                    error_log("API: record_checkout_activity - Erro ao disparar Evolution: " . $e->getMessage());
                }
            }

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Atividade de checkout registrada com sucesso.']);

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro de banco de dados em record_checkout_activity: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao registrar atividade: ' . $e->getMessage()]);
        }
        exit;
    }


    // MODIFICADO: get_member_exclusive_offers — ordem igual à de "Meus Produtos" (products_feed_items)
    if ($action == 'get_member_exclusive_offers') {
        $cliente_email = $_SESSION['usuario'] ?? null; // Assume que o email do cliente está na sessão

        if (!$cliente_email || !is_string($cliente_email)) {
            http_response_code(401);
            ob_clean();
            echo json_encode(['error' => 'Email do cliente não encontrado na sessão.']);
            exit;
        }

        try {
            // 1. Encontra os IDs dos produtos que o cliente JÁ POSSUI (comparação case-insensitive)
            $stmt_owned_product_ids = $pdo->prepare("
                SELECT DISTINCT produto_id
                FROM alunos_acessos
                WHERE LOWER(TRIM(aluno_email)) = LOWER(TRIM(?))
            ");
            $stmt_owned_product_ids->execute([$cliente_email]);
            $owned_product_ids = $stmt_owned_product_ids->fetchAll(PDO::FETCH_COLUMN);
            
            // Infoprodutor: usuário dono dos produtos que o cliente possui (para usar o feed dele)
            $usuario_id_infoprodutor = null;
            if (!empty($owned_product_ids)) {
                $ph = implode(',', array_fill(0, count($owned_product_ids), '?'));
                $stmt_u = $pdo->prepare("SELECT usuario_id FROM produtos WHERE id IN ($ph) LIMIT 1");
                $stmt_u->execute(array_values($owned_product_ids));
                $row = $stmt_u->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $usuario_id_infoprodutor = (int) $row['usuario_id'];
                }
            }
            // Fallback: se cliente não tem produtos ainda, pegar infoprodutor do primeiro banner (para manter ordem do feed)
            if ($usuario_id_infoprodutor === null) {
                try {
                    $chk_b = $pdo->query("SHOW TABLES LIKE 'banners'");
                    if ($chk_b && $chk_b->rowCount() > 0) {
                        $stmt_b = $pdo->query("SELECT usuario_id FROM banners WHERE is_active = 1 AND show_in_offers_section = 1 LIMIT 1");
                        if ($stmt_b && ($row_b = $stmt_b->fetch(PDO::FETCH_ASSOC))) {
                            $usuario_id_infoprodutor = (int) $row_b['usuario_id'];
                        }
                    }
                } catch (Throwable $e) {
                    error_log("API get_member_exclusive_offers fallback banner: " . $e->getMessage());
                }
            }

            // Ordem do feed (Mesma ordem de "Meus Produtos"): sequência exata para intercalar produtos e banners
            $feed_sequence = [];
            $feed_order_product = [];
            $feed_order_banner = [];
            if ($usuario_id_infoprodutor !== null) {
                $chk_feed = $pdo->query("SHOW TABLES LIKE 'products_feed_items'");
                if ($chk_feed && $chk_feed->rowCount() > 0) {
                    $stmt_feed = $pdo->prepare("SELECT item_type, item_id, sort_order FROM products_feed_items WHERE usuario_id = ? ORDER BY sort_order ASC, id ASC");
                    $stmt_feed->execute([$usuario_id_infoprodutor]);
                    $feed_rows = $stmt_feed->fetchAll(PDO::FETCH_ASSOC);
                    $seen = [];
                    $pos = 0;
                    foreach ($feed_rows as $r) {
                        $k = $r['item_type'] . '-' . $r['item_id'];
                        if (isset($seen[$k])) continue;
                        $seen[$k] = true;
                        $feed_sequence[] = ['item_type' => $r['item_type'], 'item_id' => (int)$r['item_id']];
                        if ($r['item_type'] === 'product') {
                            $feed_order_product[(int)$r['item_id']] = $pos++;
                        } else {
                            $feed_order_banner[(int)$r['item_id']] = $pos++;
                        }
                    }
                }
            }

            // Se o cliente não possui nenhum produto, ofertas de produtos ficam vazias (mas banners podem existir)
            $offers = [];
            if (!empty($owned_product_ids)) {
                try {
                    $owned_product_ids_placeholder = implode(',', array_fill(0, count($owned_product_ids), '?'));
                    $sql_offers = "
                        SELECT
                            p_offer.id AS product_id,
                            p_offer.nome AS product_name,
                            p_offer.descricao AS product_description,
                            p_offer.foto AS product_photo,
                            p_offer.preco AS product_price,
                            p_offer.preco_anterior AS product_previous_price,
                            p_offer.product_type AS product_type,
                            p_offer.product_tagline AS product_tagline,
                            p_offer.checkout_hash,
                            p_offer.sales_page_url AS sales_page_url,
                            MAX(peo.custom_link) AS custom_link,
                            MAX(peo.custom_button_text) AS custom_button_text,
                            u.nome AS infoprod_name
                        FROM
                            product_exclusive_offers peo
                        JOIN
                            produtos p_source ON peo.source_product_id = p_source.id
                        JOIN
                            produtos p_offer ON peo.offer_product_id = p_offer.id
                        JOIN
                            usuarios u ON p_offer.usuario_id = u.id
                        WHERE
                            peo.is_active = 1
                            AND peo.source_product_id IN ({$owned_product_ids_placeholder})
                            AND p_offer.id NOT IN ({$owned_product_ids_placeholder})
                        GROUP BY p_offer.id, p_offer.nome, p_offer.descricao, p_offer.foto, p_offer.preco, p_offer.preco_anterior, p_offer.product_type, p_offer.product_tagline, p_offer.checkout_hash, p_offer.sales_page_url, u.nome
                        LIMIT 50
                    ";
                    $params_offers = array_merge($owned_product_ids, $owned_product_ids);
                    $stmt_offers = $pdo->prepare($sql_offers);
                    $stmt_offers->execute($params_offers);
                    $offers = $stmt_offers->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($offers as &$o) {
                        $o['has_access'] = false;
                        $o['_feed_order'] = isset($feed_order_product[(int)$o['product_id']]) ? $feed_order_product[(int)$o['product_id']] : 99999;
                    }
                    unset($o);
                    usort($offers, function ($a, $b) { return $a['_feed_order'] - $b['_feed_order']; });
                    foreach ($offers as &$o) unset($o['_feed_order']);
                    unset($o);
                } catch (PDOException $e) {
                    error_log("API: get_member_exclusive_offers query ofertas: " . $e->getMessage());
                    $offers = [];
                }
            }

            // Banners para seção "Ofertas Exclusivas" (show_in_offers_section = 1) — só do infoprodutor, ordem pelo feed (Meus Produtos)
            $banners_offers = [];
            try {
                $chk_banners = $pdo->query("SHOW TABLES LIKE 'banners'");
                if ($chk_banners && $chk_banners->rowCount() > 0) {
                    $chk_bb = $pdo->query("SHOW TABLES LIKE 'banner_badges'");
                    $has_bb = $chk_bb && $chk_bb->rowCount() > 0;
                    $banners_where = "b.is_active = 1 AND b.show_in_offers_section = 1";
                    $banners_params = [];
                    if ($usuario_id_infoprodutor !== null) {
                        $banners_where .= " AND b.usuario_id = ?";
                        $banners_params[] = $usuario_id_infoprodutor;
                    }
                    $banners_sql = $has_bb
                        ? "SELECT b.*, bb.icon AS badge_icon, bb.label AS badge_label FROM banners b LEFT JOIN banner_badges bb ON bb.id = b.badge_id AND bb.is_active = 1 WHERE {$banners_where}"
                        : "SELECT * FROM banners b WHERE {$banners_where}";
                    $stmt_b = $pdo->prepare($banners_sql);
                    $stmt_b->execute($banners_params);
                    $banners_raw = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
                    // Filtrar banners vinculados a produto que o cliente já possui
                    $owned_ids = array_map('intval', $owned_product_ids);
                    $banners_offers = [];
                    foreach ($banners_raw as $bn) {
                        if (!empty($bn['product_id'])) {
                            $pid = (int)$bn['product_id'];
                            if (in_array($pid, $owned_ids, true)) continue;
                        }
                        $banners_offers[] = $bn;
                    }
                    foreach ($banners_offers as &$bn) {
                        $bn['_feed_order'] = isset($feed_order_banner[(int)$bn['id']]) ? $feed_order_banner[(int)$bn['id']] : 99999;
                    }
                    unset($bn);
                    usort($banners_offers, function ($a, $b) { return $a['_feed_order'] - $b['_feed_order']; });
                    foreach ($banners_offers as &$bn) unset($bn['_feed_order']);
                    unset($bn);
                }
            } catch (PDOException $e) {
                // Ignora se tabela não existir
            }

            // Lista única na ordem do feed (produtos e banners intercalados como em "Meus Produtos")
            $offers_by_id = [];
            foreach ($offers as $o) {
                $offers_by_id[(int)$o['product_id']] = $o;
            }
            $banners_by_id = [];
            foreach ($banners_offers as $b) {
                $banners_by_id[(int)$b['id']] = $b;
            }
            $items = [];
            if (!empty($feed_sequence)) {
                foreach ($feed_sequence as $fi) {
                    if ($fi['item_type'] === 'product' && isset($offers_by_id[$fi['item_id']])) {
                        $items[] = ['type' => 'product', 'data' => $offers_by_id[$fi['item_id']]];
                    } elseif ($fi['item_type'] === 'banner' && isset($banners_by_id[$fi['item_id']])) {
                        $items[] = ['type' => 'banner', 'data' => $banners_by_id[$fi['item_id']]];
                    }
                }
            }
            // Fallback: se feed vazio, ofertas primeiro e depois banners (evita banner no início)
            if (empty($items) && (count($offers) > 0 || count($banners_offers) > 0)) {
                foreach ($offers as $o) {
                    $items[] = ['type' => 'product', 'data' => $o];
                }
                foreach ($banners_offers as $b) {
                    $items[] = ['type' => 'banner', 'data' => $b];
                }
            }

            ob_clean();
            echo json_encode([
                'items' => $items,
                'offers' => $offers,
                'banners' => $banners_offers
            ]);
            exit;

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro de banco em get_member_exclusive_offers: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['error' => 'Erro ao buscar ofertas. Tente novamente.']);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro em get_member_exclusive_offers: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['error' => 'Erro ao carregar ofertas. Tente novamente.']);
            exit;
        }
    }

    // --- NEW: Ações para gerenciamento de cursos (modulos e aulas) ---

    // Action: Reorder Aulas
    if ($action == 'reorder_aulas' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $modulo_id = $input['modulo_id'] ?? null;
        $aulas_order = $input['aulas_order'] ?? []; // Array of lesson IDs in the new order
        $produto_id = $input['produto_id'] ?? null; // For security validation

        if (!$modulo_id || !is_array($aulas_order) || empty($aulas_order) || !$produto_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados inválidos para reordenar aulas.']);
            exit;
        }

        try {
            // 1. Validate ownership: Ensure the module belongs to a course associated with the user's product.
            $stmt_check_ownership = $pdo->prepare("
                SELECT m.id
                FROM modulos m
                JOIN cursos c ON m.curso_id = c.id
                JOIN produtos p ON c.produto_id = p.id
                WHERE m.id = :modulo_id AND p.id = :produto_id AND p.usuario_id = :usuario_id
            ");
            $stmt_check_ownership->bindParam(':modulo_id', $modulo_id, PDO::PARAM_INT);
            $stmt_check_ownership->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_check_ownership->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_ownership->execute();

            if ($stmt_check_ownership->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Acesso negado: Módulo não encontrado ou não pertence a você.']);
                exit;
            }

            // 2. Update order in a transaction
            $pdo->beginTransaction();
            $ordem = 0;
            $stmt_update_order = $pdo->prepare("UPDATE aulas SET ordem = :ordem WHERE id = :aula_id AND modulo_id = :modulo_id");

            foreach ($aulas_order as $aula_id) {
                $stmt_update_order->bindParam(':ordem', $ordem, PDO::PARAM_INT);
                $stmt_update_order->bindParam(':aula_id', $aula_id, PDO::PARAM_INT);
                $stmt_update_order->bindParam(':modulo_id', $modulo_id, PDO::PARAM_INT);
                $stmt_update_order->execute();
                $ordem++;
            }
            $pdo->commit();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Ordem das aulas atualizada com sucesso.']);

        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao reordenar aulas: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao reordenar aulas: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- Comentários nas aulas (infoprodutor) ---
    if ($action == 'save_comentarios_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $curso_id = (int)($input['curso_id'] ?? 0);
        $comentarios_ativos = isset($input['comentarios_ativos']) ? (int)$input['comentarios_ativos'] : 0;
        $comentarios_exigem_aprovacao = isset($input['comentarios_exigem_aprovacao']) ? (int)$input['comentarios_exigem_aprovacao'] : 1;
        if ($curso_id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Curso inválido.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("
                UPDATE cursos SET comentarios_ativos = ?, comentarios_exigem_aprovacao = ?
                WHERE id = ? AND produto_id IN (SELECT id FROM produtos WHERE usuario_id = ?)
            ");
            $stmt->execute([$comentarios_ativos, $comentarios_exigem_aprovacao, $curso_id, $usuario_id_logado]);
            if ($stmt->rowCount() === 0) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Curso não encontrado ou sem permissão.']);
                exit;
            }
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Configurações salvas.']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar.']);
        }
        exit;
    }

    if ($action == 'list_aula_comentarios_admin') {
        $produto_id = (int)($_GET['produto_id'] ?? 0);
        $status_filter = $_GET['status'] ?? 'all'; // all, pending, approved, rejected
        if ($produto_id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Produto inválido.']);
            exit;
        }
        try {
            $stmt_curso = $pdo->prepare("SELECT c.id FROM cursos c JOIN produtos p ON c.produto_id = p.id WHERE p.id = ? AND p.usuario_id = ?");
            $stmt_curso->execute([$produto_id, $usuario_id_logado]);
            $curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);
            if (!$curso) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Curso não encontrado.']);
                exit;
            }
            $curso_id = $curso['id'];
            $status_where = '';
            $params = [$curso_id];
            if ($status_filter === 'pending') $status_where = " AND ac.status = 'pending'";
            elseif ($status_filter === 'approved') $status_where = " AND ac.status = 'approved'";
            elseif ($status_filter === 'rejected') $status_where = " AND ac.status = 'rejected'";
            $resposta_col = '';
            $chk_resp = @$pdo->query("SHOW COLUMNS FROM aula_comentarios LIKE 'resposta_infoprodutor'");
            if ($chk_resp && $chk_resp->rowCount() > 0) $resposta_col = ', ac.resposta_infoprodutor';
            $stmt = $pdo->prepare("
                SELECT ac.id, ac.aula_id, ac.aluno_email, ac.nome_aluno, ac.texto, ac.status, ac.created_at $resposta_col,
                       a.titulo as aula_titulo, m.titulo as modulo_titulo
                FROM aula_comentarios ac
                INNER JOIN aulas a ON ac.aula_id = a.id
                INNER JOIN modulos m ON a.modulo_id = m.id
                WHERE m.curso_id = ? $status_where
                ORDER BY ac.created_at DESC
            ");
            $stmt->execute($params);
            $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt_config = $pdo->prepare("SELECT comentarios_ativos, comentarios_exigem_aprovacao FROM cursos WHERE id = ?");
            $stmt_config->execute([$curso_id]);
            $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode([
                'success' => true,
                'comentarios' => $comentarios,
                'comentarios_ativos' => (bool)($config['comentarios_ativos'] ?? 0),
                'comentarios_exigem_aprovacao' => (bool)($config['comentarios_exigem_aprovacao'] ?? 1)
            ]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao listar comentários.']);
        }
        exit;
    }

    if ($action == 'approve_comentario' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("
                UPDATE aula_comentarios ac
                INNER JOIN aulas a ON ac.aula_id = a.id
                INNER JOIN modulos m ON a.modulo_id = m.id
                INNER JOIN cursos c ON m.curso_id = c.id
                INNER JOIN produtos p ON c.produto_id = p.id
                SET ac.status = 'approved'
                WHERE ac.id = ? AND p.usuario_id = ?
            ");
            $stmt->execute([$id, $usuario_id_logado]);
            ob_clean();
            echo json_encode(['success' => $stmt->rowCount() > 0, 'message' => 'Comentário aprovado.']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao aprovar.']);
        }
        exit;
    }

    if ($action == 'reject_comentario' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("
                UPDATE aula_comentarios ac
                INNER JOIN aulas a ON ac.aula_id = a.id
                INNER JOIN modulos m ON a.modulo_id = m.id
                INNER JOIN cursos c ON m.curso_id = c.id
                INNER JOIN produtos p ON c.produto_id = p.id
                SET ac.status = 'rejected'
                WHERE ac.id = ? AND p.usuario_id = ?
            ");
            $stmt->execute([$id, $usuario_id_logado]);
            ob_clean();
            echo json_encode(['success' => $stmt->rowCount() > 0, 'message' => 'Comentário rejeitado.']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao rejeitar.']);
        }
        exit;
    }

    if ($action == 'resposta_comentario' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($content_type, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $input = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        } else {
            $input = $_POST;
        }
        $id = (int)($input['id'] ?? 0);
        $resposta = trim($input['resposta'] ?? '');
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }
        if (mb_strlen($resposta) > 2000) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Resposta deve ter no máximo 2000 caracteres.']);
            exit;
        }
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM aula_comentarios LIKE 'resposta_infoprodutor'");
            if (!$chk || $chk->rowCount() === 0) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Coluna resposta não existe. Execute a migration.']);
                exit;
            }
            $stmt = $pdo->prepare("
                UPDATE aula_comentarios ac
                INNER JOIN aulas a ON ac.aula_id = a.id
                INNER JOIN modulos m ON a.modulo_id = m.id
                INNER JOIN cursos c ON m.curso_id = c.id
                INNER JOIN produtos p ON c.produto_id = p.id
                SET ac.resposta_infoprodutor = ?
                WHERE ac.id = ? AND p.usuario_id = ?
            ");
            $stmt->execute([$resposta ?: null, $id, $usuario_id_logado]);
            $rows = $stmt->rowCount();
            ob_clean();
            if ($rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Resposta salva.']);
            } else {
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    $dbg = $pdo->prepare("SELECT ac.id, p.usuario_id as produto_owner FROM aula_comentarios ac INNER JOIN aulas a ON ac.aula_id=a.id INNER JOIN modulos m ON a.modulo_id=m.id INNER JOIN cursos c ON m.curso_id=c.id INNER JOIN produtos p ON c.produto_id=p.id WHERE ac.id=?");
                    $dbg->execute([$id]);
                    $row = $dbg->fetch(PDO::FETCH_ASSOC);
                    error_log("resposta_comentario rowCount=0: id=$id, usuario_logado=$usuario_id_logado, db_row=" . json_encode($row));
                }
                echo json_encode(['success' => false, 'error' => 'Comentário não encontrado ou você não tem permissão para responder.']);
            }
        } catch (PDOException $e) {
            ob_clean();
            error_log("API resposta_comentario: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar resposta.']);
        }
        exit;
    }

    // Action: Salvar ordem dos produtos (drag & drop em Meus Produtos)
    if ($action == 'salvar_ordem_produtos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $itens = $input['ordem'] ?? $input ?? []; // [{id: 42, ordem: 1}, {id: 41, ordem: 2}, ...]

        if (!is_array($itens) || empty($itens)) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados inválidos. Envie um array [{id, ordem}, ...].']);
            exit;
        }

        // Verifica se coluna ordem existe (executar migrations/add_produtos_ordem.sql)
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'ordem'");
            if (!$chk || $chk->rowCount() === 0) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Coluna ordem não existe. Execute migrations/add_produtos_ordem.sql']);
                exit;
            }
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao verificar estrutura.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt_update = $pdo->prepare("UPDATE produtos SET ordem = ? WHERE id = ? AND usuario_id = ?");
            foreach ($itens as $item) {
                $id = isset($item['id']) ? (int) $item['id'] : 0;
                $ordem = isset($item['ordem']) ? (int) $item['ordem'] : 0;
                if ($id <= 0) continue;
                $stmt_update->execute([$ordem, $id, $usuario_id_logado]);
            }

            $pdo->commit();

            ob_clean();
            echo json_encode(['success' => true]);

        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao salvar ordem produtos: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar ordem.']);
        }
        exit;
    }

    // Action: Get Lesson Files
    if ($action == 'get_lesson_files' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $aula_id = $_GET['aula_id'] ?? null;

        if (!$aula_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID da aula é obrigatório.']);
            exit;
        }

        try {
            // 1. Validate ownership: Ensure the lesson belongs to a module of a course associated with the user's product.
            $stmt_check_ownership = $pdo->prepare("
                SELECT af.id, af.nome_original, af.nome_salvo, af.caminho_arquivo
                FROM aula_arquivos af
                JOIN aulas a ON af.aula_id = a.id
                JOIN modulos m ON a.modulo_id = m.id
                JOIN cursos c ON m.curso_id = c.id
                JOIN produtos p ON c.produto_id = p.id
                WHERE a.id = :aula_id AND p.usuario_id = :usuario_id
                ORDER BY af.ordem ASC, af.id ASC
            ");
            $stmt_check_ownership->bindParam(':aula_id', $aula_id, PDO::PARAM_INT);
            $stmt_check_ownership->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_ownership->execute();

            $files = $stmt_check_ownership->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'files' => $files]);

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar arquivos da aula: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao buscar arquivos da aula: ' . $e->getMessage()]);
        }
        exit;
    }

    // Action: Delete Aula File
    if ($action == 'delete_aula_file' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $file_id = $input['file_id'] ?? null;

        if (!$file_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do arquivo é obrigatório.']);
            exit;
        }

        try {
            // 1. Validate ownership and get file path
            $stmt_get_file = $pdo->prepare("
                SELECT af.caminho_arquivo
                FROM aula_arquivos af
                JOIN aulas a ON af.aula_id = a.id
                JOIN modulos m ON a.modulo_id = m.id
                JOIN cursos c ON m.curso_id = c.id
                JOIN produtos p ON c.produto_id = p.id
                WHERE af.id = :file_id AND p.usuario_id = :usuario_id
            ");
            $stmt_get_file->bindParam(':file_id', $file_id, PDO::PARAM_INT);
            $stmt_get_file->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_get_file->execute();

            $file_info = $stmt_get_file->fetch(PDO::FETCH_ASSOC);

            if (!$file_info) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Acesso negado: Arquivo não encontrado ou não pertence a você.']);
                exit;
            }

            $caminho_arquivo = $file_info['caminho_arquivo'];

            // 2. Delete physical file
            if (file_exists($caminho_arquivo)) {
                unlink($caminho_arquivo);
            } else {
                error_log("API: Warning - Arquivo físico não encontrado para deletar: " . $caminho_arquivo);
            }

            // 3. Delete database record
            $stmt_delete_record = $pdo->prepare("DELETE FROM aula_arquivos WHERE id = :file_id");
            $stmt_delete_record->bindParam(':file_id', $file_id, PDO::PARAM_INT);
            $stmt_delete_record->execute();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Arquivo deletado com sucesso.']);

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao deletar arquivo da aula: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao deletar arquivo da aula: ' . $e->getMessage()]);
        }
        exit;
    }

    // NOVO: Ações para GatewayPro Track
    if ($action == 'get_GatewayPro_tracked_products') {
        // Garante que erros PHP não sejam exibidos como HTML
        ini_set('display_errors', 0);
        
        try {
            // Verifica se a tabela existe antes de prosseguir
            $stmt_check_table = $pdo->query("SHOW TABLES LIKE 'gatewaypro_tracking_products'");
            if ($stmt_check_table->rowCount() === 0) {
                ob_clean();
                echo json_encode(['success' => true, 'products' => [], 'warning' => 'Tabela de rastreamento não existe.']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT stp.id, stp.produto_id, stp.tracking_id, p.nome FROM gatewaypro_tracking_products stp JOIN produtos p ON stp.produto_id = p.id WHERE stp.usuario_id = :usuario_id ORDER BY p.nome ASC");
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode(['success' => true, 'products' => $products]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar produtos rastreados (get_GatewayPro_tracked_products): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar produtos rastreados: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'add_GatewayPro_tracked_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Garante que erros PHP não sejam exibidos como HTML
        ini_set('display_errors', 0);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $produto_id = $input['produto_id'] ?? null;
        error_log("API: add_GatewayPro_tracked_product - Produto ID: $produto_id, Usuário ID: $usuario_id_logado");

        if (!$produto_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do produto é obrigatório.']);
            exit;
        }

        try {
            // Verifica se a tabela existe antes de prosseguir
            $stmt_check_table = $pdo->query("SHOW TABLES LIKE 'gatewaypro_tracking_products'");
            if ($stmt_check_table->rowCount() === 0) {
                http_response_code(500);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'A tabela de rastreamento não existe. Execute o script SQL para criar as tabelas necessárias.']);
                exit;
            }

            // Verifica se o produto pertence ao usuário logado
            $stmt_check_owner = $pdo->prepare("SELECT id FROM produtos WHERE id = :produto_id AND usuario_id = :usuario_id");
            $stmt_check_owner->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_check_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_owner->execute();

            if ($stmt_check_owner->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou não pertence a você.']);
                exit;
            }
            error_log("API: add_GatewayPro_tracked_product - Produto pertence ao usuário.");

            // Verifica se já está rastreado
            $stmt_check_tracked = $pdo->prepare("SELECT tracking_id FROM gatewaypro_tracking_products WHERE produto_id = :produto_id AND usuario_id = :usuario_id");
            $stmt_check_tracked->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_check_tracked->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_tracked->execute();

            if ($stmt_check_tracked->rowCount() > 0) {
                $existing_tracking = $stmt_check_tracked->fetch(PDO::FETCH_ASSOC);
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Produto já está sendo rastreado.', 'tracking_id' => $existing_tracking['tracking_id']]);
                exit;
            }
            error_log("API: add_GatewayPro_tracked_product - Produto ainda não rastreado, criando novo.");

            $tracking_id = uniqid('st_') . bin2hex(random_bytes(8));
            $stmt_insert = $pdo->prepare("INSERT INTO gatewaypro_tracking_products (usuario_id, produto_id, tracking_id) VALUES (:usuario_id, :produto_id, :tracking_id)");
            $stmt_insert->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_insert->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':tracking_id', $tracking_id, PDO::PARAM_STR);
            $stmt_insert->execute();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Produto configurado para rastreamento.', 'tracking_id' => $tracking_id]);

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao configurar rastreamento (add_GatewayPro_tracked_product): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao configurar rastreamento: ' . $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro geral (add_GatewayPro_tracked_product): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'generate_tracking_script') {
        $tracking_id = $_GET['tracking_id'] ?? null;
        error_log("API: generate_tracking_script - Tracking ID: $tracking_id, Usuário ID: $usuario_id_logado");

        if (!$tracking_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tracking ID é obrigatório.']);
            exit;
        }

        // Verifica se o tracking_id pertence ao usuário logado
        $stmt_check_owner = $pdo->prepare("SELECT stp.id, p.id as produto_id FROM gatewaypro_tracking_products stp JOIN produtos p ON stp.produto_id = p.id WHERE stp.tracking_id = :tracking_id AND stp.usuario_id = :usuario_id");
        $stmt_check_owner->bindParam(':tracking_id', $tracking_id, PDO::PARAM_STR);
        $stmt_check_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_check_owner->execute();

        if ($stmt_check_owner->rowCount() === 0) {
            http_response_code(403);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tracking ID inválido ou não pertence a você.']);
            exit;
        }
        $tracking_product_id_db_row = $stmt_check_owner->fetch(PDO::FETCH_ASSOC);
        $tracking_product_id_db = $tracking_product_id_db_row['id'];
        $associated_product_id = $tracking_product_id_db_row['produto_id'];

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $track_beacon_endpoint = $protocol . $domainName . $basePath . '/track_beacon.php';
        $track_event_endpoint = $protocol . $domainName . $basePath . '/api.php'; // Updated to use api.php
        $checkout_endpoint = $protocol . $domainName . '/checkout'; // Para detectar visitas ao checkout

        // CORREÇÃO: Removendo a lógica de IS_CHECKOUT_PAGE e voltando para a detecção de clique mais simples
        // O script SÓ DEVE SER INSTALADO NA PÁGINA DE VENDAS.
        $script = <<<EOT
<script>
    (function() {
        const GatewayPro_TRACK_ID = '{$tracking_id}';
        const TRACK_EVENT_ENDPOINT = '{$track_event_endpoint}';
        const TRACK_BEACON_ENDPOINT = '{$track_beacon_endpoint}';
        const CHECKOUT_BASE_URL = '{$checkout_endpoint}?p=';
        const CHECKOUT_ENDPOINT_PARTIAL = 'checkout'; // Para detecção de forms (URL limpa sem .php)
        const TRACKING_PRODUCT_DB_ID = '{$tracking_product_id_db}'; 

        console.log('GatewayPro Track Script Loaded. Tracking ID:', GatewayPro_TRACK_ID, 'Checkout Base URL:', CHECKOUT_BASE_URL);

        // Função para obter parâmetros UTM da URL
        function getUrlUtmParameters() {
            const urlParams = new URLSearchParams(window.location.search);
            const utmParams = {};
            const utmKeys = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'src', 'sck'];
            utmKeys.forEach(key => {
                utmParams[key] = urlParams.get(key);
            });
            return utmParams;
        }

        const utmParameters = getUrlUtmParameters();

        function getSessionId() {
            let sessionId = localStorage.getItem('GatewayPro_session_id');
            if (!sessionId) {
                // Gera Session ID
                sessionId = 's_' + Date.now() + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('GatewayPro_session_id', sessionId);
            }
            return sessionId;
        }

        const sessionId = getSessionId();

        // Envia evento para o endpoint de rastreamento via API (POST)
        function sendEvent(eventType, eventData = {}) {
            // Apenas para eventos que não são Page View (como initiate_checkout ou purchase, se usados)
            const payload = {
                action: 'record_tracking_event', 
                tracking_id: GatewayPro_TRACK_ID,
                session_id: sessionId,
                event_type: eventType,
                event_data: {
                    ...eventData,
                    url: window.location.href,
                    referrer: document.referrer,
                    ...utmParameters 
                }
            };
            
            console.log('GatewayPro Track: Sending event', eventType, 'with payload:', payload);

            fetch(TRACK_EVENT_ENDPOINT, {
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

        // Rastreia a visualização de página usando um beacon transparente (GET via imagem)
        function trackPageViewBeacon() {
            const img = new Image();
            const eventDataString = encodeURIComponent(JSON.stringify({
                url: window.location.href,
                referrer: document.referrer,
                ...utmParameters 
            }));
            
            // A URL do beacon é usada para o evento page_view
            img.src = TRACK_BEACON_ENDPOINT + '?tracking_id=' + GatewayPro_TRACK_ID + '&session_id=' + sessionId + '&event_type=page_view&event_data=' + eventDataString + '&t=' + Date.now();
            
            // Log para debug
            console.log('GatewayPro Track: Sending Page View via Beacon to:', img.src);

            img.style.width = '1px';
            img.style.height = '1px';
            img.style.position = 'absolute';
            img.style.left = '-9999px';
            img.style.top = '-9999px';
            document.body.appendChild(img);
            img.onload = () => { if(img.parentNode) img.parentNode.removeChild(img); };
            img.onerror = () => { console.error('GatewayPro Track: Erro ao carregar beacon de rastreamento.'); if(img.parentNode) img.parentNode.removeChild(img); };
        }

        // --- INICIALIZAÇÃO ---

        // 1. DISPARO INICIAL (Page View)
        if (document.body) {
             trackPageViewBeacon(); 
        } else {
             window.addEventListener('load', trackPageViewBeacon);
        }
        
        // 2. Tenta extrair o checkout hash da URL (usado na maioria das implementações do checkout)
        // Isso será útil para links diretos ou formas mais simples
        function extractCheckoutHash(url) {
            try {
                const urlObj = new URL(url);
                return urlObj.searchParams.get('p');
            } catch (e) {
                return null;
            }
        }
        
        // 3. MONITORAMENTO DE INTERAÇÕES (Início de Checkout)
        
        // Listener principal para cliques
        document.addEventListener('click', (e) => {
            let target = e.target;
            while (target && target !== document.body) {
                
                // Opção A: Detecção por link <a> com a URL de checkout
                if (target.tagName === 'A' && target.href && target.href.includes(CHECKOUT_ENDPOINT_PARTIAL)) {
                    const checkoutHash = extractCheckoutHash(target.href);
                    if (checkoutHash) {
                        sendEvent('initiate_checkout', { checkout_hash: checkoutHash, via: 'link_a' });
                        console.log('GatewayPro Track: Evento initiate_checkout disparado para hash:', checkoutHash, 'no link A.');
                        return; // Evento capturado, sair
                    }
                }
                
                // Opção B: Detecção por Classe de Botão de Checkout (Fallback manual)
                if (target.classList && target.classList.contains('GatewayPro-checkout-btn')) {
                    // Tenta encontrar o hash no link ou em um atributo de dado se o link não for claro
                    const href = target.tagName === 'A' ? target.href : target.getAttribute('data-checkout-url');
                    let checkoutHash = null;
                    if(href) {
                        checkoutHash = extractCheckoutHash(href);
                    }
                    
                    // Se não encontrou o hash no href, use o ID do produto ou o nome da classe como fallback (menos ideal)
                    if(!checkoutHash) {
                        // Tenta obter o hash de um data-attribute auxiliar
                        checkoutHash = target.getAttribute('data-checkout-hash') || 'hash_via_class_unknown';
                    }

                    sendEvent('initiate_checkout', { checkout_hash: checkoutHash, via: 'button_class' });
                    console.log('GatewayPro Track: Evento initiate_checkout disparado via classe para hash:', checkoutHash);
                    return; // Evento capturado, sair
                }
                
                target = target.parentElement;
            }
        });
        
        // Listener para interceptar envio de formulário (se o botão for um <button type="submit"> dentro de um <form>)
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.tagName === 'FORM' && form.action.includes(CHECKOUT_ENDPOINT_PARTIAL)) {
                 
                // Tenta encontrar o hash no action ou em um campo de formulário oculto
                let checkoutHash = extractCheckoutHash(form.action);
                
                // Se não encontrou na action, verifica se há um campo 'p' dentro do form
                if (!checkoutHash) {
                    const inputHash = form.querySelector('input[name="p"]');
                    if (inputHash) {
                        checkoutHash = inputHash.value;
                    }
                }
                
                if (checkoutHash) {
                    sendEvent('initiate_checkout', { checkout_hash: checkoutHash, via: 'form_submit' });
                    console.log('GatewayPro Track: Evento initiate_checkout disparado via FORM submit para hash:', checkoutHash);
                    // O formulário continuará a ser enviado normalmente
                }
            }
        });
    })();
</script>
EOT;

        ob_clean();
        echo json_encode(['success' => true, 'script' => $script]);
        exit;
    }

    if ($action == 'record_tracking_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $tracking_id = $input['tracking_id'] ?? null;
        $session_id = $input['session_id'] ?? null;
        $event_type = $input['event_type'] ?? null;
        $event_data = $input['event_data'] ?? [];

        error_log("API: record_tracking_event - Received data: " . print_r($input, true));

        if (!$tracking_id || !$session_id || !$event_type) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados de rastreamento incompletos.']);
            exit;
        }

        try {
            // Retrieve the internal tracking_product_id using the public tracking_id
            $stmt_get_internal_id = $pdo->prepare("SELECT id, produto_id FROM gatewaypro_tracking_products WHERE tracking_id = :tracking_id");
            $stmt_get_internal_id->bindParam(':tracking_id', $tracking_id, PDO::PARAM_STR);
            $stmt_get_internal_id->execute();
            $tracked_product_info = $stmt_get_internal_id->fetch(PDO::FETCH_ASSOC);

            if (!$tracked_product_info) {
                http_response_code(404);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Tracking ID não encontrado.']);
                exit;
            }

            $internal_tracking_product_id = $tracked_product_info['id'];
            $associated_product_id = $tracked_product_info['produto_id'];

            // Store event data as JSON
            $event_data_json = json_encode($event_data);

            $stmt_insert_event = $pdo->prepare("
                INSERT INTO gatewaypro_tracking_events (tracking_product_id, session_id, event_type, event_data)
                VALUES (:tracking_product_id, :session_id, :event_type, :event_data)
            ");
            $stmt_insert_event->bindParam(':tracking_product_id', $internal_tracking_product_id, PDO::PARAM_INT);
            $stmt_insert_event->bindParam(':session_id', $session_id, PDO::PARAM_STR);
            $stmt_insert_event->bindParam(':event_type', $event_type, PDO::PARAM_STR);
            $stmt_insert_event->bindParam(':event_data', $event_data_json, PDO::PARAM_STR);
            $stmt_insert_event->execute();

            error_log("API: Evento de rastreamento registrado: Tipo='" . $event_type . "', Session ID='" . $session_id . "'");

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Evento de rastreamento registrado com sucesso.']);

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro de banco de dados em record_tracking_event: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao registrar evento: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'get_GatewayPro_tracking_data') {
        // Garante que erros PHP não sejam exibidos como HTML
        ini_set('display_errors', 0);
        
        $tracking_product_id = $_GET['tracking_product_id'] ?? null;
        $period = $_GET['period'] ?? 'all'; // 'today', 'yesterday', '7days', 'month', 'year', 'all'
        error_log("API: get_GatewayPro_tracking_data - Tracking Product ID: $tracking_product_id, Period: $period, Usuário ID: $usuario_id_logado");

        if (!$tracking_product_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tracking Product ID é obrigatório.']);
            exit;
        }

        try {
            // Valida se o tracking_product_id pertence ao usuário logado
            $stmt_check_owner = $pdo->prepare("SELECT stp.id, stp.produto_id, p.checkout_hash FROM gatewaypro_tracking_products stp JOIN produtos p ON stp.produto_id = p.id WHERE stp.id = :tracking_product_id AND stp.usuario_id = :usuario_id");
            $stmt_check_owner->bindParam(':tracking_product_id', $tracking_product_id, PDO::PARAM_INT);
            $stmt_check_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_owner->execute();

            if ($stmt_check_owner->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Tracking Product ID inválido ou não pertence a você.']);
                exit;
            }
            $tracked_product_info = $stmt_check_owner->fetch(PDO::FETCH_ASSOC);
            $associated_checkout_hash = $tracked_product_info['checkout_hash'];
            $original_product_id = $tracked_product_info['produto_id'];
            error_log("API: get_GatewayPro_tracking_data - Produto rastreado encontrado. Original Product ID: $original_product_id.");


        $date_filter_sql_ste = ''; // Para gatewaypro_tracking_events
        $date_filter_sql_vendas = ''; // Para vendas
        switch ($period) {
            case 'today': 
                $date_filter_sql_ste = "AND DATE(ste.created_at) = CURDATE()"; 
                $date_filter_sql_vendas = "AND DATE(v.data_venda) = CURDATE()"; 
                break;
            case 'yesterday': 
                $date_filter_sql_ste = "AND DATE(ste.created_at) = CURDATE() - INTERVAL 1 DAY"; 
                $date_filter_sql_vendas = "AND DATE(v.data_venda) = CURDATE() - INTERVAL 1 DAY"; 
                break;
            case '7days': 
                $date_filter_sql_ste = "AND ste.created_at >= CURDATE() - INTERVAL 6 DAY"; 
                $date_filter_sql_vendas = "AND v.data_venda >= CURDATE() - INTERVAL 6 DAY"; 
                break;
            case 'month': 
                $date_filter_sql_ste = "AND MONTH(ste.created_at) = MONTH(CURDATE()) AND YEAR(ste.created_at) = YEAR(CURDATE())"; 
                $date_filter_sql_vendas = "AND MONTH(v.data_venda) = MONTH(CURDATE()) AND YEAR(v.data_venda) = YEAR(CURDATE())"; 
                break;
            case 'year': 
                $date_filter_sql_ste = "AND YEAR(ste.created_at) = YEAR(CURDATE())"; 
                $date_filter_sql_vendas = "AND YEAR(v.data_venda) = YEAR(CURDATE())"; 
                break;
            case 'all': default: 
                $date_filter_sql_ste = ""; 
                $date_filter_sql_vendas = ""; 
                break;
        }
        error_log("API: get_GatewayPro_tracking_data - Filtro de data STE: '$date_filter_sql_ste', Filtro de data Vendas: '$date_filter_sql_vendas'.");


        // 1. Total Page Views (unique sessions)
        try {
            // CORREÇÃO: Adicionado alias 'ste' à tabela
            $sql_page_views = "SELECT COUNT(DISTINCT ste.session_id) FROM gatewaypro_tracking_events ste WHERE ste.tracking_product_id = :tracking_product_id AND ste.event_type = 'page_view' {$date_filter_sql_ste}";
            $stmt_page_views = $pdo->prepare($sql_page_views);
            $stmt_page_views->bindParam(':tracking_product_id', $tracking_product_id, PDO::PARAM_INT);
            $stmt_page_views->execute();
            $page_views = (int)$stmt_page_views->fetchColumn();
            error_log("API: Page Views - SQL: '$sql_page_views', Result: $page_views");
        } catch (PDOException $e) {
            error_log("API: Erro PDO na consulta de page_views: " . $e->getMessage() . " SQL: " . $sql_page_views);
            throw $e;
        }

        // 2. Total Initiate Checkouts (unique sessions)
        try {
            // CORREÇÃO: Adicionado alias 'ste' à tabela
            $sql_initiate_checkouts = "SELECT COUNT(DISTINCT ste.session_id) FROM gatewaypro_tracking_events ste WHERE ste.tracking_product_id = :tracking_product_id AND ste.event_type = 'initiate_checkout' {$date_filter_sql_ste}";
            $stmt_initiate_checkouts = $pdo->prepare($sql_initiate_checkouts);
            $stmt_initiate_checkouts->bindParam(':tracking_product_id', $tracking_product_id, PDO::PARAM_INT);
            $stmt_initiate_checkouts->execute();
            $initiate_checkouts = (int)$stmt_initiate_checkouts->fetchColumn();
            error_log("API: Initiate Checkouts - SQL: '$sql_initiate_checkouts', Result: $initiate_checkouts");
        } catch (PDOException $e) {
            error_log("API: Erro PDO na consulta de initiate_checkouts: " . $e->getMessage() . " SQL: " . $sql_initiate_checkouts);
            throw $e;
        }

        // 3. Total Purchases (unique sales for main product)
        try {
            $sql_purchases = "
                SELECT COUNT(DISTINCT v.id) 
                FROM vendas v
                WHERE v.produto_id = :original_product_id 
                AND v.status_pagamento = 'approved'
                {$date_filter_sql_vendas}
                AND v.checkout_session_uuid IN (SELECT DISTINCT ste.session_id FROM gatewaypro_tracking_events ste WHERE ste.event_type = 'purchase' AND ste.tracking_product_id = :tracking_product_id {$date_filter_sql_ste})
            ";
            $stmt_purchases = $pdo->prepare($sql_purchases);
            $stmt_purchases->bindParam(':original_product_id', $original_product_id, PDO::PARAM_INT);
            $stmt_purchases->bindParam(':tracking_product_id', $tracking_product_id, PDO::PARAM_INT);
            $stmt_purchases->execute();
            $purchases = (int)$stmt_purchases->fetchColumn();
            error_log("API: Purchases - SQL: '$sql_purchases', Original Product ID: $original_product_id, Tracking Product ID: $tracking_product_id, Result: $purchases");
        } catch (PDOException $e) {
            error_log("API: Erro PDO na consulta de purchases: " . $e->getMessage() . " SQL: " . $sql_purchases);
            throw $e;
        }

        // 4. Sales details (main product and order bumps)
        try {
            $sql_sales_details = "
                SELECT 
                    p.nome as product_name, 
                    SUM(v.valor) as total_value, 
                    COUNT(v.id) as total_count,
                    p.id as product_db_id
                FROM vendas v
                JOIN produtos p ON v.produto_id = p.id
                WHERE v.status_pagamento = 'approved'
                {$date_filter_sql_vendas}
                AND v.checkout_session_uuid IN (
                    SELECT DISTINCT ste.session_id 
                    FROM gatewaypro_tracking_events ste 
                    WHERE ste.event_type = 'purchase' 
                    AND ste.tracking_product_id = :tracking_product_id 
                    {$date_filter_sql_ste}
                )
                GROUP BY product_db_id, p.nome
                ORDER BY FIELD(product_db_id, :original_product_id) DESC, product_name ASC
            ";
            $stmt_sales_details = $pdo->prepare($sql_sales_details);
            $stmt_sales_details->bindParam(':original_product_id', $original_product_id, PDO::PARAM_INT);
            $stmt_sales_details->bindParam(':tracking_product_id', $tracking_product_id, PDO::PARAM_INT);
            $stmt_sales_details->execute();
            $sales_details_raw = $stmt_sales_details->fetchAll(PDO::FETCH_ASSOC);
            error_log("API: Sales Details - SQL: '$sql_sales_details', Original Product ID: $original_product_id, Tracking Product ID: $tracking_product_id, Result: " . print_r($sales_details_raw, true));
        } catch (PDOException $e) {
            error_log("API: Erro PDO na consulta de sales_details: " . $e->getMessage() . " SQL: " . $sql_sales_details);
            throw $e;
        }

        $main_product_sales_value = 0;
        $main_product_sales_count = 0;
        $order_bump_sales = [];
        foreach ($sales_details_raw as $sale) {
            if ($sale['product_db_id'] == $original_product_id) {
                $main_product_sales_value = $sale['total_value'];
                $main_product_sales_count = $sale['total_count'];
            } else {
                $order_bump_sales[] = [
                    'product_name' => $sale['product_name'],
                    'total_value' => (float)$sale['total_value'],
                    'total_count' => (int)$sale['total_count']
                ];
            }
        }
        
        // Conversions
        $page_to_checkout_conversion = ($page_views > 0) ? ($initiate_checkouts / $page_views) * 100 : 0;
        $checkout_to_purchase_conversion = ($initiate_checkouts > 0) ? ($purchases / $initiate_checkouts) * 100 : 0;
        $overall_conversion = ($page_views > 0) ? ($purchases / $page_views) * 100 : 0;

        // Clicks per sale
        $clicks_to_sale_page = ($purchases > 0) ? round($page_views / $purchases, 2) : 0;
        $clicks_to_sale_checkout = ($purchases > 0) ? round($initiate_checkouts / $purchases, 2) : 0;


        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => [
                'funnel' => [
                    'page_views' => $page_views,
                    'initiate_checkouts' => $initiate_checkouts,
                    'purchases' => $purchases,
                ],
                'conversions' => [
                    'page_to_checkout' => round($page_to_checkout_conversion, 2),
                    'checkout_to_purchase' => round($checkout_to_purchase_conversion, 2),
                    'overall' => round($overall_conversion, 2),
                ],
                'sales_summary' => [
                    'main_product_sales_value' => (float)$main_product_sales_value,
                    'main_product_sales_count' => (int)$main_product_sales_count,
                    'order_bump_sales' => $order_bump_sales,
                ],
                'kpis' => [
                    'clicks_to_sale_page' => $clicks_to_sale_page,
                    'clicks_to_sale_checkout' => $clicks_to_sale_checkout,
                ]
            ]
        ]);
        
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro PDO em get_GatewayPro_tracking_data: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar dados de rastreamento: ' . $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro geral em get_GatewayPro_tracking_data: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
        exit;
    }

    // NOVO: Ação para excluir um funil de rastreamento
    if ($action == 'delete_GatewayPro_tracked_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $tracking_product_db_id = $input['tracking_product_db_id'] ?? null;
        error_log("API: delete_GatewayPro_tracked_product - Tracking Product DB ID: $tracking_product_db_id, Usuário ID: $usuario_id_logado");

        if (!$tracking_product_db_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do funil de rastreamento é obrigatório.']);
            exit;
        }

        try {
            // Verifica se o funil de rastreamento pertence ao usuário logado
            $stmt_check_owner = $pdo->prepare("SELECT id FROM gatewaypro_tracking_products WHERE id = :tracking_product_db_id AND usuario_id = :usuario_id");
            $stmt_check_owner->bindParam(':tracking_product_db_id', $tracking_product_db_id, PDO::PARAM_INT);
            $stmt_check_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_owner->execute();

            if ($stmt_check_owner->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Funil de rastreamento não encontrado ou não pertence a você.']);
                exit;
            }

            // Deleta o funil de rastreamento (aulas serão deletadas em cascata se a FK estiver configurada)
            $stmt_delete = $pdo->prepare("DELETE FROM gatewaypro_tracking_products WHERE id = :tracking_product_db_id");
            $stmt_delete->bindParam(':tracking_product_db_id', $tracking_product_db_id, PDO::PARAM_INT);
            $stmt_delete->execute();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Funil de rastreamento excluído com sucesso.']);

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao excluir funil de rastreamento (delete_GatewayPro_tracked_product): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir funil de rastreamento: ' . $e->getMessage()]);
        }
        exit;
    }

    // NOVO: Ações para Webhooks
    if ($action == 'get_webhooks') {
        try {
            // Busca todos os webhooks do usuário logado, incluindo o nome do produto se associado
            $stmt = $pdo->prepare("SELECT w.*, p.nome as produto_nome FROM webhooks w LEFT JOIN produtos p ON w.produto_id = p.id WHERE w.usuario_id = :usuario_id ORDER BY w.created_at DESC");
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->execute();
            $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode(['success' => true, 'webhooks' => $webhooks]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar webhooks (get_webhooks): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar webhooks: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'create_webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $url = filter_var($input['url'] ?? '', FILTER_VALIDATE_URL);
        $produto_id = $input['produto_id'] ?? null; // Pode ser null
        $events = $input['events'] ?? [];

        if (!$url) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'URL do webhook inválida ou ausente.']);
            exit;
        }

        // Se produto_id for fornecido, verifica se pertence ao usuário
        if ($produto_id) {
            $stmt_check_product = $pdo->prepare("SELECT id FROM produtos WHERE id = :produto_id AND usuario_id = :usuario_id");
            $stmt_check_product->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_check_product->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_product->execute();
            if ($stmt_check_product->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto associado não encontrado ou não pertence a você.']);
                exit;
            }
        }

        try {
            $stmt_insert = $pdo->prepare("
                INSERT INTO webhooks (
                    usuario_id, produto_id, url, 
                    event_approved, event_pending, event_rejected, 
                    event_refunded, event_charged_back
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert->execute([
                $usuario_id_logado,
                $produto_id,
                $url,
                (int)($events['approved'] ?? 0),
                (int)($events['pending'] ?? 0),
                (int)($events['rejected'] ?? 0),
                (int)($events['refunded'] ?? 0),
                (int)($events['charged_back'] ?? 0)
            ]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Webhook criado com sucesso!', 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao criar webhook (create_webhook): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao criar webhook: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'update_webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $webhook_id = $input['id'] ?? null;
        $url = filter_var($input['url'] ?? '', FILTER_VALIDATE_URL);
        $produto_id = $input['produto_id'] ?? null; // Pode ser null
        $events = $input['events'] ?? [];

        if (!$webhook_id || !$url) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do webhook ou URL inválida/ausente.']);
            exit;
        }

        // Verifica se o webhook pertence ao usuário
        $stmt_check_webhook_owner = $pdo->prepare("SELECT id FROM webhooks WHERE id = :webhook_id AND usuario_id = :usuario_id");
        $stmt_check_webhook_owner->bindParam(':webhook_id', $webhook_id, PDO::PARAM_INT);
        $stmt_check_webhook_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_check_webhook_owner->execute();
        if ($stmt_check_webhook_owner->rowCount() === 0) {
            http_response_code(403);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Webhook não encontrado ou não pertence a você.']);
            exit;
        }

        // Se produto_id for fornecido, verifica se pertence ao usuário
        if ($produto_id) {
            $stmt_check_product = $pdo->prepare("SELECT id FROM produtos WHERE id = :produto_id AND usuario_id = :usuario_id");
            $stmt_check_product->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_check_product->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_product->execute();
            if ($stmt_check_product->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto associado não encontrado ou não pertence a você.']);
                exit;
            }
        }

        try {
            $stmt_update = $pdo->prepare("
                UPDATE webhooks SET 
                    produto_id = ?, url = ?, 
                    event_approved = ?, event_pending = ?, event_rejected = ?, 
                    event_refunded = ?, event_charged_back = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt_update->execute([
                $produto_id,
                $url,
                (int)($events['approved'] ?? 0),
                (int)($events['pending'] ?? 0),
                (int)($events['rejected'] ?? 0),
                (int)($events['refunded'] ?? 0),
                (int)($events['charged_back'] ?? 0),
                $webhook_id,
                $usuario_id_logado
            ]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Webhook atualizado com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao atualizar webhook (update_webhook): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar webhook: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'delete_webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $webhook_id = $input['id'] ?? null;

        if (!$webhook_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do webhook é obrigatório.']);
            exit;
        }

        // Verifica se o webhook pertence ao usuário
        $stmt_check_owner = $pdo->prepare("SELECT id FROM webhooks WHERE id = :webhook_id AND usuario_id = :usuario_id");
        $stmt_check_owner->bindParam(':webhook_id', $webhook_id, PDO::PARAM_INT);
        $stmt_check_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_check_owner->execute();
        if ($stmt_check_owner->rowCount() === 0) {
            http_response_code(403);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Webhook não encontrado ou não pertence a você.']);
            exit;
        }

        try {
            $stmt_delete = $pdo->prepare("DELETE FROM webhooks WHERE id = :webhook_id AND usuario_id = :usuario_id");
            $stmt_delete->bindParam(':webhook_id', $webhook_id, PDO::PARAM_INT);
            $stmt_delete->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_delete->execute();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Webhook excluído com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao excluir webhook (delete_webhook): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir webhook: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'test_webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $url = filter_var($input['url'] ?? '', FILTER_VALIDATE_URL);

        if (!$url) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'URL do webhook inválida.']);
            exit;
        }

        // Preparar um payload de teste universal
        $test_payload = [
            'event' => 'test_webhook',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => [
                'test_message' => 'Este é um evento de teste da GatewayPro',
                'infoprodutor_id' => $usuario_id_logado,
                'produto_nome' => 'Produto de Teste',
                'valor' => 99.99,
                'status_pagamento' => 'approved',
                'comprador_nome' => 'Cliente Teste',
                'comprador_email' => 'cliente@teste.com',
                'comprador_telefone' => '556291252643',
                'transacao_id' => 'TEST_TRANS_12345',
                'metodo_pagamento' => 'Pix',
                'checkout_session_uuid' => 'TEST_CHECKOUT_UUID'
            ]
        ];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                http_response_code(500);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro cURL ao enviar teste: ' . $curl_error]);
                exit;
            }

            if ($http_code >= 200 && $http_code < 300) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => "Webhook de teste enviado com sucesso! Resposta HTTP: {$http_code}."]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => "Webhook de teste falhou. Resposta HTTP: {$http_code}. Resposta: " . (strlen($response) > 200 ? substr($response, 0, 200) . '...' : $response)]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao testar webhook (test_webhook): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao testar webhook: ' . $e->getMessage()]);
        }
        exit;
    }

    // NOVO: Ações para Integração UTMfy

    // Listar integrações UTMfy
    if ($action == 'get_utmfy_integrations') {
        try {
            $stmt = $pdo->prepare("
                SELECT utmfy.*, p.nome as product_name 
                FROM utmfy_integrations utmfy
                LEFT JOIN produtos p ON utmfy.product_id = p.id
                WHERE utmfy.usuario_id = :usuario_id 
                ORDER BY utmfy.created_at DESC
            ");
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->execute();
            $integrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode(['success' => true, 'integrations' => $integrations]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar integrações UTMfy (get_utmfy_integrations): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar integrações UTMfy: ' . $e->getMessage()]);
        }
        exit;
    }

    // Criar nova integração UTMfy
    if ($action == 'create_utmfy_integration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $api_token = trim($input['api_token'] ?? '');
        $product_id = $input['product_id'] ?? null; // Pode ser null
        $events = $input['events'] ?? [];

        if (empty($name) || empty($api_token)) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Nome da integração e API Token são obrigatórios.']);
            exit;
        }

        // Se product_id for fornecido, verifica se pertence ao usuário
        if ($product_id) {
            $stmt_check_product = $pdo->prepare("SELECT id FROM produtos WHERE id = :product_id AND usuario_id = :usuario_id");
            $stmt_check_product->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_check_product->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_product->execute();
            if ($stmt_check_product->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto associado não encontrado ou não pertence a você.']);
                exit;
            }
        }

        try {
            $stmt_insert = $pdo->prepare("
                INSERT INTO utmfy_integrations (
                    usuario_id, name, api_token, product_id, 
                    event_approved, event_pending, event_rejected, 
                    event_refunded, event_charged_back
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert->execute([
                $usuario_id_logado,
                $name,
                $api_token,
                $product_id,
                (int)($events['approved'] ?? 0),
                (int)($events['pending'] ?? 0),
                (int)($events['rejected'] ?? 0),
                (int)($events['refunded'] ?? 0),
                (int)($events['charged_back'] ?? 0)
            ]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Integração UTMfy criada com sucesso!', 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao criar integração UTMfy (create_utmfy_integration): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao criar integração UTMfy: ' . $e->getMessage()]);
        }
        exit;
    }

    // Atualizar integração UTMfy
    if ($action == 'update_utmfy_integration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $integration_id = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $api_token = trim($input['api_token'] ?? '');
        $product_id = $input['product_id'] ?? null; // Pode ser null
        $events = $input['events'] ?? [];

        if (!$integration_id || empty($name) || empty($api_token)) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID da integração, nome ou API Token inválido/ausente.']);
            exit;
        }

        // Verifica se a integração pertence ao usuário
        $stmt_check_integration_owner = $pdo->prepare("SELECT id FROM utmfy_integrations WHERE id = :integration_id AND usuario_id = :usuario_id");
        $stmt_check_integration_owner->bindParam(':integration_id', $integration_id, PDO::PARAM_INT);
        $stmt_check_integration_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_check_integration_owner->execute();
        if ($stmt_check_integration_owner->rowCount() === 0) {
            http_response_code(403);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Integração UTMfy não encontrada ou não pertence a você.']);
            exit;
        }

        // Se product_id for fornecido, verifica se pertence ao usuário
        if ($product_id) {
            $stmt_check_product = $pdo->prepare("SELECT id FROM produtos WHERE id = :product_id AND usuario_id = :usuario_id");
            $stmt_check_product->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_check_product->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_product->execute();
            if ($stmt_check_product->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto associado não encontrado ou não pertence a você.']);
                exit;
            }
        }

        try {
            $stmt_update = $pdo->prepare("
                UPDATE utmfy_integrations SET 
                    name = ?, api_token = ?, product_id = ?, 
                    event_approved = ?, event_pending = ?, event_rejected = ?, 
                    event_refunded = ?, event_charged_back = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt_update->execute([
                $name,
                $api_token,
                $product_id,
                (int)($events['approved'] ?? 0),
                (int)($events['pending'] ?? 0),
                (int)($events['rejected'] ?? 0),
                (int)($events['refunded'] ?? 0),
                (int)($events['charged_back'] ?? 0),
                $integration_id,
                $usuario_id_logado
            ]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Integração UTMfy atualizada com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao atualizar integração UTMfy (update_utmfy_integration): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar integração UTMfy: ' . $e->getMessage()]);
        }
        exit;
    }

    // Deletar integração UTMfy
    if ($action == 'delete_utmfy_integration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $integration_id = $input['id'] ?? null;

        if (!$integration_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID da integração é obrigatório.']);
            exit;
        }

        // Verifica se a integração pertence ao usuário
        $stmt_check_owner = $pdo->prepare("SELECT id FROM utmfy_integrations WHERE id = :integration_id AND usuario_id = :usuario_id");
        $stmt_check_owner->bindParam(':integration_id', $integration_id, PDO::PARAM_INT);
        $stmt_check_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
        $stmt_check_owner->execute();
        if ($stmt_check_owner->rowCount() === 0) {
            http_response_code(403);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Integração UTMfy não encontrada ou não pertence a você.']);
            exit;
        }

        try {
            $stmt_delete = $pdo->prepare("DELETE FROM utmfy_integrations WHERE id = :integration_id AND usuario_id = :usuario_id");
            $stmt_delete->bindParam(':integration_id', $integration_id, PDO::PARAM_INT);
            $stmt_delete->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_delete->execute();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Integração UTMfy excluída com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao excluir integração UTMfy (delete_utmfy_integration): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir integração UTMfy: ' . $e->getMessage()]);
        }
        exit;
    }

    // NEW: Ações para Clonar Site
    if ($action == 'clone_url' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $url_to_clone = filter_var($input['url'], FILTER_VALIDATE_URL);

        if (!$url_to_clone) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'URL inválida ou ausente.']);
            exit;
        }

        try {
            // Configure a stream context para simular um navegador (User-Agent)
            $context = stream_context_create([
                'http' => [
                    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ]
            ]);

            $html_content = file_get_contents($url_to_clone, false, $context);

            if ($html_content === false) {
                http_response_code(500);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Não foi possível buscar o conteúdo da URL. Verifique se a URL está correta e acessível.']);
                exit;
            }

            // Usar DOMDocument para parsear e manipular o HTML
            $dom = new DOMDocument();
            // Suprime avisos de HTML malformado
            libxml_use_internal_errors(true);
            $dom->loadHTML($html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            // Resolve URLs relativas para absolutas
            $base_url_parts = parse_url($url_to_clone);
            $base_scheme = $base_url_parts['scheme'] ?? 'http';
            $base_host = $base_url_parts['host'] ?? '';
            $base_path = dirname($base_url_parts['path'] ?? '/');

            // Função helper para resolver URLs (handle relative paths)
            $resolve_url = function($relative_url) use ($base_scheme, $base_host, $base_path) {
                if (empty($relative_url)) return '';

                // Already absolute (starts with http(s):// or //)
                if (preg_match('/^(https?:\/\/|\/\/)/i', $relative_url)) {
                    // Handle protocol-relative URLs
                    if (str_starts_with($relative_url, '//')) {
                        return $base_scheme . ':' . $relative_url;
                    }
                    return $relative_url;
                }

                // Absolute path from root (starts with /)
                if (str_starts_with($relative_url, '/')) {
                    return $base_scheme . '://' . $base_host . $relative_url;
                }

                // Relative path
                return $base_scheme . '://' . $base_host . rtrim($base_path, '/') . '/' . $relative_url;
            };

            $elements_to_resolve = [
                'a' => 'href',
                'img' => 'src',
                'link' => 'href',
                'script' => 'src',
                'iframe' => 'src',
                'source' => 'src', // For <video> and <audio> elements
                'video' => 'src',
                'audio' => 'src',
                '*' => 'data-src' // Catch common lazy-load attributes
            ];

            foreach ($elements_to_resolve as $tag => $attr) {
                if ($tag === '*') { // Handle generic data-src attributes
                    $xpath = new DOMXPath($dom);
                    $nodes = $xpath->query("//*[@{$attr}]");
                    foreach ($nodes as $node) {
                        $current_url = $node->getAttribute($attr);
                        if (!empty($current_url)) {
                            $node->setAttribute($attr, $resolve_url($current_url));
                        }
                    }
                } else {
                    $elements = $dom->getElementsByTagName($tag);
                    foreach ($elements as $element) {
                        $current_url = $element->getAttribute($attr);
                        if (!empty($current_url)) {
                            $element->setAttribute($attr, $resolve_url($current_url));
                        }
                    }
                }
            }

            // Remover scripts de rastreamento conhecidos
            $scripts = $dom->getElementsByTagName('script');
            $scripts_to_remove = [];
            foreach ($scripts as $script) {
                $script_content = $script->textContent;
                $script_src = $script->getAttribute('src');

                // Facebook Pixel patterns
                if (strpos($script_content, 'fbq(') !== false || strpos($script_src, 'connect.facebook.net') !== false) {
                    $scripts_to_remove[] = $script;
                    continue;
                }
                // Google Analytics / Tag Manager patterns
                if (strpos($script_content, 'gtag(') !== false || strpos($script_src, 'googletagmanager.com') !== false || strpos($script_src, 'google-analytics.com') !== false) {
                    $scripts_to_remove[] = $script;
                    continue;
                }
                // TikTok Pixel (example pattern)
                if (strpos($script_content, 'ttq.load') !== false || strpos($script_src, 'tiktok.com/analytics.js') !== false) {
                    $scripts_to_remove[] = $script;
                    continue;
                }
                // Other common tracking (e.g., Hotjar, CrazyEgg - specific patterns would be needed)
                // For a robust solution, a whitelist of allowed scripts would be better than a blacklist.
            }

            foreach ($scripts_to_remove as $script) {
                if ($script->parentNode) {
                    $script->parentNode->removeChild($script);
                }
            }
            
            // Tenta obter o título da página
            $page_title = 'Site Clonado';
            $title_nodes = $dom->getElementsByTagName('title');
            if ($title_nodes->length > 0) {
                $page_title = $title_nodes->item(0)->textContent;
            }

            // Salva o HTML limpo e manipulado no banco de dados
            $cleaned_html = $dom->saveHTML();

            $stmt_insert_site = $pdo->prepare("INSERT INTO cloned_sites (usuario_id, original_url, title, original_html, edited_html) VALUES (:usuario_id, :original_url, :title, :original_html, :edited_html)");
            $stmt_insert_site->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_insert_site->bindParam(':original_url', $url_to_clone, PDO::PARAM_STR);
            $stmt_insert_site->bindParam(':title', $page_title, PDO::PARAM_STR);
            $stmt_insert_site->bindParam(':original_html', $cleaned_html, PDO::PARAM_STR);
            $stmt_insert_site->bindParam(':edited_html', $cleaned_html, PDO::PARAM_STR); // Initially, edited_html is the same as original
            $stmt_insert_site->execute();
            $cloned_site_id = $pdo->lastInsertId();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Site clonado e scripts de rastreamento removidos com sucesso!', 'cloned_site_id' => $cloned_site_id, 'html_content' => $cleaned_html, 'title' => $page_title, 'original_url' => $url_to_clone]);

        } catch (Exception $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao clonar URL (clone_url): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao clonar site: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'save_cloned_site' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $cloned_site_id = $input['cloned_site_id'] ?? null;
        $edited_html_content = $input['edited_html_content'] ?? '';
        $facebook_pixel_id = trim($input['facebook_pixel_id'] ?? '');
        $google_analytics_id = trim($input['google_analytics_id'] ?? '');
        $custom_head_scripts = trim($input['custom_head_scripts'] ?? '');
        $new_title = trim($input['title'] ?? 'Site Clonado'); // Allow updating title
        $slug = trim($input['slug'] ?? '');
        $status = trim($input['status'] ?? 'draft');

        // Slug validation
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $slug));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Cannot publish without a slug
        if (empty($slug) && $status === 'published') {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'É necessário definir um slug (URL amigável) para publicar o site.']);
            exit;
        }

        // Check unique slug if provided
        if (!empty($slug)) {
             $stmt_check_slug = $pdo->prepare("SELECT id FROM cloned_sites WHERE slug = :slug AND id != :cloned_site_id");
             $stmt_check_slug->execute([':slug' => $slug, ':cloned_site_id' => $cloned_site_id]);
             if ($stmt_check_slug->rowCount() > 0) {
                 http_response_code(400);
                 ob_clean();
                 echo json_encode(['success' => false, 'error' => 'Este slug já está em uso por outro site.']);
                 exit;
             }
        } else {
            $slug = null;
        }

        // Debugging: Log the pixel IDs received
        error_log("API: save_cloned_site - Received for ID {$cloned_site_id}:");
        error_log("  Facebook Pixel ID: '{$facebook_pixel_id}'");
        error_log("  Google Analytics ID: '{$google_analytics_id}'");
        error_log("  Custom Head Scripts (first 100 chars): '" . substr($custom_head_scripts, 0, 100) . "'");


        if (!$cloned_site_id || empty($edited_html_content)) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do site clonado ou conteúdo HTML editado é obrigatório.']);
            exit;
        }

        try {
            // Validate ownership
            $stmt_check_owner = $pdo->prepare("SELECT id FROM cloned_sites WHERE id = :cloned_site_id AND usuario_id = :usuario_id");
            $stmt_check_owner->bindParam(':cloned_site_id', $cloned_site_id, PDO::PARAM_INT);
            $stmt_check_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_owner->execute();

            if ($stmt_check_owner->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Site clonado não encontrado ou não pertence a você.']);
                exit;
            }

            $pdo->beginTransaction();

            // Update edited_html, title, slug, and status in cloned_sites
            $stmt_update_site = $pdo->prepare("UPDATE cloned_sites SET edited_html = :edited_html, title = :title, slug = :slug, status = :status, updated_at = NOW() WHERE id = :cloned_site_id");
            $stmt_update_site->bindParam(':edited_html', $edited_html_content, PDO::PARAM_STR);
            $stmt_update_site->bindParam(':title', $new_title, PDO::PARAM_STR);
            $stmt_update_site->bindParam(':slug', $slug, $slug === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt_update_site->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt_update_site->bindParam(':cloned_site_id', $cloned_site_id, PDO::PARAM_INT);
            $stmt_update_site->execute();

            // Insert or update cloned_site_settings
            $stmt_upsert_settings = $pdo->prepare("
                INSERT INTO cloned_site_settings (cloned_site_id, facebook_pixel_id, google_analytics_id, custom_head_scripts)
                VALUES (:cloned_site_id, :facebook_pixel_id, :google_analytics_id, :custom_head_scripts)
                ON DUPLICATE KEY UPDATE
                    facebook_pixel_id = :facebook_pixel_id_update,
                    google_analytics_id = :google_analytics_id_update,
                    custom_head_scripts = :custom_head_scripts_update,
                    updated_at = NOW()
            ");
            $stmt_upsert_settings->bindParam(':cloned_site_id', $cloned_site_id, PDO::PARAM_INT);
            $stmt_upsert_settings->bindParam(':facebook_pixel_id', $facebook_pixel_id, PDO::PARAM_STR);
            $stmt_upsert_settings->bindParam(':google_analytics_id', $google_analytics_id, PDO::PARAM_STR);
            $stmt_upsert_settings->bindParam(':custom_head_scripts', $custom_head_scripts, PDO::PARAM_STR);
            $stmt_upsert_settings->bindParam(':facebook_pixel_id_update', $facebook_pixel_id, PDO::PARAM_STR);
            $stmt_upsert_settings->bindParam(':google_analytics_id_update', $google_analytics_id, PDO::PARAM_STR);
            $stmt_upsert_settings->bindParam(':custom_head_scripts_update', $custom_head_scripts, PDO::PARAM_STR);
            $stmt_upsert_settings->execute();

            $pdo->commit();

            // Debug: Verify what was actually saved for Facebook Pixel ID immediately after commit
            $stmt_verify_pixel = $pdo->prepare("SELECT facebook_pixel_id FROM cloned_site_settings WHERE cloned_site_id = :cloned_site_id");
            $stmt_verify_pixel->bindParam(':cloned_site_id', $cloned_site_id, PDO::PARAM_INT);
            $stmt_verify_pixel->execute();
            $verified_pixel_id = $stmt_verify_pixel->fetchColumn();
            error_log("API: save_cloned_site - Verified Facebook Pixel ID in DB after save: '{$verified_pixel_id}' for site ID {$cloned_site_id}");


            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Site clonado salvo com sucesso!']);

        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao salvar site clonado (save_cloned_site): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao salvar site clonado: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'get_cloned_sites') {
        try {
            $stmt = $pdo->prepare("SELECT id, original_url, title, slug, status, created_at FROM cloned_sites WHERE usuario_id = :usuario_id ORDER BY created_at DESC");
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->execute();
            $cloned_sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'cloned_sites' => $cloned_sites]);

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar sites clonados (get_cloned_sites): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar sites clonados: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'get_cloned_site_details') {
        $cloned_site_id = $_GET['cloned_site_id'] ?? null;

        if (!$cloned_site_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do site clonado é obrigatório.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    cs.id, cs.original_url, cs.title, cs.original_html, cs.edited_html, cs.slug, cs.status,
                    css.facebook_pixel_id, css.google_analytics_id, css.custom_head_scripts
                FROM cloned_sites cs
                LEFT JOIN cloned_site_settings css ON cs.id = css.cloned_site_id
                WHERE cs.id = :cloned_site_id AND cs.usuario_id = :usuario_id
            ");
            $stmt->bindParam(':cloned_site_id', $cloned_site_id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->execute();
            $site_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$site_details) {
                http_response_code(404);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Site clonado não encontrado ou não pertence a você.']);
                exit;
            }

            ob_clean();
            echo json_encode(['success' => true, 'details' => $site_details]);

        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar detalhes do site clonado (get_cloned_site_details): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar detalhes do site clonado: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action == 'delete_cloned_site' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $cloned_site_id = $input['cloned_site_id'] ?? null;

        if (!$cloned_site_id) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do site clonado é obrigatório.']);
            exit;
        }

        try {
            // Validate ownership
            $stmt_check_owner = $pdo->prepare("SELECT id FROM cloned_sites WHERE id = :cloned_site_id AND usuario_id = :usuario_id");
            $stmt_check_owner->bindParam(':cloned_site_id', $cloned_site_id, PDO::PARAM_INT);
            $stmt_check_owner->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt_check_owner->execute();

            if ($stmt_check_owner->rowCount() === 0) {
                http_response_code(403);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Site clonado não encontrado ou não pertence a você.']);
                exit;
            }

            $pdo->beginTransaction();

            // Delete settings first (if not cascaded by FK)
            $stmt_delete_settings = $pdo->prepare("DELETE FROM cloned_site_settings WHERE cloned_site_id = :cloned_site_id");
            $stmt_delete_settings->bindParam(':cloned_site_id', $cloned_site_id, PDO::PARAM_INT);
            $stmt_delete_settings->execute();

            // Delete the cloned site itself
            $stmt_delete_site = $pdo->prepare("DELETE FROM cloned_sites WHERE id = :cloned_site_id");
            $stmt_delete_site->bindParam(':cloned_site_id', $cloned_site_id, PDO::PARAM_INT);
            $stmt_delete_site->execute();

            $pdo->commit();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Site clonado excluído com sucesso.']);

        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao excluir site clonado (delete_cloned_site): " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Erro interno ao excluir site clonado: ' . $e->getMessage()]);
        }
        exit;
    }

    // =====================================================
    // CATEGORIAS DE PRODUTO (product_type_categories)
    // =====================================================

    if ($action == 'list_product_type_categories') {
        try {
            $stmt = $pdo->prepare("SELECT id, group_name, value, label, icon, ordem FROM product_type_categories WHERE usuario_id = ? ORDER BY group_name ASC, ordem ASC, label ASC");
            $stmt->execute([$usuario_id_logado]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode(['success' => true, 'items' => $items]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao listar categorias: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao listar categorias']);
        }
        exit;
    }

    if ($action == 'create_product_type_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $group_name = trim($input['group_name'] ?? '');
        $value = trim($input['value'] ?? '');
        $label = trim($input['label'] ?? '');
        $icon = isset($input['icon']) ? trim($input['icon']) : null;
        $ordem = (int)($input['ordem'] ?? 0);

        if (empty($group_name) || empty($value) || empty($label)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Preencha grupo, valor e label']);
            exit;
        }
        $value = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $value));
        if (strlen($value) > 40) $value = substr($value, 0, 40);
        if (strlen($value) < 1) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Valor inválido (use letras, números e underscore)']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO product_type_categories (usuario_id, group_name, value, label, icon, ordem) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id_logado, $group_name, $value, $label, $icon ?: null, $ordem]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Categoria criada!', 'id' => (int)$pdo->lastInsertId()]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Já existe uma categoria com esse valor']);
            } else {
                http_response_code(500);
                ob_clean();
                error_log("API: Erro ao criar categoria: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Erro ao criar categoria']);
            }
        }
        exit;
    }

    if ($action == 'update_product_type_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $group_name = trim($input['group_name'] ?? '');
        $value = trim($input['value'] ?? '');
        $label = trim($input['label'] ?? '');
        $icon = isset($input['icon']) ? trim($input['icon']) : null;
        $ordem = (int)($input['ordem'] ?? 0);

        if ($id <= 0 || empty($group_name) || empty($value) || empty($label)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
            exit;
        }
        $value = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $value));
        if (strlen($value) > 40) $value = substr($value, 0, 40);

        try {
            $stmt = $pdo->prepare("UPDATE product_type_categories SET group_name = ?, value = ?, label = ?, icon = ?, ordem = ? WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$group_name, $value, $label, $icon ?: null, $ordem, $id, $usuario_id_logado]);
            if ($stmt->rowCount() > 0) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Categoria atualizada!']);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Categoria não encontrada ou sem alteração']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Já existe outra categoria com esse valor']);
            } else {
                http_response_code(500);
                ob_clean();
                error_log("API: Erro ao atualizar categoria: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Erro ao atualizar categoria']);
            }
        }
        exit;
    }

    if ($action == 'delete_product_type_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT value FROM product_type_categories WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id_logado]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cat) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Categoria não encontrada']);
                exit;
            }
            $check = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE usuario_id = ? AND product_type = ?");
            $check->execute([$usuario_id_logado, $cat['value']]);
            if ($check->fetchColumn() > 0) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Não é possível excluir: há produtos usando esta categoria']);
                exit;
            }
            $del = $pdo->prepare("DELETE FROM product_type_categories WHERE id = ? AND usuario_id = ?");
            $del->execute([$id, $usuario_id_logado]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Categoria excluída!']);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao excluir categoria: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir categoria']);
        }
        exit;
    }

    // =====================================================
    // TAXONOMIA TEMÁTICA (product_main_categories / product_subcategories)
    // Independente de product_type / product_type_categories
    // =====================================================

    if ($action == 'list_product_main_categories') {
        try {
            $items = taxonomy_list_main_categories($pdo, (int) $usuario_id_logado);
            ob_clean();
            echo json_encode(['success' => true, 'items' => $items]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao listar categorias principais: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao listar categorias principais']);
        }
        exit;
    }

    if ($action == 'create_product_main_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $result = taxonomy_create_main_category($pdo, (int) $usuario_id_logado, $input);
        ob_clean();
        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'id' => $result['id'] ?? null,
            ]);
        }
        exit;
    }

    if ($action == 'update_product_main_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }
        $result = taxonomy_update_main_category($pdo, (int) $usuario_id_logado, $id, $input);
        ob_clean();
        echo json_encode($result['success']
            ? ['success' => true, 'message' => $result['message']]
            : ['success' => false, 'error' => $result['error']]);
        exit;
    }

    if ($action == 'delete_product_main_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }
        $result = taxonomy_delete_main_category($pdo, (int) $usuario_id_logado, $id);
        ob_clean();
        echo json_encode($result['success']
            ? ['success' => true, 'message' => $result['message']]
            : ['success' => false, 'error' => $result['error']]);
        exit;
    }

    if ($action == 'list_product_subcategories') {
        try {
            $main_category_id = isset($_GET['main_category_id']) ? (int) $_GET['main_category_id'] : 0;
            if ($main_category_id <= 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true) ?: [];
                $main_category_id = (int) ($input['main_category_id'] ?? 0);
            }
            if ($main_category_id > 0) {
                $main_assert = taxonomy_assert_main_category_owner($pdo, $main_category_id, (int) $usuario_id_logado);
                if (!$main_assert['ok']) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => $main_assert['error']]);
                    exit;
                }
            }
            $items = taxonomy_list_subcategories(
                $pdo,
                (int) $usuario_id_logado,
                $main_category_id > 0 ? $main_category_id : null
            );
            ob_clean();
            echo json_encode(['success' => true, 'items' => $items]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao listar subcategorias: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao listar subcategorias']);
        }
        exit;
    }

    if ($action == 'create_product_subcategory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $result = taxonomy_create_subcategory($pdo, (int) $usuario_id_logado, $input);
        ob_clean();
        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'id' => $result['id'] ?? null,
            ]);
        }
        exit;
    }

    if ($action == 'update_product_subcategory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }
        $result = taxonomy_update_subcategory($pdo, (int) $usuario_id_logado, $id, $input);
        ob_clean();
        echo json_encode($result['success']
            ? ['success' => true, 'message' => $result['message']]
            : ['success' => false, 'error' => $result['error']]);
        exit;
    }

    if ($action == 'delete_product_subcategory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }
        $result = taxonomy_delete_subcategory($pdo, (int) $usuario_id_logado, $id);
        ob_clean();
        echo json_encode($result['success']
            ? ['success' => true, 'message' => $result['message']]
            : ['success' => false, 'error' => $result['error']]);
        exit;
    }

    // =====================================================
    // CUPONS DE DESCONTO
    // =====================================================

    if ($action == 'list_cupons') {
        try {
            $stmt = $pdo->prepare("
                SELECT c.*, 
                    (SELECT GROUP_CONCAT(produto_id) FROM cupom_produtos WHERE cupom_id = c.id) as produto_ids
                FROM cupons c WHERE c.usuario_id = ? ORDER BY c.created_at DESC
            ");
            $stmt->execute([$usuario_id_logado]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ob_clean();
            echo json_encode(['success' => true, 'items' => $items]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao listar cupons']);
        }
        exit;
    }

    if ($action == 'create_cupom' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $codigo = strtoupper(trim(preg_replace('/[^A-Za-z0-9_-]/', '', $input['codigo'] ?? '')));
        $tipo = in_array($input['tipo'] ?? '', ['percentual', 'fixo']) ? $input['tipo'] : 'percentual';
        $valor = (float)($input['valor'] ?? 0);
        $pedido_minimo = isset($input['pedido_minimo']) && $input['pedido_minimo'] !== '' ? (float)$input['pedido_minimo'] : null;
        $max_usos = isset($input['max_usos']) && $input['max_usos'] !== '' ? (int)$input['max_usos'] : null;
        $valido_de = !empty($input['valido_de']) ? $input['valido_de'] : null;
        $valido_ate = !empty($input['valido_ate']) ? $input['valido_ate'] : null;
        $ativo = isset($input['ativo']) ? (int)$input['ativo'] : 1;
        $produto_ids = $input['produto_ids'] ?? [];

        if (strlen($codigo) < 2) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Código inválido (mín. 2 caracteres)']);
            exit;
        }
        if ($tipo === 'percentual' && ($valor < 0 || $valor > 100)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Percentual deve ser entre 0 e 100']);
            exit;
        }
        if ($tipo === 'fixo' && $valor < 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Valor fixo deve ser positivo']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO cupons (usuario_id, codigo, tipo, valor, pedido_minimo, max_usos, valido_de, valido_ate, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id_logado, $codigo, $tipo, $valor, $pedido_minimo, $max_usos, $valido_de, $valido_ate, $ativo]);
            $cupom_id = (int)$pdo->lastInsertId();
            if ($cupom_id > 0 && !empty($produto_ids) && is_array($produto_ids)) {
                $stmt_cp = $pdo->prepare("INSERT INTO cupom_produtos (cupom_id, produto_id) VALUES (?, ?)");
                foreach ($produto_ids as $pid) {
                    $pid = (int)$pid;
                    if ($pid > 0) $stmt_cp->execute([$cupom_id, $pid]);
                }
            }
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Cupom criado!', 'id' => $cupom_id]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Já existe um cupom com este código']);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro ao criar cupom']);
            }
        }
        exit;
    }

    if ($action == 'update_cupom' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $codigo = strtoupper(trim(preg_replace('/[^A-Za-z0-9_-]/', '', $input['codigo'] ?? '')));
        $tipo = in_array($input['tipo'] ?? '', ['percentual', 'fixo']) ? $input['tipo'] : 'percentual';
        $valor = (float)($input['valor'] ?? 0);
        $pedido_minimo = isset($input['pedido_minimo']) && $input['pedido_minimo'] !== '' ? (float)$input['pedido_minimo'] : null;
        $max_usos = isset($input['max_usos']) && $input['max_usos'] !== '' ? (int)$input['max_usos'] : null;
        $valido_de = !empty($input['valido_de']) ? $input['valido_de'] : null;
        $valido_ate = !empty($input['valido_ate']) ? $input['valido_ate'] : null;
        $ativo = isset($input['ativo']) ? (int)$input['ativo'] : 1;
        $produto_ids = $input['produto_ids'] ?? [];

        if ($id <= 0 || strlen($codigo) < 2) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE cupons SET codigo=?, tipo=?, valor=?, pedido_minimo=?, max_usos=?, valido_de=?, valido_ate=?, ativo=? WHERE id=? AND usuario_id=?");
            $stmt->execute([$codigo, $tipo, $valor, $pedido_minimo, $max_usos, $valido_de, $valido_ate, $ativo, $id, $usuario_id_logado]);
            if ($stmt->rowCount() > 0) {
                $pdo->prepare("DELETE FROM cupom_produtos WHERE cupom_id = ?")->execute([$id]);
                if (!empty($produto_ids) && is_array($produto_ids)) {
                    $stmt_cp = $pdo->prepare("INSERT INTO cupom_produtos (cupom_id, produto_id) VALUES (?, ?)");
                    foreach ($produto_ids as $pid) {
                        $pid = (int)$pid;
                        if ($pid > 0) $stmt_cp->execute([$id, $pid]);
                    }
                }
            }
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Cupom atualizado!']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar cupom']);
        }
        exit;
    }

    if ($action == 'delete_cupom' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM cupons WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id_logado]);
            if ($stmt->rowCount() > 0) {
                $pdo->prepare("DELETE FROM cupom_produtos WHERE cupom_id = ?")->execute([$id]);
            }
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Cupom excluído!']);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir cupom']);
        }
        exit;
    }

    // =====================================================
    // EVOLUTION API - MENSAGENS WHATSAPP
    // =====================================================

    // Listar mensagens da Evolution API
    if ($action == 'get_evolution_messages') {
        try {
            $stmt = $pdo->prepare("
                SELECT em.*, p.nome as produto_nome 
                FROM evolution_messages em 
                LEFT JOIN produtos p ON em.produto_id = p.id 
                WHERE em.usuario_id = :usuario_id 
                ORDER BY em.created_at DESC
            ");
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar mensagens Evolution: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar mensagens']);
        }
        exit;
    }

    // Obter uma mensagem específica
    if ($action == 'get_evolution_message') {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM evolution_messages WHERE id = :id AND usuario_id = :usuario_id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->execute();
            $message = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($message) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Mensagem não encontrada']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao buscar mensagem Evolution: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar mensagem']);
        }
        exit;
    }

    // Criar nova mensagem
    if ($action == 'create_evolution_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $name = trim($input['name'] ?? '');
        $produto_id = !empty($input['produto_id']) ? (int)$input['produto_id'] : null;
        $event_type = $input['event_type'] ?? '';
        $message_template = trim($input['message_template'] ?? '');
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $valid_events = ['approved', 'pending', 'rejected', 'refunded', 'charged_back', 'info_filled'];

        if (empty($name) || empty($event_type) || empty($message_template)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Preencha todos os campos obrigatórios']);
            exit;
        }

        if (!in_array($event_type, $valid_events)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de evento inválido']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO evolution_messages (usuario_id, produto_id, name, event_type, message_template, is_active) 
                VALUES (:usuario_id, :produto_id, :name, :event_type, :message_template, :is_active)
            ");
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->bindParam(':produto_id', $produto_id, $produto_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);
            $stmt->bindParam(':message_template', $message_template, PDO::PARAM_STR);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
            $stmt->execute();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Mensagem criada com sucesso!', 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao criar mensagem Evolution: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao criar mensagem']);
        }
        exit;
    }

    // Atualizar mensagem existente
    if ($action == 'update_evolution_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $produto_id = !empty($input['produto_id']) ? (int)$input['produto_id'] : null;
        $event_type = $input['event_type'] ?? '';
        $message_template = trim($input['message_template'] ?? '');
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $valid_events = ['approved', 'pending', 'rejected', 'refunded', 'charged_back', 'info_filled'];

        if ($id <= 0 || empty($name) || empty($event_type) || empty($message_template)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Preencha todos os campos obrigatórios']);
            exit;
        }

        if (!in_array($event_type, $valid_events)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de evento inválido']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE evolution_messages 
                SET produto_id = :produto_id, name = :name, event_type = :event_type, 
                    message_template = :message_template, is_active = :is_active 
                WHERE id = :id AND usuario_id = :usuario_id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->bindParam(':produto_id', $produto_id, $produto_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':event_type', $event_type, PDO::PARAM_STR);
            $stmt->bindParam(':message_template', $message_template, PDO::PARAM_STR);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
            $stmt->execute();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Mensagem atualizada com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao atualizar mensagem Evolution: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar mensagem']);
        }
        exit;
    }

    // Toggle ativo/inativo
    if ($action == 'toggle_evolution_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = (int)($input['id'] ?? 0);
        $is_active = (int)($input['is_active'] ?? 0);

        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE evolution_messages SET is_active = :is_active WHERE id = :id AND usuario_id = :usuario_id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
            $stmt->execute();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Status atualizado!']);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao toggle mensagem Evolution: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar status']);
        }
        exit;
    }

    // Excluir mensagem
    if ($action == 'delete_evolution_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM evolution_messages WHERE id = :id AND usuario_id = :usuario_id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $usuario_id_logado, PDO::PARAM_INT);
            $stmt->execute();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Mensagem excluída com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            ob_clean();
            error_log("API: Erro ao excluir mensagem Evolution: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir mensagem']);
        }
        exit;
    }

    // Enviar mensagem de teste
    if ($action == 'test_evolution_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $phone = preg_replace('/\D/', '', $input['phone'] ?? '');
        $message = $input['message'] ?? '';

        if (empty($phone) || empty($message)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Telefone e mensagem são obrigatórios']);
            exit;
        }

        // Buscar configurações da Evolution API do usuário
        $stmt_config = $pdo->prepare("SELECT evolution_server_url, evolution_api_key, evolution_instance FROM usuarios WHERE id = ?");
        $stmt_config->execute([$usuario_id_logado]);
        $config = $stmt_config->fetch(PDO::FETCH_ASSOC);

        if (empty($config['evolution_server_url']) || empty($config['evolution_api_key']) || empty($config['evolution_instance'])) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Evolution API não configurada. Vá em Integrações para configurar.']);
            exit;
        }

        // Substituir variáveis de teste
        $test_replacements = [
            '{cliente_nome}' => 'Cliente Teste',
            '{cliente_email}' => 'teste@exemplo.com',
            '{cliente_telefone}' => $phone,
            '{produto_nome}' => 'Produto de Teste',
            '{valor}' => 'R$ 99,90',
            '{transacao_id}' => 'TEST-' . time(),
            '{data_compra}' => date('d/m/Y H:i')
        ];
        
        $message = str_replace(array_keys($test_replacements), array_values($test_replacements), $message);

        // Enviar via Evolution API
        $url = rtrim($config['evolution_server_url'], '/') . '/message/sendText/' . $config['evolution_instance'];
        
        $payload = [
            'number' => $phone,
            'text' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $config['evolution_api_key']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            error_log("Evolution API Test Error (cURL): " . $curl_error);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro de conexão: ' . $curl_error]);
            exit;
        }

        $response_data = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso!']);
        } else {
            error_log("Evolution API Test Error: HTTP $http_code - " . $response);
            $error_msg = $response_data['message'] ?? $response_data['error'] ?? 'Erro ao enviar mensagem';
            ob_clean();
            echo json_encode(['success' => false, 'error' => $error_msg]);
        }
        exit;
    }

    // ========================================
    // AÇÕES DE GERENCIAMENTO DE VENDAS (EDITAR/DELETAR)
    // ========================================

    // Atualizar venda
    if ($action == 'update_venda' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $venda_id = $input['venda_id'] ?? null;
        $comprador_nome = trim($input['comprador_nome'] ?? '');
        $comprador_email = trim($input['comprador_email'] ?? '');
        $comprador_telefone = trim($input['comprador_telefone'] ?? '');
        $status_pagamento = $input['status_pagamento'] ?? null;

        if (!$venda_id || !is_numeric($venda_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID da venda é obrigatório.']);
            exit;
        }

        try {
            // Verifica se a venda pertence a um produto do infoprodutor
            $stmt_check = $pdo->prepare("
                SELECT v.id FROM vendas v 
                JOIN produtos p ON v.produto_id = p.id 
                WHERE v.id = ? AND p.usuario_id = ?
            ");
            $stmt_check->execute([$venda_id, $usuario_id_logado]);
            
            if (!$stmt_check->fetch()) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Venda não encontrada ou não pertence a você.']);
                exit;
            }

            // Atualiza a venda
            $stmt_update = $pdo->prepare("
                UPDATE vendas SET 
                    comprador_nome = ?,
                    comprador_email = ?,
                    comprador_telefone = ?,
                    status_pagamento = ?
                WHERE id = ?
            ");
            $stmt_update->execute([$comprador_nome, $comprador_email, $comprador_telefone, $status_pagamento, $venda_id]);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Venda atualizada com sucesso.']);
            exit;

        } catch (PDOException $e) {
            error_log("Erro ao atualizar venda: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao atualizar venda.']);
            exit;
        }
    }

    // Deletar venda
    if ($action == 'delete_venda' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $venda_id = $input['venda_id'] ?? null;

        if (!$venda_id || !is_numeric($venda_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID da venda é obrigatório.']);
            exit;
        }

        try {
            // Verifica se a venda pertence a um produto do infoprodutor
            $stmt_check = $pdo->prepare("
                SELECT v.id FROM vendas v 
                JOIN produtos p ON v.produto_id = p.id 
                WHERE v.id = ? AND p.usuario_id = ?
            ");
            $stmt_check->execute([$venda_id, $usuario_id_logado]);
            
            if (!$stmt_check->fetch()) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Venda não encontrada ou não pertence a você.']);
                exit;
            }

            // Deleta a venda
            $stmt_delete = $pdo->prepare("DELETE FROM vendas WHERE id = ?");
            $stmt_delete->execute([$venda_id]);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Venda excluída com sucesso.']);
            exit;

        } catch (PDOException $e) {
            error_log("Erro ao deletar venda: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao excluir venda.']);
            exit;
        }
    }

    // Deletar acesso manual (remove acesso, progresso e usuário)
    if ($action == 'delete_manual_access' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $acesso_id = $input['acesso_id'] ?? null;
        $email = $input['email'] ?? null;

        if (!$acesso_id || !is_numeric($acesso_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do acesso é obrigatório.']);
            exit;
        }

        try {
            // Verifica se o acesso pertence a um produto do infoprodutor e é manual
            $stmt_check = $pdo->prepare("
                SELECT aa.id, aa.aluno_email, aa.produto_id 
                FROM alunos_acessos aa 
                JOIN produtos p ON aa.produto_id = p.id 
                WHERE aa.id = ? AND p.usuario_id = ? AND aa.criado_manualmente = 1
            ");
            $stmt_check->execute([$acesso_id, $usuario_id_logado]);
            $acesso = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$acesso) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Acesso não encontrado, não pertence a você ou não é um acesso manual.']);
                exit;
            }
            
            $aluno_email = $acesso['aluno_email'];
            
            $pdo->beginTransaction();
            
            // 1. Deletar progresso do aluno nas aulas do produto
            $stmt_delete_progresso = $pdo->prepare("
                DELETE ap FROM aluno_progresso ap
                JOIN aulas a ON ap.aula_id = a.id
                JOIN modulos m ON a.modulo_id = m.id
                JOIN cursos c ON m.curso_id = c.id
                WHERE ap.aluno_email = ? AND c.produto_id = ?
            ");
            $stmt_delete_progresso->execute([$aluno_email, $acesso['produto_id']]);
            
            // 2. Deletar o acesso
            $stmt_delete_acesso = $pdo->prepare("DELETE FROM alunos_acessos WHERE id = ?");
            $stmt_delete_acesso->execute([$acesso_id]);
            
            // 3. Verificar se o aluno tem outros acessos
            $stmt_outros_acessos = $pdo->prepare("SELECT COUNT(*) FROM alunos_acessos WHERE aluno_email = ?");
            $stmt_outros_acessos->execute([$aluno_email]);
            $outros_acessos = $stmt_outros_acessos->fetchColumn();
            
            // 4. Se não tem outros acessos, deletar o usuário também
            if ($outros_acessos == 0) {
                $stmt_delete_user = $pdo->prepare("DELETE FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
                $stmt_delete_user->execute([$aluno_email]);
            }
            
            $pdo->commit();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Cliente excluído com sucesso.']);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erro ao deletar acesso manual: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao excluir cliente.']);
            exit;
        }
    }

    // ========================================
    // AÇÕES DE GERENCIAMENTO DE CLIENTES
    // ========================================

    // Listar clientes de um curso
    if ($action == 'list_clientes') {
        $produto_id = $_GET['produto_id'] ?? null;

        if (!$produto_id || !is_numeric($produto_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do produto é obrigatório.']);
            exit;
        }

        try {
            // Valida que o produto pertence ao infoprodutor
            $stmt_produto = $pdo->prepare("SELECT id, nome FROM produtos WHERE id = ? AND usuario_id = ? AND tipo_entrega = 'area_membros'");
            $stmt_produto->execute([$produto_id, $usuario_id_logado]);
            $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou não pertence a você.']);
                exit;
            }

            // Busca alunos do produto com data de expiração
            $stmt = $pdo->prepare("
                SELECT 
                    aa.aluno_email,
                    aa.data_concessao,
                    aa.data_expiracao,
                    u.nome,
                    p.nome as produto_nome,
                    p.id as produto_id
                FROM alunos_acessos aa
                JOIN produtos p ON aa.produto_id = p.id
                LEFT JOIN usuarios u ON u.usuario = aa.aluno_email AND u.tipo = 'usuario'
                WHERE p.id = ? AND p.usuario_id = ?
                ORDER BY aa.data_concessao DESC
            ");
            $stmt->execute([$produto_id, $usuario_id_logado]);
            $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcula progresso para cada aluno
            foreach ($alunos as &$aluno) {
                // Total de aulas do curso
                $stmt_total = $pdo->prepare("
                    SELECT COUNT(*) as total
                    FROM aulas a
                    JOIN modulos m ON a.modulo_id = m.id
                    JOIN cursos c ON m.curso_id = c.id
                    WHERE c.produto_id = ?
                ");
                $stmt_total->execute([$produto_id]);
                $total_aulas = $stmt_total->fetchColumn();

                // Aulas concluídas pelo aluno
                $stmt_concluidas = $pdo->prepare("
                    SELECT COUNT(*) as concluidas
                    FROM aluno_progresso ap
                    JOIN aulas a ON ap.aula_id = a.id
                    JOIN modulos m ON a.modulo_id = m.id
                    JOIN cursos c ON m.curso_id = c.id
                    WHERE ap.aluno_email = ? AND c.produto_id = ?
                ");
                $stmt_concluidas->execute([$aluno['aluno_email'], $produto_id]);
                $aulas_concluidas = $stmt_concluidas->fetchColumn();

                // Calcula percentual
                $progresso_percentual = $total_aulas > 0 ? round(($aulas_concluidas / $total_aulas) * 100) : 0;

                $aluno['progresso_percentual'] = $progresso_percentual;
                $aluno['total_aulas'] = (int)$total_aulas;
                $aluno['aulas_concluidas'] = (int)$aulas_concluidas;
                $aluno['nome'] = $aluno['nome'] ?? $aluno['aluno_email'];
                
                // Determinar tipo de plano baseado na data de expiração
                if ($aluno['data_expiracao'] === null) {
                    $aluno['tipo_plano'] = 'vitalicio';
                    $aluno['tipo_plano_label'] = 'Vitalício';
                    $aluno['acesso_expirado'] = false;
                } else {
                    $data_exp = new DateTime($aluno['data_expiracao']);
                    $agora = new DateTime();
                    $aluno['acesso_expirado'] = $agora > $data_exp;
                    
                    // Calcular diferença para determinar tipo aproximado
                    $data_concessao = new DateTime($aluno['data_concessao']);
                    $diff = $data_concessao->diff($data_exp)->days;
                    
                    if ($diff <= 35) {
                        $aluno['tipo_plano'] = 'mensal';
                        $aluno['tipo_plano_label'] = 'Mensal';
                    } elseif ($diff <= 190) {
                        $aluno['tipo_plano'] = 'semestral';
                        $aluno['tipo_plano_label'] = 'Semestral';
                    } else {
                        $aluno['tipo_plano'] = 'anual';
                        $aluno['tipo_plano_label'] = 'Anual';
                    }
                }
            }

            ob_clean();
            echo json_encode(['success' => true, 'alunos' => $alunos, 'produto' => $produto]);
            exit;
        } catch (PDOException $e) {
            error_log("Erro ao listar clientes: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao listar clientes.']);
            exit;
        }
    }

    // Criar/adicionar cliente a um curso
    if ($action == 'create_cliente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = strtolower(trim($input['email'] ?? ''));
        $nome = trim($input['nome'] ?? '');
        $produto_id = $input['produto_id'] ?? null;
        $tipo_acesso = $input['tipo_acesso'] ?? 'vitalicio';
        $senha_custom = trim($input['senha'] ?? '');
        $enviar_email = isset($input['enviar_email']) && $input['enviar_email'] === true;

        // Validar tipo de acesso
        $tipos_validos = ['mensal', 'semestral', 'anual', 'vitalicio'];
        if (!in_array($tipo_acesso, $tipos_validos)) {
            $tipo_acesso = 'vitalicio';
        }

        // Validações
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Email inválido.']);
            exit;
        }

        if (empty($nome)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Nome é obrigatório.']);
            exit;
        }

        if (!$produto_id || !is_numeric($produto_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Selecione um curso.']);
            exit;
        }

        try {
            // Valida que o produto pertence ao infoprodutor
            $stmt_produto = $pdo->prepare("SELECT id, nome FROM produtos WHERE id = ? AND usuario_id = ? AND tipo_entrega = 'area_membros'");
            $stmt_produto->execute([$produto_id, $usuario_id_logado]);
            $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou não pertence a você.']);
                exit;
            }

            // Verifica se aluno já tem acesso ao produto
            $stmt_check = $pdo->prepare("SELECT id FROM alunos_acessos WHERE aluno_email = ? AND produto_id = ?");
            $stmt_check->execute([$email, $produto_id]);
            if ($stmt_check->fetch()) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Este cliente já possui acesso a este curso.']);
                exit;
            }

            // Inicia transação
            $pdo->beginTransaction();

            // Calcular data de expiração baseada no tipo de acesso
            $data_expiracao = null;
            if ($tipo_acesso !== 'vitalicio') {
                $data = new DateTime();
                switch ($tipo_acesso) {
                    case 'mensal':
                        $data->modify('+30 days');
                        break;
                    case 'semestral':
                        $data->modify('+180 days');
                        break;
                    case 'anual':
                        $data->modify('+365 days');
                        break;
                }
                $data_expiracao = $data->format('Y-m-d H:i:s');
            }

            // Insere acesso do aluno com data de expiração
            $stmt_insert = $pdo->prepare("INSERT INTO alunos_acessos (aluno_email, produto_id, data_expiracao, criado_manualmente) VALUES (?, ?, ?, 1)");
            $stmt_insert->execute([$email, $produto_id, $data_expiracao]);

            // Verifica se usuário existe
            $stmt_user = $pdo->prepare("SELECT id, senha, nome FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
            $stmt_user->execute([$email]);
            $existing_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

            $is_new_user = false;
            $generated_password = null;

            if (!$existing_user) {
                // Cliente NOVO - cria usuário com senha (personalizada ou gerada)
                if (!empty($senha_custom)) {
                    $generated_password = $senha_custom;
                } else {
                    $generated_password = substr(bin2hex(random_bytes(4)), 0, 8); // Senha de 8 caracteres
                }
                $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);

                $stmt_create_user = $pdo->prepare("INSERT INTO usuarios (usuario, nome, senha, tipo) VALUES (?, ?, ?, 'usuario')");
                $stmt_create_user->execute([$email, $nome, $hashed_password]);
                $is_new_user = true;
            } else {
                // Cliente existente - atualiza nome e senha se fornecida
                if (!empty($senha_custom)) {
                    $hashed_password = password_hash($senha_custom, PASSWORD_DEFAULT);
                    $stmt_update = $pdo->prepare("UPDATE usuarios SET nome = ?, senha = ? WHERE id = ?");
                    $stmt_update->execute([$nome, $hashed_password, $existing_user['id']]);
                    $generated_password = $senha_custom;
                } elseif (empty($existing_user['nome']) || $existing_user['nome'] !== $nome) {
                    $stmt_update_nome = $pdo->prepare("UPDATE usuarios SET nome = ? WHERE id = ?");
                    $stmt_update_nome->execute([$nome, $existing_user['id']]);
                }
            }

            $pdo->commit();

            // Envia email se solicitado
            $email_enviado = false;
            if ($enviar_email) {
                try {
                    // Busca configurações SMTP
                    $stmt_smtp = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name')");
                    $stmt_smtp->execute();
                    $smtp_configs = $stmt_smtp->fetchAll(PDO::FETCH_KEY_PAIR);

                    if (!empty($smtp_configs['smtp_host']) && !empty($smtp_configs['smtp_username'])) {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = $smtp_configs['smtp_host'];
                        $mail->Port = (int)($smtp_configs['smtp_port'] ?? 587);
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtp_configs['smtp_username'];
                        $mail->Password = $smtp_configs['smtp_password'];
                        
                        if (($smtp_configs['smtp_encryption'] ?? 'tls') === 'ssl') {
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        } else {
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        }
                        
                        $mail->SMTPOptions = [
                            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
                        ];
                        
                        $mail->CharSet = 'UTF-8';
                        $mail->setFrom($smtp_configs['smtp_username'], $smtp_configs['smtp_from_name'] ?? 'Área de Membros');
                        $mail->addAddress($email, $nome);
                        $mail->Subject = 'Seu acesso ao curso: ' . $produto['nome'];
                        $mail->isHTML(true);
                        
                        $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/member_login';
                        
                        $body = "<html><body style='font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;'>";
                        $body .= "<div style='max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px;'>";
                        $body .= "<h2 style='color: #333;'>Olá, {$nome}!</h2>";
                        $body .= "<p>Você recebeu acesso ao curso: <strong>{$produto['nome']}</strong></p>";
                        
                        if ($is_new_user && $generated_password) {
                            $body .= "<p><strong>Seus dados de acesso:</strong></p>";
                            $body .= "<p>Email: <strong>{$email}</strong></p>";
                            $body .= "<p>Senha: <strong>{$generated_password}</strong></p>";
                            $body .= "<p style='color: #666; font-size: 12px;'>Recomendamos que você altere sua senha após o primeiro acesso.</p>";
                        }
                        
                        $body .= "<p style='margin-top: 20px;'><a href='{$login_url}' style='background-color: #32e768; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Acessar Área de Membros</a></p>";
                        $body .= "</div></body></html>";
                        
                        $mail->Body = $body;
                        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $body));
                        
                        $mail->send();
                        $email_enviado = true;
                    }
                } catch (Exception $e) {
                    error_log("Erro ao enviar email de acesso: " . $e->getMessage());
                }
            }

            ob_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Cliente adicionado com sucesso!' . ($email_enviado ? ' Email de acesso enviado.' : ''),
                'email_enviado' => $email_enviado,
                'is_new_user' => $is_new_user
            ]);
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro ao criar cliente: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao adicionar cliente.']);
            exit;
        }
    }

    // Remover acesso de cliente
    if ($action == 'remove_cliente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = trim($input['email'] ?? '');
        $produto_id = $input['produto_id'] ?? null;

        if (empty($email)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Email do cliente é obrigatório.']);
            exit;
        }

        if (!$produto_id || !is_numeric($produto_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do produto é obrigatório.']);
            exit;
        }

        try {
            // Valida que o produto pertence ao infoprodutor
            $stmt_produto = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
            $stmt_produto->execute([$produto_id, $usuario_id_logado]);
            $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou não pertence a você.']);
                exit;
            }

            // Remove acesso
            $stmt_remove = $pdo->prepare("DELETE FROM alunos_acessos WHERE aluno_email = ? AND produto_id = ?");
            $stmt_remove->execute([$email, $produto_id]);

            if ($stmt_remove->rowCount() > 0) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Acesso do cliente removido com sucesso.']);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Cliente não possui acesso a este curso.']);
            }
            exit;

        } catch (PDOException $e) {
            error_log("Erro ao remover cliente: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao remover acesso.']);
            exit;
        }
    }

    // Alterar plano de cliente
    if ($action == 'update_cliente_plano' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = strtolower(trim($input['email'] ?? ''));
        $produto_id = $input['produto_id'] ?? null;
        $tipo_acesso = $input['tipo_acesso'] ?? 'vitalicio';

        // Validar tipo de acesso
        $tipos_validos = ['mensal', 'semestral', 'anual', 'vitalicio'];
        if (!in_array($tipo_acesso, $tipos_validos)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de plano inválido.']);
            exit;
        }

        if (empty($email)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Email do cliente é obrigatório.']);
            exit;
        }

        if (!$produto_id || !is_numeric($produto_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do produto é obrigatório.']);
            exit;
        }

        try {
            // Valida que o produto pertence ao infoprodutor
            $stmt_produto = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
            $stmt_produto->execute([$produto_id, $usuario_id_logado]);
            $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou não pertence a você.']);
                exit;
            }

            // Verifica se o cliente tem acesso ao produto
            $stmt_check = $pdo->prepare("SELECT id FROM alunos_acessos WHERE aluno_email = ? AND produto_id = ?");
            $stmt_check->execute([$email, $produto_id]);
            $acesso = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$acesso) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Cliente não possui acesso a este curso.']);
                exit;
            }

            // Calcular nova data de expiração baseada no tipo de acesso
            $data_expiracao = null;
            if ($tipo_acesso !== 'vitalicio') {
                $data = new DateTime();
                switch ($tipo_acesso) {
                    case 'mensal':
                        $data->modify('+30 days');
                        break;
                    case 'semestral':
                        $data->modify('+180 days');
                        break;
                    case 'anual':
                        $data->modify('+365 days');
                        break;
                }
                $data_expiracao = $data->format('Y-m-d H:i:s');
            }

            // Atualiza o plano do cliente
            $stmt_update = $pdo->prepare("UPDATE alunos_acessos SET data_expiracao = ? WHERE aluno_email = ? AND produto_id = ?");
            $stmt_update->execute([$data_expiracao, $email, $produto_id]);

            $plano_label = [
                'vitalicio' => 'Vitalício',
                'mensal' => 'Mensal',
                'semestral' => 'Semestral',
                'anual' => 'Anual'
            ][$tipo_acesso];

            ob_clean();
            echo json_encode([
                'success' => true, 
                'message' => "Plano alterado para {$plano_label} com sucesso!"
            ]);
            exit;

        } catch (PDOException $e) {
            error_log("Erro ao alterar plano do cliente: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao alterar plano.']);
            exit;
        }
    }

    // ========================================
    // AÇÕES DE GERENCIAMENTO DE OFERTAS
    // ========================================

    // Criar nova oferta
    if ($action == 'create_oferta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $produto_id = $input['produto_id'] ?? null;
        $nome = trim($input['nome'] ?? '');
        $preco = floatval($input['preco'] ?? 0);
        $tipo_acesso = $input['tipo_acesso'] ?? 'vitalicio';
        
        // Validar tipo de acesso
        $tipos_validos = ['mensal', 'semestral', 'anual', 'vitalicio'];
        if (!in_array($tipo_acesso, $tipos_validos)) {
            $tipo_acesso = 'vitalicio';
        }

        if (!$produto_id || !is_numeric($produto_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do produto é obrigatório.']);
            exit;
        }

        if (strlen($nome) < 3) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'O nome da oferta deve ter pelo menos 3 caracteres.']);
            exit;
        }

        if ($preco <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'O preço deve ser maior que zero.']);
            exit;
        }

        try {
            // Valida que o produto pertence ao infoprodutor
            $stmt_produto = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
            $stmt_produto->execute([$produto_id, $usuario_id_logado]);
            
            if (!$stmt_produto->fetch()) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou não pertence a você.']);
                exit;
            }

            // Gerar hash único para a oferta
            $hash = bin2hex(random_bytes(16));

            // Inserir oferta com tipo de acesso
            $stmt_insert = $pdo->prepare("INSERT INTO produto_ofertas (produto_id, nome, preco, tipo_acesso, hash, ativo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt_insert->execute([$produto_id, $nome, $preco, $tipo_acesso, $hash]);

            $oferta_id = $pdo->lastInsertId();

            ob_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Oferta criada com sucesso!',
                'oferta' => [
                    'id' => $oferta_id,
                    'nome' => $nome,
                    'preco' => $preco,
                    'tipo_acesso' => $tipo_acesso,
                    'hash' => $hash,
                    'ativo' => 1
                ]
            ]);
            exit;

        } catch (PDOException $e) {
            error_log("Erro ao criar oferta: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao criar oferta.']);
            exit;
        }
    }

    // Atualizar oferta existente
    if ($action == 'update_oferta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $oferta_id = $input['oferta_id'] ?? null;
        $produto_id = $input['produto_id'] ?? null;
        $nome = trim($input['nome'] ?? '');
        $preco = floatval($input['preco'] ?? 0);
        $tipo_acesso = $input['tipo_acesso'] ?? 'vitalicio';
        $ativo = isset($input['ativo']) ? (int)$input['ativo'] : 1;
        
        // Validar tipo de acesso
        $tipos_validos = ['mensal', 'semestral', 'anual', 'vitalicio'];
        if (!in_array($tipo_acesso, $tipos_validos)) {
            $tipo_acesso = 'vitalicio';
        }

        if (!$oferta_id || !is_numeric($oferta_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID da oferta é obrigatório.']);
            exit;
        }

        if (strlen($nome) < 3) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'O nome da oferta deve ter pelo menos 3 caracteres.']);
            exit;
        }

        if ($preco <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'O preço deve ser maior que zero.']);
            exit;
        }

        try {
            // Valida que a oferta pertence a um produto do infoprodutor
            $stmt_check = $pdo->prepare("
                SELECT o.id FROM produto_ofertas o 
                JOIN produtos p ON o.produto_id = p.id 
                WHERE o.id = ? AND p.usuario_id = ?
            ");
            $stmt_check->execute([$oferta_id, $usuario_id_logado]);
            
            if (!$stmt_check->fetch()) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Oferta não encontrada ou não pertence a você.']);
                exit;
            }

            // Atualizar oferta com tipo de acesso
            $stmt_update = $pdo->prepare("UPDATE produto_ofertas SET nome = ?, preco = ?, tipo_acesso = ?, ativo = ? WHERE id = ?");
            $stmt_update->execute([$nome, $preco, $tipo_acesso, $ativo, $oferta_id]);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Oferta atualizada com sucesso!']);
            exit;

        } catch (PDOException $e) {
            error_log("Erro ao atualizar oferta: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao atualizar oferta.']);
            exit;
        }
    }

    // Excluir oferta
    if ($action == 'delete_oferta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $oferta_id = $input['oferta_id'] ?? null;

        if (!$oferta_id || !is_numeric($oferta_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID da oferta é obrigatório.']);
            exit;
        }

        try {
            // Valida que a oferta pertence a um produto do infoprodutor
            $stmt_check = $pdo->prepare("
                SELECT o.id FROM produto_ofertas o 
                JOIN produtos p ON o.produto_id = p.id 
                WHERE o.id = ? AND p.usuario_id = ?
            ");
            $stmt_check->execute([$oferta_id, $usuario_id_logado]);
            
            if (!$stmt_check->fetch()) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Oferta não encontrada ou não pertence a você.']);
                exit;
            }

            // Excluir oferta
            $stmt_delete = $pdo->prepare("DELETE FROM produto_ofertas WHERE id = ?");
            $stmt_delete->execute([$oferta_id]);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Oferta excluída com sucesso!']);
            exit;

        } catch (PDOException $e) {
            error_log("Erro ao excluir oferta: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao excluir oferta.']);
            exit;
        }
    }

    // Listar ofertas de um produto
    if ($action == 'list_ofertas') {
        $produto_id = $_GET['produto_id'] ?? null;

        if (!$produto_id || !is_numeric($produto_id)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do produto é obrigatório.']);
            exit;
        }

        try {
            // Valida que o produto pertence ao infoprodutor
            $stmt_produto = $pdo->prepare("SELECT id, checkout_hash FROM produtos WHERE id = ? AND usuario_id = ?");
            $stmt_produto->execute([$produto_id, $usuario_id_logado]);
            $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Produto não encontrado ou não pertence a você.']);
                exit;
            }

            // Buscar ofertas
            $stmt_ofertas = $pdo->prepare("SELECT * FROM produto_ofertas WHERE produto_id = ? ORDER BY data_criacao DESC");
            $stmt_ofertas->execute([$produto_id]);
            $ofertas = $stmt_ofertas->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode([
                'success' => true,
                'ofertas' => $ofertas,
                'checkout_hash' => $produto['checkout_hash']
            ]);
            exit;

        } catch (PDOException $e) {
            error_log("Erro ao listar ofertas: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro interno ao listar ofertas.']);
            exit;
        }
    }

    http_response_code(400);
    ob_clean(); // Limpa o buffer antes de enviar o JSON
    echo json_encode(['error' => 'Ação inválida']);

} catch (Throwable $e) { // Captura Exception e Error
    http_response_code(500);
    error_log('API: Erro Fatal na API do Usuário: ' . $e->getMessage() . ' no arquivo ' . $e->getFile() . ' na linha ' . $e->getLine());
    ob_clean(); // Limpa o buffer antes de enviar o JSON
    echo json_encode(['error' => 'Ocorreu um erro interno no servidor. Verifique os logs de erro em ' . __DIR__ . '/api_errors.log para mais detalhes.']);
}
