<?php
// PWA Manifest Generator - Retorna APENAS JSON válido
// IMPORTANTE: Este arquivo NÃO deve iniciar sessão ou gerar qualquer output antes do JSON

while (@ob_end_clean());
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('output_buffering', 0);
ini_set('session.auto_start', 0);

if (!file_exists(__DIR__ . '/pwa_config.php')) {
    http_response_code(404);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'PWA config not found']));
}

ob_start();
@require_once __DIR__ . '/pwa_config.php';
ob_clean();

if (!function_exists('pwa_module_installed') || !pwa_module_installed()) {
    http_response_code(404);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'PWA module not installed']));
}

if (!file_exists(__DIR__ . '/pwa_functions.php')) {
    http_response_code(404);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'PWA functions not found']));
}

ob_start();
@require_once __DIR__ . '/pwa_functions.php';
ob_get_clean();

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');

try {
    $config = function_exists('pwa_get_config_direct') ? pwa_get_config_direct() : (function_exists('pwa_get_config') ? pwa_get_config() : false);
    if (!$config) {
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
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443);
    $host_check = $_SERVER['HTTP_HOST'] ?? '';
    if (!$is_https && strpos($host_check, '.') !== false && strpos($host_check, 'localhost') === false) {
        $is_https = true;
    }
    $protocol = $is_https ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/pwa/manifest.php', PHP_URL_PATH);
    $base_path = ($request_path !== false && $request_path !== '') ? dirname($request_path) : '/pwa';
    $base_path = str_replace('\\', '/', $base_path);
    $base_path = preg_replace('#^/var/www/[^/]+#i', '', $base_path);
    $base_path = preg_replace('#^.*htdocs#i', '', $base_path);
    $base_path = preg_replace('#^[A-Z]:[/\\].*#i', '', $base_path);
    if (!empty($base_path) && $base_path[0] !== '/') $base_path = '/' . $base_path;
    $base_path = preg_replace('#/+#', '/', $base_path);
    $base_path = rtrim($base_path, '/');
    if (empty($base_path) || $base_path === '/' || strpos($base_path, '/var') === 0) $base_path = '/pwa';
    $base_url = $protocol . $host . $base_path;

    $start_url = $config['start_url'] ?? '/';
    if (empty($start_url) || $start_url === '/') $start_url = '/index.php';
    elseif ($start_url[0] !== '/') $start_url = '/' . ltrim($start_url, '/');
    if ($start_url !== '/' && $start_url[strlen($start_url) - 1] === '/') $start_url = rtrim($start_url, '/');

    $scope = $config['scope'] ?? '/';
    if (empty($scope) || $scope === '/') $scope = '/';
    elseif ($scope[0] !== '/') $scope = '/' . ltrim($scope, '/');
    if ($scope !== '/' && $scope[strlen($scope) - 1] === '/') $scope = rtrim($scope, '/');

    $manifest = [
        'name' => $config['app_name'] ?? 'Plataforma',
        'short_name' => $config['short_name'] ?? 'App',
        'description' => $config['description'] ?? '',
        'start_url' => $start_url,
        'scope' => $scope,
        'display' => $config['display_mode'] ?? 'standalone',
        'theme_color' => $config['theme_color'] ?? '#32e768',
        'background_color' => $config['background_color'] ?? '#ffffff',
        'orientation' => 'portrait-primary',
        'dir' => 'ltr',
        'lang' => 'pt-BR',
        'prefer_related_applications' => false,
        'icons' => []
    ];

    if (!empty($config['icon_path'])) {
        $icon_path_clean = ltrim($config['icon_path'], '/');
        if (strpos($icon_path_clean, 'http://') === 0) $icon_url = $is_https ? 'https://' . substr($icon_path_clean, 7) : $icon_path_clean;
        elseif (strpos($icon_path_clean, 'https://') === 0) $icon_url = $icon_path_clean;
        else $icon_url = $base_url . '/' . $icon_path_clean;
        $manifest['icons'][] = ['src' => $icon_url, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
        $manifest['icons'][] = ['src' => $icon_url, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'];
    }

    $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        $json = json_encode(['name' => 'Plataforma', 'short_name' => 'App', 'display' => 'standalone', 'start_url' => '/index.php', 'scope' => '/'], JSON_UNESCAPED_SLASHES);
    }
    $json = trim($json);
    if (substr($json, 0, 3) === "\xEF\xBB\xBF") $json = substr($json, 3);
    $start = strpos($json, '{');
    if ($start !== false && $start > 0) $json = substr($json, $start);
    while (@ob_end_clean());
    echo $json;
    exit;
} catch (Throwable $e) {
    while (@ob_end_clean());
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Error generating manifest']));
}
