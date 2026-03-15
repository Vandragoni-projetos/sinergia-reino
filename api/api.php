<?php
// CRUD de categorias de produto (product_type) por infoprodutor
$usuario_id = $_SESSION['id'] ?? 0;
if ($usuario_id === 0) {
    header("location: /login");
    exit;
}

require_once __DIR__ . '/../helpers/product_helper.php';

$mensagem = '';
$msg_type = '';
if (isset($_SESSION['flash_message'])) {
    $mensagem = $_SESSION['flash_message'];
    $msg_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>

<div class="container mx-auto">
    <div class="flex items-center mb-6">
        <a href="/index?pagina=produtos" class="text-[#32e768] hover:text-[#28d15e] mr-4">
            <i data-lucide="arrow-left-circle" class="w-8 h-8"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-white">Categorias de Produto</h1>
            <p class="text-gray-400">Gerencie as categorias exibidas em Meus Produtos e na Área de Membros. Se não criar categorias, serão usadas as padrão.</p>
        </div>
    </div>

    <?php if (!empty($mensagem)): ?>
    <div id="toast-msg" class="fixed top-5 right-5 z-50 animate-fade-in flex items-center w-full max-w-xs p-4 text-gray-300 bg-dark-card rounded-lg shadow-xl border border-dark-border">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 <?php echo ($msg_type == 'success' ? 'text-green-400 bg-green-900/30' : ($msg_type == 'error' ? 'text-red-400 bg-red-900/30' : 'text-blue-400 bg-blue-900/30')); ?> rounded-lg">
            <i data-lucide="<?php echo ($msg_type == 'success' ? 'check' : ($msg_type == 'error' ? 'alert-circle' : 'info')); ?>" class="w-5 h-5"></i>
        </div>
        <div class="ml-3 text-sm font-medium"><?php echo $mensagem; ?></div>
        <button type="button" class="ml-auto -mx-1.5 -my-1.5 text-gray-400 hover:text-gray-300 rounded-lg p-1.5 hover:bg-dark-elevated inline-flex h-8 w-8" onclick="this.parentElement.remove()">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
    <?php endif; ?>

    <div class="bg-dark-card p-8 rounded-2xl shadow-md border border-dark-border mb-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="tags" class="w-6 h-6 text-[#32e768]"></i>
                Suas Categorias
            </h2>
            <button type="button" id="btn-nova" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-bold py-2.5 px-5 rounded-xl flex items-center gap-2 transition">
                <i data-lucide="plus" class="w-5 h-5"></i>
                Nova Categoria
            </button>
        </div>

        <div id="loading-state" class="text-center py-12 text-gray-400">
            <svg class="animate-spin h-8 w-8 text-[#32e768] mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
            </svg>
            <p class="mt-4 font-medium">Carregando categorias...</p>
        </div>

        <div id="empty-state" class="text-center py-12 text-gray-400" style="display: none;">
            <i data-lucide="folder-open" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
            <p class="text-lg font-medium">Nenhuma categoria customizada</p>
            <p class="text-sm mt-1">Clique em "Nova Categoria" para criar. Enquanto não houver categorias, serão usadas as padrão do sistema.</p>
        </div>

        <div id="categories-list" class="space-y-3" style="display: none;">
            <!-- Preenchido via JS -->
        </div>
    </div>
</div>

<!-- Modal Criar/Editar -->
<div id="modal-categoria" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center p-4">
    <div class="bg-dark-card rounded-2xl border border-dark-border w-full max-w-lg">
        <div class="p-6 border-b border-dark-border">
            <h3 id="modal-title" class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="tag" class="w-6 h-6 text-[#32e768]"></i>
                <span>Nova Categoria</span>
            </h3>
        </div>
        <form id="form-categoria" class="p-6 space-y-4">
            <input type="hidden" id="cat_id" name="id" value="">
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Grupo</label>
                <input type="text" id="cat_group" name="group_name" required maxlength="80" placeholder="Ex: Nichos, Produtos Digitais"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Valor (slug)</label>
                <input type="text" id="cat_value" name="value" required maxlength="40" placeholder="Ex: CRISTAO, PLR"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white font-mono">
                <p class="text-xs text-gray-500 mt-1">Letras, números e underscore. Será convertido para maiúsculas.</p>
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Label (exibido)</label>
                <input type="text" id="cat_label" name="label" required maxlength="100" placeholder="Ex: ✝️ Cristão"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Ícone (emoji, opcional)</label>
                <input type="text" id="cat_icon" name="icon" maxlength="10" placeholder="Ex: ✝️"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Ordem</label>
                <input type="number" id="cat_ordem" name="ordem" value="0" min="0"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-dark-border">
                <button type="button" id="btn-cancel-modal" class="px-6 py-3 bg-dark-elevated text-gray-300 rounded-xl hover:bg-dark-base border border-dark-border transition">
                    Cancelar
                </button>
                <button type="submit" class="px-6 py-3 bg-[#32e768] text-white rounded-xl hover:bg-[#28d15e] transition flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    fetchCategories();

    document.getElementById('btn-nova').addEventListener('click', function() {
        document.getElementById('modal-title').querySelector('span').textContent = 'Nova Categoria';
        document.getElementById('form-categoria').reset();
        document.getElementById('cat_id').value = '';
        document.getElementById('cat_ordem').value = '0';
        document.getElementById('modal-categoria').classList.remove('hidden');
        document.getElementById('modal-categoria').classList.add('flex');
        lucide.createIcons();
    });

    document.getElementById('btn-cancel-modal').addEventListener('click', closeModal);

    document.getElementById('form-categoria').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('cat_id').value;
        const payload = {
            group_name: document.getElementById('cat_group').value.trim(),
            value: document.getElementById('cat_value').value.trim(),
            label: document.getElementById('cat_label').value.trim(),
            icon: document.getElementById('cat_icon').value.trim() || null,
            ordem: parseInt(document.getElementById('cat_ordem').value) || 0
        };
        const action = id ? 'update_product_type_category' : 'create_product_type_category';
        if (id) payload.id = parseInt(id);

        fetch('/api/api?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                fetchCategories();
                showToast(data.message || 'Salvo!', 'success');
            } else {
                showToast(data.error || 'Erro ao salvar', 'error');
            }
        })
        .catch(() => showToast('Erro de conexão', 'error'));
    });

    const toast = document.getElementById('toast-msg');
    if (toast) setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.5s'; setTimeout(() => toast.remove(), 500); }, 4000);
});

