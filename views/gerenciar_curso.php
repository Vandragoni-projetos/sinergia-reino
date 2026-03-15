<?php
// Este arquivo é incluído a partir do index.php,
// então a verificação de login e a conexão com o banco ($pdo) já existem.
if (file_exists(__DIR__ . '/../helpers/html_sanitizer.php')) {
    require_once __DIR__ . '/../helpers/html_sanitizer.php';
}

// Obter o ID do usuário logado
$usuario_id_logado = $_SESSION['id'] ?? 0;

// Se por algum motivo o ID do usuário não estiver definido, redireciona para o login
if ($usuario_id_logado === 0) {
    header("location: /login");
    exit;
}

$mensagem = '';
$produto = null;
$curso = null;
$modulos_com_aulas = [];
$upload_dir = 'uploads/';
$aula_files_dir = 'uploads/aula_files/';
$aula_covers_dir = 'uploads/aula_covers/';

// Garante que os diretórios existam
if (!is_dir($aula_files_dir)) mkdir($aula_files_dir, 0755, true);
if (!is_dir($aula_covers_dir)) mkdir($aula_covers_dir, 0755, true);

// === CONFIGURAÇÃO DE TIPOS DE ARQUIVOS PERMITIDOS ===
$allowed_image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowed_file_extensions = [
    // Documentos
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt',
    // Imagens
    'jpg', 'jpeg', 'png',
    // Áudio
    'mp3', 'wav', 'ogg', 'aac', 'm4a',
    // Vídeo
    'mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'webm',
    // Compactados
    'zip', 'rar', '7z', 'tar', 'gz',
    // Outros
    'csv', 'sql', 'json', 'yaml', 'yml'
];

// Extensões perigosas que NUNCA devem ser permitidas
$dangerous_extensions = [
    'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'exe', 'bat', 'cmd', 'sh', 'bash', 'ps1', 'svg',
    'htaccess', 'htpasswd',
    'js', 'jsx', 'ts', 'tsx', // Scripts que podem ser executados
    'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb',
    'dll', 'so', 'dylib',
    'ini', 'conf', 'config'
];

// MIME types perigosos
$dangerous_mime_types = [
    'application/x-php',
    'application/x-httpd-php',
    'application/x-httpd-php-source',
    'text/x-php',
    'application/x-executable',
    'application/x-msdownload',
    'application/x-msdos-program'
];

/**
 * Valida se o arquivo é seguro para upload
 * @param string $file_extension Extensão do arquivo
 * @param string $tmp_path Caminho temporário do arquivo
 * @param array $allowed_extensions Lista de extensões permitidas
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_file_upload($file_extension, $tmp_path, $allowed_extensions) {
    global $dangerous_extensions, $dangerous_mime_types;
    
    $file_extension = strtolower($file_extension);
    
    // 1. Verificar se a extensão está na lista de perigosas
    if (in_array($file_extension, $dangerous_extensions)) {
        return ['valid' => false, 'error' => "Tipo de arquivo não permitido por segurança: .$file_extension"];
    }
    
    // 2. Verificar se a extensão está na lista de permitidas
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['valid' => false, 'error' => "Tipo de arquivo não suportado: .$file_extension"];
    }
    
    // 3. Verificar MIME type real do arquivo (não confiar apenas na extensão)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_path);
        finfo_close($finfo);
        
        if (in_array($mime_type, $dangerous_mime_types)) {
            return ['valid' => false, 'error' => "Arquivo detectado como potencialmente perigoso"];
        }
        
        // Verificar se o conteúdo do arquivo contém código PHP
        $file_content = file_get_contents($tmp_path, false, null, 0, 1024); // Lê apenas os primeiros 1KB
        if (preg_match('/<\?php|<\?=|<\?[\s\n]/i', $file_content)) {
            return ['valid' => false, 'error' => "Arquivo contém código executável não permitido"];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Sanitiza o nome original do arquivo para exibição e gravação em nome_original.
 * Remove path traversal, caracteres inválidos; mantém extensão e nome legível.
 * @param string $filename Nome original vindo do upload
 * @return string Nome seguro para exibição
 */
function sanitize_original_filename($filename) {
    if (!is_string($filename) || trim($filename) === '') return 'arquivo';
    $name = basename($filename); // Remove path
    $name = preg_replace('/[^\p{L}\p{N}\s._\-\(\)]/u', '_', $name); // Caracteres inválidos
    $name = preg_replace('/_+/', '_', $name); // Múltiplos _ seguidos
    $name = trim($name, '_.');
    return mb_substr($name ?: 'arquivo', 0, 255);
}

/** Origens de vídeo permitidas na Fase 1. Valor inválido ou vazio = youtube (compatibilidade). */
define('ORIGENS_VIDEO_PERMITIDAS', ['youtube', 'vimeo', 'self_hosted']);

