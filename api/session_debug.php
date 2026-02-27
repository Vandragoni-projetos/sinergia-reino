<?php
/**
 * Diagnóstico de sessão única — REMOVER em produção após testar
 * Acesso: /api/session_debug?key=SESSION_DEBUG_2024
 * Carrega só o necessário para não disparar enforce_single_session (que redirecionaria).
 */
require_once __DIR__ . '/../config/env_loader.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'gatewaypro'));
define('DB_PASS', env('DB_PASS', 'gatewaypro_secret_2024'));
define('DB_NAME', env('DB_NAME', 'checkout'));

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed', 'message' => $e->getMessage()]);
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$secret = env('SESSION_DEBUG_KEY', 'SESSION_DEBUG_2024');
$key = $_GET['key'] ?? '';
$allowed = ($key === $secret) || (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && ($_SESSION['tipo'] ?? '') === 'admin');

if (!$allowed) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado', 'hint' => 'Use ?key=SESSION_DEBUG_2024 ou acesse logado como admin']);
    exit;
}

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session_id' => session_id(),
    'loggedin' => $_SESSION['loggedin'] ?? false,
    'user_id' => $_SESSION['id'] ?? null,
    'session_token_in_session' => isset($_SESSION['session_token']) ? 'presente (' . strlen($_SESSION['session_token']) . ' chars)' : 'AUSENTE',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
];

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'session_token'");
    $debug['column_session_token_exists'] = $stmt && $stmt->rowCount() > 0;

    if ($debug['column_session_token_exists'] && !empty($_SESSION['id'])) {
        $st = $pdo->prepare("SELECT session_token FROM usuarios WHERE id = ?");
        $st->execute([(int) $_SESSION['id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $valid = trim((string) ($row['session_token'] ?? ''));
        $current = trim((string) ($_SESSION['session_token'] ?? ''));
        $debug['valid_token_in_db'] = $valid !== '' ? 'presente (' . strlen($valid) . ' chars)' : 'NULL ou vazio';
        $debug['tokens_match'] = ($current !== '' && $valid !== '' && hash_equals($valid, $current));
        $debug['enforce_would_invalidate'] = !$debug['tokens_match'] && $valid !== '';
    }
} catch (PDOException $e) {
    $debug['db_error'] = $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
