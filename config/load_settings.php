<?php
/**
 * Carrega configurações do sistema e aplica dinamicamente
 * Este arquivo deve ser incluído no <head> de todas as páginas principais
 */

/**
 * Ajusta o brilho de uma cor hexadecimal
 * @param string $hex Cor em hexadecimal (#RRGGBB)
 * @param int $steps Passos de ajuste (negativo escurece, positivo clareia)
 * @return string Cor ajustada em hexadecimal
 */
if (!function_exists('adjustBrightness')) {
    function adjustBrightness($hex, $steps) {
        // Remove # se presente
        $hex = str_replace('#', '', $hex);
        
        // Converte para RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Ajusta o brilho
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));
        
        // Converte de volta para hex
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . 
                     str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . 
                     str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}

/**
 * Converte cor hexadecimal para RGB
 * @param string $hex Cor em hexadecimal (#RRGGBB)
 * @return array Array com r, g, b
 */
if (!function_exists('hexToRgb')) {
    function hexToRgb($hex) {
        $hex = str_replace('#', '', $hex);
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
}

// Garante que config.php já foi incluído
if (!function_exists('getSystemSetting')) {
    require_once __DIR__ . '/config.php';
}

// Carrega theme_helper para CSS Variables (White-label) - opcional (evita página em branco se não existir)
$theme_data = null;
if (file_exists(__DIR__ . '/theme_helper.php')) {
    require_once __DIR__ . '/theme_helper.php';
    $theme_data = get_theme_json();
}
$cor_primaria = $theme_data ? ($theme_data['primary'] ?? '#32e768') : getSystemSetting('cor_primaria', '#32e768');
$cor_primaria_hover = $theme_data ? ($theme_data['primaryHover'] ?? (function_exists('adjustBrightness') ? adjustBrightness($cor_primaria, -10) : '#28d15e')) : adjustBrightness($cor_primaria, -10);

// Busca configurações (logo, login_image etc. - theme pode sobrescrever se configurado)
if ($theme_data && !empty($theme_data['logo_url'])) {
    $logo_url_raw = $theme_data['logo_url'];
} else {
    $logo_url_raw = getSystemSetting('logo_url', 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png');
}
$login_image_url_raw = ($theme_data && !empty($theme_data['login_banner_url'])) ? $theme_data['login_banner_url'] : getSystemSetting('login_image_url', '');
$nome_plataforma = getSystemSetting('nome_plataforma', 'Hub SinergIA');
$logo_checkout_url_raw = getSystemSetting('logo_checkout_url', '');
$favicon_url_raw = getSystemSetting('favicon_url', '');
$notification_image_url_raw = getSystemSetting('notification_image_url', '');

// Normaliza URLs: igual às imagens dos módulos
// Remove barra inicial se houver (valores antigos podem ter)
$logo_url = ltrim($logo_url_raw, '/');
if (empty($logo_url)) {
    $logo_url = 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png';
} elseif (strpos($logo_url, 'http') === 0) {
    // URL completa, mantém como está
} elseif (strpos($logo_url, 'uploads/') === 0) {
    // Adiciona barra inicial (igual às imagens dos módulos)
    $logo_url = '/' . $logo_url;
} else {
    // Outros casos, adiciona barra se necessário
    $logo_url = '/' . $logo_url;
}

$login_image_url = ltrim($login_image_url_raw, '/');
if (!empty($login_image_url) && strpos($login_image_url, 'http') !== 0) {
    if (strpos($login_image_url, 'uploads/') === 0) {
        // Adiciona barra inicial (igual às imagens dos módulos)
        $login_image_url = '/' . $login_image_url;
    } elseif (!empty($login_image_url)) {
        $login_image_url = '/' . $login_image_url;
    }
}

// Logo do checkout: se não configurada, usa a logo padrão
$logo_checkout_url = ltrim($logo_checkout_url_raw, '/');
if (empty($logo_checkout_url)) {
    $logo_checkout_url = $logo_url;
} elseif (strpos($logo_checkout_url, 'http') === 0) {
    // URL completa, mantém como está
} elseif (strpos($logo_checkout_url, 'uploads/') === 0) {
    // Adiciona barra inicial (igual às imagens dos módulos)
    $logo_checkout_url = '/' . $logo_checkout_url;
} else {
    $logo_checkout_url = '/' . $logo_checkout_url;
}

// Normaliza URL do favicon
$favicon_url = ltrim($favicon_url_raw, '/');
if (!empty($favicon_url) && strpos($favicon_url, 'http') !== 0) {
    if (strpos($favicon_url, 'uploads/') === 0) {
        // Adiciona barra inicial (igual às imagens dos módulos)
        $favicon_url = '/' . $favicon_url;
    } else {
        $favicon_url = '/' . $favicon_url;
    }
}

// Normaliza URL da imagem de notificações (se não configurada, usa a logo)
$notification_image_url = ltrim($notification_image_url_raw, '/');
if (empty($notification_image_url)) {
    $notification_image_url = $logo_url;
} elseif (strpos($notification_image_url, 'http') === 0) {
    // URL completa, mantém como está
} elseif (strpos($notification_image_url, 'uploads/') === 0) {
    $notification_image_url = '/' . $notification_image_url;
} else {
    $notification_image_url = '/' . $notification_image_url;
}

// Gera bloco de CSS Variables (theme-vars) - White-label ou fallback básico
?>
<style id="theme-vars">
<?php
if (function_exists('get_theme_css_vars')) {
    echo get_theme_css_vars();
} else {
    echo ":root {\n    --accent-primary: " . htmlspecialchars($cor_primaria) . ";\n    --accent-primary-hover: " . htmlspecialchars($cor_primaria_hover) . ";\n}\n";
}
?>

/* Classe utilitária para cor primária */
.bg-primary {
    background-color: var(--accent-primary) !important;
}

.bg-primary-hover:hover {
    background-color: var(--accent-primary-hover) !important;
}

.text-primary {
    color: var(--accent-primary) !important;
}

.border-primary {
    border-color: var(--accent-primary) !important;
}

.ring-primary:focus {
    ring-color: var(--accent-primary) !important;
}

/* Background dinâmico para sidebar-item-active */
.sidebar-item-active {
    background: <?php 
        $rgb = hexToRgb($cor_primaria);
        echo "rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, 0.1)";
    ?> !important;
}

.sidebar-item-active i,
.sidebar-item-active span {
    filter: drop-shadow(0 0 4px <?php 
        $rgb = hexToRgb($cor_primaria);
        echo "rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, 0.4)";
    ?>) !important;
}

/* Override cores hardcoded do Tailwind (bg-[#32e768], text-[#32e768], etc.) */
.bg-\[\#32e768\],
[style*="background-color: #32e768"],
[style*="background:#32e768"],
[style*="background: #32e768"] {
    background-color: var(--accent-primary) !important;
}

.text-\[\#32e768\],
[style*="color: #32e768"],
[style*="color:#32e768"] {
    color: var(--accent-primary) !important;
}

.hover\:bg-\[\#28d15e\]:hover,
[style*="background-color: #28d15e"],
[style*="background:#28d15e"],
[style*="background: #28d15e"] {
    background-color: var(--accent-primary-hover) !important;
}

/* Botões e links com cor verde hardcoded */
button[style*="#32e768"],
a[style*="#32e768"],
.btn-primary,
[class*="bg-green-"] {
    background-color: var(--accent-primary) !important;
}

button[style*="#32e768"]:hover,
a[style*="#32e768"]:hover {
    background-color: var(--accent-primary-hover) !important;
}

/* Override bordas e textos Tailwind com cor hardcoded */
.border-\[\#32e768\] {
    border-color: var(--accent-primary) !important;
}

.text-\[\#32e768\] {
    color: var(--accent-primary) !important;
}

.focus\:ring-\[\#32e768\]:focus {
    --tw-ring-color: var(--accent-primary) !important;
}

/* Ícones Lucide com cor verde */
[data-lucide][class*="text-[#32e768]"] {
    color: var(--accent-primary) !important;
}
</style>
<?php
// Gera tag <link rel="icon"> se favicon estiver configurado
if (!empty($favicon_url)) {
    // Determina o tipo MIME baseado na extensão
    $favicon_ext = strtolower(pathinfo($favicon_url, PATHINFO_EXTENSION));
    $favicon_type = 'image/x-icon'; // padrão
    if ($favicon_ext === 'png') {
        $favicon_type = 'image/png';
    } elseif ($favicon_ext === 'svg') {
        $favicon_type = 'image/svg+xml';
    }
    echo '<link rel="icon" type="' . htmlspecialchars($favicon_type) . '" href="' . htmlspecialchars($favicon_url) . '">' . "\n";
}
?>

