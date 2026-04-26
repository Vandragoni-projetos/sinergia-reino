<?php
// Aba Funil de Vendas – visão tipo canvas (Produto principal → Upsell → Downsell → Obrigado)

// Carrega funil atual (se existir)
$stmt_funnel = $pdo->prepare("SELECT * FROM product_funnels WHERE main_product_id = ? LIMIT 1");
$stmt_funnel->execute([$id_produto]);
$funnel = $stmt_funnel->fetch(PDO::FETCH_ASSOC) ?: [
    'upsell_product_id' => null,
    'downsell_product_id' => null,
    'is_active' => 0,
];
$upsell_custom = [];
$downsell_custom = [];
if (!empty($funnel['upsell_custom_config'])) {
    $decoded = json_decode($funnel['upsell_custom_config'], true);
    if (is_array($decoded)) $upsell_custom = $decoded;
}
if (!empty($funnel['downsell_custom_config'])) {
    $decoded = json_decode($funnel['downsell_custom_config'], true);
    if (is_array($decoded)) $downsell_custom = $decoded;
}
$offer_theme = [];
if (!empty($funnel['offer_theme'])) {
    $decoded = json_decode($funnel['offer_theme'], true);
    if (is_array($decoded)) $offer_theme = $decoded;
}

// Lista de produtos do infoprodutor para usar como upsell/downsell (mesmo usuário, qualquer gateway)
$stmt_all_products = $pdo->prepare("SELECT id, nome FROM produtos WHERE id != ? AND usuario_id = ? ORDER BY nome ASC");
$stmt_all_products->execute([$id_produto, $usuario_id]);
$lista_produtos_funnel = $stmt_all_products->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-8">
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="workflow" class="w-5 h-5 text-[#32e768]"></i>
            Funil de Vendas
        </h2>
        <p class="text-sm text-gray-400 mb-4">
            Conecte o produto principal a ofertas de <strong>Upsell</strong> e <strong>Downsell</strong>.
            Após a compra, os clientes serão direcionados automaticamente para essas etapas antes da página de obrigado.
        </p>
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border space-y-6">
            <div class="flex items-center gap-3">
                <input type="checkbox" name="funnel_is_active" id="funnel_is_active" value="1" class="form-checkbox"
                       <?php echo !empty($funnel['is_active']) ? 'checked' : ''; ?>>
                <label for="funnel_is_active" class="text-sm text-gray-200">
                    Ativar funil de vendas para este produto
                </label>
            </div>

            <!-- Design / Aparência da página de oferta (cores, logo, textos) -->
            <div class="mt-6 p-5 rounded-xl border-2 border-emerald-500/60 bg-dark-card">
                <h3 class="text-base font-semibold text-emerald-400 mb-1 flex items-center gap-2">
                    <span>Aparência da página de oferta</span>
                    <span class="bg-emerald-500/20 text-emerald-300 text-xs px-2 py-0.5 rounded-full">Cores, logo e títulos</span>
                </h3>
                <p class="text-xs text-gray-400 mb-4">Configure o visual que o cliente vê na tela de upsell/downsell. Cada produto pode ter seu próprio design.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Logo (URL ou envio)</label>
                        <input type="url" name="funnel_theme_logo_url" class="form-input w-full text-sm" placeholder="https://..." value="<?php echo htmlspecialchars($offer_theme['logo_url'] ?? ''); ?>">
                        <input type="file" name="funnel_theme_logo" class="form-input w-full text-sm mt-1" accept="image/*">
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Cor primária (cabeçalho e botão)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" id="funnel_theme_primary_swatch" value="<?php echo htmlspecialchars($offer_theme['primary_color'] ?? '#6366f1'); ?>" class="color-swatch h-10 w-10 rounded-full border-2 border-gray-500 cursor-pointer flex-shrink-0" style="padding: 2px;">
                                <input type="text" name="funnel_theme_primary_color" value="<?php echo htmlspecialchars($offer_theme['primary_color'] ?? '#6366f1'); ?>" maxlength="7" class="color-hex-input w-28 rounded-lg border border-gray-500 bg-gray-800 text-gray-100 px-3 py-2 text-sm font-mono" placeholder="#000000">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Cor secundária (gradiente)</label>
                            <div class="flex items-center gap-3">
                                <input type="color" id="funnel_theme_secondary_swatch" value="<?php echo htmlspecialchars($offer_theme['secondary_color'] ?? '#4f46e5'); ?>" class="color-swatch h-10 w-10 rounded-full border-2 border-gray-500 cursor-pointer flex-shrink-0" style="padding: 2px;">
                                <input type="text" name="funnel_theme_secondary_color" value="<?php echo htmlspecialchars($offer_theme['secondary_color'] ?? '#4f46e5'); ?>" maxlength="7" class="color-hex-input w-28 rounded-lg border border-gray-500 bg-gray-800 text-gray-100 px-3 py-2 text-sm font-mono" placeholder="#000000">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs text-gray-400 mb-1">Cor de fundo da página</label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="funnel_theme_page_bg_swatch" value="<?php echo htmlspecialchars($offer_theme['page_bg'] ?? '#f1f5f9'); ?>" class="color-swatch h-10 w-10 rounded-full border-2 border-gray-500 cursor-pointer flex-shrink-0" style="padding: 2px;">
                        <input type="text" name="funnel_theme_page_bg" value="<?php echo htmlspecialchars($offer_theme['page_bg'] ?? '#f1f5f9'); ?>" maxlength="7" class="color-hex-input w-28 rounded-lg border border-gray-500 bg-gray-800 text-gray-100 px-3 py-2 text-sm font-mono" placeholder="#ffffff">
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-3 rounded border border-blue-500/30 bg-dark-elevated">
                        <p class="text-xs font-medium text-blue-400 mb-2">Cabeçalho — Upsell</p>
                        <input type="text" name="funnel_theme_header_label_upsell" class="form-input w-full text-sm mb-2" placeholder="Ex: Oferta especial" value="<?php echo htmlspecialchars($offer_theme['header_label_upsell'] ?? ''); ?>">
                        <input type="text" name="funnel_theme_header_headline_upsell" class="form-input w-full text-sm" placeholder="Ex: Quer levar isso também?" value="<?php echo htmlspecialchars($offer_theme['header_headline_upsell'] ?? ''); ?>">
                    </div>
                    <div class="p-3 rounded border border-amber-500/30 bg-dark-elevated">
                        <p class="text-xs font-medium text-amber-400 mb-2">Cabeçalho — Downsell</p>
                        <input type="text" name="funnel_theme_header_label_downsell" class="form-input w-full text-sm mb-2" placeholder="Ex: Última chance" value="<?php echo htmlspecialchars($offer_theme['header_label_downsell'] ?? ''); ?>">
                        <input type="text" name="funnel_theme_header_headline_downsell" class="form-input w-full text-sm" placeholder="Ex: Última chance com desconto" value="<?php echo htmlspecialchars($offer_theme['header_headline_downsell'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Canvas do funil -->
            <div class="relative overflow-x-auto">
                <div class="min-w-[920px] flex flex-col gap-8 py-4">
                    <!-- Linha 1: Produto principal -->
                    <div class="flex items-center gap-4">
                        <div class="flex-1"></div>
                        <div class="flex flex-col items-center">
                            <div class="px-6 py-4 rounded-xl border border-[#32e768] bg-dark-card shadow-md">
                                <p class="text-xs uppercase tracking-wide text-[#32e768] mb-1 text-center">Produto principal</p>
                                <p class="text-white font-semibold text-center max-w-xs">
                                    <?php echo htmlspecialchars($produto['nome']); ?>
                                </p>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">Checkout atual</p>
                        </div>
                        <div class="flex-1"></div>
                    </div>

                    <!-- Linha da seta para Upsell -->
                    <div class="flex items-center justify-center">
                        <div class="h-px bg-gradient-to-r from-transparent via-[#32e768] to-transparent w-64 relative">
                            <div class="absolute -top-2 left-1/2 -translate-x-1/2 bg-dark-card px-2 text-xs text-gray-300">
                                Compra aprovada
                            </div>
                        </div>
                    </div>

                    <!-- Linha 2: Upsell -->
                    <div class="flex items-start justify-center gap-12">
                        <div class="flex flex-col items-center">
                            <div class="px-6 py-4 rounded-xl border border-blue-500 bg-dark-card shadow-md w-72">
                                <p class="text-xs uppercase tracking-wide text-blue-400 mb-1 text-center">Upsell</p>
                                <div class="space-y-2">
                                    <select name="funnel_upsell_product_id" class="form-input">
                                        <option value="">Nenhum upsell</option>
                                        <?php foreach ($lista_produtos_funnel as $p): ?>
                                            <option value="<?php echo $p['id']; ?>"
                                                <?php echo (!empty($funnel['upsell_product_id']) && (int)$funnel['upsell_product_id'] === (int)$p['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-gray-400">
                                        Esta oferta é apresentada imediatamente após a compra do produto principal.
                                    </p>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">Se o cliente clicar em “Sim, quero”</p>
                        </div>
                    </div>

                    <!-- Linha 3: Downsell / Obrigado -->
                    <div class="flex items-start justify-center gap-12">
                        <div class="flex flex-col items-center">
                            <div class="px-6 py-4 rounded-xl border border-amber-500 bg-dark-card shadow-md w-72">
                                <p class="text-xs uppercase tracking-wide text-amber-400 mb-1 text-center">Downsell</p>
                                <div class="space-y-2">
                                    <select name="funnel_downsell_product_id" class="form-input">
                                        <option value="">Nenhum downsell</option>
                                        <?php foreach ($lista_produtos_funnel as $p): ?>
                                            <option value="<?php echo $p['id']; ?>"
                                                <?php echo (!empty($funnel['downsell_product_id']) && (int)$funnel['downsell_product_id'] === (int)$p['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-gray-400">
                                        Oferta alternativa mostrada para quem recusar o upsell.
                                    </p>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">Se o cliente recusar o upsell</p>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="px-6 py-4 rounded-xl border border-emerald-500 bg-dark-card shadow-md w-72">
                                <p class="text-xs uppercase tracking-wide text-emerald-400 mb-1 text-center">Página de Obrigado</p>
                                <p class="text-xs text-gray-400 text-center">
                                    Após terminar o fluxo (ou se não houver upsell/downsell), o cliente é enviado para a página de obrigado padrão.
                                </p>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">Fim do funil</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-xs text-gray-500">
                Dica: você pode criar produtos específicos para Upsell/Downsell (ex.: versão PRO, pacote complementar) e selecioná‑los acima.
            </div>

            <!-- Personalização Upsell -->
            <div class="mt-6 p-4 rounded-lg border border-blue-500/50 bg-dark-card">
                <h3 class="text-sm font-semibold text-blue-400 mb-3">Personalizar oferta Upsell</h3>
                <p class="text-xs text-gray-400 mb-4">Opcional: capa, banners e descrição para a tela de oferta. Se vazio, usa os dados do produto selecionado.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Banner cabeçalho (URL ou envio)</label>
                        <input type="text" name="funnel_upsell_banner_header_url" class="form-input w-full text-sm" placeholder="https://... ou uploads/arquivo.png" value="<?php echo htmlspecialchars($upsell_custom['banner_header'] ?? ''); ?>">
                        <input type="file" name="funnel_upsell_banner_header" class="form-input w-full text-sm mt-1" accept="image/*">
                        <p class="text-xs text-gray-500 mt-1">Após enviar arquivo ou preencher o caminho, clique em <strong>Salvar</strong> para aplicar.</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Banner lateral (URL ou envio)</label>
                        <input type="text" name="funnel_upsell_banner_side_url" class="form-input w-full text-sm" placeholder="https://... ou uploads/arquivo.png" value="<?php echo htmlspecialchars($upsell_custom['banner_side'] ?? ''); ?>">
                        <input type="file" name="funnel_upsell_banner_side" class="form-input w-full text-sm mt-1" accept="image/*">
                        <p class="text-xs text-gray-500 mt-1">Após enviar arquivo ou preencher o caminho, clique em <strong>Salvar</strong> para aplicar.</p>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs text-gray-400 mb-1">Capa da oferta (opcional; sobrescreve a capa do produto)</label>
                    <input type="file" name="funnel_upsell_cover" class="form-input w-full text-sm" accept="image/*">
                    <?php if (!empty($upsell_custom['cover_image'])): ?>
                        <span class="text-xs text-gray-500">Atual: <?php echo htmlspecialchars(basename($upsell_custom['cover_image'])); ?></span>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 mt-1">Clique em <strong>Salvar</strong> após escolher o arquivo.</p>
                </div>
                <div class="mt-3">
                    <label class="block text-xs text-gray-400 mb-1">Descrição / copy da oferta (HTML permitido)</label>
                    <textarea name="funnel_upsell_description" class="form-input w-full text-sm" rows="4" placeholder="Texto de vendas da oferta de upsell..."><?php echo htmlspecialchars($upsell_custom['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Personalização Downsell -->
            <div class="mt-6 p-4 rounded-lg border border-amber-500/50 bg-dark-card">
                <h3 class="text-sm font-semibold text-amber-400 mb-3">Personalizar oferta Downsell</h3>
                <p class="text-xs text-gray-400 mb-4">Opcional: capa, banners e descrição para a tela de downsell. Se vazio, usa os dados do produto selecionado.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Banner cabeçalho (URL ou envio)</label>
                        <input type="text" name="funnel_downsell_banner_header_url" class="form-input w-full text-sm" placeholder="https://... ou uploads/arquivo.png" value="<?php echo htmlspecialchars($downsell_custom['banner_header'] ?? ''); ?>">
                        <input type="file" name="funnel_downsell_banner_header" class="form-input w-full text-sm mt-1" accept="image/*">
                        <p class="text-xs text-gray-500 mt-1">Após enviar arquivo ou preencher o caminho, clique em <strong>Salvar</strong> para aplicar.</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Banner lateral (URL ou envio)</label>
                        <input type="text" name="funnel_downsell_banner_side_url" class="form-input w-full text-sm" placeholder="https://... ou uploads/arquivo.png" value="<?php echo htmlspecialchars($downsell_custom['banner_side'] ?? ''); ?>">
                        <input type="file" name="funnel_downsell_banner_side" class="form-input w-full text-sm mt-1" accept="image/*">
                        <p class="text-xs text-gray-500 mt-1">Após enviar arquivo ou preencher o caminho, clique em <strong>Salvar</strong> para aplicar.</p>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs text-gray-400 mb-1">Capa da oferta (opcional)</label>
                    <input type="file" name="funnel_downsell_cover" class="form-input w-full text-sm" accept="image/*">
                    <?php if (!empty($downsell_custom['cover_image'])): ?>
                        <span class="text-xs text-gray-500">Atual: <?php echo htmlspecialchars(basename($downsell_custom['cover_image'])); ?></span>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 mt-1">Clique em <strong>Salvar</strong> após escolher o arquivo.</p>
                </div>
                <div class="mt-3">
                    <label class="block text-xs text-gray-400 mb-1">Descrição / copy da oferta (HTML permitido)</label>
                    <textarea name="funnel_downsell_description" class="form-input w-full text-sm" rows="4" placeholder="Texto de vendas da oferta de downsell..."><?php echo htmlspecialchars($downsell_custom['description'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.color-swatch::-webkit-color-swatch-wrapper { padding: 0; }
.color-swatch::-webkit-color-swatch { border-radius: 50%; border: none; }
.color-swatch::-moz-color-swatch { border-radius: 50%; border: none; }
</style>
<script>
(function() {
    function normalizeHex(v) {
        v = (v || '').trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(v)) return v;
        if (/^[0-9A-Fa-f]{6}$/.test(v)) return '#' + v;
        return null;
    }
    function syncColorPair(swatchId, inputName) {
        var swatch = document.getElementById(swatchId);
        var input = document.querySelector('input[name="' + inputName + '"]');
        if (!swatch || !input) return;
        swatch.addEventListener('input', function() { input.value = swatch.value; });
        swatch.addEventListener('change', function() { input.value = swatch.value; });
        input.addEventListener('input', function() {
            var hex = normalizeHex(input.value);
            if (hex) swatch.value = hex;
        });
        input.addEventListener('blur', function() {
            var hex = normalizeHex(input.value);
            if (hex) { swatch.value = hex; input.value = hex; }
        });
    }
    syncColorPair('funnel_theme_primary_swatch', 'funnel_theme_primary_color');
    syncColorPair('funnel_theme_secondary_swatch', 'funnel_theme_secondary_color');
    syncColorPair('funnel_theme_page_bg_swatch', 'funnel_theme_page_bg');
})();
</script>
