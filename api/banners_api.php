<?php
/**
 * API para gerenciamento de Banners Publicitários
 * Endpoints: CRUD de banners + reordenação drag-drop
 */

require_once __DIR__ . '/../config/config.php';

// Proteção: apenas usuários logados (compatível com admin e infoprodutor)
$logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$usuario_id = null;
if (!empty($_SESSION['usuario_id'])) {
    $usuario_id = (int) $_SESSION['usuario_id'];
} elseif (!empty($_SESSION['id'])) {
    $usuario_id = (int) $_SESSION['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $usuario_id = (int) $_SESSION['user_id'];
}
$community_id = $_SESSION['community_id'] ?? null; // Multi-tenant (opcional)

if (!$logged_in || !$usuario_id) {
    http_response_code(401);
    $msg = !$logged_in ? 'Sessão inválida ou expirada. Faça login novamente.' : 'Usuário não identificado.';
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            // Listar banners do infoprodutor
            list_banners($pdo, $usuario_id, $community_id);
            break;
            
        case 'create':
            // Criar novo banner
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            create_banner($pdo, $usuario_id, $community_id);
            break;
            
        case 'update':
            // Atualizar banner existente
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            update_banner($pdo, $usuario_id, $community_id);
            break;
            
        case 'delete':
            // Excluir banner
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            delete_banner($pdo, $usuario_id, $community_id);
            break;
            
        case 'reorder_feed':
            // Reordenar itens do feed (produtos + banners)
            if ($method !== 'POST') {
                throw new Exception('Método não permitido');
            }
            reorder_feed($pdo, $usuario_id, $community_id);
            break;
            
        case 'get_feed':
            // Obter feed ordenado (produtos + banners)
            get_feed($pdo, $usuario_id, $community_id);
            break;
            
        case 'get_badges':
            // Listar badges ativos para dropdown
            get_badges($pdo);
            break;
            
        case 'get_products':
            // Listar produtos do infoprodutor para vincular ao banner
            get_products_for_banner($pdo, $usuario_id);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Ação não encontrada']);
            exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Listar produtos do infoprodutor para dropdown de vínculo com banner
 */
function get_products_for_banner($pdo, $usuario_id) {
    $cf_where = '';
    $cf_param = null;
    if (function_exists('getCommunityFilter')) {
        list($cf_where, $cf_param) = getCommunityFilter('produtos');
    }
    $sql = "SELECT id, nome FROM produtos WHERE usuario_id = ?" . $cf_where . " ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $params = [$usuario_id];
    if ($cf_param !== null) $params[] = $cf_param;
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'products' => $products]);
}

/**
 * Listar badges ativos para dropdown
 */
function get_badges($pdo) {
    $chk = $pdo->query("SHOW TABLES LIKE 'banner_badges'");
    if (!$chk || $chk->rowCount() === 0) {
        echo json_encode(['success' => true, 'badges' => []]);
        return;
    }
    $stmt = $pdo->query("SELECT id, slug, icon, label FROM banner_badges WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $badges = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    echo json_encode(['success' => true, 'badges' => $badges]);
}

/**
 * Listar todos os banners do infoprodutor (com badge)
 */
function list_banners($pdo, $usuario_id, $community_id) {
    $chk_badges = $pdo->query("SHOW TABLES LIKE 'banner_badges'");
    $has_badges = $chk_badges && $chk_badges->rowCount() > 0;
    
    $sql = "SELECT b.*";
    if ($has_badges) {
        $sql .= ", bb.icon AS badge_icon, bb.label AS badge_label";
    }
    $sql .= " FROM banners b";
    if ($has_badges) {
        $sql .= " LEFT JOIN banner_badges bb ON bb.id = b.badge_id AND bb.is_active = 1";
    }
    $sql .= " WHERE b.usuario_id = ?";
    $params = [$usuario_id];
    
    if ($community_id !== null) {
        $sql .= " AND (community_id IS NULL OR community_id = ?)";
        $params[] = $community_id;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'banners' => $banners]);
}

