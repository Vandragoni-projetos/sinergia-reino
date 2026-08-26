<?php
$usuario_id = $_SESSION['id'] ?? 0;
if ($usuario_id === 0) {
    header('location: /login');
    exit;
}
?>

<div class="container mx-auto max-w-6xl">
    <div class="flex items-center mb-6">
        <a href="/index?pagina=produtos" class="text-[#32e768] hover:text-[#28d15e] mr-4 transition-colors">
            <i data-lucide="arrow-left-circle" class="w-8 h-8"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-white">Categorias de Produto</h1>
            <p class="text-gray-400">Organize seus produtos por categoria principal e subcategoria (taxonomia temática).</p>
        </div>
    </div>

    <!-- Abas -->
    <div class="flex gap-2 mb-6 border-b border-dark-border pb-1">
        <button type="button" id="tab-main" class="taxonomy-tab px-5 py-2.5 text-sm font-semibold rounded-t-lg border-b-2 border-[#32e768] text-[#32e768] bg-dark-elevated/50">
            Categorias Principais
        </button>
        <button type="button" id="tab-sub" class="taxonomy-tab px-5 py-2.5 text-sm font-semibold rounded-t-lg border-b-2 border-transparent text-gray-400 hover:text-white transition-colors">
            Subcategorias
        </button>
    </div>

    <!-- ========== CATEGORIAS PRINCIPAIS ========== -->
    <section id="panel-main" class="bg-dark-card p-6 md:p-8 rounded-2xl shadow-md border border-dark-border mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h2 class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="folder-tree" class="w-6 h-6 text-[#32e768]"></i>
                Categorias Principais
            </h2>
            <button type="button" id="btn-new-main" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-bold py-2.5 px-5 rounded-xl flex items-center justify-center gap-2 transition">
                <i data-lucide="plus" class="w-5 h-5"></i>
                Nova Categoria
            </button>
        </div>

        <div id="main-loading" class="text-center py-12 text-gray-400">
            <svg class="animate-spin h-8 w-8 text-[#32e768] mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
            </svg>
            <p class="mt-4 font-medium">Carregando categorias...</p>
        </div>

        <div id="main-empty" class="text-center py-12 text-gray-400" style="display:none;">
            <i data-lucide="folder-open" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
            <p class="text-lg font-medium text-white">Nenhuma categoria principal cadastrada.</p>
            <p class="text-sm mt-1 mb-4">Crie categorias para agrupar seus produtos por tema.</p>
            <button type="button" id="btn-first-main" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-bold py-2.5 px-5 rounded-xl inline-flex items-center gap-2 transition">
                <i data-lucide="plus" class="w-5 h-5"></i>
                Criar primeira categoria
            </button>
        </div>

        <div id="main-table-wrap" class="overflow-x-auto" style="display:none;">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-dark-border text-gray-400 uppercase text-xs tracking-wide">
                        <th class="py-3 pr-4 font-semibold">Nome</th>
                        <th class="py-3 px-4 font-semibold w-24 text-center">Ordem</th>
                        <th class="py-3 px-4 font-semibold w-28 text-center">Status</th>
                        <th class="py-3 pl-4 font-semibold w-40 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody id="main-table-body" class="divide-y divide-dark-border"></tbody>
            </table>
        </div>
    </section>

    <!-- ========== SUBCATEGORIAS ========== -->
    <section id="panel-sub" class="bg-dark-card p-6 md:p-8 rounded-2xl shadow-md border border-dark-border mb-8" style="display:none;">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
            <h2 class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="layers" class="w-6 h-6 text-[#32e768]"></i>
                Subcategorias
            </h2>
            <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                <div class="flex items-center gap-2">
                    <label for="filter-main-category" class="text-sm text-gray-400 whitespace-nowrap">Categoria Principal:</label>
                    <select id="filter-main-category" class="px-3 py-2 bg-dark-elevated border border-dark-border rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#32e768] min-w-[180px]">
                        <option value="">Todas</option>
                    </select>
                </div>
                <button type="button" id="btn-new-sub" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-bold py-2.5 px-5 rounded-xl flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                    Nova Subcategoria
                </button>
            </div>
        </div>

        <div id="sub-no-main-hint" class="bg-blue-900/20 border border-blue-500/30 text-blue-300 p-4 rounded-lg text-sm mb-4" style="display:none;">
            Cadastre ao menos uma categoria principal antes de criar subcategorias.
        </div>

        <div id="sub-loading" class="text-center py-12 text-gray-400">
            <svg class="animate-spin h-8 w-8 text-[#32e768] mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
            </svg>
            <p class="mt-4 font-medium">Carregando subcategorias...</p>
        </div>

        <div id="sub-empty" class="text-center py-12 text-gray-400" style="display:none;">
            <i data-lucide="layers" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
            <p class="text-lg font-medium text-white">Nenhuma subcategoria cadastrada.</p>
            <p class="text-sm mt-1">Use o botão acima para adicionar subcategorias às suas categorias principais.</p>
        </div>

        <div id="sub-table-wrap" class="overflow-x-auto" style="display:none;">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-dark-border text-gray-400 uppercase text-xs tracking-wide">
                        <th class="py-3 pr-4 font-semibold">Categoria Principal</th>
                        <th class="py-3 px-4 font-semibold">Subcategoria</th>
                        <th class="py-3 px-4 font-semibold w-24 text-center">Ordem</th>
                        <th class="py-3 px-4 font-semibold w-28 text-center">Status</th>
                        <th class="py-3 pl-4 font-semibold w-40 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody id="sub-table-body" class="divide-y divide-dark-border"></tbody>
            </table>
        </div>
    </section>
</div>

<!-- Modal Categoria Principal -->
<div id="modal-main" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center p-4">
    <div class="bg-dark-card rounded-2xl border border-dark-border w-full max-w-md">
        <div class="p-6 border-b border-dark-border">
            <h3 id="modal-main-title" class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="folder-tree" class="w-6 h-6 text-[#32e768]"></i>
                <span>Nova Categoria Principal</span>
            </h3>
        </div>
        <form id="form-main" class="p-6 space-y-4">
            <input type="hidden" id="main_id" value="">
            <div>
                <label for="main_nome" class="block text-gray-300 text-sm font-semibold mb-2">Nome *</label>
                <input type="text" id="main_nome" required maxlength="120" placeholder="Ex: Marketing Digital"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div>
                <label for="main_ordem" class="block text-gray-300 text-sm font-semibold mb-2">Ordem</label>
                <input type="number" id="main_ordem" value="0" min="0" step="1"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="main_ativo" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-dark-border peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#32e768]/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#32e768]"></div>
                </label>
                <span class="text-gray-300 font-medium">Ativa</span>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-dark-border">
                <button type="button" class="btn-cancel-main px-6 py-3 bg-dark-elevated text-gray-300 rounded-xl hover:bg-dark-base border border-dark-border transition">Cancelar</button>
                <button type="submit" class="px-6 py-3 bg-[#32e768] text-white rounded-xl hover:bg-[#28d15e] transition flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Subcategoria -->
<div id="modal-sub" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center p-4">
    <div class="bg-dark-card rounded-2xl border border-dark-border w-full max-w-md">
        <div class="p-6 border-b border-dark-border">
            <h3 id="modal-sub-title" class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="layers" class="w-6 h-6 text-[#32e768]"></i>
                <span>Nova Subcategoria</span>
            </h3>
        </div>
        <form id="form-sub" class="p-6 space-y-4">
            <input type="hidden" id="sub_id" value="">
            <div>
                <label for="sub_main_category_id" class="block text-gray-300 text-sm font-semibold mb-2">Categoria Principal *</label>
                <select id="sub_main_category_id" required
                        class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                    <option value="">-- Selecione --</option>
                </select>
            </div>
            <div>
                <label for="sub_nome" class="block text-gray-300 text-sm font-semibold mb-2">Nome *</label>
                <input type="text" id="sub_nome" required maxlength="120" placeholder="Ex: Tráfego Pago"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div>
                <label for="sub_ordem" class="block text-gray-300 text-sm font-semibold mb-2">Ordem</label>
                <input type="number" id="sub_ordem" value="0" min="0" step="1"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="sub_ativo" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-dark-border peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#32e768]/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#32e768]"></div>
                </label>
                <span class="text-gray-300 font-medium">Ativa</span>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-dark-border">
                <button type="button" class="btn-cancel-sub px-6 py-3 bg-dark-elevated text-gray-300 rounded-xl hover:bg-dark-base border border-dark-border transition">Cancelar</button>
                <button type="submit" class="px-6 py-3 bg-[#32e768] text-white rounded-xl hover:bg-[#28d15e] transition flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    let cachedMainCategories = [];
    let cachedSubcategories = [];
    let activeTab = 'main';

    const tabMain = document.getElementById('tab-main');
    const tabSub = document.getElementById('tab-sub');
    const panelMain = document.getElementById('panel-main');
    const panelSub = document.getElementById('panel-sub');

    function escapeHtml(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showToast(msg, type) {
        const existing = document.getElementById('toast-msg');
        if (existing) existing.remove();
        const div = document.createElement('div');
        div.id = 'toast-msg';
        div.className = 'fixed top-5 right-5 z-[60] flex items-center w-full max-w-sm p-4 text-gray-300 bg-dark-card rounded-lg shadow-xl border border-dark-border';
        const iconClass = type === 'success' ? 'text-green-400 bg-green-900/30' : 'text-red-400 bg-red-900/30';
        const icon = type === 'success' ? 'check' : 'alert-circle';
        div.innerHTML = '<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 ' + iconClass + ' rounded-lg"><i data-lucide="' + icon + '" class="w-5 h-5"></i></div><div class="ml-3 text-sm font-medium">' + escapeHtml(msg) + '</div>';
        document.body.appendChild(div);
        lucide.createIcons();
        setTimeout(function() {
            div.style.opacity = '0';
            div.style.transition = 'opacity 0.5s';
            setTimeout(function() { div.remove(); }, 500);
        }, 3500);
    }

    function apiErrorMessage(data) {
        if (data && data.error) return data.error;
        if (data && data.message) return data.message;
        return 'Não foi possível concluir a operação. Tente novamente.';
    }

    function switchTab(tab) {
        activeTab = tab;
        const activeClasses = ['border-[#32e768]', 'text-[#32e768]', 'bg-dark-elevated/50'];
        const inactiveClasses = ['border-transparent', 'text-gray-400'];

        if (tab === 'main') {
            panelMain.style.display = '';
            panelSub.style.display = 'none';
            tabMain.classList.add(...activeClasses);
            tabMain.classList.remove(...inactiveClasses);
            tabSub.classList.remove(...activeClasses);
            tabSub.classList.add(...inactiveClasses);
        } else {
            panelMain.style.display = 'none';
            panelSub.style.display = '';
            tabSub.classList.add(...activeClasses);
            tabSub.classList.remove(...inactiveClasses);
            tabMain.classList.remove(...activeClasses);
            tabMain.classList.add(...inactiveClasses);
            loadSubcategories();
        }
    }

    tabMain.addEventListener('click', function() { switchTab('main'); });
    tabSub.addEventListener('click', function() { switchTab('sub'); });

    function statusBadge(ativo) {
        return Number(ativo) === 1
            ? '<span class="text-xs px-2 py-0.5 rounded bg-green-900/30 text-green-300">Ativa</span>'
            : '<span class="text-xs px-2 py-0.5 rounded bg-red-900/30 text-red-300">Inativa</span>';
    }

    function mainCategoryNameById(id) {
        const item = cachedMainCategories.find(function(c) { return String(c.id) === String(id); });
        return item ? item.nome : '—';
    }

    function populateMainSelects() {
        const filter = document.getElementById('filter-main-category');
        const modalSelect = document.getElementById('sub_main_category_id');
        const currentFilter = filter.value;

        filter.innerHTML = '<option value="">Todas</option>';
        modalSelect.innerHTML = '<option value="">-- Selecione --</option>';

        cachedMainCategories.forEach(function(cat) {
            const opt1 = document.createElement('option');
            opt1.value = cat.id;
            opt1.textContent = cat.nome;
            filter.appendChild(opt1);

            const opt2 = document.createElement('option');
            opt2.value = cat.id;
            opt2.textContent = cat.nome;
            modalSelect.appendChild(opt2);
        });

        if (currentFilter && filter.querySelector('option[value="' + currentFilter + '"]')) {
            filter.value = currentFilter;
        }

        const hasMain = cachedMainCategories.length > 0;
        document.getElementById('btn-new-sub').disabled = !hasMain;
        document.getElementById('sub-no-main-hint').style.display = hasMain ? 'none' : 'block';
    }

    function renderMainCategories() {
        const loading = document.getElementById('main-loading');
        const empty = document.getElementById('main-empty');
        const wrap = document.getElementById('main-table-wrap');
        const tbody = document.getElementById('main-table-body');

        loading.style.display = 'none';

        if (cachedMainCategories.length === 0) {
            empty.style.display = 'block';
            wrap.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        wrap.style.display = 'block';
        tbody.innerHTML = cachedMainCategories.map(function(cat) {
            return '<tr class="hover:bg-dark-elevated/40 transition">' +
                '<td class="py-3 pr-4 font-medium text-white">' + escapeHtml(cat.nome) + '</td>' +
                '<td class="py-3 px-4 text-center text-gray-300">' + escapeHtml(cat.ordem ?? 0) + '</td>' +
                '<td class="py-3 px-4 text-center">' + statusBadge(cat.ativo) + '</td>' +
                '<td class="py-3 pl-4 text-right">' +
                    '<div class="flex justify-end gap-2">' +
                        '<button type="button" data-edit-main="' + cat.id + '" class="flex items-center gap-1 px-3 py-1.5 text-sm text-gray-300 hover:text-[#32e768] hover:bg-[#32e768]/10 rounded-lg border border-dark-border transition"><i data-lucide="pencil" class="w-4 h-4"></i><span>Editar</span></button>' +
                        '<button type="button" data-delete-main="' + cat.id + '" class="flex items-center gap-1 px-3 py-1.5 text-sm text-gray-300 hover:text-red-400 hover:bg-red-500/10 rounded-lg border border-dark-border transition"><i data-lucide="trash-2" class="w-4 h-4"></i><span>Excluir</span></button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        lucide.createIcons();
    }

    function renderSubcategories() {
        const loading = document.getElementById('sub-loading');
        const empty = document.getElementById('sub-empty');
        const wrap = document.getElementById('sub-table-wrap');
        const tbody = document.getElementById('sub-table-body');

        loading.style.display = 'none';

        if (cachedSubcategories.length === 0) {
            empty.style.display = 'block';
            wrap.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        wrap.style.display = 'block';
        tbody.innerHTML = cachedSubcategories.map(function(sub) {
            return '<tr class="hover:bg-dark-elevated/40 transition">' +
                '<td class="py-3 pr-4 text-gray-300">' + escapeHtml(mainCategoryNameById(sub.main_category_id)) + '</td>' +
                '<td class="py-3 px-4 font-medium text-white">' + escapeHtml(sub.nome) + '</td>' +
                '<td class="py-3 px-4 text-center text-gray-300">' + escapeHtml(sub.ordem ?? 0) + '</td>' +
                '<td class="py-3 px-4 text-center">' + statusBadge(sub.ativo) + '</td>' +
                '<td class="py-3 pl-4 text-right">' +
                    '<div class="flex justify-end gap-2">' +
                        '<button type="button" data-edit-sub="' + sub.id + '" class="flex items-center gap-1 px-3 py-1.5 text-sm text-gray-300 hover:text-[#32e768] hover:bg-[#32e768]/10 rounded-lg border border-dark-border transition"><i data-lucide="pencil" class="w-4 h-4"></i><span>Editar</span></button>' +
                        '<button type="button" data-delete-sub="' + sub.id + '" class="flex items-center gap-1 px-3 py-1.5 text-sm text-gray-300 hover:text-red-400 hover:bg-red-500/10 rounded-lg border border-dark-border transition"><i data-lucide="trash-2" class="w-4 h-4"></i><span>Excluir</span></button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
        lucide.createIcons();
    }

    function loadMainCategories() {
        document.getElementById('main-loading').style.display = 'block';
        document.getElementById('main-empty').style.display = 'none';
        document.getElementById('main-table-wrap').style.display = 'none';

        return fetch('/api/api?action=list_product_main_categories')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    showToast(apiErrorMessage(data), 'error');
                    cachedMainCategories = [];
                } else {
                    cachedMainCategories = data.items || [];
                }
                populateMainSelects();
                renderMainCategories();
            })
            .catch(function() {
                document.getElementById('main-loading').style.display = 'none';
                showToast('Erro de conexão ao carregar categorias.', 'error');
            });
    }

    function loadSubcategories() {
        const filterId = document.getElementById('filter-main-category').value;
        let url = '/api/api?action=list_product_subcategories';
        if (filterId) {
            url += '&main_category_id=' + encodeURIComponent(filterId);
        }

        document.getElementById('sub-loading').style.display = 'block';
        document.getElementById('sub-empty').style.display = 'none';
        document.getElementById('sub-table-wrap').style.display = 'none';

        return fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    showToast(apiErrorMessage(data), 'error');
                    cachedSubcategories = [];
                } else {
                    cachedSubcategories = data.items || [];
                }
                renderSubcategories();
            })
            .catch(function() {
                document.getElementById('sub-loading').style.display = 'none';
                showToast('Erro de conexão ao carregar subcategorias.', 'error');
            });
    }

    function openMainModal(item) {
        document.getElementById('modal-main-title').querySelector('span').textContent = item ? 'Editar Categoria Principal' : 'Nova Categoria Principal';
        document.getElementById('main_id').value = item ? item.id : '';
        document.getElementById('main_nome').value = item ? (item.nome || '') : '';
        document.getElementById('main_ordem').value = item ? (item.ordem ?? 0) : 0;
        document.getElementById('main_ativo').checked = item ? Number(item.ativo) === 1 : true;
        document.getElementById('modal-main').classList.remove('hidden');
        document.getElementById('modal-main').classList.add('flex');
        lucide.createIcons();
    }

    function closeMainModal() {
        document.getElementById('modal-main').classList.add('hidden');
        document.getElementById('modal-main').classList.remove('flex');
    }

    function openSubModal(item) {
        if (cachedMainCategories.length === 0) {
            showToast('Cadastre uma categoria principal antes de criar subcategorias.', 'error');
            return;
        }
        populateMainSelects();
        document.getElementById('modal-sub-title').querySelector('span').textContent = item ? 'Editar Subcategoria' : 'Nova Subcategoria';
        document.getElementById('sub_id').value = item ? item.id : '';
        document.getElementById('sub_nome').value = item ? (item.nome || '') : '';
        document.getElementById('sub_ordem').value = item ? (item.ordem ?? 0) : 0;
        document.getElementById('sub_ativo').checked = item ? Number(item.ativo) === 1 : true;

        const filterId = document.getElementById('filter-main-category').value;
        const mainId = item ? item.main_category_id : (filterId || '');
        document.getElementById('sub_main_category_id').value = mainId || '';

        document.getElementById('modal-sub').classList.remove('hidden');
        document.getElementById('modal-sub').classList.add('flex');
        lucide.createIcons();
    }

    function closeSubModal() {
        document.getElementById('modal-sub').classList.add('hidden');
        document.getElementById('modal-sub').classList.remove('flex');
    }

    document.getElementById('btn-new-main').addEventListener('click', function() { openMainModal(null); });
    document.getElementById('btn-first-main').addEventListener('click', function() { openMainModal(null); });
    document.getElementById('btn-new-sub').addEventListener('click', function() { openSubModal(null); });
    document.querySelectorAll('.btn-cancel-main').forEach(function(btn) { btn.addEventListener('click', closeMainModal); });
    document.querySelectorAll('.btn-cancel-sub').forEach(function(btn) { btn.addEventListener('click', closeSubModal); });

    document.getElementById('filter-main-category').addEventListener('change', loadSubcategories);

    document.getElementById('main-table-body').addEventListener('click', function(e) {
        const editBtn = e.target.closest('[data-edit-main]');
        const deleteBtn = e.target.closest('[data-delete-main]');
        if (editBtn) {
            const id = editBtn.getAttribute('data-edit-main');
            const item = cachedMainCategories.find(function(c) { return String(c.id) === String(id); });
            if (item) openMainModal(item);
        }
        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-delete-main');
            const item = cachedMainCategories.find(function(c) { return String(c.id) === String(id); });
            if (!item) return;
            if (!confirm('Excluir a categoria "' + item.nome + '"?')) return;
            fetch('/api/api?action=delete_product_main_category', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id, 10) })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast(data.message || 'Categoria excluída!', 'success');
                    loadMainCategories().then(function() {
                        if (activeTab === 'sub') loadSubcategories();
                    });
                } else {
                    showToast(apiErrorMessage(data), 'error');
                }
            })
            .catch(function() { showToast('Erro de conexão.', 'error'); });
        }
    });

    document.getElementById('sub-table-body').addEventListener('click', function(e) {
        const editBtn = e.target.closest('[data-edit-sub]');
        const deleteBtn = e.target.closest('[data-delete-sub]');
        if (editBtn) {
            const id = editBtn.getAttribute('data-edit-sub');
            const item = cachedSubcategories.find(function(s) { return String(s.id) === String(id); });
            if (item) openSubModal(item);
        }
        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-delete-sub');
            const item = cachedSubcategories.find(function(s) { return String(s.id) === String(id); });
            if (!item) return;
            if (!confirm('Excluir a subcategoria "' + item.nome + '"?')) return;
            fetch('/api/api?action=delete_product_subcategory', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id, 10) })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast(data.message || 'Subcategoria excluída!', 'success');
                    loadSubcategories();
                } else {
                    showToast(apiErrorMessage(data), 'error');
                }
            })
            .catch(function() { showToast('Erro de conexão.', 'error'); });
        }
    });

    document.getElementById('form-main').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('main_id').value;
        const payload = {
            nome: document.getElementById('main_nome').value.trim(),
            ordem: parseInt(document.getElementById('main_ordem').value, 10) || 0,
            ativo: document.getElementById('main_ativo').checked ? 1 : 0
        };
        if (!payload.nome) {
            showToast('Informe o nome da categoria.', 'error');
            return;
        }
        if (id) payload.id = parseInt(id, 10);
        const action = id ? 'update_product_main_category' : 'create_product_main_category';

        fetch('/api/api?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                closeMainModal();
                showToast(data.message || 'Salvo!', 'success');
                loadMainCategories();
            } else {
                showToast(apiErrorMessage(data), 'error');
            }
        })
        .catch(function() { showToast('Erro de conexão.', 'error'); });
    });

    document.getElementById('form-sub').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('sub_id').value;
        const mainCategoryId = parseInt(document.getElementById('sub_main_category_id').value, 10);
        const payload = {
            main_category_id: mainCategoryId,
            nome: document.getElementById('sub_nome').value.trim(),
            ordem: parseInt(document.getElementById('sub_ordem').value, 10) || 0,
            ativo: document.getElementById('sub_ativo').checked ? 1 : 0
        };
        if (!mainCategoryId) {
            showToast('Selecione a categoria principal.', 'error');
            return;
        }
        if (!payload.nome) {
            showToast('Informe o nome da subcategoria.', 'error');
            return;
        }
        if (id) payload.id = parseInt(id, 10);
        const action = id ? 'update_product_subcategory' : 'create_product_subcategory';

        fetch('/api/api?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                closeSubModal();
                showToast(data.message || 'Salvo!', 'success');
                loadSubcategories();
            } else {
                showToast(apiErrorMessage(data), 'error');
            }
        })
        .catch(function() { showToast('Erro de conexão.', 'error'); });
    });

    loadMainCategories();
});
</script>
