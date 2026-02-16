<?php
/**
 * Página de Geração de Licenças - Área de Membros
 * Só aparece se for o painel master legítimo
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/master_helper.php';

// Verifica login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /member_login");
    exit;
}

// Verifica se é cliente
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] !== 'usuario') {
    header("location: /");
    exit;
}

// Verifica se é o painel master LEGÍTIMO
if (!isMasterPanel()) {
    header("location: /member_area_dashboard");
    exit;
}

$cliente_email = strtolower($_SESSION['usuario']);
$cliente_nome = $_SESSION['nome'] ?? $cliente_email;

// Carrega configurações visuais
include __DIR__ . '/../../config/load_settings.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Licenças - GatewayPro</title>
    <?php
    $favicon_url_raw = getSystemSetting('favicon_url', '');
    if (!empty($favicon_url_raw)) {
        $favicon_url = '/' . ltrim($favicon_url_raw, '/');
        $favicon_ext = strtolower(pathinfo($favicon_url, PATHINFO_EXTENSION));
        $favicon_type = $favicon_ext === 'png' ? 'image/png' : ($favicon_ext === 'svg' ? 'image/svg+xml' : 'image/x-icon');
        echo '<link rel="icon" type="' . htmlspecialchars($favicon_type) . '" href="' . htmlspecialchars($favicon_url) . '">';
    }
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .license-key {
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-200 antialiased min-h-screen">

    <!-- Header -->
    <header class="sticky top-0 z-50 w-full border-b border-gray-700/50 bg-gray-900/70 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <a href="/member_area_dashboard" class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        <span>Voltar aos Cursos</span>
                    </a>
                </div>
                <div class="flex items-center space-x-5">
                    <span class="text-gray-300">Olá, <?php echo htmlspecialchars($cliente_nome); ?>!</span>
                    <a href="/member_logout" class="text-gray-400 hover:text-red-400 transition-colors">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto py-12 px-4 sm:px-6 lg:px-8 member-protected-content">
        
        <!-- Título -->
        <div class="mb-10">
            <h1 class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-green-400 to-green-500 mb-2">
                <i data-lucide="key" class="inline w-10 h-10 mr-2"></i>
                Minhas Licenças
            </h1>
            <p class="text-xl text-gray-400">
                Gere licenças de ativação para usar o GatewayPro em suas instalações.
            </p>
        </div>

        <!-- Loading -->
        <div id="loading" class="text-center py-12">
            <svg class="animate-spin h-10 w-10 text-green-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
            </svg>
            <p class="text-gray-400">Carregando suas licenças...</p>
        </div>

        <!-- Conteúdo -->
        <div id="content" class="hidden">
            
            <!-- Seção: Gerar Nova Licença -->
            <div class="bg-gray-800 rounded-2xl border border-gray-700 p-6 mb-8">
                <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-6 h-6 text-green-400"></i>
                    Gerar Nova Licença
                </h2>
                
                <div id="no-rights" class="hidden text-center py-8">
                    <i data-lucide="alert-circle" class="w-12 h-12 text-yellow-400 mx-auto mb-3"></i>
                    <p class="text-gray-400">Você não possui nenhum plano ativo que permita gerar licenças.</p>
                </div>
                
                <div id="license-rights" class="grid gap-4">
                    <!-- Preenchido via JS -->
                </div>
            </div>

            <!-- Seção: Licenças Geradas -->
            <div class="bg-gray-800 rounded-2xl border border-gray-700 p-6">
                <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <i data-lucide="list" class="w-6 h-6 text-blue-400"></i>
                    Licenças Geradas
                </h2>
                
                <div id="no-licenses" class="hidden text-center py-8">
                    <i data-lucide="inbox" class="w-12 h-12 text-gray-600 mx-auto mb-3"></i>
                    <p class="text-gray-400">Você ainda não gerou nenhuma licença.</p>
                </div>
                
                <div id="licenses-list" class="space-y-3">
                    <!-- Preenchido via JS -->
                </div>
            </div>
        </div>

    </main>

    <!-- Modal de Licença Gerada -->
    <div id="license-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-2xl border border-gray-700 w-full max-w-lg p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="check-circle" class="w-10 h-10 text-green-400"></i>
                </div>
                <h3 class="text-2xl font-bold text-white">Licença Gerada!</h3>
                <p class="text-gray-400 mt-2">Copie a chave abaixo e use para ativar seu painel.</p>
            </div>
            
            <div class="bg-gray-900 rounded-xl p-4 mb-6">
                <label class="text-xs text-gray-500 uppercase tracking-wider">Sua Chave de Licença</label>
                <div class="flex items-center gap-2 mt-2">
                    <input type="text" id="modal-license-key" readonly 
                           class="license-key flex-1 bg-transparent text-green-400 text-lg font-bold border-none outline-none">
                    <button onclick="copyLicenseKey()" class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors" title="Copiar">
                        <i data-lucide="copy" class="w-5 h-5 text-gray-300"></i>
                    </button>
                </div>
            </div>
            
            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 mb-6">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-400 flex-shrink-0 mt-0.5"></i>
                    <div class="text-sm text-yellow-300">
                        <p class="font-semibold">Importante:</p>
                        <p class="text-yellow-400/80">Guarde esta chave em local seguro. A validade começa a contar a partir do momento da ativação.</p>
                    </div>
                </div>
            </div>
            
            <button onclick="closeModal()" class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition-colors">
                Entendi
            </button>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        let licenseRights = [];
        let licensesGenerated = [];
        
        // Carrega dados ao iniciar
        document.addEventListener('DOMContentLoaded', loadData);
        
        async function loadData() {
            try {
                const response = await fetch('/api/member_api.php?action=get_license_info');
                const data = await response.json();
                
                if (data.success) {
                    licenseRights = data.license_rights || [];
                    licensesGenerated = data.licenses_generated || [];
                    renderContent();
                } else {
                    alert(data.error || 'Erro ao carregar dados.');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de conexão.');
            }
            
            document.getElementById('loading').classList.add('hidden');
            document.getElementById('content').classList.remove('hidden');
        }
        
        function renderContent() {
            // Renderiza direitos de licença
            const rightsContainer = document.getElementById('license-rights');
            const noRights = document.getElementById('no-rights');
            
            if (licenseRights.length === 0) {
                noRights.classList.remove('hidden');
                rightsContainer.classList.add('hidden');
            } else {
                noRights.classList.add('hidden');
                rightsContainer.classList.remove('hidden');
                
                rightsContainer.innerHTML = licenseRights.map(right => `
                    <div class="bg-gray-900 rounded-xl p-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-white">${escapeHtml(right.produto_nome)}</h3>
                            <p class="text-sm text-gray-400">
                                Tipo: <span class="text-green-400 font-medium">${right.nome_licenca}</span>
                                ${right.dias_validade ? `(${right.dias_validade} dias)` : '(Sem expiração)'}
                            </p>
                        </div>
                        <button onclick="generateLicense(${right.produto_id})" 
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Gerar
                        </button>
                    </div>
                `).join('');
                
                lucide.createIcons();
            }
            
            // Renderiza licenças geradas
            const listContainer = document.getElementById('licenses-list');
            const noLicenses = document.getElementById('no-licenses');
            
            if (licensesGenerated.length === 0) {
                noLicenses.classList.remove('hidden');
                listContainer.classList.add('hidden');
            } else {
                noLicenses.classList.add('hidden');
                listContainer.classList.remove('hidden');
                
                listContainer.innerHTML = licensesGenerated.map(license => {
                    const statusColors = {
                        'disponivel': 'bg-blue-500/20 text-blue-400',
                        'ativada': 'bg-green-500/20 text-green-400',
                        'expirada': 'bg-red-500/20 text-red-400',
                        'revogada': 'bg-gray-500/20 text-gray-400'
                    };
                    const statusLabels = {
                        'disponivel': 'Disponível',
                        'ativada': 'Ativada',
                        'expirada': 'Expirada',
                        'revogada': 'Revogada'
                    };
                    
                    return `
                        <div class="bg-gray-900 rounded-xl p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="license-key text-green-400 font-bold text-sm break-all">${escapeHtml(license.chave_licenca)}</div>
                                <span class="px-2 py-1 rounded-full text-xs font-medium ${statusColors[license.status] || statusColors['disponivel']}">
                                    ${statusLabels[license.status] || license.status}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-4 text-xs text-gray-400">
                                <span><i data-lucide="tag" class="w-3 h-3 inline mr-1"></i>${license.tipo_licenca}</span>
                                <span><i data-lucide="calendar" class="w-3 h-3 inline mr-1"></i>Gerada: ${formatDate(license.data_geracao)}</span>
                                ${license.data_ativacao ? `<span><i data-lucide="check" class="w-3 h-3 inline mr-1"></i>Ativada: ${formatDate(license.data_ativacao)}</span>` : ''}
                                ${license.data_expiracao ? `<span><i data-lucide="clock" class="w-3 h-3 inline mr-1"></i>Expira: ${formatDate(license.data_expiracao)}</span>` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
                
                lucide.createIcons();
            }
        }
        
        async function generateLicense(produtoId) {
            if (!confirm('Deseja gerar uma nova licença?')) return;
            
            try {
                const response = await fetch('/api/member_api.php?action=generate_license', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ produto_id: produtoId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('modal-license-key').value = data.license.chave;
                    document.getElementById('license-modal').classList.remove('hidden');
                    loadData(); // Recarrega a lista
                } else {
                    alert(data.error || 'Erro ao gerar licença.');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de conexão.');
            }
        }
        
        function copyLicenseKey() {
            const input = document.getElementById('modal-license-key');
            input.select();
            document.execCommand('copy');
            
            // Feedback visual
            const btn = event.currentTarget;
            btn.innerHTML = '<i data-lucide="check" class="w-5 h-5 text-green-400"></i>';
            lucide.createIcons();
            
            setTimeout(() => {
                btn.innerHTML = '<i data-lucide="copy" class="w-5 h-5 text-gray-300"></i>';
                lucide.createIcons();
            }, 2000);
        }
        
        function closeModal() {
            document.getElementById('license-modal').classList.add('hidden');
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('pt-BR');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
    <?php 
    $mp_path = __DIR__ . '/../includes/member_protection.php';
    if (file_exists($mp_path)) require_once $mp_path; 
    ?>
</body>
</html>
