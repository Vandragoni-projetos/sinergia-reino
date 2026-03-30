<?php
/**
 * API pública para verificar se um e-mail já está cadastrado no sistema.
 * Usado no checkout para informar ao cliente que a senha será mantida.
 */

header('Content-Type: application/json');

// Desabilitar exibição de erros HTML
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/config.php';

// Apenas aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Lê o JSON do body
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'E-mail inválido']);
    exit;
}

try {
    // member_register: coluna usuario é UNIQUE para qualquer tipo — bloquear se já existir infoprodutor/admin também.
    // checkout (padrão): só clientes (tipo usuario) para mensagem de “mesma senha”.
    $for_member_register = !empty($input['for_member_register']) || (($input['scope'] ?? '') === 'member_register');

    if ($for_member_register) {
        $stmt = $pdo->prepare("SELECT id, tipo FROM usuarios WHERE usuario = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $msg = ($user['tipo'] ?? '') === 'usuario'
                ? 'E-mail já cadastrado na área de membros.'
                : 'Este e-mail já está em uso por uma conta de infoprodutor ou administrador. Use outro e-mail para criar conta de aluno.';
            echo json_encode(['success' => true, 'exists' => true, 'message' => $msg]);
        } else {
            echo json_encode(['success' => true, 'exists' => false]);
        }
    } else {
        $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode([
                'success' => true,
                'exists' => true,
                'message' => 'E-mail já cadastrado. Sua senha de acesso será a mesma utilizada anteriormente.'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'exists' => false
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("check_email.php Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao verificar e-mail']);
}
