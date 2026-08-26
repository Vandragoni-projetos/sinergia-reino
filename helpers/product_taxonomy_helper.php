<?php
/**
 * Taxonomia temática: Categoria Principal + Subcategoria.
 * Independente de product_type / product_type_categories / nicho_separation.
 */

if (!function_exists('taxonomy_normalize_nome')) {
    /**
     * Normaliza nome para persistência (trim, espaços, limite VARCHAR(120)).
     * @return string|null null se vazio após trim
     */
    function taxonomy_normalize_nome($nome) {
        $nome = trim(preg_replace('/\s+/u', ' ', (string) $nome));
        if ($nome === '') {
            return null;
        }
        return mb_substr($nome, 0, 120);
    }
}

if (!function_exists('taxonomy_slug_from_nome')) {
    /**
     * Gera slug URL-safe a partir do nome (opcional; pode retornar null).
     */
    function taxonomy_slug_from_nome($nome) {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return null;
        }
        $slug = mb_strtolower($nome, 'UTF-8');
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
            if ($ascii !== false) {
                $slug = $ascii;
            }
        }
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            return null;
        }
        return mb_substr($slug, 0, 120);
    }
}

if (!function_exists('taxonomy_normalize_slug')) {
    function taxonomy_normalize_slug($slug, $fallback_nome = null) {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return taxonomy_slug_from_nome($fallback_nome);
        }
        $generated = taxonomy_slug_from_nome($slug);
        return $generated;
    }
}

