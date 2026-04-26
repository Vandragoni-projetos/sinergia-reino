<?php
// Este arquivo é incluído dentro de admin.php
// Inclui o helper do master para verificação segura
require_once __DIR__ . '/../../helpers/master_helper.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Configurações do Sistema</h1>
        <p class="text-gray-400 mt-1">Personalize a aparência e identidade visual da plataforma.</p>
    </div>
    <a href="/admin?pagina=admin_dashboard" class="bg-dark-elevated text-gray-300 font-bold py-2 px-4 rounded-lg hover:bg-dark-card transition duration-300 flex items-center space-x-2 border border-dark-border">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
        <span>Voltar ao Dashboard</span>
    </a>
</div>

<div id="status-message" class="hidden px-4 py-3 rounded relative mb-4" role="alert"></div>

<!-- Aviso: Cores em Configurações Visuais -->
<div class="bg-dark-card p-4 rounded-lg mb-6 border border-dark-border flex items-center gap-3">
    <i data-lucide="palette" class="w-6 h-6 flex-shrink-0" style="color: var(--accent-primary);"></i>
    <div>
        <p class="text-white font-medium">Cores, fontes e estilos visuais</p>
        <p class="text-gray-400 text-sm">Para personalizar cores primárias, fontes, bordas e sombras, use <a href="/admin?pagina=admin_visual_config" class="text-[var(--accent-primary)] hover:underline">Configurações Visuais</a>.</p>
    </div>
</div>

<!-- Seção: Logo -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="image" class="w-6 h-6 text-[#32e768]"></i>
        <span>Logo do Sistema</span>
    </h2>
    <p class="text-gray-400 mb-6">Faça upload da logo que será exibida no sidebar e nas telas de login.</p>
    
    <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="flex-1">
            <label for="logo_file" class="block text-gray-300 text-sm font-semibold mb-2">Upload da Logo</label>
            <div class="relative">
                <input type="file" id="logo_file" name="logo_file" accept="image/jpeg,image/png,image/webp,image/svg+xml" 
                       class="hidden">
                <label for="logo_file" class="cursor-pointer inline-flex items-center justify-center px-6 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white hover:bg-dark-card transition">
                    <i data-lucide="upload" class="w-5 h-5 mr-2"></i>
                    <span>Selecionar Arquivo</span>
                </label>
                <span id="logo_filename" class="ml-4 text-gray-400 text-sm"></span>
            </div>
            <p class="text-xs text-gray-400 mt-2">Formatos aceitos: JPG, PNG, WEBP, SVG. Tamanho máximo: 2MB</p>
        </div>
        <div class="flex flex-col gap-2">
            <div class="w-32 h-32 rounded-lg border-2 border-dark-border bg-dark-elevated flex items-center justify-center overflow-hidden" id="logo-preview">
                <img id="logo-preview-img" src="" alt="Logo Preview" class="max-w-full max-h-full object-contain hidden">
                <i data-lucide="image" class="w-12 h-12 text-gray-500" id="logo-placeholder"></i>
            </div>
            <button type="button" id="upload-logo-btn" class="text-white font-bold py-2 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" style="background-color: var(--accent-primary);" onmouseover="if(!this.disabled) this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="if(!this.disabled) this.style.backgroundColor='var(--accent-primary)'" disabled>
                Enviar Logo
            </button>
        </div>
    </div>
</div>

