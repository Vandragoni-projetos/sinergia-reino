<?php
/**
 * PWA Module Functions
 * Funções principais do módulo PWA
 */

// Garante que config.php está carregado
if (!isset($pdo) && file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

// Carrega configurações
if (!function_exists('pwa_module_installed')) {
    require_once __DIR__ . '/pwa_config.php';
}

/**
 * Gera o conteúdo do manifest.json baseado nas configurações
 * @return string JSON do manifest
 */
function pwa_generate_manifest() {
    // Garante que não há output antes do JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    $config = pwa_get_config();
    
    if (!$config) {
        // Retorna manifest padrão se não houver configuração
        $config = [
            'app_name' => 'Plataforma',
            'short_name' => 'App',
            'description' => '',
            'icon_path' => '',
            'theme_color' => '#32e768',
            'background_color' => '#ffffff',
            'display_mode' => 'standalone',
            'start_url' => '/',
            'scope' => '/'
        ];
    }
    
    // IMPORTANTE: Deve usar HTTPS se a requisição está em HTTPS (evita Mixed Content)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                $_SERVER['SERVER_PORT'] == 443 ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    $protocol = $is_https ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    if (empty($base_path) || $base_path == '/') {
        $base_path = '';
    }
    $base_url = $protocol . $host . $base_path;
    
    // Garante que start_url e scope estão corretos para iOS
    $start_url = $config['start_url'];
    if (empty($start_url) || $start_url === '/') {
        $start_url = '/index.php';
    } elseif ($start_url[0] !== '/') {
        $start_url = '/' . ltrim($start_url, '/');
    }
    if ($start_url !== '/' && $start_url[strlen($start_url) - 1] === '/') {
        $start_url = rtrim($start_url, '/');
    }
    if ($start_url === '/') {
        $start_url = '/index.php';
    }
    
    $scope = $config['scope'];
    if (empty($scope) || $scope === '/') {
        $scope = '/';
    } elseif ($scope[0] !== '/') {
        $scope = '/' . ltrim($scope, '/');
    }
    if ($scope !== '/' && $scope[strlen($scope) - 1] === '/') {
        $scope = rtrim($scope, '/');
    }
    
    $display_mode = $config['display_mode'] ?? 'standalone';
    if (!in_array($display_mode, ['standalone', 'fullscreen', 'minimal-ui', 'browser'])) {
        $display_mode = 'standalone';
    }
    
    $manifest = [
        'name' => $config['app_name'],
        'short_name' => $config['short_name'],
        'description' => !empty($config['description']) ? $config['description'] : $config['app_name'],
        'start_url' => $start_url,
        'scope' => $scope,
        'display' => $display_mode,
        'theme_color' => $config['theme_color'],
        'background_color' => $config['background_color'],
        'orientation' => 'portrait-primary',
        'dir' => 'ltr',
        'lang' => 'pt-BR',
        'prefer_related_applications' => false,
        'icons' => []
    ];
    
    if (!empty($config['icon_path'])) {
        $manifest['screenshots'] = [];
    }
    
    if (!empty($config['icon_path'])) {
        $icon_path_clean = ltrim($config['icon_path'], '/');
        if (strpos($icon_path_clean, 'http://') === 0) {
            $icon_url = $is_https ? 'https://' . substr($icon_path_clean, 7) : $icon_path_clean;
        } elseif (strpos($icon_path_clean, 'https://') === 0) {
            $icon_url = $icon_path_clean;
        } else {
            $icon_url = $base_url . '/' . $icon_path_clean;
        }
        $icon_sizes = [
            ['sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']
        ];
        foreach ($icon_sizes as $icon_size) {
            $manifest['icons'][] = [
                'src' => $icon_url,
                'sizes' => $icon_size['sizes'],
                'type' => $icon_size['type'],
                'purpose' => $icon_size['purpose']
            ];
        }
    } else {
        if (function_exists('getSystemSetting')) {
            $favicon_url = getSystemSetting('favicon_url', '');
            if (!empty($favicon_url)) {
                $icon_url = strpos($favicon_url, 'http') === 0 ? $favicon_url : $base_path . '/' . ltrim($favicon_url, '/');
                $manifest['icons'][] = ['src' => $icon_url, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
                $manifest['icons'][] = ['src' => $icon_url, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'];
            }
        }
    }
    
    $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return json_encode([
            'name' => 'Plataforma',
            'short_name' => 'App',
            'display' => 'standalone',
            'start_url' => '/index.php',
            'scope' => '/'
        ], JSON_UNESCAPED_SLASHES);
    }
    return $json;
}

function pwa_should_show_install_prompt() {
    if (!pwa_module_installed()) return false;
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) return false;
    if (isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin') return false;
    return true;
}

function pwa_get_theme_color() {
    $config = pwa_get_config();
    if ($config && !empty($config['theme_color'])) return $config['theme_color'];
    if (function_exists('getSystemSetting')) return getSystemSetting('cor_primaria', '#32e768');
    return '#32e768';
}
