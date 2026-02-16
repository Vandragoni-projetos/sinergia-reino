<?php
// Este arquivo é incluído a partir do index.php,
// então a verificação de login e a conexão com o banco ($pdo) já existem.

$usuario_id_logado = $_SESSION['id'] ?? 0;

if ($usuario_id_logado === 0) {
    header("location: /login");
    exit;
}

// Busca todos os cursos (produtos do tipo área de membros) do infoprodutor
try {
    $stmt = $pdo->prepare("
        SELECT id, nome, foto 
        FROM produtos 
        WHERE tipo_entrega = 'area_membros'
        AND usuario_id = ? 
        ORDER BY nome ASC
    ");
    $stmt->execute([$usuario_id_logado]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cursos = [];
}

$upload_dir = 'uploads/';
?>

<div class="container mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-3xl font-bold text-white">Gerenciar Clientes</h1>
        <button onclick="openAddClientModal()" class="flex items-center gap-2 px-4 py-2 rounded-lg font-semibold text-white transition-colors" style="background-color: var(--accent-primary);" onmouseover="this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="this.style.backgroundColor='var(--accent-primary)'">
            <i data-lucide="user-plus" class="w-5 h-5"></i>
            <span>Adicionar Cliente</span>
        </button>
    </div>

    <!-- Filtro por Curso -->
    <div class="bg-dark-card p-6 rounded-lg shadow-md border border-dark-border mb-6">
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <label for="curso-filter" class="text-white font-medium whitespace-nowrap">Filtrar por Curso:</label>
            <select id="curso-filter" class="flex-1 bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="">Selecione um curso</option>
                <?php foreach ($cursos as $curso): ?>
                    <option value="<?php echo $curso['id']; ?>"><?php echo htmlspecialchars($curso['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Listagem de Clientes -->
    <div class="bg-dark-card p-6 rounded-lg shadow-md border border-dark-border">
        <div id="clientes-container">
            <?php if (empty($cursos)): ?>
                <div class="text-center py-12 text-gray-400">
                    <i data-lucide="users" class="mx-auto w-16 h-16 text-gray-500"></i>
                    <p class="mt-4">Você ainda não possui cursos na Área de Membros.</p>
                    <p>Crie um produto com entrega via Área de Membros para gerenciar clientes.</p>
                    <a href="/index?pagina=produtos" class="inline-block mt-4 px-4 py-2 rounded-lg font-semibold text-white transition-colors" style="background-color: var(--accent-primary);">
                        Criar Produto
                    </a>
                </div>
            <?php else: ?>
                <div id="select-curso-message" class="text-center py-12 text-gray-400">
                    <i data-lucide="filter" class="mx-auto w-16 h-16 text-gray-500"></i>
                    <p class="mt-4">Selecione um curso acima para visualizar os clientes.</p>
                </div>
                <div id="clientes-list" class="hidden">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                        <h2 id="curso-nome" class="text-xl font-semibold text-white"></h2>
                        <div class="flex items-center gap-2">
                            <input type="text" id="search-cliente" placeholder="Buscar por nome ou email..." class="bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary w-64">
                        </div>
                    </div>
                    
                    <!-- Estatísticas -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="bg-dark-elevated p-4 rounded-lg">
                            <p class="text-gray-400 text-sm">Total de Clientes</p>
                            <p id="stat-total" class="text-2xl font-bold text-white">0</p>
                        </div>
                        <div class="bg-dark-elevated p-4 rounded-lg">
                            <p class="text-gray-400 text-sm">Progresso Médio</p>
                            <p id="stat-progresso" class="text-2xl font-bold text-white">0%</p>
                        </div>
                        <div class="bg-dark-elevated p-4 rounded-lg">
                            <p class="text-gray-400 text-sm">Concluíram o Curso</p>
                            <p id="stat-concluidos" class="text-2xl font-bold text-white">0</p>
                        </div>
                    </div>

                    <!-- Tabela de Clientes -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-dark-border">
                                    <th class="text-left py-3 px-4 text-gray-400 font-medium">Cliente</th>
                                    <th class="text-left py-3 px-4 text-gray-400 font-medium">Email</th>
                                    <th class="text-left py-3 px-4 text-gray-400 font-medium">Plano</th>
                                    <th class="text-left py-3 px-4 text-gray-400 font-medium">Data de Acesso</th>
                                    <th class="text-left py-3 px-4 text-gray-400 font-medium">Progresso</th>
                                    <th class="text-center py-3 px-4 text-gray-400 font-medium">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="clientes-tbody">
                                <!-- Clientes serão carregados via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="no-clientes" class="hidden text-center py-8 text-gray-400">
                        <i data-lucide="user-x" class="mx-auto w-12 h-12 text-gray-500"></i>
                        <p class="mt-2">Nenhum cliente encontrado para este curso.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Adicionar Cliente -->
<div id="add-client-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-dark-card rounded-xl border border-dark-border w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between p-6 border-b border-dark-border">
            <h3 class="text-xl font-bold text-white">Adicionar Cliente</h3>
            <button onclick="closeAddClientModal()" class="text-gray-400 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <form id="add-client-form" class="p-6 space-y-4">
            <div>
                <label for="client-nome" class="block text-sm font-medium text-gray-300 mb-2">Nome do Cliente *</label>
                <input type="text" id="client-nome" name="nome" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Nome completo">
            </div>
            <div>
                <label for="client-email" class="block text-sm font-medium text-gray-300 mb-2">Email *</label>
                <input type="email" id="client-email" name="email" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="email@exemplo.com">
            </div>
            <div>
                <label for="client-curso" class="block text-sm font-medium text-gray-300 mb-2">Curso *</label>
                <select id="client-curso" name="produto_id" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Selecione um curso</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?php echo $curso['id']; ?>"><?php echo htmlspecialchars($curso['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="client-tipo-acesso" class="block text-sm font-medium text-gray-300 mb-2">Tipo de Plano *</label>
                <select id="client-tipo-acesso" name="tipo_acesso" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="vitalicio">Vitalício (sem expiração)</option>
                    <option value="mensal">Mensal (30 dias)</option>
                    <option value="semestral">Semestral (180 dias)</option>
                    <option value="anual">Anual (365 dias)</option>
                </select>
            </div>
            <div>
                <label for="client-senha" class="block text-sm font-medium text-gray-300 mb-2">Senha (opcional)</label>
                <div class="flex gap-2">
                    <input type="text" id="client-senha" name="senha" class="flex-1 bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Deixe vazio para gerar automaticamente">
                    <button type="button" onclick="generatePassword()" class="px-4 py-3 bg-dark-elevated border border-dark-border text-gray-300 rounded-lg hover:bg-dark-border transition-colors" title="Gerar senha">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Se não informar, uma senha será gerada automaticamente.</p>
            </div>
            <!-- 
            <div class="flex items-center gap-3">
                <input type="checkbox" id="client-enviar-email" name="enviar_email" class="w-5 h-5 rounded border-dark-border bg-dark-elevated text-primary focus:ring-primary">
                <label for="client-enviar-email" class="text-sm text-gray-300">Enviar email de acesso ao cliente</label>
            </div>
             -->
            <div id="add-client-error" class="hidden bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded-lg text-sm"></div>
            <div id="add-client-success" class="hidden bg-green-900/20 border border-green-500 text-green-300 px-4 py-3 rounded-lg text-sm"></div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeAddClientModal()" class="flex-1 px-4 py-3 bg-dark-elevated text-gray-300 rounded-lg font-semibold hover:bg-dark-border transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btn-add-client" class="flex-1 px-4 py-3 rounded-lg font-semibold text-white transition-colors" style="background-color: var(--accent-primary);" onmouseover="this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="this.style.backgroundColor='var(--accent-primary)'">
                    Adicionar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Alterar Plano -->
<div id="edit-plan-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-dark-card rounded-xl border border-dark-border w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between p-6 border-b border-dark-border">
            <h3 class="text-xl font-bold text-white">Alterar Plano</h3>
            <button onclick="closeEditPlanModal()" class="text-gray-400 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <form id="edit-plan-form" class="p-6 space-y-4">
            <input type="hidden" id="edit-plan-email">
            <input type="hidden" id="edit-plan-produto">
            <div class="bg-dark-elevated p-4 rounded-lg mb-4">
                <p class="text-sm text-gray-400">Cliente</p>
                <p id="edit-plan-client-name" class="text-white font-medium"></p>
            </div>
            <div>
                <label for="edit-tipo-acesso" class="block text-sm font-medium text-gray-300 mb-2">Novo Plano *</label>
                <select id="edit-tipo-acesso" name="tipo_acesso" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="vitalicio">Vitalício (sem expiração)</option>
                    <option value="mensal">Mensal (30 dias a partir de hoje)</option>
                    <option value="semestral">Semestral (180 dias a partir de hoje)</option>
                    <option value="anual">Anual (365 dias a partir de hoje)</option>
                </select>
                <p class="text-xs text-gray-500 mt-2">Para planos com prazo, a data de expiração será recalculada a partir de hoje.</p>
            </div>
            <div id="edit-plan-error" class="hidden bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded-lg text-sm"></div>
            <div id="edit-plan-success" class="hidden bg-green-900/20 border border-green-500 text-green-300 px-4 py-3 rounded-lg text-sm"></div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeEditPlanModal()" class="flex-1 px-4 py-3 bg-dark-elevated text-gray-300 rounded-lg font-semibold hover:bg-dark-border transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btn-edit-plan" class="flex-1 px-4 py-3 rounded-lg font-semibold text-white transition-colors" style="background-color: var(--accent-primary);" onmouseover="this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="this.style.backgroundColor='var(--accent-primary)'">
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Confirmar Remoção -->
<div id="remove-client-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-dark-card rounded-xl border border-dark-border w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between p-6 border-b border-dark-border">
            <h3 class="text-xl font-bold text-white">Remover Acesso</h3>
            <button onclick="closeRemoveModal()" class="text-gray-400 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="p-6">
            <p class="text-gray-300 mb-4">Tem certeza que deseja remover o acesso de <span id="remove-client-name" class="font-semibold text-white"></span> ao curso?</p>
            <p class="text-sm text-gray-400 mb-6">Esta ação não pode ser desfeita. O cliente perderá acesso ao conteúdo do curso.</p>
            <input type="hidden" id="remove-client-email">
            <input type="hidden" id="remove-client-produto">
            <div class="flex gap-3">
                <button type="button" onclick="closeRemoveModal()" class="flex-1 px-4 py-3 bg-dark-elevated text-gray-300 rounded-lg font-semibold hover:bg-dark-border transition-colors">
                    Cancelar
                </button>
                <button type="button" onclick="confirmRemoveClient()" id="btn-confirm-remove" class="flex-1 px-4 py-3 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition-colors">
                    Remover Acesso
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    
    const cursoFilter = document.getElementById('curso-filter');
    const searchInput = document.getElementById('search-cliente');
    let allClientes = [];
    let currentProdutoId = null;

    // Event listener para filtro de curso
    if (cursoFilter) {
        cursoFilter.addEventListener('change', function() {
            const produtoId = this.value;
            if (produtoId) {
                currentProdutoId = produtoId;
                loadClientes(produtoId);
            } else {
                document.getElementById('select-curso-message').classList.remove('hidden');
                document.getElementById('clientes-list').classList.add('hidden');
            }
        });
    }

    // Event listener para busca
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterClientes(this.value);
        });
    }

    // Função para carregar clientes
    window.loadClientes = function(produtoId) {
        fetch(`/api/api.php?action=list_clientes&produto_id=${produtoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allClientes = data.alunos || [];
                    document.getElementById('select-curso-message').classList.add('hidden');
                    document.getElementById('clientes-list').classList.remove('hidden');
                    document.getElementById('curso-nome').textContent = data.produto?.nome || 'Curso';
                    renderClientes(allClientes);
                    updateStats(allClientes);
                } else {
                    showError(data.error || 'Erro ao carregar clientes');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showError('Erro ao carregar clientes');
            });
    };

    // Função para filtrar clientes
    function filterClientes(search) {
        const filtered = allClientes.filter(cliente => {
            const nome = (cliente.nome || '').toLowerCase();
            const email = (cliente.aluno_email || '').toLowerCase();
            const term = search.toLowerCase();
            return nome.includes(term) || email.includes(term);
        });
        renderClientes(filtered);
    }

    // Função para renderizar clientes na tabela
    function renderClientes(clientes) {
        const tbody = document.getElementById('clientes-tbody');
        const noClientes = document.getElementById('no-clientes');
        
        if (clientes.length === 0) {
            tbody.innerHTML = '';
            noClientes.classList.remove('hidden');
            return;
        }
        
        noClientes.classList.add('hidden');
        
        tbody.innerHTML = clientes.map(cliente => {
            const dataAcesso = cliente.data_concessao ? new Date(cliente.data_concessao).toLocaleDateString('pt-BR') : '-';
            const progresso = cliente.progresso_percentual || 0;
            const progressColor = progresso >= 100 ? 'bg-green-500' : progresso >= 50 ? 'bg-yellow-500' : 'bg-primary';
            
            // Badge do tipo de plano
            let planoBadgeClass = 'bg-green-900/30 text-green-400 border-green-500/30';
            let planoLabel = cliente.tipo_plano_label || 'Vitalício';
            
            if (cliente.acesso_expirado) {
                planoBadgeClass = 'bg-red-900/30 text-red-400 border-red-500/30';
                planoLabel += ' (Expirado)';
            } else if (cliente.tipo_plano === 'mensal') {
                planoBadgeClass = 'bg-blue-900/30 text-blue-400 border-blue-500/30';
            } else if (cliente.tipo_plano === 'semestral') {
                planoBadgeClass = 'bg-purple-900/30 text-purple-400 border-purple-500/30';
            } else if (cliente.tipo_plano === 'anual') {
                planoBadgeClass = 'bg-yellow-900/30 text-yellow-400 border-yellow-500/30';
            }
            
            // Formatar data de expiração se existir
            let dataExpInfo = '';
            if (cliente.data_expiracao) {
                const dataExp = new Date(cliente.data_expiracao).toLocaleDateString('pt-BR');
                dataExpInfo = `<span class="text-xs text-gray-500 block">Expira: ${dataExp}</span>`;
            }
            
            return `
                <tr class="border-b border-dark-border hover:bg-dark-elevated/50 transition-colors">
                    <td class="py-4 px-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold" style="background-color: var(--accent-primary);">
                                ${(cliente.nome || cliente.aluno_email).charAt(0).toUpperCase()}
                            </div>
                            <span class="text-white font-medium">${escapeHtml(cliente.nome || cliente.aluno_email)}</span>
                        </div>
                    </td>
                    <td class="py-4 px-4 text-gray-300">${escapeHtml(cliente.aluno_email)}</td>
                    <td class="py-4 px-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full border ${planoBadgeClass}">${planoLabel}</span>
                        ${dataExpInfo}
                    </td>
                    <td class="py-4 px-4 text-gray-300">${dataAcesso}</td>
                    <td class="py-4 px-4">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-dark-elevated rounded-full h-2 max-w-[100px]">
                                <div class="${progressColor} h-2 rounded-full transition-all" style="width: ${progresso}%"></div>
                            </div>
                            <span class="text-sm text-gray-300">${progresso}%</span>
                        </div>
                    </td>
                    <td class="py-4 px-4 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <button onclick="openEditPlanModal('${escapeHtml(cliente.aluno_email)}', '${escapeHtml(cliente.nome || cliente.aluno_email)}', ${currentProdutoId}, '${cliente.tipo_plano || 'vitalicio'}')" class="p-2 text-blue-400 hover:text-blue-300 hover:bg-blue-900/20 rounded-lg transition-colors" title="Alterar plano">
                                <i data-lucide="edit-3" class="w-5 h-5"></i>
                            </button>
                            <button onclick="openRemoveModal('${escapeHtml(cliente.aluno_email)}', '${escapeHtml(cliente.nome || cliente.aluno_email)}', ${currentProdutoId})" class="p-2 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded-lg transition-colors" title="Remover acesso">
                                <i data-lucide="user-minus" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        
        lucide.createIcons();
    }

    // Função para atualizar estatísticas
    function updateStats(clientes) {
        const total = clientes.length;
        const progressoMedio = total > 0 ? Math.round(clientes.reduce((sum, c) => sum + (c.progresso_percentual || 0), 0) / total) : 0;
        const concluidos = clientes.filter(c => (c.progresso_percentual || 0) >= 100).length;
        
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-progresso').textContent = progressoMedio + '%';
        document.getElementById('stat-concluidos').textContent = concluidos;
    }

    // Função auxiliar para escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError(message) {
        alert(message);
    }
});

