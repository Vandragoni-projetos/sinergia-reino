<?php
// Página unificada de configuração de produtos
$mensagem = '';
$produto = null;
$checkout_config = [];
$order_bumps = [];
$upload_dir = 'uploads/';

// Garante que o diretório de uploads exista
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Obter o ID do usuário logado
$usuario_id = $_SESSION['id'] ?? 0;
if ($usuario_id === 0) {
    header("location: /login");
    exit;
}

// Validar e buscar o produto
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: /index?pagina=produtos");
    exit;
}

$id_produto = $_GET['id'];

try {
    // Buscar o produto e verificar se ele pertence ao usuário logado
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$id_produto, $usuario_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        $_SESSION['flash_message'] = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded relative mb-4' role='alert'>Produto não encontrado ou você não tem permissão para acessá-lo.</div>";
        header("Location: /index?pagina=produtos");
        exit;
    }

    $current_gateway = $produto['gateway'] ?? 'mercadopago';
    $checkout_config = json_decode($produto['checkout_config'] ?? '{}', true);

    // Busca os order bumps existentes
    $stmt_ob = $pdo->prepare("SELECT * FROM order_bumps WHERE main_product_id = ? ORDER BY ordem ASC");
    $stmt_ob->execute([$id_produto]);
    $order_bumps = $stmt_ob->fetchAll(PDO::FETCH_ASSOC);

    // Lista de produtos para order bumps (mesmo gateway)
    $stmt_todos_produtos = $pdo->prepare("SELECT id, nome FROM produtos WHERE id != ? AND usuario_id = ? AND gateway = ?");
    $stmt_todos_produtos->execute([$id_produto, $usuario_id, $current_gateway]);
    $lista_produtos_orderbump = $stmt_todos_produtos->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// Função de upload múltiplo
function handle_multiple_uploads($file_key, $prefix, $product_id) {
    $uploaded_paths = [];
    if (isset($_FILES[$file_key]) && is_array($_FILES[$file_key]['name'])) {
        $file_count = count($_FILES[$file_key]['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES[$file_key]['error'][$i] == UPLOAD_ERR_OK) {
                $file_tmp_path = $_FILES[$file_key]['tmp_name'][$i];
                $file_name = $_FILES[$file_key]['name'][$i];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($file_extension, $allowed_extensions)) {
                    $new_file_name = $prefix . '_' . $product_id . '_' . time() . '_' . $i . '.' . $file_extension;
                    $dest_path = 'uploads/' . $new_file_name;

                    if (move_uploaded_file($file_tmp_path, $dest_path)) {
                        $uploaded_paths[] = $dest_path;
                    }
                }
            }
        }
    }
    return $uploaded_paths;
}

/**
 * Processa imagem opcional (foto_2 / foto_3): remover, upload, URL externa ou mantém atual.
 *
 * @param string $fieldName 'foto_2' ou 'foto_3'
 * @return string|null Valor a gravar no banco
 */
function process_produto_optional_foto_field($fieldName, $upload_dir, $produto_row) {
    $atual = $_POST[$fieldName . '_atual'] ?? ($produto_row[$fieldName] ?? null);
    if ($atual === '') {
        $atual = null;
    }

    $del_local = function ($stored) use ($upload_dir) {
        if (!$stored || filter_var($stored, FILTER_VALIDATE_URL)) {
            return;
        }
        $p = $upload_dir . $stored;
        if (is_file($p)) {
            @unlink($p);
        }
    };

    if (!empty($_POST['remover_' . $fieldName])) {
        $del_local($atual);
        return null;
    }

    if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed, true)) {
            $newName = $fieldName . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $upload_dir . $newName)) {
                $del_local($atual);
                return $newName;
            }
        }
        return $atual;
    }

    $url = trim($_POST[$fieldName . '_url_externa'] ?? '');
    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
        $del_local($atual);
        return $url;
    }

    return $atual;
}

// Upload único para imagens do funil (banner/capa); retorna caminho ou null
function save_funnel_single_upload($file_key) {
    global $upload_dir;
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$file_key];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return null;
    $name = 'funnel_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($f['tmp_name'], $upload_dir . $name)) return $upload_dir . $name;
    return null;
}