<!-- Seção: Nome da Plataforma -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="type" class="w-6 h-6" style="color: var(--accent-primary);"></i>
        <span>Nome da Plataforma</span>
    </h2>
    <p class="text-gray-400 mb-6">Defina o nome da plataforma que será exibido no checkout e em outras áreas do sistema.</p>
    
    <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="flex-1">
            <label for="nome_plataforma" class="block text-gray-300 text-sm font-semibold mb-2">Nome da Plataforma</label>
            <input type="text" id="nome_plataforma" name="nome_plataforma" 
                   class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-[#32e768] transition duration-300"
                   placeholder="LuraPay" maxlength="100">
            <p class="text-xs text-gray-400 mt-2">Este nome será usado no checkout e em mensagens do sistema.</p>
        </div>
        <div class="flex flex-col gap-2">
            <div class="w-32 h-32 rounded-lg border-2 border-dark-border bg-dark-elevated flex items-center justify-center">
                <span id="nome-plataforma-preview" class="text-white font-bold text-lg text-center px-2"></span>
            </div>
            <button type="button" id="save-nome-plataforma-btn" class="text-white font-bold py-2 px-4 rounded-lg transition duration-300" style="background-color: var(--accent-primary);" onmouseover="this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="this.style.backgroundColor='var(--accent-primary)'">
                Salvar Nome
            </button>
        </div>
    </div>
</div>

<!-- Seção: Logo do Checkout -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="shopping-cart" class="w-6 h-6" style="color: var(--accent-primary);"></i>
        <span>Logo do Checkout</span>
    </h2>
    <p class="text-gray-400 mb-6">Faça upload da logo que será exibida exclusivamente no checkout. Se não configurada, será usada a logo padrão do sistema.</p>
    
    <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="flex-1">
            <label for="logo_checkout_file" class="block text-gray-300 text-sm font-semibold mb-2">Upload da Logo do Checkout</label>
            <div class="relative">
                <input type="file" id="logo_checkout_file" name="logo_checkout_file" accept="image/jpeg,image/png,image/webp,image/svg+xml" 
                       class="hidden">
                <label for="logo_checkout_file" class="cursor-pointer inline-flex items-center justify-center px-6 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white hover:bg-dark-card transition">
                    <i data-lucide="upload" class="w-5 h-5 mr-2"></i>
                    <span>Selecionar Arquivo</span>
                </label>
                <span id="logo_checkout_filename" class="ml-4 text-gray-400 text-sm"></span>
            </div>
            <p class="text-xs text-gray-400 mt-2">Formatos aceitos: JPG, PNG, WEBP, SVG. Tamanho máximo: 2MB</p>
        </div>
        <div class="flex flex-col gap-2">
            <div class="w-32 h-32 rounded-lg border-2 border-dark-border bg-dark-elevated flex items-center justify-center overflow-hidden" id="logo-checkout-preview">
                <img id="logo-checkout-preview-img" src="" alt="Logo Checkout Preview" class="max-w-full max-h-full object-contain hidden">
                <i data-lucide="image" class="w-12 h-12 text-gray-500" id="logo-checkout-placeholder"></i>
            </div>
            <button type="button" id="upload-logo-checkout-btn" class="text-white font-bold py-2 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" style="background-color: var(--accent-primary);" onmouseover="if(!this.disabled) this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="if(!this.disabled) this.style.backgroundColor='var(--accent-primary)'" disabled>
                Enviar Logo
            </button>
        </div>
    </div>
</div>

<!-- Seção: Imagem de Login -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="monitor" class="w-6 h-6" style="color: var(--accent-primary);"></i>
        <span>Imagem de Fundo do Login</span>
    </h2>
    <p class="text-gray-400 mb-6">Faça upload da imagem que será exibida como fundo na tela de login.</p>
    
    <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="flex-1">
            <label for="login_image_file" class="block text-gray-300 text-sm font-semibold mb-2">Upload da Imagem</label>
            <div class="relative">
                <input type="file" id="login_image_file" name="login_image_file" accept="image/jpeg,image/png,image/webp" 
                       class="hidden">
                <label for="login_image_file" class="cursor-pointer inline-flex items-center justify-center px-6 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white hover:bg-dark-card transition">
                    <i data-lucide="upload" class="w-5 h-5 mr-2"></i>
                    <span>Selecionar Arquivo</span>
                </label>
                <span id="login_image_filename" class="ml-4 text-gray-400 text-sm"></span>
            </div>
            <p class="text-xs text-gray-400 mt-2">Formatos aceitos: JPG, PNG, WEBP. Tamanho máximo: 5MB</p>
        </div>
        <div class="flex flex-col gap-2">
            <div class="w-48 h-32 rounded-lg border-2 border-dark-border bg-dark-elevated flex items-center justify-center overflow-hidden" id="login-image-preview">
                <img id="login-image-preview-img" src="" alt="Login Image Preview" class="max-w-full max-h-full object-cover hidden">
                <i data-lucide="image" class="w-12 h-12 text-gray-500" id="login-image-placeholder"></i>
            </div>
            <button type="button" id="upload-login-image-btn" class="text-white font-bold py-2 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" style="background-color: var(--accent-primary);" onmouseover="if(!this.disabled) this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="if(!this.disabled) this.style.backgroundColor='var(--accent-primary)'" disabled>
                Enviar Imagem
            </button>
        </div>
    </div>
</div>

<!-- Seção: Imagem das Notificações do Login -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="bell" class="w-6 h-6" style="color: var(--accent-primary);"></i>
        <span>Imagem das Notificações do Login</span>
    </h2>
    <p class="text-gray-400 mb-6">Faça upload da imagem que será exibida nas notificações flutuantes da tela de login. Se não configurada, será usada a logo do sistema.</p>
    
    <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="flex-1">
            <label for="notification_image_file" class="block text-gray-300 text-sm font-semibold mb-2">Upload da Imagem</label>
            <div class="relative">
                <input type="file" id="notification_image_file" name="notification_image_file" accept="image/jpeg,image/png,image/webp,image/svg+xml" 
                       class="hidden">
                <label for="notification_image_file" class="cursor-pointer inline-flex items-center justify-center px-6 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white hover:bg-dark-card transition">
                    <i data-lucide="upload" class="w-5 h-5 mr-2"></i>
                    <span>Selecionar Arquivo</span>
                </label>
                <span id="notification_image_filename" class="ml-4 text-gray-400 text-sm"></span>
            </div>
            <p class="text-xs text-gray-400 mt-2">Formatos aceitos: JPG, PNG, WEBP, SVG. Tamanho máximo: 2MB. Recomendado: imagem quadrada.</p>
        </div>
        <div class="flex flex-col gap-2">
            <div class="w-32 h-32 rounded-lg border-2 border-dark-border bg-dark-elevated flex items-center justify-center overflow-hidden" id="notification-image-preview">
                <img id="notification-image-preview-img" src="" alt="Notification Image Preview" class="max-w-full max-h-full object-contain hidden">
                <i data-lucide="bell" class="w-12 h-12 text-gray-500" id="notification-image-placeholder"></i>
            </div>
            <button type="button" id="upload-notification-image-btn" class="text-white font-bold py-2 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" style="background-color: var(--accent-primary);" onmouseover="if(!this.disabled) this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="if(!this.disabled) this.style.backgroundColor='var(--accent-primary)'" disabled>
                Enviar Imagem
            </button>
        </div>
    </div>
</div>

<!-- Seção: Favicon -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="image" class="w-6 h-6" style="color: var(--accent-primary);"></i>
        <span>Favicon</span>
    </h2>
    <p class="text-gray-400 mb-6">Faça upload do favicon que será exibido na aba do navegador em todas as páginas do sistema.</p>
    
    <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="flex-1">
            <label for="favicon_file" class="block text-gray-300 text-sm font-semibold mb-2">Upload do Favicon</label>
            <div class="relative">
                <input type="file" id="favicon_file" name="favicon_file" accept="image/x-icon,image/vnd.microsoft.icon,image/png,image/svg+xml" 
                       class="hidden">
                <label for="favicon_file" class="cursor-pointer inline-flex items-center justify-center px-6 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white hover:bg-dark-card transition">
                    <i data-lucide="upload" class="w-5 h-5 mr-2"></i>
                    <span>Selecionar Arquivo</span>
                </label>
                <span id="favicon_filename" class="ml-4 text-gray-400 text-sm"></span>
            </div>
            <p class="text-xs text-gray-400 mt-2">Formatos aceitos: ICO, PNG, SVG. Tamanho máximo: 2MB</p>
        </div>
        <div class="flex flex-col gap-2">
            <div class="w-32 h-32 rounded-lg border-2 border-dark-border bg-dark-elevated flex items-center justify-center overflow-hidden" id="favicon-preview">
                <img id="favicon-preview-img" src="" alt="Favicon Preview" class="max-w-full max-h-full object-contain hidden">
                <i data-lucide="image" class="w-12 h-12 text-gray-500" id="favicon-placeholder"></i>
            </div>
            <button type="button" id="upload-favicon-btn" class="text-white font-bold py-2 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" style="background-color: var(--accent-primary);" onmouseover="if(!this.disabled) this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="if(!this.disabled) this.style.backgroundColor='var(--accent-primary)'" disabled>
                Enviar Favicon
            </button>
        </div>
    </div>
</div>

<!-- Seção: Selo de Segurança do Checkout -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="shield-check" class="w-6 h-6" style="color: var(--accent-primary);"></i>
        <span>Selo de Segurança do Checkout</span>
    </h2>
    <p class="text-gray-400 mb-6">Faça upload de uma imagem de selo de segurança (ex: bandeiras de cartões, selos de compra segura) que será exibida no rodapé do checkout para passar credibilidade aos compradores.</p>
    
    <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="flex-1">
            <label for="security_seal_file" class="block text-gray-300 text-sm font-semibold mb-2">Upload do Selo de Segurança</label>
            <div class="relative">
                <input type="file" id="security_seal_file" name="security_seal_file" accept="image/jpeg,image/png,image/webp,image/svg+xml" 
                       class="hidden">
                <label for="security_seal_file" class="cursor-pointer inline-flex items-center justify-center px-6 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white hover:bg-dark-card transition">
                    <i data-lucide="upload" class="w-5 h-5 mr-2"></i>
                    <span>Selecionar Arquivo</span>
                </label>
                <span id="security_seal_filename" class="ml-4 text-gray-400 text-sm"></span>
            </div>
            <p class="text-xs text-gray-400 mt-2">Formatos aceitos: JPG, PNG, WEBP, SVG. Tamanho máximo: 2MB. Recomendado: imagem com fundo transparente.</p>
        </div>
        <div class="flex flex-col gap-2">
            <div class="w-48 h-24 rounded-lg border-2 border-dark-border bg-dark-elevated flex items-center justify-center overflow-hidden" id="security-seal-preview">
                <img id="security-seal-preview-img" src="" alt="Selo de Segurança Preview" class="max-w-full max-h-full object-contain hidden">
                <i data-lucide="shield-check" class="w-12 h-12 text-gray-500" id="security-seal-placeholder"></i>
            </div>
            <div class="flex gap-2">
                <button type="button" id="upload-security-seal-btn" class="flex-1 text-white font-bold py-2 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" style="background-color: var(--accent-primary);" onmouseover="if(!this.disabled) this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="if(!this.disabled) this.style.backgroundColor='var(--accent-primary)'" disabled>
                    Enviar Selo
                </button>
                <button type="button" id="delete-security-seal-btn" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition duration-300 hidden" title="Remover selo">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (isMasterPanel()): ?>
<!-- Seção: Gerenciamento de Licenças (MASTER PANEL) -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-[#32e768]">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="key" class="w-6 h-6 text-[#32e768]"></i>
        <span>Gerenciamento de Licenças (Painel Master)</span>
    </h2>
    <p class="text-gray-400 mb-6">Este é o painel master. Gerencie as licenças geradas pelos seus alunos e configure quais produtos permitem gerar licenças.</p>
    
    <!-- Token da API -->
    <div class="mb-6 p-4 bg-dark-elevated rounded-lg border border-dark-border">
        <h3 class="text-sm font-semibold text-gray-300 mb-3">Token da API de Licenças</h3>
        <p class="text-xs text-gray-400 mb-3">Use este token nos painéis clientes para validar licenças.</p>
        <div class="flex gap-2">
            <input type="text" id="license_api_token" readonly
                   class="flex-1 px-4 py-3 bg-dark-card border border-dark-border rounded-lg text-green-400 font-mono text-sm"
                   value="<?php echo htmlspecialchars(getSystemSetting('license_api_token', '')); ?>">
            <button type="button" onclick="copyApiToken()" class="px-4 py-3 bg-dark-card border border-dark-border rounded-lg text-white hover:bg-dark-elevated transition">
                <i data-lucide="copy" class="w-5 h-5"></i>
            </button>
            <button type="button" onclick="regenerateApiToken()" class="px-4 py-3 bg-orange-600 hover:bg-orange-700 rounded-lg text-white transition" title="Gerar novo token">
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
            </button>
        </div>
    </div>

    <!-- Gerar Nova Licença (Admin Master) -->
    <div class="mb-6 p-4 bg-dark-elevated rounded-lg border border-dark-border">
        <h3 class="text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-5 h-5 text-green-400"></i>
            Gerar Nova Licença
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
            <div>
                <label class="block text-gray-400 text-xs mb-1">Tipo</label>
                <select id="admin-license-tipo" class="w-full px-3 py-2 bg-dark-card border border-dark-border rounded text-white text-sm">
                    <option value="VITALICIO">Vitalícia</option>
                    <option value="MENSAL">Mensal (30 dias)</option>
                    <option value="ANUAL">Anual (365 dias)</option>
                    <option value="SEMESTRAL">Semestral (180 dias)</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-400 text-xs mb-1">Escopo</label>
                <select id="admin-license-escopo" class="w-full px-3 py-2 bg-dark-card border border-dark-border rounded text-white text-sm">
                    <option value="SYSTEM">SYSTEM (ativa sistema)</option>
                    <option value="PRODUCT">PRODUCT</option>
                    <option value="COMMUNITY">COMMUNITY</option>
                    <option value="USER_LIMIT">USER_LIMIT</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-400 text-xs mb-1">Observações (opcional)</label>
                <input type="text" id="admin-license-observacoes" class="w-full px-3 py-2 bg-dark-card border border-dark-border rounded text-white text-sm" placeholder="Ex: Cliente XYZ">
            </div>
        </div>
        <button type="button" id="admin-generate-license-btn" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition flex items-center gap-2">
            <i data-lucide="key" class="w-4 h-4"></i> Gerar Licença
        </button>
        <div id="admin-license-result" class="mt-3 hidden p-3 rounded-lg"></div>
    </div>
    
    <!-- Toggle: OFF=cinza, ON=verde (cor clara para leitura) -->
    <style>
        .licenca-toggle { background-color: #475569 !important; }
        .peer:checked ~ .licenca-toggle { background-color: #059669 !important; }
    </style>
    <!-- Produtos que geram licença -->
    <div class="mb-6">
        <h3 class="text-sm font-semibold text-gray-300 mb-3">Produtos que Geram Licença</h3>
        <p class="text-xs text-gray-400 mb-3">Marque quais produtos permitem que alunos gerem licenças de ativação.</p>
        <div id="produtos-licenca-list" class="space-y-2">
            <?php
            $produtosLicenca = [];
            try {
                // Tenta buscar com a coluna gera_licenca
                $stmtProd = $pdo->prepare("SELECT id, nome, gera_licenca FROM produtos WHERE tipo_entrega = 'area_membros' ORDER BY nome");
                $stmtProd->execute();
                $produtosLicenca = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Se a coluna não existe, busca sem ela
                try {
                    $stmtProd = $pdo->prepare("SELECT id, nome, 0 as gera_licenca FROM produtos WHERE tipo_entrega = 'area_membros' ORDER BY nome");
                    $stmtProd->execute();
                    $produtosLicenca = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
                    echo '<div class="p-3 bg-yellow-900/20 border border-yellow-500/30 rounded-lg text-yellow-300 text-sm mb-3">
                        <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
                        A coluna <code>gera_licenca</code> não existe na tabela produtos. Execute o SQL de migração.
                    </div>';
                } catch (PDOException $e2) {
                    // Erro geral
                }
            }
            
            if (empty($produtosLicenca)): ?>
                <p class="text-gray-500 text-sm">Nenhum produto do tipo "Área de Membros" encontrado.</p>
            <?php else:
                foreach ($produtosLicenca as $prod): ?>
                <div class="flex items-center justify-between p-3 bg-dark-elevated rounded-lg border border-dark-border">
                    <span class="text-white"><?php echo htmlspecialchars($prod['nome']); ?></span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer produto-gera-licenca" data-produto-id="<?php echo $prod['id']; ?>" <?php echo $prod['gera_licenca'] ? 'checked' : ''; ?>>
                        <div class="licenca-toggle w-11 h-6 bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>
            <?php endforeach;
            endif; ?>
        </div>
    </div>
    
    <!-- Estatísticas de Licenças -->
    <div class="mb-6">
        <h3 class="text-sm font-semibold text-gray-300 mb-3">Estatísticas de Licenças</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            // Conta licenças por status (com verificação se tabela existe)
            $stats = ['disponivel' => 0, 'ativada' => 0, 'ativa' => 0, 'expirada' => 0, 'bloqueada' => 0, 'revogada' => 0];
            try {
                $stmtStats = $pdo->query("SELECT status, COUNT(*) as total FROM licencas_geradas GROUP BY status");
                while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
                    $stats[$row['status']] = $row['total'];
                }
            } catch (PDOException $e) {
                // Tabela não existe ainda
            }
            $ativadasTotal = ($stats['ativada'] ?? 0) + ($stats['ativa'] ?? 0);
            ?>
            <div class="p-4 bg-blue-950/80 border border-blue-600/50 rounded-lg text-center">
                <p class="text-2xl font-bold text-white"><?php echo $stats['disponivel']; ?></p>
                <p class="text-xs text-blue-200">Disponíveis</p>
            </div>
            <div class="p-4 bg-emerald-950/80 border border-emerald-600/50 rounded-lg text-center">
                <p class="text-2xl font-bold text-white"><?php echo $ativadasTotal; ?></p>
                <p class="text-xs text-emerald-200">Ativadas</p>
            </div>
            <div class="p-4 bg-red-950/80 border border-red-600/50 rounded-lg text-center">
                <p class="text-2xl font-bold text-white"><?php echo $stats['expirada']; ?></p>
                <p class="text-xs text-red-200">Expiradas</p>
            </div>
            <div class="p-4 bg-slate-800/80 border border-slate-600 rounded-lg text-center">
                <p class="text-2xl font-bold text-white"><?php echo ($stats['revogada'] ?? 0) + ($stats['bloqueada'] ?? 0); ?></p>
                <p class="text-xs text-slate-300">Revogadas/Bloqueadas</p>
            </div>
        </div>
    </div>
    
    <!-- Lista de Licenças Recentes -->
    <div>
        <h3 class="text-sm font-semibold text-gray-300 mb-3">Licenças Recentes</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-400 border-b border-dark-border">
                        <th class="pb-2">Chave</th>
                        <th class="pb-2">Tipo</th>
                        <th class="pb-2">Aluno</th>
                        <th class="pb-2">Status</th>
                        <th class="pb-2">Gerada em</th>
                        <th class="pb-2">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $licencas = [];
                    try {
                        $stmtLicencas = $pdo->query("SELECT * FROM licencas_geradas ORDER BY data_geracao DESC LIMIT 10");
                        $licencas = $stmtLicencas->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Tabela não existe ainda
                    }
                    
                    if (empty($licencas)): ?>
                        <tr><td colspan="6" class="py-4 text-center text-gray-500">Nenhuma licença gerada ainda. Execute o SQL de migração para criar a tabela.</td></tr>
                    <?php else:
                        foreach ($licencas as $lic):
                            $statusColors = [
                                'disponivel' => 'text-blue-400',
                                'ativada' => 'text-green-400',
                                'expirada' => 'text-red-400',
                                'revogada' => 'text-gray-400'
                            ];
                    ?>
                        <tr class="border-b border-dark-border/50">
                            <td class="py-2 font-mono text-xs text-green-400"><?php echo htmlspecialchars(substr($lic['chave_licenca'], 0, 30) . '...'); ?></td>
                            <td class="py-2 text-white"><?php echo htmlspecialchars($lic['tipo_licenca']); ?></td>
                            <td class="py-2 text-gray-300"><?php echo htmlspecialchars($lic['aluno_email'] ?? '-'); ?></td>
                            <td class="py-2 <?php echo $statusColors[$lic['status']] ?? 'text-gray-400'; ?>"><?php echo ucfirst($lic['status']); ?></td>
                            <td class="py-2 text-gray-400"><?php echo !empty($lic['data_geracao']) ? date('d/m/Y H:i', strtotime($lic['data_geracao'])) : '-'; ?></td>
                            <td class="py-2">
                                <?php if (in_array($lic['status'], ['disponivel', 'ativada', 'ativa'])): ?>
                                <button type="button" class="revogar-licenca-btn text-red-400 hover:text-red-300 text-xs" data-chave="<?php echo htmlspecialchars($lic['chave_licenca']); ?>" title="Revogar">Revogar</button>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyApiToken() {
    const input = document.getElementById('license_api_token');
    input.select();
    document.execCommand('copy');
    alert('Token copiado!');
}

async function regenerateApiToken() {
    if (!confirm('Tem certeza? Isso invalidará o token atual em todos os painéis clientes.')) return;
    
    try {
        const response = await fetch('/api/admin_api.php?action=regenerate_license_token', { method: 'POST' });
        const result = await response.json();
        if (result.success) {
            document.getElementById('license_api_token').value = result.token;
            alert('Novo token gerado!');
        } else {
            alert('Erro: ' + (result.error || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro de conexão');
    }
}

// Gerar Nova Licença (Admin Master)
document.getElementById('admin-generate-license-btn')?.addEventListener('click', async function() {
    const btn = this;
    const resultEl = document.getElementById('admin-license-result');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Gerando...';
    if (typeof lucide !== 'undefined') lucide.createIcons();
    try {
        const r = await fetch('/api/admin_api?action=generate_license', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo: document.getElementById('admin-license-tipo').value,
                escopo: document.getElementById('admin-license-escopo').value,
                observacoes: document.getElementById('admin-license-observacoes').value
            })
        });
        const res = await r.json();
        resultEl.classList.remove('hidden');
        if (res.success && res.license) {
            resultEl.className = 'mt-3 p-4 rounded-lg bg-emerald-950/95 border border-emerald-700 text-white';
            const chaveEsc = (res.license.chave || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
            const chaveRaw = (res.license.chave || '').replace(/'/g, "\\'");
            resultEl.innerHTML = '<strong class="text-white">Licença gerada!</strong> Chave: <code class="font-mono text-sm break-all text-emerald-100 bg-black/30 px-2 py-1 rounded">' + chaveEsc + '</code> <button type="button" onclick="navigator.clipboard.writeText(\'' + chaveRaw + '\'); this.textContent=\'Copiado!\';" class="ml-2 px-2 py-1 bg-white/20 hover:bg-white/30 rounded text-xs text-white font-medium">Copiar</button>';
        } else {
            resultEl.className = 'mt-3 p-3 rounded-lg bg-red-900/30 border border-red-500/50 text-red-300';
            resultEl.textContent = res.error || 'Erro ao gerar licença.';
        }
    } catch (e) {
        resultEl.classList.remove('hidden');
        resultEl.className = 'mt-3 p-3 rounded-lg bg-red-900/30 border border-red-500/50 text-red-300';
        resultEl.textContent = 'Erro de conexão.';
    }
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="key" class="w-4 h-4"></i> Gerar Licença';
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

// Revogar licença (Admin Master)
document.querySelectorAll('.revogar-licenca-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const chave = this.dataset.chave;
        if (!chave || !confirm('Tem certeza que deseja revogar esta licença? Ela deixará de ser válida.')) return;
        try {
            const r = await fetch('/api/admin_api?action=revoke_license', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chave: chave, bloquear: false })
            });
            const res = await r.json();
            if (res.success) { alert(res.message); location.reload(); }
            else alert(res.error || 'Erro.');
        } catch (e) { alert('Erro de conexão.'); }
    });
});

// Toggle produto gera licença
document.querySelectorAll('.produto-gera-licenca').forEach(checkbox => {
    checkbox.addEventListener('change', async function() {
        const produtoId = this.dataset.produtoId;
        const geraLicenca = this.checked ? 1 : 0;
        
        try {
            const response = await fetch('/api/admin_api.php?action=toggle_produto_licenca', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ produto_id: produtoId, gera_licenca: geraLicenca })
            });
            const result = await response.json();
            if (!result.success) {
                alert('Erro: ' + (result.error || 'Erro desconhecido'));
                this.checked = !this.checked;
            }
        } catch (e) {
            alert('Erro de conexão');
            this.checked = !this.checked;
        }
    });
});
</script>

