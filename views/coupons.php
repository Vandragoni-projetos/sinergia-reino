<?php
$usuario_id = $_SESSION['id'] ?? 0;
if ($usuario_id === 0) {
    header("location: /login");
    exit;
}

$mensagem = '';
$msg_type = '';
if (isset($_SESSION['flash_message'])) {
    $mensagem = $_SESSION['flash_message'];
    $msg_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$stmt_prod = $pdo->prepare("SELECT id, nome FROM produtos WHERE usuario_id = ? ORDER BY nome ASC");
$stmt_prod->execute([$usuario_id]);
$produtos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto">
    <div class="flex items-center mb-6">
        <a href="/index?pagina=produtos" class="text-[#32e768] hover:text-[#28d15e] mr-4">
            <i data-lucide="arrow-left-circle" class="w-8 h-8"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-white">Cupons de Desconto</h1>
            <p class="text-gray-400">Crie cupons para campanhas, lançamentos e parcerias. Os clientes aplicam o código no checkout.</p>
        </div>
    </div>

    <?php if (!empty($mensagem)): ?>
    <div id="toast-msg" class="fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 text-gray-300 bg-dark-card rounded-lg shadow-xl border border-dark-border">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 <?php echo ($msg_type == 'success' ? 'text-green-400 bg-green-900/30' : ($msg_type == 'error' ? 'text-red-400 bg-red-900/30' : 'text-blue-400 bg-blue-900/30')); ?> rounded-lg">
            <i data-lucide="<?php echo ($msg_type == 'success' ? 'check' : ($msg_type == 'error' ? 'alert-circle' : 'info')); ?>" class="w-5 h-5"></i>
        </div>
        <div class="ml-3 text-sm font-medium"><?php echo $mensagem; ?></div>
        <button type="button" class="ml-auto -mx-1.5 -my-1.5 text-gray-400 hover:text-gray-300 rounded-lg p-1.5" onclick="this.parentElement.remove()">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
    <?php endif; ?>

    <div class="bg-dark-card p-8 rounded-2xl shadow-md border border-dark-border mb-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="ticket" class="w-6 h-6 text-[#32e768]"></i>
                Seus Cupons
            </h2>
            <button type="button" id="btn-nova" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-bold py-2.5 px-5 rounded-xl flex items-center gap-2 transition">
                <i data-lucide="plus" class="w-5 h-5"></i>
                Novo Cupom
            </button>
        </div>

        <div id="loading-state" class="text-center py-12 text-gray-400">
            <svg class="animate-spin h-8 w-8 text-[#32e768] mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
            </svg>
            <p class="mt-4 font-medium">Carregando cupons...</p>
        </div>

        <div id="empty-state" class="text-center py-12 text-gray-400" style="display: none;">
            <i data-lucide="ticket" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
            <p class="text-lg font-medium">Nenhum cupom criado</p>
            <p class="text-sm mt-1">Clique em "Novo Cupom" para criar seu primeiro cupom de desconto.</p>
        </div>

        <div id="cupons-list-wrap" style="display: none;">
            <div id="cupons-list" class="space-y-3"></div>
        </div>
    </div>
</div>

<!-- Modal Novo/Editar Cupom -->
<div id="modal-cupom" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center p-4">
    <div class="bg-dark-card rounded-2xl border border-dark-border w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-dark-border sticky top-0 bg-dark-card z-10">
            <h3 id="modal-title" class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="ticket" class="w-6 h-6 text-[#32e768]"></i>
                <span>Novo Cupom</span>
            </h3>
        </div>
        <form id="form-cupom" class="p-6 space-y-4">
            <input type="hidden" id="cupom_id" value="">
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Código *</label>
                <input type="text" id="cupom_codigo" required maxlength="50" placeholder="Ex: PROMO20, BLACKFRIDAY"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white font-mono uppercase">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-300 text-sm font-semibold mb-2">Tipo *</label>
                    <select id="cupom_tipo" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                        <option value="percentual">Percentual (%)</option>
                        <option value="fixo">Valor fixo (R$)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-300 text-sm font-semibold mb-2">Valor *</label>
                    <input type="number" id="cupom_valor" required step="0.01" min="0" placeholder="Ex: 20 ou 50"
                           class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                </div>
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Pedido mínimo (R$) - opcional</label>
                <input type="number" id="cupom_pedido_minimo" step="0.01" min="0" placeholder="Deixe vazio para qualquer valor"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Máximo de usos - opcional</label>
                <input type="number" id="cupom_max_usos" min="1" placeholder="Ilimitado"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-300 text-sm font-semibold mb-2">Válido de</label>
                    <input type="datetime-local" id="cupom_valido_de"
                           class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                </div>
                <div>
                    <label class="block text-gray-300 text-sm font-semibold mb-2">Válido até</label>
                    <input type="datetime-local" id="cupom_valido_ate"
                           class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white">
                </div>
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Produtos (opcional)</label>
                <select id="cupom_produtos" multiple class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] text-white" size="4">
                    <?php foreach ($produtos as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Nenhum selecionado = cupom vale para todos os produtos</p>
            </div>
            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="cupom_ativo" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-dark-border peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#32e768]/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#32e768]"></div>
                </label>
                <span class="text-gray-300 font-medium">Cupom ativo</span>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-dark-border">
                <button type="button" id="btn-cancel-modal" class="px-6 py-3 bg-dark-elevated text-gray-300 rounded-xl hover:bg-dark-base border border-dark-border transition">Cancelar</button>
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
    fetchCupons();

    document.getElementById('btn-nova').addEventListener('click', function() {
        document.getElementById('modal-title').querySelector('span').textContent = 'Novo Cupom';
        document.getElementById('form-cupom').reset();
        document.getElementById('cupom_id').value = '';
        document.getElementById('cupom_ativo').checked = true;
        document.getElementById('modal-cupom').classList.remove('hidden');
        document.getElementById('modal-cupom').classList.add('flex');
        lucide.createIcons();
    });

    document.getElementById('btn-cancel-modal').addEventListener('click', closeModal);

    document.getElementById('form-cupom').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('cupom_id').value;
        const produtoIds = Array.from(document.getElementById('cupom_produtos').selectedOptions).map(o => o.value);
        const payload = {
            codigo: document.getElementById('cupom_codigo').value.trim(),
            tipo: document.getElementById('cupom_tipo').value,
            valor: parseFloat(document.getElementById('cupom_valor').value) || 0,
            pedido_minimo: document.getElementById('cupom_pedido_minimo').value || null,
            max_usos: document.getElementById('cupom_max_usos').value || null,
            valido_de: document.getElementById('cupom_valido_de').value || null,
            valido_ate: document.getElementById('cupom_valido_ate').value || null,
            ativo: document.getElementById('cupom_ativo').checked ? 1 : 0,
            produto_ids: produtoIds
        };
        if (id) payload.id = parseInt(id);
        const action = id ? 'update_cupom' : 'create_cupom';

        fetch('/api/api?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                fetchCupons();
                showToast(data.message || 'Salvo!', 'success');
            } else {
                showToast(data.error || 'Erro', 'error');
            }
        })
        .catch(() => showToast('Erro de conexão', 'error'));
    });

    const toast = document.getElementById('toast-msg');
    if (toast) setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.5s'; setTimeout(() => toast.remove(), 500); }, 4000);
});