// Processar formulário de salvamento
if (isset($_POST['salvar_produto_config'])) {
    $pdo->beginTransaction();
    try {
        // Determina a aba atual para saber quais campos processar
        $aba_atual = $_GET['aba'] ?? 'geral';
        
        // ========== ABA GERAL ==========
        // Só atualiza campos da aba geral se estiver nela
        if ($aba_atual === 'geral') {
            $nome = $_POST['nome'] ?? $produto['nome'];
            $descricao = $_POST['descricao'] ?? $produto['descricao'];
            $preco = $_POST['preco'] ?? $produto['preco'];
            $preco_anterior = !empty($_POST['preco_anterior']) ? floatval(str_replace(',', '.', $_POST['preco_anterior'])) : null;
            $price_usd = !empty($_POST['price_usd']) && is_numeric($_POST['price_usd']) ? floatval($_POST['price_usd']) : null;
            if ($price_usd !== null && $price_usd <= 0) $price_usd = null;
            // Preço Order Bump: vazio ou 0,00 → NULL (usa preço principal); >= 0,01 → gravar; negativo/inválido → rejeitar
            $preco_order_bump = null;
            $preco_order_bump_valid = true;
            if (array_key_exists('preco_order_bump', $_POST)) {
                $raw_pob = trim((string) $_POST['preco_order_bump']);
                $raw_pob = str_replace(["\xc2\xa0", ' '], '', $raw_pob);
                $raw_pob = str_replace(',', '.', $raw_pob);
                if ($raw_pob === '') {
                    $preco_order_bump = null;
                } elseif (!is_numeric($raw_pob)) {
                    $preco_order_bump_valid = false;
                    $mensagem = "<div class='bg-red-900/20 border-l-4 border-red-500 text-red-300 p-4 rounded-md shadow-sm mb-6' role='alert'>Preço para Order Bump inválido. Informe um valor (ex.: 14,70), 0,00 ou deixe vazio para usar o preço principal.</div>";
                } else {
                    $pob_val = (float) $raw_pob;
                    if ($pob_val < 0) {
                        $preco_order_bump_valid = false;
                        $mensagem = "<div class='bg-red-900/20 border-l-4 border-red-500 text-red-300 p-4 rounded-md shadow-sm mb-6' role='alert'>O Preço para Order Bump não pode ser negativo. Use 0,00 ou deixe vazio para usar o preço principal.</div>";
                    } elseif ($pob_val < 0.01) {
                        $preco_order_bump = null;
                    } else {
                        $preco_order_bump = round($pob_val, 2);
                    }
                }
            }
            $gateway = $_POST['gateway'] ?? $current_gateway;
            $tipo_entrega = $_POST['tipo_entrega'] ?? $produto['tipo_entrega'];

            // Foto: URL externa ou upload
            $foto_atual = $_POST['foto_atual'] ?? $produto['foto'];
            $nome_foto = $foto_atual;
            $foto_url_externa = trim($_POST['foto_url_externa'] ?? '');
            if (!empty($foto_url_externa) && filter_var($foto_url_externa, FILTER_VALIDATE_URL)) {
                $nome_foto = $foto_url_externa;
            } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $arquivo_tmp = $_FILES['foto']['tmp_name'];
                $nome_original = $_FILES['foto']['name'];
                $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
                $allowed_img_ext = ['jpg', 'jpeg', 'png', 'webp'];
                if(in_array($extensao, $allowed_img_ext)) {
                    $nome_foto = uniqid() . '.' . $extensao;
                    if (move_uploaded_file($arquivo_tmp, $upload_dir . $nome_foto)) {
                        if ($foto_atual && !filter_var($foto_atual, FILTER_VALIDATE_URL) && file_exists($upload_dir . $foto_atual)) {
                            unlink($upload_dir . $foto_atual);
                        }
                    } else {
                        $nome_foto = $foto_atual;
                    }
                }
            }

            $nome_foto2 = process_produto_optional_foto_field('foto_2', $upload_dir, $produto);
            $nome_foto3 = process_produto_optional_foto_field('foto_3', $upload_dir, $produto);

            // Lógica de entrega
            $conteudo_entrega_atual = $_POST['conteudo_entrega_atual'] ?? $produto['conteudo_entrega'];
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
                        }
                    }
                }
            }

            // TAG/Categoria
            $product_type = isset($_POST['product_type']) && in_array($_POST['product_type'], getValidProductTypesForUser($usuario_id), true) ? $_POST['product_type'] : null;
            $product_tagline = isset($_POST['product_tagline']) ? mb_substr(trim($_POST['product_tagline']), 0, 40) : null;
            if ($product_tagline === '') $product_tagline = null;

            // URL da Página de Vendas
            $sales_page_url_raw = isset($_POST['sales_page_url']) ? trim($_POST['sales_page_url']) : '';
            $sales_page_url = ($sales_page_url_raw === '') ? null : mb_substr($sales_page_url_raw, 0, 512);
            $sales_page_url_valid = true;
            if ($sales_page_url !== null && !preg_match('#^https?://#i', $sales_page_url)) {
                $mensagem = "<div class='bg-red-900/20 border-l-4 border-red-500 text-red-300 p-4 rounded-md shadow-sm mb-6' role='alert'>A URL da Página de Vendas deve começar com http:// ou https://</div>";
                $sales_page_url_valid = false;
                $sales_page_url = $produto['sales_page_url'] ?? null;
            }

            // Atualizar dados básicos do produto
            $gera_licenca = isset($_POST['gera_licenca']) ? 1 : 0;
            $is_free = isset($_POST['is_free']) ? 1 : 0;
            $is_showcase = isset($_POST['is_showcase']) ? 1 : 0;
            
            // Se produto é grátis, força preço para 0
            if ($is_free) {
                $preco = 0;
                $preco_anterior = null;
                $price_usd = null;
                $preco_order_bump = null;
                $preco_order_bump_valid = true;
            }
            
            // Se marcou como vitrine, desmarca outros produtos vitrine do mesmo usuário
            if ($is_showcase) {
                $stmt_unset_showcase = $pdo->prepare("UPDATE produtos SET is_showcase = 0 WHERE usuario_id = ? AND id != ?");
                $stmt_unset_showcase->execute([$usuario_id, $id_produto]);
            }
            
            if ($sales_page_url_valid && $preco_order_bump_valid) {
                $has_cols_foto_extra = function_exists('db_table_has_column') && db_table_has_column($pdo, 'produtos', 'foto_2');
                $has_checkout_description_col = function_exists('db_table_has_column') && db_table_has_column($pdo, 'produtos', 'checkout_description');
                $checkout_description_val = null;
                if ($has_checkout_description_col) {
                    $raw_cd = isset($_POST['checkout_description']) ? trim((string) $_POST['checkout_description']) : '';
                    $checkout_description_val = ($raw_cd === '') ? null : mb_substr($raw_cd, 0, 65535);
                }

                if ($has_cols_foto_extra && $has_checkout_description_col) {
                    $stmt_update = $pdo->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, price_usd = ?, preco_order_bump = ?, foto = ?, foto_2 = ?, foto_3 = ?, tipo_entrega = ?, conteudo_entrega = ?, gateway = ?, preco_anterior = ?, gera_licenca = ?, is_free = ?, is_showcase = ?, product_type = ?, product_tagline = ?, sales_page_url = ?, checkout_description = ? WHERE id = ? AND usuario_id = ?");
                    $result = $stmt_update->execute([$nome, $descricao, $preco, $price_usd, $preco_order_bump, $nome_foto, $nome_foto2, $nome_foto3, $tipo_entrega, $conteudo_entrega, $gateway, $preco_anterior, $gera_licenca, $is_free, $is_showcase, $product_type, $product_tagline, $sales_page_url, $checkout_description_val, $id_produto, $usuario_id]);
                } elseif ($has_cols_foto_extra) {
                    $stmt_update = $pdo->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, price_usd = ?, preco_order_bump = ?, foto = ?, foto_2 = ?, foto_3 = ?, tipo_entrega = ?, conteudo_entrega = ?, gateway = ?, preco_anterior = ?, gera_licenca = ?, is_free = ?, is_showcase = ?, product_type = ?, product_tagline = ?, sales_page_url = ? WHERE id = ? AND usuario_id = ?");
                    $result = $stmt_update->execute([$nome, $descricao, $preco, $price_usd, $preco_order_bump, $nome_foto, $nome_foto2, $nome_foto3, $tipo_entrega, $conteudo_entrega, $gateway, $preco_anterior, $gera_licenca, $is_free, $is_showcase, $product_type, $product_tagline, $sales_page_url, $id_produto, $usuario_id]);
                } elseif ($has_checkout_description_col) {
                    $stmt_update = $pdo->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, price_usd = ?, preco_order_bump = ?, foto = ?, tipo_entrega = ?, conteudo_entrega = ?, gateway = ?, preco_anterior = ?, gera_licenca = ?, is_free = ?, is_showcase = ?, product_type = ?, product_tagline = ?, sales_page_url = ?, checkout_description = ? WHERE id = ? AND usuario_id = ?");
                    $result = $stmt_update->execute([$nome, $descricao, $preco, $price_usd, $preco_order_bump, $nome_foto, $tipo_entrega, $conteudo_entrega, $gateway, $preco_anterior, $gera_licenca, $is_free, $is_showcase, $product_type, $product_tagline, $sales_page_url, $checkout_description_val, $id_produto, $usuario_id]);
                } else {
                    $stmt_update = $pdo->prepare("UPDATE produtos SET nome = ?, descricao = ?, preco = ?, price_usd = ?, preco_order_bump = ?, foto = ?, tipo_entrega = ?, conteudo_entrega = ?, gateway = ?, preco_anterior = ?, gera_licenca = ?, is_free = ?, is_showcase = ?, product_type = ?, product_tagline = ?, sales_page_url = ? WHERE id = ? AND usuario_id = ?");
                    $result = $stmt_update->execute([$nome, $descricao, $preco, $price_usd, $preco_order_bump, $nome_foto, $tipo_entrega, $conteudo_entrega, $gateway, $preco_anterior, $gera_licenca, $is_free, $is_showcase, $product_type, $product_tagline, $sales_page_url, $id_produto, $usuario_id]);
                }
                if (!$result) {
                    throw new Exception("Erro ao atualizar produto: " . implode(", ", $stmt_update->errorInfo()));
                }
            }

            // Taxonomia temática — independente de sales_page_url_valid
            if (function_exists('db_table_has_column') && db_table_has_column($pdo, 'produtos', 'main_category_id')
                && function_exists('taxonomy_validate_product_category_assignment')) {
                $taxonomy = taxonomy_validate_product_category_assignment(
                    $pdo,
                    (int) $usuario_id,
                    $_POST['main_category_id'] ?? '',
                    array_key_exists('subcategory_id', $_POST) ? $_POST['subcategory_id'] : null,
                    isset($produto['main_category_id']) ? (int) $produto['main_category_id'] : null,
                    isset($produto['subcategory_id']) ? (int) $produto['subcategory_id'] : null,
                    array_key_exists('subcategory_id', $_POST)
                );
                if (!$taxonomy['ok']) {
                    throw new Exception($taxonomy['error']);
                }
                $stmt_taxonomy = $pdo->prepare("
                    UPDATE produtos
                    SET main_category_id = ?, subcategory_id = ?
                    WHERE id = ? AND usuario_id = ?
                ");
                $stmt_taxonomy->execute([
                    $taxonomy['main_category_id'],
                    $taxonomy['subcategory_id'],
                    $id_produto,
                    $usuario_id,
                ]);
            }

            // Se o gateway mudou, atualizar current_gateway
            if ($gateway !== $current_gateway) {
                $current_gateway = $gateway;
            }
        } // Fim do if ($aba_atual === 'geral')

        // ========== CHECKOUT CONFIG ==========
        // Busca configuração existente
        $stmt_get_config = $pdo->prepare("SELECT checkout_config FROM produtos WHERE id = ? AND usuario_id = ?");
        $stmt_get_config->execute([$id_produto, $usuario_id]);
        $current_config_json = $stmt_get_config->fetchColumn();
        $config_array = json_decode($current_config_json ?: '{}', true);

        // Upload de banners
        $current_banners = $config_array['banners'] ?? [];
        if (empty($current_banners) && !empty($config_array['bannerUrl'])) {
            $current_banners = [$config_array['bannerUrl']];
        }
        $banners_to_remove = $_POST['remove_banners'] ?? [];
        $final_banners_list = [];
        foreach ($current_banners as $banner_path) {
            if (!in_array($banner_path, $banners_to_remove)) {
                $final_banners_list[] = $banner_path;
            } else {
                if (file_exists($banner_path)) unlink($banner_path);
            }
        }
        $newly_uploaded_banners = handle_multiple_uploads('add_banner_files', 'banner', $id_produto);
        $config_array['banners'] = array_merge($final_banners_list, $newly_uploaded_banners);

        $current_side_banners = $config_array['sideBanners'] ?? [];
        if (empty($current_side_banners) && !empty($config_array['sideBannerUrl'])) {
            $current_side_banners = [$config_array['sideBannerUrl']];
        }
        $side_banners_to_remove = $_POST['remove_side_banners'] ?? [];
        $final_side_banners_list = [];
        foreach ($current_side_banners as $banner_path) {
            if (!in_array($banner_path, $side_banners_to_remove)) {
                $final_side_banners_list[] = $banner_path;
            } else {
                if (file_exists($banner_path)) unlink($banner_path);
            }
        }
        $newly_uploaded_side_banners = handle_multiple_uploads('add_side_banner_files', 'sidebanner', $id_produto);
        $config_array['sideBanners'] = array_merge($final_side_banners_list, $newly_uploaded_side_banners);

        unset($config_array['bannerUrl']);
        unset($config_array['sideBannerUrl']);

        // Configurações gerais - só atualiza se os campos estiverem presentes no POST
        if (isset($_POST['backgroundColor'])) {
            $config_array['backgroundColor'] = $_POST['backgroundColor'];
        }
        if (isset($_POST['accentColor'])) {
            $config_array['accentColor'] = $_POST['accentColor'];
        }
        if (isset($_POST['redirectUrl'])) {
            $config_array['redirectUrl'] = $_POST['redirectUrl'];
        }
        if (isset($_POST['youtubeUrl'])) {
            $config_array['youtubeUrl'] = $_POST['youtubeUrl'];
        }
        
        // Link Desabilitado (Aba Links)
        if ($aba_atual === 'links') {
            $config_array['disable_main_link'] = isset($_POST['disable_main_link']);
        }
        
        unset($config_array['facebookPixelId']);

        // Tracking - só atualiza se houver campos de tracking no POST (verifica se algum campo foi realmente enviado)
        // Verifica se algum campo de tracking foi enviado no POST (não apenas se existe, mas se foi realmente enviado)
        $has_tracking_fields = (isset($_POST['facebookPixelId']) && $_POST['facebookPixelId'] !== '') || 
                               (isset($_POST['facebookApiToken']) && $_POST['facebookApiToken'] !== '') || 
                               (isset($_POST['googleAnalyticsId']) && $_POST['googleAnalyticsId'] !== '') || 
                               (isset($_POST['googleAdsId']) && $_POST['googleAdsId'] !== '') ||
                               isset($_POST['fb_event_purchase']) || isset($_POST['fb_event_pending']) || 
                               isset($_POST['fb_event_refund']) || isset($_POST['fb_event_chargeback']) ||
                               isset($_POST['fb_event_rejected']) || isset($_POST['fb_event_initiate_checkout']) ||
                               isset($_POST['gg_event_purchase']) || isset($_POST['gg_event_pending']) ||
                               isset($_POST['gg_event_refund']) || isset($_POST['gg_event_chargeback']) ||
                               isset($_POST['gg_event_rejected']) || isset($_POST['gg_event_initiate_checkout']);
        
        if ($has_tracking_fields) {
            $existing_tracking = $config_array['tracking'] ?? [];
            // Só atualiza campos de texto se tiverem valores não vazios, caso contrário preserva o existente
            $config_array['tracking'] = [
                'facebookPixelId' => (isset($_POST['facebookPixelId']) && $_POST['facebookPixelId'] !== '') ? $_POST['facebookPixelId'] : ($existing_tracking['facebookPixelId'] ?? ''),
                'facebookApiToken' => (isset($_POST['facebookApiToken']) && $_POST['facebookApiToken'] !== '') ? $_POST['facebookApiToken'] : ($existing_tracking['facebookApiToken'] ?? ''),
                'googleAnalyticsId' => (isset($_POST['googleAnalyticsId']) && $_POST['googleAnalyticsId'] !== '') ? $_POST['googleAnalyticsId'] : ($existing_tracking['googleAnalyticsId'] ?? ''),
                'googleAdsId' => (isset($_POST['googleAdsId']) && $_POST['googleAdsId'] !== '') ? $_POST['googleAdsId'] : ($existing_tracking['googleAdsId'] ?? ''),
                'events' => [
                    'facebook' => [
                        'purchase' => isset($_POST['fb_event_purchase']) ? isset($_POST['fb_event_purchase']) : ($existing_tracking['events']['facebook']['purchase'] ?? false),
                        'pending' => isset($_POST['fb_event_pending']) ? isset($_POST['fb_event_pending']) : ($existing_tracking['events']['facebook']['pending'] ?? false),
                        'refund' => isset($_POST['fb_event_refund']) ? isset($_POST['fb_event_refund']) : ($existing_tracking['events']['facebook']['refund'] ?? false),
                        'chargeback' => isset($_POST['fb_event_chargeback']) ? isset($_POST['fb_event_chargeback']) : ($existing_tracking['events']['facebook']['chargeback'] ?? false),
                        'rejected' => isset($_POST['fb_event_rejected']) ? isset($_POST['fb_event_rejected']) : ($existing_tracking['events']['facebook']['rejected'] ?? false),
                        'initiate_checkout' => isset($_POST['fb_event_initiate_checkout']) ? isset($_POST['fb_event_initiate_checkout']) : ($existing_tracking['events']['facebook']['initiate_checkout'] ?? false),
                    ],
                    'google' => [
                        'purchase' => isset($_POST['gg_event_purchase']) ? isset($_POST['gg_event_purchase']) : ($existing_tracking['events']['google']['purchase'] ?? false),
                        'pending' => isset($_POST['gg_event_pending']) ? isset($_POST['gg_event_pending']) : ($existing_tracking['events']['google']['pending'] ?? false),
                        'refund' => isset($_POST['gg_event_refund']) ? isset($_POST['gg_event_refund']) : ($existing_tracking['events']['google']['refund'] ?? false),
                        'chargeback' => isset($_POST['gg_event_chargeback']) ? isset($_POST['gg_event_chargeback']) : ($existing_tracking['events']['google']['chargeback'] ?? false),
                        'rejected' => isset($_POST['gg_event_rejected']) ? isset($_POST['gg_event_rejected']) : ($existing_tracking['events']['google']['rejected'] ?? false),
                        'initiate_checkout' => isset($_POST['gg_event_initiate_checkout']) ? isset($_POST['gg_event_initiate_checkout']) : ($existing_tracking['events']['google']['initiate_checkout'] ?? false),
                    ]
                ]
            ];
        }

        // Summary - só atualiza se os campos estiverem presentes no POST
        if (isset($_POST['summary_product_name']) || isset($_POST['summary_discount_text'])) {
            $existing_summary = $config_array['summary'] ?? [];
            $config_array['summary'] = [
                'product_name' => $_POST['summary_product_name'] ?? ($existing_summary['product_name'] ?? ''),
                'discount_text' => $_POST['summary_discount_text'] ?? ($existing_summary['discount_text'] ?? '')
            ];
        }

        // Header - só atualiza se os campos estiverem presentes no POST
        if (isset($_POST['header_enabled']) || isset($_POST['header_title']) || isset($_POST['header_subtitle'])) {
            $existing_header = $config_array['header'] ?? [];
            $config_array['header'] = [
                'enabled' => isset($_POST['header_enabled']) ? isset($_POST['header_enabled']) : ($existing_header['enabled'] ?? true),
                'title' => $_POST['header_title'] ?? ($existing_header['title'] ?? 'Finalize sua Compra'),
                'subtitle' => $_POST['header_subtitle'] ?? ($existing_header['subtitle'] ?? 'Ambiente 100% seguro')
            ];
        }

        // Timer - só atualiza se os campos estiverem presentes no POST
        if (isset($_POST['timer_enabled']) || isset($_POST['timer_minutes']) || isset($_POST['timer_text']) || isset($_POST['timer_bgcolor']) || isset($_POST['timer_textcolor']) || isset($_POST['timer_sticky'])) {
            $existing_timer = $config_array['timer'] ?? [];
            $config_array['timer'] = [
                'enabled' => isset($_POST['timer_enabled']) ? isset($_POST['timer_enabled']) : ($existing_timer['enabled'] ?? false),
                'minutes' => isset($_POST['timer_minutes']) ? (int)$_POST['timer_minutes'] : ($existing_timer['minutes'] ?? 15),
                'text' => $_POST['timer_text'] ?? ($existing_timer['text'] ?? 'Esta oferta expira em:'),
                'bgcolor' => $_POST['timer_bgcolor'] ?? ($existing_timer['bgcolor'] ?? '#000000'),
                'textcolor' => $_POST['timer_textcolor'] ?? ($existing_timer['textcolor'] ?? '#FFFFFF'),
                'sticky' => isset($_POST['timer_sticky']) ? isset($_POST['timer_sticky']) : ($existing_timer['sticky'] ?? true)
            ];
        }

        // Sales Notification - só atualiza se os campos estiverem presentes no POST
        if (isset($_POST['sales_notification_enabled']) || isset($_POST['sales_notification_names']) || isset($_POST['sales_notification_product']) || isset($_POST['sales_notification_tempo_exibicao']) || isset($_POST['sales_notification_intervalo_notificacao'])) {
            $existing_sales_notification = $config_array['salesNotification'] ?? [];
            $config_array['salesNotification'] = [
                'enabled' => isset($_POST['sales_notification_enabled']) ? isset($_POST['sales_notification_enabled']) : ($existing_sales_notification['enabled'] ?? false),
                'names' => $_POST['sales_notification_names'] ?? ($existing_sales_notification['names'] ?? ''),
                'product' => $_POST['sales_notification_product'] ?? ($existing_sales_notification['product'] ?? ''),
                'tempo_exibicao' => isset($_POST['sales_notification_tempo_exibicao']) ? (int)$_POST['sales_notification_tempo_exibicao'] : ($existing_sales_notification['tempo_exibicao'] ?? 5),
                'intervalo_notificacao' => isset($_POST['sales_notification_intervalo_notificacao']) ? (int)$_POST['sales_notification_intervalo_notificacao'] : ($existing_sales_notification['intervalo_notificacao'] ?? 10)
            ];
        }

        // Payment Methods - Preserva configuração existente se não houver campos no POST
        $existing_payment_methods = $config_array['paymentMethods'] ?? [];
        $has_payment_methods_in_post = isset($_POST['gateway_pushinpay_enabled']) || isset($_POST['gateway_efi_enabled']) || isset($_POST['gateway_mercadopago_enabled']) || isset($_POST['gateway_beehive_enabled']) || isset($_POST['gateway_hypercash_enabled']) ||
                                       isset($_POST['gateway_pagarme_enabled']) || isset($_POST['gateway_paypal_enabled']) || isset($_POST['gateway_stripe_enabled']) ||
                                       isset($_POST['payment_pix_pushinpay']) || isset($_POST['payment_pix_efi']) || isset($_POST['payment_pix_pagarme']) || isset($_POST['payment_pix_stripe']) || isset($_POST['payment_pix_enabled']) || 
                                       isset($_POST['payment_credit_card_enabled']) || isset($_POST['payment_credit_card_mercadopago']) || isset($_POST['payment_credit_card_beehive']) || isset($_POST['payment_credit_card_hypercash']) || isset($_POST['payment_credit_card_efi']) ||
                                       isset($_POST['payment_credit_card_pagarme']) || isset($_POST['payment_credit_card_paypal']) || isset($_POST['payment_credit_card_stripe']) ||
                                       isset($_POST['payment_ticket_enabled']) || isset($_POST['payment_ticket_pagarme']);
        
        // Só atualiza paymentMethods se houver campos no POST (usuário está na aba de métodos de pagamento)
        if ($has_payment_methods_in_post) {
            // Usar campos hidden que sempre são enviados, mesmo quando checkboxes estão desabilitados
            $pushinpay_enabled = isset($_POST['gateway_pushinpay_enabled']) && $_POST['gateway_pushinpay_enabled'] == '1';
            $efi_enabled = isset($_POST['gateway_efi_enabled']) && $_POST['gateway_efi_enabled'] == '1';
            $mercadopago_enabled = isset($_POST['gateway_mercadopago_enabled']) && $_POST['gateway_mercadopago_enabled'] == '1';
            $beehive_enabled = isset($_POST['gateway_beehive_enabled']) && $_POST['gateway_beehive_enabled'] == '1';
            $hypercash_enabled = isset($_POST['gateway_hypercash_enabled']) && $_POST['gateway_hypercash_enabled'] == '1';
            $pagarme_enabled = isset($_POST['gateway_pagarme_enabled']) && $_POST['gateway_pagarme_enabled'] == '1';
            $paypal_enabled = isset($_POST['gateway_paypal_enabled']) && $_POST['gateway_paypal_enabled'] == '1';
            $stripe_enabled = isset($_POST['gateway_stripe_enabled']) && $_POST['gateway_stripe_enabled'] == '1';
            
            // Determinar qual Pix está habilitado (prioridade: PushinPay > Efí > Pagar.me > Stripe > Mercado Pago)
            $pix_pushinpay_checked = $pushinpay_enabled && isset($_POST['payment_pix_pushinpay']) && $_POST['payment_pix_pushinpay'] == '1';
            $pix_efi_checked = $efi_enabled && isset($_POST['payment_pix_efi']) && $_POST['payment_pix_efi'] == '1';
            $pix_pagarme_checked = $pagarme_enabled && isset($_POST['payment_pix_pagarme']) && $_POST['payment_pix_pagarme'] == '1';
            $pix_stripe_checked = $stripe_enabled && isset($_POST['payment_pix_stripe']) && $_POST['payment_pix_stripe'] == '1';
            $pix_mercadopago_checked = $mercadopago_enabled && isset($_POST['payment_pix_enabled']) && $_POST['payment_pix_enabled'] == '1';
            
            // Prioridade: PushinPay > Efí > Pagar.me > Stripe > Mercado Pago
            $pix_gateway = 'mercadopago';
            $pix_enabled = false;
            if ($pix_pushinpay_checked) {
                $pix_gateway = 'pushinpay';
                $pix_enabled = true;
            } elseif ($pix_efi_checked) {
                $pix_gateway = 'efi';
                $pix_enabled = true;
            } elseif ($pix_pagarme_checked) {
                $pix_gateway = 'pagarme';
                $pix_enabled = true;
            } elseif ($pix_stripe_checked) {
                $pix_gateway = 'stripe';
                $pix_enabled = true;
            } elseif ($pix_mercadopago_checked) {
                $pix_gateway = 'mercadopago';
                $pix_enabled = true;
            }

            // Determinar qual gateway de cartão está habilitado (prioridade: Stripe > PayPal > Pagar.me > Hypercash > Beehive > Efí > Mercado Pago)
            $credit_card_gateway = null;
            $credit_card_enabled = false;
            if ($stripe_enabled && isset($_POST['payment_credit_card_stripe']) && $_POST['payment_credit_card_stripe'] == '1') {
                $credit_card_gateway = 'stripe';
                $credit_card_enabled = true;
            } elseif ($paypal_enabled && isset($_POST['payment_credit_card_paypal']) && $_POST['payment_credit_card_paypal'] == '1') {
                $credit_card_gateway = 'paypal';
                $credit_card_enabled = true;
            } elseif ($pagarme_enabled && isset($_POST['payment_credit_card_pagarme']) && $_POST['payment_credit_card_pagarme'] == '1') {
                $credit_card_gateway = 'pagarme';
                $credit_card_enabled = true;
            } elseif ($hypercash_enabled && isset($_POST['payment_credit_card_hypercash']) && $_POST['payment_credit_card_hypercash'] == '1') {
                $credit_card_gateway = 'hypercash';
                $credit_card_enabled = true;
            } elseif ($beehive_enabled && isset($_POST['payment_credit_card_beehive']) && $_POST['payment_credit_card_beehive'] == '1') {
                $credit_card_gateway = 'beehive';
                $credit_card_enabled = true;
            } elseif ($efi_enabled && isset($_POST['payment_credit_card_efi']) && $_POST['payment_credit_card_efi'] == '1') {
                $credit_card_gateway = 'efi';
                $credit_card_enabled = true;
            } elseif ($mercadopago_enabled && isset($_POST['payment_credit_card_mercadopago']) && $_POST['payment_credit_card_mercadopago'] == '1') {
                $credit_card_gateway = 'mercadopago';
                $credit_card_enabled = true;
            } elseif (isset($_POST['payment_credit_card_enabled']) && $_POST['payment_credit_card_enabled'] == '1') {
                // Retrocompatibilidade: se payment_credit_card_enabled estiver marcado mas não houver gateway específico, usar Mercado Pago
                $credit_card_gateway = 'mercadopago';
                $credit_card_enabled = true;
            }

            // Configuração híbrida - múltiplos gateways podem estar habilitados
            if ($pushinpay_enabled || $efi_enabled || $mercadopago_enabled || $beehive_enabled || $hypercash_enabled || $pagarme_enabled || $paypal_enabled || $stripe_enabled) {
                $config_array['paymentMethods'] = [
                    'pix' => [
                        'gateway' => $pix_gateway,
                        'enabled' => $pix_enabled
                    ],
                    'credit_card' => [
                        'gateway' => $credit_card_gateway ?? 'mercadopago',
                        'enabled' => $credit_card_enabled
                    ],
                    'ticket' => [
                        'gateway' => ($pagarme_enabled && isset($_POST['payment_ticket_pagarme']) && $_POST['payment_ticket_pagarme'] == '1') ? 'pagarme' : 'mercadopago',
                        'enabled' => ($pagarme_enabled && isset($_POST['payment_ticket_pagarme']) && $_POST['payment_ticket_pagarme'] == '1') ||
                                    ($mercadopago_enabled && isset($_POST['payment_ticket_enabled']) && $_POST['payment_ticket_enabled'] == '1')
                    ]
                ];
            } else {
                // Nenhum gateway habilitado - preserva configuração existente se houver
                if (!empty($existing_payment_methods)) {
                    $config_array['paymentMethods'] = $existing_payment_methods;
                } else {
                    // Só desabilita se não houver configuração existente
                    $config_array['paymentMethods'] = [
                        'pix' => [
                            'gateway' => 'mercadopago',
                            'enabled' => false
                        ],
                        'credit_card' => [
                            'gateway' => 'mercadopago',
                            'enabled' => false
                        ],
                        'ticket' => [
                            'gateway' => 'mercadopago',
                            'enabled' => false
                        ]
                    ];
                }
            }
        } else {
            // Não há campos de métodos no POST - preserva configuração existente
            if (!empty($existing_payment_methods)) {
                $config_array['paymentMethods'] = $existing_payment_methods;
            }
        }

        // Desconto Pix - só atualiza se estiver na aba de métodos de pagamento
        if ($has_payment_methods_in_post) {
            $config_array['paymentMethods']['pix_discount'] = [
                'enabled' => isset($_POST['pix_discount_enabled']) && $_POST['pix_discount_enabled'] == '1',
                'type' => $_POST['pix_discount_type'] ?? 'percentage',
                'value' => floatval($_POST['pix_discount_value'] ?? 0)
            ];
        }

        // Back Redirect - só atualiza se os campos estiverem presentes no POST
        if (isset($_POST['back_redirect_enabled']) || isset($_POST['back_redirect_url'])) {
            $existing_back_redirect = $config_array['backRedirect'] ?? [];
            $config_array['backRedirect'] = [
                'enabled' => isset($_POST['back_redirect_enabled']) ? isset($_POST['back_redirect_enabled']) : ($existing_back_redirect['enabled'] ?? false),
                'url' => $_POST['back_redirect_url'] ?? ($existing_back_redirect['url'] ?? '')
            ];
        }

        // Customer Fields - só atualiza se os campos estiverem presentes no POST
        if (isset($_POST['enable_cpf']) || isset($_POST['enable_phone'])) {
            $existing_customer_fields = $config_array['customer_fields'] ?? [];
            $config_array['customer_fields'] = [
                'enable_cpf' => isset($_POST['enable_cpf']) ? isset($_POST['enable_cpf']) : ($existing_customer_fields['enable_cpf'] ?? true),
                'enable_phone' => isset($_POST['enable_phone']) ? isset($_POST['enable_phone']) : ($existing_customer_fields['enable_phone'] ?? true),
            ];
        }

        // Element Order - só atualiza se o campo estiver presente no POST
        if (isset($_POST['elementOrder']) && !empty($_POST['elementOrder'])) {
            $elementOrder = json_decode($_POST['elementOrder'], true);
            if (is_array($elementOrder)) {
                $config_array['elementOrder'] = $elementOrder;
            }
        }

        // Salvar checkout_config
        $config_json = json_encode($config_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $stmt = $pdo->prepare("UPDATE produtos SET checkout_config = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$config_json, $id_produto, $usuario_id]);

        // ========== ORDER BUMPS ==========
        // Só processa order bumps se estiver na aba correspondente
        if ($aba_atual === 'order_bumps') {
            $stmt_delete_ob = $pdo->prepare("DELETE FROM order_bumps WHERE main_product_id = ?");
            $stmt_delete_ob->execute([$id_produto]);

            if (isset($_POST['orderbump_product_id']) && is_array($_POST['orderbump_product_id'])) {
            $stmt_insert_ob = $pdo->prepare(
                "INSERT INTO order_bumps (main_product_id, offer_product_id, headline, description, ordem, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $ordem = 0;
            foreach ($_POST['orderbump_product_id'] as $index => $ob_product_id) {
                if (empty($ob_product_id)) continue;

                // Valida se o produto de order bump também pertence ao usuário E se tem o MESMO gateway
                $stmt_check_owner = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ? AND gateway = ?");
                $stmt_check_owner->execute([$ob_product_id, $usuario_id, $current_gateway]);
                
                if($stmt_check_owner->rowCount() > 0) {
                    $headline = $_POST['orderbump_headline'][$index] ?? 'Sim, eu quero aproveitar essa oferta!';
                    $description = $_POST['orderbump_description'][$index] ?? '';
                    $is_active = isset($_POST['orderbump_is_active']) && isset($_POST['orderbump_is_active'][$index]);
                    
                    $stmt_insert_ob->execute([$id_produto, $ob_product_id, $headline, $description, $ordem, $is_active]);
                    $ordem++;
                }
            }
            } // Fim do if (isset($_POST['orderbump_product_id']))
        } // Fim do if ($aba_atual === 'order_bumps')

        // ========== FUNIL DE VENDAS ==========
        // Só processa funil se estiver na aba correspondente
        if ($aba_atual === 'funil') {
            // Normaliza dados do POST
            $upsell_id = !empty($_POST['funnel_upsell_product_id']) ? (int)$_POST['funnel_upsell_product_id'] : null;
            $downsell_id = !empty($_POST['funnel_downsell_product_id']) ? (int)$_POST['funnel_downsell_product_id'] : null;
            $is_active = isset($_POST['funnel_is_active']) ? 1 : 0;

            // Busca community_id do produto principal (se existir coluna)
            $community_id = null;
            try {
                $stmt_c = $pdo->prepare("SHOW COLUMNS FROM produtos LIKE 'community_id'");
                $stmt_c->execute();
                if ($stmt_c->rowCount() > 0) {
                    $stmt_prod = $pdo->prepare("SELECT community_id FROM produtos WHERE id = ? AND usuario_id = ? LIMIT 1");
                    $stmt_prod->execute([$id_produto, $usuario_id]);
                    $community_id = (int)($stmt_prod->fetchColumn() ?? 0) ?: null;
                }
            } catch (PDOException $e) {
                // Ignora, mantém community_id null se algo der errado
            }

            // Garante que produtos escolhidos pertencem ao mesmo usuário
            $validUpsell = null;
            $validDownsell = null;
            if ($upsell_id) {
                $stmt_check = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
                $stmt_check->execute([$upsell_id, $usuario_id]);
                if ($stmt_check->rowCount() > 0) {
                    $validUpsell = $upsell_id;
                }
            }
            if ($downsell_id) {
                $stmt_check = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
                $stmt_check->execute([$downsell_id, $usuario_id]);
                if ($stmt_check->rowCount() > 0) {
                    $validDownsell = $downsell_id;
                }
            }

            // Upsert em product_funnels
            $stmt_exist = $pdo->prepare("SELECT * FROM product_funnels WHERE main_product_id = ? LIMIT 1");
            $stmt_exist->execute([$id_produto]);
            $existing_funnel = $stmt_exist->fetch(PDO::FETCH_ASSOC);
            $existing_funnel_id = $existing_funnel ? (int)$existing_funnel['id'] : null;

            // Monta JSON de personalização upsell/downsell (quando as colunas existirem)
            $upsell_custom = $existing_funnel ? (json_decode($existing_funnel['upsell_custom_config'] ?? '{}', true) ?: []) : [];
            $downsell_custom = $existing_funnel ? (json_decode($existing_funnel['downsell_custom_config'] ?? '{}', true) ?: []) : [];
            $offer_theme = $existing_funnel && !empty($existing_funnel['offer_theme']) ? (json_decode($existing_funnel['offer_theme'], true) ?: []) : [];

            $u_header = save_funnel_single_upload('funnel_upsell_banner_header');
            $u_side = save_funnel_single_upload('funnel_upsell_banner_side');
            $u_cover = save_funnel_single_upload('funnel_upsell_cover');
            $upsell_custom['banner_header'] = $u_header ?: trim($_POST['funnel_upsell_banner_header_url'] ?? '') ?: ($upsell_custom['banner_header'] ?? '');
            $upsell_custom['banner_side'] = $u_side ?: trim($_POST['funnel_upsell_banner_side_url'] ?? '') ?: ($upsell_custom['banner_side'] ?? '');
            $upsell_custom['cover_image'] = $u_cover ?: ($upsell_custom['cover_image'] ?? '');
            $upsell_custom['description'] = trim($_POST['funnel_upsell_description'] ?? '');

            $d_header = save_funnel_single_upload('funnel_downsell_banner_header');
            $d_side = save_funnel_single_upload('funnel_downsell_banner_side');
            $d_cover = save_funnel_single_upload('funnel_downsell_cover');
            $downsell_custom['banner_header'] = $d_header ?: trim($_POST['funnel_downsell_banner_header_url'] ?? '') ?: ($downsell_custom['banner_header'] ?? '');
            $downsell_custom['banner_side'] = $d_side ?: trim($_POST['funnel_downsell_banner_side_url'] ?? '') ?: ($downsell_custom['banner_side'] ?? '');
            $downsell_custom['cover_image'] = $d_cover ?: ($downsell_custom['cover_image'] ?? '');
            $downsell_custom['description'] = trim($_POST['funnel_downsell_description'] ?? '');

            $json_upsell = json_encode($upsell_custom);
            $json_downsell = json_encode($downsell_custom);

            // Tema da página de oferta (cores, logo, textos do cabeçalho)
            $theme_logo = save_funnel_single_upload('funnel_theme_logo');
            $offer_theme['logo_url'] = $theme_logo ?: trim($_POST['funnel_theme_logo_url'] ?? '') ?: ($offer_theme['logo_url'] ?? '');
            $offer_theme['primary_color'] = trim($_POST['funnel_theme_primary_color'] ?? '') ?: ($offer_theme['primary_color'] ?? '#6366f1');
            $offer_theme['secondary_color'] = trim($_POST['funnel_theme_secondary_color'] ?? '') ?: ($offer_theme['secondary_color'] ?? '#4f46e5');
            $offer_theme['page_bg'] = trim($_POST['funnel_theme_page_bg'] ?? '') ?: ($offer_theme['page_bg'] ?? '#f1f5f9');
            $offer_theme['header_label_upsell'] = trim($_POST['funnel_theme_header_label_upsell'] ?? '');
            $offer_theme['header_headline_upsell'] = trim($_POST['funnel_theme_header_headline_upsell'] ?? '');
            $offer_theme['header_label_downsell'] = trim($_POST['funnel_theme_header_label_downsell'] ?? '');
            $offer_theme['header_headline_downsell'] = trim($_POST['funnel_theme_header_headline_downsell'] ?? '');
            $json_offer_theme = json_encode($offer_theme);

            $has_custom_cols = false;
            $has_offer_theme = false;
            try {
                $stmt_col = $pdo->query("SHOW COLUMNS FROM product_funnels LIKE 'upsell_custom_config'");
                $has_custom_cols = $stmt_col && $stmt_col->rowCount() > 0;
                $stmt_theme = $pdo->query("SHOW COLUMNS FROM product_funnels LIKE 'offer_theme'");
                $has_offer_theme = $stmt_theme && $stmt_theme->rowCount() > 0;
            } catch (PDOException $e) { /* coluna não existe */ }

            if ($existing_funnel_id) {
                $sets = "upsell_product_id = ?, downsell_product_id = ?, is_active = ?, community_id = ?";
                $params = [$validUpsell, $validDownsell, $is_active, $community_id];
                if ($has_custom_cols) { $sets .= ", upsell_custom_config = ?, downsell_custom_config = ?"; $params[] = $json_upsell; $params[] = $json_downsell; }
                if ($has_offer_theme) { $sets .= ", offer_theme = ?"; $params[] = $json_offer_theme; }
                $params[] = $existing_funnel_id;
                $stmt_upd = $pdo->prepare("UPDATE product_funnels SET $sets WHERE id = ?");
                $stmt_upd->execute($params);
            } else {
                $cols = "main_product_id, community_id, upsell_product_id, downsell_product_id, is_active";
                $placeholders = "?, ?, ?, ?, ?";
                $params = [$id_produto, $community_id, $validUpsell, $validDownsell, $is_active];
                if ($has_custom_cols) { $cols .= ", upsell_custom_config, downsell_custom_config"; $placeholders .= ", ?, ?"; $params[] = $json_upsell; $params[] = $json_downsell; }
                if ($has_offer_theme) { $cols .= ", offer_theme"; $placeholders .= ", ?"; $params[] = $json_offer_theme; }
                $stmt_ins = $pdo->prepare("INSERT INTO product_funnels ($cols) VALUES ($placeholders)");
                $stmt_ins->execute($params);
            }
        } // Fim do if ($aba_atual === 'funil')

        $pdo->commit();
        if ($mensagem === '') {
            $mensagem = "<div class='bg-green-900/20 border border-green-500 text-green-300 px-4 py-3 rounded relative mb-4' role='alert'>Configurações salvas com sucesso!</div>";
        }

        // Recarrega dados após salvamento
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id_produto, $usuario_id]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        $checkout_config = json_decode($produto['checkout_config'] ?? '{}', true);
        $current_gateway = $produto['gateway'] ?? 'mercadopago';

        $stmt_ob = $pdo->prepare("SELECT * FROM order_bumps WHERE main_product_id = ? ORDER BY ordem ASC");
        $stmt_ob->execute([$id_produto]);
        $order_bumps = $stmt_ob->fetchAll(PDO::FETCH_ASSOC);

        $stmt_todos_produtos = $pdo->prepare("SELECT id, nome FROM produtos WHERE id != ? AND usuario_id = ? AND gateway = ?");
        $stmt_todos_produtos->execute([$id_produto, $usuario_id, $current_gateway]);
        $lista_produtos_orderbump = $stmt_todos_produtos->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded relative mb-4' role='alert'>Erro ao salvar: " . $e->getMessage() . "</div>";
    }
}

