<!-- Modal: Novo/Editar Banner -->
<div id="banner-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
    <div class="bg-dark-card rounded-2xl shadow-2xl border border-dark-border max-w-4xl w-full max-h-[90vh] overflow-y-auto custom-scrollbar">
        
        <!-- Header -->
        <div class="bg-dark-elevated px-8 py-5 border-b border-dark-border flex justify-between items-center sticky top-0 z-10">
            <h2 id="banner-modal-title" class="text-xl font-bold text-white flex items-center">
                <i data-lucide="image" class="w-6 h-6 mr-3 text-[#32e768]"></i>
                Novo Banner Publicitário
            </h2>
            <button id="fechar-banner-modal" class="text-gray-400 hover:text-gray-300 transition-colors p-2 rounded-lg hover:bg-dark-elevated">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Form -->
        <form id="banner-form" class="p-8 space-y-6">
            <input type="hidden" id="banner-id" name="id">
            
            <!-- Título (Opcional) -->
            <div>
                <label for="banner-titulo" class="block text-gray-300 text-sm font-semibold mb-2">
                    Título do Banner <span class="text-gray-500 font-normal">(Opcional)</span>
                </label>
                <input type="text" id="banner-titulo" name="titulo" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white placeholder-gray-500" placeholder="Ex: Promoção de Lançamento">
            </div>

            <!-- Tipo do Banner (Badge) -->
            <div id="banner-badge-container">
                <label for="banner-badge" class="block text-gray-300 text-sm font-semibold mb-2">
                    Tipo do Banner
                </label>
                <select id="banner-badge" name="badge_id" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white">
                    <option value="">Carregando...</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Badge exibida no canto do card (ex: 🚀 Novidade)</p>
            </div>

            <!-- Tipo de Banner (Upload ou URL) -->
            <div class="bg-dark-elevated p-6 rounded-xl border border-dark-border">
                <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-4 flex items-center">
                    <i data-lucide="image-plus" class="w-4 h-4 mr-2"></i> Imagem do Banner
                </h3>

                <div class="space-y-4">
                    <!-- Tabs -->
                    <div class="flex gap-2 bg-dark-card rounded-lg p-1">
                        <button type="button" id="tab-upload" class="flex-1 px-4 py-2 rounded-md text-sm font-medium transition-colors bg-[#32e768] text-white">
                            <i data-lucide="upload" class="w-4 h-4 inline mr-2"></i>Upload
                        </button>
                        <button type="button" id="tab-url" class="flex-1 px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-400 hover:text-white">
                            <i data-lucide="link" class="w-4 h-4 inline mr-2"></i>URL Externa
                        </button>
                    </div>

                    <!-- Upload Panel -->
                    <div id="panel-upload" class="space-y-3">
                        <div class="border-2 border-dashed border-dark-border rounded-xl p-8 text-center hover:border-[#32e768]/50 transition-colors">
                            <input type="file" id="banner-upload" name="banner_image" accept=".jpg,.jpeg,.png,.webp,.gif" class="hidden">
                            <div id="upload-placeholder" class="cursor-pointer" onclick="document.getElementById('banner-upload').click()">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-dark-elevated rounded-full mb-4">
                                    <i data-lucide="image" class="w-8 h-8 text-gray-500"></i>
                                </div>
                                <p class="text-white font-medium mb-1">Clique para fazer upload</p>
                                <p class="text-sm text-gray-400">JPG, PNG, WEBP ou GIF (máx 10MB)</p>
                                <p class="text-xs text-gray-500 mt-2">Recomendado: 720x1080px (2:3)</p>
                            </div>
                            <div id="upload-preview" class="hidden">
                                <img id="preview-banner-img" src="" alt="Preview" class="max-h-64 mx-auto rounded-lg shadow-lg mb-4">
                                <p class="text-sm text-gray-400 mb-2" id="upload-filename"></p>
                                <button type="button" onclick="document.getElementById('banner-upload').click()" class="text-[#32e768] text-sm hover:underline">Trocar imagem</button>
                            </div>
                        </div>
                    </div>

                    <!-- URL Panel -->
                    <div id="panel-url" class="space-y-3 hidden">
                        <input type="url" id="banner-url" name="image_url" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white placeholder-gray-500" placeholder="https://exemplo.com/imagem.png">
                        <p class="text-xs text-gray-500">Cole o link direto da imagem (.jpg, .png, .webp ou .gif)</p>
                        
                        <!-- Preview URL -->
                        <div id="url-preview" class="hidden mt-4">
                            <img id="preview-url-img" src="" alt="Preview" class="max-h-64 mx-auto rounded-lg shadow-lg" onerror="this.parentElement.innerHTML='<p class=\'text-red-400 text-sm\'>Não foi possível carregar a imagem</p>'">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Link de Destino (Redirecionamento) -->
            <div>
                <label for="banner-click-url" class="block text-gray-300 text-sm font-semibold mb-2 flex items-center">
                    Link de Destino 
                    <span class="text-gray-500 font-normal ml-2">(Opcional - deixe vazio para banner informativo)</span>
                </label>
                <input type="url" id="banner-click-url" name="click_url" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white placeholder-gray-500" placeholder="https://exemplo.com/oferta">
                <div class="mt-3">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" id="banner-new-tab" name="open_new_tab" class="w-4 h-4 rounded border-dark-border bg-dark-elevated text-[#32e768] focus:ring-[#32e768]/20">
                        <span class="text-sm text-gray-300">Abrir em nova aba</span>
                    </label>
                </div>
            </div>

            <!-- Produto Vinculado (Ocultar banner se cliente já comprou) -->
            <div>
                <label for="banner-product-id" class="block text-gray-300 text-sm font-semibold mb-2 flex items-center">
                    Produto Vinculado
                    <span class="text-gray-500 font-normal ml-2">(Opcional - se o cliente comprar este produto, o banner não será mais exibido)</span>
                </label>
                <select id="banner-product-id" name="product_id" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768]/20 focus:border-[#32e768] transition-all text-white cursor-pointer">
                    <option value="">— Nenhum —</option>
                    <!-- Opções carregadas via JS -->
                </select>
            </div>

            <!-- Configurações de Exibição -->
            <div class="bg-dark-elevated p-6 rounded-xl border border-dark-border">
                <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-4 flex items-center">
                    <i data-lucide="eye" class="w-4 h-4 mr-2"></i> Onde Exibir?
                </h3>
                <div class="space-y-3">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" id="banner-show-products" name="show_in_products_grid" checked class="w-4 h-4 rounded border-dark-border bg-dark-elevated text-[#32e768] focus:ring-[#32e768]/20">
                        <span class="text-sm text-gray-300">No grid de produtos (Infoprodutor)</span>
                    </label>
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" id="banner-show-member-dashboard" name="show_in_member_dashboard" class="w-4 h-4 rounded border-dark-border bg-dark-elevated text-[#32e768] focus:ring-[#32e768]/20">
                        <span class="text-sm text-gray-300">No dashboard do cliente (entre os cursos)</span>
                    </label>
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" id="banner-show-offers" name="show_in_offers_section" class="w-4 h-4 rounded border-dark-border bg-dark-elevated text-[#32e768] focus:ring-[#32e768]/20">
                        <span class="text-sm text-gray-300">Na seção "Ofertas Exclusivas" (dashboard do cliente)</span>
                    </label>
                    <label class="flex items-center space-x-3 cursor-pointer pt-3 border-t border-dark-border">
                        <input type="checkbox" id="banner-active" name="is_active" checked class="w-4 h-4 rounded border-dark-border bg-dark-elevated text-[#32e768] focus:ring-[#32e768]/20">
                        <span class="text-sm text-white font-medium">Banner Ativo</span>
                    </label>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex gap-4 pt-4 border-t border-dark-border">
                <button type="button" id="cancelar-banner" class="flex-1 px-6 py-3 bg-dark-elevated text-gray-300 font-medium rounded-lg hover:bg-dark-card transition-all">
                    Cancelar
                </button>
                <button type="submit" id="salvar-banner" class="flex-1 px-6 py-3 bg-[#32e768] hover:bg-[#28d15e] text-white font-bold rounded-lg shadow-lg hover:shadow-xl transition-all flex items-center justify-center">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i>
                    Salvar Banner
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Gerenciamento do Modal de Banner (IIFE executa ao parsear o script; modal e form já estão no DOM acima)
(function() {
    const modal = document.getElementById('banner-modal');
    const form = document.getElementById('banner-form');
    const bannerUpload = document.getElementById('banner-upload');
    const bannerUrl = document.getElementById('banner-url');
    
    if (!modal || !form) {
        console.error('Modal de banner: elementos não encontrados.');
        return;
    }

    // Imagem já salva no banner (edição): permite salvar checkboxes sem reenviar arquivo
    let existingImagePath = null;
    let existingImageUrl = null;

    let badgesCache = null;
    function carregarBadges() {
        if (badgesCache) return Promise.resolve(badgesCache);
        return fetch('/api/banners_api?action=get_badges', { credentials: 'same-origin' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                badgesCache = (data.success && data.badges) ? data.badges : [];
                return badgesCache;
            })
            .catch(function(e) {
                console.error('Erro ao carregar badges:', e);
                return [];
            });
    }

    function popularDropdownBadges(selectedId) {
        var sel = document.getElementById('banner-badge');
        if (!sel) return Promise.resolve();
        return carregarBadges().then(function(badges) {
            sel.innerHTML = '<option value="">🔔 Aviso (padrão)</option>';
            badges.forEach(function(b) {
                var opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = (b.icon || '') + ' ' + (b.label || b.slug || '');
                sel.appendChild(opt);
            });
            sel.value = selectedId || '';
        });
    }

    function carregarProdutosDropdown(selectedId) {
        var sel = document.getElementById('banner-product-id');
        if (!sel) return Promise.resolve();
        return fetch('/api/banners_api?action=get_products', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                sel.innerHTML = '<option value="">— Nenhum —</option>';
                if (data.success && data.products && data.products.length) {
                    data.products.forEach(function(p) {
                        var opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = (p.nome || 'Produto #' + p.id);
                        sel.appendChild(opt);
                    });
                }
                sel.value = selectedId || '';
            })
            .catch(function() {
                sel.innerHTML = '<option value="">— Nenhum —</option>';
                sel.value = '';
            });
    }

    window.abrirBannerModal = function(bannerId) {
        if (bannerId === undefined) bannerId = null;
        console.log('🎯 abrirBannerModal chamada! Banner ID:', bannerId);
        
        if (bannerId) {
            // Carregar dados do banner para edição
            carregarBanner(bannerId);
        } else {
            // Limpar form para novo banner
            existingImagePath = null;
            existingImageUrl = null;
            form.reset();
            document.getElementById('banner-id').value = '';
            document.getElementById('banner-modal-title').innerHTML = '<i data-lucide="image" class="w-6 h-6 mr-3 text-[#32e768]"></i>Novo Banner Publicitário';
            limparPreview();
            carregarProdutosDropdown('');
            // Popular dropdown de badges inline (evita dependência de closure)
            var selBadge = document.getElementById('banner-badge');
            if (selBadge) {
                selBadge.innerHTML = '<option value="">Carregando...</option>';
                fetch('/api/banners_api?action=get_badges', { credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var badges = (data.success && data.badges) ? data.badges : [];
                        selBadge.innerHTML = '<option value="">🔔 Aviso (padrão)</option>';
                        badges.forEach(function(b) {
                            var opt = document.createElement('option');
                            opt.value = b.id;
                            opt.textContent = (b.icon || '') + ' ' + (b.label || b.slug || '');
                            selBadge.appendChild(opt);
                        });
                        selBadge.value = '';
                    })
                    .catch(function() {
                        selBadge.innerHTML = '<option value="">🔔 Aviso (padrão)</option>';
                        selBadge.value = '';
                    });
            }
        }
        modal.style.display = 'flex';
        
        // Renderizar ícones Lucide
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    };

    // Fechar modal
    function fecharModal() {
        if (modal) modal.style.display = 'none';
        existingImagePath = null;
        existingImageUrl = null;
        if (form) form.reset();
        limparPreview();
    }

    const fecharBtn = document.getElementById('fechar-banner-modal');
    const cancelarBtn = document.getElementById('cancelar-banner');
    
    if (fecharBtn) fecharBtn.onclick = fecharModal;
    if (cancelarBtn) cancelarBtn.onclick = fecharModal;
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) fecharModal();
        });
    }

    // Tabs: Upload vs URL
    const tabUpload = document.getElementById('tab-upload');
    const tabUrl = document.getElementById('tab-url');
    const panelUpload = document.getElementById('panel-upload');
    const panelUrl = document.getElementById('panel-url');

    if (tabUpload && tabUrl && panelUpload && panelUrl) {
        tabUpload.onclick = () => {
            tabUpload.classList.add('bg-[#32e768]', 'text-white');
            tabUpload.classList.remove('text-gray-400');
            tabUrl.classList.remove('bg-[#32e768]', 'text-white');
            tabUrl.classList.add('text-gray-400');
            panelUpload.classList.remove('hidden');
            panelUrl.classList.add('hidden');
            if (bannerUrl) bannerUrl.value = '';
        };

        tabUrl.onclick = () => {
            tabUrl.classList.add('bg-[#32e768]', 'text-white');
            tabUrl.classList.remove('text-gray-400');
            tabUpload.classList.remove('bg-[#32e768]', 'text-white');
            tabUpload.classList.add('text-gray-400');
            panelUrl.classList.remove('hidden');
            panelUpload.classList.add('hidden');
            if (bannerUpload) bannerUpload.value = '';
            limparPreview();
        };
    }

    // Preview de upload
    if (bannerUpload) {
        bannerUpload.onchange = function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const previewImg = document.getElementById('preview-banner-img');
                    const uploadFilename = document.getElementById('upload-filename');
                    const uploadPlaceholder = document.getElementById('upload-placeholder');
                    const uploadPreview = document.getElementById('upload-preview');
                    
                    if (previewImg) previewImg.src = ev.target.result;
                    if (uploadFilename) uploadFilename.textContent = file.name;
                    if (uploadPlaceholder) uploadPlaceholder.classList.add('hidden');
                    if (uploadPreview) uploadPreview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        };
    }

    // Preview de URL
    if (bannerUrl) {
        bannerUrl.onblur = function() {
            const url = bannerUrl.value.trim();
            const previewUrlImg = document.getElementById('preview-url-img');
            const urlPreview = document.getElementById('url-preview');
            
            if (url && (url.endsWith('.jpg') || url.endsWith('.jpeg') || url.endsWith('.png') || url.endsWith('.webp') || url.endsWith('.gif'))) {
                if (previewUrlImg) previewUrlImg.src = url;
                if (urlPreview) urlPreview.classList.remove('hidden');
            } else {
                if (urlPreview) urlPreview.classList.add('hidden');
            }
        };
    }

    function limparPreview() {
        const uploadPlaceholder = document.getElementById('upload-placeholder');
        const uploadPreview = document.getElementById('upload-preview');
        const urlPreview = document.getElementById('url-preview');
        const previewBannerImg = document.getElementById('preview-banner-img');
        const previewUrlImg = document.getElementById('preview-url-img');
        
        if (uploadPlaceholder) uploadPlaceholder.classList.remove('hidden');
        if (uploadPreview) uploadPreview.classList.add('hidden');
        if (urlPreview) urlPreview.classList.add('hidden');
        if (previewBannerImg) previewBannerImg.src = '';
        if (previewUrlImg) previewUrlImg.src = '';
    }

    // Carregar banner para edição
    async function carregarBanner(bannerId) {
        try {
            const res = await fetch(`/api/banners_api?action=list`, { credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                const banner = data.banners.find(b => b.id == bannerId);
                if (banner) {
                    document.getElementById('banner-id').value = banner.id;
                    document.getElementById('banner-titulo').value = banner.titulo || '';
                    document.getElementById('banner-click-url').value = banner.click_url || '';
                    document.getElementById('banner-new-tab').checked = banner.open_new_tab == 1;
                    document.getElementById('banner-active').checked = banner.is_active == 1;
                    document.getElementById('banner-show-products').checked = banner.show_in_products_grid == 1;
                    document.getElementById('banner-show-member-dashboard').checked = banner.show_in_member_dashboard == 1;
                    document.getElementById('banner-show-offers').checked = banner.show_in_offers_section == 1;
                    var productSel = document.getElementById('banner-product-id');
                    if (productSel) productSel.value = banner.product_id || '';

                    popularDropdownBadges(banner.badge_id || '');
                    carregarProdutosDropdown(banner.product_id || '');

                    existingImagePath = banner.image_path || null;
                    existingImageUrl = banner.image_url || null;

                    if (banner.image_url) {
                        tabUrl.click();
                        bannerUrl.value = banner.image_url;
                        document.getElementById('preview-url-img').src = banner.image_url;
                        document.getElementById('url-preview').classList.remove('hidden');
                    } else if (banner.image_path) {
                        // Mostrar preview do upload existente (sem exigir novo arquivo)
                        document.getElementById('preview-banner-img').src = '/' + banner.image_path;
                        document.getElementById('upload-placeholder').classList.add('hidden');
                        document.getElementById('upload-preview').classList.remove('hidden');
                    }

                    document.getElementById('banner-modal-title').innerHTML = '<i data-lucide="edit-3" class="w-6 h-6 mr-3 text-[#32e768]"></i>Editar Banner';
                }
            }
        } catch (e) {
            console.error('Erro ao carregar banner:', e);
        }
    }

    // Submit do form
    form.onsubmit = async function(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('salvar-banner');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 mr-2 animate-spin"></i>Salvando...';
        lucide.createIcons();

        try {
            let image_path = null;
            let image_url = bannerUrl.value.trim() || null;
            const isEdit = !!(document.getElementById('banner-id').value);

            // Se tem upload novo, fazer upload primeiro
            if (bannerUpload.files.length > 0) {
                const formData = new FormData();
                formData.append('banner_image', bannerUpload.files[0]);
                
                const uploadRes = await fetch('/api/banner_upload.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                const uploadData = await uploadRes.json();
                
                if (!uploadData.success) {
                    throw new Error(uploadData.error || 'Erro no upload');
                }
                
                image_path = uploadData.image_path;
                image_url = null; // Se fez upload, anula URL externa
            } else if (image_url) {
                // URL externa informada: substitui upload anterior
                image_path = null;
            } else if (isEdit && (existingImagePath || existingImageUrl)) {
                // Edição sem trocar imagem: reutiliza a já salva (checkboxes, link, badge etc.)
                image_path = existingImagePath;
                image_url = existingImageUrl;
            }

            // Validação: precisa de imagem (nova OU já existente na edição)
            if (!image_path && !image_url) {
                // Destaca a seção de imagem para o usuário
                const imagemSection = document.querySelector('.bg-dark-elevated.p-6.rounded-xl');
                if (imagemSection) {
                    imagemSection.classList.add('border-red-500');
                    imagemSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => imagemSection.classList.remove('border-red-500'), 3000);
                }
                throw new Error('⚠️ IMAGEM OBRIGATÓRIA\n\nPor favor, forneça uma imagem para o banner:\n• Faça upload de um arquivo (aba "Upload"), OU\n• Cole uma URL de imagem externa (aba "URL Externa")');
            }

            // Preparar dados do banner
            const badgeSel = document.getElementById('banner-badge');
            const badgeIdVal = badgeSel && badgeSel.value ? badgeSel.value : '';
            const productSel = document.getElementById('banner-product-id');
            const productIdVal = productSel && productSel.value ? parseInt(productSel.value, 10) : null;
            const bannerData = {
                id: document.getElementById('banner-id').value || null,
                titulo: document.getElementById('banner-titulo').value.trim() || null,
                badge_id: badgeIdVal ? parseInt(badgeIdVal, 10) : null,
                image_path: image_path,
                image_url: image_url,
                click_url: document.getElementById('banner-click-url').value.trim() || null,
                open_new_tab: document.getElementById('banner-new-tab').checked ? 1 : 0,
                is_active: document.getElementById('banner-active').checked ? 1 : 0,
                show_in_products_grid: document.getElementById('banner-show-products').checked ? 1 : 0,
                show_in_member_dashboard: document.getElementById('banner-show-member-dashboard').checked ? 1 : 0,
                show_in_offers_section: document.getElementById('banner-show-offers').checked ? 1 : 0,
                product_id: productIdVal || null
            };

            // Criar ou atualizar banner
            const action = bannerData.id ? 'update' : 'create';
            const res = await fetch(`/api/banners_api?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(bannerData),
                credentials: 'same-origin'
            });

            const data = await res.json();
            
            if (data.success) {
                fecharModal();
                window.location.reload(); // Recarregar para mostrar novo banner no grid
            } else {
                throw new Error(data.error || 'Erro ao salvar banner');
            }

        } catch (err) {
            alert('Erro: ' + err.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            lucide.createIcons();
        }
    };
    
    console.log('✅ Modal de Banner carregado. abrirBannerModal:', typeof window.abrirBannerModal);
})();
</script>
