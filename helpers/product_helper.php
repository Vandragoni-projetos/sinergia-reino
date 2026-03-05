<?php
/**
 * Helper para categorias de produto (product_type)
 * Valores internos usados no banco; labels para exibição
 */

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
