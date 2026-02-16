<?php
/**
 * Member Protection Helper
 * Verifica se a proteção da área de membros está habilitada (global e por community)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/community_helper.php';

/**
 * Verifica se a proteção deve ser aplicada.
 * @param int|null $community_id ID da comunidade (usa getCommunityContext se null)
 * @return bool
 */
function isMemberProtectionEnabled($community_id = null) {
    global $pdo;
    
    if ($community_id === null && function_exists('getCommunityContext')) {
        $ctx = getCommunityContext();
        $community_id = $ctx['community_id'] ?? 1;
    }
    
    // Override por community
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'PROTECT_MEMBER_AREA_BY_COMMUNITY'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['valor'])) {
            $json = json_decode($row['valor'], true);
            if (is_array($json) && isset($json[(string)$community_id])) {
                return (bool) $json[(string)$community_id];
            }
        }
    } catch (PDOException $e) {}
    
    // Global
    $global = getSystemSetting('PROTECT_MEMBER_AREA', 'true');
    return in_array(strtolower($global), ['true', '1', 'on', 'yes'], true);
}

/**
 * Retorna URL protegida para arquivo de mídia.
 * @param string $path Caminho relativo (ex: uploads/xxx.png)
 * @param int $produto_id ID do produto para validar acesso (0 = qualquer membro logado)
 * @return string URL para /media
 */
function getProtectedMediaUrl($path, $produto_id = 0) {
    $p = is_string($path) ? trim($path) : '';
    if ($p === '') return '';
    return '/media?path=' . urlencode(ltrim($p, '/')) . '&produto_id=' . (int)$produto_id;
}

/**
 * Retorna URL protegida para arquivo de aula.
 */
function getProtectedAulaFileUrl($file_id, $produto_id) {
    return '/media?file_id=' . (int)$file_id . '&produto_id=' . (int)$produto_id;
}