/**
 * Valida url_video conforme a origem do vídeo (backend).
 * @param string $origem youtube|vimeo|self_hosted
 * @param string $url_video URL ou caminho
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_video_by_origin($origem, $url_video) {
    $url_video = trim($url_video);
    if ($url_video === '') {
        return ['valid' => false, 'error' => 'URL ou caminho do vídeo é obrigatório.'];
    }
    if ($origem === 'youtube') {
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/i', $url_video)) {
            return ['valid' => true, 'error' => null];
        }
        return ['valid' => false, 'error' => 'URL do YouTube inválida. Use um link de vídeo do YouTube.'];
    }
    if ($origem === 'vimeo') {
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/i', $url_video) || preg_match('/player\.vimeo\.com\/video\/(\d+)/i', $url_video)) {
            return ['valid' => true, 'error' => null];
        }
        return ['valid' => false, 'error' => 'URL do Vimeo inválida. Use um link de vídeo do Vimeo (ex: vimeo.com/123456789).'];
    }
    if ($origem === 'self_hosted') {
        if (strpos($url_video, '..') !== false) {
            return ['valid' => false, 'error' => 'Caminho inválido (não permitido "..").'];
        }
        if (preg_match('#^(/?uploads/[a-zA-Z0-9_.\/\-]+\.mp4)(\?.*)?$#i', $url_video)) {
            return ['valid' => true, 'error' => null];
        }
        return ['valid' => false, 'error' => 'Self-hosted: use apenas caminho interno terminando em .mp4 (ex: uploads/course_videos/meu-video.mp4).'];
    }
    return ['valid' => false, 'error' => 'Origem de vídeo não suportada.'];
}


// 1. Validar e buscar o produto_id
if (!isset($_GET['produto_id']) || !is_numeric($_GET['produto_id'])) {
    header("Location: /index?pagina=area_membros");
    exit;
}
$produto_id = (int)$_GET['produto_id'];

try {
    // Não filtrar por community_id: o infoprodutor pode gerenciar qualquer produto seu (ex.: criado em club e acessado de core).
    $stmt_produto = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND tipo_entrega = 'area_membros' AND usuario_id = ?");
    $stmt_produto->execute([$produto_id, $usuario_id_logado]);
    $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        // Se o produto não for encontrado ou não pertencer ao usuário, redireciona
        $_SESSION['flash_message'] = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded relative mb-4' role='alert'>Produto não encontrado ou você não tem permissão para acessá-lo.</div>";
        header("Location: /index?pagina=area_membros");
        exit;
    }

    // 3. Sincronizar com a tabela 'cursos'
    $stmt_curso = $pdo->prepare("SELECT * FROM cursos WHERE produto_id = ?");
    $stmt_curso->execute([$produto_id]);
    $curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);

    if (!$curso) {
        $community_id = function_exists('getCommunityId') ? getCommunityId() : 1;
        list($cf_cursos_where, $cf_cursos_param) = function_exists('getCommunityFilter') ? getCommunityFilter('cursos') : ['', null];
        $ins_cols = "produto_id, titulo, descricao, imagem_url";
        $ins_vals = "?, ?, ?, ?";
        $ins_params = [$produto_id, $produto['nome'], $produto['descricao'], $produto['foto'] ? 'uploads/' . $produto['foto'] : null];
        if ($cf_cursos_param !== null) {
            $ins_cols .= ", community_id";
            $ins_vals .= ", ?";
            $ins_params[] = $community_id;
        }
        $stmt_insert_curso = $pdo->prepare("INSERT INTO cursos ($ins_cols) VALUES ($ins_vals)");
        $stmt_insert_curso->execute($ins_params);
        
        // Busca o curso recém-criado
        $stmt_curso->execute([$produto_id]);
        $curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);
    }
    $curso_id = $curso['id'];

    // 4. Lógica de manipulação de dados (POST requests)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $should_redirect = false; // Flag para controlar o redirecionamento

        // Função auxiliar para upload de arquivos (com validação de segurança)
        function handle_file_upload($file_key, $target_dir, $current_file_path = null, $allowed_extensions = null) {
            global $allowed_image_extensions, $allowed_file_extensions;
            
            // Se não especificado, usa extensões de imagem por padrão
            if ($allowed_extensions === null) {
                $allowed_extensions = $allowed_image_extensions;
            }
            
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $file_tmp_path = $_FILES[$file_key]['tmp_name'];
                $file_name = $_FILES[$file_key]['name'];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Validar o arquivo antes de fazer upload
                $validation = validate_file_upload($file_extension, $file_tmp_path, $allowed_extensions);
                if (!$validation['valid']) {
                    return ['error' => $validation['error']];
                }
                
                // Deleta o arquivo antigo se existir (somente se o caminho for do diretório de uploads)
                if ($current_file_path && file_exists($current_file_path) && strpos($current_file_path, 'uploads/') === 0) {
                    unlink($current_file_path);
                }
                
                $new_file_name = uniqid($file_key . '_', true) . '.' . $file_extension;
                $dest_path = $target_dir . $new_file_name;
                if (move_uploaded_file($file_tmp_path, $dest_path)) {
                    return ['success' => true, 'path' => $dest_path];
                }
                return ['error' => 'Falha ao mover o arquivo para o destino'];
            }
            return null; // Retorna null se não houver upload
        }

        // Salvar Banner do Curso (URL externa ou upload)
        if (isset($_POST['salvar_banner_curso'])) {
            $should_redirect = true;
            $banner_url_externa = trim($_POST['banner_url_externa'] ?? '');
            if (!empty($banner_url_externa) && filter_var($banner_url_externa, FILTER_VALIDATE_URL)) {
                $stmt = $pdo->prepare("UPDATE cursos SET banner_url = ? WHERE id = ?");
                $stmt->execute([$banner_url_externa, $curso_id]);
                $mensagem = "<div class='bg-green-900/20 border border-green-500 text-green-300 px-4 py-3 rounded' role='alert'>Banner do curso atualizado (URL externa)!</div>";
            } else {
                $upload_result = handle_file_upload('banner_curso', $upload_dir, $curso['banner_url'], $allowed_image_extensions);
                if (is_array($upload_result) && isset($upload_result['error'])) {
                    $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>" . htmlspecialchars($upload_result['error']) . "</div>";
                } elseif (is_array($upload_result) && isset($upload_result['path'])) {
                    $stmt = $pdo->prepare("UPDATE cursos SET banner_url = ? WHERE id = ?");
                    $stmt->execute([$upload_result['path'], $curso_id]);
                    $mensagem = "<div class='bg-green-900/20 border border-green-500 text-green-300 px-4 py-3 rounded' role='alert'>Banner do curso atualizado!</div>";
                } else if (!empty($_POST['remove_banner'])) {
                    if ($curso['banner_url'] && !filter_var($curso['banner_url'], FILTER_VALIDATE_URL) && file_exists($curso['banner_url']) && strpos($curso['banner_url'], 'uploads/') === 0) {
                        unlink($curso['banner_url']);
                    }
                    $stmt = $pdo->prepare("UPDATE cursos SET banner_url = NULL WHERE id = ?");
                    $stmt->execute([$curso_id]);
                    $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Banner do curso removido!</div>";
                } else {
                    $mensagem = "<div class='bg-yellow-900/20 border border-yellow-500 text-yellow-300 px-4 py-3 rounded' role='alert'>Nenhuma imagem de banner enviada, URL válida ou selecionada para remover.</div>";
                }
            }
        }

        // Salvar configurações de comentários
        if (isset($_POST['salvar_comentarios_config'])) {
            $should_redirect = true;
            $comentarios_ativos = isset($_POST['comentarios_ativos']) ? 1 : 0;
            $comentarios_exigem_aprovacao = isset($_POST['comentarios_exigem_aprovacao']) ? 1 : 0;
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM cursos LIKE 'comentarios_ativos'");
                if ($chk && $chk->rowCount() > 0) {
                    $stmt = $pdo->prepare("UPDATE cursos SET comentarios_ativos = ?, comentarios_exigem_aprovacao = ? WHERE id = ?");
                    $stmt->execute([$comentarios_ativos, $comentarios_exigem_aprovacao, $curso_id]);
                    $mensagem = "<div class='bg-green-900/20 border border-green-500 text-green-300 px-4 py-3 rounded' role='alert'>Configurações de comentários salvas!</div>";
                } else {
                    $mensagem = "<div class='bg-yellow-900/20 border border-yellow-500 text-yellow-300 px-4 py-3 rounded' role='alert'>Execute a migration migrations/aula_comentarios.sql para ativar comentários.</div>";
                }
            } catch (PDOException $e) {
                $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Erro ao salvar. Execute migrations/aula_comentarios.sql</div>";
            }
        }

        // Adicionar Módulo
        if (isset($_POST['adicionar_modulo'])) {
            $should_redirect = true;
            $titulo_modulo = trim($_POST['titulo_modulo']);
            $release_days_modulo = (int)($_POST['release_days_modulo'] ?? 0); 
            $imagem_capa_modulo_url = trim($_POST['imagem_capa_modulo_url'] ?? '');
            if (!empty($titulo_modulo)) {
                if (!empty($imagem_capa_modulo_url) && filter_var($imagem_capa_modulo_url, FILTER_VALIDATE_URL)) {
                    $stmt = $pdo->prepare("INSERT INTO modulos (curso_id, titulo, imagem_capa_url, release_days) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$curso_id, $titulo_modulo, $imagem_capa_modulo_url, $release_days_modulo]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO modulos (curso_id, titulo, release_days) VALUES (?, ?, ?)");
                    $stmt->execute([$curso_id, $titulo_modulo, $release_days_modulo]);
                }
                $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Módulo adicionado!</div>";
            } else {
                $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>O título do módulo não pode estar vazio.</div>";
            }
        }
        
        // Editar Módulo (Título, Capa e Release Days)
        if (isset($_POST['editar_modulo'])) {
            $should_redirect = true;
            $modulo_id_edit = $_POST['modulo_id'];
            $titulo_modulo_edit = trim($_POST['titulo_modulo']);
            $release_days_edit = (int)($_POST['release_days_modulo'] ?? 0); 
            $imagem_capa_modulo_url = trim($_POST['imagem_capa_modulo_url'] ?? '');

            // Busca dados atuais do módulo para pegar o caminho da imagem antiga
            $stmt_old_mod = $pdo->prepare("SELECT imagem_capa_url FROM modulos WHERE id = ? AND curso_id = ?");
            $stmt_old_mod->execute([$modulo_id_edit, $curso_id]);
            $old_module = $stmt_old_mod->fetch(PDO::FETCH_ASSOC);

            if ($old_module) {
                if (!empty($imagem_capa_modulo_url) && filter_var($imagem_capa_modulo_url, FILTER_VALIDATE_URL)) {
                    if ($old_module['imagem_capa_url'] && !filter_var($old_module['imagem_capa_url'], FILTER_VALIDATE_URL) && file_exists($old_module['imagem_capa_url']) && strpos($old_module['imagem_capa_url'], 'uploads/') === 0) {
                        unlink($old_module['imagem_capa_url']);
                    }
                    $stmt = $pdo->prepare("UPDATE modulos SET titulo = ?, imagem_capa_url = ?, release_days = ? WHERE id = ? AND curso_id = ?");
                    $stmt->execute([$titulo_modulo_edit, $imagem_capa_modulo_url, $release_days_edit, $modulo_id_edit, $curso_id]);
                    $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Módulo atualizado!</div>";
                } else {
                    $upload_result = handle_file_upload('imagem_capa_modulo', $upload_dir, $old_module['imagem_capa_url'], $allowed_image_extensions);
                
                    if (is_array($upload_result) && isset($upload_result['error'])) {
                        // Erro na validação do arquivo
                        $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>" . htmlspecialchars($upload_result['error']) . "</div>";
                    } elseif (is_array($upload_result) && isset($upload_result['path'])) {
                        // Nova imagem enviada com sucesso
                        $stmt = $pdo->prepare("UPDATE modulos SET titulo = ?, imagem_capa_url = ?, release_days = ? WHERE id = ? AND curso_id = ?"); 
                        $stmt->execute([$titulo_modulo_edit, $upload_result['path'], $release_days_edit, $modulo_id_edit, $curso_id]); 
                        $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Módulo atualizado!</div>";
                    } else if (!empty($_POST['remove_imagem_capa_modulo'])) { // Lógica para remover a imagem de capa
                        if ($old_module['imagem_capa_url'] && file_exists($old_module['imagem_capa_url']) && strpos($old_module['imagem_capa_url'], 'uploads/') === 0) {
                            unlink($old_module['imagem_capa_url']);
                        }
                        $stmt = $pdo->prepare("UPDATE modulos SET titulo = ?, imagem_capa_url = NULL, release_days = ? WHERE id = ? AND curso_id = ?"); 
                        $stmt->execute([$titulo_modulo_edit, $release_days_edit, $modulo_id_edit, $curso_id]); 
                        $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Módulo e imagem de capa atualizados!</div>";
                    } else { // Se nenhuma imagem nova foi enviada e não foi pedido para remover, atualiza apenas o título
                        $stmt = $pdo->prepare("UPDATE modulos SET titulo = ?, release_days = ? WHERE id = ? AND curso_id = ?"); 
                        $stmt->execute([$titulo_modulo_edit, $release_days_edit, $modulo_id_edit, $curso_id]); 
                        $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Título do módulo atualizado!</div>";
                    }
                }
            } else {
                 $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Módulo não encontrado para edição.</div>";
            }
        }

        // Adicionar Aula
        if (isset($_POST['adicionar_aula'])) {
            $should_redirect = true;
            $modulo_id = $_POST['modulo_id'];
            $titulo_aula = trim($_POST['titulo_aula']);
            $url_video = trim($_POST['url_video'] ?? '');
            $descricao_aula = trim($_POST['descricao_aula']);
            if (function_exists('sanitize_lesson_html')) {
                $descricao_aula = sanitize_lesson_html($descricao_aula);
            }
            $release_days_aula = (int)($_POST['release_days_aula'] ?? 0);
            $tipo_conteudo = $_POST['tipo_conteudo'] ?? 'video';

            // Verifica se o módulo realmente pertence a este curso
            $stmt_check_modulo = $pdo->prepare("SELECT id FROM modulos WHERE id = ? AND curso_id = ?");
            $stmt_check_modulo->execute([$modulo_id, $curso_id]);
            if ($stmt_check_modulo->rowCount() === 0) {
                 $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Módulo inválido para este curso.</div>";
            } elseif (empty($titulo_aula)) {
                $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>O título da aula é obrigatório.</div>";
            } else {
                // Validações de conteúdo baseadas no tipo
                // NOTE: 'aula_files' will have name[0] as empty if no file is selected.
                $has_new_files = isset($_FILES['aula_files']) && !empty($_FILES['aula_files']['name'][0]);

                if ($tipo_conteudo === 'video' && empty($url_video)) {
                    $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Para aulas de vídeo, a URL do vídeo é obrigatória.</div>";
                } elseif ($tipo_conteudo === 'files' && !$has_new_files) {
                    $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Para aulas de arquivos, pelo menos um arquivo é obrigatório.</div>";
                } elseif ($tipo_conteudo === 'mixed' && empty($url_video) && !$has_new_files) {
                    $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Para aulas mistas, a URL do vídeo e pelo menos um arquivo são obrigatórios.</div>";
                } else {
                    $origem_video = 'youtube';
                    $video_validation_error = null;
                    if (($tipo_conteudo === 'video' || $tipo_conteudo === 'mixed') && $url_video !== '') {
                        $origem_video_raw = trim($_POST['origem_video'] ?? 'youtube');
                        $origem_video = in_array($origem_video_raw, ORIGENS_VIDEO_PERMITIDAS) ? $origem_video_raw : 'youtube';
                        $vid_val = validate_video_by_origin($origem_video, $url_video);
                        if (!$vid_val['valid']) {
                            $video_validation_error = $vid_val['error'];
                        }
                    }
                    if ($video_validation_error === null) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO aulas (modulo_id, titulo, url_video, origem_video, descricao, release_days, tipo_conteudo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$modulo_id, $titulo_aula, $url_video, $origem_video, $descricao_aula, $release_days_aula, $tipo_conteudo]);
                    } catch (PDOException $e) {
                        $stmt = $pdo->prepare("INSERT INTO aulas (modulo_id, titulo, url_video, descricao, release_days, tipo_conteudo) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$modulo_id, $titulo_aula, $url_video, $descricao_aula, $release_days_aula, $tipo_conteudo]);
                    }
                    $nova_aula_id = $pdo->lastInsertId();

                    // Upload de múltiplos arquivos para a aula (com validação)
                    if ($has_new_files) {
                        $upload_errors = [];
                        foreach ($_FILES['aula_files']['name'] as $key => $name) {
                            if ($_FILES['aula_files']['error'][$key] === UPLOAD_ERR_OK) {
                                $tmp_name = $_FILES['aula_files']['tmp_name'][$key];
                                $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                                
                                // Validar o arquivo antes de fazer upload
                                $validation = validate_file_upload($file_extension, $tmp_name, $allowed_file_extensions);
                                if (!$validation['valid']) {
                                    $upload_errors[] = htmlspecialchars($name) . ": " . $validation['error'];
                                    continue;
                                }
                                
                                $new_file_name = uniqid('aula_file_', true) . '.' . $file_extension;
                                $dest_path = $aula_files_dir . $new_file_name;

                                if (move_uploaded_file($tmp_name, $dest_path)) {
                                    $nome_original_safe = sanitize_original_filename($name);
                                    $stmt_insert_file = $pdo->prepare("INSERT INTO aula_arquivos (aula_id, nome_original, nome_salvo, caminho_arquivo, tipo_mime, tamanho_bytes) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt_insert_file->execute([
                                        $nova_aula_id,
                                        $nome_original_safe,
                                        $new_file_name,
                                        $dest_path,
                                        $_FILES['aula_files']['type'][$key],
                                        $_FILES['aula_files']['size'][$key]
                                    ]);
                                }
                            }
                        }
                        if (!empty($upload_errors)) {
                            $mensagem = "<div class='bg-yellow-900/20 border border-yellow-500 text-yellow-300 px-4 py-3 rounded' role='alert'>Aula adicionada, mas alguns arquivos foram rejeitados:<br>" . implode('<br>', $upload_errors) . "</div>";
                        } else {
                            $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Aula adicionada!</div>";
                        }
                    } else {
                        $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Aula adicionada!</div>";
                    }
                    // Banner/cover para Somente Arquivos ou Vídeo e Arquivos
                    if (($tipo_conteudo === 'files' || $tipo_conteudo === 'mixed') && isset($nova_aula_id)) {
                        $cover_type = null; $cover_url = null; $cover_path = null;
                        if (!empty($_FILES['lesson_cover_upload']['name']) && $_FILES['lesson_cover_upload']['error'] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($_FILES['lesson_cover_upload']['name'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                                $cover_name = 'lesson_cover_' . $nova_aula_id . '_' . uniqid('', true) . '.' . $ext;
                                $cover_full = $aula_covers_dir . $cover_name;
                                if (move_uploaded_file($_FILES['lesson_cover_upload']['tmp_name'], $cover_full)) {
                                    $cover_type = 'upload'; $cover_path = $cover_full;
                                }
                            }
                        } elseif (!empty(trim($_POST['lesson_cover_url'] ?? ''))) {
                            $u = trim($_POST['lesson_cover_url']);
                            if (filter_var($u, FILTER_VALIDATE_URL) && preg_match('/\.(jpg|jpeg|png|webp)(\?|$)/i', $u)) {
                                $cover_type = 'url'; $cover_url = $u;
                            }
                        }
                        if ($cover_type) {
                            $chk = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'lesson_cover_type'");
                            if ($chk && $chk->rowCount() > 0) {
                                $stmt_cover = $pdo->prepare("UPDATE aulas SET lesson_cover_type=?, lesson_cover_url=?, lesson_cover_path=? WHERE id=?");
                                $stmt_cover->execute([$cover_type, $cover_url, $cover_path, $nova_aula_id]);
                            }
                        }
                    }
                    } else {
                        $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>" . htmlspecialchars($video_validation_error) . "</div>";
                    }
                }
            }
        }
        
        // Editar Aula
        if (isset($_POST['editar_aula_form'])) {
            $should_redirect = true;
            $aula_id_edit = $_POST['aula_id'];
            $titulo_aula = trim($_POST['titulo_aula']);
            $url_video = trim($_POST['url_video'] ?? '');
            $descricao_aula = trim($_POST['descricao_aula'] ?? '');
            if (function_exists('sanitize_lesson_html')) {
                $descricao_aula = sanitize_lesson_html($descricao_aula);
            }
            $release_days_aula = (int)($_POST['release_days_aula'] ?? 0);
            $tipo_conteudo = $_POST['tipo_conteudo'] ?? 'video';

            // Valida se a aula pertence a um módulo deste curso
            $stmt_check_aula = $pdo->prepare("SELECT a.id FROM aulas a JOIN modulos m ON a.modulo_id = m.id WHERE a.id = ? AND m.curso_id = ?");
            $stmt_check_aula->execute([$aula_id_edit, $curso_id]);

            if ($stmt_check_aula->rowCount() === 0) {
                 $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Aula não encontrada ou não pertence a este curso.</div>";
            } elseif (empty($titulo_aula)) {
                $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>O título da aula é obrigatório.</div>";
            } else {
                // Validações de conteúdo baseadas no tipo
                $has_new_files = isset($_FILES['aula_files']) && !empty($_FILES['aula_files']['name'][0]);
                $has_existing_files_to_keep = !empty($_POST['existing_files']);

                if ($tipo_conteudo === 'video' && empty($url_video)) {
                    $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Para aulas de vídeo, a URL do vídeo é obrigatória.</div>";
                } elseif ($tipo_conteudo === 'files' && !$has_new_files && !$has_existing_files_to_keep) {
                    // Se o tipo é 'files' e não há arquivos novos e nem existentes marcados, erro.
                    $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Para aulas de arquivos, pelo menos um arquivo é obrigatório.</div>";
                } elseif ($tipo_conteudo === 'mixed' && empty($url_video) && !$has_new_files && !$has_existing_files_to_keep) {
                    // Se o tipo é 'mixed' e não há vídeo, nem arquivos novos, nem existentes.
                    $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Para aulas mistas, a URL do vídeo e pelo menos um arquivo são obrigatórios.</div>";
                } else {
                    $origem_video_edit = 'youtube';
                    $video_validation_error_edit = null;
                    if (($tipo_conteudo === 'video' || $tipo_conteudo === 'mixed') && $url_video !== '') {
                        $origem_video_raw = trim($_POST['origem_video'] ?? 'youtube');
                        $origem_video_edit = in_array($origem_video_raw, ORIGENS_VIDEO_PERMITIDAS) ? $origem_video_raw : 'youtube';
                        $vid_val = validate_video_by_origin($origem_video_edit, $url_video);
                        if (!$vid_val['valid']) {
                            $video_validation_error_edit = $vid_val['error'];
                        }
                    }
                    if ($video_validation_error_edit === null) {
                    try {
                        $stmt = $pdo->prepare("UPDATE aulas SET titulo = ?, url_video = ?, origem_video = ?, descricao = ?, release_days = ?, tipo_conteudo = ? WHERE id = ?");
                        $stmt->execute([$titulo_aula, $url_video, $origem_video_edit, $descricao_aula, $release_days_aula, $tipo_conteudo, $aula_id_edit]);
                    } catch (PDOException $e) {
                        $stmt = $pdo->prepare("UPDATE aulas SET titulo = ?, url_video = ?, descricao = ?, release_days = ?, tipo_conteudo = ? WHERE id = ?");
                        $stmt->execute([$titulo_aula, $url_video, $descricao_aula, $release_days_aula, $tipo_conteudo, $aula_id_edit]);
                    }

                    // Gerenciar arquivos existentes (deletar)
                    $existing_files_to_keep = $_POST['existing_files'] ?? [];
                    // Busca todos os arquivos da aula
                    $stmt_all_files = $pdo->prepare("SELECT id, caminho_arquivo FROM aula_arquivos WHERE aula_id = ?");
                    $stmt_all_files->execute([$aula_id_edit]);
                    $all_files = $stmt_all_files->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($all_files as $file) {
                        if (!in_array($file['id'], $existing_files_to_keep)) {
                            // Deleta o arquivo do sistema de arquivos
                            if (file_exists($file['caminho_arquivo'])) {
                                unlink($file['caminho_arquivo']);
                            }
                            // Deleta o registro do banco de dados
                            $stmt_delete_file = $pdo->prepare("DELETE FROM aula_arquivos WHERE id = ?");
                            $stmt_delete_file->execute([$file['id']]);
                        }
                    }

                    // Upload de novos arquivos (com validação)
                    $upload_errors = [];
                    if ($has_new_files) {
                        foreach ($_FILES['aula_files']['name'] as $key => $name) {
                            if ($_FILES['aula_files']['error'][$key] === UPLOAD_ERR_OK) {
                                $tmp_name = $_FILES['aula_files']['tmp_name'][$key];
                                $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                                
                                // Validar o arquivo antes de fazer upload
                                $validation = validate_file_upload($file_extension, $tmp_name, $allowed_file_extensions);
                                if (!$validation['valid']) {
                                    $upload_errors[] = htmlspecialchars($name) . ": " . $validation['error'];
                                    continue;
                                }
                                
                                $new_file_name = uniqid('aula_file_', true) . '.' . $file_extension;
                                $dest_path = $aula_files_dir . $new_file_name;

                                if (move_uploaded_file($tmp_name, $dest_path)) {
                                    $nome_original_safe = sanitize_original_filename($name);
                                    $stmt_insert_file = $pdo->prepare("INSERT INTO aula_arquivos (aula_id, nome_original, nome_salvo, caminho_arquivo, tipo_mime, tamanho_bytes) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt_insert_file->execute([
                                        $aula_id_edit,
                                        $nome_original_safe,
                                        $new_file_name,
                                        $dest_path,
                                        $_FILES['aula_files']['type'][$key],
                                        $_FILES['aula_files']['size'][$key]
                                    ]);
                                }
                            }
                        }
                    }
                    if (!empty($upload_errors)) {
                        $mensagem = "<div class='bg-yellow-900/20 border border-yellow-500 text-yellow-300 px-4 py-3 rounded' role='alert'>Aula atualizada, mas alguns arquivos foram rejeitados:<br>" . implode('<br>', $upload_errors) . "</div>";
                    } else {
                        $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Aula atualizada!</div>";
                    }
                    // Banner/cover: somente para "Somente Arquivos" (Edição)
                    $chk_cover_col = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'lesson_cover_type'");
                    if ($chk_cover_col && $chk_cover_col->rowCount() > 0 && $tipo_conteudo === 'files') {
                        $cover_type = null; $cover_url = null; $cover_path = null;
                        if (!empty($_POST['remove_lesson_cover'])) {
                            $stmt_old = $pdo->prepare("SELECT lesson_cover_path FROM aulas WHERE id = ?");
                            $stmt_old->execute([$aula_id_edit]);
                            $old = $stmt_old->fetch(PDO::FETCH_ASSOC);
                            if (!empty($old['lesson_cover_path']) && file_exists($old['lesson_cover_path'])) unlink($old['lesson_cover_path']);
                        } elseif (!empty($_FILES['lesson_cover_upload']['name']) && $_FILES['lesson_cover_upload']['error'] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($_FILES['lesson_cover_upload']['name'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                                $stmt_old = $pdo->prepare("SELECT lesson_cover_path FROM aulas WHERE id = ?");
                                $stmt_old->execute([$aula_id_edit]);
                                $old = $stmt_old->fetch(PDO::FETCH_ASSOC);
                                if (!empty($old['lesson_cover_path']) && file_exists($old['lesson_cover_path'])) unlink($old['lesson_cover_path']);
                                $cover_name = 'lesson_cover_' . $aula_id_edit . '_' . uniqid('', true) . '.' . $ext;
                                $cover_full = $aula_covers_dir . $cover_name;
                                if (move_uploaded_file($_FILES['lesson_cover_upload']['tmp_name'], $cover_full)) {
                                    $cover_type = 'upload'; $cover_path = $cover_full;
                                }
                            }
                        } elseif (!empty(trim($_POST['lesson_cover_url'] ?? ''))) {
                            $u = trim($_POST['lesson_cover_url']);
                            if (filter_var($u, FILTER_VALIDATE_URL) && preg_match('/\.(jpg|jpeg|png|webp)(\?|$)/i', $u)) {
                                $cover_type = 'url'; $cover_url = $u;
                            }
                        }
                        if (!empty($_POST['remove_lesson_cover'])) {
                            $stmt_cover = $pdo->prepare("UPDATE aulas SET lesson_cover_type=NULL, lesson_cover_url=NULL, lesson_cover_path=NULL WHERE id=?");
                            $stmt_cover->execute([$aula_id_edit]);
                        } elseif ($cover_type) {
                            $stmt_cover = $pdo->prepare("UPDATE aulas SET lesson_cover_type=?, lesson_cover_url=?, lesson_cover_path=? WHERE id=?");
                            $stmt_cover->execute([$cover_type, $cover_url, $cover_path, $aula_id_edit]);
                        }
                    }
                    } else {
                        $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>" . htmlspecialchars($video_validation_error_edit) . "</div>";
                    }
                }
            }
        }

        // Deletar Módulo
        if (isset($_POST['deletar_modulo'])) {
            $should_redirect = true;
            $modulo_id_del = $_POST['modulo_id'];

            // Primeiro, verifica se o módulo pertence a este curso antes de deletar
            $stmt_check_modulo = $pdo->prepare("SELECT imagem_capa_url FROM modulos WHERE id = ? AND curso_id = ?");
            $stmt_check_modulo->execute([$modulo_id_del, $curso_id]);
            $module_to_delete = $stmt_check_modulo->fetch(PDO::FETCH_ASSOC);

            if ($module_to_delete) {
                // Deleta a imagem de capa se existir
                if ($module_to_delete['imagem_capa_url'] && file_exists($module_to_delete['imagem_capa_url']) && strpos($module_to_delete['imagem_capa_url'], 'uploads/') === 0) {
                    unlink($module_to_delete['imagem_capa_url']);
                }
                
                // Antes de deletar o módulo, precisamos deletar os arquivos das aulas para evitar órfãos
                $stmt_get_aula_files = $pdo->prepare("
                    SELECT af.caminho_arquivo 
                    FROM aula_arquivos af
                    JOIN aulas a ON af.aula_id = a.id
                    WHERE a.modulo_id = ?
                ");
                $stmt_get_aula_files->execute([$modulo_id_del]);
                $files_to_delete = $stmt_get_aula_files->fetchAll(PDO::FETCH_COLUMN);

                foreach ($files_to_delete as $file_path) {
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }

                // Deleta o módulo (e suas aulas em cascata devido à FOREIGN KEY ON DELETE CASCADE)
                $stmt = $pdo->prepare("DELETE FROM modulos WHERE id = ? AND curso_id = ?");
                $stmt->execute([$modulo_id_del, $curso_id]);
                $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Módulo e suas aulas foram deletados.</div>";
            } else {
                 $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Módulo não encontrado ou não pertence a este curso.</div>";
            }
        }
        
        // Deletar Aula
        if (isset($_POST['deletar_aula'])) {
            $should_redirect = true;
            $aula_id_del = $_POST['aula_id'];

            // Verifica se a aula pertence a um módulo deste curso
            $stmt_check_aula = $pdo->prepare("
                SELECT a.id, af.caminho_arquivo 
                FROM aulas a 
                JOIN modulos m ON a.modulo_id = m.id 
                LEFT JOIN aula_arquivos af ON a.id = af.aula_id
                WHERE a.id = ? AND m.curso_id = ?
            ");
            $stmt_check_aula->execute([$aula_id_del, $curso_id]);
            $aula_files_to_delete = $stmt_check_aula->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($aula_files_to_delete)) {
                foreach ($aula_files_to_delete as $file_info) {
                    if ($file_info['caminho_arquivo'] && file_exists($file_info['caminho_arquivo'])) {
                        unlink($file_info['caminho_arquivo']);
                    }
                }
                // Deleta a aula (e seus arquivos em cascata se a FK estiver configurada)
                $stmt = $pdo->prepare("DELETE FROM aulas WHERE id = ?");
                $stmt->execute([$aula_id_del]);
                $mensagem = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>Aula deletada.</div>";
            } else {
                $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded' role='alert'>Aula não encontrada ou não pertence a este curso.</div>";
            }
        }
        
        // CORREÇÃO: Lógica de redirecionamento centralizada
        if ($should_redirect) {
            $_SESSION['flash_message'] = $mensagem;
            // AQUI ESTÁ A CORREÇÃO: Garantir que o redirecionamento inclua a página correta no index
            header("Location: /index?pagina=gerenciar_curso&produto_id=" . $produto_id);
            exit;
        }
    }
    
    // Pega a mensagem da sessão, se houver, e depois limpa
    if (isset($_SESSION['flash_message'])) {
        $mensagem = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
    }

    // 5. Buscar todos os módulos e aulas para exibição
    // NEW: Include release_days in SELECT for modulos
    $stmt_modulos = $pdo->prepare("SELECT id, curso_id, titulo, imagem_capa_url, ordem, release_days FROM modulos WHERE curso_id = ? ORDER BY ordem ASC, id ASC");
    $stmt_modulos->execute([$curso_id]);
    $modulos = $stmt_modulos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($modulos as $modulo) {
        // Include release_days, tipo_conteudo, origem_video e lesson_cover (se existir) para aulas
        $aulas_cols = 'id, modulo_id, titulo, url_video, descricao, ordem, release_days, tipo_conteudo';
        $chk_origem = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'origem_video'");
        if ($chk_origem && $chk_origem->rowCount() > 0) {
            $aulas_cols .= ', origem_video';
        }
        $chk_cover = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'lesson_cover_type'");
        if ($chk_cover && $chk_cover->rowCount() > 0) {
            $aulas_cols .= ', lesson_cover_type, lesson_cover_url, lesson_cover_path';
        }
        $stmt_aulas = $pdo->prepare("SELECT $aulas_cols FROM aulas WHERE modulo_id = ? ORDER BY ordem ASC, id ASC");
        $stmt_aulas->execute([$modulo['id']]);
        $aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);

        // Fetch files for each lesson
        foreach ($aulas as &$aula) {
            $stmt_files = $pdo->prepare("SELECT id, nome_original, caminho_arquivo FROM aula_arquivos WHERE aula_id = ? ORDER BY ordem ASC, id ASC");
            $stmt_files->execute([$aula['id']]);
            $aula['files'] = $stmt_files->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($aula); // Break the reference

        $modulos_com_aulas[] = [
            'modulo' => $modulo,
            'aulas' => $aulas
        ];
    }

} catch (PDOException $e) {
    $mensagem = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded' role='alert'>Erro de banco de dados: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>

<div class="container mx-auto">
    <div class="flex items-center mb-6">
        <a href="/index?pagina=area_membros" class="text-[#32e768] hover:text-[#28d15e] mr-4">
            <i data-lucide="arrow-left-circle" class="w-8 h-8"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-white">Gerenciar Conteúdo</h1>
            <p class="text-gray-400">Curso: <?php echo htmlspecialchars($curso['titulo'] ?? 'Carregando...'); ?></p>
        </div>
    </div>

    <?php if ($mensagem) echo "<div class='mb-6'>$mensagem</div>"; ?>

    <!-- Comentários nas Aulas (ativação em destaque) -->
    <?php
    $has_comentarios_cols = false;
    try {
        $chk_cols = $pdo->query("SHOW COLUMNS FROM cursos LIKE 'comentarios_ativos'");
        $has_comentarios_cols = ($chk_cols && $chk_cols->rowCount() > 0);
    } catch (PDOException $e) {}
    $comentarios_ativos = $has_comentarios_cols ? (int)($curso['comentarios_ativos'] ?? 0) : 0;
    $comentarios_exigem_aprovacao = $has_comentarios_cols ? (int)($curso['comentarios_exigem_aprovacao'] ?? 1) : 1;
    ?>
    <div id="secao-comentarios" class="bg-dark-card p-6 rounded-lg shadow-md mb-8 border-2 border-[#32e768]">
        <h2 class="text-2xl font-semibold mb-2 text-white flex items-center gap-2">
            <i data-lucide="message-circle" class="w-7 h-7 text-[#32e768]"></i>
            Comentários nas Aulas
        </h2>
        <p class="text-gray-400 text-sm mb-4">Permita que os alunos comentem nas aulas e defina se a aprovação é obrigatória.</p>
        <?php if ($has_comentarios_cols): ?>
        <form action="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" method="post" class="space-y-4">
            <div class="flex items-center justify-between py-2">
                <label for="comentarios_ativos" class="text-gray-300 font-medium">Ativar comentários nas aulas</label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="comentarios_ativos" id="comentarios_ativos" value="1" class="sr-only peer" <?php echo $comentarios_ativos ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#32e768]"></div>
                </label>
            </div>
            <div class="flex items-center justify-between py-2">
                <div>
                    <label for="comentarios_exigem_aprovacao" class="text-gray-300 font-medium">Comentários exigem aprovação</label>
                    <p class="text-xs text-gray-500 mt-1">Se ativo, os comentários só aparecem após você aprovar na lista abaixo.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="comentarios_exigem_aprovacao" id="comentarios_exigem_aprovacao" value="1" class="sr-only peer" <?php echo $comentarios_exigem_aprovacao ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#32e768]"></div>
                </label>
            </div>
            <button type="submit" name="salvar_comentarios_config" class="bg-white text-gray-900 font-bold py-2 px-5 rounded-lg hover:bg-gray-200 transition">Salvar alterações</button>
        </form>
        <div class="mt-8 pt-6 border-t border-dark-border">
            <h3 class="text-lg font-semibold text-white mb-3">Ver e aprovar comentários</h3>
            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button" class="comentarios-filter-btn px-4 py-2 rounded-lg text-sm font-medium transition bg-[#32e768] text-white" data-status="all">Todos</button>
                <button type="button" class="comentarios-filter-btn px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-600 text-gray-300 hover:bg-gray-500" data-status="pending">Pendentes</button>
                <button type="button" class="comentarios-filter-btn px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-600 text-gray-300 hover:bg-gray-500" data-status="approved">Aprovados</button>
                <button type="button" class="comentarios-filter-btn px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-600 text-gray-300 hover:bg-gray-500" data-status="rejected">Rejeitados</button>
            </div>
            <div id="comentarios-list-container" class="space-y-3 max-h-80 overflow-y-auto">
                <p class="text-gray-500 text-sm">Carregando...</p>
            </div>
        </div>
        <?php else: ?>
        <p class="text-yellow-400 text-sm">Execute a migration <code class="bg-dark-elevated px-1 rounded">migrations/aula_comentarios.sql</code> para ativar esta funcionalidade.</p>
        <?php endif; ?>
    </div>

    <!-- Personalizar Aparência do Curso -->
    <div class="bg-dark-card p-6 rounded-lg shadow-md mb-8 border border-[#32e768]">
        <h2 class="text-2xl font-semibold mb-4 text-white">Personalizar Aparência do Curso</h2>
        <form action="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" method="post" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="banner_curso" class="block text-gray-300 text-sm font-semibold mb-2">Banner do Topo</label>
                <?php
                $banner_display_src = '';
                if (!empty($curso['banner_url'])) {
                    $banner_display_src = filter_var($curso['banner_url'], FILTER_VALIDATE_URL) ? $curso['banner_url'] : ('/' . ltrim($curso['banner_url'], '/'));
                }
                ?>
                <?php if (!empty($banner_display_src)): ?>
                    <div class="mb-2">
                        <img src="<?php echo htmlspecialchars($banner_display_src); ?>" alt="Banner atual" class="w-full h-48 object-cover rounded-lg border border-dark-border">
                        <label class="mt-2 flex items-center text-sm text-gray-400">
                            <input type="checkbox" name="remove_banner" value="1" class="h-4 w-4 mr-1 text-red-400 focus:ring-red-500 rounded"> Remover banner existente
                        </label>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label for="banner_url_externa" class="block text-gray-400 text-xs mb-1">Ou use URL externa (WordPress, CDN, etc.)</label>
                    <input type="url" id="banner_url_externa" name="banner_url_externa" class="w-full px-4 py-2 bg-dark-elevated border border-dark-border rounded-lg text-white text-sm" placeholder="https://seusite.com/banner.jpg" value="<?php echo (!empty($curso['banner_url']) && filter_var($curso['banner_url'], FILTER_VALIDATE_URL)) ? htmlspecialchars($curso['banner_url']) : ''; ?>">
                </div>
                <input type="file" id="banner_curso" name="banner_curso" class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[#32e768]/20 file:text-[#32e768] hover:file:bg-[#32e768]/30" accept="image/*">
                <p class="mt-1 text-xs text-gray-400">Recomendado: 1920x400px. Upload ou URL externa evita perda no deploy.</p>
            </div>
            <button type="submit" name="salvar_banner_curso" class="bg-[#32e768] text-white font-bold py-2 px-5 rounded-lg hover:bg-[#28d15e] transition">Salvar Banner</button>
        </form>
    </div>

    <!-- Adicionar Novo Módulo -->
    <div class="bg-dark-card p-6 rounded-lg shadow-md mb-8 border border-[#32e768]">
        <h2 class="text-2xl font-semibold mb-4 text-white">Adicionar Novo Módulo</h2>
        <form action="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" method="post" class="space-y-4"> <!-- Changed to space-y-4 for vertical stacking -->
            <div>
                <label for="titulo_modulo_add" class="block text-gray-300 text-sm font-semibold mb-2">Título do Módulo</label>
                <input type="text" id="titulo_modulo_add" name="titulo_modulo" placeholder="Ex: Módulo 1 - Introdução" required class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <!-- NEW: Release Days for Add Module -->
            <div>
                <label for="release_days_modulo_add" class="block text-gray-300 text-sm font-semibold mb-2">Liberar após (dias)</label>
                <input type="number" id="release_days_modulo_add" name="release_days_modulo" value="0" min="0" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white" placeholder="0 = Liberação imediata">
                <p class="mt-1 text-xs text-gray-400">Defina quantos dias após a compra do curso este módulo será liberado para o aluno.</p>
            </div>
            <div>
                <label for="imagem_capa_modulo_url_add" class="block text-gray-300 text-sm font-semibold mb-2">Imagem de Capa do Módulo (URL)</label>
                <input type="url" id="imagem_capa_modulo_url_add" name="imagem_capa_modulo_url" placeholder="https://seusite.com/imagem.jpg" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                <p class="mt-1 text-xs text-gray-400">Use uma URL externa para evitar upload.</p>
            </div>
            <div class="flex justify-end">
                <button type="submit" name="adicionar_modulo" class="bg-[#32e768] text-white font-bold py-3 px-6 rounded-lg hover:bg-[#28d15e] transition duration-300 flex items-center space-x-2">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                    <span>Adicionar Módulo</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Listagem de Módulos e Aulas -->
    <div class="space-y-6">
        <?php if (empty($modulos_com_aulas)): ?>
            <div class="bg-dark-card p-8 rounded-lg shadow-md text-center text-gray-400 border border-[#32e768]">
                <i data-lucide="folder-open" class="mx-auto w-16 h-16 text-gray-500"></i>
                <p class="mt-4">Nenhum módulo foi criado para este curso ainda.</p>
                <p>Use o formulário acima para começar.</p>
            </div>
        <?php else: ?>
            <?php foreach ($modulos_com_aulas as $item): ?>
                <div class="bg-dark-card rounded-lg shadow-md overflow-hidden border border-dark-border">
                    <div class="bg-dark-elevated p-4 flex justify-between items-center border-b border-dark-border">
                        <div class="flex items-center gap-4">
                            <?php
                                $module_image_src = '';
                                if (!empty($item['modulo']['imagem_capa_url'])) {
                                    $module_image_raw = trim($item['modulo']['imagem_capa_url']);
                                    if (filter_var($module_image_raw, FILTER_VALIDATE_URL)) {
                                        $module_image_src = $module_image_raw;
                                    } elseif (file_exists($module_image_raw)) {
                                        $module_image_src = '/' . ltrim($module_image_raw, '/');
                                    }
                                }
                            ?>
                            <?php if (!empty($module_image_src)): ?>
                                <img src="<?php echo htmlspecialchars($module_image_src); ?>" alt="Capa do módulo" class="w-24 h-16 object-cover rounded-md border border-dark-border">
                            <?php else: ?>
                                <div class="w-24 h-16 bg-dark-card rounded-md flex items-center justify-center border border-dark-border">
                                    <i data-lucide="image-off" class="w-8 h-8 text-gray-500"></i>
                                </div>
                            <?php endif; ?>
                            <h3 class="text-xl font-bold text-white">
                                <?php echo htmlspecialchars($item['modulo']['titulo']); ?>
                                <?php if ($item['modulo']['release_days'] > 0): ?>
                                    <span class="ml-2 text-sm font-medium text-[#32e768]">(Liberado em <?php echo $item['modulo']['release_days']; ?> dias)</span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="flex items-center space-x-2">
                             <button class="add-lesson-btn text-sm bg-[#32e768] text-white font-semibold py-2 px-4 rounded-lg hover:bg-[#28d15e] transition flex items-center space-x-1" data-modulo-id="<?php echo $item['modulo']['id']; ?>" data-modulo-titulo="<?php echo htmlspecialchars($item['modulo']['titulo']); ?>">
                                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                <span>Nova Aula</span>
                            </button>
                            <button class="edit-module-btn p-2 rounded-lg bg-yellow-500 text-white hover:bg-yellow-600 transition"
                                data-modulo-id="<?php echo $item['modulo']['id']; ?>"
                                data-modulo-titulo="<?php echo htmlspecialchars($item['modulo']['titulo']); ?>"
                                data-imagem-url="<?php echo htmlspecialchars($module_image_src); ?>"
                                data-imagem-raw="<?php echo htmlspecialchars($item['modulo']['imagem_capa_url'] ?? ''); ?>"
                                data-release-days="<?php echo htmlspecialchars($item['modulo']['release_days']); ?>">
                                <i data-lucide="edit" class="w-5 h-5"></i>
                            </button>
                            <form action="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" method="post" onsubmit="return confirm('Tem certeza que deseja deletar este módulo e todas as suas aulas?');">
                                <input type="hidden" name="modulo_id" value="<?php echo $item['modulo']['id']; ?>">
                                <button type="submit" name="deletar_modulo" class="text-white bg-red-500 p-2 rounded-lg hover:bg-red-600 transition">
                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="p-4">
                        <?php if (empty($item['aulas'])): ?>
                            <p class="text-gray-400 text-center py-4">Nenhuma aula neste módulo.</p>
                        <?php else: ?>
                            <ul class="space-y-3 sortable-aulas" data-modulo-id="<?php echo $item['modulo']['id']; ?>">
                                <?php foreach ($item['aulas'] as $aula): ?>
                                    <li class="flex justify-between items-center p-3 bg-dark-elevated rounded-md border border-dark-border hover:bg-dark-card aula-item" data-aula-id="<?php echo $aula['id']; ?>">
                                        <div class="flex items-center space-x-3 cursor-grab">
                                            <i data-lucide="grip-vertical" class="w-5 h-5 text-gray-500 flex-shrink-0"></i>
                                            <?php if ($aula['tipo_conteudo'] === 'video' || $aula['tipo_conteudo'] === 'mixed'): ?>
                                                <i data-lucide="play-circle" class="w-5 h-5 text-gray-400 flex-shrink-0"></i>
                                            <?php endif; ?>
                                            <?php if ($aula['tipo_conteudo'] === 'files' || $aula['tipo_conteudo'] === 'mixed'): ?>
                                                <i data-lucide="file-text" class="w-5 h-5 text-gray-400 flex-shrink-0"></i>
                                            <?php endif; ?>
                                            <span class="font-medium text-gray-300">
                                                <?php echo htmlspecialchars($aula['titulo']); ?>
                                                <?php if ($aula['release_days'] > 0): ?>
                                                    <span class="ml-2 text-sm font-medium text-[#32e768]">(Liberada em <?php echo $aula['release_days']; ?> dias)</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-2 flex-shrink-0">
                                            <button class="edit-lesson-btn text-blue-400 hover:text-blue-300 p-1 rounded-full"
                                                data-aula-id="<?php echo $aula['id']; ?>"
                                                data-titulo="<?php echo htmlspecialchars($aula['titulo']); ?>"
                                                data-url-video="<?php echo htmlspecialchars($aula['url_video'] ?? ''); ?>"
                                                data-origem-video="<?php echo htmlspecialchars($aula['origem_video'] ?? 'youtube'); ?>"
                                                data-descricao="<?php echo htmlspecialchars($aula['descricao'] ?? ''); ?>"
                                                data-release-days="<?php echo htmlspecialchars($aula['release_days']); ?>"
                                                data-tipo-conteudo="<?php echo htmlspecialchars($aula['tipo_conteudo']); ?>"
                                                data-lesson-cover-type="<?php echo htmlspecialchars($aula['lesson_cover_type'] ?? ''); ?>"
                                                data-lesson-cover-url="<?php echo htmlspecialchars($aula['lesson_cover_url'] ?? ''); ?>"
                                                data-lesson-cover-path="<?php echo htmlspecialchars($aula['lesson_cover_path'] ?? ''); ?>"
                                                data-files='<?php echo json_encode($aula['files']); ?>'>
                                                <i data-lucide="edit" class="w-5 h-5"></i>
                                            </button>
                                            <form action="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" method="post" onsubmit="return confirm('Tem certeza que deseja deletar esta aula?');" class="inline-block">
                                                <input type="hidden" name="aula_id" value="<?php echo $aula['id']; ?>">
                                                <button type="submit" name="deletar_aula" class="text-red-400 hover:text-red-300 p-1 rounded-full">
                                                    <i data-lucide="x-circle" class="w-5 h-5"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Adicionar Aula -->
<div id="add-lesson-modal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4 hidden overflow-y-auto">
    <div class="bg-dark-card rounded-xl shadow-2xl w-full max-w-4xl h-[90vh] max-h-[90vh] transform transition-all opacity-0 scale-95 border border-[#32e768] flex flex-col my-4" id="add-lesson-modal-content">
        <form action="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" method="post" enctype="multipart/form-data" class="flex flex-col h-full min-h-0">
            <div class="p-6 border-b border-dark-border flex-shrink-0"><h2 class="text-2xl font-bold text-white">Adicionar Nova Aula em <span id="modal-modulo-titulo-add" class="text-[#32e768]"></span></h2></div>
            <div class="p-6 space-y-4 overflow-y-auto flex-1 min-h-0">
                <input type="hidden" name="modulo_id" id="modal-modulo-id-add">
                <div><label for="add_titulo_aula" class="block text-gray-300 text-sm font-semibold mb-2">Título da Aula</label><input type="text" id="add_titulo_aula" name="titulo_aula" required class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white" placeholder="Ex: Aula 1 - Bem-vindo ao curso"></div>
                
                <!-- Tipo de Conteúdo da Aula (Add) -->
                <div>
                    <label for="add_tipo_conteudo" class="block text-gray-300 text-sm font-semibold mb-2">Tipo de Conteúdo</label>
                    <select id="add_tipo_conteudo" name="tipo_conteudo" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                        <option value="video">Somente Vídeo</option>
                        <option value="files">Somente Arquivos</option>
                        <option value="mixed">Vídeo e Arquivos</option>
                    </select>
                </div>

                <!-- Origem e URL do Vídeo (Add) -->
                <div id="add-video-url-container">
                    <div class="mb-4">
                        <label for="add_origem_video" class="block text-gray-300 text-sm font-semibold mb-2">Origem do Vídeo</label>
                        <select id="add_origem_video" name="origem_video" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                            <option value="youtube">YouTube</option>
                            <option value="vimeo">Vimeo</option>
                            <option value="self_hosted">Self-hosted (MP4)</option>
                        </select>
                    </div>
                    <label for="add_url_video" id="add_url_video_label" class="block text-gray-300 text-sm font-semibold mb-2">URL do Vídeo (YouTube)</label>
                    <input type="text" id="add_url_video" name="url_video" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white" placeholder="https://www.youtube.com/watch?v=...">
                </div>

                <!-- Upload de Arquivos (Add) -->
                <div id="add-files-upload-container">
                    <label for="add_aula_files" class="block text-gray-300 text-sm font-semibold mb-2">Upload de Arquivos</label>
                    <input type="file" id="add_aula_files" name="aula_files[]" multiple class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[#32e768]/20 file:text-[#32e768] hover:file:bg-[#32e768]/30">
                    <p class="mt-1 text-xs text-gray-400">Múltiplos arquivos (PDF, imagens, zip, etc.)</p>
                </div>

                <!-- Banner/Thumbnail opcional (Somente Arquivos) - Add -->
                <div id="add-lesson-cover-container" class="hidden">
                    <div class="bg-dark-elevated p-4 rounded-lg border border-dark-border">
                        <h4 class="text-sm font-semibold text-white mb-3">Banner/Thumbnail no lugar do placeholder</h4>
                        <p class="text-xs text-gray-400 mb-3">Exibido no player no lugar de "Esta aula não contém vídeo". Orientação vertical: <strong>720x1280</strong> (prioritário) ou <strong>720x1080</strong>.</p>
                        <div class="flex gap-4 mb-2">
                            <div class="flex-1">
                                <label class="block text-gray-300 text-xs font-semibold mb-1">Upload de imagem</label>
                                <input type="file" id="add_lesson_cover_upload" name="lesson_cover_upload" accept=".jpg,.jpeg,.png,.webp" class="w-full text-sm text-gray-400 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-[#32e768]/20 file:text-[#32e768]">
                            </div>
                            <div class="flex-1">
                                <label class="block text-gray-300 text-xs font-semibold mb-1">ou URL externa</label>
                                <input type="url" id="add_lesson_cover_url" name="lesson_cover_url" class="form-input-style w-full px-3 py-2 text-sm bg-dark-card border border-dark-border rounded-lg text-white" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                </div>

                <div><label for="add_descricao_aula" class="block text-gray-300 text-sm font-semibold mb-2">Descrição / Materiais</label><div id="add_descricao_editor" class="quill-editor-add bg-dark-elevated border border-dark-border rounded-lg min-h-[150px]" style="background:#1e293b!important;"></div><textarea id="add_descricao_aula" name="descricao_aula" class="hidden" aria-hidden="true"></textarea></div>
                <!-- Release Days for Add Lesson -->
                <div>
                    <label for="add_release_days_aula" class="block text-gray-300 text-sm font-semibold mb-2">Liberar após (dias)</label>
                    <input type="number" id="add_release_days_aula" name="release_days_aula" value="0" min="0" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white" placeholder="0 = Liberação imediata">
                    <p class="mt-1 text-xs text-gray-400">Defina quantos dias após a compra do curso esta aula será liberada para o aluno.</p>
                </div>
            </div>
            <div class="px-6 py-4 bg-dark-elevated rounded-b-xl flex justify-end items-center space-x-4 border-t border-dark-border flex-shrink-0">
                <button type="button" class="modal-cancel-btn bg-dark-card text-gray-300 font-bold py-2 px-5 rounded-lg hover:bg-dark-elevated border border-dark-border">Cancelar</button>
                <button type="submit" name="adicionar_aula" class="bg-[#32e768] text-white font-bold py-2 px-5 rounded-lg hover:bg-[#28d15e]">Salvar Aula</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Editar Aula -->
<div id="edit-lesson-modal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4 hidden overflow-y-auto">
    <div class="bg-dark-card rounded-xl shadow-2xl w-full max-w-4xl h-[90vh] max-h-[90vh] transform transition-all opacity-0 scale-95 border border-[#32e768] flex flex-col my-4" id="edit-lesson-modal-content">
        <form action="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" method="post" enctype="multipart/form-data" class="flex flex-col h-full min-h-0">
            <div class="p-6 border-b border-dark-border flex-shrink-0"><h2 class="text-2xl font-bold text-white">Editar Aula</h2></div>
            <div class="p-6 space-y-4 overflow-y-auto flex-1 min-h-0">
                <input type="hidden" name="aula_id" id="edit_aula_id">
                <div><label for="edit_titulo_aula" class="block text-gray-300 text-sm font-semibold mb-2">Título da Aula</label><input type="text" id="edit_titulo_aula" name="titulo_aula" required class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white" placeholder="Ex: Aula 1 - Bem-vindo ao curso"></div>

                <!-- Tipo de Conteúdo da Aula (Edit) -->
                <div>
                    <label for="edit_tipo_conteudo" class="block text-gray-300 text-sm font-semibold mb-2">Tipo de Conteúdo</label>
                    <select id="edit_tipo_conteudo" name="tipo_conteudo" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                        <option value="video">Somente Vídeo</option>
                        <option value="files">Somente Arquivos</option>
                        <option value="mixed">Vídeo e Arquivos</option>
                    </select>
                </div>

                <!-- Origem e URL do Vídeo (Edit) - Fase 1: youtube, vimeo, self_hosted -->
                <div id="edit-video-url-container">
                    <div class="mb-4">
                        <label for="edit_origem_video" class="block text-gray-300 text-sm font-semibold mb-2">Origem do Vídeo</label>
                        <select id="edit_origem_video" name="origem_video" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                            <option value="youtube">YouTube</option>
                            <option value="vimeo">Vimeo</option>
                            <option value="self_hosted">Self-hosted (MP4)</option>
                        </select>
                    </div>
                    <div id="edit-video-url-field-wrapper">
                        <label for="edit_url_video" id="edit_url_video_label" class="block text-gray-300 text-sm font-semibold mb-2">URL do Vídeo (YouTube)</label>
                        <input type="text" id="edit_url_video" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white" placeholder="https://www.youtube.com/watch?v=...">
                        <input type="hidden" id="edit_url_video_submit" name="url_video" value="">
                    </div>
                </div>

                <!-- Arquivos Existentes (Edit) -->
                <div id="edit-existing-files-container" class="space-y-2">
                    <p class="block text-gray-300 text-sm font-semibold mb-2">Arquivos Atuais:</p>
                    <div id="existing-files-list">
                        <!-- Arquivos serão carregados aqui via JS -->
                    </div>
                </div>

                <!-- Upload de Novos Arquivos (Edit) -->
                <div id="edit-new-files-upload-container">
                    <label for="edit_aula_files" class="block text-gray-300 text-sm font-semibold mb-2">Upload de Novos Arquivos</label>
                    <input type="file" id="edit_aula_files" name="aula_files[]" multiple class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[#32e768]/20 file:text-[#32e768] hover:file:bg-[#32e768]/30">
                    <p class="mt-1 text-xs text-gray-400">Múltiplos arquivos (PDF, imagens, zip, etc.)</p>
                </div>

                <!-- Banner/Thumbnail opcional (Somente Arquivos) -->
                <div id="edit-lesson-cover-container" class="hidden">
                    <div class="bg-dark-elevated p-4 rounded-lg border border-dark-border">
                        <h4 class="text-sm font-semibold text-white mb-3">Banner/Thumbnail no lugar do placeholder</h4>
                        <p class="text-xs text-gray-400 mb-3">Exibido no player no lugar de "Esta aula não contém vídeo". Orientação vertical: <strong>720x1280</strong> (prioritário) ou <strong>720x1080</strong>.</p>
                        <div class="flex gap-4 mb-2">
                            <div class="flex-1">
                                <label class="block text-gray-300 text-xs font-semibold mb-1">Upload de imagem</label>
                                <input type="file" id="edit_lesson_cover_upload" name="lesson_cover_upload" accept=".jpg,.jpeg,.png,.webp" class="w-full text-sm text-gray-400 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-[#32e768]/20 file:text-[#32e768]">
                            </div>
                            <div class="flex-1">
                                <label class="block text-gray-300 text-xs font-semibold mb-1">ou URL externa</label>
                                <input type="url" id="edit_lesson_cover_url" name="lesson_cover_url" class="form-input-style w-full px-3 py-2 text-sm bg-dark-card border border-dark-border rounded-lg text-white" placeholder="https://...">
                            </div>
                        </div>
                        <input type="hidden" name="remove_lesson_cover" id="edit_remove_lesson_cover" value="0">
                        <label class="flex items-center text-xs text-gray-400 mt-2"><input type="checkbox" id="edit_remove_lesson_cover_cb" class="mr-1"> Remover banner atual</label>
                    </div>
                </div>

                <div>
                    <label for="edit_descricao_aula" class="block text-gray-300 text-sm font-semibold mb-2">Descrição / Materiais</label>
                    <div id="edit_descricao_editor" class="quill-editor-edit bg-dark-elevated border border-dark-border rounded-lg min-h-[150px]" style="background:#1e293b!important;"></div>
                    <textarea id="edit_descricao_aula" name="descricao_aula" class="hidden" aria-hidden="true"></textarea>
                </div>
                <!-- Release Days for Edit Lesson -->
                <div>
                    <label for="edit_release_days_aula" class="block text-gray-300 text-sm font-semibold mb-2">Liberar após (dias)</label>
                    <input type="number" id="edit_release_days_aula" name="release_days_aula" value="0" min="0" class="form-input-style w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white" placeholder="0 = Liberação imediata">
                    <p class="mt-1 text-xs text-gray-400">Defina quantos dias após a compra do curso esta aula será liberada para o aluno.</p>
                </div>
            </div>
            <div class="px-6 py-4 bg-dark-elevated rounded-b-xl flex justify-end items-center space-x-4 border-t border-dark-border flex-shrink-0">
                <button type="button" class="modal-cancel-btn bg-dark-card text-gray-300 font-bold py-2 px-5 rounded-lg hover:bg-dark-elevated border border-dark-border">Cancelar</button>
                <button type="submit" name="editar_aula_form" class="bg-[#32e768] text-white font-bold py-2 px-5 rounded-lg hover:bg-[#28d15e]">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Editar Módulo -->
<div id="edit-module-modal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-dark-card rounded-xl shadow-2xl w-full max-w-2xl transform transition-all opacity-0 scale-95 border border-[#32e768]" id="edit-module-modal-content">
        <form action="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" method="post" enctype="multipart/form-data">
            <div class="p-6 border-b border-dark-border"><h2 class="text-2xl font-bold text-white">Editar Módulo</h2></div>
            <div class="p-6 space-y-4">
                <input type="hidden" name="modulo_id" id="modal-modulo-id-edit">
                <div><label for="modal-titulo-modulo-edit" class="block text-gray-300 text-sm font-semibold mb-2">Título do Módulo</label><input type="text" id="modal-titulo-modulo-edit" name="titulo_modulo" required class="form-input-style"></div>
                <div>
                    <label for="imagem_capa_modulo" class="block text-gray-300 text-sm font-semibold mb-2">Imagem de Capa do Módulo</label>
                    <img id="modal-imagem-preview" src="" alt="Preview da imagem" class="w-48 h-auto object-cover rounded-lg border border-dark-border mb-2 hidden">
                    <input type="file" id="imagem_capa_modulo" name="imagem_capa_modulo" class="w-full text-sm text-gray-300 bg-dark-elevated border border-dark-border rounded-lg px-4 py-2 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[#32e768]/20 file:text-[#32e768] hover:file:bg-[#32e768]/30 file:cursor-pointer cursor-pointer" accept="image/*">
                    <label for="imagem_capa_modulo_url" class="block text-gray-300 text-sm font-semibold mt-3 mb-2">Ou use URL externa</label>
                    <input type="url" id="imagem_capa_modulo_url" name="imagem_capa_modulo_url" class="form-input-style" placeholder="https://seusite.com/imagem.jpg">
                    <label class="mt-2 flex items-center text-sm text-gray-400">
                        <input type="checkbox" name="remove_imagem_capa_modulo" value="1" id="remove_imagem_capa_modulo" class="h-4 w-4 mr-1 bg-dark-elevated border-dark-border text-[#32e768] focus:ring-[#32e768] focus:ring-2 rounded cursor-pointer"> Remover imagem de capa existente
                    </label>
                </div>
                <!-- Release Days for Edit Module -->
                <div>
                    <label for="modal-release-days-modulo-edit" class="block text-gray-300 text-sm font-semibold mb-2">Liberar após (dias)</label>
                    <input type="number" id="modal-release-days-modulo-edit" name="release_days_modulo" value="0" min="0" class="form-input-style" placeholder="0 = Liberação imediata">
                    <p class="mt-1 text-xs text-gray-400">Defina quantos dias após a compra do curso este módulo será liberado para o aluno.</p>
                </div>
            </div>
            <div class="px-6 py-4 bg-dark-elevated rounded-b-xl flex justify-end items-center space-x-4 border-t border-dark-border">
                <button type="button" class="modal-cancel-btn bg-dark-card text-gray-300 font-bold py-2 px-5 rounded-lg hover:bg-dark-elevated border border-dark-border">Cancelar</button>
                <button type="submit" name="editar_modulo" class="bg-[#32e768] text-white font-bold py-2 px-5 rounded-lg hover:bg-[#28d15e]">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<style>
.form-input-style { 
    @apply w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white; 
}
.sortable-ghost { 
    opacity: 0.4; 
    background: rgba(50, 231, 104, 0.2); 
} /* Estilo para o item sendo arrastado */