function closeModal() {
    document.getElementById('modal-cupom').classList.add('hidden');
    document.getElementById('modal-cupom').classList.remove('flex');
}

function showToast(msg, type) {
    const existing = document.getElementById('toast-msg');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.id = 'toast-msg';
    div.className = 'fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 text-gray-300 bg-dark-card rounded-lg shadow-xl border border-dark-border';
    const iconClass = type === 'success' ? 'text-green-400 bg-green-900/30' : 'text-red-400 bg-red-900/30';
    div.innerHTML = '<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 ' + iconClass + ' rounded-lg"><i data-lucide="check" class="w-5 h-5"></i></div><div class="ml-3 text-sm font-medium">' + (msg || '').replace(/</g, '&lt;') + '</div>';
    document.body.appendChild(div);
    lucide.createIcons();
    setTimeout(() => { div.style.opacity = '0'; div.style.transition = 'opacity 0.5s'; setTimeout(() => div.remove(), 500); }, 3000);
}

function fetchCupons() {
    const loading = document.getElementById('loading-state');
    const empty = document.getElementById('empty-state');
    const wrap = document.getElementById('cupons-list-wrap');
    const list = document.getElementById('cupons-list');
    loading.style.display = 'block';
    empty.style.display = 'none';
    wrap.style.display = 'none';

    fetch('/api/api?action=list_cupons')
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            const items = data.items || [];
            if (items.length === 0) {
                empty.style.display = 'block';
            } else {
                cachedCupons = items;
                wrap.style.display = 'block';
                list.innerHTML = items.map(c => {
                    const tipoLabel = c.tipo === 'percentual' ? c.valor + '%' : 'R$ ' + parseFloat(c.valor).toFixed(2);
                    const ativoBadge = c.ativo == 1 ? '<span class="text-xs px-2 py-0.5 rounded bg-green-900/30 text-green-300">Ativo</span>' : '<span class="text-xs px-2 py-0.5 rounded bg-red-900/30 text-red-300">Inativo</span>';
                    const usos = (c.max_usos !== null && c.max_usos !== '') ? (c.usos_atual || 0) + '/' + c.max_usos : (c.usos_atual || 0);
                    return '<div class="flex items-center justify-between p-4 bg-dark-elevated rounded-xl border border-dark-border hover:border-[#32e768]/50 transition">' +
                        '<div><span class="font-mono font-bold text-[#32e768]">' + (c.codigo || '').replace(/</g, '&lt;') + '</span> ' + ativoBadge +
                        '<span class="ml-2 text-gray-400">' + tipoLabel + '</span>' +
                        '<span class="block text-xs text-gray-500 mt-1">Usos: ' + usos + '</span></div>' +
                        '<div class="flex gap-2">' +
                        '<button type="button" onclick="editCupom(' + c.id + ')" class="flex items-center gap-1.5 px-3 py-2 text-sm text-gray-300 hover:text-[#32e768] hover:bg-[#32e768]/10 rounded-lg border border-dark-border transition"><i data-lucide="pencil" class="w-4 h-4"></i><span>Editar</span></button>' +
                        '<button type="button" onclick="deleteCupom(' + c.id + ')" class="flex items-center gap-1.5 px-3 py-2 text-sm text-gray-300 hover:text-red-400 hover:bg-red-500/10 rounded-lg border border-dark-border transition"><i data-lucide="trash-2" class="w-4 h-4"></i><span>Excluir</span></button>' +
                        '</div></div>';
                }).join('');
                lucide.createIcons();
            }
        })
        .catch(() => {
            loading.style.display = 'none';
            empty.style.display = 'block';
            empty.innerHTML = '<p class="text-red-400">Erro ao carregar cupons.</p>';
        });
}