// Preparar variáveis para os componentes
$tracking_config = $checkout_config['tracking'] ?? [];
if (empty($tracking_config['facebookPixelId']) && !empty($checkout_config['facebookPixelId'])) {
    $tracking_config['facebookPixelId'] = $checkout_config['facebookPixelId'];
}
$tracking_events = $tracking_config['events'] ?? [];
$fb_events = $tracking_events['facebook'] ?? [];
$gg_events = $tracking_events['google'] ?? [];
$default_order = ['header', 'banner', 'youtube_video', 'summary', 'customer_info', 'order_bump', 'final_summary', 'payment', 'guarantee', 'security_info'];
$element_order = $checkout_config['elementOrder'] ?? $default_order;
if(empty($element_order) || !is_array($element_order)) $element_order = $default_order;

// Ler paymentMethods com retrocompatibilidade
$payment_methods_config = $checkout_config['paymentMethods'] ?? [];
if (empty($payment_methods_config) || !isset($payment_methods_config['pix']['gateway'])) {
    // Estrutura antiga - migrar
    $old_payment_methods = $checkout_config['paymentMethods'] ?? ['credit_card' => true, 'pix' => true, 'ticket' => true];
    $payment_methods_config = [
        'pix' => [
            'gateway' => ($current_gateway === 'pushinpay') ? 'pushinpay' : 'mercadopago',
            'enabled' => $old_payment_methods['pix'] ?? true
        ],
        'credit_card' => [
            'gateway' => 'mercadopago',
            'enabled' => $old_payment_methods['credit_card'] ?? true
        ],
        'ticket' => [
            'gateway' => 'mercadopago',
            'enabled' => $old_payment_methods['ticket'] ?? true
        ]
    ];
}

