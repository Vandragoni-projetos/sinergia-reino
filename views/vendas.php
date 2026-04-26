<?php
// Este arquivo é incluído a partir do index.php

// Buscar produtos do infoprodutor para o filtro
$usuario_id_logado = $_SESSION['id'] ?? 0;
$produtos_lista = [];
try {
    $stmt_produtos = $pdo->prepare("SELECT id, nome FROM produtos WHERE usuario_id = ? ORDER BY nome ASC");
    $stmt_produtos->execute([$usuario_id_logado]);
    $produtos_lista = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produtos_lista = [];
}
?>

<style>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="container mx-auto relative">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <h1 class="text-3xl font-bold text-white">Relatório de Vendas</h1>
        <div class="flex items-center gap-2">
            <button type="button" id="btn-export-excel" class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 border border-emerald-500 rounded-lg text-white font-medium transition-colors" title="Baixar planilha com os filtros atuais">
                <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                <span>Baixar Excel</span>
            </button>
            <button onclick="toggleFilters()" class="flex items-center gap-2 px-4 py-2 bg-dark-elevated border border-dark-border rounded-lg text-gray-300 hover:text-white hover:border-[#32e768] transition-colors">
                <i data-lucide="sliders-horizontal" class="w-5 h-5"></i>
                <span>Filtros</span>
                <span id="active-filters-count" class="hidden px-2 py-0.5 text-xs font-bold rounded-full bg-[#32e768] text-black">0</span>
            </button>
        </div>
    </div>

    <!-- Painel de Filtros Avançados -->
    <div id="filters-panel" class="hidden bg-dark-card p-6 rounded-lg shadow-md border border-dark-border mb-6 animate-fade-in">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                <i data-lucide="filter" class="w-5 h-5 text-[#32e768]"></i>
                Filtros Avançados
            </h3>
            <button onclick="clearAllFilters()" class="text-sm text-gray-400 hover:text-red-400 transition-colors flex items-center gap-1">
                <i data-lucide="x" class="w-4 h-4"></i>
                Limpar Filtros
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Filtro por Produto -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Produto</label>
                <select id="filter-produto" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
                    <option value="">Todos os produtos</option>
                    <?php foreach ($produtos_lista as $produto): ?>
                        <option value="<?php echo $produto['id']; ?>"><?php echo htmlspecialchars($produto['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filtro por Método de Pagamento -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Método de Pagamento</label>
                <select id="filter-metodo" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
                    <option value="">Todos os métodos</option>
                    <option value="Pix">Pix</option>
                    <option value="Cartão de crédito">Cartão de Crédito</option>
                    <option value="Boleto">Boleto</option>
                </select>
            </div>
            
            <!-- Filtro por Data Início -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Data Início</label>
                <input type="date" id="filter-data-inicio" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
            </div>
            
            <!-- Filtro por Data Fim -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Data Fim</label>
                <input type="date" id="filter-data-fim" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
            </div>
        </div>
        
        <!-- Segunda linha de filtros -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <!-- Filtro por Telefone -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Telefone</label>
                <input type="text" id="filter-telefone" placeholder="(00) 00000-0000" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-3 py-2 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
            </div>
            
            <!-- Filtro por Valor Mínimo -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Valor Mínimo (R$)</label>
                <input type="number" id="filter-valor-min" placeholder="0,00" step="0.01" min="0" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-3 py-2 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
            </div>
            
            <!-- Filtro por Valor Máximo -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Valor Máximo (R$)</label>
                <input type="number" id="filter-valor-max" placeholder="0,00" step="0.01" min="0" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-3 py-2 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
            </div>
            
            <!-- Botão Aplicar -->
            <div class="flex items-end">
                <button onclick="applyFilters()" class="w-full px-4 py-2 rounded-lg font-semibold text-white transition-colors flex items-center justify-center gap-2" style="background-color: var(--accent-primary);" onmouseover="this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="this.style.backgroundColor='var(--accent-primary)'">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    Aplicar Filtros
                </button>
            </div>
        </div>
        
        <!-- Filtros Rápidos de Data -->
        <div class="mt-4 pt-4 border-t border-dark-border">
            <span class="text-sm text-gray-400 mr-3">Período rápido:</span>
            <div class="inline-flex flex-wrap gap-2 mt-2">
                <button onclick="setQuickDateFilter('today')" class="px-3 py-1 text-sm bg-dark-elevated border border-dark-border text-gray-300 rounded-lg hover:border-[#32e768] hover:text-white transition-colors">Hoje</button>
                <button onclick="setQuickDateFilter('yesterday')" class="px-3 py-1 text-sm bg-dark-elevated border border-dark-border text-gray-300 rounded-lg hover:border-[#32e768] hover:text-white transition-colors">Ontem</button>
                <button onclick="setQuickDateFilter('7days')" class="px-3 py-1 text-sm bg-dark-elevated border border-dark-border text-gray-300 rounded-lg hover:border-[#32e768] hover:text-white transition-colors">Últimos 7 dias</button>
                <button onclick="setQuickDateFilter('30days')" class="px-3 py-1 text-sm bg-dark-elevated border border-dark-border text-gray-300 rounded-lg hover:border-[#32e768] hover:text-white transition-colors">Últimos 30 dias</button>
                <button onclick="setQuickDateFilter('month')" class="px-3 py-1 text-sm bg-dark-elevated border border-dark-border text-gray-300 rounded-lg hover:border-[#32e768] hover:text-white transition-colors">Este mês</button>
                <button onclick="setQuickDateFilter('lastmonth')" class="px-3 py-1 text-sm bg-dark-elevated border border-dark-border text-gray-300 rounded-lg hover:border-[#32e768] hover:text-white transition-colors">Mês passado</button>
            </div>
        </div>
    </div>

    <!-- Cards de Métricas -->
    <div class="grid grid-cols-2 md:grid-cols-7 gap-4 mb-6">
        <div data-status="all" class="metric-card p-4 bg-dark-card rounded-lg shadow-md cursor-pointer border-2" style="border-color: var(--accent-primary);">
            <h3 class="text-gray-400 text-sm font-medium">Total de Vendas</h3>
            <p id="metric-all" class="text-2xl font-bold text-white">0</p>
        </div>
        <div data-status="approved" class="metric-card p-4 bg-dark-card rounded-lg shadow-md cursor-pointer border-2 border-transparent" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='transparent'">
            <h3 class="text-gray-400 text-sm font-medium">Aprovadas</h3>
            <p id="metric-approved" class="text-2xl font-bold text-green-400">0</p>
        </div>
        <div data-status="manual" class="metric-card p-4 bg-dark-card rounded-lg shadow-md cursor-pointer border-2 border-transparent" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='transparent'">
            <h3 class="text-gray-400 text-sm font-medium">Manuais</h3>
            <p id="metric-manual" class="text-2xl font-bold text-cyan-400">0</p>
        </div>
        <div data-status="abandoned_all" class="metric-card p-4 bg-dark-card rounded-lg shadow-md cursor-pointer border-2 border-transparent" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='transparent'">
            <h3 class="text-gray-400 text-sm font-medium">Abandonadas</h3>
            <p id="metric-abandoned_all" class="text-2xl font-bold text-red-400">0</p>
        </div>
        <div data-status="info_filled" class="metric-card p-4 bg-dark-card rounded-lg shadow-md cursor-pointer border-2 border-transparent" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='transparent'">
            <h3 class="text-gray-400 text-sm font-medium">Checkout Aband.</h3>
            <p id="metric-info_filled" class="text-2xl font-bold text-red-300">0</p>
        </div>
        <div data-status="refunded" class="metric-card p-4 bg-dark-card rounded-lg shadow-md cursor-pointer border-2 border-transparent" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='transparent'">
            <h3 class="text-gray-400 text-sm font-medium">Reembolsadas</h3>
            <p id="metric-refunded" class="text-2xl font-bold text-purple-400">0</p>
        </div>
        <div data-status="charged_back" class="metric-card p-4 bg-dark-card rounded-lg shadow-md cursor-pointer border-2 border-transparent" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='transparent'">
            <h3 class="text-gray-400 text-sm font-medium">Chargeback</h3>
            <p id="metric-charged_back" class="text-2xl font-bold text-pink-400">0</p>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-dark-card p-6 rounded-lg shadow-md border" style="border-color: var(--accent-primary);">
        <div class="mb-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
                </div>
                <input type="text" id="search-input" class="block w-full pl-10 pr-3 py-2 border border-dark-border rounded-md leading-5 bg-dark-elevated placeholder-gray-500 text-white focus:outline-none focus:placeholder-gray-400 focus:ring-1 sm:text-sm" style="--tw-ring-color: var(--accent-primary);" onfocus="this.style.borderColor='var(--accent-primary)'; this.style.boxShadow='0 0 0 1px var(--accent-primary)'" onblur="this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'" placeholder="Pesquisar por nome, e-mail, telefone ou ID...">
            </div>
        </div>

        <div id="table-wrapper" class="overflow-x-auto" style="display: none;">
            <table class="min-w-full divide-y divide-dark-border">
                <thead class="bg-dark-elevated">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Produto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Método</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody id="vendas-tbody" class="bg-dark-card divide-y divide-dark-border"></tbody>
            </table>
        </div>

        <div id="loading-state" class="text-center py-12 text-gray-400">
            <svg class="animate-spin h-8 w-8 mx-auto" style="color: var(--accent-primary);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path></svg>
            <p class="mt-4 font-medium">Carregando...</p>
        </div>
        <div id="error-state" class="text-center py-12 text-red-400 hidden">
            <i data-lucide="alert-circle" class="mx-auto w-12 h-12"></i>
            <p class="mt-4 font-medium" id="error-state-message">Erro ao carregar vendas.</p>
            <button type="button" onclick="window.fetchVendasData && window.fetchVendasData()" class="mt-4 px-4 py-2 bg-dark-elevated border border-dark-border rounded-lg text-gray-300 hover:text-white transition-colors">Tentar novamente</button>
        </div>
        <div id="empty-state" class="text-center py-12 text-gray-400" style="display: none;">
            <i data-lucide="inbox" class="mx-auto w-16 h-16 text-gray-500"></i>
            <p class="mt-4">Nenhuma venda encontrada.</p>
        </div>

        <div id="pagination-controls" class="hidden mt-4 flex items-center justify-between">
            <button id="prev-page" class="relative inline-flex items-center px-4 py-2 border border-dark-border text-sm font-medium rounded-md text-gray-300 bg-dark-elevated hover:bg-dark-card">Anterior</button>
            <span id="page-info" class="text-sm text-gray-300">Página 1 de 1</span>
            <button id="next-page" class="ml-3 relative inline-flex items-center px-4 py-2 border border-dark-border text-sm font-medium rounded-md text-gray-300 bg-dark-elevated hover:bg-dark-card">Próximo</button>
        </div>
    </div>
</div>

<!-- MODAL DE REENVIAR ACESSO -->
<div id="resend-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-dark-card rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border" style="border-color: var(--accent-primary);">
            <div class="bg-dark-card px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-[#32e768]/20 sm:mx-0 sm:h-10 sm:w-10">
                        <i data-lucide="mail" class="h-6 w-6" style="color: var(--accent-primary);"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">Reenviar Acesso</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-400 mb-4">O cliente cadastrou o e-mail errado? Corrija abaixo para reenviar o acesso.</p>
                            <label for="modal-email-input" class="block text-sm font-medium text-gray-300">E-mail de Destino</label>
                            <input type="email" id="modal-email-input" class="mt-1 focus:ring-primary focus:border-primary block w-full shadow-sm sm:text-sm border-dark-border rounded-md border p-2 bg-dark-elevated text-white placeholder-gray-500" placeholder="email@cliente.com">
                            <input type="hidden" id="modal-venda-id">
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-dark-elevated px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirm-resend-btn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm" style="background-color: var(--accent-primary);" onmouseover="this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="this.style.backgroundColor='var(--accent-primary)'" onfocus="this.style.boxShadow='0 0 0 2px var(--accent-primary)'" onblur="this.style.boxShadow='none'">
                    Enviar Agora
                </button>
                <button type="button" id="cancel-resend-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-dark-border shadow-sm px-4 py-2 bg-dark-card text-base font-medium text-gray-300 hover:bg-dark-elevated focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE EDITAR VENDA -->
<div id="edit-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="edit-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="closeEditModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-dark-card rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border" style="border-color: var(--accent-primary);">
            <div class="bg-dark-card px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-[#32e768]/20 sm:mx-0 sm:h-10 sm:w-10">
                        <i data-lucide="edit" class="h-6 w-6" style="color: var(--accent-primary);"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-white" id="edit-modal-title">Editar Venda</h3>
                        <div class="mt-4 space-y-4">
                            <input type="hidden" id="edit-venda-id">
                            <div>
                                <label for="edit-nome" class="block text-sm font-medium text-gray-300">Nome do Cliente</label>
                                <input type="text" id="edit-nome" class="mt-1 block w-full shadow-sm sm:text-sm border-dark-border rounded-md border p-2 bg-dark-elevated text-white placeholder-gray-500 focus:ring-[#32e768] focus:border-[#32e768]" placeholder="Nome completo">
                            </div>
                            <div>
                                <label for="edit-email" class="block text-sm font-medium text-gray-300">E-mail</label>
                                <input type="email" id="edit-email" class="mt-1 block w-full shadow-sm sm:text-sm border-dark-border rounded-md border p-2 bg-dark-elevated text-white placeholder-gray-500 focus:ring-[#32e768] focus:border-[#32e768]" placeholder="email@cliente.com">
                            </div>
                            <div>
                                <label for="edit-telefone" class="block text-sm font-medium text-gray-300">Telefone</label>
                                <input type="text" id="edit-telefone" class="mt-1 block w-full shadow-sm sm:text-sm border-dark-border rounded-md border p-2 bg-dark-elevated text-white placeholder-gray-500 focus:ring-[#32e768] focus:border-[#32e768]" placeholder="(00) 00000-0000">
                            </div>
                            <div>
                                <label for="edit-status" class="block text-sm font-medium text-gray-300">Status</label>
                                <select id="edit-status" class="mt-1 block w-full shadow-sm sm:text-sm border-dark-border rounded-md border p-2 bg-dark-elevated text-white focus:ring-[#32e768] focus:border-[#32e768]">
                                    <option value="approved">Aprovada</option>
                                    <option value="pending">Pendente</option>
                                    <option value="in_process">Em Processamento</option>
                                    <option value="rejected">Rejeitada</option>
                                    <option value="cancelled">Cancelada</option>
                                    <option value="refunded">Reembolsada</option>
                                    <option value="charged_back">Chargeback</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-dark-elevated px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirm-edit-btn" onclick="confirmEditVenda()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none sm:ml-3 sm:w-auto sm:text-sm" style="background-color: var(--accent-primary);">
                    Salvar Alterações
                </button>
                <button type="button" onclick="closeEditModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-dark-border shadow-sm px-4 py-2 bg-dark-card text-base font-medium text-gray-300 hover:bg-dark-elevated sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE DELETAR VENDA -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="delete-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="closeDeleteModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-dark-card rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-red-500">
            <div class="bg-dark-card px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-500/20 sm:mx-0 sm:h-10 sm:w-10">
                        <i data-lucide="trash-2" class="h-6 w-6 text-red-500"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-white" id="delete-modal-title">Excluir Venda</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-400">Tem certeza que deseja excluir a venda de <span id="delete-cliente-nome" class="font-semibold text-white"></span>?</p>
                            <p class="text-sm text-red-400 mt-2">Esta ação não pode ser desfeita.</p>
                            <input type="hidden" id="delete-venda-id">
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-dark-elevated px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirm-delete-btn" onclick="confirmDeleteVenda()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    Excluir
                </button>
                <button type="button" onclick="closeDeleteModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-dark-border shadow-sm px-4 py-2 bg-dark-card text-base font-medium text-gray-300 hover:bg-dark-elevated sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE DELETAR ACESSO MANUAL -->
<div id="delete-manual-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="delete-manual-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="closeDeleteManualModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-dark-card rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-red-500">
            <div class="bg-dark-card px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-500/20 sm:mx-0 sm:h-10 sm:w-10">
                        <i data-lucide="trash-2" class="h-6 w-6 text-red-500"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-white" id="delete-manual-modal-title">Excluir Cliente Manual</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-400">Tem certeza que deseja excluir <span id="delete-manual-cliente-nome" class="font-semibold text-white"></span>?</p>
                            <p class="text-sm text-red-400 mt-2">Esta ação irá remover o acesso, progresso e dados do cliente. Não pode ser desfeita.</p>
                            <input type="hidden" id="delete-manual-acesso-id">
                            <input type="hidden" id="delete-manual-cliente-email">
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-dark-elevated px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirm-delete-manual-btn" onclick="confirmDeleteManualAccess()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    Excluir
                </button>
                <button type="button" onclick="closeDeleteManualModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-dark-border shadow-sm px-4 py-2 bg-dark-card text-base font-medium text-gray-300 hover:bg-dark-elevated sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('vendas-tbody');
    const loadingState = document.getElementById('loading-state');
    const emptyState = document.getElementById('empty-state');
    const errorState = document.getElementById('error-state');
    const tableWrapper = document.getElementById('table-wrapper');
    const searchInput = document.getElementById('search-input');
    const metricCardsContainer = document.querySelector('.grid.grid-cols-2.md\\:grid-cols-7');
    
    // Paginação
    const paginationControls = document.getElementById('pagination-controls');
    const pageInfo = document.getElementById('page-info');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');

    // Modal Elements
    const resendModal = document.getElementById('resend-modal');
    const modalEmailInput = document.getElementById('modal-email-input');
    const modalVendaId = document.getElementById('modal-venda-id');
    const confirmResendBtn = document.getElementById('confirm-resend-btn');
    const cancelResendBtn = document.getElementById('cancel-resend-btn');

    let state = { 
        status: 'all', 
        search: '', 
        page: 1,
        produto_id: '',
        metodo_pagamento: '',
        data_inicio: '',
        data_fim: '',
        telefone: '',
        valor_min: '',
        valor_max: ''
    };
    let debounceTimer;

    const statusBadges = {
        'approved': 'bg-green-100 text-green-800', 
        'paid': 'bg-green-100 text-green-800', // ADICIONADO: Tratamento para 'paid' igual a approved
        'completed': 'bg-green-100 text-green-800', // ADICIONADO: Tratamento para 'completed' igual a approved
        'pending': 'bg-yellow-100 text-yellow-800',
        'in_process': 'bg-blue-100 text-blue-800', 
        'rejected': 'bg-red-100 text-red-800',
        'cancelled': 'bg-gray-100 text-gray-800', 
        'refunded': 'bg-purple-100 text-purple-800',
        'charged_back': 'bg-pink-100 text-pink-800', 
        'info_filled': 'bg-indigo-100 text-indigo-800',
        'abandoned_all': 'bg-red-100 text-red-800'
    };
    
    const paymentMethodIcons = {
        'Pix': 'https://img.icons8.com/color/48/pix.png',
        'Cartão de crédito': 'https://img.icons8.com/color/48/bank-cards.png',
        'Boleto': 'https://img.icons8.com/color/48/barcode.png',
        'Não informado': 'https://img.icons8.com/material-outlined/48/help.png'
    };

    const formatStatusText = (status) => {
        const map = { 
            'approved': 'Aprovada', 
            'paid': 'Aprovada', // ADICIONADO: Traduz 'paid' para Aprovada
            'completed': 'Aprovada', // ADICIONADO
            'pending': 'Pendente', 
            'in_process': 'Em Processamento', 
            'rejected': 'Rejeitada', 
            'cancelled': 'Cancelada', 
            'refunded': 'Reembolsada', 
            'charged_back': 'Chargeback', 
            'info_filled': 'Info. Preenchidas' 
        };
        return map[status] || status;
    };

    const buildVendasParams = (forExport) => {
            const params = new URLSearchParams({
                action: forExport ? 'export_vendas_excel' : 'get_vendas',
                status: state.status,
                search: state.search,
                page: forExport ? '1' : state.page
            });
            if (state.produto_id) params.append('produto_id', state.produto_id);
            if (state.metodo_pagamento) params.append('metodo_pagamento', state.metodo_pagamento);
            if (state.data_inicio) params.append('data_inicio', state.data_inicio);
            if (state.data_fim) params.append('data_fim', state.data_fim);
            if (state.telefone) params.append('telefone', state.telefone);
            if (state.valor_min) params.append('valor_min', state.valor_min);
            if (state.valor_max) params.append('valor_max', state.valor_max);
            return params;
        };

    const fetchVendas = async () => {
        loadingState.style.display = 'block';
        emptyState.style.display = 'none';
        if (errorState) errorState.classList.add('hidden');
        if (tableWrapper) tableWrapper.style.display = 'none';
        paginationControls.classList.add('hidden');
        tbody.innerHTML = '';
        
        try {
            const params = buildVendasParams(false);
            const url = `/api/api?${params.toString()}`;
            const response = await fetch(url, { credentials: 'same-origin' });
            
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok) {
                const text = await response.text();
                throw new Error(response.status === 403 ? 'Sessão expirada. Faça login novamente.' : (text || 'Erro ao carregar vendas.'));
            }
            if (!contentType.includes('application/json')) {
                throw new Error('Resposta inválida do servidor.');
            }
            const data = await response.json();
            if (data.error) throw new Error(data.error);

            document.getElementById('metric-all').textContent = data.metrics.all || 0;
            document.getElementById('metric-approved').textContent = data.metrics.approved || 0;
            document.getElementById('metric-refunded').textContent = data.metrics.refunded || 0;
            document.getElementById('metric-charged_back').textContent = data.metrics.charged_back || 0;
            document.getElementById('metric-abandoned_all').textContent = data.metrics.abandoned_all || 0;
            document.getElementById('metric-info_filled').textContent = data.metrics.info_filled || 0;
            
            // Atualizar métrica de manuais se existir
            const metricManual = document.getElementById('metric-manual');
            if (metricManual) {
                metricManual.textContent = data.metrics.manual || 0;
            }

            if (data.vendas.length === 0) {
                emptyState.style.display = 'block';
            } else {
                if (tableWrapper) tableWrapper.style.display = 'block';
                data.vendas.forEach(venda => {
                    const tr = document.createElement('tr');
                    const valorFormatado = venda.criado_manualmente == 1 ? 'Acesso Manual' : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(venda.valor);
                    const dataFormatada = new Date(venda.data_venda).toLocaleString('pt-BR');
                    const badgeClass = statusBadges[venda.status_pagamento] || 'bg-gray-100 text-gray-800';
                    const metodo = venda.metodo_pagamento || 'Não informado';
                    const iconUrl = paymentMethodIcons[metodo] || paymentMethodIcons['Não informado'];
                    
                    // Badge manual
                    const manualBadge = venda.criado_manualmente == 1 
                        ? '<span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-cyan-100 text-cyan-800">Manual</span>' 
                        : '';

                    let whatsappLink = '#';
                    let whatsappClass = 'opacity-50 cursor-not-allowed';
                    if (venda.comprador_telefone) {
                        const cleanPhone = venda.comprador_telefone.replace(/\D/g, '');
                        if (cleanPhone.length >= 10) {
                           whatsappLink = `https://api.whatsapp.com/send?phone=55${cleanPhone}&text=Ol%C3%A1%2C%20${encodeURIComponent(venda.comprador_nome)}%21%20Referente%20%C3%A0%20sua%20compra%3A`;
                           whatsappClass = 'text-green-600 hover:text-green-800';
                        }
                    }
                    
                    // Lógica dos botões
                    let actionsHtml = '';
                    
                    // Não mostrar botões de ação para acessos manuais (ID começa com M)
                    const isManual = String(venda.id).startsWith('M');
                    
                    // Botão Reenviar Acesso (Para approved OU paid) - não para manuais
                    if (!isManual && (venda.status_pagamento === 'approved' || venda.status_pagamento === 'paid' || venda.status_pagamento === 'completed')) {
                        actionsHtml += `
                            <button class="resend-btn p-1" style="color: var(--accent-primary);" onmouseover="this.style.color='var(--accent-primary-hover)'" onmouseout="this.style.color='var(--accent-primary)'" title="Reenviar Acesso" onclick="openResendModal(${venda.id}, '${venda.comprador_email}')">
                                <i data-lucide="mail" class="w-5 h-5"></i>
                            </button>
                        `;
                    }

                    // Botão Aprovar (Só se NÃO for aprovada/paga) - não para manuais
                    if (!isManual && venda.status_pagamento !== 'approved' && venda.status_pagamento !== 'paid' && venda.status_pagamento !== 'completed') {
                        actionsHtml += `
                            <button class="approve-btn text-green-600 hover:text-green-800 p-1" title="Aprovar Manualmente" data-venda-id="${venda.id}" onclick="approveSale(${venda.id})">
                                <i data-lucide="check-circle" class="w-5 h-5"></i>
                            </button>`;
                    }

                    tr.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-white">${venda.comprador_nome || 'Não informado'}${manualBadge}</div>
                            <div class="text-sm text-gray-400">${venda.comprador_email || ''}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-300">${venda.produto_nome || 'Produto não encontrado'}</div></td>
                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-semibold ${venda.criado_manualmente == 1 ? 'text-cyan-400' : 'text-white'}">${valorFormatado}</div></td>
                        <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-300 flex items-center">${venda.criado_manualmente == 1 ? '<i data-lucide="user-plus" class="w-5 h-5 mr-2 text-cyan-400"></i>' : '<img src="' + iconUrl + '" class="w-5 h-5 mr-2">'} ${metodo}</div></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">${dataFormatada}</td>
                        <td class="px-6 py-4 whitespace-nowrap"><span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${badgeClass}">${formatStatusText(venda.status_pagamento)}</span></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex items-center space-x-2">
                            ${isManual ? `
                            <button onclick="openDeleteManualModal('${venda.id}', '${(venda.comprador_nome || 'este cliente').replace(/'/g, "\\'")}', '${(venda.comprador_email || '').replace(/'/g, "\\'")}')" class="p-1.5 text-red-500 hover:text-red-400 hover:bg-red-500/10 rounded transition-colors" title="Excluir Cliente">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </button>
                            ` : `
                            <button onclick="openEditModal(${venda.id}, '${(venda.comprador_nome || '').replace(/'/g, "\\'")}', '${venda.comprador_email || ''}', '${venda.comprador_telefone || ''}', '${venda.status_pagamento}')" class="p-1.5 text-[#32e768] hover:text-[#28d15e] hover:bg-[#32e768]/10 rounded transition-colors" title="Editar Venda">
                                <i data-lucide="edit" class="w-5 h-5"></i>
                            </button>
                            <button onclick="openDeleteModal(${venda.id}, '${(venda.comprador_nome || 'esta venda').replace(/'/g, "\\'")}')" class="p-1.5 text-red-500 hover:text-red-400 hover:bg-red-500/10 rounded transition-colors" title="Excluir Venda">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </button>
                            ${actionsHtml}
                            `}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
                updatePagination(data.pagination);
                lucide.createIcons();
            }
        } catch (error) {
            console.error('Erro:', error);
            if (errorState) {
                const msg = document.getElementById('error-state-message');
                if (msg) msg.textContent = error.message || 'Erro ao carregar vendas.';
                errorState.classList.remove('hidden');
            }
        } finally {
            loadingState.style.display = 'none';
        }
    };

    // Botão Exportar Excel: usa os mesmos filtros e abre o download
    const btnExport = document.getElementById('btn-export-excel');
    if (btnExport) {
        btnExport.addEventListener('click', function(e) {
            e.preventDefault();
            const params = buildVendasParams(true);
            const url = `/api/api?${params.toString()}`;
            window.open(url, '_blank', 'noopener');
        });
    }

    function updatePagination(data) {
        if (!data || data.totalPages <= 1) { paginationControls.classList.add('hidden'); return; }
        paginationControls.classList.remove('hidden');
        pageInfo.textContent = `Página ${data.currentPage} de ${data.totalPages}`;
        prevPageBtn.disabled = data.currentPage <= 1;
        nextPageBtn.disabled = data.currentPage >= data.totalPages;
    }

    // --- FUNÇÕES DE AÇÃO ---
    
    window.approveSale = async (vendaId) => {
        if (!confirm('Tem certeza que deseja APROVAR esta venda manualmente? O cliente receberá o acesso.')) return;
        
        try {
            const res = await fetch('/api/vendas_actions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=approve&venda_id=${vendaId}`
            });
            const result = await res.json();
            if (result.success) {
                alert('Venda aprovada com sucesso!');
                fetchVendas(); // Recarrega a tabela
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (e) { alert('Erro de conexão.'); }
    };

    window.openResendModal = (vendaId, currentEmail) => {
        modalVendaId.value = vendaId;
        modalEmailInput.value = currentEmail;
        resendModal.classList.remove('hidden');
    };

    confirmResendBtn.addEventListener('click', async () => {
        const vendaId = modalVendaId.value;
        const newEmail = modalEmailInput.value;
        
        if(!newEmail) { alert('Digite um e-mail válido.'); return; }
        
        confirmResendBtn.disabled = true;
        confirmResendBtn.textContent = "Enviando...";
        
        try {
            const res = await fetch('/api/vendas_actions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=resend&venda_id=${vendaId}&email=${encodeURIComponent(newEmail)}`
            });
            const result = await res.json();
            if (result.success) {
                alert('Acesso reenviado com sucesso para: ' + newEmail);
                resendModal.classList.add('hidden');
                fetchVendas(); // Atualiza caso o email tenha mudado na visualização
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (e) { alert('Erro de conexão.'); }
        finally {
            confirmResendBtn.disabled = false;
            confirmResendBtn.textContent = "Enviar Agora";
        }
    });

    cancelResendBtn.addEventListener('click', () => {
        resendModal.classList.add('hidden');
    });

    // --- FUNÇÕES DE EDITAR E DELETAR ---
    
    window.openEditModal = (vendaId, nome, email, telefone, status) => {
        document.getElementById('edit-venda-id').value = vendaId;
        document.getElementById('edit-nome').value = nome || '';
        document.getElementById('edit-email').value = email || '';
        document.getElementById('edit-telefone').value = telefone || '';
        document.getElementById('edit-status').value = status || 'pending';
        document.getElementById('edit-modal').classList.remove('hidden');
        lucide.createIcons();
    };
    
    window.closeEditModal = () => {
        document.getElementById('edit-modal').classList.add('hidden');
    };
    
    window.confirmEditVenda = async () => {
        const vendaId = document.getElementById('edit-venda-id').value;
        const nome = document.getElementById('edit-nome').value;
        const email = document.getElementById('edit-email').value;
        const telefone = document.getElementById('edit-telefone').value;
        const status = document.getElementById('edit-status').value;
        
        const btn = document.getElementById('confirm-edit-btn');
        btn.disabled = true;
        btn.textContent = 'Salvando...';
        
        try {
            const res = await fetch('/api/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_venda',
                    venda_id: vendaId,
                    comprador_nome: nome,
                    comprador_email: email,
                    comprador_telefone: telefone,
                    status_pagamento: status
                })
            });
            const result = await res.json();
            if (result.success) {
                alert('Venda atualizada com sucesso!');
                closeEditModal();
                fetchVendas();
            } else {
                alert('Erro: ' + (result.error || 'Erro ao atualizar venda'));
            }
        } catch (e) {
            console.error(e);
            alert('Erro de conexão.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar Alterações';
        }
    };
    
    window.openDeleteModal = (vendaId, clienteNome) => {
        document.getElementById('delete-venda-id').value = vendaId;
        document.getElementById('delete-cliente-nome').textContent = clienteNome;
        document.getElementById('delete-modal').classList.remove('hidden');
        lucide.createIcons();
    };
    
    window.closeDeleteModal = () => {
        document.getElementById('delete-modal').classList.add('hidden');
    };
    
    window.confirmDeleteVenda = async () => {
        const vendaId = document.getElementById('delete-venda-id').value;
        
        const btn = document.getElementById('confirm-delete-btn');
        btn.disabled = true;
        btn.textContent = 'Excluindo...';
        
        try {
            const res = await fetch('/api/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_venda',
                    venda_id: vendaId
                })
            });
            const result = await res.json();
            if (result.success) {
                alert('Venda excluída com sucesso!');
                closeDeleteModal();
                fetchVendas();
            } else {
                alert('Erro: ' + (result.error || 'Erro ao excluir venda'));
            }
        } catch (e) {
            console.error(e);
            alert('Erro de conexão.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Excluir';
        }
    };
    
    // Funções para modal de deletar acesso manual
    window.openDeleteManualModal = (acessoId, clienteNome, clienteEmail) => {
        document.getElementById('delete-manual-acesso-id').value = acessoId;
        document.getElementById('delete-manual-cliente-nome').textContent = clienteNome;
        document.getElementById('delete-manual-cliente-email').value = clienteEmail || '';
        document.getElementById('delete-manual-modal').classList.remove('hidden');
        lucide.createIcons();
    };
    
    window.closeDeleteManualModal = () => {
        document.getElementById('delete-manual-modal').classList.add('hidden');
    };
    
    window.confirmDeleteManualAccess = async () => {
        const acessoId = document.getElementById('delete-manual-acesso-id').value;
        const clienteEmail = document.getElementById('delete-manual-cliente-email').value;
        // Remove o prefixo 'M' para obter o ID real
        const realId = acessoId.replace('M', '');
        
        const btn = document.getElementById('confirm-delete-manual-btn');
        btn.disabled = true;
        btn.textContent = 'Excluindo...';
        
        try {
            const res = await fetch('/api/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_manual_access',
                    acesso_id: realId,
                    email: clienteEmail
                })
            });
            const result = await res.json();
            if (result.success) {
                alert('Cliente excluído com sucesso!');
                closeDeleteManualModal();
                fetchVendas();
            } else {
                alert('Erro: ' + (result.error || 'Erro ao excluir cliente'));
            }
        } catch (e) {
            console.error(e);
            alert('Erro de conexão.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Excluir';
        }
    };

    // Listeners de Filtro
    metricCardsContainer.addEventListener('click', (e) => {
        const card = e.target.closest('.metric-card');
        if (card) {
            document.querySelectorAll('.metric-card').forEach(c => { 
                c.style.borderColor = 'transparent';
                c.onmouseover = function() { this.style.borderColor = 'var(--accent-primary)'; };
                c.onmouseout = function() { if (!c.classList.contains('active')) this.style.borderColor = 'transparent'; };
            });
            card.style.borderColor = 'var(--accent-primary)';
            card.classList.add('active');
            state.status = card.dataset.status; state.page = 1; fetchVendas();
        }
    });
    searchInput.addEventListener('keyup', () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => { state.search = searchInput.value; state.page = 1; fetchVendas(); }, 500); });
    prevPageBtn.addEventListener('click', () => { if (state.page > 1) { state.page--; fetchVendas(); } });
    nextPageBtn.addEventListener('click', () => { if (!nextPageBtn.disabled) { state.page++; fetchVendas(); } });

    // Expor funções e state globalmente para os filtros
    window.vendasState = state;
    window.fetchVendasData = fetchVendas;

    fetchVendas();
});