/**
 * Criar novo banner
 */
function create_banner($pdo, $usuario_id, $community_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validações básicas
    if (empty($data['image_path']) && empty($data['image_url'])) {
        throw new Exception('É necessário fornecer uma imagem (upload ou URL)');
    }
    
    // Sanitizar dados
    $titulo = !empty($data['titulo']) ? trim($data['titulo']) : null;
    $image_path = !empty($data['image_path']) ? trim($data['image_path']) : null;
    $image_url = !empty($data['image_url']) ? filter_var(trim($data['image_url']), FILTER_VALIDATE_URL) : null;
    $click_url = !empty($data['click_url']) ? filter_var(trim($data['click_url']), FILTER_VALIDATE_URL) : null;
    $open_new_tab = !empty($data['open_new_tab']) ? 1 : 0;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $show_in_products_grid = isset($data['show_in_products_grid']) ? (int)$data['show_in_products_grid'] : 1;
    $show_in_member_dashboard = isset($data['show_in_member_dashboard']) ? (int)$data['show_in_member_dashboard'] : 0;
    $show_in_offers_section = isset($data['show_in_offers_section']) ? (int)$data['show_in_offers_section'] : 0;
    
    $badge_id = null;
    if (!empty($data['badge_id']) && is_numeric($data['badge_id'])) {
        $bid = (int)$data['badge_id'];
        $chk = $pdo->prepare("SELECT id FROM banner_badges WHERE id = ? AND is_active = 1");
        $chk->execute([$bid]);
        if ($chk->fetch()) {
            $badge_id = $bid;
        }
    }
    
    $product_id = null;
    if (!empty($data['product_id']) && is_numeric($data['product_id'])) {
        $pid = (int)$data['product_id'];
        $chk_p = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
        $chk_p->execute([$pid, $usuario_id]);
        if ($chk_p->fetch()) {
            $product_id = $pid;
        }
    }
    
    $chk_col = $pdo->query("SHOW COLUMNS FROM banners LIKE 'badge_id'");
    $has_badge_col = $chk_col && $chk_col->rowCount() > 0;
    $chk_product_col = $pdo->query("SHOW COLUMNS FROM banners LIKE 'product_id'");
    $has_product_col = $chk_product_col && $chk_product_col->rowCount() > 0;
    
    $cols = "community_id, usuario_id, titulo";
    $placeholders = "?, ?, ?";
    $vals = [$community_id, $usuario_id, $titulo];
    if ($has_badge_col) {
        $cols .= ", badge_id";
        $placeholders .= ", ?";
        $vals[] = $badge_id;
    }
    $cols .= ", image_path, image_url, click_url, open_new_tab, is_active, show_in_products_grid, show_in_member_dashboard, show_in_offers_section";
    $placeholders .= ", ?, ?, ?, ?, ?, ?, ?, ?";
    $vals = array_merge($vals, [$image_path, $image_url, $click_url, $open_new_tab, $is_active, $show_in_products_grid, $show_in_member_dashboard, $show_in_offers_section]);
    if ($has_product_col) {
        $cols .= ", product_id";
        $placeholders .= ", ?";
        $vals[] = $product_id;
    }
    
    $sql = "INSERT INTO banners ($cols) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
    
    $banner_id = $pdo->lastInsertId();
    
    // Adicionar ao feed com sort_order no final
    $max_order_sql = "SELECT COALESCE(MAX(sort_order), 0) as max_order FROM products_feed_items WHERE usuario_id = ?";
    $max_order_params = [$usuario_id];
    
    if ($community_id !== null) {
        $max_order_sql .= " AND (community_id IS NULL OR community_id = ?)";
        $max_order_params[] = $community_id;
    }
    
    $stmt_max = $pdo->prepare($max_order_sql);
    $stmt_max->execute($max_order_params);
    $max_order = $stmt_max->fetchColumn();
    
    $feed_sql = "INSERT INTO products_feed_items (community_id, usuario_id, item_type, item_id, sort_order) 
                 VALUES (?, ?, 'banner', ?, ?)";
    $stmt_feed = $pdo->prepare($feed_sql);
    $stmt_feed->execute([$community_id, $usuario_id, $banner_id, $max_order + 1]);
    
    echo json_encode(['success' => true, 'banner_id' => $banner_id, 'message' => 'Banner criado com sucesso']);
}

