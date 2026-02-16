<?php
/**
 * Sanitiza HTML para exibição segura (descrição de aulas/módulos).
 * Permite: a, p, br, strong, em, b, i, ul, ol, li, h1-h4, blockquote, span, img.
 * span: permite style com color e background-color (destacadores do Quill).
 * Para <a>: permite href, target; força rel="noopener noreferrer nofollow" quando target="_blank".
 * Para <img>: permite apenas src com http, https ou data:image/ (colagem de imagens no editor).
 */
function sanitize_lesson_html($html) {
    if (!is_string($html) || trim($html) === '') return '';
    $allowed_tags = '<a><p><br><strong><em><b><i><ul><ol><li><h1><h2><h3><h4><blockquote><span><img>';
    $html = strip_tags($html, $allowed_tags);
    // Remove event handlers e javascript:
    $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/\s*on\w+\s*=\s*[^\s>]*/i', '', $html);
    // Para links: garantir rel="noopener noreferrer nofollow" quando target="_blank"
    $html = preg_replace_callback('/<a\s+([^>]*)>/i', function ($m) {
        $atts = $m[1];
        if (preg_match('/target\s*=\s*["\']?\s*_blank/i', $atts) && !preg_match('/rel\s*=/i', $atts)) {
            $atts .= ' rel="noopener noreferrer nofollow"';
        } elseif (preg_match('/target\s*=\s*["\']?\s*_blank/i', $atts)) {
            $atts = preg_replace('/rel\s*=\s*["\'][^"\']*["\']/i', 'rel="noopener noreferrer nofollow"', $atts);
        }
        $atts = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $atts);
        return '<a ' . trim($atts) . '>';
    }, $html);
    // Sanitizar style em span: só permite color e background-color (evita XSS)
    $html = preg_replace_callback('/<span\s+([^>]*)>/i', function ($m) {
        $atts = $m[1];
        if (!preg_match('/style\s*=\s*["\']([^"\']*)["\']/i', $atts, $s)) return '<span ' . $atts . '>';
        $style = $s[1];
        if (preg_match('/expression|javascript|url\s*\(\s*javascript/i', $style)) return '<span>';
        $out = [];
        if (preg_match('/color\s*:\s*([^;]+)/i', $style, $c)) $out[] = 'color:' . trim($c[1]);
        if (preg_match('/background-color\s*:\s*([^;]+)/i', $style, $b)) $out[] = 'background-color:' . trim($b[1]);
        $atts = preg_replace('/style\s*=\s*["\'][^"\']*["\']/i', '', $atts);
        $newStyle = empty($out) ? '' : ' style="' . implode('; ', $out) . '"';
        return '<span ' . trim($atts) . $newStyle . '>';
    }, $html);

    // Sanitizar <img>: só permite src com https?, data:image/ ou caminhos relativos /uploads/
    $html = preg_replace_callback('/<img\s+([^>]*)>/i', function ($m) {
        $atts = $m[1];
        if (!preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $atts, $srcMatch)) return '';
        $src = trim($srcMatch[1]);
        if (preg_match('/^\s*javascript\s*:/i', $src)) return '';
        if (!preg_match('#^(https?:/|data:image/|/uploads/)#i', $src)) return '';
        $safeSrc = $src;
        $alt = '';
        if (preg_match('/alt\s*=\s*["\']([^"\']*)["\']/i', $atts, $altMatch)) {
            $alt = ' alt="' . htmlspecialchars($altMatch[1], ENT_QUOTES, 'UTF-8') . '"';
        }
        return '<img src="' . htmlspecialchars($safeSrc, ENT_QUOTES, 'UTF-8') . '"' . $alt . '>';
    }, $html);

    return $html;
}
