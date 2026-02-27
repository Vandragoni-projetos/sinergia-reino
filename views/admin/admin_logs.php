<?php
/**
 * View de Logs do Sistema
 * Visualização e gerenciamento de logs para administradores
 */

require_once __DIR__ . '/../../helpers/log_viewer.php';

$log_config = get_log_config();
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2">📊 Logs do Sistema</h1>
                <p class="text-gray-400">Visualize e gerencie os logs de atividades e erros do sistema</p>
            </div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                    <input type="checkbox" id="auto-refresh" class="form-checkbox">
                    <span>🔄 Auto-refresh</span>
                </label>
                <button onclick="refreshAllLogs()" class="px-4 py-2 bg-[#32e768] hover:bg-[#28d15e] text-white rounded-lg transition-colors flex items-center gap-2">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    Atualizar Tudo
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs de Categorias -->
    <div class="mb-6">
        <div class="flex gap-2 border-b border-gray-700">
            <button onclick="switchCategory('errors')" class="category-tab active px-6 py-3 text-white font-semibold border-b-2 border-[#32e768] transition-colors" data-category="errors">
                ⚠️ Logs
            </button>
            <!--
            <button onclick="switchCategory('activities')" class="category-tab px-6 py-3 text-gray-400 font-semibold border-b-2 border-transparent hover:text-white transition-colors" data-category="activities">
                📝 Atividades
            </button>
            <button onclick="switchCategory('security')" class="category-tab px-6 py-3 text-gray-400 font-semibold border-b-2 border-transparent hover:text-white transition-colors" data-category="security">
                🔒 Segurança
            </button>
             -->
        </div>
    </div>

    <!-- Container de Logs -->
    <div id="logs-container">
        <!-- Logs serão carregados aqui via JavaScript -->
    </div>
</div>


<style>
.log-card {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.log-card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    transition: background 0.2s;
}

.log-card-header:hover {
    background: rgba(50, 231, 104, 0.05);
}

.log-card-body {
    padding: 1.5rem;
    display: none;
}

.log-card.expanded .log-card-body {
    display: block;
}