/**
 * Atualizar banner existente
 */
function update_banner($pdo, $usuario_id, $community_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $banner_id = $data['id'] ?? null;
    if (!$banner_id) {
        throw new Exception('ID do banner não fornecido');
    }
    
    // Verificar se o banner pertence ao usuário (e carregar imagem atual)
    $check_sql = "SELECT id, image_path, image_url FROM banners WHERE id = ? AND usuario_id = ?";
    $check_params = [$banner_id, $usuario_id];
    
    if ($community_id !== null) {
        $check_sql .= " AND (community_id IS NULL OR community_id = ?)";
        $check_params[] = $community_id;
    }
    
    $stmt_check = $pdo->prepare($check_sql);
    $stmt_check->execute($check_params);
    $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        throw new Exception('Banner não encontrado ou não pertence ao usuário');
    }
    
    // Sanitizar dados
    $titulo = isset($data['titulo']) ? trim($data['titulo']) : null;
    $image_path = isset($data['image_path']) ? trim($data['image_path']) : null;
    $image_url = isset($data['image_url']) ? filter_var(trim($data['image_url']), FILTER_VALIDATE_URL) : null;
    // Sem nova imagem no payload: preservar a já salva (ex.: só alterar "Onde exibir")
    if (empty($image_path) && empty($image_url)) {
        $image_path = !empty($existing['image_path']) ? $existing['image_path'] : null;
        $image_url = !empty($existing['image_url']) ? $existing['image_url'] : null;
    }
    if (empty($image_path) && empty($image_url)) {
        throw new Exception('É necessário fornecer uma imagem (upload ou URL)');
    }
    $click_url = isset($data['click_url']) ? filter_var(trim($data['click_url']), FILTER_VALIDATE_URL) : null;
    $open_new_tab = isset($data['open_new_tab']) ? (int)$data['open_new_tab'] : 0;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $show_in_products_grid = isset($data['show_in_products_grid']) ? (int)$data['show_in_products_grid'] : 1;
    $show_in_member_dashboard = isset($data['show_in_member_dashboard']) ? (int)$data['show_in_member_dashboard'] : 0;
    $show_in_offers_section = isset($data['show_in_offers_section']) ? (int)$data['show_in_offers_section'] : 0;
    
    $badge_id = null;
    if (isset($data['badge_id'])) {
        if (!empty($data['badge_id']) && is_numeric($data['badge_id'])) {
            $bid = (int)$data['badge_id'];
            $chk = $pdo->prepare("SELECT id FROM banner_badges WHERE id = ? AND is_active = 1");
            $chk->execute([$bid]);
            if ($chk->fetch()) {
                $badge_id = $bid;
            }
        }
    }
    
    $product_id = null;
    if (isset($data['product_id'])) {
        if (!empty($data['product_id']) && is_numeric($data['product_id'])) {
            $pid = (int)$data['product_id'];
            $chk_p = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
            $chk_p->execute([$pid, $usuario_id]);
            if ($chk_p->fetch()) {
                $product_id = $pid;
            }
        }
    }
    
    $chk_col = $pdo->query("SHOW COLUMNS FROM banners LIKE 'badge_id'");
    $has_badge_col = $chk_col && $chk_col->rowCount() > 0;
    $chk_product_col = $pdo->query("SHOW COLUMNS FROM banners LIKE 'product_id'");
    $has_product_col = $chk_product_col && $chk_product_col->rowCount() > 0;
    
    $sql = "UPDATE banners SET titulo = ?, image_path = ?, image_url = ?, click_url = ?, open_new_tab = ?, is_active = ?, show_in_products_grid = ?, show_in_member_dashboard = ?, show_in_offers_section = ?";
    $params = [$titulo, $image_path, $image_url, $click_url, $open_new_tab, $is_active, $show_in_products_grid, $show_in_member_dashboard, $show_in_offers_section];
    if ($has_badge_col) {
        $sql .= ", badge_id = ?";
        $params[] = $badge_id;
    }
    if ($has_product_col) {
        $sql .= ", product_id = ?";
        $params[] = $product_id;
    }
    $sql .= " WHERE id = ? AND usuario_id = ?";
    $params[] = $banner_id;
    $params[] = $usuario_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Banner atualizado com sucesso']);
}

