<?php
// Inclui o arquivo de configuração que inicia a sessão
require_once __DIR__ . '/config/config.php';

// Verifica se o usuário está logado, se não, redireciona para a página de login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /login");
    exit;
}

// Define a página antes da verificação de acesso
$pagina = isset($_GET['pagina']) ? $_GET['pagina'] : 'dashboard';

// Verifica acesso SaaS (se plugin estiver ativo)
if (plugin_active('saas')) {
    require_once __DIR__ . '/plugins/saas/includes/notifications.php';
    require_once __DIR__ . '/plugins/saas/saas.php';
    
    // Garante que usuário tenha plano free se disponível
    if ($_SESSION['tipo'] !== 'admin') {
        saas_ensure_free_plan($_SESSION['id']);
    }
    
    // Se não tiver acesso e não estiver na página de planos, redireciona
    if (!saas_check_user_access($_SESSION['id']) && $_SESSION['tipo'] !== 'admin' && $pagina !== 'planos') {
        $_SESSION['flash_message'] = "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded relative mb-4' role='alert'>Você precisa adquirir um plano para continuar usando a plataforma.</div>";
        header("location: /index?pagina=planos");
        exit;
    }
}

// Se o usuário logado for um administrador, redireciona para o painel de administração.
// Isso garante que admins não acessem o painel de usuário/infoprodutor.
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] === 'admin') {
    header("location: /admin");
    exit;
}

// Se o usuário logado for um cliente/aluno (tipo 'usuario'), redireciona para a área de membros.
// Isso garante que clientes não acessem o painel de infoprodutor.
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] === 'usuario') {
    header("location: /member_area_dashboard");
    exit;
}

// A página index.php é agora o dashboard unificado para infoprodutores,
// sem redirecionamento condicional para mobile_dashboard_charts.php
// A distinção entre desktop e PWA será apenas na experiência do navegador/aplicativo instalado,
// mas a base do conteúdo será a mesma.
// A remoção de $_SESSION['is_pwa_session'] e sua lógica relacionada é feita.


// Fetch user data for display in the header
$user_id_display = $_SESSION['id'];
$user_name_display = htmlspecialchars($_SESSION['usuario']); // Fallback to session username/email
$foto_perfil = null;

