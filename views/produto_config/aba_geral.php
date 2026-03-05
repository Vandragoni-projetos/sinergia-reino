<?php
// Aba Geral - Configurações básicas do produto
?>

<div class="space-y-6">
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="package" class="w-5 h-5 text-[#32e768]"></i>
            Informações Básicas
        </h2>
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border space-y-4">
            <div>
                <label for="nome" class="block text-gray-300 text-sm font-semibold mb-2">Nome do Produto</label>
                <input type="text" id="nome" name="nome" class="form-input" value="<?php echo htmlspecialchars($produto['nome']); ?>" required>
            </div>
            <div>
                <label for="descricao" class="block text-gray-300 text-sm font-semibold mb-2">Descrição</label>
                <textarea id="descricao" name="descricao" rows="4" class="form-input" placeholder="Descreva os benefícios do seu produto..."><?php echo htmlspecialchars($produto['descricao'] ?? ''); ?></textarea>
            </div>
            
            <!-- Toggle Produto Grátis (OFF = cinza, ON = verde) -->
            <div class="bg-dark-card p-4 rounded-lg border border-dark-border">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="text-gray-300 text-sm font-semibold flex items-center gap-2">
                            <i data-lucide="gift" class="w-4 h-4 text-[#32e768]"></i>
                            Produto Grátis
                        </label>
                        <p class="text-xs text-gray-400 mt-1">Ofereça este produto gratuitamente. O cliente só precisará preencher os dados para receber.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_free" id="is_free" value="1" class="sr-only peer" <?php echo (!empty($produto['is_free']) && $produto['is_free'] == 1) ? 'checked' : ''; ?> onchange="togglePriceFields(); updateIsFreeToggle(this);">
                        <div id="is_free_track" class="w-11 h-6 rounded-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-focus:outline-none peer peer-checked:after:translate-x-full peer-checked:after:border-white" style="background-color: <?php echo (!empty($produto['is_free']) && $produto['is_free'] == 1) ? '#22c55e' : '#4b5563'; ?>;"></div>
                    </label>
                </div>
            </div>
            
            <!-- Toggle Produto Vitrine (apenas para área de membros) -->
            <div id="showcase-container" class="bg-gradient-to-r from-purple-900/20 to-indigo-900/20 p-4 rounded-lg border border-purple-500/30" style="display: <?php echo (($produto['tipo_entrega'] ?? '') === 'area_membros') ? 'block' : 'none'; ?>;">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="text-gray-300 text-sm font-semibold flex items-center gap-2">
                            <i data-lucide="star" class="w-4 h-4 text-purple-400"></i>
                            Produto Vitrine
                            <span class="bg-purple-500/20 text-purple-300 text-xs px-2 py-0.5 rounded-full">NOVO</span>
                        </label>
                        <p class="text-xs text-gray-400 mt-1">Usuários que criarem conta grátis terão acesso automático a este produto. Apenas um produto pode ser vitrine.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_showcase" id="is_showcase" value="1" class="sr-only peer" <?php echo (!empty($produto['is_showcase']) && $produto['is_showcase'] == 1) ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-500"></div>
                    </label>
                </div>
                <?php 
                // Verifica se já existe outro produto vitrine
                $stmt_showcase = $pdo->prepare("SELECT id, nome FROM produtos WHERE is_showcase = 1 AND id != ? LIMIT 1");
                $stmt_showcase->execute([$produto['id']]);
                $outro_vitrine = $stmt_showcase->fetch(PDO::FETCH_ASSOC);
                if ($outro_vitrine): 
                ?>
                <div class="mt-3 p-3 bg-yellow-900/20 border border-yellow-500/30 rounded-lg">
                    <p class="text-xs text-yellow-300 flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                        <span>Atenção: O produto "<strong><?php echo htmlspecialchars($outro_vitrine['nome']); ?></strong>" já está marcado como vitrine. Ao ativar aqui, ele será desmarcado.</span>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="price-fields-container" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="<?php echo (!empty($produto['is_free']) && $produto['is_free'] == 1) ? 'display: none;' : ''; ?>">
                <div>
                    <label for="preco" class="block text-gray-300 text-sm font-semibold mb-2">Preço (R$)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 font-bold"></span>
                        <input type="number" step="0.01" id="preco" name="preco" class="form-input pl-10" value="<?php echo htmlspecialchars($produto['preco']); ?>" <?php echo (!empty($produto['is_free']) && $produto['is_free'] == 1) ? '' : 'required'; ?>>
                    </div>
                </div>
                <div>
                    <label for="preco_anterior" class="block text-gray-300 text-sm font-semibold mb-2">Preço Anterior (De)</label>
                    <input type="text" id="preco_anterior" name="preco_anterior" class="form-input" placeholder="Ex: 99,90" value="<?php echo !empty($produto['preco_anterior']) ? htmlspecialchars(number_format($produto['preco_anterior'], 2, ',', '.')) : ''; ?>">
                    <p class="text-xs text-gray-400 mt-1">Deixe em branco para não exibir o preço cortado.</p>
                </div>
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="image" class="w-5 h-5 text-[#32e768]"></i>
            Capa do Produto
        </h2>
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border space-y-4">
            <div class="relative group">
                <div class="w-full h-64 bg-dark-card rounded-xl overflow-hidden border-2 border-dark-border border-dashed flex items-center justify-center relative">
                    <?php
                    $foto_src = !empty($produto['foto']) ? resolve_product_image_url($produto['foto'], $upload_dir ?? 'uploads/') : '';
                    ?>
                    <?php if (!empty($foto_src)): ?>
                        <img src="<?php echo htmlspecialchars($foto_src); ?>" id="preview-img" class="absolute inset-0 w-full h-full object-cover">
                    <?php else: ?>
                        <img id="preview-img" class="absolute inset-0 w-full h-full object-cover hidden">
                        <div id="placeholder-img" class="text-center p-4">
                            <i data-lucide="image" class="w-12 h-12 text-gray-500 mx-auto mb-2"></i>
                            <p class="text-sm text-gray-400">Nenhuma imagem selecionada</p>
                        </div>
                    <?php endif; ?>
                    
                    <label for="foto" class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all duration-300 flex items-center justify-center cursor-pointer">
                        <span class="bg-dark-card text-white px-4 py-2 rounded-full shadow-lg font-medium text-sm transform scale-90 opacity-0 group-hover:scale-100 group-hover:opacity-100 transition-all">
                            <i data-lucide="camera" class="w-4 h-4 inline mr-1"></i> Alterar Capa
                        </span>
                    </label>
                </div>
                <input type="file" id="foto" name="foto" class="hidden" accept="image/png, image/jpeg, image/webp" onchange="previewImage(this)">
            </div>
            <div>
                <label for="foto_url_externa" class="block text-gray-300 text-sm font-medium mb-2">
                    <i data-lucide="link" class="w-4 h-4 inline mr-1"></i> Ou use URL externa (WordPress, CDN, etc.)
                </label>
                <input type="url" id="foto_url_externa" name="foto_url_externa" class="form-input" placeholder="https://seusite.com/imagem.jpg"
                       value="<?php echo (!empty($produto['foto']) && filter_var($produto['foto'], FILTER_VALIDATE_URL)) ? htmlspecialchars($produto['foto']) : ''; ?>"
                       oninput="previewImageFromUrl(this.value)">
                <p class="text-xs text-gray-400 mt-1">Cole a URL da imagem. Se preenchido, substitui o upload e evita perda de imagens no deploy.</p>
            </div>
            <p class="text-xs text-gray-400 text-center">Recomendado: 800x800px (JPG/PNG/WebP). Upload ou URL externa.</p>
        </div>
    </div>

    <!-- TAG/Categoria do Produto -->
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="tag" class="w-5 h-5 text-[#32e768]"></i>
            TAG / Categoria
        </h2>
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="product_type" class="block text-gray-300 text-sm font-semibold mb-2">Tipo/Categoria</label>
                    <select id="product_type" name="product_type" class="form-input cursor-pointer">
                        <option value="">— Nenhum —</option>
                        <?php
                        $pt_current = $produto['product_type'] ?? '';
                        foreach (getProductTypeOptions() as $group => $items):
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
                    <input type="text" id="product_tagline" name="product_tagline" maxlength="40" class="form-input" placeholder="Ex: Conteúdo para revenda, Quiz interativo" value="<?php echo htmlspecialchars($produto['product_tagline'] ?? ''); ?>">
                    <p class="text-xs text-gray-400 mt-1">Exibido abaixo do título no card. Máx. 40 caracteres.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- URL da Página de Vendas -->
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="external-link" class="w-5 h-5 text-[#32e768]"></i>
            URL da Página de Vendas
        </h2>
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border">
            <div>
                <label for="sales_page_url" class="block text-gray-300 text-sm font-semibold mb-2">URL da Página de Vendas</label>
                <input type="url" id="sales_page_url" name="sales_page_url" class="form-input" placeholder="https://seusite.com/pagina-de-vendas" value="<?php echo htmlspecialchars($produto['sales_page_url'] ?? ''); ?>">
                <p class="text-xs text-gray-400 mt-1">Se preenchida, o botão do card será "Saiba Mais" e direcionará para esta página. Se estiver vazio, o comportamento continua sendo checkout direto.</p>
                <p id="sales_page_url_error" class="text-xs text-red-400 mt-1 hidden">A URL deve começar com http:// ou https://</p>
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="truck" class="w-5 h-5 text-[#32e768]"></i>
            Configuração de Entrega
        </h2>
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border space-y-4">
            <div>
                <label for="tipo_entrega" class="block text-gray-300 text-sm font-medium mb-2">Como o cliente receberá o produto?</label>
                <select id="tipo_entrega" name="tipo_entrega" class="form-input cursor-pointer" onchange="toggleEntregaFields()">
                    <option value="link" <?php echo (($produto['tipo_entrega'] ?? 'link') == 'link') ? 'selected' : ''; ?>>🔗 Link Externo (Google Drive, Notion, etc)</option>
                    <option value="email_pdf" <?php echo (($produto['tipo_entrega'] ?? '') == 'email_pdf') ? 'selected' : ''; ?>>📄 Arquivo PDF (Anexo no E-mail)</option>
                    <option value="area_membros" <?php echo (($produto['tipo_entrega'] ?? '') == 'area_membros') ? 'selected' : ''; ?>>🔐 Área de Membros Interna</option>
                </select>
            </div>

            <div id="entrega-fields-container">
                <div id="entrega-link-container" class="animate-fade-in-down" style="display: <?php echo (($produto['tipo_entrega'] ?? '') === 'link') ? 'block' : 'none'; ?>;">
                    <label for="conteudo_entrega_link" class="block text-gray-300 text-sm font-medium mb-2">URL de Acesso</label>
                    <input type="url" id="conteudo_entrega_link" name="conteudo_entrega_link" class="form-input" placeholder="https://" value="<?php echo ($produto['tipo_entrega'] ?? '') === 'link' ? htmlspecialchars($produto['conteudo_entrega'] ?? '') : ''; ?>">
                </div>

                <div id="entrega-pdf-container" class="animate-fade-in-down" style="display: <?php echo (($produto['tipo_entrega'] ?? '') === 'email_pdf') ? 'block' : 'none'; ?>;">
                    <label class="block text-gray-300 text-sm font-medium mb-2">Upload do Arquivo PDF</label>
                    <?php if (($produto['tipo_entrega'] ?? '') == 'email_pdf' && !empty($produto['conteudo_entrega'])): ?>
                        <div class="flex items-center space-x-3 mb-3 p-3 bg-dark-card border border-dark-border rounded-lg shadow-sm">
                            <div class="bg-red-900/30 p-2 rounded-lg"><i data-lucide="file-text" class="w-5 h-5 text-red-400"></i></div>
                            <div class="flex-1 truncate">
                                <p class="text-xs text-gray-400">Arquivo Atual:</p>
                                <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($produto['conteudo_entrega']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dark-border border-dashed rounded-lg cursor-pointer bg-dark-card hover:bg-dark-elevated transition-colors">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i data-lucide="upload-cloud" class="w-8 h-8 text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-400"><span class="font-semibold">Clique para enviar</span> ou arraste</p>
                            <p class="text-xs text-gray-400">PDF (MAX. 10MB)</p>
                        </div>
                        <input type="file" id="conteudo_entrega_pdf" name="conteudo_entrega_pdf" class="hidden" accept="application/pdf">
                    </label>
                    <div id="pdf-file-name" class="mt-2 text-sm text-gray-400 font-medium text-center hidden"></div>
                </div>

                <div id="entrega-membros-container" class="animate-fade-in-down" style="display: <?php echo (($produto['tipo_entrega'] ?? '') === 'area_membros') ? 'block' : 'none'; ?>;">
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

    <?php 
    // Inclui helper do master
    require_once __DIR__ . '/../../helpers/master_helper.php';
    
    if (isMasterPanel()): ?>
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="key" class="w-5 h-5 text-[#32e768]"></i>
            Geração de Licenças
        </h2>
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border">
            <div class="flex items-center justify-between">
                <div>
                    <label class="text-gray-300 text-sm font-semibold">Este produto permite gerar licenças GatewayPro?</label>
                    <p class="text-xs text-gray-400 mt-1">Alunos que comprarem este produto poderão gerar chaves de ativação na área de membros.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="gera_licenca" value="1" class="sr-only peer" <?php echo (!empty($produto['gera_licenca']) && $produto['gera_licenca'] == 1) ? 'checked' : ''; ?>>
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                </label>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('preview-img');
            const placeholder = document.getElementById('placeholder-img');
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function previewImageFromUrl(url) {
    const preview = document.getElementById('preview-img');
    const placeholder = document.getElementById('placeholder-img');
    const trimmed = (url || '').trim();
    if (trimmed && (trimmed.startsWith('http://') || trimmed.startsWith('https://'))) {
        preview.src = trimmed;
        preview.classList.remove('hidden');
        if (placeholder) placeholder.classList.add('hidden');
    } else if (!document.getElementById('foto')?.files?.length) {
        preview.classList.add('hidden');
        preview.src = '';
        if (placeholder) placeholder.classList.remove('hidden');
    }
}

function toggleEntregaFields() {
    const tipoEntregaSelect = document.getElementById('tipo_entrega');
    const linkContainer = document.getElementById('entrega-link-container');
    const pdfContainer = document.getElementById('entrega-pdf-container');
    const membrosContainer = document.getElementById('entrega-membros-container');
    const showcaseContainer = document.getElementById('showcase-container');
    
    const selectedValue = tipoEntregaSelect.value;

    linkContainer.style.display = 'none';
    pdfContainer.style.display = 'none';
    membrosContainer.style.display = 'none';
    if (showcaseContainer) showcaseContainer.style.display = 'none';

    if (selectedValue === 'link') {
        linkContainer.style.display = 'block';
    } else if (selectedValue === 'email_pdf') {
        pdfContainer.style.display = 'block';
    } else if (selectedValue === 'area_membros') {
        membrosContainer.style.display = 'block';
        if (showcaseContainer) showcaseContainer.style.display = 'block';
    }
}

function togglePriceFields() {
    const isFreeCheckbox = document.getElementById('is_free');
    const priceFieldsContainer = document.getElementById('price-fields-container');
    const precoInput = document.getElementById('preco');
    
    if (isFreeCheckbox.checked) {
        priceFieldsContainer.style.display = 'none';
        precoInput.removeAttribute('required');
        precoInput.value = '0';
    } else {
        priceFieldsContainer.style.display = 'grid';
        precoInput.setAttribute('required', 'required');
    }
}

function updateIsFreeToggle(checkbox) {
    var track = document.getElementById('is_free_track');
    if (track) track.style.backgroundColor = checkbox.checked ? '#22c55e' : '#4b5563';
}

document.getElementById('conteudo_entrega_pdf')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0] ? e.target.files[0].name : '';
    const display = document.getElementById('pdf-file-name');
    if (fileName && display) {
        display.textContent = 'Arquivo selecionado: ' + fileName;
        display.classList.remove('hidden');
    } else if (display) {
        display.classList.add('hidden');
    }
});

// Validação da URL da Página de Vendas
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form[action*="produto_config"]');
    var salesUrlInput = document.getElementById('sales_page_url');
    var salesUrlError = document.getElementById('sales_page_url_error');
    if (form && salesUrlInput && salesUrlError) {
        form.addEventListener('submit', function(e) {
            var val = (salesUrlInput.value || '').trim();
            if (val && !/^https?:\/\//i.test(val)) {
                e.preventDefault();
                salesUrlError.classList.remove('hidden');
                salesUrlInput.focus();
                return false;
            }
            salesUrlError.classList.add('hidden');
        });
        salesUrlInput.addEventListener('input', function() {
            salesUrlError.classList.add('hidden');
        });
    }
});

// Inicializa o estado dos campos de preço e do toggle ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    togglePriceFields();
    var isFree = document.getElementById('is_free');
    if (isFree) updateIsFreeToggle(isFree);
});
</script>