/**
 * Excluir banner
 */
function delete_banner($pdo, $usuario_id, $community_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $banner_id = $data['id'] ?? null;
    if (!$banner_id) {
        throw new Exception('ID do banner não fornecido');
    }
    
    // Verificar se o banner pertence ao usuário
    $check_sql = "SELECT id, image_path FROM banners WHERE id = ? AND usuario_id = ?";
    $check_params = [$banner_id, $usuario_id];
    
    if ($community_id !== null) {
        $check_sql .= " AND (community_id IS NULL OR community_id = ?)";
        $check_params[] = $community_id;
    }
    
    $stmt_check = $pdo->prepare($check_sql);
    $stmt_check->execute($check_params);
    $banner = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$banner) {
        throw new Exception('Banner não encontrado ou não pertence ao usuário');
    }
    
    // Excluir do feed
    $feed_sql = "DELETE FROM products_feed_items WHERE item_type = 'banner' AND item_id = ? AND usuario_id = ?";
    $stmt_feed = $pdo->prepare($feed_sql);
    $stmt_feed->execute([$banner_id, $usuario_id]);
    
    // Excluir banner
    $delete_sql = "DELETE FROM banners WHERE id = ? AND usuario_id = ?";
    $stmt_delete = $pdo->prepare($delete_sql);
    $stmt_delete->execute([$banner_id, $usuario_id]);
    
    // Excluir arquivo físico se foi upload local
    if (!empty($banner['image_path']) && file_exists(__DIR__ . '/../' . $banner['image_path'])) {
        @unlink(__DIR__ . '/../' . $banner['image_path']);
    }
    
    echo json_encode(['success' => true, 'message' => 'Banner excluído com sucesso']);
}

/**
 * Reordenar feed (drag & drop e "mover para posição").
 *
 * Identidade canônica do item no feed unificado:
 *   (usuario_id, item_type, item_id)
 * A listagem (Meus Produtos, área de membros, ofertas) não filtra por community_id
 * e já faz dedupe por (item_type, item_id). community_id NULL = linha global do usuário.
 * Isolamento: só lê/grava linhas de $usuario_id; nunca altera outro usuário.
 *
 * NÃO usar rowCount() após UPDATE para decidir INSERT: se sort_order já é o mesmo,
 * MySQL reporta 0 linhas afetadas mesmo com o registro existente, o que gerava duplicatas
 * (UNIQUE com community_id NULL não impede múltiplos NULL no MySQL/MariaDB).
 */