/* Garantir inputs dark theme nos modais */
#add-lesson-modal input[type="text"],
#add-lesson-modal input[type="url"],
#add-lesson-modal input[type="number"],
#add-lesson-modal textarea,
#add-lesson-modal select,
#edit-lesson-modal input[type="text"],
#edit-lesson-modal input[type="url"],
#edit-lesson-modal input[type="number"],
#edit-lesson-modal textarea,
#edit-lesson-modal select,
#edit-module-modal input[type="text"],
#edit-module-modal input[type="url"],
#edit-module-modal input[type="number"],
#edit-module-modal input[type="file"] {
    background-color: #0f1419 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

#edit-module-modal input[type="checkbox"] {
    background-color: #0f1419 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    accent-color: #32e768 !important;
}

#add-lesson-modal input[type="text"]:focus,
#add-lesson-modal input[type="url"]:focus,
#add-lesson-modal input[type="number"]:focus,
#add-lesson-modal textarea:focus,
#add-lesson-modal select:focus,
#edit-lesson-modal input[type="text"]:focus,
#edit-lesson-modal input[type="url"]:focus,
#edit-lesson-modal input[type="number"]:focus,
#edit-lesson-modal textarea:focus,
#edit-lesson-modal select:focus {
    border-color: #32e768 !important;
    ring-color: #32e768 !important;
}

