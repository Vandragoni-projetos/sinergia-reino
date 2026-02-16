<?php
/**
 * API para upload de imagens de banners
 * Valida, processa e salva a imagem
 */

require_once __DIR__ . '/../config/config.php';

// Proteção: apenas usuários logados (compatível com admin e infoprodutor)
$logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$usuario_id = null;
if (!empty($_SESSION['usuario_id'])) {
    $usuario_id = (int) $_SESSION['usuario_id'];
} elseif (!empty($_SESSION['id'])) {
    $usuario_id = (int) $_SESSION['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $usuario_id = (int) $_SESSION['user_id'];
}

if (!$logged_in || !$usuario_id) {
    http_response_code(401);
    $msg = !$logged_in ? 'Sessão inválida ou expirada. Faça login novamente.' : 'Usuário não identificado.';
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// Libera o lock da sessão antes do processamento do arquivo (evita bloqueio em uploads grandes)
if (function_exists('session_write_close')) {
    session_write_close();
}

header('Content-Type: application/json');

try {
    // Verificar se arquivo foi enviado
    if (empty($_FILES['banner_image'])) {
        throw new Exception('Nenhum arquivo foi enviado');
    }
    
    $file = $_FILES['banner_image'];
    
    // Verificar erros de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload: ' . $file['error']);
    }
    
    // Validar tamanho (máx 10MB - permite GIFs animados)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        throw new Exception('Arquivo muito grande. Tamanho máximo: 10MB');
    }
    
    // Validar tipo de arquivo
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowed_mimes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/x-webp',           // Variante alternativa
        'image/gif',
        'application/octet-stream' // Alguns servidores retornam isso para webp
    ];
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Extensão não permitida. Use: ' . implode(', ', $allowed_extensions));
    }
    
    // Para webp/gif, aceita mesmo se MIME não for detectado corretamente
    $is_webp_by_extension = ($file_extension === 'webp');
    $is_gif_by_extension = ($file_extension === 'gif');
    $is_valid_mime = in_array($mime_type, $allowed_mimes);
    
    if (!$is_valid_mime && !$is_webp_by_extension && !$is_gif_by_extension) {
        throw new Exception('Tipo de arquivo não permitido. MIME detectado: ' . $mime_type);
    }
    
    // Validação extra para webp: verificar assinatura do arquivo (magic bytes)
    if ($is_webp_by_extension && !$is_valid_mime) {
        $handle = fopen($file['tmp_name'], 'rb');
        $header = fread($handle, 12);
        fclose($handle);
        // WebP files start with "RIFF" and contain "WEBP" at bytes 8-11
        if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
            throw new Exception('Arquivo webp inválido ou corrompido');
        }
    }
    
    // Gerar nome único para o arquivo
    $unique_name = 'banner_' . uniqid() . '_' . time() . '.' . $file_extension;
    
    // Definir diretório de upload
    $upload_dir = __DIR__ . '/../uploads/';
    
    // Criar diretório se não existir
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $upload_path = $upload_dir . $unique_name;
    $relative_path = 'uploads/' . $unique_name;
    
    // Mover arquivo para destino final
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Erro ao salvar arquivo');
    }
    
    // Retornar caminho relativo
    echo json_encode([
        'success' => true,
        'image_path' => $relative_path,
        'filename' => $unique_name,
        'message' => 'Upload realizado com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
