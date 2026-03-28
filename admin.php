<?php
require_once __DIR__ . '/config/config.php';

// Este painel (admin.php) é exclusivo para administradores do sistema.
// Infoprodutores acessam o painel principal (index.php) e clientes finais acessam a área de membros (member_area_dashboard.php).
// Proteção de página: verifica se o usuário está logado E se é um administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["tipo"]) || $_SESSION["tipo"] !== 'admin') {
    header("location: /login");
    exit;
}

// Fetch admin user data for display
$admin_user_id = $_SESSION['id'];
$admin_user_name_display = htmlspecialchars($_SESSION['nome'] ?? $_SESSION['usuario']);
$admin_user_email = $_SESSION['usuario'] ?? '';
// Data de cadastro (se coluna existir - execute migrations/add_usuarios_data_cadastro.sql)
$admin_data_cadastro = null;
try {
    $stmt_admin = $pdo->prepare("SELECT nome, usuario FROM usuarios WHERE id = ? AND tipo = 'admin'");
    $stmt_admin->execute([$admin_user_id]);
    $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC);
    if ($admin_row) {
        $admin_user_name_display = htmlspecialchars($admin_row['nome'] ?: $admin_row['usuario']);
        $admin_user_email = $admin_row['usuario'];
    }
    $chk = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'data_cadastro'");
    if ($chk && $chk->rowCount() > 0) {
        $stmt_dc = $pdo->prepare("SELECT data_cadastro FROM usuarios WHERE id = ?");
        $stmt_dc->execute([$admin_user_id]);
        $admin_data_cadastro = $stmt_dc->fetchColumn();
    }
} catch (PDOException $e) {}

// Busca a logo do sistema
$logo_url = 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png';
try {
    $stmt_logo = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'logo_url'");
    $stmt_logo->execute();
    $logo_result = $stmt_logo->fetch(PDO::FETCH_ASSOC);
    if ($logo_result && !empty($logo_result['valor'])) {
        $logo_url = '/' . ltrim($logo_result['valor'], '/');
    }
} catch (Exception $e) {
    // Mantém o fallback padrão
} 

// Sistema de roteamento simples para o painel de admin
$pagina_admin = isset($_GET['pagina']) ? $_GET['pagina'] : 'admin_dashboard';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all'; // Nova variável para o filtro de função
$paginas_permitidas_admin = ['admin_dashboard', 'admin_usuarios', 'admin_relatorios', 'admin_smtp_config', 'admin_configuracoes', 'admin_visual_config', 'admin_revenda_autorizada', 'admin_logs', 'admin_pwa'];

// Classes para o menu ativo - Modern Glassmorphism Design
$active_class = 'sidebar-item sidebar-item-active';
$inactive_class = 'sidebar-item sidebar-item-inactive';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Administrador</title>
    <?php include __DIR__ . '/config/load_settings.php'; ?>

    <!-- PWA Tags -->
    <meta name="theme-color" content="#2DD05E">
    <link rel="manifest" href="manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Plataforma">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logo_url); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            orange: { 50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74', 400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c', 800: '#9a3412', 900: '#7c2d12' }
          }
        }
      }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Live Floating Notification */
        .live-notification-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 320px;
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateY(120%); /* Start off-screen */
            opacity: 0;
            transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.5s ease-out;
            z-index: 1000;
        }

        .live-notification-container.show {
            transform: translateY(0);
            opacity: 1;
        }

        .live-notification-product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #e5e7eb; /* Adiciona uma borda sutil ao ícone */
        }
        .cash-register-sound {
            display: none; /* Hide audio element */
        }

        /* Sidebar recolhido - só ícones, 72px de largura */
        @media (min-width: 768px) {
            #admin-sidebar.sidebar-collapsed {
                width: 72px !important;
                min-width: 72px;
            }
            #admin-sidebar.sidebar-collapsed .sidebar-header {
                justify-content: center;
                padding: 1rem 0.5rem;
            }
            #admin-sidebar.sidebar-collapsed .admin-sidebar-logo {
                display: none;
            }
            #admin-sidebar.sidebar-collapsed #admin-sidebar-collapse {
                margin: 0 auto;
            }
            #admin-sidebar.sidebar-collapsed nav .sidebar-item span,
            #admin-sidebar.sidebar-collapsed nav .sidebar-item-active span,
            #admin-sidebar.sidebar-collapsed nav .sidebar-item-inactive span {
                display: none;
            }
            #admin-sidebar.sidebar-collapsed nav .sidebar-item,
            #admin-sidebar.sidebar-collapsed nav .sidebar-item-active,
            #admin-sidebar.sidebar-collapsed nav .sidebar-item-inactive {
                justify-content: center;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
        }

    </style>
