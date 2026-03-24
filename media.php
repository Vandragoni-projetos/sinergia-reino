<?php
/**
 * Endpoint de mídia/arquivos protegidos
 * Servir arquivos apenas para usuários autenticados com acesso ao conteúdo
 * Uso: /media?file_id=123&produto_id=42  ou  /media?path=uploads/xxx&produto_id=42
 */
ob_start();
require_once __DIR__ . '/config/config.php';

function sendUnauthorized() {
    if (ob_get_level()) ob_end_clean();
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

$cliente_email = trim($_SESSION['usuario'] ?? '');
$file_id = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;
$path = isset($_GET['path']) ? trim($_GET['path']) : '';
$external = isset($_GET['external']) ? trim($_GET['external']) : '';
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;

$path_canonical = null;
$download_name = null;

if ($file_id > 0 && $produto_id > 0) {
    // Arquivo de aula: valida via aula->modulo->curso->produto
    try {
        $chk_termo = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'require_download_terms'");
        $sel_cols = "af.caminho_arquivo, af.nome_original, af.aula_id";
        if ($chk_termo && $chk_termo->rowCount() > 0) $sel_cols .= ", a.require_download_terms";
        $stmt = $pdo->prepare("
            SELECT $sel_cols
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
        $aula_id = (int)($row['aula_id'] ?? 0);
        $require_terms = (int)($row['require_download_terms'] ?? 0);
        if ($require_terms === 1 && $aula_id > 0) {
            $chk_tbl = @$pdo->query("SHOW TABLES LIKE 'aula_download_term_acceptances'");
            if ($chk_tbl && $chk_tbl->rowCount() > 0) {
                $stmt_acc = $pdo->prepare("SELECT 1 FROM aula_download_term_acceptances WHERE LOWER(TRIM(aluno_email)) = LOWER(TRIM(?)) AND aula_id = ? AND file_id = ?");
                $stmt_acc->execute([$cliente_email, $aula_id, $file_id]);
                if (!$stmt_acc->fetch()) {
                    logUnauthorized();
                    sendUnauthorized();
                }
            }
        }
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
} elseif (!empty($external)) {
    // URL externa: será buscada via servidor após validar acesso
    if (!filter_var($external, FILTER_VALIDATE_URL)) {
        logUnauthorized();
        sendUnauthorized();
    }
} else {
    logUnauthorized();
    sendUnauthorized();
}

// Valida acesso: produto específico ou qualquer membro (produto_id=0)
// Usa LOWER(TRIM()) para consistência com member_api e evitar falha por diferença de capitalização do e-mail
if ($produto_id > 0) {
    if ($cliente_email === '') {
        logUnauthorized();
        sendUnauthorized();
    }
    $tem_acesso = false;
    $stmt_acesso = $pdo->prepare("
        SELECT 1 FROM alunos_acessos aa
        JOIN produtos p ON aa.produto_id = p.id
        WHERE LOWER(TRIM(aa.aluno_email)) = LOWER(TRIM(?)) AND aa.produto_id = ?
        AND p.tipo_entrega = 'area_membros'
        AND (aa.data_expiracao IS NULL OR aa.data_expiracao > NOW())
    ");
    $stmt_acesso->execute([$cliente_email, $produto_id]);
    if ($stmt_acesso->fetch()) {
        $tem_acesso = true;
    }
    // Fallback: cliente novo pode ter venda aprovada mas alunos_acessos ainda não populado (race) ou email com formato diferente
    if (!$tem_acesso) {
        $stmt_venda = $pdo->prepare("
            SELECT 1 FROM vendas v
            JOIN produtos p ON v.produto_id = p.id
            WHERE LOWER(TRIM(v.comprador_email)) = LOWER(TRIM(?)) AND v.produto_id = ?
            AND p.tipo_entrega = 'area_membros'
            AND LOWER(TRIM(v.status_pagamento)) IN ('approved', 'paid')
        ");
        $stmt_venda->execute([$cliente_email, $produto_id]);
        if ($stmt_venda->fetch()) {
            $tem_acesso = true;
            // Reparar: garantir que alunos_acessos exista para evitar novas falhas
            try {
                $stmt_repair = $pdo->prepare("INSERT IGNORE INTO alunos_acessos (aluno_email, produto_id, criado_manualmente) VALUES (?, ?, 0)");
                $stmt_repair->execute([$cliente_email, $produto_id]);
            } catch (PDOException $e) { /* ignora se tabela/estrutura diferente */ }
        }
    }
    if (!$tem_acesso) {
        logUnauthorized();
        sendUnauthorized();
    }
}

if (!empty($external)) {
    $url_parts = parse_url($external);
    $scheme = strtolower($url_parts['scheme'] ?? '');
    $host = $url_parts['host'] ?? '';
    if ($scheme !== 'http' && $scheme !== 'https') {
        logUnauthorized();
        sendUnauthorized();
    }
    if ($host === '' || !empty($url_parts['user']) || !empty($url_parts['pass'])) {
        logUnauthorized();
        sendUnauthorized();
    }
    $resolved_ip = gethostbyname($host);
    if (!filter_var($resolved_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        logUnauthorized();
        sendUnauthorized();
    }

    if (!function_exists('curl_init')) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['error' => 'curl indisponível no servidor']);
        exit;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $external,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $max_bytes = 5 * 1024 * 1024;
    $downloaded = 0;
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $dl_size, $dl_now) use (&$downloaded, $max_bytes) {
        $downloaded = (int)$dl_now;
        if ($downloaded > $max_bytes) {
            return 1;
        }
        return 0;
    });

    $body = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $http_code >= 400 || $curl_err) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(502);
        echo json_encode(['error' => 'Falha ao buscar imagem externa']);
        exit;
    }

    if (stripos($content_type, 'image/') !== 0) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(415);
        echo json_encode(['error' => 'Conteúdo externo não é imagem']);
        exit;
    }

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: private, max-age=300');
    header('Content-Disposition: inline');
    echo $body;
    exit;
}

$base_dir = __DIR__ . '/';
$full_path = realpath($base_dir . $path_canonical);
if (!$full_path || strpos($full_path, realpath($base_dir)) !== 0 || !is_file($full_path)) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo não encontrado']);
    exit;
}

$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
$mime_map = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
    'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed',
    '7z' => 'application/x-7z-compressed', 'tar' => 'application/x-tar', 'gz' => 'application/gzip',
];
$mime_type = $mime_map[$ext] ?? 'application/octet-stream';
$is_inline = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// Descarta qualquer saída acidental (config, plugins, etc.) antes de enviar o arquivo
if (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=300');
if ($download_name && !$is_inline) {
    $safe_filename = preg_replace('/[^\p{L}\p{N}\s._\-\(\)]/u', '_', basename($download_name));
    header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
} else {
    header('Content-Disposition: inline');
}

readfile($full_path);
exit;