$customer_fields_config = $checkout_config['customer_fields'] ?? ['enable_cpf' => true, 'enable_phone' => true];

// Determinar aba ativa
$aba_ativa = $_GET['aba'] ?? 'geral';
$abas_permitidas = ['geral', 'order_bumps', 'funil', 'metodos_pagamento', 'rastreamento', 'checkout', 'links'];
if (!in_array($aba_ativa, $abas_permitidas)) {
    $aba_ativa = 'geral';
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-4">
                <a href="/index?pagina=produtos" class="text-[#32e768] hover:text-[#28d15e] transition-colors p-2 hover:bg-dark-elevated rounded-lg" title="Voltar para Meus Produtos">
                    <i data-lucide="arrow-left" class="w-6 h-6"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-white mb-1">Configuração do Produto</h1>
                    <p class="text-gray-400 text-sm flex items-center gap-2">
                        <i data-lucide="package" class="w-4 h-4"></i>
                        <?php echo htmlspecialchars($produto['nome']); ?>
                        <span class="text-gray-500">•</span>
                        <a href="/index?pagina=categorias_produto" class="text-[#32e768] hover:text-[#28d15e] hover:underline">Gerenciar categorias</a>
                    </p>
                </div>
            </div>
        </div>
        <?php if (!empty($mensagem)): ?>
            <div class="mb-6">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sistema de Abas -->
    <div class="bg-dark-card rounded-xl shadow-xl border border-dark-border overflow-hidden mb-8">
        <div class="border-b border-dark-border bg-dark-elevated">
            <nav class="flex overflow-x-auto scrollbar-hide" role="tablist" style="scrollbar-width: none; -ms-overflow-style: none;">
                <a href="/index?pagina=produto_config&id=<?php echo $id_produto; ?>&aba=geral" 
                   class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all duration-200 whitespace-nowrap <?php echo $aba_ativa === 'geral' ? 'text-[#32e768] border-[#32e768] bg-dark-card' : 'text-gray-400 border-transparent hover:text-white hover:border-gray-600'; ?>">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                    <span>Geral</span>
                </a>
                <a href="/index?pagina=produto_config&id=<?php echo $id_produto; ?>&aba=order_bumps" 
                   class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all duration-200 whitespace-nowrap <?php echo $aba_ativa === 'order_bumps' ? 'text-[#32e768] border-[#32e768] bg-dark-card' : 'text-gray-400 border-transparent hover:text-white hover:border-gray-600'; ?>">
                    <i data-lucide="gift" class="w-5 h-5"></i>
                    <span>Order Bumps</span>
                </a>
                <a href="/index?pagina=produto_config&id=<?php echo $id_produto; ?>&aba=funil" 
                   class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all duration-200 whitespace-nowrap <?php echo $aba_ativa === 'funil' ? 'text-[#32e768] border-[#32e768] bg-dark-card' : 'text-gray-400 border-transparent hover:text-white hover:border-gray-600'; ?>">
                    <i data-lucide="workflow" class="w-5 h-5"></i>
                    <span>Funil de Vendas</span>
                </a>
                <a href="/index?pagina=produto_config&id=<?php echo $id_produto; ?>&aba=metodos_pagamento" 
                   class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all duration-200 whitespace-nowrap <?php echo $aba_ativa === 'metodos_pagamento' ? 'text-[#32e768] border-[#32e768] bg-dark-card' : 'text-gray-400 border-transparent hover:text-white hover:border-gray-600'; ?>">
                    <i data-lucide="credit-card" class="w-5 h-5"></i>
                    <span>Métodos de Pagamento</span>
                </a>
                <a href="/index?pagina=produto_config&id=<?php echo $id_produto; ?>&aba=rastreamento" 
                   class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all duration-200 whitespace-nowrap <?php echo $aba_ativa === 'rastreamento' ? 'text-[#32e768] border-[#32e768] bg-dark-card' : 'text-gray-400 border-transparent hover:text-white hover:border-gray-600'; ?>">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                    <span>Rastreamento & Pixels</span>
                </a>
                <a href="/index?pagina=produto_config&id=<?php echo $id_produto; ?>&aba=checkout" 
                   class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all duration-200 whitespace-nowrap <?php echo $aba_ativa === 'checkout' ? 'text-[#32e768] border-[#32e768] bg-dark-card' : 'text-gray-400 border-transparent hover:text-white hover:border-gray-600'; ?>">
                    <i data-lucide="palette" class="w-5 h-5"></i>
                    <span>Checkout</span>
                </a>
                <a href="/index?pagina=produto_config&id=<?php echo $id_produto; ?>&aba=links" 
                   class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition-all duration-200 whitespace-nowrap <?php echo $aba_ativa === 'links' ? 'text-[#32e768] border-[#32e768] bg-dark-card' : 'text-gray-400 border-transparent hover:text-white hover:border-gray-600'; ?>">
                    <i data-lucide="link" class="w-5 h-5"></i>
                    <span>Links</span>
                </a>
            </nav>
        </div>

        <!-- Conteúdo das Abas -->
        <form action="/index?pagina=produto_config&id=<?php echo $id_produto; ?>&aba=<?php echo $aba_ativa; ?>" method="post" enctype="multipart/form-data" class="p-8 bg-dark-card">
            <input type="hidden" name="id_produto" value="<?php echo $id_produto; ?>">
            <input type="hidden" name="foto_atual" value="<?php echo htmlspecialchars($produto['foto'] ?? ''); ?>">
            <input type="hidden" name="foto_2_atual" value="<?php echo htmlspecialchars($produto['foto_2'] ?? ''); ?>">
            <input type="hidden" name="foto_3_atual" value="<?php echo htmlspecialchars($produto['foto_3'] ?? ''); ?>">
            <input type="hidden" name="conteudo_entrega_atual" value="<?php echo htmlspecialchars($produto['conteudo_entrega'] ?? ''); ?>">
            <input type="hidden" name="elementOrder" value="<?php echo htmlspecialchars(json_encode($element_order)); ?>">

            <?php
            // Incluir componente da aba ativa (require: include falho deixava o formulário vazio sem erro claro)
            $aba_dir = __DIR__ . '/produto_config';
            $aba_safe = preg_match('/^[a-z0-9_]+$/', (string) $aba_ativa) ? $aba_ativa : 'geral';
            $aba_file = $aba_dir . '/aba_' . $aba_safe . '.php';
            if (!is_readable($aba_file)) {
                $aba_file = $aba_dir . '/aba_geral.php';
            }
            require $aba_file;
            ?>

            <!-- Botão Salvar -->
            <?php if ($aba_ativa !== 'checkout'): ?>
            <div class="mt-10 pt-6 border-t border-dark-border flex justify-end bg-dark-elevated -mx-8 -mb-8 px-8 py-6 rounded-b-xl">
                <button type="submit" name="salvar_produto_config" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center gap-2 transform hover:scale-105 active:scale-95">
                    <i data-lucide="save" class="w-5 h-5"></i>
                    Salvar Alterações
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<style>
    /* Esconder scrollbar nas abas */
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    
    /* Estilos para inputs e formulários */
    .form-input {
        width: 100%;
        padding: 0.625rem 1rem;
        background-color: rgba(26, 31, 36, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        color: white;
        transition: all 0.2s ease;
        font-family: inherit;
    }
    
    .form-input::placeholder {
        color: rgba(156, 163, 175, 0.6);
    }
    
    .form-input:focus {
        outline: none;
        border-color: #32e768;
        box-shadow: 0 0 0 3px rgba(50, 231, 104, 0.1);
    }
    
    textarea.form-input {
        resize: vertical;
        min-height: 100px;
    }
    
    .form-checkbox {
        width: 1.25rem;
        height: 1.25rem;
        color: #32e768;
        background-color: rgba(26, 31, 36, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.25rem;
        cursor: pointer;
        transition: all 0.2s ease;
        appearance: none;
        -webkit-appearance: none;
        position: relative;
    }
    
    .form-checkbox:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(50, 231, 104, 0.2);
    }
    
    .form-checkbox:checked {
        background-color: #32e768;
        border-color: #32e768;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='white'%3E%3Cpath fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd'/%3E%3C/svg%3E");
        background-size: 100% 100%;
        background-position: center;
        background-repeat: no-repeat;
    }
    
    select.form-input {
        cursor: pointer;
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
});
</script>
