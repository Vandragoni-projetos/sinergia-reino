<?php
/**
 * API de Logs
 * Endpoints para visualização e gerenciamento de logs do sistema
 */

// Garantir que sempre retornamos JSON, mesmo em caso de erro fatal
ob_start();

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../helpers/log_viewer.php';
    
    // Tentar carregar security_helper se existir
    if (file_exists(__DIR__ . '/../helpers/security_helper.php')) {
        require_once __DIR__ . '/../helpers/security_helper.php';
    }
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar dependências',
        'message' => $e->getMessage()
    ]);
    exit;
}

// Configurar para não exibir erros no navegador
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../api_errors.log');

// Limpar qualquer output anterior
ob_end_clean();

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Verificar se é admin
if ($_SESSION["tipo"] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas administradores podem acessar logs.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Tratamento de erro global
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Erro na API de logs: [$errno] $errstr em $errfile:$errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    switch ($action) {
        case 'test':
            echo json_encode([
                'success' => true,
                'message' => 'API funcionando',
                'php_version' => PHP_VERSION,
                'session' => [
                    'loggedin' => $_SESSION["loggedin"] ?? false,
                    'tipo' => $_SESSION["tipo"] ?? 'none',
                    'id' => $_SESSION["id"] ?? 0
                ],
                'helpers' => [
                    'log_viewer' => function_exists('get_log_config'),
                    'security_helper' => function_exists('log_security_event')
                ]
            ]);
            break;
            
        case 'list_logs':
            list_logs();
            break;
            
        case 'read_log':
            read_log();
            break;
            
        case 'download_log':
            download_log();
            break;
            
        case 'clear_log':
            clear_log();
            break;
            
        case 'get_stats':
            get_stats();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
            break;
    }
} catch (Exception $e) {
    $error_details = [
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTrace(), 0, 3) // Primeiras 3 linhas do stack trace
    ];
    
    error_log("Erro na API de logs: " . json_encode($error_details));
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
} finally {
    restore_error_handler();
}

/**
 * Lista todos os logs disponíveis com estatísticas
 */
function list_logs() {
    $log_config = get_log_config();
    $result = [];
    
    foreach ($log_config as $category => $logs) {
        $result[$category] = [];
        
        foreach ($logs as $file_name => $config) {
            $stats = get_log_stats($config['path']);
            
            $result[$category][$file_name] = array_merge($config, [
                'file_name' => $file_name,
                'stats' => $stats
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $result
    ]);
}

/**
 * Lê conteúdo de um log específico
 */
function read_log() {
    $log_file = $_GET['log_file'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $per_page = intval($_GET['per_page'] ?? 50);
    $search = $_GET['search'] ?? '';
    $date_filter = $_GET['date_filter'] ?? '';
    
    if (empty($log_file)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome do arquivo não fornecido']);
        return;
    }
    
    // Validar arquivo
    $log_config = get_log_config();
    $file_path = null;
    
    foreach ($log_config as $category => $logs) {
        if (isset($logs[$log_file])) {
            $file_path = $logs[$log_file]['path'];
            break;
        }
    }
    
    if ($file_path === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Arquivo de log inválido']);
        return;
    }
    
    $result = read_log_lines($file_path, $page, $per_page, $search, $date_filter);
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
}

/**
 * Download de um arquivo de log
 */
function download_log() {
    $log_file = $_GET['log_file'] ?? '';
    
    if (empty($log_file)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome do arquivo não fornecido']);
        return;
    }
    
    // Validar arquivo
    $log_config = get_log_config();
    $file_path = null;
    
    foreach ($log_config as $category => $logs) {
        if (isset($logs[$log_file])) {
            $file_path = $logs[$log_file]['path'];
            break;
        }
    }
    
    if ($file_path === null || !file_exists($file_path)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Arquivo de log não encontrado']);
        return;
    }
    
    // Registrar ação de segurança (se função existir)
    if (function_exists('log_security_event')) {
        try {
            log_security_event('log_download', [
                'log_file' => $log_file,
                'user_id' => $_SESSION['id']
            ], $_SESSION['id']);
        } catch (Exception $e) {
            error_log("Erro ao registrar evento de segurança: " . $e->getMessage());
        }
    }
    
    // Forçar download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $log_file . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}

/**
 * Limpa um arquivo de log (com backup)
 */
function clear_log() {
    $log_file = $_POST['log_file'] ?? '';
    
    if (empty($log_file)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome do arquivo não fornecido']);
        return;
    }
    
    // Validar arquivo
    $log_config = get_log_config();
    $file_path = null;
    
    foreach ($log_config as $category => $logs) {
        if (isset($logs[$log_file])) {
            $file_path = $logs[$log_file]['path'];
            break;
        }
    }
    
    if ($file_path === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Arquivo de log inválido']);
        return;
    }
    
    // Registrar ação de segurança (se função existir)
    if (function_exists('log_security_event')) {
        try {
            log_security_event('log_clear', [
                'log_file' => $log_file,
                'user_id' => $_SESSION['id']
            ], $_SESSION['id']);
        } catch (Exception $e) {
            error_log("Erro ao registrar evento de segurança: " . $e->getMessage());
        }
    }
    
    $result = clear_log_file($file_path);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'backup' => basename($result['backup'])
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['message']
        ]);
    }
}

/**
 * Retorna estatísticas de um log específico
 */
function get_stats() {
    $log_file = $_GET['log_file'] ?? '';
    
    if (empty($log_file)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nome do arquivo não fornecido']);
        return;
    }
    
    // Validar arquivo
    $log_config = get_log_config();
    $file_path = null;
    
    foreach ($log_config as $category => $logs) {
        if (isset($logs[$log_file])) {
            $file_path = $logs[$log_file]['path'];
            break;
        }
    }
    
    if ($file_path === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Arquivo de log inválido']);
        return;
    }
    
    $stats = get_log_stats($file_path);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}
