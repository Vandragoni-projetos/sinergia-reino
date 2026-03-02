<?php
/**
 * Image Helper
 * Resolve URLs de imagens de produtos/cursos: suporta upload local e URL externa (WordPress, CDN, etc.)
 */

/**
 * Resolve a URL da imagem.
 * Se for URL completa (http/https), retorna como está.
 * Caso contrário, trata como caminho relativo em uploads/.
 *
 * @param string|null $path Caminho relativo (ex: nome.png) ou URL completa
 * @param string $upload_dir Diretório base (ex: uploads/)
 * @return string URL final para uso em src da img
 */
function resolve_product_image_url($path, $upload_dir = 'uploads/') {
    $p = is_string($path) ? trim($path) : '';
    if ($p === '') return '';

    if (filter_var($p, FILTER_VALIDATE_URL)) {
        return $p;
    }

    $upload_dir = rtrim($upload_dir, '/') . '/';
    $normalized = ltrim($p, '/');
    $upload_prefix = ltrim($upload_dir, '/');
    $relative = (stripos($normalized, $upload_prefix) === 0) ? $normalized : ($upload_prefix . $normalized);
    return '/' . ltrim($relative, '/');
}

/**
 * Retorna URL da imagem considerando proteção de mídia.
 * Para URLs externas, retorna direto. Para locais, usa getProtectedMediaUrl se disponível.
 *
 * @param string|null $path
 * @param string $upload_dir
 * @param int $produto_id Para validação de acesso em mídia protegida
 * @return string
 */
function resolve_product_image_url_protected($path, $upload_dir = 'uploads/', $produto_id = 0) {
    $p = is_string($path) ? trim($path) : '';
    if ($p === '') return '';

    if (filter_var($p, FILTER_VALIDATE_URL)) {
        return $p;
    }

    $normalized = ltrim($p, '/');
    $upload_prefix = ltrim($upload_dir, '/');
    $relative = (stripos($normalized, $upload_prefix) === 0) ? $normalized : ($upload_prefix . $normalized);
    if (function_exists('getProtectedMediaUrl')) {
        return getProtectedMediaUrl($relative, $produto_id);
    }
    return '/' . ltrim($relative, '/');
}
