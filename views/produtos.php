<?php
// Lógica para processar o formulário de adição/edição de produto
$mensagem = '';
$produto_edit = null;
$upload_dir = 'uploads/'; // Pasta para salvar as imagens e PDFs

// Garante que o diretório de uploads exista
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Obter o ID do usuário logado
$usuario_id = $_SESSION['id'] ?? 0;
$community_id = function_exists('getCommunityId') ? getCommunityId() : 1;
if ($usuario_id === 0) {
    header("location: /login");
    exit;
}

// Deletar produto
if (isset($_POST['deletar_produto'])) {
    try {
        list($cf_where, $cf_param) = function_exists('getCommunityFilter') ? getCommunityFilter('produtos') : ['', null];
        $del_img_cols = 'foto';
        if (function_exists('db_table_has_column') && db_table_has_column($pdo, 'produtos', 'foto_2')) {
            $del_img_cols = 'foto, foto_2, foto_3';
        }
        $stmt_find = $pdo->prepare("SELECT {$del_img_cols}, tipo_entrega, conteudo_entrega FROM produtos WHERE id = ? AND usuario_id = ?" . $cf_where);
        $params = [$_POST['id_produto'], $usuario_id];
        if ($cf_param !== null) $params[] = $cf_param;
        $stmt_find->execute($params);
        $produto_files = $stmt_find->fetch(PDO::FETCH_ASSOC);

        if ($produto_files) {
            foreach (['foto', 'foto_2', 'foto_3'] as $_del_img) {
                if (!array_key_exists($_del_img, $produto_files)) {
                    continue;
                }
                $fn = $produto_files[$_del_img] ?? '';
                if ($fn && !filter_var($fn, FILTER_VALIDATE_URL) && file_exists($upload_dir . $fn)) {
                    @unlink($upload_dir . $fn);
                }
            }
            if ($produto_files['tipo_entrega'] === 'email_pdf' && $produto_files['conteudo_entrega'] && file_exists($upload_dir . $produto_files['conteudo_entrega'])) {
                unlink($upload_dir . $produto_files['conteudo_entrega']);
            }

            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ? AND usuario_id = ?" . $cf_where);
            $del_params = [$_POST['id_produto'], $usuario_id];
            if ($cf_param !== null) $del_params[] = $cf_param;
            $stmt->execute($del_params);
            // Remover do feed para não deixar linha órfã (evita listagem vazia depois)
            try {
                $pdo->prepare("DELETE FROM products_feed_items WHERE item_type = 'product' AND item_id = ? AND usuario_id = ?")->execute([$_POST['id_produto'], $usuario_id]);
            } catch (PDOException $e) { /* ignora */ }
            $mensagem = "<div class='animate-fade-in-down bg-green-900/20 border-l-4 border-green-500 text-green-300 p-4 rounded-md shadow-sm mb-6' role='alert'><div class='flex'><div class='py-1'><i data-lucide='check-circle' class='w-6 h-6 mr-3 text-green-400'></i></div><div><p class='font-bold text-white'>Sucesso</p><p class='text-sm text-green-200'>Produto deletado com sucesso!</p></div></div></div>";
        } else {
            $mensagem = "<div class='animate-fade-in-down bg-red-900/20 border-l-4 border-red-500 text-red-300 p-4 rounded-md shadow-sm mb-6' role='alert'><div class='flex'><div class='py-1'><i data-lucide='alert-circle' class='w-6 h-6 mr-3 text-red-400'></i></div><div><p class='font-bold text-white'>Erro</p><p class='text-sm text-red-200'>Produto não encontrado ou permissão negada.</p></div></div></div>";
        }
    } catch (PDOException $e) {
        $mensagem = "<div class='bg-red-900/20 border-l-4 border-red-500 text-red-300 p-4 rounded-md shadow-sm mb-6' role='alert'>Erro ao deletar: " . $e->getMessage() . "</div>";
    }
}