function closeModal() {
    document.getElementById('modal-categoria').classList.add('hidden');
    document.getElementById('modal-categoria').classList.remove('flex');
}

function showToast(msg, type) {
    const existing = document.getElementById('toast-msg');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.id = 'toast-msg';
    div.className = 'fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 text-gray-300 bg-dark-card rounded-lg shadow-xl border border-dark-border';
    const iconClass = type === 'success' ? 'text-green-400 bg-green-900/30' : (type === 'error' ? 'text-red-400 bg-red-900/30' : 'text-blue-400 bg-blue-900/30');
    div.innerHTML = '<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 ' + iconClass + ' rounded-lg"><i data-lucide="' + (type === 'success' ? 'check' : 'alert-circle') + '" class="w-5 h-5"></i></div><div class="ml-3 text-sm font-medium">' + escapeHtml(msg) + '</div>';
    document.body.appendChild(div);
    lucide.createIcons();
    setTimeout(() => { div.style.opacity = '0'; div.style.transition = 'opacity 0.5s'; setTimeout(() => div.remove(), 500); }, 3000);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function fetchCategories() {
    const loading = document.getElementById('loading-state');
    const empty = document.getElementById('empty-state');
    const list = document.getElementById('categories-list');
    loading.style.display = 'block';
    empty.style.display = 'none';
    list.style.display = 'none';

    fetch('/api/api?action=list_product_type_categories')
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            const items = data.items || [];
            if (items.length === 0) {
                empty.style.display = 'block';
            } else {
                cachedCategories = items;
                list.style.display = 'block';
                list.innerHTML = items.map(c => {
                    const icon = c.icon ? escapeHtml(c.icon) + ' ' : '';
                    return '<div class="flex items-center justify-between p-4 bg-dark-elevated rounded-xl border border-dark-border hover:border-[#32e768]/50 transition">' +
                        '<div class="flex items-center gap-3">' +
                        '<span class="text-2xl">' + (c.icon || '📦') + '</span>' +
                        '<div><span class="font-semibold text-white">' + icon + escapeHtml(c.label) + '</span>' +
                        '<span class="text-gray-500 text-sm ml-2">(' + escapeHtml(c.value) + ')</span>' +
                        '<span class="block text-xs text-gray-400">Grupo: ' + escapeHtml(c.group_name) + '</span></div></div>' +
                        '<div class="flex gap-2">' +
                        '<button type="button" onclick="editCat(' + c.id + ')" class="p-2 text-gray-400 hover:text-[#32e768] rounded-lg hover:bg-dark-card transition" title="Editar"><i data-lucide="pencil" class="w-4 h-4"></i></button>' +
                        '<button type="button" onclick="deleteCat(' + c.id + ')" class="p-2 text-gray-400 hover:text-red-400 rounded-lg hover:bg-dark-card transition" title="Excluir"><i data-lucide="trash-2" class="w-4 h-4"></i></button>' +
                        '</div></div>';
                }).join('');
                lucide.createIcons();
            }
        })
        .catch(() => {
            loading.style.display = 'none';
            empty.style.display = 'block';
            empty.innerHTML = '<p class="text-red-400">Erro ao carregar categorias.</p>';
        });
}

let cachedCategories = [];

function editCat(id) {
    const cat = cachedCategories.find(c => c.id == id);
    if (!cat) {
        fetch('/api/api?action=list_product_type_categories')
            .then(r => r.json())
            .then(data => {
                cachedCategories = data.items || [];
                doEditCat(cachedCategories.find(c => c.id == id));
            });
    } else {
        doEditCat(cat);
    }
}

function doEditCat(cat) {
    if (!cat) return;
    document.getElementById('modal-title').querySelector('span').textContent = 'Editar Categoria';
    document.getElementById('cat_id').value = cat.id;
    document.getElementById('cat_group').value = cat.group_name;
    document.getElementById('cat_value').value = cat.value;
    document.getElementById('cat_label').value = cat.label;
    document.getElementById('cat_icon').value = cat.icon || '';
    document.getElementById('cat_ordem').value = cat.ordem || 0;
    document.getElementById('modal-categoria').classList.remove('hidden');
    document.getElementById('modal-categoria').classList.add('flex');
    lucide.createIcons();
}

function deleteCat(id) {
    if (!confirm('Excluir esta categoria? Produtos que a usam não poderão ser excluídos.')) return;
    fetch('/api/api?action=delete_product_type_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            fetchCategories();
            showToast(data.message || 'Excluído!', 'success');
        } else {
            showToast(data.error || 'Erro ao excluir', 'error');
        }
    })
    .catch(() => showToast('Erro de conexão', 'error'));
}
</script>