// Funções do Modal Adicionar Cliente
function generatePassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    let password = '';
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('client-senha').value = password;
}

function openAddClientModal() {
    document.getElementById('add-client-modal').classList.remove('hidden');
    document.getElementById('add-client-form').reset();
    document.getElementById('add-client-error').classList.add('hidden');
    document.getElementById('add-client-success').classList.add('hidden');
    
    // Se já tem um curso selecionado, pré-seleciona no modal
    const cursoFilter = document.getElementById('curso-filter');
    if (cursoFilter && cursoFilter.value) {
        document.getElementById('client-curso').value = cursoFilter.value;
    }
    
    lucide.createIcons();
}

function closeAddClientModal() {
    document.getElementById('add-client-modal').classList.add('hidden');
}

// Submit do formulário de adicionar cliente
document.getElementById('add-client-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn-add-client');
    const errorDiv = document.getElementById('add-client-error');
    const successDiv = document.getElementById('add-client-success');
    
    btn.disabled = true;
    btn.textContent = 'Adicionando...';
    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');
    
    const enviarEmailCheckbox = document.getElementById('client-enviar-email');
    const formData = {
        action: 'create_cliente',
        nome: document.getElementById('client-nome').value,
        email: document.getElementById('client-email').value,
        produto_id: parseInt(document.getElementById('client-curso').value),
        tipo_acesso: document.getElementById('client-tipo-acesso').value,
        senha: document.getElementById('client-senha').value.trim(),
        enviar_email: enviarEmailCheckbox ? enviarEmailCheckbox.checked : false
    };
    
    fetch('/api/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            successDiv.textContent = data.message || 'Cliente adicionado com sucesso!';
            successDiv.classList.remove('hidden');
            
            // Recarrega a lista se o curso selecionado for o mesmo
            const cursoFilter = document.getElementById('curso-filter');
            if (cursoFilter && cursoFilter.value == formData.produto_id) {
                loadClientes(formData.produto_id);
            }
            
            setTimeout(() => {
                closeAddClientModal();
            }, 1500);
        } else {
            errorDiv.textContent = data.error || 'Erro ao adicionar cliente';
            errorDiv.classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = 'Erro ao adicionar cliente';
        errorDiv.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Adicionar';
    });
});