$user_email_display = $_SESSION['usuario'] ?? '';
$infoprodutor_data_cadastro = null;
try {
    $stmt = $pdo->prepare("SELECT nome, usuario, foto_perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id_display]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_name_display = htmlspecialchars($user_data['nome'] ?? $_SESSION['usuario']);
        $user_email_display = $user_data['usuario'] ?? $_SESSION['usuario'];
        $foto_perfil = htmlspecialchars($user_data['foto_perfil'] ?? '');
    }
    $chk = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'data_cadastro'");
    if ($chk && $chk->rowCount() > 0) {
        $stmt_dc = $pdo->prepare("SELECT data_cadastro FROM usuarios WHERE id = ?");
        $stmt_dc->execute([$user_id_display]);
        $infoprodutor_data_cadastro = $stmt_dc->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Error fetching user data for index.php: " . $e->getMessage());
}


// Lista de páginas permitidas para segurança
$paginas_permitidas = ['dashboard', 'produtos', 'configuracoes', 'checkout_editor', 'checkout_editor_preview', 'produto_config', 'categorias_produto', 'cupons', 'vendas', 'area_membros', 'gerenciar_curso', 'profile', 'infoprodutor_member_offers', 'tracking', 'integracoes', 'integracoes_webhooks', 'integracoes_utmfy', 'integracoes_evolution', 'integracoes_api', 'clonar_site', 'planos', 'clientes'];

// Lógica para link ativo do menu - Modern Glassmorphism Design
$active_class = 'sidebar-item sidebar-item-active';
$inactive_class = 'sidebar-item sidebar-item-inactive';

// Inicia o buffer de saída. Isso captura todo o HTML que seria gerado,
// permitindo que a página 'gerenciar_curso.php' use a função header() para redirecionar sem erros.
ob_start();

// Exibe a mensagem flash (se existir) dentro do buffer
if (isset($_SESSION['flash_message']) && !empty($_SESSION['flash_message'])) {
    echo '<div class="mb-6">';
    echo $_SESSION['flash_message'];
    echo '</div>';
    unset($_SESSION['flash_message']); // Limpa a mensagem após exibir
}

// Exibe mensagens de feedback do perfil (se existir)
if (isset($_SESSION['profile_feedback_for_js']) && !empty($_SESSION['profile_feedback_for_js'])) {
    $profile_messages_html = '';
    foreach ($_SESSION['profile_feedback_for_js'] as $msg) {
        $profile_messages_html .= '<p>' . htmlspecialchars($msg) . '</p>';
    }
    echo '<div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">';
    echo $profile_messages_html;
    echo '</div>';
    unset($_SESSION['profile_feedback_for_js']);
}

// Inclui a página solicitada (como 'gerenciar_curso.php') dentro do buffer
if (in_array($pagina, $paginas_permitidas) && file_exists(__DIR__ . '/views/' . $pagina . '.php')) {
    include __DIR__ . '/views/' . $pagina . '.php';
} else {
    // Se a página não for encontrada, mostra um erro 404
    echo "<div class='text-center p-10 bg-dark-card rounded-lg shadow border border-dark-border'><h1 class='text-4xl font-bold text-white'>Erro 404</h1><p class='mt-2 text-gray-400'>Página não encontrada.</p></div>";
}

// Captura todo o conteúdo do buffer para a variável $page_content
$page_content = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Usuário</title>
    <?php include __DIR__ . '/config/load_settings.php'; ?>
    
    <!-- PWA Tags (manifest dinâmico quando módulo PWA ativado) -->
    <?php
    $pwa_manifest_href = 'manifest.json';
    $pwa_theme_color = '#2DD05E';
    try {
        if (isset($pdo)) {
            $st = $pdo->query("SELECT valor FROM configuracoes_sistema WHERE chave = 'pwa_activated' LIMIT 1");
            if ($st && ($r = $st->fetch(PDO::FETCH_ASSOC)) && ($r['valor'] ?? '') === '1') {
                $pwa_manifest_href = '/pwa/manifest.php';
                $st2 = $pdo->query("SELECT theme_color FROM pwa_config ORDER BY id DESC LIMIT 1");
                if ($st2 && ($r2 = $st2->fetch(PDO::FETCH_ASSOC)) && !empty($r2['theme_color'])) $pwa_theme_color = $r2['theme_color'];
            }
        }
    } catch (Exception $e) {}
    ?>
    <meta name="theme-color" content="<?php echo htmlspecialchars($pwa_theme_color); ?>">
    <link rel="manifest" href="<?php echo htmlspecialchars($pwa_manifest_href); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Plataforma">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            orange: {
              50: '#fff7ed',
              100: '#ffedd5',
              200: '#fed7aa',
              300: '#fdba74',
              400: '#fb923c',
              500: '#f97316',
              600: '#ea580c',
              700: '#c2410c',
              800: '#9a3412',
              900: '#7c2d12',
            },
          }
        }
      }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Estilos para o sino de notificações */
        .notification-bell-container {
            position: relative;
            cursor: pointer;
            padding: 8px;
            border-radius: 9999px; /* Full rounded */
            transition: background-color 0.2s;
        }
        .notification-bell-container:hover {
            background-color: #f3f4f6; /* Gray-100 */
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #2DD05E; /* Orange-500 */
            color: white;
            font-size: 0.75rem; /* text-xs */
            font-weight: 700; /* font-bold */
            border-radius: 9999px; /* Full rounded */
            padding: 0.15rem 0.4rem;
            min-width: 1.25rem; /* w-5 h-5 */
            height: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            transform: translate(25%, -25%);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s;
        }
        .notification-popup {
            position: fixed;
            top: 0;
            right: 0;
            width: 320px;
            height: 100vh;
            background-color: #0f1419;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: -4px 0 15px rgba(0,0,0,0.3);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
        }
        .notification-popup.open {
            transform: translateX(0);
        }
        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-list {
            flex-grow: 1;
            overflow-y: auto;
        }
        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            transition: background-color 0.2s;
            color: rgba(255, 255, 255, 0.9);
        }
        .notification-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        .notification-item.unread {
            background-color: rgba(50, 231, 104, 0.1);
            font-weight: 500;
        }
        .notification-icon {
            flex-shrink: 0;
            width: 1.25rem;
            height: 1.25rem;
            color: var(--accent-primary);
            margin-top: 2px;
        }
        .notification-item-message {
            flex-grow: 1;
            font-size: 0.875rem;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.9);
        }
        .notification-item-time {
            flex-shrink: 0;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            white-space: nowrap;
        }
        .empty-notifications {
            padding: 1.5rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
        }

        /* Live Floating Notification */
        .live-notification-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 320px;
            background-color: #0f1419;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
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
            border: 1px solid #e5e7eb;
        }
        .cash-register-sound {
            display: none; /* Hide audio element */
        }

        /* Responsividade para o menu lateral */
        #sidebar {
            width: 100%;
            max-width: 280px; /* Ajuste para um tamanho mais comum em mobile */
            transform: translateX(-100%); /* Escondido por padrão */
        }
        #sidebar.open {
            transform: translateX(0); /* Visível quando aberto */
        }
        #sidebar-overlay {
            display: none; /* Escondido por padrão */
        }
        #sidebar-overlay.open {
            display: block; /* Visível quando o menu está aberto */
        }
        /* Ajuste do conteúdo principal para telas menores */
        main {
            margin-left: 0; /* Remove a margem fixa em mobile */
        }
        /* Oculta o botão de toggle em telas maiores */
        #sidebar-toggle {
            display: flex; /* Exibe por padrão em mobile */
        }

        /* Media query para telas maiores (desktop) */
        @media (min-width: 768px) { /* md breakpoint */
            #sidebar {
                transform: translateX(0); /* Sempre visível em desktop */
                width: 256px; /* md:w-64 */
            }
            #sidebar-toggle {
                display: none; /* Oculta em desktop */
            }
            main {
                margin-left: 256px; /* md:ml-64 */
            }
            #sidebar-overlay {
                display: none; /* Nunca visível em desktop */
            }
        }

    </style>
