<?php
/**
 * API para recuperação de senha.
 * Gera uma nova senha e envia por e-mail para o usuário.
 */

header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/security_helper.php';

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$phpmailer_path = __DIR__ . '/../PHPMailer/src/';
require_once $phpmailer_path . 'Exception.php';
require_once $phpmailer_path . 'PHPMailer.php';
require_once $phpmailer_path . 'SMTP.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'error' => 'Requisição inválida.']);
    exit;
}
$csrf = $input['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'Sessão expirada ou token inválido. Atualize a página e tente novamente.']);
    exit;
}
$email = trim($input['email'] ?? '');
$user_type = $input['user_type'] ?? 'usuario'; // 'usuario' para membros, 'all' para qualquer tipo

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Por favor, informe um e-mail válido.']);
    exit;
}

try {
    // Busca o usuário pelo e-mail
    if ($user_type === 'all') {
        // Para login.php - busca qualquer tipo de usuário
        $stmt = $pdo->prepare("SELECT id, nome, usuario, tipo FROM usuarios WHERE usuario = ?");
    } else {
        // Para member_login.php - busca apenas usuários do tipo 'usuario' (clientes/alunos)
        $stmt = $pdo->prepare("SELECT id, nome, usuario, tipo FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
    }
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Por segurança, não revelamos se o e-mail existe ou não
        echo json_encode(['success' => true, 'message' => 'Se o e-mail estiver cadastrado, você receberá uma nova senha em instantes.']);
        exit;
    }
    
    // Gera nova senha — só grava no banco após o e-mail ser enviado (evita bloquear o usuário se o SMTP falhar)
    $new_password = bin2hex(random_bytes(6)); // 12 caracteres
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $nome_plataforma = getSystemSetting('nome_plataforma', 'Hub SinergAI');

    // Mesmas chaves e padrões que api.php / notifications_api (TLS + 587 por padrão)
    $stmt_smtp = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name')");
    $stmt_smtp->execute();
    $smtp_raw = $stmt_smtp->fetchAll(PDO::FETCH_KEY_PAIR);

    $encRaw = strtolower(trim((string)($smtp_raw['smtp_encryption'] ?? '')));
    if ($encRaw === '') {
        $encRaw = 'tls';
    }
    $port = (int)($smtp_raw['smtp_port'] ?? 587);
    if ($port <= 0) {
        $port = 587;
    }

    $smtp_config = [
        'host' => trim((string)($smtp_raw['smtp_host'] ?? '')),
        'port' => $port,
        'username' => trim((string)($smtp_raw['smtp_username'] ?? '')),
        'password' => (string)($smtp_raw['smtp_password'] ?? ''),
        'encryption' => $encRaw,
        'from_email' => trim((string)($smtp_raw['smtp_from_email'] ?? '')),
        'from_name' => trim((string)($smtp_raw['smtp_from_name'] ?? '')) ?: $nome_plataforma,
    ];
    
    // Monta URL base
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = $protocol . '://' . $host;
    
    // Busca URL de login da área de membros
    $stmt_login_url = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'member_area_login_url'");
    $login_url = $stmt_login_url->fetchColumn() ?: '';
    
    if (empty($login_url)) {
        $login_url = $base_url . '/member_login';
    } elseif (strpos($login_url, 'http') !== 0) {
        // Se a URL de login não é absoluta, torna absoluta
        $login_url = $base_url . '/' . ltrim($login_url, '/');
    }
    
    $mail = new PHPMailer(true);

    try {
        if (empty($smtp_config['host']) || empty($smtp_config['username']) || empty($smtp_config['password'])) {
            error_log('forgot_password: SMTP incompleto (host/username/password). Hostinger e similares exigem SMTP configurado em Integrações / configuracoes.');
            echo json_encode([
                'success' => false,
                'error' => 'Envio de e-mail não está configurado no sistema. Entre em contato com o suporte.',
            ]);
            exit;
        }

        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->Port = $smtp_config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_config['password'];
        $mail->CharSet = 'UTF-8';

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        if ($smtp_config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp_config['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        // Evita "Sender address rejected": remetente alinhado ao login SMTP (igual api.php)
        $fromAddr = $smtp_config['from_email'];
        if ($fromAddr === '' || !filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) {
            $fromAddr = $smtp_config['username'];
        }
        $mail->setFrom($fromAddr, $smtp_config['from_name']);

        $mail->addAddress($email, $user['nome']);
        
        $mail->isHTML(true);
        $mail->Subject = 'Recuperação de Senha - ' . $nome_plataforma;
        
        // Template do e-mail
        $mail->Body = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: Arial, sans-serif;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden;">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 30px 40px; background: linear-gradient(135deg, #1e1e2f 0%, #2d2d44 100%);">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: bold;">' . htmlspecialchars($nome_plataforma) . '</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 24px;">Recuperação de Senha</h2>
                            
                            <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Olá, <strong>' . htmlspecialchars($user['nome']) . '</strong>!
                            </p>
                            
                            <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                Recebemos uma solicitação para redefinir sua senha. Sua nova senha de acesso é:
                            </p>
                            
                            <!-- Password Box -->
                            <div style="background-color: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 30px;">
                                <p style="color: #64748b; font-size: 14px; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 1px;">Sua Nova Senha</p>
                                <p style="color: #1e293b; font-size: 28px; font-weight: bold; margin: 0; font-family: monospace; letter-spacing: 3px;">' . htmlspecialchars($new_password) . '</p>
                            </div>
                            
                            <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                Por segurança, recomendamos que você altere esta senha após o primeiro acesso.
                            </p>
                            
                            <!-- Button -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <a href="' . htmlspecialchars($login_url) . '" style="display: inline-block; background-color: #22c55e; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold; padding: 16px 40px; border-radius: 8px;">Acessar Minha Conta</a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0; text-align: center;">
                                Se você não solicitou esta alteração, por favor ignore este e-mail ou entre em contato conosco.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 30px 40px; background-color: #f8fafc; border-top: 1px solid #e2e8f0;">
                            <p style="color: #94a3b8; font-size: 13px; margin: 0;">
                                ' . htmlspecialchars($nome_plataforma) . ' &copy; ' . date('Y') . '. Todos os direitos reservados.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        $mail->AltBody = "Olá, {$user['nome']}!\n\nSua nova senha de acesso é: {$new_password}\n\nAcesse: {$login_url}\n\nSe você não solicitou esta alteração, ignore este e-mail.";
        
        $mail->send();

        $stmt_update = $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
        $stmt_update->execute([$hashed_password, $user['id']]);

        echo json_encode(['success' => true, 'message' => 'Uma nova senha foi enviada para o seu e-mail.']);
    } catch (Exception $e) {
        error_log('forgot_password PHPMailer: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível enviar o e-mail. Verifique SMTP (host, porta, TLS/SSL e senha de app) nas configurações do painel ou tente mais tarde.',
        ]);
    }
    
} catch (PDOException $e) {
    error_log("forgot_password.php Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar solicitação.']);
}
