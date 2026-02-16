<?php
/**
 * Theme Helper - Configurações Visuais White-label
 * Carrega e gera CSS Variables a partir de theme_json em configuracoes_sistema.
 * O theme_json é armazenado na chave 'theme_json' da tabela configuracoes_sistema.
 * 
 * @see README_visual.md para documentação completa e como adicionar novos tokens.
 */

if (!function_exists('getSystemSetting')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Retorna o tema padrão (fallback quando theme_json não existe ou está vazio)
 */
function get_default_theme() {
    return [
        'primary' => '#32e768',
        'primaryHover' => '#28d15e',
        'bg' => '#07090d',
        'text' => 'rgba(255, 255, 255, 0.9)',
        'textMuted' => 'rgba(255, 255, 255, 0.5)',
        'card' => '#1a1f24',
        'cardElevated' => '#0f1419',
        'border' => 'rgba(255, 255, 255, 0.1)',
        'radius' => '0.5rem',
        'shadow' => '0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2)',
        'fontSans' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
        'logo_url' => '',
        'login_banner_url' => '',
    ];
}

/**
 * Obtém theme_json - prioridade: community > configuracoes_sistema > defaults
 * Communities (multi-tenant) podem ter theme_json e primary_color próprios.
 */
function get_theme_json() {
    $default = get_default_theme();
    // 1) Tema da comunidade (se multi-tenant ativo)
    if (function_exists('getCommunityContext')) {
        $ctx = getCommunityContext();
        if (!empty($ctx['theme_json'])) {
            $decoded = json_decode($ctx['theme_json'], true);
            if (is_array($decoded)) {
                return array_merge($default, $decoded);
            }
        }
        if (!empty($ctx['primary_color'])) {
            $default['primary'] = $ctx['primary_color'];
            $default['primaryHover'] = function_exists('adjustBrightness') ? adjustBrightness($ctx['primary_color'], -10) : '#28d15e';
        }
    }
    // 2) Tema global (configuracoes_sistema)
    $raw = getSystemSetting('theme_json', '');
    if (empty($raw)) {
        // Mescla com valores legados (cor_primaria, logo_url, login_image_url) para retrocompatibilidade
        $default['primary'] = getSystemSetting('cor_primaria', $default['primary']);
        $default['primaryHover'] = function_exists('adjustBrightness') ? adjustBrightness($default['primary'], -10) : '#28d15e';
        $default['logo_url'] = getSystemSetting('logo_url', $default['logo_url']);
        $default['login_banner_url'] = getSystemSetting('login_image_url', $default['login_banner_url']);
        return $default;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return get_default_theme();
    }
    return array_merge(get_default_theme(), $decoded);
}

/**
 * Persiste theme_json no banco.
 * @param array $theme Array associativo com as chaves do tema
 * @return bool
 */
function set_theme_json($theme) {
    $theme = array_merge(get_default_theme(), $theme);
    $json = json_encode($theme);
    return setSystemSetting('theme_json', $json);
}

/**
 * Gera o bloco <style id="theme-vars"> com :root { --var: value; ... }
 * Injetado no <head> de todas as páginas via load_settings.php
 */
function get_theme_css_vars() {
    $theme = get_theme_json();
    
    // Garante primaryHover se não definido
    if (empty($theme['primaryHover']) && !empty($theme['primary'])) {
        $theme['primaryHover'] = function_exists('adjustBrightness') ? adjustBrightness($theme['primary'], -10) : '#28d15e';
    }
    
    $vars = [
        '--brand-primary' => $theme['primary'] ?? '#32e768',
        '--brand-primary-hover' => $theme['primaryHover'] ?? '#28d15e',
        '--theme-bg' => $theme['bg'] ?? '#07090d',
        '--theme-text' => $theme['text'] ?? 'rgba(255, 255, 255, 0.9)',
        '--theme-text-muted' => $theme['textMuted'] ?? 'rgba(255, 255, 255, 0.5)',
        '--theme-card' => $theme['card'] ?? '#1a1f24',
        '--theme-card-elevated' => $theme['cardElevated'] ?? '#0f1419',
        '--theme-border' => $theme['border'] ?? 'rgba(255, 255, 255, 0.1)',
        '--theme-radius' => $theme['radius'] ?? '0.5rem',
        '--theme-shadow' => $theme['shadow'] ?? '0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2)',
        '--theme-font-sans' => $theme['fontSans'] ?? "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    ];
    
    // Retrocompatibilidade: accent-primary usado em várias páginas
    $vars['--accent-primary'] = $vars['--brand-primary'];
    $vars['--accent-primary-hover'] = $vars['--brand-primary-hover'];
    
    $lines = [];
    foreach ($vars as $name => $value) {
        $lines[] = sprintf('    %s: %s;', $name, $value);
    }
    
    return ":root {\n" . implode("\n", $lines) . "\n}";
}