// --- FUNÇÕES DE FILTROS AVANÇADOS ---

function toggleFilters() {
    const panel = document.getElementById('filters-panel');
    panel.classList.toggle('hidden');
    lucide.createIcons();
}

function applyFilters() {
    const state = window.vendasState;
    if (!state) return;
    
    // Atualizar state com valores dos filtros
    state.produto_id = document.getElementById('filter-produto').value;
    state.metodo_pagamento = document.getElementById('filter-metodo').value;
    state.data_inicio = document.getElementById('filter-data-inicio').value;
    state.data_fim = document.getElementById('filter-data-fim').value;
    state.telefone = document.getElementById('filter-telefone').value;
    state.valor_min = document.getElementById('filter-valor-min').value;
    state.valor_max = document.getElementById('filter-valor-max').value;
    state.page = 1;
    
    // Contar filtros ativos
    let activeCount = 0;
    if (state.produto_id) activeCount++;
    if (state.metodo_pagamento) activeCount++;
    if (state.data_inicio) activeCount++;
    if (state.data_fim) activeCount++;
    if (state.telefone) activeCount++;
    if (state.valor_min) activeCount++;
    if (state.valor_max) activeCount++;
    
    // Atualizar badge de filtros ativos
    const badge = document.getElementById('active-filters-count');
    if (activeCount > 0) {
        badge.textContent = activeCount;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
    
    // Buscar vendas
    if (window.fetchVendasData) {
        window.fetchVendasData();
    }
}

function clearAllFilters() {
    document.getElementById('filter-produto').value = '';
    document.getElementById('filter-metodo').value = '';
    document.getElementById('filter-data-inicio').value = '';
    document.getElementById('filter-data-fim').value = '';
    document.getElementById('filter-telefone').value = '';
    document.getElementById('filter-valor-min').value = '';
    document.getElementById('filter-valor-max').value = '';
    
    document.getElementById('active-filters-count').classList.add('hidden');
    
    // Limpar state
    const state = window.vendasState;
    if (state) {
        state.produto_id = '';
        state.metodo_pagamento = '';
        state.data_inicio = '';
        state.data_fim = '';
        state.telefone = '';
        state.valor_min = '';
        state.valor_max = '';
        state.page = 1;
        
        if (window.fetchVendasData) {
            window.fetchVendasData();
        }
    }
}

function setQuickDateFilter(period) {
    const today = new Date();
    let startDate, endDate;
    
    switch(period) {
        case 'today':
            startDate = endDate = today.toISOString().split('T')[0];
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            startDate = endDate = yesterday.toISOString().split('T')[0];
            break;
        case '7days':
            endDate = today.toISOString().split('T')[0];
            const sevenDaysAgo = new Date(today);
            sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 6);
            startDate = sevenDaysAgo.toISOString().split('T')[0];
            break;
        case '30days':
            endDate = today.toISOString().split('T')[0];
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 29);
            startDate = thirtyDaysAgo.toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDate = today.toISOString().split('T')[0];
            break;
        case 'lastmonth':
            const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            startDate = lastMonth.toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), today.getMonth(), 0).toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('filter-data-inicio').value = startDate;
    document.getElementById('filter-data-fim').value = endDate;
    applyFilters();
}
</script>