.log-line {
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    background: rgba(15, 23, 42, 0.5);
    border-left: 3px solid transparent;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.log-line:hover {
    background: rgba(15, 23, 42, 0.8);
    transform: translateX(2px);
}

.log-line.error { border-left-color: #ef4444; }
.log-line.warning { border-left-color: #f59e0b; }
.log-line.success { border-left-color: #10b981; }
.log-line.info { border-left-color: #3b82f6; }
.log-line.debug { border-left-color: #6b7280; }

.category-tab.active {
    color: white;
    border-bottom-color: #32e768;
}

.pagination-btn {
    padding: 0.5rem 1rem;
    background: rgba(50, 231, 104, 0.1);
    border: 1px solid rgba(50, 231, 104, 0.3);
    color: #32e768;
    border-radius: 0.5rem;
    transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
    background: rgba(50, 231, 104, 0.2);
    border-color: #32e768;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

</style>

<script>
let currentCategory = 'errors';
let autoRefreshInterval = null;
let expandedLogs = new Set();

// Carregar logs ao iniciar
document.addEventListener('DOMContentLoaded', () => {
    loadLogs();
    
    // Auto-refresh
    document.getElementById('auto-refresh').addEventListener('change', (e) => {
        if (e.target.checked) {
            autoRefreshInterval = setInterval(() => {
                loadLogs();
            }, 30000); // 30 segundos
        } else {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
    });
    
    lucide.createIcons();
});

// Trocar categoria
function switchCategory(category) {
    currentCategory = category;
    
    // Atualizar tabs
    document.querySelectorAll('.category-tab').forEach(tab => {
        if (tab.dataset.category === category) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });
    
    loadLogs();
}

// Carregar logs
async function loadLogs() {
    try {
        const response = await fetch('/api/logs_api.php?action=list_logs');
        const result = await response.json();
        
        if (result.success) {
            renderLogs(result.logs[currentCategory] || {});
        } else {
            showNotification('Erro ao carregar logs: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao carregar logs', 'error');
    }
}

// Renderizar logs
function renderLogs(logs) {
    const container = document.getElementById('logs-container');
    
    if (Object.keys(logs).length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 text-gray-400">
                <i data-lucide="inbox" class="w-16 h-16 mx-auto mb-4 opacity-50"></i>
                <p>Nenhum log disponível nesta categoria</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    let html = '';
    
    for (const [fileName, config] of Object.entries(logs)) {
        const isExpanded = expandedLogs.has(fileName);
        const stats = config.stats;
        
        html += `
            <div class="log-card ${isExpanded ? 'expanded' : ''}" data-log="${fileName}">
                <div class="log-card-header" onclick="toggleLog('${fileName}')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i data-lucide="${config.icon}" class="w-5 h-5 text-${config.color}-400"></i>
                            <div>
                                <h3 class="text-lg font-semibold text-white">${config.name}</h3>
                                <p class="text-sm text-gray-400">${config.description}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="event.stopPropagation(); clearLog('${fileName}')" class="px-3 py-1.5 bg-red-600/20 hover:bg-red-600/40 text-red-400 hover:text-red-300 rounded-lg text-sm flex items-center gap-1.5 border border-red-600/40" title="Limpar este log (cria backup)">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    Limpar
                                </button>
                                <button type="button" onclick="event.stopPropagation(); downloadLog('${fileName}')" class="px-3 py-1.5 bg-blue-600/20 hover:bg-blue-600/40 text-blue-400 hover:text-blue-300 rounded-lg text-sm flex items-center gap-1.5 border border-blue-600/40" title="Download do log">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                    Download
                                </button>
                            </div>
                            <div class="text-right text-sm">
                                <div class="text-gray-300">📊 ${stats.lines} linhas | ${stats.size_formatted}</div>
                                <div class="text-gray-500">⏱️ ${stats.last_modified_formatted}</div>
                            </div>
                            <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400 transition-transform ${isExpanded ? 'rotate-180' : ''}"></i>
                        </div>
                    </div>
                </div>
                <div class="log-card-body" id="log-body-${fileName}">
                    <div class="flex items-center gap-2 mb-4">
                        <input type="text" placeholder="🔍 Buscar..." class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm" onkeyup="searchLog('${fileName}', this.value)">
                        <select class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm" onchange="filterLogByDate('${fileName}', this.value)">
                            <option value="">Todas as datas</option>
                            <option value="today">Hoje</option>
                            <option value="yesterday">Ontem</option>
                            <option value="week">Últimos 7 dias</option>
                            <option value="month">Últimos 30 dias</option>
                        </select>
                    </div>
                    <div id="log-content-${fileName}" class="min-h-[200px]">
                        <div class="text-center py-8 text-gray-400">
                            <i data-lucide="loader-2" class="w-8 h-8 mx-auto mb-2 animate-spin"></i>
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
    lucide.createIcons();
    
    // Carregar conteúdo dos logs expandidos (aguardar próximo ciclo do event loop)
    setTimeout(() => {
        expandedLogs.forEach(fileName => {
            loadLogContent(fileName);
        });
    }, 0);
}


// Toggle log expandido/colapsado
function toggleLog(fileName) {
    const card = document.querySelector(`[data-log="${fileName}"]`);
    const isExpanded = card.classList.contains('expanded');
    
    if (isExpanded) {
        card.classList.remove('expanded');
        expandedLogs.delete(fileName);
    } else {
        card.classList.add('expanded');
        expandedLogs.add(fileName);
        loadLogContent(fileName);
    }
    
    lucide.createIcons();
}

// Carregar conteúdo do log
async function loadLogContent(fileName, page = 1, search = '', dateFilter = '') {
    const contentDiv = document.getElementById(`log-content-${fileName}`);
    
    // Verificação de segurança: elemento pode não existir ainda
    if (!contentDiv) {
        console.warn(`Elemento log-content-${fileName} não encontrado no DOM`);
        return;
    }
    
    try {
        const params = new URLSearchParams({
            action: 'read_log',
            log_file: fileName,
            page: page,
            per_page: 50,
            search: search,
            date_filter: dateFilter
        });
        
        const response = await fetch(`/api/logs_api.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            renderLogContent(fileName, result.data);
        } else {
            contentDiv.innerHTML = `<div class="text-center py-8 text-red-400">Erro: ${result.error}</div>`;
        }
    } catch (error) {
        console.error('Erro:', error);
        contentDiv.innerHTML = `<div class="text-center py-8 text-red-400">Erro ao carregar log</div>`;
    }
}

// Renderizar conteúdo do log
function renderLogContent(fileName, data) {
    const contentDiv = document.getElementById(`log-content-${fileName}`);
    
    // Verificação de segurança: elemento pode não existir ainda
    if (!contentDiv) {
        console.warn(`Elemento log-content-${fileName} não encontrado no DOM`);
        return;
    }
    
    if (data.lines.length === 0) {
        contentDiv.innerHTML = `
            <div class="text-center py-8 text-gray-400">
                <i data-lucide="file-text" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                <p>Nenhuma linha encontrada</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    let html = '<div class="space-y-2">';
    
    data.lines.forEach(line => {
        const parsed = line.parsed;
        const levelClass = parsed.level.toLowerCase();
        const icon = getLogLevelIcon(parsed.level);
        
        html += `
            <div class="log-line ${levelClass}">
                <div class="flex items-start gap-3">
                    <i data-lucide="${icon}" class="w-4 h-4 mt-1 flex-shrink-0 ${getLogLevelColor(parsed.level)}"></i>
                    <div class="flex-1 min-w-0">
                        ${parsed.timestamp ? `<div class="text-xs text-gray-500 mb-1">${parsed.timestamp}</div>` : ''}
                        <div class="text-gray-300 break-words">${escapeHtml(parsed.message)}</div>
                    </div>
                    <button onclick="copyToClipboard(\`${escapeHtml(line.raw).replace(/`/g, '\\`')}\`)" class="text-gray-500 hover:text-white transition-colors" title="Copiar">
                        <i data-lucide="copy" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    // Paginação
    if (data.total_pages > 1) {
        html += `
            <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-700">
                <div class="text-sm text-gray-400">
                    Mostrando ${data.lines.length} de ${data.total} linhas
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="loadLogContent('${fileName}', ${data.page - 1})" ${data.page === 1 ? 'disabled' : ''} class="pagination-btn">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    <span class="text-sm text-gray-300 px-3">
                        Página ${data.page} de ${data.total_pages}
                    </span>
                    <button onclick="loadLogContent('${fileName}', ${data.page + 1})" ${data.page === data.total_pages ? 'disabled' : ''} class="pagination-btn">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        `;
    }
    
    contentDiv.innerHTML = html;
    lucide.createIcons();
}

// Buscar no log
let searchTimeouts = {};
function searchLog(fileName, query) {
    if (searchTimeouts[fileName]) {
        clearTimeout(searchTimeouts[fileName]);
    }
    
    searchTimeouts[fileName] = setTimeout(() => {
        const dateFilter = document.querySelector(`[data-log="${fileName}"] select`).value;
        loadLogContent(fileName, 1, query, dateFilter);
    }, 500);
}

// Filtrar por data
function filterLogByDate(fileName, dateFilter) {
    const search = document.querySelector(`[data-log="${fileName}"] input[type="text"]`).value;
    loadLogContent(fileName, 1, search, dateFilter);
}


// Download log
function downloadLog(fileName) {
    window.location.href = `/api/logs_api.php?action=download_log&log_file=${fileName}`;
    showNotification('Download iniciado', 'success');
}

// Limpar log
async function clearLog(fileName) {
    if (!confirm('Tem certeza que deseja limpar este log? Um backup será criado automaticamente.')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'clear_log');
        formData.append('log_file', fileName);
        
        const response = await fetch('/api/logs_api.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`Log limpo com sucesso! Backup: ${result.backup}`, 'success');
            loadLogContent(fileName);
            loadLogs(); // Atualizar estatísticas
        } else {
            showNotification('Erro ao limpar log: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao limpar log', 'error');
    }
}

// Atualizar todos os logs
function refreshAllLogs() {
    loadLogs();
    showNotification('Logs atualizados', 'success');
}

// Copiar para clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copiado para a área de transferência', 'success');
    }).catch(() => {
        showNotification('Erro ao copiar', 'error');
    });
}

// Helpers
function getLogLevelIcon(level) {
    const icons = {
        'ERROR': 'x-circle',
        'WARNING': 'alert-triangle',
        'SUCCESS': 'check-circle',
        'INFO': 'info',
        'DEBUG': 'bug'
    };
    return icons[level] || 'circle';
}

function getLogLevelColor(level) {
    const colors = {
        'ERROR': 'text-red-400',
        'WARNING': 'text-yellow-400',
        'SUCCESS': 'text-green-400',
        'INFO': 'text-blue-400',
        'DEBUG': 'text-gray-400'
    };
    return colors[level] || 'text-gray-400';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-semibold transition-all duration-300 ${
        type === 'success' ? 'bg-green-600' : 'bg-red-600'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>