</head>
<body class="font-sans flex flex-col min-h-screen bg-dark-base">
    <?php include __DIR__ . '/views/includes/session_heartbeat.php'; ?>
    <!-- Header Fixo Invisível (Topo) -->
    <header class="fixed top-0 left-0 right-0 z-40 bg-dark-base/80 backdrop-blur-sm h-[60px] flex items-center justify-between px-4 md:px-6">
        <!-- Botão de Toggle Mobile -->
        <button id="sidebar-toggle" class="md:hidden p-2 rounded-lg bg-dark-elevated border border-dark-border text-white hover:bg-dark-card transition-colors">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
        <div class="hidden md:block"></div> <!-- Espaçador para desktop -->
        
        <!-- Controles do Header (Notificação, Perfil, Logout) -->
        <div class="flex items-center space-x-3">
            <!-- Sininho de Notificações -->
            <div id="notification-bell" class="notification-bell-container flex items-center justify-center relative cursor-pointer p-2 rounded-lg hover:bg-dark-elevated transition-colors">
                <i data-lucide="bell" id="bell-icon" class="w-6 h-6 text-gray-400 hover:text-white transition-colors"></i>
                <span id="notification-badge" class="notification-badge hidden">0</span>
            </div>

            <!-- Dropdown Perfil (igual ao admin) -->
            <div class="relative" id="infoprodutor-profile-dropdown-container">
                <button type="button" onclick="toggleInfoprodutorProfileDropdown()" class="flex items-center space-x-2 font-medium text-gray-300 hover:text-white transition-colors cursor-pointer px-3 py-2 rounded-lg hover:bg-dark-elevated">
                    <?php if (!empty($foto_perfil)): ?>
                        <img src="uploads/<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto" class="w-10 h-10 rounded-full object-cover shadow-sm" style="border: 2px solid var(--accent-primary);">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-lg font-bold shadow-lg" style="background-color: var(--accent-primary);">
                            <?php echo strtoupper(substr($user_name_display, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-sm font-semibold text-white hidden sm:block"><?php echo $user_name_display; ?></span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" id="infoprodutor-dropdown-arrow"></i>
                </button>
                <div id="infoprodutor-profile-dropdown" class="hidden absolute right-0 mt-2 w-52 bg-dark-card border border-dark-border rounded-lg shadow-xl py-2 z-50">
                    <button type="button" onclick="openInfoprodutorProfileModal()" class="w-full flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-dark-elevated hover:text-white transition-colors text-left">
                        <i data-lucide="user-cog" class="w-4 h-4"></i>
                        <span>Editar Perfil</span>
                    </button>
                    <a href="/index?pagina=profile" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-dark-elevated hover:text-white transition-colors">
                        <i data-lucide="user" class="w-4 h-4"></i>
                        <span>Meu Perfil</span>
                    </a>
                    <hr class="border-dark-border my-2">
                    <a href="/logout" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-dark-elevated hover:text-red-400 transition-colors">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Modal Editar Perfil Infoprodutor -->
    <div id="infoprodutor-profile-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-dark-card rounded-xl border border-dark-border w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between p-6 border-b border-dark-border">
                <h3 class="text-xl font-bold text-white">Editar Perfil</h3>
                <button type="button" onclick="closeInfoprodutorProfileModal()" class="text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <form id="infoprodutor-profile-form" class="p-6 space-y-4">
                <?php if ($infoprodutor_data_cadastro): ?>
                <div class="pb-2 border-b border-dark-border">
                    <p class="text-sm text-gray-400">Data de cadastro</p>
                    <p class="text-white font-medium"><?php echo date('d/m/Y', strtotime($infoprodutor_data_cadastro)); ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <label for="infoprodutor-profile-nome" class="block text-sm font-medium text-gray-300 mb-2">Nome</label>
                    <input type="text" id="infoprodutor-profile-nome" name="nome" value="<?php echo htmlspecialchars($user_name_display); ?>" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent">
                </div>
                <div>
                    <label for="infoprodutor-profile-email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" id="infoprodutor-profile-email" name="email" value="<?php echo htmlspecialchars($user_email_display); ?>" required class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent">
                </div>
                <div>
                    <label for="infoprodutor-profile-senha-atual" class="block text-sm font-medium text-gray-300 mb-2">Senha Atual</label>
                    <input type="password" id="infoprodutor-profile-senha-atual" name="senha_atual" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent" placeholder="Digite para confirmar alterações">
                </div>
                <div>
                    <label for="infoprodutor-profile-nova-senha" class="block text-sm font-medium text-gray-300 mb-2">Nova Senha <span class="text-gray-500">(opcional)</span></label>
                    <input type="password" id="infoprodutor-profile-nova-senha" name="nova_senha" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent" placeholder="Deixe vazio para manter a atual">
                </div>
                <div>
                    <label for="infoprodutor-profile-confirmar-senha" class="block text-sm font-medium text-gray-300 mb-2">Confirmar Nova Senha</label>
                    <input type="password" id="infoprodutor-profile-confirmar-senha" name="confirmar_senha" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[var(--accent-primary)] focus:border-transparent" placeholder="Repita a nova senha">
                </div>
                <div id="infoprodutor-profile-error" class="hidden bg-red-900/30 border border-red-500 text-red-300 px-4 py-3 rounded-lg text-sm"></div>
                <div id="infoprodutor-profile-success" class="hidden bg-green-900/30 border border-green-500 text-green-300 px-4 py-3 rounded-lg text-sm"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeInfoprodutorProfileModal()" class="flex-1 px-4 py-3 bg-dark-elevated text-gray-300 rounded-lg font-semibold hover:bg-dark-card transition-colors border border-dark-border">Cancelar</button>
                    <button type="submit" id="infoprodutor-btn-save-profile" class="flex-1 px-4 py-3 rounded-lg font-semibold transition-colors" style="background-color: var(--accent-primary); color: white;">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Popup de Notificações Lateral -->
    <div id="notification-popup" class="notification-popup">
        <div class="notification-header">
            <h3 class="text-lg font-bold text-white">Notificações</h3>
            <button id="close-notification-popup" class="text-gray-400 hover:text-white p-1 rounded-full hover:bg-dark-elevated transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div id="notification-list" class="notification-list">
            <div class="empty-notifications" id="empty-notifications-state">
                <i data-lucide="bell-off" class="mx-auto w-12 h-12 text-gray-500 mb-2"></i>
                <p class="text-sm text-gray-400">Nenhuma notificação recente.</p>
            </div>
            <!-- Notifications will be loaded here by JavaScript -->
        </div>
    </div>


    <!-- Menu Lateral (Sidebar) -->
    <aside id="sidebar" class="sidebar-glass fixed top-0 left-0 bottom-0 z-50 transform -translate-x-full transition-transform duration-300 w-full max-w-xs md:translate-x-0 md:w-64 flex flex-col overflow-y-auto">
        <!-- Sidebar Header (Logo) -->
        <div class="sidebar-header">
            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logotipo" class="h-10 w-auto">
        </div>
        
        <nav class="mt-4 flex-grow px-2">
            <a href="/index?pagina=dashboard" class="<?php echo $pagina == 'dashboard' ? $active_class : $inactive_class; ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>Dashboard</span>
            </a>
            <a href="/index?pagina=vendas" class="<?php echo $pagina == 'vendas' ? $active_class : $inactive_class; ?>">
                <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                <span>Vendas</span>
            </a>
            <a href="/index?pagina=produtos" class="<?php echo ($pagina == 'produtos' || $pagina == 'checkout_editor' || $pagina == 'produto_config') ? $active_class : $inactive_class; ?>">
                <i data-lucide="package" class="w-5 h-5"></i>
                <span>Produtos</span>
            </a>
            <a href="/index?pagina=categorias_produto" class="<?php echo $pagina == 'categorias_produto' ? $active_class : $inactive_class; ?> pl-8 text-sm">
                <i data-lucide="tags" class="w-4 h-4"></i>
                <span>Categorias</span>
            </a>
            <a href="/index?pagina=cupons" class="<?php echo $pagina == 'cupons' ? $active_class : $inactive_class; ?> pl-8 text-sm">
                <i data-lucide="ticket" class="w-4 h-4"></i>
                <span>Cupons</span>
            </a>
            <a href="/index?pagina=area_membros" class="<?php echo ($pagina == 'area_membros' || $pagina == 'gerenciar_curso' || $pagina == 'infoprodutor_member_offers') ? $active_class : $inactive_class; ?>">
                <i data-lucide="play-square" class="w-5 h-5"></i>
                <span>Área de Membros</span>
            </a>
            <a href="/index?pagina=clientes" class="<?php echo $pagina == 'clientes' ? $active_class : $inactive_class; ?>">
                <i data-lucide="users" class="w-5 h-5"></i>
                <span>Clientes</span>
            </a>
            <!-- 
            <a href="/index?pagina=tracking" class="<?php echo $pagina == 'tracking' ? $active_class : $inactive_class; ?>">
                <i data-lucide="line-chart" class="w-5 h-5"></i>
                <span>Tracking</span>
            </a>
            -->
            <!-- 
            <a href="/index?pagina=clonar_site" class="<?php echo $pagina == 'clonar_site' ? $active_class : $inactive_class; ?>">
                <i data-lucide="copy-check" class="w-5 h-5"></i>
                <span>Clonar Site</span>
            </a>
             -->
            <a href="/index?pagina=integracoes" class="<?php echo (in_array($pagina, ['integracoes', 'integracoes_webhooks', 'integracoes_utmfy'])) ? $active_class : $inactive_class; ?>">
                <i data-lucide="plug-zap" class="w-5 h-5"></i>
                <span>Integrações</span>
            </a>
            <?php
            // Itens de menu dinâmicos de plugins (SaaS - Planos)
            if (function_exists('do_action')) {
                global $plugin_hooks;
                $all_menu_items = [];
                
                // Coleta todos os arrays retornados pelos hooks
                if (isset($plugin_hooks['infoprodutor_menu_items'])) {
                    foreach ($plugin_hooks['infoprodutor_menu_items'] as $hook) {
                        if (is_callable($hook['callback'])) {
                            $items = call_user_func($hook['callback']);
                            if (is_array($items)) {
                                $all_menu_items = array_merge($all_menu_items, $items);
                            }
                        }
                    }
                }
                
                foreach ($all_menu_items as $item) {
                    if (isset($item['title']) && isset($item['url'])) {
                        $icon = $item['icon'] ?? 'settings';
                        $item_pagina = parse_str(parse_url($item['url'], PHP_URL_QUERY), $params);
                        $item_pagina = $params['pagina'] ?? '';
                        $is_active = ($pagina === $item_pagina || strpos($_SERVER['REQUEST_URI'], $item['url']) !== false);
                        echo '<a href="' . htmlspecialchars($item['url']) . '" class="' . ($is_active ? $active_class : $inactive_class) . '">';
                        echo '<i data-lucide="' . htmlspecialchars($icon) . '" class="w-5 h-5"></i>';
                        echo '<span>' . htmlspecialchars($item['title']) . '</span>';
                        echo '</a>';
                    }
                }
            }
            ?>
            <?php // O link para o Painel Admin foi removido do painel de usuário, pois admins serão redirecionados diretamente. ?>
        </nav>
        
        <!-- Card do Plano SaaS (parte inferior do sidebar) -->
        <?php
        if (plugin_active('saas') && isset($_SESSION['tipo']) && $_SESSION['tipo'] !== 'admin') {
            require_once __DIR__ . '/plugins/saas/includes/user_dashboard_info.php';
            $plan_info = get_user_plan_dashboard_info($_SESSION['id']);
            if ($plan_info):
        ?>
        <div class="mt-auto px-2 pb-4 pt-4">
            <div class="bg-gradient-to-br from-blue-900/20 via-purple-900/20 to-indigo-900/20 rounded-lg p-3 border border-primary/30">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-white truncate"><?php echo htmlspecialchars($plan_info['plano_nome']); ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">Vence: <?php echo date('d/m/Y', strtotime($plan_info['data_vencimento'])); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <div class="flex-1 bg-dark-elevated/50 rounded px-2 py-1">
                        <span class="text-gray-400">Produtos:</span>
                        <span class="text-white font-semibold">
                            <?php echo $plan_info['produtos_criados']; ?>/<?php echo $plan_info['max_produtos'] ?? '∞'; ?>
                        </span>
                    </div>
                    <div class="flex-1 bg-dark-elevated/50 rounded px-2 py-1">
                        <span class="text-gray-400">Pedidos:</span>
                        <span class="text-white font-semibold">
                            <?php echo $plan_info['pedidos_realizados']; ?>/<?php echo $plan_info['max_pedidos_mes'] ?? '∞'; ?>
                        </span>
                    </div>
                </div>
                <a href="/index?pagina=planos" class="block mt-2 w-full bg-primary/20 hover:bg-primary/30 text-primary text-xs font-semibold py-1.5 px-2 rounded text-center transition-colors">
                    Ver Planos
                </a>
            </div>
        </div>
        <?php
            endif;
        }
        ?>
    </aside>

    <!-- Overlay para o menu mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden"></div>

    <!-- Conteúdo Principal -->
    <main class="flex-1 md:ml-64 mt-[60px] p-6 lg:p-8 overflow-y-auto">
        <!-- Banner: ativar notificações push -->
        <div id="pwa-push-banner" class="hidden mb-4" role="region" aria-label="Notificações">
            <div class="flex items-center justify-between gap-4 rounded-lg border border-dark-border bg-dark-card px-4 py-3 text-sm">
                <span id="pwa-push-banner-text" class="text-gray-300"></span>
                <button type="button" id="pwa-push-banner-btn" class="hidden shrink-0 rounded-lg px-4 py-2 font-medium text-white transition" style="background: var(--accent-primary);">Receber notificações</button>
            </div>
        </div>
        <!-- Card pós-login: notificações + instalar como app -->
        <div id="pwa-welcome-card" class="hidden mb-4">
            <div class="rounded-xl border border-dark-border bg-dark-card p-6 shadow-lg">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-1">Melhore sua experiência</h3>
                        <p class="text-gray-400 text-sm mb-4">Ative as notificações e instale o painel como app no celular ou computador.</p>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" id="pwa-welcome-notify-btn" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white transition" style="background: var(--accent-primary);">Receber notificações</button>
                            <button type="button" id="pwa-welcome-install-btn" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-500 transition">Instalar como app</button>
                            <button type="button" id="pwa-welcome-close" class="inline-flex items-center gap-2 rounded-lg border border-dark-border px-4 py-2.5 text-sm font-medium text-gray-400 hover:text-white transition">Agora não</button>
                        </div>
                    </div>
                    <button type="button" id="pwa-welcome-dismiss" class="text-gray-500 hover:text-white p-1 rounded" aria-label="Fechar"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <p id="pwa-install-hint" class="hidden mt-3 text-xs text-gray-500">Chrome: menu (⋮) → Instalar aplicativo. Celular: Adicionar à tela inicial.</p>
            </div>
        </div>
        <?php
        // Agora, simplesmente exibe o conteúdo que foi capturado no buffer
        echo $page_content;
        ?>
    </main>

    <?php
    // Modal de Banner (só na área de produtos) — no layout para o script rodar sempre
    if (in_array($pagina, ['produtos', 'checkout_editor', 'produto_config']) && file_exists(__DIR__ . '/views/includes/banner_modal.php')) {
        include __DIR__ . '/views/includes/banner_modal.php';
    }
    ?>

    <!-- Floating Live Notification -->
    <div id="live-notification-container" class="live-notification-container">
        <!-- Substituído o ícone padrão pela URL fornecida -->
        <img id="live-notification-product-image" src="<?php echo htmlspecialchars($notification_image_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Notificação" class="live-notification-product-image">
        <div>
            <p class="text-sm font-semibold text-white" id="live-notification-message"></p>
            <p class="text-xs text-gray-400 mt-1" id="live-notification-details"></p>
        </div>
        <audio id="cash-register-sound" class="cash-register-sound" src="assets/cash_register.mp3" preload="auto"></audio>
    </div>

    <script>
        // Move lucide.createIcons() to the very end of the body to ensure all elements are parsed.
        lucide.createIcons();

        // --- Lógica de Responsividade do Menu Lateral ---
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const body = document.body;

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('open'); // Adiciona a classe open para controle de visibilidade
            sidebarOverlay.classList.toggle('hidden');
            sidebarOverlay.classList.toggle('open'); // Adiciona a classe open ao overlay
            body.classList.toggle('overflow-hidden'); // Previne o scroll do body quando o sidebar está aberto
        }

        sidebarToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar); // Fechar o sidebar ao clicar no overlay

        // Close sidebar if window resized to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) { // Tailwind's 'md' breakpoint
                sidebar.classList.remove('-translate-x-full', 'open');
                sidebarOverlay.classList.add('hidden', 'open');
                body.classList.remove('overflow-hidden');
            }
        });

        // --- Dropdown e Modal Editar Perfil Infoprodutor ---
        function toggleInfoprodutorProfileDropdown() {
            const dropdown = document.getElementById('infoprodutor-profile-dropdown');
            const arrow = document.getElementById('infoprodutor-dropdown-arrow');
            if (dropdown) dropdown.classList.toggle('hidden');
            if (arrow) arrow.style.transform = dropdown && !dropdown.classList.contains('hidden') ? 'rotate(180deg)' : '';
        }
        function closeInfoprodutorProfileDropdown() {
            const dropdown = document.getElementById('infoprodutor-profile-dropdown');
            const arrow = document.getElementById('infoprodutor-dropdown-arrow');
            if (dropdown) dropdown.classList.add('hidden');
            if (arrow) arrow.style.transform = '';
        }
        document.addEventListener('click', (e) => {
            const container = document.getElementById('infoprodutor-profile-dropdown-container');
            if (container && !container.contains(e.target)) closeInfoprodutorProfileDropdown();
        });
        function openInfoprodutorProfileModal() {
            closeInfoprodutorProfileDropdown();
            const modal = document.getElementById('infoprodutor-profile-modal');
            if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
            document.body.classList.add('overflow-hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
        function closeInfoprodutorProfileModal() {
            const modal = document.getElementById('infoprodutor-profile-modal');
            if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
            document.body.classList.remove('overflow-hidden');
        }
        document.getElementById('infoprodutor-profile-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('infoprodutor-btn-save-profile');
            const errorDiv = document.getElementById('infoprodutor-profile-error');
            const successDiv = document.getElementById('infoprodutor-profile-success');
            const novaSenha = document.getElementById('infoprodutor-profile-nova-senha')?.value || '';
            const confirmar = document.getElementById('infoprodutor-profile-confirmar-senha')?.value || '';
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
            fetch('/api/api?action=update_infoprodutor_profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nome: document.getElementById('infoprodutor-profile-nome')?.value,
                    email: document.getElementById('infoprodutor-profile-email')?.value,
                    senha_atual: document.getElementById('infoprodutor-profile-senha-atual')?.value,
                    nova_senha: novaSenha
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message || 'Perfil atualizado!';
                    successDiv.classList.remove('hidden');
                    const headerSpan = document.querySelector('#infoprodutor-profile-dropdown-container button span');
                    if (headerSpan && data.nome) headerSpan.textContent = data.nome;
                    if (data.email_changed) {
                        setTimeout(() => { alert('Email alterado. Faça login novamente.'); window.location.href = '/login'; }, 1500);
                    } else {
                        setTimeout(closeInfoprodutorProfileModal, 2000);
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

        // --- Lógica de Notificações ---
        const notificationBell = document.getElementById('notification-bell');
        const bellIcon = document.getElementById('bell-icon');
        const notificationBadge = document.getElementById('notification-badge');
        const notificationPopup = document.getElementById('notification-popup');
        const closePopupBtn = document.getElementById('close-notification-popup');
        const notificationList = document.getElementById('notification-list');
        const emptyNotificationsState = document.getElementById('empty-notifications-state');
        // Floating Live Notification elements
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

        // Flag to prevent repeated attempts to resume audio context
        let audioContextResumed = false;
        // Queue for live notifications
        let notificationQueue = [];
        let isDisplayingNotification = false;

        // Function to attempt to resume audio context (unlock audio playback)
        function tryResumeAudioContext() {
            if (!audioContextResumed && cashRegisterSound) {
                // Store original volume
                const originalVolume = cashRegisterSound.volume;
                // Set volume to 0 for silent unlock attempt
                cashRegisterSound.volume = 0;

                // Ensure the audio element has a valid source and is loaded
                if (!cashRegisterSound.src || cashRegisterSound.readyState < 2) {
                    cashRegisterSound.load();
                    // Wait for it to load, then try to play (or rely on next interaction)
                    cashRegisterSound.oncanplaythrough = () => {
                         cashRegisterSound.play().then(() => {
                            audioContextResumed = true;
                            cashRegisterSound.pause();
                            cashRegisterSound.currentTime = 0;
                            cashRegisterSound.volume = originalVolume; // Restore original volume
                        }).catch(e => {
                            console.warn("Autoplay was prevented after load, waiting for user interaction.", e);
                            cashRegisterSound.volume = originalVolume; // Restore original volume on error
                        });
                        cashRegisterSound.oncanplaythrough = null; // Remove handler
                    };
                    return; // Exit, will try again on next interaction/poll
                }

                // If audio is ready, try to play
                cashRegisterSound.play().then(() => {
                    audioContextResumed = true;
                    // Pause it immediately if it's just for unlocking
                    cashRegisterSound.pause();
                    cashRegisterSound.currentTime = 0;
                    cashRegisterSound.volume = originalVolume; // Restore original volume
                }).catch(e => {
                    console.warn("Autoplay was prevented, waiting for user interaction.", e);
                    cashRegisterSound.volume = originalVolume; // Restore original volume on error
                    // This error is expected if no user interaction yet.
                    // We don't mark audioContextResumed as true here.
                });
            }
        }

        // Attach audio context resume attempt to first user interaction
        // Using { once: true } ensures it runs only once per event type
        document.addEventListener('click', tryResumeAudioContext, { once: true });
        document.addEventListener('keydown', tryResumeAudioContext, { once: true });


        // CORREÇÃO: Função formatTimeAgo com mais granularidade e correção de fuso horário
        function formatTimeAgo(timestamp) {
            const now = new Date();
            // A API em 'notification.php' agora formata a data como 'YYYY-MM-DDTHH:MM:SS'.
            // Ao criar um objeto Date com esta string sem um fuso horário explícito ('Z' ou offset),
            // o navegador a interpreta no fuso horário LOCAL do usuário, conforme solicitado.
            const date = new Date(timestamp);
            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 5) return "Agora mesmo";
            if (seconds < 60) return `Há ${seconds} segundo(s) atrás`;

            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return `Há ${minutes} minuto(s) atrás`;

            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `Há ${hours} hora(s) atrás`;

            const days = Math.floor(hours / 24);
            if (days < 30) return `Há ${days} dia(s) atrás`;

            const months = Math.floor(days / 30);
            if (months < 12) return `Há ${months} mês(es) atrás`;

            const years = Math.floor(days / 365);
            return `Há ${years} ano(s) atrás`;
        }


        async function fetchNotificationsCount() {
            try {
                const response = await fetch('/notification?action=get_unread_count'); // Use notification.php
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                
                if (data.count > 0) {
                    notificationBadge.textContent = data.count;
                    notificationBadge.classList.remove('hidden');
                    bellIcon.classList.remove('text-gray-400');
                    bellIcon.classList.add('text-orange-500'); // Cor laranja para notificações
                } else {
                    notificationBadge.classList.add('hidden');
                    bellIcon.classList.remove('text-orange-500');
                    bellIcon.classList.add('text-gray-400'); // Cinza quando não há notificações
                }
            } catch (error) {
                console.error('Error fetching notification count:', error);
            }
        }

        async function fetchRecentNotifications() {
            try {
                const response = await fetch('/notification?action=get_recent_notifications'); // Use notification.php
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();

                notificationList.innerHTML = ''; // Clear previous notifications
                if (data.notifications && data.notifications.length > 0) {
                    emptyNotificationsState.style.display = 'none';
                    data.notifications.forEach(notification => {
                        const item = document.createElement('a');
                        item.href = notification.link_acao || '#'; // If link_acao exists, make it clickable
                        item.target = notification.link_acao ? '_blank' : '_self'; // Open in new tab if there's a link
                        item.classList.add('notification-item');
                        if (notification.lida === 0) {
                            item.classList.add('unread');
                        }

                        // Determine icon based on type (example mapping)
                        let iconName = 'bell'; // Default icon
                        switch (notification.tipo) {
                            case 'Compra Aprovada': iconName = 'check-circle'; break;
                            case 'Pix Gerado': iconName = 'smartphone'; break;
                            case 'Boleto Gerado': iconName = 'file-text'; break;
                            case 'Pagamento Pendente': iconName = 'clock'; break;
                            case 'Pagamento Recusado': iconName = 'x-circle'; break;
                            case 'Reembolso': iconName = 'rotate-ccw'; break;
                            case 'Chargeback': iconName = 'shield-alert'; break;
                            case 'Novo Comentário': iconName = 'message-circle'; break;
                            default: iconName = 'info'; break;
                        }

                        const isInternalLink = notification.link_acao && notification.link_acao.startsWith('/');
                        item.target = (notification.link_acao && !isInternalLink) ? '_blank' : '_self';

                        item.innerHTML = `
                            <i data-lucide="${iconName}" class="notification-icon"></i>
                            <div class="notification-item-message">
                                <span class="font-semibold">${notification.tipo}:</span> ${notification.mensagem}
                            </div>
                            <span class="notification-item-time">${formatTimeAgo(notification.data_notificacao)}</span>
                        `;
                        notificationList.appendChild(item);
                    });
                    lucide.createIcons(); // Re-render Lucide icons for new content
                } else {
                    emptyNotificationsState.style.display = 'block';
                }
            } catch (error) {
                console.error('Error fetching recent notifications:', error);
                notificationList.innerHTML = `<div class="empty-notifications"><p class="text-red-500">Erro ao carregar notificações.</p></div>`;
            }
        }

        async function markNotificationsAsRead() {
            try {
                const response = await fetch('/notification?action=mark_all_as_read', { method: 'POST' }); // Use notification.php
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                // No need to process response, just update count locally
                notificationBadge.classList.add('hidden');
                bellIcon.classList.remove('text-orange-500');
                bellIcon.classList.add('text-gray-400');
            } catch (error) {
                console.error('Error marking notifications as read:', error);
            }
        }

        // --- Lógica para Notificações Flutuantes (Live Notifications) ---
        async function fetchLiveNotifications() {
            try {
                const response = await fetch('/notification?action=get_live_notifications'); // Use notification.php
                if (!response.ok) {
                    throw new Error('Failed to fetch live notifications');
                }
                const data = await response.json();

                if (data.live_notifications && data.live_notifications.length > 0) {
                    for (const notification of data.live_notifications) {
                        notificationQueue.push(notification); 
                        // Mark as displayed_live on the server immediately upon *receiving* it
                        // This prevents it from being fetched again in subsequent polls
                        await fetch('/notification?action=mark_as_displayed_live', { // Use notification.php
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `notification_id=${notification.id}`
                        });
                    }
                    // Once all fetched notifications are in the queue, process them
                    processNotificationQueue();
                    // Refresh main notification count after potentially 'consuming' new notifications
                    fetchNotificationsCount();
                }
            } catch (error) {
                console.error('Error fetching live notifications:', error);
            }
        }

        // Processes the notification queue
        function processNotificationQueue() {
            if (!isDisplayingNotification && notificationQueue.length > 0) {
                isDisplayingNotification = true;
                const notification = notificationQueue.shift(); // Get the next notification
                _actualDisplayLiveNotification(notification); // Call the internal displayer
            }
        }

        // Actual function to display a single live notification
        function _actualDisplayLiveNotification(notification) {
            const allowedTypes = ['Compra Aprovada', 'Pix Gerado', 'Boleto Gerado'];
            if (!allowedTypes.includes(notification.tipo)) {
                isDisplayingNotification = false; // Important: reset flag even if not displayed
                processNotificationQueue(); // Try next in queue
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
                    isDisplayingNotification = false; // Reset flag
                    processNotificationQueue(); // Try next in queue
                    return;
            }

            liveNotificationMessage.textContent = messageText;
            liveNotificationDetails.textContent = detailsText;

            liveNotificationProductImage.src = resolveLiveNotificationProductImageSrc(notification.produto_foto, liveNotificationImageFallback);

            // Play sound
            if (cashRegisterSound && audioContextResumed) { // Only play if context is resumed
                cashRegisterSound.load(); // Ensure the audio is ready to play
                cashRegisterSound.currentTime = 0; // Reset sound to start
                cashRegisterSound.volume = 1; // Ensure volume is audible for real notifications
                cashRegisterSound.play().catch(e => console.error("Error playing sound, autoplay might be blocked:", e));
            }

            liveNotificationContainer.classList.add('show');
            setTimeout(() => {
                liveNotificationContainer.classList.remove('show');
                isDisplayingNotification = false; // Reset flag
                processNotificationQueue(); // Process the next one in queue
            }, 8000); // Display for 8 seconds
        }

        notificationBell.addEventListener('click', () => {
            notificationPopup.classList.toggle('open');
            if (notificationPopup.classList.contains('open')) {
                fetchRecentNotifications();
                markNotificationsAsRead();
            }
            // Attempt to resume audio context on bell click as well
            tryResumeAudioContext();
        });

        closePopupBtn.addEventListener('click', () => {
            notificationPopup.classList.remove('open');
        });

        // Close popup when clicking outside
        document.addEventListener('click', (event) => {
            if (!notificationPopup.contains(event.target) && !notificationBell.contains(event.target) && notificationPopup.classList.contains('open')) {
                notificationPopup.classList.remove('open');
            }
        });
        
        // Initial fetch and polling for count
        fetchNotificationsCount();
        setInterval(fetchNotificationsCount, 15000); // Poll every 15 seconds

        // Polling for live notifications (more frequent)
        fetchLiveNotifications();
        setInterval(fetchLiveNotifications, 10000);
    </script>
    <script>
        // Registra o Service Worker (PWA com push)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/pwa/sw.js').then(registration => {
                    console.log('ServiceWorker registrado com sucesso: ', registration.scope);
                }, err => {
                    console.log('Falha no registro do ServiceWorker: ', err);
                });
            });
        }
    </script>
    <script src="/pwa/pwa_push_register.js"></script>
    <script>
    (function() {
        var banner = document.getElementById('pwa-push-banner');
        var text = document.getElementById('pwa-push-banner-text');
        var btn = document.getElementById('pwa-push-banner-btn');
        if (!banner || !text || !btn) return;
        function showBanner(msg, showButton) {
            text.textContent = msg;
            btn.style.display = showButton ? 'block' : 'none';
            banner.classList.remove('hidden');
        }
        function hideBanner() {
            banner.classList.add('hidden');
        }
        function updateFromState() {
            if (typeof window.PwaPush === 'undefined') return;
            if (window.PwaPush.isSubscribed) {
                showBanner('Você está inscrito para receber notificações.', false);
                setTimeout(hideBanner, 4000);
                return;
            }
            if (window.PwaPush.isDenied) {
                showBanner('Para receber notificações, ative no ícone de cadeado da barra de endereço.', false);
                return;
            }
            if (window.PwaPush.isRequestable) {
                showBanner('Deseja receber notificações de novidades e avisos?', true);
            }
        }
        window.addEventListener('pwa-push-state', function(e) {
            var d = e.detail || {};
            if (d.subscribed) { showBanner('Notificações ativadas. Você passará a receber avisos.', false); setTimeout(hideBanner, 4000); return; }
            if (d.denied) { showBanner('Para receber notificações, ative no ícone de cadeado da barra de endereço.', false); return; }
            if (d.requestable) { showBanner('Deseja receber notificações de novidades e avisos?', true); }
        });
        btn.addEventListener('click', function() {
            if (window.PwaPush && window.PwaPush.requestPermission) {
                btn.disabled = true;
                btn.textContent = 'Aguarde...';
                window.PwaPush.requestPermission().then(function(ok) {
                    btn.disabled = false;
                    btn.textContent = 'Receber notificações';
                    if (ok) { showBanner('Notificações ativadas.', false); setTimeout(hideBanner, 4000); }
                }).catch(function() { btn.disabled = false; btn.textContent = 'Receber notificações'; });
            }
        });
        setTimeout(updateFromState, 1500);
    })();
    </script>
    <script>
    (function() {
        var welcomeCard = document.getElementById('pwa-welcome-card');
        var notifyBtn = document.getElementById('pwa-welcome-notify-btn');
        var installBtn = document.getElementById('pwa-welcome-install-btn');
        var installHint = document.getElementById('pwa-install-hint');
        var closeBtn = document.getElementById('pwa-welcome-close');
        var dismissBtn = document.getElementById('pwa-welcome-dismiss');
        if (!welcomeCard) return;
        var deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            if (installBtn) installBtn.classList.remove('hidden');
        });
        function closeWelcome() {
            try { localStorage.setItem('pwa_welcome_closed', '1'); } catch (x) {}
            welcomeCard.classList.add('hidden');
        }
        function showWelcomeIfNeeded() {
            if (localStorage.getItem('pwa_welcome_closed') === '1') return;
            if (deferredPrompt || (window.PwaPush && window.PwaPush.isRequestable)) welcomeCard.classList.remove('hidden');
        }
        if (notifyBtn) notifyBtn.addEventListener('click', function() {
            if (window.PwaPush && window.PwaPush.requestPermission) {
                notifyBtn.disabled = true;
                notifyBtn.textContent = 'Aguarde...';
                window.PwaPush.requestPermission().then(function(ok) {
                    notifyBtn.disabled = false;
                    notifyBtn.textContent = 'Receber notificações';
                    if (ok) closeWelcome();
                }).catch(function() { notifyBtn.disabled = false; notifyBtn.textContent = 'Receber notificações'; });
            }
        });
        if (installBtn) installBtn.addEventListener('click', function() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choice) {
                    if (choice.outcome === 'accepted') closeWelcome();
                    deferredPrompt = null;
                });
            } else if (installHint) installHint.classList.remove('hidden');
        });
        if (closeBtn) closeBtn.addEventListener('click', closeWelcome);
        if (dismissBtn) dismissBtn.addEventListener('click', closeWelcome);
        window.addEventListener('pwa-push-state', function() {
            if (window.PwaPush && window.PwaPush.isRequestable) showWelcomeIfNeeded();
        });
        setTimeout(showWelcomeIfNeeded, 2000);
    })();
    </script>
</body>
</html>