<?php else: ?>
<?php
$envMasterSecret = getenv('GATEWAYPRO_MASTER_SECRET');
$podeHabilitarMaster = !empty($envMasterSecret);
?>
<!-- Se for Painel Cliente: opção de habilitar Master para ver Gerar Nova Licença -->
<?php if ($podeHabilitarMaster): ?>
<div class="bg-dark-card p-6 rounded-lg mb-6 border border-dark-border">
    <h3 class="text-lg font-semibold text-white mb-2 flex items-center gap-2">
        <i data-lucide="shield" class="w-5 h-5 text-green-400"></i>
        Habilitar Painel Master
    </h3>
    <p class="text-gray-400 text-sm mb-4">Para ver <strong>Gerar Nova Licença</strong>, Token da API, Produtos que geram licença e Estatísticas, habilite este painel como Master.</p>
    <button type="button" id="habilitar-master-btn" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition">
        <i data-lucide="check-circle" class="w-4 h-4 inline mr-2"></i> Habilitar Painel Master
    </button>
</div>
<?php else: ?>
<div class="bg-amber-900/20 border border-amber-500/40 p-4 rounded-lg mb-6">
    <p class="text-amber-200 text-sm"><strong>Para ver "Gerar Nova Licença":</strong> adicione no arquivo <code>.env</code> a variável <code>GATEWAYPRO_MASTER_SECRET=sua_chave_secreta</code>, recarregue esta página e clique em "Habilitar Painel Master".</p>
