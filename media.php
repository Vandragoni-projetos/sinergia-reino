<?php
/**
 * Endpoint de mídia/arquivos protegidos
 * Servir arquivos apenas para usuários autenticados com acesso ao conteúdo
 * Uso: /media?file_id=123&produto_id=42  ou  /media?path=uploads/xxx&produto_id=42
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');
http_response_code(403);

function sendUnauthorized() {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

function logUnauthorized() {
    try {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO security_events (user_id, event_type, page, ip, user_agent) VALUES (?, 'unauthorized_download_attempt', ?, ?, ?)");
        $stmt->execute([
            (int)($_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 0),
            $_SERVER['REQUEST_URI'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512)
        ]);
    } catch (Exception $e) {}
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    logUnauthorized();
    sendUnauthorized();
}
if (($_SESSION['tipo'] ?? '') === 'admin') {
    sendUnauthorized();
}

$cliente_email = $_SESSION['usuario'] ?? '';
$file_id = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;
$path = isset($_GET['path']) ? trim($_GET['path']) : '';
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;

$path_canonical = null;
$download_name = null;

if ($file_id > 0 && $produto_id > 0) {
    // Arquivo de aula: valida via aula->modulo->curso->produto
    try {
        $stmt = $pdo->prepare("
            SELECT af.caminho_arquivo, af.nome_original
            FROM aula_arquivos af
            JOIN aulas a ON af.aula_id = a.id
            JOIN modulos m ON a.modulo_id = m.id
            JOIN cursos c ON m.curso_id = c.id
            WHERE af.id = ? AND c.produto_id = ?
        ");
        $stmt->execute([$file_id, $produto_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            logUnauthorized();
            sendUnauthorized();
        }
        $path_canonical = $row['caminho_arquivo'];
        $download_name = $row['nome_original'];
    } catch (PDOException $e) {
        logUnauthorized();
        sendUnauthorized();
    }
} elseif (!empty($path)) {
    $path_canonical = str_replace(['../', '\\'], '', $path);
    if (strpos($path_canonical, 'uploads/') !== 0) {
        logUnauthorized();
        sendUnauthorized();
    }
} else {
    logUnauthorized();
    sendUnauthorized();
}

// Valida acesso: produto específico ou qualquer membro (produto_id=0)
if ($produto_id > 0) {
    $stmt_acesso = $pdo->prepare("
        SELECT 1 FROM alunos_acessos aa
        JOIN produtos p ON aa.produto_id = p.id
        WHERE aa.aluno_email = ? AND aa.produto_id = ?
        AND p.tipo_entrega = 'area_membros'
        AND (aa.data_expiracao IS NULL OR aa.data_expiracao > NOW())
    ");
    $stmt_acesso->execute([$cliente_email, $produto_id]);
    if (!$stmt_acesso->fetch()) {
        logUnauthorized();
        sendUnauthorized();
    }
}

$base_dir = __DIR__ . '/';
$full_path = realpath($base_dir . $path_canonical);
if (!$full_path || strpos($full_path, realpath($base_dir)) !== 0 || !is_file($full_path)) {
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo não encontrado']);
    exit;
}

$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
$mime_map = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
];
$mime_type = $mime_map[$ext] ?? 'application/octet-stream';
$is_inline = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=300');
if ($download_name && !$is_inline) {
    header('Content-Disposition: attachment; filename="' . basename($download_name) . '"');
} else {
    header('Content-Disposition: inline');
}

readfile($full_path);
exit;