</head>
<body class="font-sans flex flex-col min-h-screen bg-dark-base">
    <?php include __DIR__ . '/views/includes/session_heartbeat.php'; ?>
    <!-- Header Fixo Invisível (Topo) -->
    <header class="fixed top-0 left-0 right-0 z-40 bg-dark-base/80 backdrop-blur-sm h-[60px] flex items-center justify-between px-4 md:px-6">
        <!-- Botão de Toggle Mobile -->
        <button id="admin-sidebar-toggle" class="md:hidden p-2 rounded-lg bg-dark-elevated border border-dark-border text-white hover:bg-dark-card transition-colors">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
        <div class="hidden md:block"></div> <!-- Espaçador para desktop -->
        
        <!-- Dropdown Perfil + Logout -->
        <div class="flex items-center space-x-2">
            <div class="relative" id="admin-profile-dropdown-container">
                <button type="button" onclick="toggleAdminProfileDropdown()" class="flex items-center space-x-2 font-medium text-gray-300 hover:text-white transition-colors cursor-pointer px-3 py-2 rounded-lg hover:bg-dark-elevated">
                    <span class="hidden sm:inline">Olá, <?php echo $admin_user_name_display; ?>!</span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" id="admin-dropdown-arrow"></i>
                </button>
                <div id="admin-profile-dropdown" class="hidden absolute right-0 mt-2 w-52 bg-dark-card border border-dark-border rounded-lg shadow-xl py-2 z-50">
                    <button type="button" onclick="openAdminProfileModal()" class="w-full flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-dark-elevated hover:text-white transition-colors text-left">
                        <i data-lucide="user-cog" class="w-4 h-4"></i>
                        <span>Editar Perfil</span>
                    </button>
                    <hr class="border-dark-border my-2">
                    <a href="/logout" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-dark-elevated hover:text-red-400 transition-colors">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Modal Editar Perfil Admin -->
    <div id="admin-profile-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-dark-card rounded-xl border border-dark-border w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between p-6 border-b border-dark-border">
                <h3 class="text-xl font-bold text-white">Editar Perfil</h3>
                <button type="button" onclick="closeAdminProfileModal()" class="text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <form id="admin-profile-form" class="p-6 space-y-4">
                <?php if ($admin_data_cadastro): ?>
                <div class="pb-2 border-b border-dark-border">
                    <p class="text-sm text-gray-400">Data de cadastro</p>
                    <p class="text-white font-medium"><?php echo date('d/m/Y', strtotime($admin_data_cadastro)); ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <label for="admin-profile-nome" class="block text-sm font-medium text-gray-300 mb-2">Nome</label>
                    <input type="text" id="admin-profile-nome" name="nome" value="<?php echo htmlspecialchars($admin_user_name_display); ?>" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent">
                </div>
                <div>
                    <label for="admin-profile-email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" id="admin-profile-email" name="email" value="<?php echo htmlspecialchars($admin_user_email); ?>" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent">
                </div>
                <div>
                    <label for="admin-profile-senha-atual" class="block text-sm font-medium text-gray-300 mb-2">Senha Atual</label>
                    <input type="password" id="admin-profile-senha-atual" name="senha_atual" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent" placeholder="Digite para confirmar alterações">
                </div>
                <div>
                    <label for="admin-profile-nova-senha" class="block text-sm font-medium text-gray-300 mb-2">Nova Senha <span class="text-gray-500">(opcional)</span></label>
                    <input type="password" id="admin-profile-nova-senha" name="nova_senha" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent" placeholder="Deixe vazio para manter a atual">
                </div>
                <div>
                    <label for="admin-profile-confirmar-senha" class="block text-sm font-medium text-gray-300 mb-2">Confirmar Nova Senha</label>
                    <input type="password" id="admin-profile-confirmar-senha" name="confirmar_senha" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent" placeholder="Repita a nova senha">
                </div>
                <div id="admin-profile-error" class="hidden bg-red-900/30 border border-red-500 text-red-300 px-4 py-3 rounded-lg text-sm"></div>
                <div id="admin-profile-success" class="hidden bg-green-900/30 border border-green-500 text-green-300 px-4 py-3 rounded-lg text-sm"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeAdminProfileModal()" class="flex-1 px-4 py-3 bg-dark-elevated text-gray-300 rounded-lg font-semibold hover:bg-dark-card transition-colors border border-dark-border">Cancelar</button>
                    <button type="submit" id="admin-btn-save-profile" class="flex-1 px-4 py-3 rounded-lg font-semibold transition-colors" style="background-color: var(--accent-primary); color: white;">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Menu Lateral do Admin -->
    <aside id="admin-sidebar" class="admin-sidebar sidebar-glass fixed top-0 left-0 bottom-0 z-50 transform -translate-x-full transition-all duration-300 w-full max-w-xs md:translate-x-0 md:w-64 flex flex-col overflow-y-auto">
        <!-- Sidebar Header (Logo + Botão Recolher) -->
        <div class="sidebar-header flex items-center justify-between gap-2 px-3">
            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logotipo" class="h-10 w-auto flex-shrink-0 admin-sidebar-logo">
            <button id="admin-sidebar-collapse" class="hidden md:flex p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-elevated transition-colors flex-shrink-0" title="Recolher menu" aria-label="Recolher menu">
                <i data-lucide="panel-left-close" class="w-5 h-5 admin-sidebar-collapse-icon"></i>
                <i data-lucide="panel-left-open" class="w-5 h-5 admin-sidebar-expand-icon hidden"></i>
            </button>
        </div>
        
        <nav class="mt-4 flex-grow px-2">
            <a href="/admin?pagina=admin_dashboard" class="<?php echo $pagina_admin == 'admin_dashboard' ? $active_class : $inactive_class; ?>">
                <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                <span>Dashboard Admin</span>
            </a>
            
            <!-- Gerenciamento de Usuários (Links Separados) -->
            <div>
                <a href="/admin?pagina=admin_usuarios&role=all" class="<?php echo ($pagina_admin == 'admin_usuarios' && $role_filter == 'all') ? $active_class : $inactive_class; ?>">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <span>Todos os Usuários</span>
                </a>
                <a href="/admin?pagina=admin_usuarios&role=infoproducer" class="<?php echo ($pagina_admin == 'admin_usuarios' && $role_filter == 'infoproducer') ? $active_class : $inactive_class; ?>">
                    <i data-lucide="award" class="w-5 h-5"></i>
                    <span>Gerenciar Infoprodutores</span>
                </a>
                <a href="/admin?pagina=admin_usuarios&role=client" class="<?php echo ($pagina_admin == 'admin_usuarios' && $role_filter == 'client') ? $active_class : $inactive_class; ?>">
                    <i data-lucide="handshake" class="w-5 h-5"></i>
                    <span>Gerenciar Clientes Finais</span>
                </a>
            </div>
            <!-- 
            <a href="/admin?pagina=admin_relatorios" class="<?php echo $pagina_admin == 'admin_relatorios' ? $active_class : $inactive_class; ?>">
                <i data-lucide="file-text" class="w-5 h-5"></i>
                <span>Relatórios Detalhados</span> 
            </a>
            -->
            <!-- NOVO: Link para Configurações SMTP -->
            <a href="/admin?pagina=admin_smtp_config" class="<?php echo $pagina_admin == 'admin_smtp_config' ? $active_class : $inactive_class; ?>">
                <i data-lucide="mail" class="w-5 h-5"></i>
                <span>Configurações SMTP</span>
            </a>
            <!-- NOVO: Link para Configurações do Sistema -->
            <a href="/admin?pagina=admin_configuracoes" class="<?php echo $pagina_admin == 'admin_configuracoes' ? $active_class : $inactive_class; ?>">
                <i data-lucide="settings" class="w-5 h-5"></i>
                <span>Configurações</span>
            </a>
            <!-- NOVO: Link para Configurações Visuais (White-label) -->
            <a href="/admin?pagina=admin_visual_config" class="<?php echo $pagina_admin == 'admin_visual_config' ? $active_class : $inactive_class; ?>">
                <i data-lucide="palette" class="w-5 h-5"></i>
                <span>Configurações Visuais</span>
            </a>
            <!-- NOVO: Link para Logs do Sistema -->
            <a href="/admin?pagina=admin_logs" class="<?php echo $pagina_admin == 'admin_logs' ? $active_class : $inactive_class; ?>">
                <i data-lucide="file-text" class="w-5 h-5"></i>
                <span>Logs do Sistema</span>
            </a>
            <!-- PWA -->
            <a href="/admin?pagina=admin_pwa" class="<?php echo $pagina_admin == 'admin_pwa' ? $active_class : $inactive_class; ?>">
                <i data-lucide="smartphone" class="w-5 h-5"></i>
                <span>PWA</span>
            </a>
            <!-- NOVO: Link para Revenda Autorizada 
            <a href="/admin?pagina=admin_revenda_autorizada" class="<?php echo $pagina_admin == 'admin_revenda_autorizada' ? $active_class : $inactive_class; ?>">
                <i data-lucide="store" class="w-5 h-5"></i>
                <span>Revenda Autorizada</span>
            </a>
            -->
            <?php
            // Itens de menu dinâmicos de plugins (SaaS)
            if (function_exists('do_action')) {
                $plugin_menu_items = do_action('admin_menu_items');
                if (!empty($plugin_menu_items) && is_array($plugin_menu_items)) {
                    foreach ($plugin_menu_items as $item) {
                        if (isset($item['title']) && isset($item['url'])) {
                            $icon = $item['icon'] ?? 'settings';
                            $is_active = (strpos($_SERVER['REQUEST_URI'], $item['url']) !== false);
                            echo '<a href="' . htmlspecialchars($item['url']) . '" class="' . ($is_active ? $active_class : $inactive_class) . '">';
                            echo '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-5 h-5"></i>';
                            echo '<span>' . htmlspecialchars($item['title']) . '</span>';
                            echo '</a>';
                        }
                    }
                }
            }
            ?>
        </nav>
    </aside>

    <!-- Overlay para o menu mobile -->
    <div id="admin-sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden"></div>

    <!-- Conteúdo Principal -->
    <main id="admin-main-content" class="flex-1 md:ml-64 mt-[60px] p-6 lg:p-8 overflow-y-auto transition-[margin] duration-300">
        <?php
        if (in_array($pagina_admin, $paginas_permitidas_admin) && file_exists(__DIR__ . '/views/admin/' . $pagina_admin . '.php')) {
            include __DIR__ . '/views/admin/' . $pagina_admin . '.php';
        } else {
            echo "<div class='text-center p-10 bg-dark-card rounded-lg shadow border border-dark-border'><h1 class='text-4xl font-bold text-white'>Erro 404</h1><p class='mt-2 text-gray-400'>Página não encontrada no painel administrativo.</p></div>";
        }
        ?>
    </main>

    <!-- Floating Live Notification (Mantido para o admin ver, se quiser, mas não ligado ao sininho) -->
    <div id="live-notification-container" class="live-notification-container">
        <img id="live-notification-product-image" src="<?php echo htmlspecialchars($notification_image_url); ?>" alt="Produto" class="live-notification-product-image">
        <div>
            <p class="text-sm font-semibold text-gray-900" id="live-notification-message"></p>
            <p class="text-xs text-gray-500 mt-1" id="live-notification-details"></p>
        </div>
        <audio id="cash-register-sound" class="cash-register-sound" src="assets/cash_register.mp3" preload="auto"></audio>
    </div>

    <script>
        // --- Lógica de Responsividade do Menu Lateral ---
        const adminSidebarToggle = document.getElementById('admin-sidebar-toggle');
        const adminSidebar = document.getElementById('admin-sidebar');
        const adminSidebarOverlay = document.getElementById('admin-sidebar-overlay');
        const body = document.body;

        function toggleAdminSidebar() {
            adminSidebar.classList.toggle('-translate-x-full');
            adminSidebar.classList.toggle('open');
            adminSidebarOverlay.classList.toggle('hidden');
            adminSidebarOverlay.classList.toggle('open');
            body.classList.toggle('overflow-hidden');
        }

        adminSidebarToggle.addEventListener('click', toggleAdminSidebar);
        adminSidebarOverlay.addEventListener('click', toggleAdminSidebar);

        // --- Recolher sidebar em desktop (evita sobreposição em tela 100%) ---
        const adminSidebarCollapse = document.getElementById('admin-sidebar-collapse');
        const adminMainContent = document.getElementById('admin-main-content');
        const STORAGE_KEY = 'admin_sidebar_collapsed';

        function applySidebarCollapsed(collapsed) {
            if (window.innerWidth < 768) return;
            if (collapsed) {
                adminSidebar.classList.add('sidebar-collapsed');
                adminMainContent.classList.remove('md:ml-64');
                adminMainContent.classList.add('md:ml-[72px]');
                document.querySelector('.admin-sidebar-collapse-icon')?.classList.add('hidden');
                document.querySelector('.admin-sidebar-expand-icon')?.classList.remove('hidden');
                adminSidebarCollapse?.setAttribute('title', 'Expandir menu');
            } else {
                adminSidebar.classList.remove('sidebar-collapsed');
                adminMainContent.classList.remove('md:ml-[72px]');
                adminMainContent.classList.add('md:ml-64');
                document.querySelector('.admin-sidebar-collapse-icon')?.classList.remove('hidden');
                document.querySelector('.admin-sidebar-expand-icon')?.classList.add('hidden');
                adminSidebarCollapse?.setAttribute('title', 'Recolher menu');
            }
        }

        function toggleSidebarCollapsed() {
            if (window.innerWidth < 768) return;
            const collapsed = adminSidebar.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
            if (collapsed) {
                adminMainContent.classList.remove('md:ml-64');
                adminMainContent.classList.add('md:ml-[72px]');
                document.querySelector('.admin-sidebar-collapse-icon')?.classList.add('hidden');
                document.querySelector('.admin-sidebar-expand-icon')?.classList.remove('hidden');
                adminSidebarCollapse?.setAttribute('title', 'Expandir menu');
            } else {
                adminMainContent.classList.remove('md:ml-[72px]');
                adminMainContent.classList.add('md:ml-64');
                document.querySelector('.admin-sidebar-collapse-icon')?.classList.remove('hidden');
                document.querySelector('.admin-sidebar-expand-icon')?.classList.add('hidden');
                adminSidebarCollapse?.setAttribute('title', 'Recolher menu');
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        adminSidebarCollapse?.addEventListener('click', toggleSidebarCollapsed);

        (function initSidebarCollapsed() {
            if (window.innerWidth < 768) return;
            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved === '1') applySidebarCollapsed(true);
            } catch (e) {}
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })();

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) { // Desktop breakpoint
                adminSidebar.classList.remove('-translate-x-full', 'open');
                adminSidebarOverlay.classList.add('hidden');
                adminSidebarOverlay.classList.remove('open');
                body.classList.remove('overflow-hidden');
                try {
                    const saved = localStorage.getItem(STORAGE_KEY);
                    applySidebarCollapsed(saved === '1');
                } catch (e) {}
            } else { // Mobile breakpoint
                adminSidebar.classList.remove('sidebar-collapsed');
                adminMainContent?.classList.remove('md:ml-[72px]');
                adminMainContent?.classList.add('md:ml-64');
                if (!adminSidebar.classList.contains('open')) {
                    adminSidebar.classList.add('-translate-x-full');
                }
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });

        // --- Dropdown Perfil Admin ---
        function toggleAdminProfileDropdown() {
            const dd = document.getElementById('admin-profile-dropdown');
            const arrow = document.getElementById('admin-dropdown-arrow');
            dd?.classList.toggle('hidden');
            arrow?.classList.toggle('rotate-180');
        }
        function closeAdminProfileDropdown() {
            document.getElementById('admin-profile-dropdown')?.classList.add('hidden');
            document.getElementById('admin-dropdown-arrow')?.classList.remove('rotate-180');
        }
        document.addEventListener('click', function(e) {
            const container = document.getElementById('admin-profile-dropdown-container');
            if (container && !container.contains(e.target)) closeAdminProfileDropdown();
        });

        // --- Modal Editar Perfil Admin ---
        function openAdminProfileModal() {
            closeAdminProfileDropdown();
            document.getElementById('admin-profile-modal')?.classList.remove('hidden');
            document.getElementById('admin-profile-modal')?.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
        function closeAdminProfileModal() {
            document.getElementById('admin-profile-modal')?.classList.add('hidden');
            document.getElementById('admin-profile-modal')?.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
        document.getElementById('admin-profile-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('admin-btn-save-profile');
            const errorDiv = document.getElementById('admin-profile-error');
            const successDiv = document.getElementById('admin-profile-success');
            const novaSenha = document.getElementById('admin-profile-nova-senha')?.value || '';
            const confirmar = document.getElementById('admin-profile-confirmar-senha')?.value || '';
            if (novaSenha && novaSenha !== confirmar) {
                errorDiv.textContent = 'As senhas não coincidem.';
                errorDiv.classList.remove('hidden');
                successDiv.classList.add('hidden');
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Salvando...';
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            fetch('/api/admin_api.php?action=update_admin_profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nome: document.getElementById('admin-profile-nome')?.value,
                    email: document.getElementById('admin-profile-email')?.value,
                    senha_atual: document.getElementById('admin-profile-senha-atual')?.value,
                    nova_senha: novaSenha
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message || 'Perfil atualizado!';
                    successDiv.classList.remove('hidden');
                    const headerBtn = document.querySelector('#admin-profile-dropdown-container button span');
                    if (headerBtn && data.nome) headerBtn.textContent = 'Olá, ' + data.nome + '!';
                    if (data.email_changed) {
                        setTimeout(() => { alert('Email alterado. Faça login novamente.'); window.location.href = '/login'; }, 1500);
                    } else {
                        setTimeout(closeAdminProfileModal, 2000);
                    }
                } else {
                    errorDiv.textContent = data.error || 'Erro ao atualizar.';
                    errorDiv.classList.remove('hidden');
                }
            })
            .catch(err => {
                errorDiv.textContent = 'Erro ao atualizar perfil.';
                errorDiv.classList.remove('hidden');
            })
            .finally(() => { btn.disabled = false; btn.textContent = 'Salvar'; });
        });

        // --- Lógica de Notificações Flutuantes (Live Notifications) ---
        const liveNotificationContainer = document.getElementById('live-notification-container');
        const liveNotificationMessage = document.getElementById('live-notification-message');
        const liveNotificationDetails = document.getElementById('live-notification-details');
        const liveNotificationProductImage = document.getElementById('live-notification-product-image');
        const liveNotificationImageFallback = <?php echo json_encode($notification_image_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function resolveLiveNotificationProductImageSrc(produtoFoto, fallbackUrl) {
            if (!produtoFoto) return fallbackUrl;
            const s = String(produtoFoto).trim();
            if (s.startsWith('http://') || s.startsWith('https://')) return s;
            const clean = s.replace(/^\/+/, '');
            if (clean.startsWith('uploads/')) return '/' + clean;
            return '/uploads/' + clean;
        }

        const cashRegisterSound = document.getElementById('cash-register-sound');

        let audioContextResumed = false;
        let notificationQueue = [];
        let isDisplayingNotification = false;

        function tryResumeAudioContext() {
            if (!audioContextResumed && cashRegisterSound) {
                const originalVolume = cashRegisterSound.volume; // Store original volume
                cashRegisterSound.volume = 0; // Set volume to 0 for silent unlock

                if (!cashRegisterSound.src || cashRegisterSound.readyState < 2) {
                    cashRegisterSound.load();
                    cashRegisterSound.oncanplaythrough = () => {
                         cashRegisterSound.play().then(() => {
                            audioContextResumed = true;
                            cashRegisterSound.pause();
                            cashRegisterSound.currentTime = 0;
                            cashRegisterSound.volume = originalVolume; // Restore original volume
                        }).catch(e => {
                            console.warn("Autoplay prevented after load, waiting for user interaction.", e);
                            cashRegisterSound.volume = originalVolume; // Restore original volume on error
                        });
                        cashRegisterSound.oncanplaythrough = null;
                    };
                    return;
                }
                cashRegisterSound.play().then(() => {
                    audioContextResumed = true;
                    cashRegisterSound.pause();
                    cashRegisterSound.currentTime = 0;
                    cashRegisterSound.volume = originalVolume; // Restore original volume
                }).catch(e => {
                    console.warn("Autoplay was prevented, waiting for user interaction.", e);
                    cashRegisterSound.volume = originalVolume; // Restore original volume on error
                });
            }
        }
        document.addEventListener('click', tryResumeAudioContext, { once: true });
        document.addEventListener('keydown', tryResumeAudioContext, { once: true });

        async function fetchLiveNotifications() {
            try {
                const response = await fetch('/api/notifications_api?action=get_live_notifications');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();

                if (data.live_notifications && data.live_notifications.length > 0) {
                    for (const notification of data.live_notifications) {
                        notificationQueue.push(notification); 
                        await fetch('/api/notifications_api?action=mark_as_displayed_live', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `notification_id=${notification.id}`
                        });
                    }
                    processNotificationQueue();
                }
            } catch (error) {
                console.error('Error fetching live notifications:', error);
            }
        }

        function processNotificationQueue() {
            if (!isDisplayingNotification && notificationQueue.length > 0) {
                isDisplayingNotification = true;
                const notification = notificationQueue.shift();
                _actualDisplayLiveNotification(notification);
            }
        }

        function _actualDisplayLiveNotification(notification) {
            const allowedTypes = ['Compra Aprovada', 'Pix Gerado', 'Boleto Gerado'];
            if (!allowedTypes.includes(notification.tipo)) {
                isDisplayingNotification = false;
                processNotificationQueue();
                return;
            }

            let messageText = '';
            let detailsText = '';
            const value = parseFloat(notification.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const productName = notification.produto_nome || 'Um produto';

            switch (notification.tipo) {
                case 'Compra Aprovada':
                    messageText = `Nova Compra Aprovada!`;
                    detailsText = `${productName} por ${value} (${notification.metodo_pagamento})`;
                    break;
                case 'Pix Gerado':
                    messageText = `Pix Gerado!`;
                    detailsText = `${productName} por ${value}`;
                    break;
                case 'Boleto Gerado':
                    messageText = `Boleto Gerado!`;
                    detailsText = `${productName} por ${value}`;
                    break;
                default:
                    isDisplayingNotification = false;
                    processNotificationQueue();
                    return;
            }

            liveNotificationMessage.textContent = messageText;
            liveNotificationDetails.textContent = detailsText;
            liveNotificationProductImage.src = resolveLiveNotificationProductImageSrc(notification.produto_foto, liveNotificationImageFallback);
            
            if (cashRegisterSound && audioContextResumed) {
                cashRegisterSound.load();
                cashRegisterSound.currentTime = 0;
                cashRegisterSound.volume = 1; // Ensure volume is audible for real notifications
                cashRegisterSound.play().catch(e => console.error("Error playing sound:", e));
            }

            liveNotificationContainer.classList.add('show');
            setTimeout(() => {
                liveNotificationContainer.classList.remove('show');
                isDisplayingNotification = false;
                processNotificationQueue();
            }, 8000);
        }
        
        // Polling for live notifications (more frequent)
        fetchLiveNotifications();
        setInterval(fetchLiveNotifications, 10000);

    </script>
    <script>
        // Move lucide.createIcons() to the very end of the body to ensure all elements are parsed.
        lucide.createIcons();
    </script>
    <script>
        // Registra o Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').then(registration => {
                    console.log('ServiceWorker registrado com sucesso: ', registration.scope);
                }, err => {
                    console.log('Falha no registro do ServiceWorker: ', err);
                });
            });
        }
    </script>
</body>
</html>
