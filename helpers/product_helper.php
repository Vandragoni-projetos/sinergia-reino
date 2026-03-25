<?php
/**
 * Helper para categorias de produto (product_type)
 * Valores internos usados no banco; labels para exibição
 * Suporta categorias customizadas por infoprodutor (product_type_categories)
 */

if (!function_exists('getProductTypeOptionsForUser')) {
    /**
     * Retorna opções de categorias para um infoprodutor.
     * Se tiver categorias customizadas no BD, usa essas; senão usa as padrão.
     * @param int|null $usuario_id ID do infoprodutor (null = usa padrão)
     * @return array [group_name => [value => label], ...]
     */
    function getProductTypeOptionsForUser($usuario_id = null) {
        global $pdo;
        if ($usuario_id !== null && $usuario_id > 0 && isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT group_name, value, label, icon FROM product_type_categories WHERE usuario_id = ? ORDER BY group_name ASC, ordem ASC, label ASC");
                $stmt->execute([$usuario_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $opts = [];
                    foreach ($rows as $r) {
                        $group = $r['group_name'];
                        $label = !empty($r['icon']) ? ($r['icon'] . ' ' . $r['label']) : $r['label'];
                        $opts[$group][$r['value']] = $label;
                    }
                    return $opts;
                }
            } catch (PDOException $e) {
                error_log("product_helper: " . $e->getMessage());
            }
        }
        return getProductTypeOptions();
    }
}

if (!function_exists('getValidProductTypesForUser')) {
    function getValidProductTypesForUser($usuario_id = null) {
        $opts = getProductTypeOptionsForUser($usuario_id);
        $types = [];
        foreach ($opts as $group => $items) {
            $types = array_merge($types, array_keys($items));
        }
        return $types;
    }
}

if (!function_exists('getProductTypeIconsForUser')) {
    function getProductTypeIconsForUser($usuario_id = null) {
        global $pdo;
        $icons = getProductTypeIcons();
        if ($usuario_id !== null && $usuario_id > 0 && isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT value, icon FROM product_type_categories WHERE usuario_id = ? AND icon IS NOT NULL AND icon != ''");
                $stmt->execute([$usuario_id]);
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $icons[$r['value']] = $r['icon'];
                }
            } catch (PDOException $e) {}
        }
        return $icons;
    }
}

if (!function_exists('getProductTypeOptions')) {
    function getProductTypeOptions() {
        return [
            'Produtos Digitais' => [
                'ADS' => '📣 ADS',
                'E_BOOKS' => '📚 E-books',
                'IMAGENS' => '🖼️ Imagens',
                'LIVROS_COLORIR' => '🎨 Livros de Colorir',
                'PACKS' => '📦 Pack\'s',
                'PLR' => '🧩 PLR',
                'QUIZ' => '🧠 Quiz',
            ],
            'Ferramentas' => [
                'APP' => '📱 Apps',
                'AUTOMACAO' => '⚙️ Automação',
                'IA' => '🤖 IA',
                'PROGRAMACAO' => '💻 Programação',
                'SOFTWARE' => '🖥️ Software',
            ],
            'Criativos' => [
                'DESIGN' => '✨ Design',
                'MARKETING' => '📊 Marketing',
                'REDES_SOCIAIS' => '📱 Redes Sociais',
                'WEB' => '🌐 Web',
                'FUNIL' => '🧲 Funil',
            ],
            'Nichos' => [
                'CRISTAO' => '✝️ Cristão',
                'KIDS' => '👶 Kids',
                'PAPELARIA' => '📝 Papelaria',
                'PROFISSOES' => '💼 Profissões',
            ],
            'Destaques' => [
                'AFILIADOS' => '🤝 Afiliados',
                'LANCAMENTOS' => '🚀 Lançamentos',
                'PARCEIROS' => '🤲 Parceiros',
            ],
        ];
    }
}

if (!function_exists('getValidProductTypes')) {
    function getValidProductTypes() {
        $opts = getProductTypeOptions();
        $types = [];
        foreach ($opts as $group => $items) {
            $types = array_merge($types, array_keys($items));
        }
        return $types;
    }
}

if (!function_exists('getProductTypeIcons')) {
    function getProductTypeIcons() {
        return [
            'ADS' => '📣', 'E_BOOKS' => '📚', 'IMAGENS' => '🖼️', 'LIVROS_COLORIR' => '🎨', 'PACKS' => '📦', 'PLR' => '🧩', 'QUIZ' => '🧠',
            'APP' => '📱', 'AUTOMACAO' => '⚙️', 'IA' => '🤖', 'PROGRAMACAO' => '💻', 'SOFTWARE' => '🖥️',
            'DESIGN' => '✨', 'MARKETING' => '📊', 'REDES_SOCIAIS' => '📱', 'WEB' => '🌐', 'FUNIL' => '🧲',
            'CRISTAO' => '✝️', 'KIDS' => '👶', 'PAPELARIA' => '📝', 'PROFISSOES' => '💼',
            'AFILIADOS' => '🤝', 'LANCAMENTOS' => '🚀', 'PARCEIROS' => '🤲',
        ];
    }
}

if (!function_exists('db_table_has_column')) {
    /**
     * Verifica se a coluna existe (information_schema; fallback SHOW COLUMNS + fetch — rowCount() em SELECT no MySQL é pouco confiável).
     */
    function db_table_has_column(PDO $pdo, string $table, string $column): bool {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);
            if ($st->fetchColumn()) {
                return true;
            }
        } catch (Throwable $e) {
            // information_schema pode estar restrito em alguns hostings
        }
        try {
            $tbl = str_replace('`', '``', $table);
            $q = $pdo->query('SHOW COLUMNS FROM `' . $tbl . '` LIKE ' . $pdo->quote($column));
            return $q && $q->fetch(PDO::FETCH_NUM) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
}