// Salvar (Adicionar ou Editar) produto
if (isset($_POST['salvar_produto'])) {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $preco = $_POST['preco'];
    $id_produto = $_POST['id_produto'];
    $product_type = isset($_POST['product_type']) && in_array($_POST['product_type'], getValidProductTypesForUser($usuario_id), true) ? $_POST['product_type'] : null;
    $product_tagline = isset($_POST['product_tagline']) ? mb_substr(trim($_POST['product_tagline']), 0, 40) : null;
    if ($product_tagline === '') $product_tagline = null;
    // Gateway padrão para novos produtos, mantém o existente ao editar
    $gateway = !empty($id_produto) ? ($_POST['gateway'] ?? 'mercadopago') : 'mercadopago';
    
    // --- Lógica de Upload de Imagem de Capa ---
    $foto_atual = $_POST['foto_atual'] ?? null;
    $nome_foto = $foto_atual;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $arquivo_tmp = $_FILES['foto']['tmp_name'];
        $nome_original = $_FILES['foto']['name'];
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
        $allowed_img_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if(in_array($extensao, $allowed_img_ext)) {
            $nome_foto = uniqid() . '.' . $extensao;
            if (move_uploaded_file($arquivo_tmp, $upload_dir . $nome_foto)) {
                if ($foto_atual && file_exists($upload_dir . $foto_atual)) {
                    unlink($upload_dir . $foto_atual);
                }
            } else {
                $mensagem .= "<div class='bg-red-900/20 text-red-300 p-3 rounded mb-4'>Erro no upload da imagem.</div>";
                $nome_foto = $foto_atual;
            }
        } else {
             $mensagem .= "<div class='bg-red-900/20 text-red-300 p-3 rounded mb-4'>Formato de imagem inválido.</div>";
        }
    }

    // --- Lógica de Entrega do Produto ---
    $tipo_entrega = $_POST['tipo_entrega'];
    $conteudo_entrega_atual = $_POST['conteudo_entrega_atual'] ?? null;
    $conteudo_entrega = $conteudo_entrega_atual;

    if ($tipo_entrega === 'link') {
        $conteudo_entrega = $_POST['conteudo_entrega_link'] ?? null;
    } elseif ($tipo_entrega === 'area_membros') {
        $conteudo_entrega = null; 
    } elseif ($tipo_entrega === 'email_pdf') {
        if (isset($_FILES['conteudo_entrega_pdf']) && $_FILES['conteudo_entrega_pdf']['error'] === UPLOAD_ERR_OK) {
            $pdf_file = $_FILES['conteudo_entrega_pdf'];
            $pdf_ext = strtolower(pathinfo($pdf_file['name'], PATHINFO_EXTENSION));

            if ($pdf_ext === 'pdf') {
                if ($conteudo_entrega_atual && file_exists($upload_dir . $conteudo_entrega_atual)) {
                    unlink($upload_dir . $conteudo_entrega_atual);
                }
                $new_pdf_name = 'pdf_' . uniqid() . '.pdf';
                if (move_uploaded_file($pdf_file['tmp_name'], $upload_dir . $new_pdf_name)) {
                    $conteudo_entrega = $new_pdf_name;
                } else {
                    $mensagem .= "<div class='bg-red-900/20 text-red-300 p-3 rounded mb-4'>Erro no upload do PDF.</div>";
                    $conteudo_entrega = $conteudo_entrega_atual;
                }
            } else {
                $mensagem .= "<div class='bg-red-900/20 text-red-300 p-3 rounded mb-4'>Apenas PDF é permitido.</div>";
            }
        }
    }

    try {
        if (empty($id_produto)) {
            // Verifica limitações via hooks (SaaS)
            $limit_check = do_action('before_create_product', $usuario_id);
            if ($limit_check && isset($limit_check['allowed']) && !$limit_check['allowed']) {
                $mensagem = "<div class='animate-fade-in-down bg-red-900/20 border-l-4 border-red-500 text-red-300 p-4 rounded-md shadow-sm mb-6' role='alert'><div class='flex'><div class='py-1'><i data-lucide='alert-circle' class='w-6 h-6 mr-3 text-red-400'></i></div><div><p class='font-bold text-white'>Limite Atingido</p><p class='text-sm text-red-200'>" . htmlspecialchars($limit_check['message'] ?? 'Limite atingido') . "</p></div></div></div>";
            } else {
                // Adicionar novo produto (ordem = max+1 para o usuario_id, entra no final)
                $checkout_hash = bin2hex(random_bytes(16));

                // Verifica se coluna ordem existe (migration add_produtos_ordem.sql)
                $tem_ordem = false;
                try {
                    $chk = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'ordem'");
                    $tem_ordem = $chk && $chk->rowCount() > 0;
                } catch (PDOException $e) { /* ignora */ }

                list($cf_ins_where, $cf_ins_param) = function_exists('getCommunityFilter') ? getCommunityFilter('produtos') : ['', null];
                $has_community = ($cf_ins_param !== null);
                if ($tem_ordem) {
                    $stmt_max = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 FROM produtos WHERE usuario_id = ?" . $cf_ins_where);
                    $max_params = [$usuario_id];
                    if ($cf_ins_param !== null) $max_params[] = $cf_ins_param;
                    $stmt_max->execute($max_params);
                    $nova_ordem = (int) $stmt_max->fetchColumn();
                    if ($has_community) {
                        $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco, foto, checkout_hash, tipo_entrega, conteudo_entrega, usuario_id, gateway, ordem, community_id, product_type, product_tagline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nome, $descricao, $preco, $nome_foto, $checkout_hash, $tipo_entrega, $conteudo_entrega, $usuario_id, $gateway, $nova_ordem, $community_id, $product_type, $product_tagline]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco, foto, checkout_hash, tipo_entrega, conteudo_entrega, usuario_id, gateway, ordem, product_type, product_tagline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nome, $descricao, $preco, $nome_foto, $checkout_hash, $tipo_entrega, $conteudo_entrega, $usuario_id, $gateway, $nova_ordem, $product_type, $product_tagline]);
                    }
                } else {
                    if ($has_community) {
                        $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco, foto, checkout_hash, tipo_entrega, conteudo_entrega, usuario_id, gateway, community_id, product_type, product_tagline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nome, $descricao, $preco, $nome_foto, $checkout_hash, $tipo_entrega, $conteudo_entrega, $usuario_id, $gateway, $community_id, $product_type, $product_tagline]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco, foto, checkout_hash, tipo_entrega, conteudo_entrega, usuario_id, gateway, product_type, product_tagline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nome, $descricao, $preco, $nome_foto, $checkout_hash, $tipo_entrega, $conteudo_entrega, $usuario_id, $gateway, $product_type, $product_tagline]);
                    }
                }

                $novo_produto_id = $pdo->lastInsertId();
                // Inserir no feed (substitui o trigger when log_bin_trust_function_creators não está ativo)
                try {
                    $chk_feed = $pdo->query("SHOW TABLES LIKE 'products_feed_items'");
                    if ($chk_feed && $chk_feed->rowCount() > 0) {
                        $max_sql = "SELECT COALESCE(MAX(sort_order), 0) FROM products_feed_items WHERE usuario_id = ?";
                        $max_params = [$usuario_id];
                        list($cf_feed_where, $cf_feed_param) = function_exists('getCommunityFilter') ? getCommunityFilter('products_feed_items') : ['', null];
                        if ($cf_feed_param !== null) {
                            $max_sql .= " AND (community_id IS NULL OR community_id = ?)";
                            $max_params[] = $cf_feed_param;
                        }
                        $stmt_max_feed = $pdo->prepare($max_sql);
                        $stmt_max_feed->execute($max_params);
                        $max_order = (int) $stmt_max_feed->fetchColumn() + 1;
                        $feed_community = ($cf_feed_param !== null) ? $community_id : null;
                        $ins_feed = $pdo->prepare("INSERT INTO products_feed_items (community_id, usuario_id, item_type, item_id, sort_order) VALUES (?, ?, 'product', ?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP");
                        $ins_feed->execute([$feed_community, $usuario_id, $novo_produto_id, $max_order]);
                    }
                } catch (PDOException $e) {
                    error_log("Produtos: insert feed_items após criar produto: " . $e->getMessage());
                }
                // Executa hook após criação
                do_action('after_create_product', $novo_produto_id, $usuario_id);
                // Redireciona para página de edição do produto recém-criado
                header("Location: /index?pagina=produto_config&id=" . $novo_produto_id . "&aba=geral");
                exit;
            }
        } else {
            // Atualizar produto
            $stmt = $pdo->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, foto = ?, tipo_entrega = ?, conteudo_entrega = ?, gateway = ?, product_type = ?, product_tagline = ? WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$nome, $descricao, $preco, $nome_foto, $tipo_entrega, $conteudo_entrega, $gateway, $product_type, $product_tagline, $id_produto, $usuario_id]);
            if ($stmt->rowCount() > 0) {
                 $mensagem = "<div class='animate-fade-in-down bg-green-900/20 border-l-4 border-green-500 text-green-300 p-4 rounded-md shadow-sm mb-6' role='alert'><div class='flex'><div class='py-1'><i data-lucide='check-circle' class='w-6 h-6 mr-3 text-green-400'></i></div><div><p class='font-bold text-white'>Sucesso</p><p class='text-sm text-green-200'>Produto atualizado com sucesso!</p></div></div></div>";
            } else {
                 $mensagem = "<div class='bg-blue-900/20 border-l-4 border-blue-500 text-blue-300 p-4 rounded-md shadow-sm mb-6' role='alert'>Nenhuma alteração realizada ou produto não encontrado.</div>";
            }
        }
    } catch (PDOException $e) {
        $mensagem = "<div class='bg-red-900/20 border-l-4 border-red-500 text-red-300 p-4 rounded-md shadow-sm mb-6' role='alert'>Erro ao salvar: " . $e->getMessage() . "</div>";
    }
}

// Redirecionar para nova página de configuração se editar for usado
if (isset($_GET['editar'])) {
    header("Location: /index?pagina=produto_config&id=" . intval($_GET['editar']) . "&aba=geral");
    exit;
}

// Buscar produto para edição (mantido para compatibilidade com formulário antigo)
$produto_edit = null;

// ========== NOVO: Sistema de Feed Unificado (Produtos + Banners) ==========
// Buscar itens do feed ordenados (produtos + banners misturados)
$feed_items = [];

