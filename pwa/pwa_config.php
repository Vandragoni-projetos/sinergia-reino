<?php
/**
 * PWA Module Configuration
 * Funções de verificação e configuração do módulo PWA
 */

// Garante que config.php está carregado
if (!isset($pdo) && file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

/**
 * Verifica se o módulo PWA está instalado
 * @return bool True se a pasta pwa/ existe, False caso contrário
 */
function pwa_module_installed() {
    $pwa_dir = __DIR__;
    return is_dir($pwa_dir) && file_exists($pwa_dir . '/pwa_functions.php');
}

/**
 * Busca configurações do PWA diretamente do banco SEM iniciar sessão
 * Esta função é usada pelo manifest.php para evitar output de sessão
 * @return array|false Array com configurações ou false se não encontrado
 */
function pwa_get_config_direct() {
    if (!pwa_module_installed()) {
        return false;
    }
    
    // Lê constantes do config.php sem incluir o arquivo completo (evita sessão)
    $config_file = __DIR__ . '/../config/config.php';
    if (!file_exists($config_file)) {
        return false;
    }
    
    // Lê o arquivo e extrai as constantes
    $config_content = file_get_contents($config_file);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $config_content, $host_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $config_content, $user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"]/", $config_content, $pass_match);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $config_content, $name_match);
    
    if (empty($host_match[1]) || empty($user_match[1]) || empty($name_match[1])) {
        return false;
    }
    
    $db_host = $host_match[1];
    $db_user = $user_match[1];
    $db_pass = $pass_match[1] ?? '';
    $db_name = $name_match[1];
    
    try {
        // Cria conexão PDO própria SEM iniciar sessão
        $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET time_zone = '-03:00';");
        
        // Verifica se a tabela existe
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'pwa_config'");
        if ($stmt_check->rowCount() == 0) {
            return false;
        }
        
        // Busca configuração
        $stmt = $pdo->query("SELECT * FROM pwa_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            // Busca cor primária do configuracoes_sistema (compatível com sinergiacore/GatewayPro)
            try {
                $stmt_settings = $pdo->query("SELECT valor FROM configuracoes_sistema WHERE chave = 'cor_primaria' LIMIT 1");
                $cor_setting = $stmt_settings->fetch(PDO::FETCH_ASSOC);
                if ($cor_setting && !empty($cor_setting['valor']) && (empty($config['theme_color']) || $config['theme_color'] == '#32e768')) {
                    $config['theme_color'] = $cor_setting['valor'];
                }
            } catch (PDOException $e) {
                // Ignora erro se tabela não existir
            }
            
            return $config;
        }
        
        // Retorna valores padrão
        return [
            'app_name' => 'Plataforma',
            'short_name' => 'App',
            'description' => '',
            'icon_path' => '',
            'theme_color' => '#32e768',
            'background_color' => '#ffffff',
            'display_mode' => 'standalone',
            'start_url' => '/',
            'scope' => '/',
            'push_enabled' => 0
        ];
    } catch (PDOException $e) {
        // Não loga erro para evitar output
        return false;
    }
}

/**
 * Busca configurações do PWA no banco de dados
 * @return array|false Array com configurações ou false se não encontrado
 */
function pwa_get_config() {
    global $pdo;
    
    if (!pwa_module_installed()) {
        return false;
    }
    
    // Garante que $pdo está disponível
    if (!isset($pdo) && file_exists(__DIR__ . '/../config/config.php')) {
        require_once __DIR__ . '/../config/config.php';
    }
    
    if (!isset($pdo)) {
        error_log("PWA: PDO não disponível em pwa_get_config");
        return false;
    }
    
    try {
        // Verifica se a tabela existe
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'pwa_config'");
        if ($stmt_check->rowCount() == 0) {
            return false;
        }
        
        $stmt = $pdo->query("SELECT * FROM pwa_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            // Busca cor primária do sistema como fallback para theme_color
            if (empty($config['theme_color']) || $config['theme_color'] == '#32e768') {
                if (function_exists('getSystemSetting')) {
                    $cor_primaria = getSystemSetting('cor_primaria', '#32e768');
                    if (!empty($cor_primaria)) {
                        $config['theme_color'] = $cor_primaria;
                    }
                }
            }
            
            return $config;
        }
        
        // Se não há configuração, retorna valores padrão
        return [
            'app_name' => 'Plataforma',
            'short_name' => 'App',
            'description' => '',
            'icon_path' => '',
            'theme_color' => function_exists('getSystemSetting') ? getSystemSetting('cor_primaria', '#32e768') : '#32e768',
            'background_color' => '#ffffff',
            'display_mode' => 'standalone',
            'start_url' => '/',
            'scope' => '/',
            'push_enabled' => 0
        ];
    } catch (PDOException $e) {
        error_log("Erro ao buscar configuração PWA: " . $e->getMessage());
        return false;
    }
}

/**
 * Salva configurações do PWA no banco de dados
 * @param array $config Array com as configurações
 * @return bool True se sucesso, False se erro
 */
function pwa_save_config($config) {
    global $pdo;
    
    if (!pwa_module_installed()) {
        return false;
    }
    
    // Garante que $pdo está disponível
    if (!isset($pdo) && file_exists(__DIR__ . '/../config/config.php')) {
        require_once __DIR__ . '/../config/config.php';
    }
    
    if (!isset($pdo)) {
        error_log("PWA: PDO não disponível em pwa_save_config");
        return false;
    }
    
    try {
        // Verifica se a tabela existe
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'pwa_config'");
        if ($stmt_check->rowCount() == 0) {
            error_log("PWA: Tabela pwa_config não existe. Execute a migração SQL primeiro.");
            return false;
        }
        
        // Busca configuração existente
        $stmt_get = $pdo->query("SELECT id FROM pwa_config ORDER BY id DESC LIMIT 1");
        $existing = $stmt_get->fetch(PDO::FETCH_ASSOC);
        
        $app_name = $config['app_name'] ?? 'Plataforma';
        $short_name = $config['short_name'] ?? 'App';
        $description = $config['description'] ?? '';
        $icon_path = $config['icon_path'] ?? '';
        $theme_color = $config['theme_color'] ?? '#32e768';
        $background_color = $config['background_color'] ?? '#ffffff';
        $display_mode = $config['display_mode'] ?? 'standalone';
        $start_url = $config['start_url'] ?? '/';
        $scope = $config['scope'] ?? '/';
        $push_enabled = isset($config['push_enabled']) ? (int)$config['push_enabled'] : 0;
        
        if ($existing) {
            // Atualiza configuração existente
            $stmt = $pdo->prepare("
                UPDATE pwa_config SET 
                    app_name = ?, 
                    short_name = ?, 
                    description = ?, 
                    icon_path = ?, 
                    theme_color = ?, 
                    background_color = ?, 
                    display_mode = ?, 
                    start_url = ?, 
                    scope = ?, 
                    push_enabled = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $app_name, $short_name, $description, $icon_path,
                $theme_color, $background_color, $display_mode,
                $start_url, $scope, $push_enabled, $existing['id']
            ]);
        } else {
            // Insere nova configuração
            $stmt = $pdo->prepare("
                INSERT INTO pwa_config (
                    app_name, short_name, description, icon_path,
                    theme_color, background_color, display_mode,
                    start_url, scope, push_enabled
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $app_name, $short_name, $description, $icon_path,
                $theme_color, $background_color, $display_mode,
                $start_url, $scope, $push_enabled
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao salvar configuração PWA: " . $e->getMessage());
        return false;
    }
}