if (!function_exists('taxonomy_list_main_categories')) {
    function taxonomy_list_main_categories(PDO $pdo, int $usuario_id) {
        $stmt = $pdo->prepare("
            SELECT id, usuario_id, nome, slug, ordem, ativo, created_at
            FROM product_main_categories
            WHERE usuario_id = ?
            ORDER BY ordem ASC, nome ASC
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('taxonomy_get_main_category')) {
    function taxonomy_get_main_category(PDO $pdo, int $id, int $usuario_id) {
        if ($id <= 0 || $usuario_id <= 0) {
            return null;
        }
        $stmt = $pdo->prepare("
            SELECT id, usuario_id, nome, slug, ordem, ativo, created_at
            FROM product_main_categories
            WHERE id = ? AND usuario_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $usuario_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('taxonomy_list_subcategories')) {
    /**
     * @param int|null $main_category_id Se informado, filtra pela categoria principal (validada antes).
     */
    function taxonomy_list_subcategories(PDO $pdo, int $usuario_id, ?int $main_category_id = null) {
        if ($main_category_id !== null && $main_category_id > 0) {
            $stmt = $pdo->prepare("
                SELECT id, usuario_id, main_category_id, nome, slug, ordem, ativo, created_at
                FROM product_subcategories
                WHERE usuario_id = ? AND main_category_id = ?
                ORDER BY ordem ASC, nome ASC
            ");
            $stmt->execute([$usuario_id, $main_category_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, usuario_id, main_category_id, nome, slug, ordem, ativo, created_at
                FROM product_subcategories
                WHERE usuario_id = ?
                ORDER BY main_category_id ASC, ordem ASC, nome ASC
            ");
            $stmt->execute([$usuario_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('taxonomy_get_subcategory')) {
    function taxonomy_get_subcategory(PDO $pdo, int $id, int $usuario_id) {
        if ($id <= 0 || $usuario_id <= 0) {
            return null;
        }
        $stmt = $pdo->prepare("
            SELECT id, usuario_id, main_category_id, nome, slug, ordem, ativo, created_at
            FROM product_subcategories
            WHERE id = ? AND usuario_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $usuario_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('taxonomy_assert_main_category_owner')) {
    /**
     * @return array{ok:bool, row:?array, error:?string}
     */
    function taxonomy_assert_main_category_owner(PDO $pdo, int $id, int $usuario_id) {
        $row = taxonomy_get_main_category($pdo, $id, $usuario_id);
        if (!$row) {
            return ['ok' => false, 'row' => null, 'error' => 'Categoria principal não encontrada'];
        }
        return ['ok' => true, 'row' => $row, 'error' => null];
    }
}

if (!function_exists('taxonomy_assert_subcategory_owner')) {
    /**
     * Valida dono da subcategoria e consistência com a categoria principal vinculada.
     * @return array{ok:bool, row:?array, main:?array, error:?string}
     */
    function taxonomy_assert_subcategory_owner(PDO $pdo, int $id, int $usuario_id) {
        $row = taxonomy_get_subcategory($pdo, $id, $usuario_id);
        if (!$row) {
            return ['ok' => false, 'row' => null, 'main' => null, 'error' => 'Subcategoria não encontrada'];
        }
        $main = taxonomy_get_main_category($pdo, (int) $row['main_category_id'], $usuario_id);
        if (!$main) {
            return ['ok' => false, 'row' => $row, 'main' => null, 'error' => 'Subcategoria com categoria principal inválida'];
        }
        if ((int) $row['usuario_id'] !== (int) $main['usuario_id']) {
            return ['ok' => false, 'row' => $row, 'main' => $main, 'error' => 'Inconsistência de proprietário entre subcategoria e categoria principal'];
        }
        return ['ok' => true, 'row' => $row, 'main' => $main, 'error' => null];
    }
}

if (!function_exists('taxonomy_validate_subcategory_belongs_to_main')) {
    /**
     * Garante que a subcategoria pertence à categoria principal informada e ao usuário.
     * @return array{ok:bool, sub:?array, main:?array, error:?string}
     */
    function taxonomy_validate_subcategory_belongs_to_main(PDO $pdo, int $subcategory_id, int $main_category_id, int $usuario_id) {
        $check = taxonomy_assert_subcategory_owner($pdo, $subcategory_id, $usuario_id);
        if (!$check['ok']) {
            return ['ok' => false, 'sub' => null, 'main' => null, 'error' => $check['error']];
        }
        if ((int) $check['row']['main_category_id'] !== $main_category_id) {
            return ['ok' => false, 'sub' => $check['row'], 'main' => $check['main'], 'error' => 'Subcategoria não pertence à categoria principal informada'];
        }
        return ['ok' => true, 'sub' => $check['row'], 'main' => $check['main'], 'error' => null];
    }
}

if (!function_exists('taxonomy_count_products_by_main_category')) {
    function taxonomy_count_products_by_main_category(PDO $pdo, int $main_category_id, int $usuario_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE usuario_id = ? AND main_category_id = ?");
        $stmt->execute([$usuario_id, $main_category_id]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('taxonomy_count_products_by_subcategory')) {
    function taxonomy_count_products_by_subcategory(PDO $pdo, int $subcategory_id, int $usuario_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE usuario_id = ? AND subcategory_id = ?");
        $stmt->execute([$usuario_id, $subcategory_id]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('taxonomy_count_subcategories_by_main')) {
    function taxonomy_count_subcategories_by_main(PDO $pdo, int $main_category_id, int $usuario_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_subcategories WHERE usuario_id = ? AND main_category_id = ?");
        $stmt->execute([$usuario_id, $main_category_id]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('taxonomy_create_main_category')) {
    function taxonomy_create_main_category(PDO $pdo, int $usuario_id, array $data) {
        if ($usuario_id <= 0) {
            return ['success' => false, 'error' => 'Usuário inválido'];
        }
        $nome = taxonomy_normalize_nome($data['nome'] ?? '');
        if ($nome === null) {
            return ['success' => false, 'error' => 'Nome da categoria principal é obrigatório'];
        }
        $ordem = (int) ($data['ordem'] ?? 0);
        $ativo = isset($data['ativo']) ? ((int) (bool) $data['ativo']) : 1;
        $slug = taxonomy_normalize_slug($data['slug'] ?? '', $nome);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_main_categories (usuario_id, nome, slug, ordem, ativo)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $nome, $slug, $ordem, $ativo]);
            return ['success' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Categoria principal criada!'];
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return ['success' => false, 'error' => 'Já existe uma categoria principal com esse nome'];
            }
            error_log('taxonomy_create_main_category: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao criar categoria principal'];
        }
    }
}

if (!function_exists('taxonomy_update_main_category')) {
    function taxonomy_update_main_category(PDO $pdo, int $usuario_id, int $id, array $data) {
        $assert = taxonomy_assert_main_category_owner($pdo, $id, $usuario_id);
        if (!$assert['ok']) {
            return ['success' => false, 'error' => $assert['error']];
        }
        $nome = taxonomy_normalize_nome($data['nome'] ?? '');
        if ($nome === null) {
            return ['success' => false, 'error' => 'Nome da categoria principal é obrigatório'];
        }
        $ordem = (int) ($data['ordem'] ?? $assert['row']['ordem']);
        $ativo = isset($data['ativo']) ? ((int) (bool) $data['ativo']) : (int) $assert['row']['ativo'];
        $slug = array_key_exists('slug', $data)
            ? taxonomy_normalize_slug($data['slug'] ?? '', $nome)
            : ($assert['row']['slug'] ?? taxonomy_slug_from_nome($nome));

        try {
            $stmt = $pdo->prepare("
                UPDATE product_main_categories
                SET nome = ?, slug = ?, ordem = ?, ativo = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([$nome, $slug, $ordem, $ativo, $id, $usuario_id]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'Categoria principal não encontrada ou sem alteração'];
            }
            return ['success' => true, 'message' => 'Categoria principal atualizada!'];
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return ['success' => false, 'error' => 'Já existe outra categoria principal com esse nome'];
            }
            error_log('taxonomy_update_main_category: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao atualizar categoria principal'];
        }
    }
}

if (!function_exists('taxonomy_delete_main_category')) {
    function taxonomy_delete_main_category(PDO $pdo, int $usuario_id, int $id) {
        $assert = taxonomy_assert_main_category_owner($pdo, $id, $usuario_id);
        if (!$assert['ok']) {
            return ['success' => false, 'error' => $assert['error']];
        }
        $linked_products = taxonomy_count_products_by_main_category($pdo, $id, $usuario_id);
        if ($linked_products > 0) {
            return ['success' => false, 'error' => 'Não é possível excluir: existem produtos vinculados a esta categoria principal'];
        }
        $linked_subs = taxonomy_count_subcategories_by_main($pdo, $id, $usuario_id);
        if ($linked_subs > 0) {
            return ['success' => false, 'error' => 'Não é possível excluir: existem subcategorias vinculadas a esta categoria principal'];
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM product_main_categories WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'Categoria principal não encontrada'];
            }
            return ['success' => true, 'message' => 'Categoria principal excluída!'];
        } catch (PDOException $e) {
            error_log('taxonomy_delete_main_category: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao excluir categoria principal'];
        }
    }
}

if (!function_exists('taxonomy_create_subcategory')) {
    function taxonomy_create_subcategory(PDO $pdo, int $usuario_id, array $data) {
        if ($usuario_id <= 0) {
            return ['success' => false, 'error' => 'Usuário inválido'];
        }
        $main_category_id = (int) ($data['main_category_id'] ?? 0);
        if ($main_category_id <= 0) {
            return ['success' => false, 'error' => 'Categoria principal é obrigatória'];
        }
        $main_assert = taxonomy_assert_main_category_owner($pdo, $main_category_id, $usuario_id);
        if (!$main_assert['ok']) {
            return ['success' => false, 'error' => $main_assert['error']];
        }
        if ((int) $main_assert['row']['usuario_id'] !== $usuario_id) {
            return ['success' => false, 'error' => 'Categoria principal não pertence ao usuário autenticado'];
        }

        $nome = taxonomy_normalize_nome($data['nome'] ?? '');
        if ($nome === null) {
            return ['success' => false, 'error' => 'Nome da subcategoria é obrigatório'];
        }
        $ordem = (int) ($data['ordem'] ?? 0);
        $ativo = isset($data['ativo']) ? ((int) (bool) $data['ativo']) : 1;
        $slug = taxonomy_normalize_slug($data['slug'] ?? '', $nome);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_subcategories (usuario_id, main_category_id, nome, slug, ordem, ativo)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $main_category_id, $nome, $slug, $ordem, $ativo]);
            return ['success' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Subcategoria criada!'];
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return ['success' => false, 'error' => 'Já existe uma subcategoria com esse nome nesta categoria principal'];
            }
            error_log('taxonomy_create_subcategory: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao criar subcategoria'];
        }
    }
}

if (!function_exists('taxonomy_update_subcategory')) {
    function taxonomy_update_subcategory(PDO $pdo, int $usuario_id, int $id, array $data) {
        $assert = taxonomy_assert_subcategory_owner($pdo, $id, $usuario_id);
        if (!$assert['ok']) {
            return ['success' => false, 'error' => $assert['error']];
        }

        $main_category_id = isset($data['main_category_id'])
            ? (int) $data['main_category_id']
            : (int) $assert['row']['main_category_id'];
        if ($main_category_id <= 0) {
            return ['success' => false, 'error' => 'Categoria principal é obrigatória'];
        }
        $main_assert = taxonomy_assert_main_category_owner($pdo, $main_category_id, $usuario_id);
        if (!$main_assert['ok']) {
            return ['success' => false, 'error' => $main_assert['error']];
        }
        if ((int) $main_assert['row']['usuario_id'] !== $usuario_id) {
            return ['success' => false, 'error' => 'Categoria principal não pertence ao usuário autenticado'];
        }

        $nome = taxonomy_normalize_nome($data['nome'] ?? '');
        if ($nome === null) {
            return ['success' => false, 'error' => 'Nome da subcategoria é obrigatório'];
        }
        $ordem = (int) ($data['ordem'] ?? $assert['row']['ordem']);
        $ativo = isset($data['ativo']) ? ((int) (bool) $data['ativo']) : (int) $assert['row']['ativo'];
        $slug = array_key_exists('slug', $data)
            ? taxonomy_normalize_slug($data['slug'] ?? '', $nome)
            : ($assert['row']['slug'] ?? taxonomy_slug_from_nome($nome));

        try {
            $stmt = $pdo->prepare("
                UPDATE product_subcategories
                SET main_category_id = ?, nome = ?, slug = ?, ordem = ?, ativo = ?, usuario_id = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([$main_category_id, $nome, $slug, $ordem, $ativo, $usuario_id, $id, $usuario_id]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'Subcategoria não encontrada ou sem alteração'];
            }
            return ['success' => true, 'message' => 'Subcategoria atualizada!'];
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                return ['success' => false, 'error' => 'Já existe outra subcategoria com esse nome nesta categoria principal'];
            }
            error_log('taxonomy_update_subcategory: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao atualizar subcategoria'];
        }
    }
}

if (!function_exists('taxonomy_delete_subcategory')) {
    function taxonomy_delete_subcategory(PDO $pdo, int $usuario_id, int $id) {
        $assert = taxonomy_assert_subcategory_owner($pdo, $id, $usuario_id);
        if (!$assert['ok']) {
            return ['success' => false, 'error' => $assert['error']];
        }
        $linked_products = taxonomy_count_products_by_subcategory($pdo, $id, $usuario_id);
        if ($linked_products > 0) {
            return ['success' => false, 'error' => 'Não é possível excluir: existem produtos vinculados a esta subcategoria'];
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM product_subcategories WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'Subcategoria não encontrada'];
            }
            return ['success' => true, 'message' => 'Subcategoria excluída!'];
        } catch (PDOException $e) {
            error_log('taxonomy_delete_subcategory: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao excluir subcategoria'];
        }
    }
}

if (!function_exists('taxonomy_list_active_main_categories')) {
    /**
     * Categorias principais ativas do usuário (para selects de produto).
     */
    function taxonomy_list_active_main_categories(PDO $pdo, int $usuario_id) {
        $items = taxonomy_list_main_categories($pdo, $usuario_id);
        return array_values(array_filter($items, function ($row) {
            return (int) ($row['ativo'] ?? 1) === 1;
        }));
    }
}

if (!function_exists('taxonomy_list_active_subcategories')) {
    /**
     * Subcategorias ativas do usuário, opcionalmente filtradas por categoria principal.
     */
    function taxonomy_list_active_subcategories(PDO $pdo, int $usuario_id, ?int $main_category_id = null) {
        $items = taxonomy_list_subcategories($pdo, $usuario_id, $main_category_id);
        return array_values(array_filter($items, function ($row) {
            return (int) ($row['ativo'] ?? 1) === 1;
        }));
    }
}

if (!function_exists('taxonomy_format_select_option_label')) {
    function taxonomy_format_select_option_label(array $row, bool $mark_inactive = true) {
        $nome = (string) ($row['nome'] ?? '');
        if ($mark_inactive && (int) ($row['ativo'] ?? 1) !== 1) {
            return $nome . ' (Inativa)';
        }
        return $nome;
    }
}

if (!function_exists('taxonomy_main_categories_for_product_select')) {
    /**
     * Opções de categoria principal para select de produto: ativas + vínculo inativo atual.
     */
    function taxonomy_main_categories_for_product_select(PDO $pdo, int $usuario_id, ?int $linked_main_id = null) {
        $items = taxonomy_list_active_main_categories($pdo, $usuario_id);
        $known_ids = [];
        foreach ($items as $row) {
            $known_ids[(int) $row['id']] = true;
        }
        $linked_main_id = (int) ($linked_main_id ?? 0);
        if ($linked_main_id > 0 && !isset($known_ids[$linked_main_id])) {
            $linked = taxonomy_get_main_category($pdo, $linked_main_id, $usuario_id);
            if ($linked) {
                $items[] = $linked;
            }
        }
        usort($items, function ($a, $b) {
            $ordem_cmp = ((int) ($a['ordem'] ?? 0)) <=> ((int) ($b['ordem'] ?? 0));
            if ($ordem_cmp !== 0) {
                return $ordem_cmp;
            }
            return strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
        });
        return $items;
    }
}

if (!function_exists('taxonomy_subcategories_for_product_select')) {
    /**
     * Opções de subcategoria para select de produto: ativas + vínculo inativo atual.
     */
    function taxonomy_subcategories_for_product_select(PDO $pdo, int $usuario_id, ?int $main_category_id, ?int $linked_sub_id = null) {
        $main_category_id = (int) ($main_category_id ?? 0);
        if ($main_category_id <= 0) {
            return [];
        }
        $items = taxonomy_list_active_subcategories($pdo, $usuario_id, $main_category_id);
        $known_ids = [];
        foreach ($items as $row) {
            $known_ids[(int) $row['id']] = true;
        }
        $linked_sub_id = (int) ($linked_sub_id ?? 0);
        if ($linked_sub_id > 0 && !isset($known_ids[$linked_sub_id])) {
            $linked = taxonomy_get_subcategory($pdo, $linked_sub_id, $usuario_id);
            if ($linked && (int) $linked['main_category_id'] === $main_category_id) {
                $items[] = $linked;
            }
        }
        usort($items, function ($a, $b) {
            $ordem_cmp = ((int) ($a['ordem'] ?? 0)) <=> ((int) ($b['ordem'] ?? 0));
            if ($ordem_cmp !== 0) {
                return $ordem_cmp;
            }
            return strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
        });
        return $items;
    }
}

if (!function_exists('taxonomy_validate_product_category_assignment')) {
    /**
     * Valida associação produto → categoria principal / subcategoria (save backend).
     *
     * @param mixed $main_raw Valor bruto do POST (main_category_id)
     * @param mixed $sub_raw Valor bruto do POST (subcategory_id); null se campo ausente (ex.: disabled)
     * @param int|null $existing_main_id Vínculo atual do produto (preservação)
     * @param int|null $existing_sub_id Vínculo atual do produto (preservação)
     * @param bool $subcategory_id_in_post Se subcategory_id veio no POST
     * @return array{ok:bool, main_category_id:?int, subcategory_id:?int, error:?string}
     */
    function taxonomy_validate_product_category_assignment(
        PDO $pdo,
        int $usuario_id,
        $main_raw,
        $sub_raw,
        ?int $existing_main_id = null,
        ?int $existing_sub_id = null,
        bool $subcategory_id_in_post = true
    ) {
        if ($usuario_id <= 0) {
            return ['ok' => false, 'main_category_id' => null, 'subcategory_id' => null, 'error' => 'Usuário inválido'];
        }

        $existing_main_id = ($existing_main_id !== null && $existing_main_id > 0) ? (int) $existing_main_id : null;
        $existing_sub_id = ($existing_sub_id !== null && $existing_sub_id > 0) ? (int) $existing_sub_id : null;

        $main_int = is_numeric($main_raw) ? (int) $main_raw : 0;
        if ($subcategory_id_in_post) {
            $sub_int = is_numeric($sub_raw) ? (int) $sub_raw : 0;
        } else {
            $sub_int = 0;
        }
        if ($main_int < 0) {
            $main_int = 0;
        }
        if ($sub_int < 0) {
            $sub_int = 0;
        }

        // Campo sub ausente (disabled): preservar vínculo existente se main não mudou
        if (!$subcategory_id_in_post && $existing_sub_id !== null && $existing_main_id !== null
            && $main_int > 0 && $main_int === $existing_main_id) {
            $sub_int = $existing_sub_id;
        }

        if ($sub_int > 0 && $main_int <= 0) {
            return [
                'ok' => false,
                'main_category_id' => null,
                'subcategory_id' => null,
                'error' => 'Não é permitido associar subcategoria sem categoria principal.',
            ];
        }

        $main_id = null;
        $sub_id = null;

        if ($main_int > 0) {
            $main_assert = taxonomy_assert_main_category_owner($pdo, $main_int, $usuario_id);
            if (!$main_assert['ok']) {
                return [
                    'ok' => false,
                    'main_category_id' => null,
                    'subcategory_id' => null,
                    'error' => 'Categoria principal inválida ou não pertence ao seu usuário.',
                ];
            }
            $main_id = $main_int;
        }

        if ($sub_int > 0) {
            if ($main_id === null) {
                return [
                    'ok' => false,
                    'main_category_id' => null,
                    'subcategory_id' => null,
                    'error' => 'Não é permitido associar subcategoria sem categoria principal.',
                ];
            }
            $check = taxonomy_validate_subcategory_belongs_to_main($pdo, $sub_int, $main_id, $usuario_id);
            if (!$check['ok']) {
                return [
                    'ok' => false,
                    'main_category_id' => $main_id,
                    'subcategory_id' => null,
                    'error' => 'Subcategoria inválida ou não pertence à categoria principal selecionada.',
                ];
            }
            $sub_id = $sub_int;
        }

        return ['ok' => true, 'main_category_id' => $main_id, 'subcategory_id' => $sub_id, 'error' => null];
    }
}