try {
    // Verificar se tabela products_feed_items existe
    $check_feed_table = $pdo->query("SHOW TABLES LIKE 'products_feed_items'");
    $has_feed_table = $check_feed_table && $check_feed_table->rowCount() > 0;
    
    if ($has_feed_table) {
        // Ordem canônica: sort_order (igual à área do cliente e à API de Ofertas Exclusivas).
        $sql_feed = "SELECT pfi.*, pfi.item_type, pfi.item_id, pfi.sort_order
                     FROM products_feed_items pfi
                     WHERE pfi.usuario_id = ?
                     ORDER BY pfi.sort_order ASC, pfi.id ASC";
        $params_feed = [$usuario_id];
        
        $stmt_feed = $pdo->prepare($sql_feed);
        $stmt_feed->execute($params_feed);
        $feed_raw = $stmt_feed->fetchAll(PDO::FETCH_ASSOC);
        
        // Se o feed existir mas estiver vazio (ex.: trigger não rodou), listar direto de produtos (todos do usuário, sem filtro de comunidade)
        if (empty($feed_raw)) {
            $ordem_sql = "ORDER BY id DESC";
            try {
                $chk_ordem = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'ordem'");
                if ($chk_ordem && $chk_ordem->rowCount() > 0) {
                    $ordem_sql = "ORDER BY ordem ASC, id DESC";
                }
            } catch (PDOException $e) { /* ignora */ }
            $stmt_produtos_list = $pdo->prepare("SELECT * FROM produtos WHERE usuario_id = ? " . $ordem_sql);
            $stmt_produtos_list->execute([$usuario_id]);
            $produtos_fallback = $stmt_produtos_list->fetchAll(PDO::FETCH_ASSOC);
            foreach ($produtos_fallback as $produto) {
                $feed_items[] = ['type' => 'product', 'data' => $produto];
            }
        }
        
        // Para cada item do feed, buscar dados completos (ignora órfãos; um por item_type+item_id para não duplicar)
        $vistos_feed = [];
        foreach ($feed_raw as $item) {
            $key = $item['item_type'] . '-' . $item['item_id'];
            if (isset($vistos_feed[$key])) continue;
            $vistos_feed[$key] = true;
            if ($item['item_type'] === 'product') {
                $stmt_prod = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
                $stmt_prod->execute([$item['item_id']]);
                $produto = $stmt_prod->fetch(PDO::FETCH_ASSOC);
                if ($produto) {
                    $feed_items[] = ['type' => 'product', 'data' => $produto];
                }
            } elseif ($item['item_type'] === 'banner') {
                $chk_bb = $pdo->query("SHOW TABLES LIKE 'banner_badges'");
                $has_bb = $chk_bb && $chk_bb->rowCount() > 0;
                $banner_sql = $has_bb
                    ? "SELECT b.*, bb.icon AS badge_icon, bb.label AS badge_label FROM banners b LEFT JOIN banner_badges bb ON bb.id = b.badge_id AND bb.is_active = 1 WHERE b.id = ?"
                    : "SELECT * FROM banners WHERE id = ?";
                $stmt_banner = $pdo->prepare($banner_sql);
                $stmt_banner->execute([$item['item_id']]);
                $banner = $stmt_banner->fetch(PDO::FETCH_ASSOC);
                if ($banner) {
                    $feed_items[] = ['type' => 'banner', 'data' => $banner];
                }
            }
        }
        // Sempre completar com produtos que existem em produtos mas não estão no feed (evita "sumir" produtos).
        // Sem filtro por community_id para não esconder produtos criados em outra comunidade (ex.: club).
        $ids_ja_no_feed = array_map(function($it) { return (int) $it['data']['id']; }, array_filter($feed_items, function($it) { return $it['type'] === 'product'; }));
        $ordem_sql = "ORDER BY id DESC";
        try {
            $chk_ordem = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'ordem'");
            if ($chk_ordem && $chk_ordem->rowCount() > 0) {
                $ordem_sql = "ORDER BY ordem ASC, id DESC";
            }
        } catch (PDOException $e) { /* ignora */ }
        $sql_resto = "SELECT * FROM produtos WHERE usuario_id = ?";
        if (!empty($ids_ja_no_feed)) {
            $placeholders = implode(',', array_fill(0, count($ids_ja_no_feed), '?'));
            $sql_resto .= " AND id NOT IN ($placeholders)";
        }
        $sql_resto .= " " . $ordem_sql;
        $stmt_resto = $pdo->prepare($sql_resto);
        $params_resto = [$usuario_id];
        if (!empty($ids_ja_no_feed)) {
            $params_resto = array_merge($params_resto, $ids_ja_no_feed);
        }
        $stmt_resto->execute($params_resto);
        while ($produto = $stmt_resto->fetch(PDO::FETCH_ASSOC)) {
            $feed_items[] = ['type' => 'product', 'data' => $produto];
        }
        // Completar com banners que existem em banners mas não estão no feed (evita "sumir" banners)
        $ids_banners_ja_no_feed = array_map(function($it) { return (int) $it['data']['id']; }, array_filter($feed_items, function($it) { return $it['type'] === 'banner'; }));
        $chk_ban = $pdo->query("SHOW TABLES LIKE 'banners'");
        if ($chk_ban && $chk_ban->rowCount() > 0) {
            $chk_bb = $pdo->query("SHOW TABLES LIKE 'banner_badges'");
            $has_bb = $chk_bb && $chk_bb->rowCount() > 0;
            $banner_sql_resto = $has_bb
                ? "SELECT b.*, bb.icon AS badge_icon, bb.label AS badge_label FROM banners b LEFT JOIN banner_badges bb ON bb.id = b.badge_id AND bb.is_active = 1 WHERE b.usuario_id = ?"
                : "SELECT * FROM banners WHERE usuario_id = ?";
            if (!empty($ids_banners_ja_no_feed)) {
                $ph = implode(',', array_fill(0, count($ids_banners_ja_no_feed), '?'));
                $banner_sql_resto .= $has_bb ? " AND b.id NOT IN ($ph)" : " AND id NOT IN ($ph)";
            }
            $banner_sql_resto .= $has_bb ? " ORDER BY b.created_at DESC" : " ORDER BY created_at DESC";
            $stmt_ban = $pdo->prepare($banner_sql_resto);
            $params_ban = [$usuario_id];
            if (!empty($ids_banners_ja_no_feed)) $params_ban = array_merge($params_ban, $ids_banners_ja_no_feed);
            $stmt_ban->execute($params_ban);
            while ($banner = $stmt_ban->fetch(PDO::FETCH_ASSOC)) {
                $feed_items[] = ['type' => 'banner', 'data' => $banner];
            }
        }
    } else {
        // Fallback: se tabela feed não existe, mostrar apenas produtos
        $ordem_sql = "ORDER BY id DESC";
        try {
            $chk_ordem = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'ordem'");
            if ($chk_ordem && $chk_ordem->rowCount() > 0) {
                $ordem_sql = "ORDER BY ordem ASC, id DESC";
            }
        } catch (PDOException $e) { /* usa fallback */ }
        
        list($cf_where, $cf_param) = function_exists('getCommunityFilter') ? getCommunityFilter('produtos') : ['', null];
        $stmt_produtos_list = $pdo->prepare("SELECT * FROM produtos WHERE usuario_id = ?" . $cf_where . " " . $ordem_sql);
        $list_params = [$usuario_id];
        if ($cf_param !== null) $list_params[] = $cf_param;
        $stmt_produtos_list->execute($list_params);
        $produtos = $stmt_produtos_list->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($produtos as $produto) {
            $feed_items[] = ['type' => 'product', 'data' => $produto];
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar feed: " . $e->getMessage());
    // Fallback em caso de erro
    $feed_items = [];
}

// Manter $produtos para compatibilidade (apenas produtos)
$produtos = array_filter(array_map(function($item) {
    return $item['type'] === 'product' ? $item['data'] : null;
}, $feed_items));
?>

<style>
    /* Custom Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-down { animation: fadeInDown 0.4s ease-out forwards; }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    
    /* Scrollbar personalizada suave */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #0f1419; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #1a1f24; border-radius: 3px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #32e768; }

    /* SortableJS: feedback visual ao arrastar */
    .sortable-ghost { opacity: 0.4; background: rgba(50, 231, 104, 0.1); }
    .feed-pos-control { pointer-events: auto; }
    .feed-pos-control input::-webkit-outer-spin-button,
    .feed-pos-control input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .feed-pos-control input[type=number] { -moz-appearance: textfield; appearance: textfield; }
    .sortable-chosen { box-shadow: 0 0 0 2px rgba(50, 231, 104, 0.5); }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-10 gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Meus Produtos</h1>
            <p class="text-gray-400 mt-1 text-sm">Gerencie seu catálogo, preços e formas de entrega.</p>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
            <?php if (!empty($feed_items)): ?>
            <button type="button" id="salvar-ordem-btn" class="group bg-gray-700 hover:bg-gray-600 text-gray-200 font-medium py-2.5 px-5 rounded-xl border border-gray-600 transition-all duration-300 flex items-center space-x-2" title="Salvar a ordem atual dos produtos e banners">
                <i data-lucide="save" class="w-5 h-5"></i>
                <span>Salvar ordem</span>
            </button>
            <?php endif; ?>
            <button id="novo-produto-btn" class="group bg-[#32e768] hover:bg-[#28d15e] text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-[#32e768]/20 transition-all duration-300 transform hover:-translate-y-0.5 flex items-center space-x-2">
                <i data-lucide="plus" class="w-5 h-5 transition-transform group-hover:rotate-90"></i>
                <span>Novo Produto</span>
            </button>
            <a href="/index?pagina=categorias_produto" class="group bg-dark-elevated hover:bg-dark-card text-gray-300 hover:text-white font-medium py-2.5 px-5 rounded-xl border border-dark-border transition-all duration-300 flex items-center space-x-2">
                <i data-lucide="tags" class="w-5 h-5"></i>
                <span>Categorias</span>
            </a>
            <a href="/index?pagina=cupons" class="group bg-dark-elevated hover:bg-dark-card text-gray-300 hover:text-white font-medium py-2.5 px-5 rounded-xl border border-dark-border transition-all duration-300 flex items-center space-x-2">
                <i data-lucide="ticket" class="w-5 h-5"></i>
                <span>Cupons</span>
            </a>
            <button type="button" id="novo-banner-btn" onclick="if(typeof window.abrirBannerModal==='function'){window.abrirBannerModal();}else{alert('Recarregue a página e tente novamente.');}" class="group bg-purple-600 hover:bg-purple-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-purple-600/20 transition-all duration-300 transform hover:-translate-y-0.5 flex items-center space-x-2">
                <i data-lucide="image" class="w-5 h-5"></i>
                <span>Novo Banner</span>
            </button>
        </div>
    </div>

    <!-- Area de Mensagens -->
    <?php echo $mensagem; ?>

    <!-- Formulário (Slide Down) -->
    <div id="form-container" class="bg-dark-card rounded-2xl shadow-xl border border-dark-border overflow-hidden mb-10 animate-fade-in-down" style="display: none;">
        <div class="bg-dark-elevated px-8 py-4 border-b border-dark-border flex justify-between items-center">
            <h2 class="text-lg font-bold text-white flex items-center">
                <i data-lucide="<?php echo $produto_edit ? 'edit-3' : 'package-plus'; ?>" class="w-5 h-5 mr-2 text-[#32e768]"></i>
                <?php echo $produto_edit ? 'Editar Produto' : 'Cadastrar Novo Produto'; ?>
            </h2>
            <button id="fechar-form-btn" class="text-gray-400 hover:text-gray-300 transition-colors p-1 rounded-full hover:bg-dark-elevated">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <form action="/index?pagina=produtos" method="post" enctype="multipart/form-data" class="p-8">
            <input type="hidden" name="id_produto" value="<?php echo $produto_edit['id'] ?? ''; ?>">
            <input type="hidden" name="foto_atual" value="<?php echo $produto_edit['foto'] ?? ''; ?>">
            <input type="hidden" name="conteudo_entrega_atual" value="<?php echo htmlspecialchars($produto_edit['conteudo_entrega'] ?? ''); ?>">

            <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                
                <!-- Coluna Esquerda: Informações Básicas -->
                <div class="md:col-span-8 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="nome" class="block text-gray-300 text-sm font-semibold mb-2">Nome do Produto</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                    <i data-lucide="tag" class="w-4 h-4"></i>
                                </span>
                                <input type="text" id="nome" name="nome" class="pl-10 w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white placeholder-gray-500" placeholder="Ex: E-book Premium" value="<?php echo htmlspecialchars($produto_edit['nome'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div>
                            <label for="preco" class="block text-gray-300 text-sm font-semibold mb-2">Preço (R$)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 font-bold"></span>
                                <input type="number" step="0.01" id="preco" name="preco" class="pl-10 w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white" placeholder="0.00" value="<?php echo htmlspecialchars($produto_edit['preco'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="descricao" class="block text-gray-300 text-sm font-semibold mb-2">Descrição</label>
                        <textarea id="descricao" name="descricao" rows="4" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white placeholder-gray-500" placeholder="Descreva os benefícios do seu produto..."><?php echo htmlspecialchars($produto_edit['descricao'] ?? ''); ?></textarea>
                    </div>

                    <!-- TAG/Categoria do Produto -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="product_type" class="block text-gray-300 text-sm font-semibold mb-2">Tipo/Categoria</label>
                            <select id="product_type" name="product_type" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all cursor-pointer text-white">
                                <option value="">— Nenhum —</option>
                                <?php
                                $pt_current = $produto_edit['product_type'] ?? '';
                                foreach (getProductTypeOptionsForUser($usuario_id) as $group => $items):
                                    ?><optgroup label="— <?php echo htmlspecialchars($group); ?> —"><?php
                                    foreach ($items as $value => $label):
                                        ?><option value="<?php echo htmlspecialchars($value); ?>" <?php echo $pt_current === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php
                                    endforeach;
                                    ?></optgroup><?php
                                endforeach;
                                ?>
                            </select>
                        </div>
                        <div>
                            <label for="product_tagline" class="block text-gray-300 text-sm font-semibold mb-2">Tagline (até 40 caracteres)</label>
                            <input type="text" id="product_tagline" name="product_tagline" maxlength="40" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white placeholder-gray-500" placeholder="Ex: Conteúdo para revenda, Quiz interativo" value="<?php echo htmlspecialchars($produto_edit['product_tagline'] ?? ''); ?>">
                            <p class="text-xs text-gray-400 mt-1">Exibido abaixo do título no card. Máx. 40 caracteres.</p>
                        </div>
                    </div>

                    <!-- Configuração de Entrega -->
                    <div class="bg-dark-elevated p-6 rounded-xl border border-dark-border">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-4 flex items-center">
                            <i data-lucide="truck" class="w-4 h-4 mr-2"></i> Configuração de Entrega
                        </h3>
                        
                        <div class="mb-4">
                            <label for="tipo_entrega" class="block text-gray-300 text-sm font-medium mb-2">Como o cliente receberá o produto?</label>
                            <select id="tipo_entrega" name="tipo_entrega" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all cursor-pointer text-white">
                                <option value="link" <?php echo (($produto_edit['tipo_entrega'] ?? 'link') == 'link') ? 'selected' : ''; ?>>🔗 Link Externo (Google Drive, Notion, etc)</option>
                                <option value="email_pdf" <?php echo (($produto_edit['tipo_entrega'] ?? '') == 'email_pdf') ? 'selected' : ''; ?>>📄 Arquivo PDF (Anexo no E-mail)</option>
                                <option value="area_membros" <?php echo (($produto_edit['tipo_entrega'] ?? '') == 'area_membros') ? 'selected' : ''; ?>>🔐 Área de Membros Interna</option>
                            </select>
                        </div>

                        <!-- Campos Dinâmicos de Entrega -->
                        <div id="entrega-fields-container">
                            <div id="entrega-link-container" class="animate-fade-in-down" style="display: none;">
                                <label for="conteudo_entrega_link" class="block text-gray-300 text-sm font-medium mb-2">URL de Acesso</label>
                                <input type="url" id="conteudo_entrega_link" name="conteudo_entrega_link" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:border-[#32e768] focus:ring-2 focus:ring-[#32e768]/20 transition-all text-white placeholder-gray-500" placeholder="https://" value="<?php echo ($produto_edit['tipo_entrega'] ?? '') === 'link' ? htmlspecialchars($produto_edit['conteudo_entrega'] ?? '') : ''; ?>">
                            </div>

                            <div id="entrega-pdf-container" class="animate-fade-in-down" style="display: none;">
                                <label class="block text-gray-300 text-sm font-medium mb-2">Upload do Arquivo PDF</label>
                                <?php if (($produto_edit['tipo_entrega'] ?? '') == 'email_pdf' && !empty($produto_edit['conteudo_entrega'])): ?>
                                    <div class="flex items-center space-x-3 mb-3 p-3 bg-dark-card border border-dark-border rounded-lg shadow-sm">
                                        <div class="bg-red-900/30 p-2 rounded-lg"><i data-lucide="file-text" class="w-5 h-5 text-red-400"></i></div>
                                        <div class="flex-1 truncate">
                                            <p class="text-xs text-gray-400">Arquivo Atual:</p>
                                            <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($produto_edit['conteudo_entrega']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dark-border border-dashed rounded-lg cursor-pointer bg-dark-elevated hover:bg-dark-card transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <i data-lucide="upload-cloud" class="w-8 h-8 text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-400"><span class="font-semibold">Clique para enviar</span> ou arraste</p>
                                        <p class="text-xs text-gray-400">PDF (MAX. 10MB)</p>
                                    </div>
                                    <input type="file" id="conteudo_entrega_pdf" name="conteudo_entrega_pdf" class="hidden" accept="application/pdf">
                                </label>
                                <div id="pdf-file-name" class="mt-2 text-sm text-gray-400 font-medium text-center hidden"></div>
                            </div>

                            <div id="entrega-membros-container" class="animate-fade-in-down" style="display: none;">
                                <div class="flex items-start p-4 bg-blue-900/20 border border-blue-500/30 rounded-lg">
                                    <i data-lucide="info" class="w-5 h-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0"></i>
                                    <div>
                                        <h4 class="font-bold text-blue-300 text-sm">Integração Automática</h4>
                                        <p class="text-sm text-blue-200 mt-1">O acesso será liberado automaticamente na área "Meus Cursos" do aluno após a confirmação do pagamento.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coluna Direita: Imagem e Gateway -->
                <div class="md:col-span-4 space-y-6">
                    <!-- Upload de Imagem -->
                    <div>
                        <label class="block text-gray-300 text-sm font-semibold mb-2">Capa do Produto</label>
                        <div class="relative group">
                            <div class="w-full h-64 bg-dark-elevated rounded-xl overflow-hidden border-2 border-dark-border border-dashed flex items-center justify-center relative">
                                <?php if ($produto_edit && !empty($produto_edit['foto'])): ?>
                                    <?php $foto_src_edit = resolve_product_image_url($produto_edit['foto'], $upload_dir ?? 'uploads/'); ?>
                                    <img src="<?php echo htmlspecialchars($foto_src_edit); ?>" id="preview-img" class="absolute inset-0 w-full h-full object-cover">
                                <?php else: ?>
                                    <img id="preview-img" class="absolute inset-0 w-full h-full object-cover hidden">
                                    <div id="placeholder-img" class="text-center p-4">
                                        <i data-lucide="image" class="w-12 h-12 text-gray-500 mx-auto mb-2"></i>
                                        <p class="text-sm text-gray-400">Nenhuma imagem selecionada</p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Overlay para troca -->
                                <label for="foto" class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all duration-300 flex items-center justify-center cursor-pointer">
                                    <span class="bg-dark-card text-white px-4 py-2 rounded-full shadow-lg font-medium text-sm transform scale-90 opacity-0 group-hover:scale-100 group-hover:opacity-100 transition-all">
                                        <i data-lucide="camera" class="w-4 h-4 inline mr-1"></i> Alterar Capa
                                    </span>
                                </label>
                            </div>
                            <input type="file" id="foto" name="foto" class="hidden" accept="image/png, image/jpeg, image/webp" onchange="previewImage(this)">
                        </div>
                        <p class="text-xs text-gray-400 mt-2 text-center">Recomendado: 1080x1080px (JPG/PNG)</p>
                    </div>
                </div>
            </div>

            <!-- Footer do Form -->
            <div class="flex items-center justify-end space-x-4 mt-8 pt-6 border-t border-dark-border">
                <button type="button" id="cancelar-btn" class="px-6 py-2.5 rounded-lg text-gray-300 hover:bg-dark-elevated hover:text-white font-medium transition-colors">Cancelar</button>
                <button type="submit" name="salvar_produto" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-bold py-2.5 px-8 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 flex items-center">
                    <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                    <?php echo $produto_edit ? 'Salvar Alterações' : 'Cadastrar Produto'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Grid de Produtos + Banners -->
    <div class="animate-fade-in-up">
        <?php if (empty($feed_items)): ?>
            <div class="bg-dark-card rounded-2xl shadow-sm border border-[#32e768] p-12 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-dark-elevated rounded-full mb-6">
                    <i data-lucide="package-open" class="w-10 h-10 text-gray-500"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Nenhum produto encontrado</h3>
                <p class="text-gray-400 mb-8 max-w-md mx-auto">Seu catálogo está vazio. Comece adicionando seu primeiro produto digital agora mesmo.</p>
                <button onclick="document.getElementById('novo-produto-btn').click()" class="text-[#32e768] font-bold hover:text-[#28d15e] hover:underline">
                    Criar meu primeiro produto &rarr;
                </button>
            </div>
        <?php else: ?>
            <div id="lista-produtos" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                <?php foreach ($feed_items as $feed_idx => $item): ?>
                    <?php $feed_pos_ui = (int) $feed_idx + 1; ?>
                    
                    <?php if ($item['type'] === 'product'): ?>
                        <?php $produto = $item['data']; ?>
                    <div class="produto-card relative bg-dark-card rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 border <?php echo (!empty($produto['is_showcase']) && $produto['is_showcase'] == 1) ? 'border-purple-500/50' : 'border-dark-border'; ?> flex flex-col overflow-hidden group" data-id="<?php echo (int) $produto['id']; ?>" data-type="product">
                        
                        <!-- Handle para arrastar (não interfere em editar/excluir) -->
                        <div class="drag-handle cursor-grab active:cursor-grabbing absolute top-3 left-3 z-10 p-2 rounded-lg bg-black/40 hover:bg-black/60 text-gray-300 hover:text-white transition-colors" title="Arrastar para reordenar">
                            <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                        </div>
                        
                        <!-- Capa do Card -->
                        <div class="relative h-56 overflow-hidden bg-dark-elevated">
                            <?php if ($produto['foto']): ?>
                                <?php $prod_foto_src = resolve_product_image_url($produto['foto'], $upload_dir ?? 'uploads/'); ?>
                                <img src="<?php echo htmlspecialchars($prod_foto_src); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110 pointer-events-none">
                            <?php else: ?>
                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-500">
                                    <i data-lucide="image" class="w-12 h-12 mb-2"></i>
                                    <span class="text-xs font-medium">Sem imagem</span>
                                </div>
                            <?php endif; ?>
                            <div class="feed-pos-control absolute bottom-2 left-2 z-30 flex items-center gap-0.5 bg-black/70 backdrop-blur-sm rounded-lg pl-1.5 pr-0.5 py-0.5" onpointerdown="event.stopPropagation();" onmousedown="event.stopPropagation();">
                                <span class="text-[10px] text-gray-300 font-medium select-none">Pos.</span>
                                <button type="button" data-feed-pos-label class="min-w-[1.5rem] px-0.5 text-xs font-semibold text-white hover:text-[#32e768] cursor-text" title="Clique para alterar a posição"><?php echo $feed_pos_ui; ?></button>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" data-feed-pos-input
                                       class="hidden w-12 bg-black/50 border border-white/30 rounded px-0.5 text-center text-xs font-semibold text-white focus:outline-none focus:ring-1 focus:ring-[#32e768]"
                                       value="<?php echo $feed_pos_ui; ?>"
                                       aria-label="Posição no feed">
                                <button type="button" data-feed-pos-go class="p-1 rounded text-gray-300 hover:text-white hover:bg-white/10" title="Mover para esta posição">
                                    <i data-lucide="arrow-right" class="w-3 h-3 pointer-events-none"></i>
                                </button>
                            </div>
                            
                            <!-- Badge Vitrine (ao lado do handle de arraste) -->
                            <?php if (!empty($produto['is_showcase']) && $produto['is_showcase'] == 1): ?>
                            <div class="absolute top-3 left-14 z-10">
                                <span class="bg-purple-500 text-white text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1 shadow-lg">
                                    <i data-lucide="star" class="w-3 h-3"></i>
                                    VITRINE
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Botões de Ação (Canto Superior Direito) -->
                            <div class="absolute top-3 right-3 flex flex-col gap-2">
                                <!-- Botão de Editar -->
                                <a href="/index?pagina=produto_config&id=<?php echo $produto['id']; ?>&aba=geral" class="bg-white/90 hover:bg-white text-gray-800 p-2 rounded-lg shadow-md transition-all duration-200 hover:shadow-lg backdrop-blur-sm" title="Editar Produto">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </a>
                                
                                <!-- Botão de Excluir -->
                                <button type="button" class="delete-produto-btn bg-white/90 hover:bg-white text-red-600 p-2 rounded-lg shadow-md transition-all duration-200 hover:shadow-lg backdrop-blur-sm w-full" title="Excluir Produto" data-produto-id="<?php echo (int)$produto['id']; ?>" data-produto-nome="<?php echo htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Info do Card -->
                        <div class="p-5 flex-grow flex flex-col">
                            <h3 class="font-bold text-white text-lg leading-snug mb-2 line-clamp-2 min-h-[3.5rem]" title="<?php echo htmlspecialchars($produto['nome']); ?>">
                                <?php echo htmlspecialchars($produto['nome']); ?>
                            </h3>
                            <?php
                            $tag_icons = getProductTypeIcons();
                            $ptype = $produto['product_type'] ?? '';
                            $ptag = $produto['product_tagline'] ?? '';
                            $tag_line = '';
                            if ($ptype && isset($tag_icons[$ptype])) {
                                $tag_line = $tag_icons[$ptype] . ' ' . $ptype . ($ptag ? ' • ' . mb_substr($ptag, 0, 40) : '');
                            } elseif ($ptag) {
                                $tag_line = mb_substr($ptag, 0, 40);
                            }
                            ?>
                            <?php if ($tag_line): ?>
                            <p class="text-xs text-gray-400 mb-2 truncate" title="<?php echo htmlspecialchars($tag_line); ?>"><?php echo htmlspecialchars($tag_line); ?></p>
                            <?php endif; ?>
                            <div class="mt-auto flex items-end justify-between border-t border-dark-border pt-4">
                                <div>
                                    <p class="text-xs text-gray-400 uppercase font-semibold">Preço</p>
                                    <?php if (!empty($produto['is_free']) && $produto['is_free'] == 1): ?>
                                        <p class="text-[#32e768] font-bold text-xl flex items-center gap-2">
                                            <span class="bg-green-500/20 text-green-400 text-xs px-2 py-0.5 rounded-full">GRÁTIS</span>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-[#32e768] font-bold text-xl">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-gray-500" title="Tipo de Entrega">
                                    <?php if($produto['tipo_entrega'] == 'link'): ?>
                                        <i data-lucide="link" class="w-5 h-5"></i>
                                    <?php elseif($produto['tipo_entrega'] == 'email_pdf'): ?>
                                        <i data-lucide="file-text" class="w-5 h-5"></i>
                                    <?php else: ?>
                                        <i data-lucide="lock" class="w-5 h-5"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif ($item['type'] === 'banner'): ?>
                        <?php $banner = $item['data']; ?>
                        <?php $banner_img = $banner['image_url'] ?: ('/' . $banner['image_path']); ?>
                        
                        <div class="banner-card relative bg-dark-card rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 border border-purple-500/50 flex flex-col overflow-hidden group" data-id="<?php echo (int) $banner['id']; ?>" data-type="banner">
                            
                            <!-- Handle para arrastar -->
                            <div class="drag-handle cursor-grab active:cursor-grabbing absolute top-3 left-3 z-10 p-2 rounded-lg bg-black/40 hover:bg-black/60 text-gray-300 hover:text-white transition-colors" title="Arrastar para reordenar">
                                <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                            </div>
                            
                            <!-- Badge do Banner (configurável) -->
                            <?php 
                            $badge_icon = !empty($banner['badge_icon']) ? $banner['badge_icon'] : '🔔';
                            $badge_label = !empty($banner['badge_label']) ? $banner['badge_label'] : 'Aviso';
                            ?>
                            <div class="absolute top-3 left-14 z-10">
                                <span class="badge-banner-pill bg-purple-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg flex items-center gap-1 max-w-[140px]" title="<?php echo htmlspecialchars($badge_icon . ' ' . $badge_label); ?>">
                                    <span><?php echo htmlspecialchars($badge_icon); ?></span>
                                    <span class="truncate block"><?php echo htmlspecialchars($badge_label); ?></span>
                                </span>
                            </div>
                            
                            <!-- Capa do Banner (aspect-ratio 2:3) -->
                            <div class="relative overflow-hidden bg-dark-elevated" style="aspect-ratio: 2/3;">
                                <img src="<?php echo htmlspecialchars($banner_img); ?>" alt="<?php echo htmlspecialchars($banner['titulo'] ?: 'Banner'); ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110 pointer-events-none">
                                <div class="feed-pos-control absolute bottom-2 left-2 z-30 flex items-center gap-0.5 bg-black/70 backdrop-blur-sm rounded-lg pl-1.5 pr-0.5 py-0.5" onpointerdown="event.stopPropagation();" onmousedown="event.stopPropagation();">
                                    <span class="text-[10px] text-gray-300 font-medium select-none">Pos.</span>
                                    <button type="button" data-feed-pos-label class="min-w-[1.5rem] px-0.5 text-xs font-semibold text-white hover:text-[#32e768] cursor-text" title="Clique para alterar a posição"><?php echo $feed_pos_ui; ?></button>
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" data-feed-pos-input
                                           class="hidden w-12 bg-black/50 border border-white/30 rounded px-0.5 text-center text-xs font-semibold text-white focus:outline-none focus:ring-1 focus:ring-[#32e768]"
                                           value="<?php echo $feed_pos_ui; ?>"
                                           aria-label="Posição no feed">
                                    <button type="button" data-feed-pos-go class="p-1 rounded text-gray-300 hover:text-white hover:bg-white/10" title="Mover para esta posição">
                                        <i data-lucide="arrow-right" class="w-3 h-3 pointer-events-none"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Botões de Ação -->
                            <div class="absolute top-3 right-3 flex flex-col gap-2 z-20">
                                <button type="button" onclick="if(typeof window.abrirBannerModal==='function'){window.abrirBannerModal(<?php echo (int)$banner['id']; ?>);}else{alert('Recarregue a página e tente novamente.');}" class="bg-white/90 hover:bg-white text-gray-800 p-2 rounded-lg shadow-md transition-all duration-200 hover:shadow-lg backdrop-blur-sm" title="Editar Banner">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                                <button onclick="excluirBanner(<?php echo $banner['id']; ?>)" class="bg-white/90 hover:bg-white text-red-600 p-2 rounded-lg shadow-md transition-all duration-200 hover:shadow-lg backdrop-blur-sm" title="Excluir Banner">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                            
                            <!-- Info do Card -->
                            <div class="p-5 flex-grow flex flex-col bg-gradient-to-b from-transparent to-purple-900/10">
                                <h3 class="font-bold text-white text-lg leading-snug mb-2 line-clamp-2 min-h-[3.5rem]" title="<?php echo htmlspecialchars($banner['titulo'] ?: 'Banner Publicitário'); ?>">
                                    <?php echo htmlspecialchars($banner['titulo'] ?: 'Banner Publicitário'); ?>
                                </h3>
                                
                                <?php if ($banner['click_url']): ?>
                                    <p class="text-xs text-gray-400 truncate mb-2" title="<?php echo htmlspecialchars($banner['click_url']); ?>">
                                        <i data-lucide="external-link" class="w-3 h-3 inline"></i>
                                        <?php echo htmlspecialchars($banner['click_url']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="mt-auto flex items-center justify-between pt-3 border-t border-purple-500/30">
                                    <div class="flex items-center gap-2">
                                        <?php if ($banner['is_active']): ?>
                                            <span class="text-xs bg-green-500/20 text-green-400 px-2 py-1 rounded-full font-medium">✓ Ativo</span>
                                        <?php else: ?>
                                            <span class="text-xs bg-gray-500/20 text-gray-400 px-2 py-1 rounded-full font-medium">⊘ Inativo</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-purple-400 text-xs flex items-center gap-1">
                                        <?php if ($banner['show_in_member_dashboard']): ?>
                                            <i data-lucide="users" class="w-3 h-3" title="Visível no dashboard do cliente"></i>
                                        <?php endif; ?>
                                        <?php if ($banner['show_in_offers_section']): ?>
                                            <i data-lucide="tag" class="w-3 h-3" title="Visível em Ofertas"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    
                    <?php endif; ?>
                    
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmação de exclusão de produto -->
<div id="delete-produto-modal" class="fixed inset-0 z-[9999] hidden overflow-y-auto" aria-labelledby="delete-produto-modal-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="closeDeleteProdutoModal()"></div>
        <div class="relative bg-dark-card rounded-xl shadow-xl border border-dark-border max-w-md w-full p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="h-6 w-6 text-red-500"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-white" id="delete-produto-modal-title">Excluir Produto</h3>
                    <p class="mt-2 text-sm text-gray-400">
                        Tem certeza que deseja excluir o produto <strong id="delete-produto-nome" class="text-white"></strong>?
                    </p>
                    <p class="mt-2 text-sm text-amber-400/90">
                        Esta ação é <strong>irreversível</strong>. Se o produto tiver curso associado (área de membros), todos os módulos e aulas serão perdidos.
                    </p>
                    <form id="delete-produto-form" method="post" action="/index?pagina=produtos" class="mt-4">
                        <input type="hidden" name="id_produto" id="delete-produto-id">
                        <input type="hidden" name="deletar_produto" value="1">
                        <div class="flex gap-3">
                            <button type="button" onclick="confirmDeleteProduto()" class="flex-1 inline-flex justify-center items-center gap-2 rounded-lg px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium text-sm transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                Sim, excluir
                            </button>
                            <button type="button" onclick="closeDeleteProdutoModal()" class="flex-1 inline-flex justify-center items-center gap-2 rounded-lg px-4 py-2.5 bg-dark-elevated hover:bg-dark-border text-gray-300 font-medium text-sm border border-dark-border transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    // Inicializa ícones Lucide
    lucide.createIcons();

    // Função compartilhada: salvar ordem dos cards (produtos + banners) na API
    async function salvarOrdemProdutos() {
        const lista = document.getElementById('lista-produtos');
        if (!lista) return false;
        const cards = getFeedCards();
        if (!cards.length) return false;
        const payload = cards.map((el, idx) => ({
            item_type: el.getAttribute('data-type') === 'banner' ? 'banner' : 'product',
            item_id: parseInt(el.getAttribute('data-id'), 10),
            sort_order: idx
        }));

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15000);

        try {
            const r = await fetch('/api/banners_api?action=reorder_feed', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: payload }),
                credentials: 'same-origin',
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            let res;
            const ct = (r.headers.get('Content-Type') || '').toLowerCase();
            if (ct.includes('application/json')) {
                res = await r.json();
            } else {
                const text = await r.text();
                console.error('Resposta não-JSON:', r.status, text.slice(0, 200));
                throw new Error(r.status === 401 ? 'Sessão expirada. Faça login novamente.' : 'Resposta inválida do servidor.');
            }

            if (!res.success) {
                alert('Não foi possível salvar a ordem: ' + (res.error || 'erro desconhecido'));
                return false;
            }
            return true;
        } catch (e) {
            clearTimeout(timeoutId);
            console.error('Erro ao salvar ordem:', e);
            if (e.name === 'AbortError') {
                alert('A requisição demorou demais. Tente novamente.');
            } else {
                alert(e.message || 'Erro ao comunicar com o servidor. Tente novamente.');
            }
            return false;
        }
    }

    function getFeedCards() {
        const lista = document.getElementById('lista-produtos');
        if (!lista) return [];
        return Array.from(lista.querySelectorAll(':scope > [data-id][data-type]'));
    }

    function refreshFeedPositionInputs() {
        getFeedCards().forEach(function(el, idx) {
            const n = String(idx + 1);
            const label = el.querySelector('[data-feed-pos-label]');
            const input = el.querySelector('[data-feed-pos-input]');
            if (label) label.textContent = n;
            if (input && document.activeElement !== input) {
                input.value = n;
            }
        });
    }

    function parseFeedPosition(raw, max) {
        const texto = String(raw == null ? '' : raw).trim();
        if (texto === '') return null;
        if (!/^\d+$/.test(texto)) return null;
        const n = parseInt(texto, 10);
        if (n < 1 || n > max) return null;
        return n;
    }

    function closeAllFeedPosEdits() {
        document.querySelectorAll('.feed-pos-control.is-editing').forEach(function(controle) {
            cancelFeedPosEdit(controle, true);
        });
    }

    function startFeedPosEdit(controle) {
        if (!controle) return;
        closeAllFeedPosEdits();
        const label = controle.querySelector('[data-feed-pos-label]');
        const input = controle.querySelector('[data-feed-pos-input]');
        const card = controle.closest('[data-id][data-type]');
        if (!label || !input || !card) return;
        const pos = getFeedCards().indexOf(card) + 1;
        input.value = String(pos > 0 ? pos : 1);
        controle.classList.add('is-editing');
        label.classList.add('hidden');
        input.classList.remove('hidden');
        input.focus();
        input.select();
    }

    function cancelFeedPosEdit(controle, skipRefresh) {
        if (!controle) return;
        const label = controle.querySelector('[data-feed-pos-label]');
        const input = controle.querySelector('[data-feed-pos-input]');
        controle.classList.remove('is-editing');
        if (label) label.classList.remove('hidden');
        if (input) input.classList.add('hidden');
        if (!skipRefresh) refreshFeedPositionInputs();
    }

    async function moverCardParaPosicao(card, posicao1) {
        const lista = document.getElementById('lista-produtos');
        const cards = getFeedCards();
        const total = cards.length;
        if (!lista || !card || total === 0) return false;

        const destino = parseFeedPosition(posicao1, total);
        if (destino === null) {
            alert('Informe uma posição inteira entre 1 e ' + total + '.');
            refreshFeedPositionInputs();
            return false;
        }

        const origem = cards.indexOf(card);
        if (origem < 0) return false;
        const destinoIdx = destino - 1;
        if (origem === destinoIdx) {
            refreshFeedPositionInputs();
            return true;
        }

        cards.splice(origem, 1);
        cards.splice(destinoIdx, 0, card);
        cards.forEach(function(el) { lista.appendChild(el); });
        refreshFeedPositionInputs();

        const ok = await salvarOrdemProdutos();
        const btn = document.getElementById('salvar-ordem-btn');
        if (ok) {
            if (btn) {
                const label = btn.querySelector('span');
                if (label) label.textContent = 'Ordem salva!';
                btn.disabled = true;
                setTimeout(function() {
                    if (label) label.textContent = 'Salvar ordem';
                    btn.disabled = false;
                }, 1500);
            }
            return true;
        }
        window.location.reload();
        return false;
    }

    async function aplicarPosicaoDoControle(controlRoot) {
        const card = controlRoot && controlRoot.closest('[data-id][data-type]');
        const input = controlRoot && controlRoot.querySelector('[data-feed-pos-input]');
        if (!card || !input) return;
        const cards = getFeedCards();
        const total = cards.length;
        const destino = parseFeedPosition(input.value, total);
        if (destino === null) {
            alert('Informe uma posição inteira entre 1 e ' + total + '.');
            const atual = cards.indexOf(card) + 1;
            input.value = String(atual > 0 ? atual : 1);
            input.select();
            return;
        }
        const ok = await moverCardParaPosicao(card, destino);
        if (ok) {
            cancelFeedPosEdit(controlRoot, true);
            refreshFeedPositionInputs();
        }
    }

    // Drag & Drop: SortableJS na lista de produtos + banners
    document.addEventListener('DOMContentLoaded', function() {
        const lista = document.getElementById('lista-produtos');
        if (lista && typeof Sortable !== 'undefined') {
            new Sortable(lista, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                filter: '.feed-pos-control, .feed-pos-control *',
                preventOnFilter: false,
                onEnd: async function(evt) {
                    refreshFeedPositionInputs();
                    const ok = await salvarOrdemProdutos();
                    if (ok) {
                        const btn = document.getElementById('salvar-ordem-btn');
                        if (btn) {
                            const label = btn.querySelector('span');
                            if (label) label.textContent = 'Ordem salva!';
                            btn.disabled = true;
                            setTimeout(function() {
                                if (label) label.textContent = 'Salvar ordem';
                                btn.disabled = false;
                            }, 1500);
                        }
                    } else {
                        window.location.reload();
                    }
                }
            });
        }

        if (lista) {
            let feedPosGoLock = false;
            function acionarSetaPosicao(controle) {
                if (!controle || feedPosGoLock) return;
                feedPosGoLock = true;
                setTimeout(function() { feedPosGoLock = false; }, 300);
                if (!controle.classList.contains('is-editing')) {
                    startFeedPosEdit(controle);
                    return;
                }
                aplicarPosicaoDoControle(controle);
            }
            lista.addEventListener('pointerdown', function(e) {
                const go = e.target.closest('[data-feed-pos-go]');
                if (!go) return;
                e.stopPropagation();
                acionarSetaPosicao(go.closest('.feed-pos-control'));
            }, true);
            lista.addEventListener('click', function(e) {
                const go = e.target.closest('[data-feed-pos-go]');
                if (go) {
                    e.preventDefault();
                    acionarSetaPosicao(go.closest('.feed-pos-control'));
                    return;
                }
                const label = e.target.closest('[data-feed-pos-label]');
                if (label) {
                    e.preventDefault();
                    startFeedPosEdit(label.closest('.feed-pos-control'));
                }
            }, true);
            lista.addEventListener('keydown', function(e) {
                const input = e.target.closest('[data-feed-pos-input]');
                if (!input) return;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    aplicarPosicaoDoControle(input.closest('.feed-pos-control'));
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelFeedPosEdit(input.closest('.feed-pos-control'));
                }
            });
        }

        // Botão "Salvar ordem": salva a ordem atual e recarrega para refletir
        const btnSalvarOrdem = document.getElementById('salvar-ordem-btn');
        if (btnSalvarOrdem) {
            btnSalvarOrdem.addEventListener('click', async function() {
                const btn = this;
                const label = btn.querySelector('span');
                const origText = label ? label.textContent : 'Salvar ordem';
                if (label) label.textContent = 'Salvando...';
                btn.disabled = true;
                const ok = await salvarOrdemProdutos();
                if (ok) {
                    if (label) label.textContent = 'Ordem salva!';
                    setTimeout(function() { window.location.reload(); }, 800);
                } else {
                    if (label) label.textContent = origText;
                    btn.disabled = false;
                }
            });
        }
    });

    // Preview de Imagem
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('preview-img');
                const placeholder = document.getElementById('placeholder-img');
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Input file PDF feedback
    document.getElementById('conteudo_entrega_pdf').addEventListener('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : '';
        const display = document.getElementById('pdf-file-name');
        if (fileName) {
            display.textContent = 'Arquivo selecionado: ' + fileName;
            display.classList.remove('hidden');
        } else {
            display.classList.add('hidden');
        }
    });

    // Função Copiar Link com Feedback Visual Melhorado
    function copiarLink(link, btn) {
        navigator.clipboard.writeText(link).then(() => {
            const icon = btn.querySelector('svg'); // Pega o SVG gerado pelo Lucide
            const originalIconHtml = btn.innerHTML; // Salva o HTML original (pode ser o SVG)
            
            // Troca o ícone/classe
            btn.innerHTML = '<i data-lucide="check" class="w-5 h-5"></i>'; // Adiciona o check
            btn.classList.add('bg-[#32e768]', 'text-white');
            btn.classList.remove('bg-dark-card', 'text-white');
            
            lucide.createIcons(); // Renderiza o check

            setTimeout(() => {
                btn.innerHTML = originalIconHtml; // Restaura o original (seja SVG ou <i>)
                
                // Se o original era <i>, precisa renderizar novamente
                if (originalIconHtml.includes('data-lucide')) {
                    lucide.createIcons();
                }

                btn.classList.remove('bg-[#32e768]', 'text-white');
                btn.classList.add('bg-dark-card', 'text-white');
            }, 2000);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const formContainer = document.getElementById('form-container');
        const novoProdutoBtn = document.getElementById('novo-produto-btn');
        const cancelarBtn = document.getElementById('cancelar-btn');
        const fecharFormBtn = document.getElementById('fechar-form-btn');

        function toggleForm(show) {
            if (show) {
                formContainer.style.display = 'block';
                // Scroll suave até o formulário
                formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                novoProdutoBtn.classList.add('opacity-50', 'cursor-not-allowed');
                novoProdutoBtn.disabled = true;
            } else {
                formContainer.style.display = 'none';
                novoProdutoBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                novoProdutoBtn.disabled = false;
                
                // Limpa parâmetro URL
                const url = new URL(window.location);
                url.searchParams.delete('editar');
                window.history.replaceState({}, document.title, url);
            }
        }

        novoProdutoBtn.addEventListener('click', () => toggleForm(true));
        fecharFormBtn.addEventListener('click', () => toggleForm(false));
        cancelarBtn.addEventListener('click', () => {
            // Se estiver editando, volta para o padrão (pode recarregar ou só fechar)
            window.location.href = '/index?pagina=produtos';
        });

        const urlParams = new URLSearchParams(window.location.search);
        // Abre o form apenas se estiver editando ou se tiver mensagem de erro/sucesso (mas não de exclusão)
        const alertElement = document.querySelector('[role="alert"]');
        const isDeleteMessage = alertElement && (alertElement.textContent.includes('deletado') || alertElement.textContent.includes('excluir'));
        
        if (urlParams.has('editar') || (alertElement && !isDeleteMessage)) { 
            toggleForm(true);
        } else {
            toggleForm(false);
        }

        // Lógica de Entrega (Tabs)
        const tipoEntregaSelect = document.getElementById('tipo_entrega');
        const linkContainer = document.getElementById('entrega-link-container');
        const pdfContainer = document.getElementById('entrega-pdf-container');
        const membrosContainer = document.getElementById('entrega-membros-container');
        
        const linkInput = document.getElementById('conteudo_entrega_link');
        const pdfInput = document.getElementById('conteudo_entrega_pdf');

        function toggleEntregaFields() {
            const selectedValue = tipoEntregaSelect.value;

            // Hide all
            linkContainer.style.display = 'none';
            pdfContainer.style.display = 'none';
            membrosContainer.style.display = 'none';
            
            // Reset required
            linkInput.required = false;
            // PDF input required logic is handled in PHP validation largely, but frontend helps
            // We don't force 'required' on file input if updating, logic stays custom.

            if (selectedValue === 'link') {
                linkContainer.style.display = 'block';
                linkInput.required = true;
            } else if (selectedValue === 'email_pdf') {
                pdfContainer.style.display = 'block';
            } else if (selectedValue === 'area_membros') {
                membrosContainer.style.display = 'block';
            }
        }

        tipoEntregaSelect.addEventListener('change', toggleEntregaFields);
        toggleEntregaFields(); // Init

        // ========== Botão Novo Banner (dentro do DOMContentLoaded para garantir que o modal já foi inicializado) ==========
        const novoBannerBtn = document.getElementById('novo-banner-btn');
        if (novoBannerBtn) {
            novoBannerBtn.addEventListener('click', function() {
                if (typeof window.abrirBannerModal === 'function') {
                    window.abrirBannerModal();
                } else {
                    console.error('abrirBannerModal não encontrada.');
                    alert('Erro: Modal de banner não carregado. Recarregue a página.');
                }
            });
        }
    });

    // ========== Funções para Gerenciar Banners ==========
    
    // Excluir banner (função global para ser chamada pelos botões)
    async function excluirBanner(bannerId) {
        if (!confirm('Tem certeza que deseja excluir este banner? Esta ação não pode ser desfeita.')) {
            return;
        }
        
        try {
            const res = await fetch('/api/banners_api?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: bannerId })
            });
            
            const data = await res.json();
            
            if (data.success) {
                // Remover card do DOM com animação
                const bannerCard = document.querySelector(`.banner-card[data-id="${bannerId}"]`);
                if (bannerCard) {
                    bannerCard.style.opacity = '0';
                    bannerCard.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        bannerCard.remove();
                        // Recarregar para garantir consistência
                        window.location.reload();
                    }, 300);
                } else {
                    window.location.reload();
                }
            } else {
                alert('Erro ao excluir banner: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (e) {
            console.error('Erro de conexão:', e);
            alert('Erro de conexão ao excluir banner. Tente novamente.');
        }
    }
    
    // Tornar função global para ser acessível pelos botões inline
    window.excluirBanner = excluirBanner;

    // Modal de exclusão de produto
    window.openDeleteProdutoModal = function(produtoId, produtoNome) {
        var modal = document.getElementById('delete-produto-modal');
        var idInput = document.getElementById('delete-produto-id');
        var nomeEl = document.getElementById('delete-produto-nome');
        if (modal && idInput && nomeEl) {
            idInput.value = String(produtoId);
            nomeEl.textContent = produtoNome || '';
            modal.classList.remove('hidden');
            lucide.createIcons();
        }
    };
    window.closeDeleteProdutoModal = function() {
        var modal = document.getElementById('delete-produto-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };
    window.confirmDeleteProduto = function() {
        var form = document.getElementById('delete-produto-form');
        if (form) form.submit();
    };

    // Delegar clique nos botões de excluir produto (evita problemas com caracteres especiais no nome)
    document.addEventListener('DOMContentLoaded', function() {
        document.body.addEventListener('click', function(e) {
            var btn = e.target.closest('.delete-produto-btn');
            if (btn) {
                e.preventDefault();
                var id = btn.getAttribute('data-produto-id');
                var nome = btn.getAttribute('data-produto-nome') || '';
                if (id) openDeleteProdutoModal(id, nome);
            }
        });
    });
</script>