let cachedCupons = [];

function editCupom(id) {
    const c = cachedCupons.find(x => x.id == id);
    if (!c) {
        fetch('/api/api?action=list_cupons').then(r => r.json()).then(data => {
            cachedCupons = data.items || [];
            doEditCupom(cachedCupons.find(x => x.id == id));
        });
    } else {
        doEditCupom(c);
    }
}

function doEditCupom(c) {
    if (!c) return;
    document.getElementById('modal-title').querySelector('span').textContent = 'Editar Cupom';
    document.getElementById('cupom_id').value = c.id;
    document.getElementById('cupom_codigo').value = c.codigo || '';
    document.getElementById('cupom_tipo').value = c.tipo || 'percentual';
    document.getElementById('cupom_valor').value = c.valor || '';
    document.getElementById('cupom_pedido_minimo').value = c.pedido_minimo || '';
    document.getElementById('cupom_max_usos').value = c.max_usos || '';
    document.getElementById('cupom_valido_de').value = c.valido_de ? c.valido_de.slice(0, 16) : '';
    document.getElementById('cupom_valido_ate').value = c.valido_ate ? c.valido_ate.slice(0, 16) : '';
    document.getElementById('cupom_ativo').checked = c.ativo == 1;
    const ids = (c.produto_ids || '').toString().split(',').filter(Boolean);
    Array.from(document.getElementById('cupom_produtos').options).forEach(opt => {
        opt.selected = ids.includes(opt.value);
    });
    document.getElementById('modal-cupom').classList.remove('hidden');
    document.getElementById('modal-cupom').classList.add('flex');
    lucide.createIcons();
}

function deleteCupom(id) {
    if (!confirm('Excluir este cupom?')) return;
    fetch('/api/api?action=delete_cupom', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            fetchCupons();
            showToast(data.message || 'Excluído!', 'success');
        } else {
            showToast(data.error || 'Erro', 'error');
        }
    })
    .catch(() => showToast('Erro de conexão', 'error'));
}
</script>