#add-lesson-modal select option,
#edit-lesson-modal select option {
    background-color: #0f1419 !important;
    color: #ffffff !important;
}

/* Quill: paleta de cores customizada (funciona em todos os navegadores) */
.quill-color-palette { display: none; position: fixed; z-index: 99999; background: #1e293b; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
.quill-color-palette.show { display: block; }
.quill-color-palette .palette-row { display: flex; gap: 4px; margin-bottom: 4px; }
.quill-color-palette .palette-row:last-child { margin-bottom: 0; }
.quill-color-palette .swatch { width: 24px; height: 24px; border-radius: 4px; cursor: pointer; border: 2px solid transparent; transition: transform 0.15s; }
.quill-color-palette .swatch:hover { transform: scale(1.15); border-color: #fff; }
</style>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    const currentProductId = <?php echo $produto_id; ?>;

    // Quill: paleta de cores customizada (evita bloqueio do input type=color)
    var QUILL_COLORS = ['#000000','#333333','#666666','#ffffff','#ef4444','#f97316','#eab308','#22c55e','#06b6d4','#3b82f6','#8b5cf6','#ec4899'];
    var quillAdd = null, quillEdit = null;
    const addEditorEl = document.getElementById('add_descricao_editor');
    const editEditorEl = document.getElementById('edit_descricao_editor');
    var quillColorTarget = null, quillColorSavedRange = null, quillColorMode = null; // 'color' ou 'background'
    function createColorPalette(id) {
        var pal = document.createElement('div');
        pal.className = 'quill-color-palette';
        pal.id = id;
        var rows = [QUILL_COLORS.slice(0,4), QUILL_COLORS.slice(4,8), QUILL_COLORS.slice(8,12)];
        rows.forEach(function(row) {
            var r = document.createElement('div');
            r.className = 'palette-row';
            row.forEach(function(hex) {
                var s = document.createElement('div');
                s.className = 'swatch';
                s.style.backgroundColor = hex;
                s.dataset.color = hex;
                s.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var q = quillColorTarget, mode = quillColorMode;
                    if (q && mode) {
                        var rng = quillColorSavedRange || q.getSelection(true);
                        if (rng) q.formatText(rng.index, rng.length, mode, hex, 'user');
                        else q.format(mode, hex, 'user');
                    }
                    document.querySelectorAll('.quill-color-palette').forEach(function(p){ p.classList.remove('show'); });
                });
                r.appendChild(s);
            });
            pal.appendChild(r);
        });
        document.body.appendChild(pal);
        return pal;
    }
    function showPalette(triggerEl, quill, mode) {
        document.querySelectorAll('.quill-color-palette').forEach(function(p){ p.classList.remove('show'); });
        quillColorTarget = quill;
        quillColorSavedRange = quill.getSelection(true);
        quillColorMode = mode;
        var palId = 'quill-palette-' + (mode === 'color' ? 'text' : 'bg');
        var pal = document.getElementById(palId);
        if (!pal) pal = createColorPalette(palId);
        var rect = triggerEl.getBoundingClientRect();
        pal.style.top = (rect.bottom + 4) + 'px';
        pal.style.left = rect.left + 'px';
        pal.classList.add('show');
    }
    function setupQuillColorHandlers(quill) {
        if (!quill) return;
        var tb = quill.getModule('toolbar');
        if (!tb) return;
        tb.addHandler('color', function() {
            var root = quill.root, cont = quill.container;
            var btn = (cont && cont.querySelector('.ql-color')) || (root.closest('.ql-container') && root.closest('.ql-container').parentElement && root.closest('.ql-container').parentElement.querySelector('.ql-color')) || root.parentElement;
            showPalette(btn, quill, 'color');
        });
        tb.addHandler('background', function() {
            var root = quill.root, cont = quill.container;
            var btn = (cont && cont.querySelector('.ql-background')) || (root.closest('.ql-container') && root.closest('.ql-container').parentElement && root.closest('.ql-container').parentElement.querySelector('.ql-background')) || root.parentElement;
            showPalette(btn, quill, 'background');
        });
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.quill-color-palette') && !e.target.closest('.ql-color') && !e.target.closest('.ql-background')) {
            document.querySelectorAll('.quill-color-palette').forEach(function(p){ p.classList.remove('show'); });
        }
    });
    function attachQuillColorClicks() {
        document.addEventListener('click', function(e) {
            var colorBtn = e.target.closest('.ql-color');
            var bgBtn = e.target.closest('.ql-background');
            var q = (addEditorEl && addEditorEl.contains(e.target)) ? quillAdd : ((editEditorEl && editEditorEl.contains(e.target)) ? quillEdit : null);
            if (colorBtn && q) { e.preventDefault(); e.stopPropagation(); showPalette(colorBtn, q, 'color'); }
            if (bgBtn && q) { e.preventDefault(); e.stopPropagation(); showPalette(bgBtn, q, 'background'); }
        }, true);
    }
    if (addEditorEl && typeof Quill !== 'undefined') {
        quillAdd = new Quill(addEditorEl, { theme: 'snow', placeholder: 'Links, textos de apoio, HTML com links...', modules: { toolbar: [['bold','italic','underline'],['link'],['color','background'],['{list:ordered}','{list:bullet}']] } });
        quillAdd.on('text-change', () => { const t = document.getElementById('add_descricao_aula'); if (t) t.value = quillAdd.root.innerHTML; });
        setupQuillColorHandlers(quillAdd);
    }
    if (editEditorEl && typeof Quill !== 'undefined') {
        quillEdit = new Quill(editEditorEl, { theme: 'snow', placeholder: 'Links, textos de apoio, HTML com links...', modules: { toolbar: [['bold','italic','underline'],['link'],['color','background'],['{list:ordered}','{list:bullet}']] } });
        quillEdit.on('text-change', () => { const t = document.getElementById('edit_descricao_aula'); if (t) t.value = quillEdit.root.innerHTML; });
        setupQuillColorHandlers(quillEdit);
    }
    attachQuillColorClicks();

    // --- Lógica genérica para Modais ---
    function openModal(modal) {
        modal.classList.remove('hidden');
        setTimeout(() => {
            const content = modal.querySelector('.transform');
            if (content) content.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeModal(modal) {
        const content = modal.querySelector('.transform');
        if (content) content.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            const form = modal.querySelector('form');
            if (form) form.reset();
        }, 200);
    }

    document.querySelectorAll('.modal-cancel-btn').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.closest('.fixed')));
    });

    // Antes do submit: sincronizar Quill -> textarea
    document.getElementById('add-lesson-modal')?.querySelector('form')?.addEventListener('submit', function() {
        if (quillAdd) document.getElementById('add_descricao_aula').value = quillAdd.root.innerHTML;
    });
    document.getElementById('edit-lesson-modal')?.querySelector('form')?.addEventListener('submit', function() {
        if (quillEdit) document.getElementById('edit_descricao_aula').value = quillEdit.root.innerHTML;
    });

    document.querySelectorAll('.fixed[id$="-modal"]').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });

    // --- Lógica para Modal de Adicionar Aula ---
    const addLessonModal = document.getElementById('add-lesson-modal');
    const addTipoConteudoSelect = document.getElementById('add_tipo_conteudo');
    const addVideoUrlContainer = document.getElementById('add-video-url-container');
    const addAulaFilesContainer = document.getElementById('add-files-upload-container');
    const addUrlVideoInput = document.getElementById('add_url_video');
    const addAulaFilesInput = document.getElementById('add_aula_files');

    const addLessonCoverContainer = document.getElementById('add-lesson-cover-container');
    function toggleAddLessonFields() {
        const selectedType = addTipoConteudoSelect.value;
        addUrlVideoInput.required = false;
        addAulaFilesInput.required = false;
        addVideoUrlContainer.style.display = 'none';
        addAulaFilesContainer.style.display = 'none';
        if (addLessonCoverContainer) addLessonCoverContainer.classList.add('hidden');
        if (selectedType === 'video' || selectedType === 'mixed') {
            addVideoUrlContainer.style.display = 'block';
            addUrlVideoInput.required = true;
        }
        if (selectedType === 'files' || selectedType === 'mixed') {
            addAulaFilesContainer.style.display = 'block';
            addAulaFilesInput.required = true;
        }
        // Banner/Thumbnail: somente para "Somente Arquivos" (não para Vídeo e Arquivos)
        if (selectedType === 'files' && addLessonCoverContainer) {
            addLessonCoverContainer.classList.remove('hidden');
        }
    }

    addTipoConteudoSelect.addEventListener('change', toggleAddLessonFields);

    document.querySelectorAll('.add-lesson-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('modal-modulo-id-add').value = this.dataset.moduloId;
            document.getElementById('modal-modulo-titulo-add').textContent = this.dataset.moduloTitulo;
            document.getElementById('add_release_days_aula').value = 0;
            addTipoConteudoSelect.value = 'video';
            const addOrigemEl = document.getElementById('add_origem_video');
            if (addOrigemEl) addOrigemEl.value = 'youtube';
            addUrlVideoInput.value = '';
            if (quillAdd) { quillAdd.setContents([]); quillAdd.setText(''); }
            document.getElementById('add_descricao_aula').value = '';
            const acu = document.getElementById('add_lesson_cover_upload');
            const acu2 = document.getElementById('add_lesson_cover_url');
            if (acu) acu.value = '';
            if (acu2) acu2.value = '';
            toggleAddLessonFields();
            openModal(addLessonModal);
        });
    });

    // --- Lógica para Modal de Editar Aula ---
    const editLessonModal = document.getElementById('edit-lesson-modal');
    const editTipoConteudoSelect = document.getElementById('edit_tipo_conteudo');
    const editVideoUrlContainer = document.getElementById('edit-video-url-container');
    const editExistingFilesContainer = document.getElementById('edit-existing-files-container');
    const editNewFilesUploadContainer = document.getElementById('edit-new-files-upload-container');
    const editUrlVideoInput = document.getElementById('edit_url_video');
    const existingFilesList = document.getElementById('existing-files-list');
    const editAulaFilesInput = document.getElementById('edit_aula_files');

    const editLessonCoverContainer = document.getElementById('edit-lesson-cover-container');
    function toggleEditLessonFields() {
        const selectedType = editTipoConteudoSelect.value;
        editUrlVideoInput.required = false;
        editAulaFilesInput.required = false;
        editVideoUrlContainer.style.display = 'none';
        editExistingFilesContainer.style.display = 'none';
        editNewFilesUploadContainer.style.display = 'none';
        if (editLessonCoverContainer) editLessonCoverContainer.classList.add('hidden');
        if (selectedType === 'video' || selectedType === 'mixed') {
            editVideoUrlContainer.style.display = 'block';
            editUrlVideoInput.required = true;
        }
        if (selectedType === 'files' || selectedType === 'mixed') {
            editExistingFilesContainer.style.display = 'block';
            editNewFilesUploadContainer.style.display = 'block';
            const anyExistingFileSelectedToKeep = existingFilesList.querySelectorAll('input[name="existing_files[]"]:checked').length > 0;
            if (!anyExistingFileSelectedToKeep) editAulaFilesInput.required = true;
        }
        // Banner/Thumbnail: somente para "Somente Arquivos" (não para Vídeo e Arquivos)
        if (selectedType === 'files' && editLessonCoverContainer) {
            editLessonCoverContainer.classList.remove('hidden');
        }
    }

    editTipoConteudoSelect.addEventListener('change', toggleEditLessonFields);
    existingFilesList.addEventListener('change', (e) => {
        if (e.target.type === 'checkbox' && (editTipoConteudoSelect.value === 'files' || editTipoConteudoSelect.value === 'mixed')) toggleEditLessonFields();
    });

    document.querySelectorAll('.edit-lesson-btn').forEach(button => {
        button.addEventListener('click', function() {
            const aulaId = this.dataset.aulaId;
            const titulo = this.dataset.titulo;
            const urlVideo = this.dataset.urlVideo || '';
            const descricao = this.dataset.descricao;
            const releaseDays = this.dataset.releaseDays;
            const tipoConteudo = this.dataset.tipoConteudo;
            const files = JSON.parse(this.dataset.files || '[]');

            document.getElementById('edit_aula_id').value = aulaId;
            document.getElementById('edit_titulo_aula').value = titulo;
            editUrlVideoInput.value = urlVideo;
            const origemVideo = (this.dataset.origemVideo || 'youtube').toLowerCase();
            const editOrigemEl = document.getElementById('edit_origem_video');
            if (editOrigemEl) editOrigemEl.value = ['youtube', 'vimeo', 'self_hosted'].includes(origemVideo) ? origemVideo : 'youtube';
            if (quillEdit) quillEdit.root.innerHTML = descricao || '';
            document.getElementById('edit_descricao_aula').value = descricao || '';
            document.getElementById('edit_release_days_aula').value = releaseDays;
            document.getElementById('edit_tipo_conteudo').value = tipoConteudo;
            const coverType = this.dataset.lessonCoverType || '';
            const coverUrl = this.dataset.lessonCoverUrl || '';
            const coverPath = this.dataset.lessonCoverPath || '';
            const editCoverUrl = document.getElementById('edit_lesson_cover_url');
            const editCoverUpload = document.getElementById('edit_lesson_cover_upload');
            const editRemoveCoverCb = document.getElementById('edit_remove_lesson_cover_cb');
            if (editCoverUrl) editCoverUrl.value = coverUrl;
            if (editCoverUpload) editCoverUpload.value = '';
            if (editRemoveCoverCb) editRemoveCoverCb.checked = false;

            // Preencher lista de arquivos existentes
            existingFilesList.innerHTML = '';
            if (files && files.length > 0) {
                files.forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'flex items-center space-x-2 p-2 bg-dark-elevated rounded-md border border-dark-border';
                    fileItem.innerHTML = `
                        <input type="checkbox" name="existing_files[]" value="${file.id}" id="edit_file_${file.id}" class="h-4 w-4 text-[#32e768] focus:ring-[#32e768] rounded" checked>
                        <label for="edit_file_${file.id}" class="text-sm text-gray-300">${file.nome_original}</label>
                        <a href="${file.caminho_arquivo}" target="_blank" class="ml-auto text-blue-400 hover:text-blue-300 hover:underline"><i data-lucide="download" class="w-4 h-4"></i></a>
                    `;
                    existingFilesList.appendChild(fileItem);
                });
            } else {
                existingFilesList.innerHTML = '<p class="text-sm text-gray-400">Nenhum arquivo enviado para esta aula.</p>';
            }

            // Reset o input de novos arquivos
            editAulaFilesInput.value = '';

            toggleEditLessonFields();
            lucide.createIcons();
            openModal(editLessonModal);
        });
    });

    // Ao submeter o formulário de edição, enviar o valor atual do campo URL do vídeo
    const editLessonForm = document.querySelector('#edit-lesson-modal form');
    if (editLessonForm) {
        editLessonForm.addEventListener('submit', function() {
            const sub = document.getElementById('edit_url_video_submit');
            const inp = document.getElementById('edit_url_video');
            if (sub && inp) sub.value = inp.value;
        });
    }

    // --- Lógica para Modal de Editar Módulo ---
    const editModuleModal = document.getElementById('edit-module-modal');
    const imgPreview = document.getElementById('modal-imagem-preview');
    const removeImageCheckbox = document.getElementById('remove_imagem_capa_modulo');
    const moduleCoverUrlInput = document.getElementById('imagem_capa_modulo_url');
    document.querySelectorAll('.edit-module-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('modal-modulo-id-edit').value = this.dataset.moduloId;
            document.getElementById('modal-titulo-modulo-edit').value = this.dataset.moduloTitulo;
            document.getElementById('modal-release-days-modulo-edit').value = this.dataset.releaseDays; 
            const imageUrl = this.dataset.imageUrl;
            const rawImageUrl = this.dataset.imagemRaw || '';
            if (moduleCoverUrlInput) {
                moduleCoverUrlInput.value = (rawImageUrl.startsWith('http://') || rawImageUrl.startsWith('https://')) ? rawImageUrl : '';
            }
            if (imageUrl) {
                imgPreview.src = imageUrl;
                imgPreview.classList.remove('hidden');
                removeImageCheckbox.checked = false; // Garante que não esteja marcado ao abrir
                removeImageCheckbox.parentElement.style.display = 'flex'; // Mostra a opção de remover
            } else {
                imgPreview.src = '';
                imgPreview.classList.add('hidden');
                removeImageCheckbox.checked = false;
                removeImageCheckbox.parentElement.style.display = 'none'; // Esconde se não houver imagem
            }
            openModal(editModuleModal);
        });
    });

    // --- Lógica de Reordenação (Drag-and-Drop) de Aulas ---
    document.querySelectorAll('.sortable-aulas').forEach(ul => {
        new Sortable(ul, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            handle: '.cursor-grab', // A alça para arrastar será o ícone de 'grip-vertical'
            onEnd: function (evt) {
                const moduloId = evt.from.dataset.moduloId;
                const newOrder = Array.from(evt.from.children).map(item => item.dataset.aulaId);
                
                // Enviar a nova ordem para a API
                fetch('/api/api?action=reorder_aulas', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        modulo_id: moduloId,
                        aulas_order: newOrder,
                        produto_id: currentProductId // Passa o ID do produto para validação
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Opcional: Feedback visual de sucesso
                        // alert('Ordem das aulas atualizada!');
                        console.log('Ordem das aulas atualizada com sucesso!');
                    } else {
                        // alert('Erro ao reordenar aulas: ' + (data.error || 'Erro desconhecido.'));
                        console.error('Erro ao reordenar aulas:', data.error);
                        // Opcional: Recarregar a página para reverter a ordem visual para a do banco
                        // window.location.reload(); 
                    }
                })
                .catch(error => {
                    console.error('Erro de rede ao reordenar aulas:', error);
                    // alert('Erro de comunicação com o servidor ao reordenar aulas.');
                    // window.location.reload();
                });
            }
        });
    });

    // Validação de ratio do banner (720x1280 ou 720x1080 = vertical)
    function validarRatioBanner(input, callback) {
        if (!input || !input.files || !input.files[0]) return;
        var f = input.files[0];
        if (!f.type.match(/image\/(jpeg|png|webp)/i)) return;
        var img = new Image();
        img.onload = function() {
            var w = img.width, h = img.height;
            var ratio = w / h;
            var idealMin = 720/1280; var idealMax = 720/1080;
            if (ratio < idealMin * 0.7 || ratio > idealMax * 1.3) {
                if (typeof callback === 'function') callback(false, 'Proporção muito diferente do recomendado (vertical 720x1280 ou 720x1080). A imagem pode não exibir bem.');
            } else if (typeof callback === 'function') callback(true);
        };
        img.src = URL.createObjectURL(f);
    }
    var addCoverUpload = document.getElementById('add_lesson_cover_upload');
    var editCoverUpload = document.getElementById('edit_lesson_cover_upload');
    if (addCoverUpload) addCoverUpload.addEventListener('change', function() { validarRatioBanner(this, function(ok, msg) { if (!ok && msg) alert(msg); }); });
    if (editCoverUpload) editCoverUpload.addEventListener('change', function() { validarRatioBanner(this, function(ok, msg) { if (!ok && msg) alert(msg); }); });

    // Chamada inicial para toggleAddLessonFields para garantir que o formulário "Adicionar Aula" esteja correto ao carregar
    toggleAddLessonFields();

    // --- Comentários: carregar lista e filtros ---
    const comentariosListContainer = document.getElementById('comentarios-list-container');
    const comentariosFilterBtns = document.querySelectorAll('.comentarios-filter-btn');
    if (comentariosListContainer && comentariosFilterBtns.length) {
        let comentariosCurrentStatus = 'all';
        function loadComentarios() {
            fetch(`/api/api?action=list_aula_comentarios_admin&produto_id=${currentProductId}&status=${comentariosCurrentStatus}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        comentariosListContainer.innerHTML = '<p class="text-red-400 text-sm">Erro ao carregar comentários.</p>';
                        return;
                    }
                    const comentarios = data.comentarios || [];
                    if (comentarios.length === 0) {
                        comentariosListContainer.innerHTML = '<p class="text-gray-500 text-sm">Nenhum comentário encontrado.</p>';
                        return;
                    }
                    comentariosListContainer.innerHTML = comentarios.map(c => {
                        const statusBadge = c.status === 'pending' ? 'bg-yellow-600' : (c.status === 'approved' ? 'bg-green-600' : 'bg-red-600');
                        const actions = c.status === 'pending' ? `
                            <button type="button" class="comentario-approve-btn text-green-400 hover:text-green-300 text-sm font-medium" data-id="${c.id}">Aprovar</button>
                            <button type="button" class="comentario-reject-btn text-red-400 hover:text-red-300 text-sm font-medium" data-id="${c.id}">Rejeitar</button>
                        ` : '';
                        const respostaHtml = (c.status === 'approved') ? `
                            <div class="mt-3 pt-3 border-t border-dark-border">
                                ${c.resposta_infoprodutor ? `<p class="text-green-300 text-sm mb-2"><strong>Sua resposta:</strong> ${escapeHtml(c.resposta_infoprodutor)}</p>` : ''}
                                <textarea class="resposta-textarea w-full px-3 py-2 bg-dark-card border border-dark-border rounded text-white text-sm" rows="2" placeholder="Responder ao aluno..." data-id="${c.id}">${escapeHtml(c.resposta_infoprodutor || '')}</textarea>
                                <button type="button" class="comentario-resposta-btn mt-1 text-[#32e768] hover:text-[#28d15e] text-sm font-medium" data-id="${c.id}">Salvar resposta</button>
                            </div>
                        ` : '';
                        return `<div class="p-3 bg-dark-elevated rounded-lg border border-dark-border" data-id="${c.id}">
                            <div class="flex justify-between items-start gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-white font-medium">${escapeHtml(c.nome_aluno || c.aluno_email)}</p>
                                    <p class="text-xs text-gray-500">${c.modulo_titulo} › ${escapeHtml(c.aula_titulo)}</p>
                                    <p class="text-gray-300 text-sm mt-2">${escapeHtml(c.texto)}</p>
                                    <p class="text-xs text-gray-500 mt-2">${formatDate(c.created_at)}</p>
                                    ${respostaHtml}
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="px-2 py-0.5 rounded text-xs ${statusBadge}">${c.status === 'pending' ? 'Pendente' : (c.status === 'approved' ? 'Aprovado' : 'Rejeitado')}</span>
                                    ${actions}
                                </div>
                            </div>
                        </div>`;
                    }).join('');
                    comentariosListContainer.querySelectorAll('.comentario-approve-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            fetch('/api/api?action=approve_comentario', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: parseInt(btn.dataset.id) }) })
                                .then(r => r.json()).then(d => { if (d.success) loadComentarios(); });
                        });
                    });
                    comentariosListContainer.querySelectorAll('.comentario-reject-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            fetch('/api/api?action=reject_comentario', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: parseInt(btn.dataset.id) }) })
                                .then(r => r.json()).then(d => { if (d.success) loadComentarios(); });
                        });
                    });
                    comentariosListContainer.querySelectorAll('.comentario-resposta-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const id = parseInt(btn.dataset.id);
                            const textarea = comentariosListContainer.querySelector(`.resposta-textarea[data-id="${id}"]`);
                            const resposta = textarea ? textarea.value.trim() : '';
                            fetch('/api/api?action=resposta_comentario', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, resposta }) })
                                .then(r => r.json()).then(d => { if (d.success) loadComentarios(); else if (d.error) alert(d.error); });
                        });
                    });
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                })
                .catch(() => { comentariosListContainer.innerHTML = '<p class="text-red-400 text-sm">Erro ao carregar.</p>'; });
        }
        function escapeHtml(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        function formatDate(s) { if (!s) return ''; try { const d = new Date(s); return d.toLocaleString('pt-BR'); } catch(e) { return s; } }
        comentariosFilterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                comentariosFilterBtns.forEach(b => { b.classList.remove('bg-[#32e768]', 'text-white'); b.classList.add('bg-gray-600', 'text-gray-300'); });
                btn.classList.remove('bg-gray-600', 'text-gray-300'); btn.classList.add('bg-[#32e768]', 'text-white');
                comentariosCurrentStatus = btn.dataset.status;
                loadComentarios();
            });
        });
        loadComentarios();
    }
});
</script>
