<?php
/**
 * API para registrar eventos de segurança (devtools_detected, print_attempt, etc.)
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$event_type = $_POST['event_type'] ?? $_GET['event_type'] ?? '';
$page = $_POST['page'] ?? $_GET['page'] ?? '';

$allowed_events = ['devtools_detected', 'print_attempt', 'blocked_shortcut', 'unauthorized_download_attempt'];
if (!in_array($event_type, $allowed_events)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Event type invalid']);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = (int)($_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 0);
$community_id = null;
if (function_exists('getCommunityContext')) {
    $ctx = getCommunityContext();
    $community_id = $ctx['community_id'] ?? null;
}
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

try {
    $stmt = $pdo->prepare("
        INSERT INTO security_events (community_id, user_id, event_type, page, ip, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$community_id, $user_id ?: null, $event_type, $page ?: null, $ip ?: null, $user_agent ?: null]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // Tabela pode não existir ainda
    error_log("security_log: " . $e->getMessage());
    echo json_encode(['success' => true]); // Não quebrar front se tabela não existir
}