// Funções do Modal Remover Cliente
function openRemoveModal(email, nome, produtoId) {
    document.getElementById('remove-client-modal').classList.remove('hidden');
    document.getElementById('remove-client-name').textContent = nome;
    document.getElementById('remove-client-email').value = email;
    document.getElementById('remove-client-produto').value = produtoId;
    lucide.createIcons();
}

function closeRemoveModal() {
    document.getElementById('remove-client-modal').classList.add('hidden');
}

function confirmRemoveClient() {
    const btn = document.getElementById('btn-confirm-remove');
    const email = document.getElementById('remove-client-email').value;
    const produtoId = document.getElementById('remove-client-produto').value;
    
    btn.disabled = true;
    btn.textContent = 'Removendo...';
    
    fetch('/api/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'remove_cliente',
            email: email,
            produto_id: parseInt(produtoId)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeRemoveModal();
            loadClientes(produtoId);
        } else {
            alert(data.error || 'Erro ao remover cliente');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao remover cliente');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Remover Acesso';
    });
}

// Funções do Modal Alterar Plano
function openEditPlanModal(email, nome, produtoId, tipoAtual) {
    document.getElementById('edit-plan-modal').classList.remove('hidden');
    document.getElementById('edit-plan-email').value = email;
    document.getElementById('edit-plan-produto').value = produtoId;
    document.getElementById('edit-plan-client-name').textContent = nome;
    document.getElementById('edit-tipo-acesso').value = tipoAtual;
    document.getElementById('edit-plan-error').classList.add('hidden');
    document.getElementById('edit-plan-success').classList.add('hidden');
    lucide.createIcons();
}

function closeEditPlanModal() {
    document.getElementById('edit-plan-modal').classList.add('hidden');
}

// Submit do formulário de alterar plano
document.getElementById('edit-plan-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn-edit-plan');
    const errorDiv = document.getElementById('edit-plan-error');
    const successDiv = document.getElementById('edit-plan-success');
    const email = document.getElementById('edit-plan-email').value;
    const produtoId = document.getElementById('edit-plan-produto').value;
    const tipoAcesso = document.getElementById('edit-tipo-acesso').value;
    
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');
    
    fetch('/api/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_cliente_plano',
            email: email,
            produto_id: parseInt(produtoId),
            tipo_acesso: tipoAcesso
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            successDiv.textContent = data.message || 'Plano alterado com sucesso!';
            successDiv.classList.remove('hidden');
            
            // Recarrega a lista
            loadClientes(produtoId);
            
            setTimeout(() => {
                closeEditPlanModal();
            }, 1500);
        } else {
            errorDiv.textContent = data.error || 'Erro ao alterar plano';
            errorDiv.classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = 'Erro ao alterar plano';
        errorDiv.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Salvar';
    });
});
</script>