</div>
<?php endif; ?>

<!-- Seção: Licença do Sistema (PAINÉIS CLIENTES) -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-[#32e768]">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="key" class="w-6 h-6 text-[#32e768]"></i>
        <span>Licença do Sistema</span>
    </h2>
    <p class="text-gray-400 mb-6">Gerencie a chave de ativação do Sistema. A licença é necessária para o funcionamento do sistema.</p>
    
    <!-- Status da Licença -->
    <div id="license-status-card" class="mb-6 p-4 rounded-lg border">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div id="license-status-icon" class="w-10 h-10 rounded-full flex items-center justify-center">
                    <i data-lucide="loader" class="w-5 h-5 animate-spin text-gray-400"></i>
                </div>
                <div>
                    <p id="license-status-text" class="font-semibold text-white">Verificando...</p>
                    <p id="license-status-detail" class="text-sm text-gray-400">Carregando informações da licença</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chave de Ativação -->
    <div class="mb-6">
        <label for="license_key" class="block text-gray-300 text-sm font-semibold mb-2">Chave de Ativação</label>
        <div class="flex gap-2">
            <input type="text" id="license_key" name="license_key" 
                   class="flex-1 px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-[#32e768] transition duration-300 font-mono"
                   placeholder="XXXXXXXX-XXXXXXXX">
            <button type="button" id="activate-license-btn" class="px-6 py-3 text-white font-bold rounded-lg transition duration-300" style="background-color: var(--accent-primary);" onmouseover="this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="this.style.backgroundColor='var(--accent-primary)'">
                Ativar
            </button>
        </div>
        <p class="text-xs text-gray-400 mt-2">Insira sua chave de ativação para ativar ou renovar a licença.</p>
    </div>
    
    <!-- Informações da Licença -->
    <div id="license-info" class="p-4 bg-dark-elevated rounded-lg border border-dark-border hidden">
        <h3 class="text-sm font-semibold text-gray-300 mb-3">Detalhes da Licença</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Status</p>
                <p id="info-status" class="text-white font-medium">-</p>
            </div>
            <div>
                <p class="text-gray-500">Validade</p>
                <p id="info-expiration" class="text-white font-medium">-</p>
            </div>
            <div>
                <p class="text-gray-500">Ativada em</p>
                <p id="info-activated-at" class="text-white font-medium">-</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    
    const statusMessage = document.getElementById('status-message');
    
    const logoFile = document.getElementById('logo_file');
    const logoFilename = document.getElementById('logo_filename');
    const logoPreview = document.getElementById('logo-preview-img');
    const logoPlaceholder = document.getElementById('logo-placeholder');
    const uploadLogoBtn = document.getElementById('upload-logo-btn');
    
    const loginImageFile = document.getElementById('login_image_file');
    const loginImageFilename = document.getElementById('login_image_filename');
    const loginImagePreview = document.getElementById('login-image-preview-img');
    const loginImagePlaceholder = document.getElementById('login-image-placeholder');
    const uploadLoginImageBtn = document.getElementById('upload-login-image-btn');
    
    const nomePlataformaInput = document.getElementById('nome_plataforma');
    const nomePlataformaPreview = document.getElementById('nome-plataforma-preview');
    const saveNomePlataformaBtn = document.getElementById('save-nome-plataforma-btn');
    
    const logoCheckoutFile = document.getElementById('logo_checkout_file');
    const logoCheckoutFilename = document.getElementById('logo_checkout_filename');
    const logoCheckoutPreview = document.getElementById('logo-checkout-preview-img');
    const logoCheckoutPlaceholder = document.getElementById('logo-checkout-placeholder');
    const uploadLogoCheckoutBtn = document.getElementById('upload-logo-checkout-btn');
    
    const faviconFile = document.getElementById('favicon_file');
    const faviconFilename = document.getElementById('favicon_filename');
    const faviconPreview = document.getElementById('favicon-preview-img');
    const faviconPlaceholder = document.getElementById('favicon-placeholder');
    const uploadFaviconBtn = document.getElementById('upload-favicon-btn');
    
    const notificationImageFile = document.getElementById('notification_image_file');
    const notificationImageFilename = document.getElementById('notification_image_filename');
    const notificationImagePreview = document.getElementById('notification-image-preview-img');
    const notificationImagePlaceholder = document.getElementById('notification-image-placeholder');
    const uploadNotificationImageBtn = document.getElementById('upload-notification-image-btn');
    
    const securitySealFile = document.getElementById('security_seal_file');
    const securitySealFilename = document.getElementById('security_seal_filename');
    const securitySealPreview = document.getElementById('security-seal-preview-img');
    const securitySealPlaceholder = document.getElementById('security-seal-placeholder');
    const uploadSecuritySealBtn = document.getElementById('upload-security-seal-btn');
    const deleteSecuritySealBtn = document.getElementById('delete-security-seal-btn');
    
    // Carregar configurações atuais
    async function loadSettings() {
        try {
            const response = await fetch('/api/admin_api?action=get_system_settings');
            const result = await response.json();
            
            if (result.success && result.data) {
                // Logo
                if (result.data.logo_url) {
                    logoPreview.src = result.data.logo_url;
                    logoPreview.classList.remove('hidden');
                    logoPlaceholder.classList.add('hidden');
                } else {
                    logoPreview.classList.add('hidden');
                    logoPlaceholder.classList.remove('hidden');
                }
                
                // Imagem de login
                if (result.data.login_image_url) {
                    loginImagePreview.src = result.data.login_image_url;
                    loginImagePreview.classList.remove('hidden');
                    loginImagePlaceholder.classList.add('hidden');
                } else {
                    loginImagePreview.classList.add('hidden');
                    loginImagePlaceholder.classList.remove('hidden');
                }
                
                // Nome da Plataforma
                if (result.data.nome_plataforma) {
                    nomePlataformaInput.value = result.data.nome_plataforma;
                    nomePlataformaPreview.textContent = result.data.nome_plataforma;
                }
                
                // Logo do Checkout
                if (result.data.logo_checkout_url) {
                    logoCheckoutPreview.src = result.data.logo_checkout_url;
                    logoCheckoutPreview.classList.remove('hidden');
                    logoCheckoutPlaceholder.classList.add('hidden');
                } else {
                    logoCheckoutPreview.classList.add('hidden');
                    logoCheckoutPlaceholder.classList.remove('hidden');
                }
                
                // Favicon
                if (result.data.favicon_url) {
                    faviconPreview.src = result.data.favicon_url;
                    faviconPreview.classList.remove('hidden');
                    faviconPlaceholder.classList.add('hidden');
                } else {
                    faviconPreview.classList.add('hidden');
                    faviconPlaceholder.classList.remove('hidden');
                }
                
                // Imagem das Notificações
                if (result.data.notification_image_url) {
                    notificationImagePreview.src = result.data.notification_image_url;
                    notificationImagePreview.classList.remove('hidden');
                    notificationImagePlaceholder.classList.add('hidden');
                } else {
                    notificationImagePreview.classList.add('hidden');
                    notificationImagePlaceholder.classList.remove('hidden');
                }
                
                // Selo de Segurança
                if (result.data.security_seal_url) {
                    securitySealPreview.src = result.data.security_seal_url;
                    securitySealPreview.classList.remove('hidden');
                    securitySealPlaceholder.classList.add('hidden');
                    deleteSecuritySealBtn.classList.remove('hidden');
                } else {
                    securitySealPreview.classList.add('hidden');
                    securitySealPlaceholder.classList.remove('hidden');
                    deleteSecuritySealBtn.classList.add('hidden');
                }
            }
        } catch (error) {
            console.error('Erro ao carregar configurações:', error);
        }
    }
    
    function showMessage(message, type = 'success') {
        // Remove hidden primeiro
        statusMessage.classList.remove('hidden');
        // Reset classes e adiciona novas
        statusMessage.className = 'px-4 py-3 rounded relative mb-4';
        if (type === 'success') {
            statusMessage.classList.add('bg-green-900/20', 'border', 'border-green-500/30', 'text-green-300');
        } else {
            statusMessage.classList.add('bg-red-900/20', 'border', 'border-red-500/30', 'text-red-300');
        }
        statusMessage.textContent = message;
        statusMessage.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(() => {
            statusMessage.classList.add('hidden');
        }, 5000);
    }
    
    // Preview logo
    logoFile.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            logoFilename.textContent = this.files[0].name;
            uploadLogoBtn.disabled = false;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreview.src = e.target.result;
                logoPreview.classList.remove('hidden');
                logoPlaceholder.classList.add('hidden');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Upload logo
    uploadLogoBtn.addEventListener('click', async function() {
        if (!logoFile.files || !logoFile.files[0]) {
            showMessage('Selecione um arquivo primeiro', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('logo', logoFile.files[0]);
        
        uploadLogoBtn.disabled = true;
        uploadLogoBtn.textContent = 'Enviando...';
        
        try {
            const response = await fetch('/api/admin_api?action=upload_logo', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                showMessage('Logo enviada com sucesso!', 'success');
                logoFile.value = '';
                logoFilename.textContent = '';
                uploadLogoBtn.disabled = true;
            } else {
                showMessage('Erro ao enviar logo: ' + (result.error || 'Erro desconhecido'), 'error');
                uploadLogoBtn.disabled = false;
            }
        } catch (error) {
            console.error('Erro:', error);
            showMessage('Erro de comunicação com o servidor', 'error');
            uploadLogoBtn.disabled = false;
        } finally {
            uploadLogoBtn.textContent = 'Enviar Logo';
        }
    });
    
    // Preview imagem de login
    loginImageFile.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            loginImageFilename.textContent = this.files[0].name;
            uploadLoginImageBtn.disabled = false;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                loginImagePreview.src = e.target.result;
                loginImagePreview.classList.remove('hidden');
                loginImagePlaceholder.classList.add('hidden');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Upload imagem de login
    uploadLoginImageBtn.addEventListener('click', async function() {
        if (!loginImageFile.files || !loginImageFile.files[0]) {
            showMessage('Selecione um arquivo primeiro', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('login_image', loginImageFile.files[0]);
        
        uploadLoginImageBtn.disabled = true;
        uploadLoginImageBtn.textContent = 'Enviando...';
        
        try {
            const response = await fetch('/api/admin_api?action=upload_login_image', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                showMessage('Imagem de login enviada com sucesso!', 'success');
                loginImageFile.value = '';
                loginImageFilename.textContent = '';
                uploadLoginImageBtn.disabled = true;
            } else {
                showMessage('Erro ao enviar imagem: ' + (result.error || 'Erro desconhecido'), 'error');
                uploadLoginImageBtn.disabled = false;
            }
        } catch (error) {
            console.error('Erro:', error);
            showMessage('Erro de comunicação com o servidor', 'error');
            uploadLoginImageBtn.disabled = false;
        } finally {
            uploadLoginImageBtn.textContent = 'Enviar Imagem';
        }
    });

        // Nome da Plataforma - Preview em tempo real
        nomePlataformaInput.addEventListener('input', function() {
            nomePlataformaPreview.textContent = this.value || 'GatewayPro';
        });

        // Salvar Nome da Plataforma
        saveNomePlataformaBtn.addEventListener('click', async function() {
            const nome = nomePlataformaInput.value.trim();
            if (!nome) {
                showMessage('Por favor, insira um nome para a plataforma.', 'error');
                return;
            }

            saveNomePlataformaBtn.disabled = true;
            saveNomePlataformaBtn.textContent = 'Salvando...';

            try {
                console.log('Enviando nome_plataforma:', nome);
                const response = await fetch('/api/admin_api?action=save_system_settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ nome_plataforma: nome })
                });

                const result = await response.json();
                console.log('Resposta do servidor:', result);
                
                if (result.success) {
                    showMessage('Nome da plataforma salvo com sucesso!', 'success');
                    nomePlataformaPreview.textContent = nome;
                } else {
                    showMessage(result.error || 'Erro ao salvar nome da plataforma.', 'error');
                    console.error('Erro ao salvar:', result);
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                showMessage('Erro ao salvar nome da plataforma: ' + error.message, 'error');
            } finally {
                saveNomePlataformaBtn.disabled = false;
                saveNomePlataformaBtn.textContent = 'Salvar Nome';
            }
        });

        // Upload Logo do Checkout
        logoCheckoutFile.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                logoCheckoutFilename.textContent = this.files[0].name;
                uploadLogoCheckoutBtn.disabled = false;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    logoCheckoutPreview.src = e.target.result;
                    logoCheckoutPreview.classList.remove('hidden');
                    logoCheckoutPlaceholder.classList.add('hidden');
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Upload logo do checkout
        uploadLogoCheckoutBtn.addEventListener('click', async function() {
            if (!logoCheckoutFile.files || !logoCheckoutFile.files[0]) {
                showMessage('Selecione um arquivo primeiro', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('logo_checkout', logoCheckoutFile.files[0]);
            
            uploadLogoCheckoutBtn.disabled = true;
            uploadLogoCheckoutBtn.textContent = 'Enviando...';
            
            try {
                const response = await fetch('/api/admin_api?action=upload_logo_checkout', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showMessage('Logo do checkout enviada com sucesso!', 'success');
                    logoCheckoutPreview.src = result.url;
                    logoCheckoutPreview.classList.remove('hidden');
                    logoCheckoutPlaceholder.classList.add('hidden');
                } else {
                    showMessage(result.error || 'Erro ao enviar logo do checkout', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showMessage('Erro de comunicação com o servidor', 'error');
                uploadLogoCheckoutBtn.disabled = false;
            } finally {
                uploadLogoCheckoutBtn.textContent = 'Enviar Logo';
            }
        });

    // Preview favicon
    faviconFile.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            faviconFilename.textContent = this.files[0].name;
            uploadFaviconBtn.disabled = false;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                faviconPreview.src = e.target.result;
                faviconPreview.classList.remove('hidden');
                faviconPlaceholder.classList.add('hidden');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Upload favicon
    uploadFaviconBtn.addEventListener('click', async function() {
        if (!faviconFile.files || !faviconFile.files[0]) {
            showMessage('Selecione um arquivo primeiro', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('favicon', faviconFile.files[0]);
        
        uploadFaviconBtn.disabled = true;
        uploadFaviconBtn.textContent = 'Enviando...';
        
        try {
            const response = await fetch('/api/admin_api?action=upload_favicon', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                showMessage('Favicon enviado com sucesso!', 'success');
                faviconPreview.src = result.url;
                faviconPreview.classList.remove('hidden');
                faviconPlaceholder.classList.add('hidden');
                faviconFile.value = '';
                faviconFilename.textContent = '';
                uploadFaviconBtn.disabled = true;
            } else {
                showMessage(result.error || 'Erro ao enviar favicon', 'error');
                uploadFaviconBtn.disabled = false;
            }
        } catch (error) {
            console.error('Erro:', error);
            showMessage('Erro de comunicação com o servidor', 'error');
            uploadFaviconBtn.disabled = false;
        } finally {
            uploadFaviconBtn.textContent = 'Enviar Favicon';
        }
    });

    // Preview imagem de notificações
    notificationImageFile.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            notificationImageFilename.textContent = this.files[0].name;
            uploadNotificationImageBtn.disabled = false;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                notificationImagePreview.src = e.target.result;
                notificationImagePreview.classList.remove('hidden');
                notificationImagePlaceholder.classList.add('hidden');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Upload imagem de notificações
    uploadNotificationImageBtn.addEventListener('click', async function() {
        if (!notificationImageFile.files || !notificationImageFile.files[0]) {
            showMessage('Selecione um arquivo primeiro', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('notification_image', notificationImageFile.files[0]);
        
        uploadNotificationImageBtn.disabled = true;
        uploadNotificationImageBtn.textContent = 'Enviando...';
        
        try {
            const response = await fetch('/api/admin_api?action=upload_notification_image', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                showMessage('Imagem das notificações enviada com sucesso!', 'success');
                notificationImagePreview.src = result.url;
                notificationImagePreview.classList.remove('hidden');
                notificationImagePlaceholder.classList.add('hidden');
                notificationImageFile.value = '';
                notificationImageFilename.textContent = '';
                uploadNotificationImageBtn.disabled = true;
            } else {
                showMessage(result.error || 'Erro ao enviar imagem', 'error');
                uploadNotificationImageBtn.disabled = false;
            }
        } catch (error) {
            console.error('Erro:', error);
            showMessage('Erro de comunicação com o servidor', 'error');
            uploadNotificationImageBtn.disabled = false;
        } finally {
            uploadNotificationImageBtn.textContent = 'Enviar Imagem';
        }
    });

    // Preview selo de segurança
    securitySealFile.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            securitySealFilename.textContent = this.files[0].name;
            uploadSecuritySealBtn.disabled = false;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                securitySealPreview.src = e.target.result;
                securitySealPreview.classList.remove('hidden');
                securitySealPlaceholder.classList.add('hidden');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Upload selo de segurança
    uploadSecuritySealBtn.addEventListener('click', async function() {
        if (!securitySealFile.files || !securitySealFile.files[0]) {
            showMessage('Selecione um arquivo primeiro', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('security_seal', securitySealFile.files[0]);
        
        uploadSecuritySealBtn.disabled = true;
        uploadSecuritySealBtn.textContent = 'Enviando...';
        
        try {
            const response = await fetch('/api/admin_api?action=upload_security_seal', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                showMessage('Selo de segurança enviado com sucesso!', 'success');
                securitySealPreview.src = result.url;
                securitySealPreview.classList.remove('hidden');
                securitySealPlaceholder.classList.add('hidden');
                deleteSecuritySealBtn.classList.remove('hidden');
                securitySealFile.value = '';
                securitySealFilename.textContent = '';
                uploadSecuritySealBtn.disabled = true;
            } else {
                showMessage(result.error || 'Erro ao enviar selo', 'error');
                uploadSecuritySealBtn.disabled = false;
            }
        } catch (error) {
            console.error('Erro:', error);
            showMessage('Erro de comunicação com o servidor', 'error');
            uploadSecuritySealBtn.disabled = false;
        } finally {
            uploadSecuritySealBtn.textContent = 'Enviar Selo';
        }
    });

    // Deletar selo de segurança
    deleteSecuritySealBtn.addEventListener('click', async function() {
        if (!confirm('Tem certeza que deseja remover o selo de segurança?')) return;
        
        deleteSecuritySealBtn.disabled = true;
        
        try {
            const response = await fetch('/api/admin_api?action=delete_security_seal', {
                method: 'POST'
            });
            const result = await response.json();
            
            if (result.success) {
                showMessage('Selo de segurança removido com sucesso!', 'success');
                securitySealPreview.src = '';
                securitySealPreview.classList.add('hidden');
                securitySealPlaceholder.classList.remove('hidden');
                deleteSecuritySealBtn.classList.add('hidden');
            } else {
                showMessage(result.error || 'Erro ao remover selo', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showMessage('Erro de comunicação com o servidor', 'error');
        } finally {
            deleteSecuritySealBtn.disabled = false;
        }
    });
    
    // Carregar configurações ao iniciar
    loadSettings();
    
    // Habilitar Painel Master
    document.getElementById('habilitar-master-btn')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Habilitando...';
        try {
            const r = await fetch('/api/admin_api.php?action=enable_master_panel', { method: 'POST' });
            const res = await r.json();
            if (res.success) { alert(res.message); location.reload(); }
            else alert(res.error || 'Erro.');
        } catch (e) { alert('Erro de conexão.'); }
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4 inline mr-2"></i> Habilitar Painel Master';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    // ==================== LICENÇA ====================
    const licenseKey = document.getElementById('license_key');
    const activateLicenseBtn = document.getElementById('activate-license-btn');
    const licenseStatusCard = document.getElementById('license-status-card');
    const licenseStatusIcon = document.getElementById('license-status-icon');
    const licenseStatusText = document.getElementById('license-status-text');
    const licenseStatusDetail = document.getElementById('license-status-detail');
    const licenseInfo = document.getElementById('license-info');
    
    // Função para formatar data para dd/mm/aaaa
    function formatDateBR(dateStr) {
        if (!dateStr || dateStr === 'lifetime') return null;
        
        // Se já está no formato dd/mm/aaaa, retorna
        if (/^\d{2}\/\d{2}\/\d{4}/.test(dateStr)) return dateStr;
        
        // Tenta parsear a data
        let date;
        if (dateStr.includes('T') || dateStr.includes(' ')) {
            // Formato ISO ou datetime
            date = new Date(dateStr);
        } else if (dateStr.includes('-')) {
            // Formato yyyy-mm-dd
            const parts = dateStr.split('-');
            date = new Date(parts[0], parts[1] - 1, parts[2]);
        } else {
            return dateStr;
        }
        
        if (isNaN(date.getTime())) return dateStr;
        
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        
        return `${day}/${month}/${year}`;
    }
    
    // Função para formatar datetime para dd/mm/aaaa HH:mm
    function formatDateTimeBR(dateStr) {
        if (!dateStr) return '-';
        
        let date;
        if (dateStr.includes('T')) {
            date = new Date(dateStr);
        } else if (dateStr.includes(' ')) {
            date = new Date(dateStr.replace(' ', 'T'));
        } else {
            return formatDateBR(dateStr);
        }
        
        if (isNaN(date.getTime())) return dateStr;
        
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }

    // Carregar informações da licença
    async function loadLicenseInfo() {
        try {
            const response = await fetch('/api/admin_api?action=get_license_info');
            const result = await response.json();
            
            if (result.success && result.data) {
                const data = result.data;
                
                // Atualizar status visual
                if (data.is_valid && data.status === 'active') {
                    licenseStatusCard.className = 'mb-6 p-4 rounded-lg border border-green-500/30 bg-green-500/10';
                    licenseStatusIcon.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5 text-green-500"></i>';
                    licenseStatusText.textContent = 'Licença Ativa';
                    licenseStatusText.className = 'font-semibold text-green-400';
                    
                    if (data.expiration === 'lifetime' || !data.expiration) {
                        licenseStatusDetail.textContent = 'Licença vitalícia - sem expiração';
                    } else {
                        const expDate = new Date(data.expiration + 'T23:59:59');
                        const now = new Date();
                        const diffDays = Math.ceil((expDate - now) / (1000 * 60 * 60 * 24));
                        const formattedDate = formatDateBR(data.expiration);
                        if (diffDays > 30) {
                            licenseStatusDetail.textContent = 'Expira em ' + diffDays + ' dias (' + formattedDate + ')';
                        } else if (diffDays > 0) {
                            licenseStatusDetail.textContent = '⚠️ Expira em ' + diffDays + ' dias (' + formattedDate + ')';
                            // Alerta amarelo se faltam menos de 30 dias
                            licenseStatusCard.className = 'mb-6 p-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10';
                        } else {
                            licenseStatusDetail.textContent = 'Expirada em: ' + formattedDate;
                        }
                    }
                } else if (data.status === 'expired') {
                    licenseStatusCard.className = 'mb-6 p-4 rounded-lg border border-orange-500/30 bg-orange-500/10';
                    licenseStatusIcon.innerHTML = '<i data-lucide="clock" class="w-5 h-5 text-orange-500"></i>';
                    licenseStatusText.textContent = 'Licença Expirada';
                    licenseStatusText.className = 'font-semibold text-orange-400';
                    licenseStatusDetail.textContent = 'Sua licença expirou em ' + (formatDateBR(data.expiration) || '-') + '. Renove para continuar usando.';
                } else {
                    licenseStatusCard.className = 'mb-6 p-4 rounded-lg border border-red-500/30 bg-red-500/10';
                    licenseStatusIcon.innerHTML = '<i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>';
                    licenseStatusText.textContent = 'Licença Inativa';
                    licenseStatusText.className = 'font-semibold text-red-400';
                    licenseStatusDetail.textContent = 'Ative sua licença para usar o sistema';
                }
                
                // Preencher informações
                if (data.license_key) {
                    licenseInfo.classList.remove('hidden');
                    
                    // Status com cores
                    const statusEl = document.getElementById('info-status');
                    if (data.status === 'active') {
                        statusEl.textContent = 'Ativa';
                        statusEl.className = 'text-green-400 font-medium';
                    } else if (data.status === 'expired') {
                        statusEl.textContent = 'Expirada';
                        statusEl.className = 'text-orange-400 font-medium';
                    } else {
                        statusEl.textContent = 'Inativa';
                        statusEl.className = 'text-red-400 font-medium';
                    }
                    
                    // Expiração
                    if (data.expiration === 'lifetime' || !data.expiration) {
                        document.getElementById('info-expiration').textContent = 'Vitalícia';
                    } else {
                        document.getElementById('info-expiration').textContent = formatDateBR(data.expiration);
                    }
                    
                    document.getElementById('info-activated-at').textContent = formatDateTimeBR(data.activated_at) || '-';
                }
                
                lucide.createIcons();
            }
        } catch (error) {
            console.error('Erro ao carregar licença:', error);
            licenseStatusCard.className = 'mb-6 p-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10';
            licenseStatusIcon.innerHTML = '<i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-500"></i>';
            licenseStatusText.textContent = 'Erro ao verificar';
            licenseStatusText.className = 'font-semibold text-yellow-400';
            licenseStatusDetail.textContent = 'Não foi possível verificar o status da licença';
            lucide.createIcons();
        }
    }
    
    // Ativar licença
    activateLicenseBtn.addEventListener('click', async function() {
        const key = licenseKey.value.trim();
        if (!key) {
            showMessage('Por favor, insira a chave de ativação.', 'error');
            return;
        }
        
        activateLicenseBtn.disabled = true;
        activateLicenseBtn.textContent = 'Ativando...';
        
        try {
            const response = await fetch('/api/admin_api?action=activate_license', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ activation_key: key })
            });
            const result = await response.json();
            
            if (result.success) {
                showMessage(result.message || 'Licença ativada com sucesso!', 'success');
                licenseKey.value = '';
                loadLicenseInfo();
            } else {
                showMessage(result.error || 'Erro ao ativar licença', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showMessage('Erro de comunicação com o servidor', 'error');
        } finally {
            activateLicenseBtn.disabled = false;
            activateLicenseBtn.textContent = 'Ativar';
        }
    });
    
    // Carregar informações da licença ao iniciar
    loadLicenseInfo();
});
</script>