function reorder_feed($pdo, $usuario_id, $community_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $items = $data['items'] ?? [];
    if (empty($items) || !is_array($items)) {
        throw new Exception('Lista de itens inválida');
    }
    
    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['item_type']) || !isset($item['item_id']) || !isset($item['sort_order'])) {
            throw new Exception('Estrutura de item inválida');
        }
        $item_type = (string) $item['item_type'];
        if ($item_type !== 'product' && $item_type !== 'banner') {
            throw new Exception('Tipo de item inválido');
        }
        $item_id = (int) $item['item_id'];
        if ($item_id <= 0) {
            throw new Exception('ID de item inválido');
        }
        $sort_order = (int) $item['sort_order'];
        if ($sort_order < 0) {
            throw new Exception('Ordem inválida');
        }
        $key = $item_type . ':' . $item_id;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $normalized[] = [
            'item_type' => $item_type,
            'item_id' => $item_id,
            'sort_order' => $sort_order,
        ];
    }
    if (empty($normalized)) {
        throw new Exception('Lista de itens inválida');
    }
    
    $pdo->beginTransaction();
    
    try {
        $stmt_exist = $pdo->prepare(
            "SELECT item_type, item_id
             FROM products_feed_items
             WHERE usuario_id = ?
             GROUP BY item_type, item_id"
        );
        $stmt_exist->execute([$usuario_id]);
        $existing = [];
        foreach ($stmt_exist->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['item_type'] . ':' . (int) $row['item_id']] = true;
        }
        
        $stmt_upd = $pdo->prepare(
            "UPDATE products_feed_items
             SET sort_order = ?
             WHERE usuario_id = ?
             AND item_type = ?
             AND item_id = ?"
        );
        $stmt_ins = $pdo->prepare(
            "INSERT INTO products_feed_items (community_id, usuario_id, item_type, item_id, sort_order)
             VALUES (?, ?, ?, ?, ?)"
        );
        $insert_community_id = ($community_id !== null && $community_id !== '') ? (int) $community_id : null;
        
        foreach ($normalized as $item) {
            $key = $item['item_type'] . ':' . $item['item_id'];
            if (isset($existing[$key])) {
                $stmt_upd->execute([$item['sort_order'], $usuario_id, $item['item_type'], $item['item_id']]);
            } else {
                $stmt_ins->execute([
                    $insert_community_id,
                    $usuario_id,
                    $item['item_type'],
                    $item['item_id'],
                    $item['sort_order'],
                ]);
                $existing[$key] = true;
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ordem atualizada com sucesso']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Obter feed ordenado (produtos + banners)
 */
function get_feed($pdo, $usuario_id, $community_id) {
    // Buscar itens do feed ordenados
    $sql = "SELECT pfi.*, pfi.item_type, pfi.item_id, pfi.sort_order
            FROM products_feed_items pfi
            WHERE pfi.usuario_id = ?";
    
    $params = [$usuario_id];
    
    if ($community_id !== null) {
        $sql .= " AND (pfi.community_id IS NULL OR pfi.community_id = ?)";
        $params[] = $community_id;
    }
    
    $sql .= " ORDER BY pfi.sort_order ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $feed_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada item, buscar os dados completos (produto ou banner)
    $result = [];
    
    foreach ($feed_items as $item) {
        $item_data = [
            'feed_id' => $item['id'],
            'item_type' => $item['item_type'],
            'item_id' => $item['item_id'],
            'sort_order' => $item['sort_order'],
            'data' => null
        ];
        
        if ($item['item_type'] === 'product') {
            // Buscar dados do produto
            $prod_sql = "SELECT * FROM produtos WHERE id = ?";
            $prod_stmt = $pdo->prepare($prod_sql);
            $prod_stmt->execute([$item['item_id']]);
            $item_data['data'] = $prod_stmt->fetch(PDO::FETCH_ASSOC);
            
        } elseif ($item['item_type'] === 'banner') {
            $chk_bb = $pdo->query("SHOW TABLES LIKE 'banner_badges'");
            $has_bb = $chk_bb && $chk_bb->rowCount() > 0;
            $banner_sql = $has_bb
                ? "SELECT b.*, bb.icon AS badge_icon, bb.label AS badge_label FROM banners b LEFT JOIN banner_badges bb ON bb.id = b.badge_id AND bb.is_active = 1 WHERE b.id = ?"
                : "SELECT * FROM banners WHERE id = ?";
            $banner_stmt = $pdo->prepare($banner_sql);
            $banner_stmt->execute([$item['item_id']]);
            $item_data['data'] = $banner_stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Só adicionar se o item ainda existe
        if ($item_data['data']) {
            $result[] = $item_data;
        }
    }
    
    echo json_encode(['success' => true, 'feed' => $result]);
}
