<?php
/**
 * Community Helper - Multi-tenant por subdomínio
 * Resolve community_id a partir de HTTP_HOST.
 * 
 * Fluxo: HTTP_HOST -> subdomínio (primeiro label) -> communities.slug -> community_id
 * Fallback: hosts desconhecidos (ex: gatewaypro1.vitrineacademy.com.br) usam slug 'club'.
 */

if (!isset($pdo)) {
    if (!function_exists('getCommunityContext')) {
        // Carregar config se necessário (para $pdo)
        if (!defined('DB_HOST')) {
            require_once __DIR__ . '/../config/config.php';
        }
    }
}

/** Cache estático para evitar múltiplas queries por request */
$_community_context_cache = null;

/**
 * Extrai o subdomínio (primeiro label) do host.
 * Ex: mktd.sinergia.club -> mktd | flow.sinergia.club -> flow
 *     gatewaypro1.vitrineacademy.com.br -> gatewaypro1 (não mapeado, usa default)
 */
function _extract_subdomain($host) {
    $host = strtolower(trim($host ?? ''));
    if (empty($host)) return '';
    $parts = explode('.', $host);
    return $parts[0] ?? '';
}

/**
 * Obtém o contexto da comunidade para o request atual.
 * 
 * @return array {
 *   community_id: int,
 *   slug: string,
 *   name: string,
 *   theme_json: string|null,
 *   primary_color: string,
 *   is_default: bool  // true se caiu no fallback (club)
 * }
 */
function getCommunityContext() {
    global $pdo, $_community_context_cache;
    
    if ($_community_context_cache !== null) {
        return $_community_context_cache;
    }
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $subdomain = _extract_subdomain($host);
    
    $default_slug = 'club';
    $context = [
        'community_id' => 1,
        'slug' => $default_slug,
        'name' => 'SinergIA Club',
        'theme_json' => null,
        'primary_color' => '#32e768',
        'is_default' => true,
    ];
    
    if (empty($pdo)) {
        $_community_context_cache = $context;
        return $context;
    }
    
    try {
        // Verificar se tabela communities existe
        $chk = $pdo->query("SHOW TABLES LIKE 'communities'");
        if (!$chk || $chk->rowCount() === 0) {
            $_community_context_cache = $context;
            return $context;
        }
        
        // Buscar comunidade por slug
        if (!empty($subdomain)) {
            $stmt = $pdo->prepare("SELECT id, slug, name, theme_json, primary_color FROM communities WHERE slug = ? LIMIT 1");
            $stmt->execute([$subdomain]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $context = [
                    'community_id' => (int) $row['id'],
                    'slug' => $row['slug'],
                    'name' => $row['name'] ?? $subdomain,
                    'theme_json' => $row['theme_json'],
                    'primary_color' => $row['primary_color'] ?? '#32e768',
                    'is_default' => false,
                ];
            }
        }
        
        // Se não encontrou por subdomain, usar default (club)
        if ($context['is_default']) {
            $stmt = $pdo->prepare("SELECT id, slug, name, theme_json, primary_color FROM communities WHERE slug = ? LIMIT 1");
            $stmt->execute([$default_slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $context['community_id'] = (int) $row['id'];
                $context['slug'] = $row['slug'];
                $context['name'] = $row['name'] ?? $default_slug;
                $context['theme_json'] = $row['theme_json'];
                $context['primary_color'] = $row['primary_color'] ?? '#32e768';
            }
        }
    } catch (PDOException $e) {
        error_log("community_helper getCommunityContext: " . $e->getMessage());
    }
    
    $_community_context_cache = $context;
    return $context;
}

/**
 * Retorna apenas o community_id (atalho).
 */
function getCommunityId() {
    return getCommunityContext()['community_id'];
}

/**
 * Verifica se a tabela tem coluna community_id (cache por request).
 * Usado para compatibilidade durante migração gradual.
 */
function _table_has_community_column($table) {
    static $cache = [];
    if (!isset($cache[$table])) {
        global $pdo;
        $cache[$table] = false;
        if (!empty($pdo)) {
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'community_id'");
                $cache[$table] = $chk && $chk->rowCount() > 0;
            } catch (PDOException $e) {}
        }
    }
    return $cache[$table];
}

/**
 * Retorna sufixo SQL e parâmetro para filtro community_id.
 * Ex: [' AND community_id = ?', $cid] ou ['', null] se coluna não existir.
 */
function getCommunityFilter($table) {
    if (!_table_has_community_column($table)) {
        return ['', null];
    }
    return [' AND community_id = ?', getCommunityId()];
}
