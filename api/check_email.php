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
    // Verifica se o e-mail já existe na tabela de usuários (clientes)
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
} catch (PDOException $e) {
    error_log("check_email.php Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao verificar e-mail']);
}
