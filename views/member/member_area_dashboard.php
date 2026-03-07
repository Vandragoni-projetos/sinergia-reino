<?php
require_once __DIR__ . '/../../config/config.php';
if (file_exists(__DIR__ . '/../../helpers/member_protection_helper.php')) {
    require_once __DIR__ . '/../../helpers/member_protection_helper.php';
}

// ... (código de sessão PHP inalterado) ...
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /member_login");
    exit;
}
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] === 'admin') {
    header("location: /admin");
    exit;
}
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] === 'infoprodutor') {
    header("location: /");
    exit;
}
$cliente_email = strtolower($_SESSION['usuario']);
$cliente_nome = $_SESSION['nome'] ?? $cliente_email;
$cliente_id = (int)($_SESSION['id'] ?? 0);
$cliente_data_cadastro = null;
try {
    if ($cliente_id > 0) {
        $chk = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'data_cadastro'");
        if ($chk && $chk->rowCount() > 0) {
            $stmt_dc = $pdo->prepare("SELECT data_cadastro FROM usuarios WHERE id = ?");
            $stmt_dc->execute([$cliente_id]);
            $cliente_data_cadastro = $stmt_dc->fetchColumn();
        }
    }
} catch (PDOException $e) {}
$cursos_adquiridos = [];
$upload_dir = 'uploads/';

// Busca a logo do sistema
$logo_url = 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png';
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

try {
    // Não filtrar por community_id: o cliente deve ver todos os produtos a que tem acesso (alunos_acessos), inclusive Vitrine de outra comunidade.
    $stmt = $pdo->prepare("
        SELECT
            aa.produto_id,
            aa.data_concessao,
            aa.data_expiracao,
            p.nome AS produto_nome,
            p.foto AS produto_foto,
            p.is_showcase AS produto_is_showcase,
            p.product_type AS produto_type,
            p.product_tagline AS produto_tagline,
            p.usuario_id AS usuario_id,
            c.id AS curso_id,
            c.titulo AS curso_titulo,
            c.descricao AS curso_descricao,
            c.imagem_url AS curso_imagem_url,
            c.banner_url AS curso_banner_url
        FROM alunos_acessos aa
        JOIN produtos p ON aa.produto_id = p.id
        LEFT JOIN cursos c ON p.id = c.produto_id
        WHERE aa.aluno_email = ? 
        AND p.tipo_entrega = 'area_membros'
        AND (aa.data_expiracao IS NULL OR aa.data_expiracao > NOW())
        ORDER BY p.ordem ASC, aa.data_concessao DESC
    ");
    $stmt->execute([$cliente_email]);
    $cursos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_expirados = $pdo->prepare("
        SELECT
            aa.produto_id,
            aa.data_concessao,
            aa.data_expiracao,
            p.nome AS produto_nome,
            p.foto AS produto_foto,
            p.is_showcase AS produto_is_showcase,
            p.product_type AS produto_type,
            p.product_tagline AS produto_tagline,
            c.id AS curso_id,
            c.titulo AS curso_titulo,
            c.descricao AS curso_descricao,
            c.imagem_url AS curso_imagem_url,
            c.banner_url AS curso_banner_url
        FROM alunos_acessos aa
        JOIN produtos p ON aa.produto_id = p.id
        LEFT JOIN cursos c ON p.id = c.produto_id
        WHERE aa.aluno_email = ? 
        AND p.tipo_entrega = 'area_membros'
        AND aa.data_expiracao IS NOT NULL 
        AND aa.data_expiracao <= NOW()
        ORDER BY p.ordem ASC, aa.data_expiracao DESC
    ");
    $stmt_expirados->execute([$cliente_email]);
    $cursos_expirados = $stmt_expirados->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcula o progresso de cada curso considerando aulas desbloqueadas
    $cursos_adquiridos = [];
    foreach ($cursos_raw as $curso) {
        $total_aulas_desbloqueadas = 0;
        $aulas_concluidas = 0;
        
        if (!empty($curso['curso_id'])) {
            $data_concessao = new DateTime($curso['data_concessao']);
            $hoje = new DateTime();
            $dias_desde_compra = $data_concessao->diff($hoje)->days;
            
            // Busca todas as aulas do curso
            $stmt_aulas = $pdo->prepare("
                SELECT a.id, a.release_days
                FROM aulas a
                INNER JOIN modulos m ON a.modulo_id = m.id
                WHERE m.curso_id = ?
            ");
            $stmt_aulas->execute([$curso['curso_id']]);
            $aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($aulas as $aula) {
                // Verifica se a aula está desbloqueada
                if ($aula['release_days'] <= $dias_desde_compra) {
                    $total_aulas_desbloqueadas++;
                    
                    // Verifica se o aluno concluiu esta aula
                    $stmt_prog = $pdo->prepare("SELECT COUNT(*) FROM aluno_progresso WHERE aluno_email = ? AND aula_id = ?");
                    $stmt_prog->execute([$cliente_email, $aula['id']]);
                    if ($stmt_prog->fetchColumn() > 0) {
                        $aulas_concluidas++;
                    }
                }
            }
        }
        
        $curso['total_aulas'] = $total_aulas_desbloqueadas;
        $curso['aulas_concluidas'] = $aulas_concluidas;
        $cursos_adquiridos[] = $curso;
    }

    // Banners do infoprodutor para exibir no dashboard do cliente (com badge)
    $banners_dashboard = [];
    $infoprodutor_id = !empty($cursos_adquiridos) ? (int)$cursos_adquiridos[0]['usuario_id'] : null;
    try {
        $chk_banners = $pdo->query("SHOW TABLES LIKE 'banners'");
        if ($chk_banners && $chk_banners->rowCount() > 0 && $infoprodutor_id) {
            $chk_bb = $pdo->query("SHOW TABLES LIKE 'banner_badges'");
            $has_bb = $chk_bb && $chk_bb->rowCount() > 0;
            $banners_sql = $has_bb
                ? "SELECT b.*, bb.icon AS badge_icon, bb.label AS badge_label FROM banners b LEFT JOIN banner_badges bb ON bb.id = b.badge_id AND bb.is_active = 1 WHERE b.usuario_id = ? AND b.is_active = 1 AND b.show_in_member_dashboard = 1 ORDER BY b.created_at DESC"
                : "SELECT * FROM banners WHERE usuario_id = ? AND is_active = 1 AND show_in_member_dashboard = 1 ORDER BY created_at DESC";
            $stmt_b = $pdo->prepare($banners_sql);
            $stmt_b->execute([$infoprodutor_id]);
            $banners_raw = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
            // Filtrar banners vinculados a produto que o cliente já possui
            $produto_ids_cliente = array_map('intval', array_filter(array_column($cursos_adquiridos, 'produto_id')));
            $banners_dashboard = [];
            foreach ($banners_raw as $b) {
                if (!empty($b['product_id'])) {
                    $pid = (int)$b['product_id'];
                    if (in_array($pid, $produto_ids_cliente, true)) continue;
                }
                $banners_dashboard[] = $b;
            }
        }
    } catch (PDOException $e) {
        // Ignora se tabela não existir
    }

    // Lista unificada: cursos + banners na MESMA ORDEM do feed (como em "Meus Produtos")
    $feed_items_biblioteca = [];
    $produto_ids_cliente = array_column($cursos_adquiridos, 'produto_id');
    $cursos_por_produto = [];
    foreach ($cursos_adquiridos as $c) {
        $cursos_por_produto[$c['produto_id']] = $c;
    }
    $banners_por_id = [];
    foreach ($banners_dashboard as $b) {
        $banners_por_id[$b['id']] = $b;
    }

    $usuario_id_feed = $cursos_adquiridos[0]['usuario_id'] ?? null;
    $chk_feed = $pdo->query("SHOW TABLES LIKE 'products_feed_items'");
    $has_feed_table = $chk_feed && $chk_feed->rowCount() > 0;
    // Ordem igual à de "Meus Produtos": sort_order define a posição; dedupe por (item_type, item_id).
    if ($has_feed_table && $usuario_id_feed) {
        $stmt_feed = $pdo->prepare("SELECT item_type, item_id, sort_order FROM products_feed_items WHERE usuario_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt_feed->execute([$usuario_id_feed]);
        $feed_raw = $stmt_feed->fetchAll(PDO::FETCH_ASSOC);
        $vistos = [];
        foreach ($feed_raw as $item) {
            $key = $item['item_type'] . '-' . $item['item_id'];
            if (isset($vistos[$key])) continue;
            $vistos[$key] = true;
            if ($item['item_type'] === 'product' && isset($cursos_por_produto[$item['item_id']])) {
                $feed_items_biblioteca[] = ['type' => 'course', 'data' => $cursos_por_produto[$item['item_id']]];
            } elseif ($item['item_type'] === 'banner' && isset($banners_por_id[$item['item_id']])) {
                $feed_items_biblioteca[] = ['type' => 'banner', 'data' => $banners_por_id[$item['item_id']]];
            }
        }
        // Garantir que todos os cursos do cliente apareçam (evita sumir quando há banner no feed)
        $produto_ids_ja_na_biblioteca = array_unique(array_map(function($it) {
            return $it['type'] === 'course' ? (int)$it['data']['produto_id'] : null;
        }, $feed_items_biblioteca));
        $produto_ids_ja_na_biblioteca = array_filter($produto_ids_ja_na_biblioteca);
        foreach ($cursos_adquiridos as $c) {
            if (!in_array((int)$c['produto_id'], $produto_ids_ja_na_biblioteca, true)) {
                $feed_items_biblioteca[] = ['type' => 'course', 'data' => $c];
            }
        }
    }

    if (empty($feed_items_biblioteca)) {
        foreach ($cursos_adquiridos as $c) {
            $feed_items_biblioteca[] = ['type' => 'course', 'data' => $c];
        }
        foreach ($banners_dashboard as $b) {
            $feed_items_biblioteca[] = ['type' => 'banner', 'data' => $b];
        }
    }

} catch (PDOException $e) {
    $mensagem_erro = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Erro ao buscar seus cursos: " . htmlspecialchars($e->getMessage()) . "</div>";
    $cursos_adquiridos = [];
}
if (!isset($banners_dashboard)) {
    $banners_dashboard = [];
}
if (!isset($feed_items_biblioteca)) {
    $feed_items_biblioteca = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>Meus Cursos - Área de Membros GatewayPro</title>
    <?php
    // PWA: manifest e meta para instalação como app
    $pwa_activated = false;
    if (file_exists(__DIR__ . '/../../config/config.php')) {
        require_once __DIR__ . '/../../config/config.php';
        try {
            if (isset($pdo)) {
                $st = $pdo->query("SELECT valor FROM configuracoes_sistema WHERE chave = 'pwa_activated' LIMIT 1");
                if ($st) { $r = $st->fetch(PDO::FETCH_ASSOC); $pwa_activated = ($r && ($r['valor'] ?? '') === '1'); }
            }
        } catch (Exception $e) {}
    }
    if ($pwa_activated):
        $theme_color = '#1e293b';
        try {
            if (isset($pdo)) {
                $st = $pdo->query("SELECT theme_color FROM pwa_config ORDER BY id DESC LIMIT 1");
                if ($st) { $r = $st->fetch(PDO::FETCH_ASSOC); if (!empty($r['theme_color'])) $theme_color = $r['theme_color']; }
            }
        } catch (Exception $e) {}
    ?>
    <link rel="manifest" href="/pwa/manifest.php">
    <meta name="theme-color" content="<?php echo htmlspecialchars($theme_color); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <?php endif; ?>
    <?php
    // Adiciona favicon se configurado
    if (!isset($pdo) && file_exists(__DIR__ . '/../../config/config.php')) {
        require_once __DIR__ . '/../../config/config.php';
    }
    $favicon_url_raw = getSystemSetting('favicon_url', '');
    $blocked_offers_grayscale = (getSystemSetting('blocked_offers_grayscale', '0') === '1');
    if (!empty($favicon_url_raw)) {
        $favicon_url = ltrim($favicon_url_raw, '/');
        if (strpos($favicon_url, 'http') !== 0) {
            if (strpos($favicon_url, 'uploads/') === 0) {
                $favicon_url = '/' . $favicon_url;
            } else {
                $favicon_url = '/' . $favicon_url;
            }
        }
        $favicon_ext = strtolower(pathinfo($favicon_url, PATHINFO_EXTENSION));
        $favicon_type = 'image/x-icon';
        if ($favicon_ext === 'png') {
            $favicon_type = 'image/png';
        } elseif ($favicon_ext === 'svg') {
            $favicon_type = 'image/svg+xml';
        }
        echo '<link rel="icon" type="' . htmlspecialchars($favicon_type) . '" href="' . htmlspecialchars($favicon_url) . '">' . "\n";
    }
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-gray-200 antialiased">
    <?php include __DIR__ . '/../includes/session_heartbeat.php'; ?>
    <!-- Cabeçalho Premium Fixo (voltando para paleta 'gray') -->
    <header class="sticky top-0 z-50 w-full border-b border-gray-700/50 bg-gray-900/70 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <a href="/member_area_dashboard">
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="GatewayPro Logo" class="h-10">
                    </a>
                    <div class="relative flex items-center gap-2">
                        <span class="text-sm text-gray-400 hidden sm:inline">Categoria</span>
                        <select id="category-filter" class="bg-gray-800 border border-gray-600 text-gray-200 text-sm rounded-lg pl-3 pr-8 py-2 focus:ring-2 focus:ring-green-500/50 focus:border-green-500 cursor-pointer appearance-none" title="Filtrar por categoria">
                            <option value="">Todas</option>
                            <?php foreach (getProductTypeOptions() as $group => $items): ?>
                            <optgroup label="— <?php echo htmlspecialchars($group); ?> —">
                                <?php foreach ($items as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <i data-lucide="chevron-down" class="w-4 h-4 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400"></i>
                    </div>
                </div>
                <div class="flex items-center space-x-5">
                    <!-- Dropdown do Perfil -->
                    <div class="relative" id="profile-dropdown-container">
                        <button onclick="toggleProfileDropdown()" class="flex items-center space-x-2 font-medium text-gray-300 hover:text-white transition-colors cursor-pointer">
                            <span class="hidden md:block">Olá, <?php echo htmlspecialchars($cliente_nome); ?>!</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" id="dropdown-arrow"></i>
                        </button>
                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-gray-800 border border-gray-700 rounded-lg shadow-xl py-2 z-50">
                            <button onclick="openProfileModal()" class="w-full flex items-center space-x-3 px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors text-left">
                                <i data-lucide="user-cog" class="w-4 h-4"></i>
                                <span>Editar Perfil</span>
                            </button>
                            <?php 
                            // Inclui helper do master e verifica se aluno tem acesso a produto que gera licença
                            require_once __DIR__ . '/../../helpers/master_helper.php';
                            $showLicenseLink = false;
                            if (isMasterPanel()) {
                                $stmtLic = $pdo->prepare("
                                    SELECT COUNT(*) FROM alunos_acessos aa
                                    JOIN produtos p ON aa.produto_id = p.id
                                    WHERE aa.aluno_email = ? AND p.gera_licenca = 1
                                    AND (aa.data_expiracao IS NULL OR aa.data_expiracao > NOW())
                                ");
                                $stmtLic->execute([$cliente_email]);
                                $showLicenseLink = $stmtLic->fetchColumn() > 0;
                            }
                            if ($showLicenseLink): 
                            ?>
                            <a href="/member_licenses" class="flex items-center space-x-3 px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-green-400 transition-colors">
                                <i data-lucide="key" class="w-4 h-4"></i>
                                <span>Minhas Licenças</span>
                            </a>
                            <?php endif; ?>
                            <hr class="border-gray-700 my-1">
                            <a href="/member_logout" class="flex items-center space-x-3 px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-red-400 transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                <span>Sair</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Banner: ativar notificações push (novo usuário deve clicar para inscrever) -->
    <div id="pwa-push-banner" class="hidden max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2" role="region" aria-label="Notificações">
        <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-600 bg-gray-800/90 px-4 py-3 text-sm">
            <span id="pwa-push-banner-text" class="text-gray-300"></span>
            <button type="button" id="pwa-push-banner-btn" class="hidden shrink-0 rounded-lg bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-500 transition">Receber notificações</button>
        </div>
    </div>

    <!-- Card pós-login: notificações + instalar como app (mostra uma vez) -->
    <div id="pwa-welcome-card" class="hidden max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="rounded-xl border border-gray-600 bg-gray-800/95 p-6 shadow-lg">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-white mb-1">Instale o app e não perca o acesso</h3>
                    <p class="text-gray-400 text-sm mb-4">Adicione à tela inicial do celular ou instale no computador para acessar seus cursos com um toque e não esquecer da plataforma. Você também pode ativar as notificações para receber avisos.</p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" id="pwa-welcome-install-btn" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-500 transition">
                            Instalar como app
                        </button>
                        <button type="button" id="pwa-welcome-notify-btn" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-500 transition">
                            Receber notificações
                        </button>
                        <button type="button" id="pwa-welcome-close" class="inline-flex items-center gap-2 rounded-lg border border-gray-600 px-4 py-2.5 text-sm font-medium text-gray-400 hover:text-white hover:border-gray-500 transition">
                            Agora não
                        </button>
                    </div>
                </div>
                <button type="button" id="pwa-welcome-dismiss" class="text-gray-500 hover:text-white p-1 rounded" aria-label="Fechar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p id="pwa-install-hint" class="hidden mt-3 text-xs text-gray-500">No Chrome: menu (⋮) → Instalar aplicativo. No celular: Adicionar à tela inicial.</p>
        </div>
    </div>

    <!-- Modal Editar Perfil -->
    <div id="profile-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-xl border border-gray-700 w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between p-6 border-b border-gray-700">
                <h3 class="text-xl font-bold text-white">Editar Perfil</h3>
                <button onclick="closeProfileModal()" class="text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <form id="profile-form" class="p-6 space-y-4">
                <?php if ($cliente_data_cadastro): ?>
                <div class="pb-2 border-b border-gray-700">
                    <p class="text-sm text-gray-400">Data de cadastro</p>
                    <p class="text-white font-medium"><?php echo date('d/m/Y', strtotime($cliente_data_cadastro)); ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <label for="profile-nome" class="block text-sm font-medium text-gray-300 mb-2">Nome</label>
                    <input type="text" id="profile-nome" name="nome" value="<?php echo htmlspecialchars($cliente_nome); ?>" required class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                    <label for="profile-email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($cliente_email); ?>" required class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                    <label for="profile-senha-atual" class="block text-sm font-medium text-gray-300 mb-2">Senha Atual</label>
                    <input type="password" id="profile-senha-atual" name="senha_atual" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Digite para confirmar alterações">
                </div>
                <div>
                    <label for="profile-nova-senha" class="block text-sm font-medium text-gray-300 mb-2">Nova Senha <span class="text-gray-500">(opcional)</span></label>
                    <input type="password" id="profile-nova-senha" name="nova_senha" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Deixe vazio para manter a atual">
                </div>
                <div>
                    <label for="profile-confirmar-senha" class="block text-sm font-medium text-gray-300 mb-2">Confirmar Nova Senha</label>
                    <input type="password" id="profile-confirmar-senha" name="confirmar_senha" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Repita a nova senha">
                </div>
                <div id="profile-error" class="hidden bg-red-900/30 border border-red-500 text-red-300 px-4 py-3 rounded-lg text-sm"></div>
                <div id="profile-success" class="hidden bg-green-900/30 border border-green-500 text-green-300 px-4 py-3 rounded-lg text-sm"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeProfileModal()" class="flex-1 px-4 py-3 bg-gray-700 text-gray-300 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" id="btn-save-profile" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 member-protected-content">
        <?php if (isset($mensagem_erro)) echo $mensagem_erro; ?>

        <!-- Novo Título Premium -->
        <div class_id="intro-header" class="mb-10">
            <h1 class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-green-400 to-green-500 mb-2">
                Sua Biblioteca de Cursos
            </h1>
            <p class="text-xl text-gray-400">
                Todo seu conhecimento adquirido em um só lugar. Pronto para começar?
            </p>
        </div>


        <?php
        // Segmentos: cursos em blocos e banners em linha inteira (como na imagem 1).
        // Banner = linha full-width ENTRE as linhas de cards, não um card no grid.
        $segments_biblioteca = [];
        $current_courses = [];
        foreach ($feed_items_biblioteca as $item) {
            if ($item['type'] === 'course') {
                $current_courses[] = $item['data'];
            } else {
                if (!empty($current_courses)) {
                    $segments_biblioteca[] = ['type' => 'courses', 'items' => $current_courses];
                    $current_courses = [];
                }
                $segments_biblioteca[] = ['type' => 'banner', 'data' => $item['data']];
            }
        }
        if (!empty($current_courses)) {
            $segments_biblioteca[] = ['type' => 'courses', 'items' => $current_courses];
        }
        ?>
        <?php if (empty($feed_items_biblioteca)): ?>
            <!-- Tela de Boas-Vindas / Vazio -->
            <div class="bg-gray-800 p-8 rounded-lg shadow-md text-center text-gray-400 border border-gray-700">
                <i data-lucide="inbox" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
                <p class="text-lg font-semibold text-white">Você ainda não possui cursos</p>
                <p class="mt-2 text-sm">Parece que você ainda não adquiriu nenhum produto. Explore nossa loja ou, se você acredita que isso é um erro, por favor, entre em contato com o suporte.</p>
            </div>
        <?php else: ?>
            <!-- Empty state quando filtro não encontra cursos -->
            <div id="biblioteca-filter-empty" class="hidden bg-gray-800 p-8 rounded-lg shadow-md text-center text-gray-400 border border-gray-700 mb-8">
                <i data-lucide="folder-x" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
                <p class="text-lg font-semibold text-white">Nenhum curso encontrado nesta categoria</p>
                <button type="button" id="biblioteca-reset-filter" class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-500 text-white font-medium rounded-lg transition-colors">
                    Ver todos os cursos
                </button>
            </div>
            <div id="biblioteca-content">
            <?php foreach ($segments_biblioteca as $seg): ?>
                <?php if ($seg['type'] === 'courses'): ?>
                    <!-- Grid de cards de cursos (2 ou 3 por linha) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8 biblioteca-course-grid">
                        <?php foreach ($seg['items'] as $curso): ?>
                            <a href="/member_course_view?produto_id=<?php echo $curso['produto_id']; ?>"
                               class="group bg-gray-800 rounded-2xl shadow-lg overflow-hidden transition-all duration-300 hover:shadow-2xl hover:scale-[1.02] border border-gray-700/50 flex flex-col biblioteca-course-card"
                               data-product-type="<?php echo htmlspecialchars($curso['produto_type'] ?? ''); ?>">
                                <div class="relative aspect-video overflow-hidden">
                                    <?php
                                    $image_path = null;
                                    $placeholder_url = 'https://placehold.co/600x400/1f2937/9ca3af?text=Curso+Sem+Imagem';
                                    if (!empty($curso['produto_foto'])) {
                                        $image_path = resolve_product_image_url_protected($curso['produto_foto'], $upload_dir, $curso['produto_id'] ?? 0);
                                    } elseif (!empty($curso['curso_imagem_url'])) {
                                        $image_path = resolve_product_image_url_protected($curso['curso_imagem_url'], $upload_dir, $curso['produto_id'] ?? 0);
                                    }
                                    if (empty($image_path)) {
                                        $image_path = $placeholder_url;
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($image_path); ?>"
                                         alt="<?php echo htmlspecialchars($curso['curso_titulo'] ?? $curso['produto_nome']); ?>"
                                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                         onerror="this.onerror=null; this.src='<?php echo $placeholder_url; ?>';">
                                    <?php if (!empty($curso['produto_is_showcase']) && $curso['produto_is_showcase'] == 1): ?>
                                    <span class="absolute top-2 left-2 z-10 bg-purple-500 text-white text-xs font-bold px-2 py-1 rounded-full shadow-lg flex items-center gap-1">
                                        <i data-lucide="star" class="w-3 h-3"></i> VITRINE
                                    </span>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                        <i data-lucide="play-circle" class="w-16 h-16 text-white/80"></i>
                                    </div>
                                </div>
                                <div class="p-6 flex flex-col flex-grow">
                                    <h3 class="text-2xl font-bold text-white mb-3 line-clamp-2">
                                        <?php echo htmlspecialchars($curso['curso_titulo'] ?? $curso['produto_nome']); ?>
                                    </h3>
                                    <?php
                                    $tag_icons = getProductTypeIcons();
                                    $ptype = $curso['produto_type'] ?? '';
                                    $ptag = $curso['produto_tagline'] ?? '';
                                    $tag_line = '';
                                    if ($ptype && isset($tag_icons[$ptype])) {
                                        $tag_line = $tag_icons[$ptype] . ' ' . $ptype . ($ptag ? ' • ' . mb_substr($ptag, 0, 40) : '');
                                    } elseif ($ptag) {
                                        $tag_line = mb_substr($ptag, 0, 40);
                                    }
                                    ?>
                                    <?php if ($tag_line): ?>
                                    <p class="text-xs text-gray-400 mb-2 truncate" title="<?php echo htmlspecialchars($tag_line); ?>"><?php echo htmlspecialchars($tag_line); ?></p>
                                    <?php endif; ?>
                                    <p class="text-gray-400 text-sm mb-4 line-clamp-3 flex-grow">
                                        <?php echo htmlspecialchars($curso['curso_descricao'] ?? 'Acesse para ver mais detalhes.'); ?>
                                    </p>
                                    <div class="mt-4">
                                        <?php
                                        $total_aulas = (int)($curso['total_aulas'] ?? 0);
                                        $aulas_concluidas = (int)($curso['aulas_concluidas'] ?? 0);
                                        $progresso_percentual = $total_aulas > 0 ? round(($aulas_concluidas / $total_aulas) * 100) : 0;
                                        ?>
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-semibold text-green-400">SEU PROGRESSO</span>
                                            <span class="text-sm font-bold text-white"><?php echo $progresso_percentual; ?>% Completo</span>
                                        </div>
                                        <div class="w-full bg-gray-700 rounded-full h-2.5">
                                            <div class="bg-green-500 h-2.5 rounded-full transition-all duration-300" style="width: <?php echo $progresso_percentual; ?>%"></div>
                                        </div>
                                        <?php if (!empty($curso['data_expiracao'])):
                                            $data_exp = new DateTime($curso['data_expiracao']);
                                            $hoje = new DateTime();
                                            $dias_restantes = $hoje->diff($data_exp)->days;
                                            $is_expiring_soon = $dias_restantes <= 7;
                                            ?>
                                            <div class="mt-3 flex items-center gap-2 <?php echo $is_expiring_soon ? 'text-yellow-400' : 'text-gray-400'; ?>">
                                                <i data-lucide="clock" class="w-4 h-4"></i>
                                                <span class="text-xs">
                                                    <?php if ($is_expiring_soon): ?>
                                                        Expira em <?php echo $dias_restantes; ?> dia<?php echo $dias_restantes != 1 ? 's' : ''; ?>!
                                                    <?php else: ?>
                                                        Acesso até <?php echo $data_exp->format('d/m/Y'); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-3 flex items-center gap-2 text-green-400">
                                                <i data-lucide="infinity" class="w-4 h-4"></i>
                                                <span class="text-xs">Acesso vitalício</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($seg['type'] === 'banner'): ?>
                    <?php
                    $banner = $seg['data'];
                    $banner_img = !empty($banner['image_url']) ? $banner['image_url'] : (function_exists('getProtectedMediaUrl') ? getProtectedMediaUrl($banner['image_path'] ?? '', 0) : '/' . ltrim($banner['image_path'] ?? '', '/'));
                    if (empty($banner_img)) $banner_img = 'https://placehold.co/1200x400/4c1d95/9ca3af?text=Banner';
                    $is_clickable = !empty($banner['click_url']);
                    $link_target = !empty($banner['open_new_tab']) ? '_blank' : '_self';
                    $banner_titulo = !empty($banner['titulo']) ? $banner['titulo'] : 'Promoção';
                    ?>
                    <!-- Banner em linha inteira (full-width) entre as linhas de cards - layout: badge acima, imagem, CTA abaixo -->
                    <div class="w-full mb-12 biblioteca-banner" data-item-type="banner">
                        <?php if ($is_clickable): ?>
                        <a href="<?php echo htmlspecialchars($banner['click_url']); ?>" target="<?php echo $link_target; ?>" rel="noopener noreferrer"
                           class="banner-card block group rounded-2xl overflow-hidden border border-purple-500/50 shadow-lg transition-all duration-300 hover:shadow-2xl hover:opacity-95">
                        <?php else: ?>
                        <div class="banner-card block rounded-2xl overflow-hidden border border-purple-500/50 shadow-lg">
                        <?php endif; ?>
                            <?php $bi = !empty($banner['badge_icon']) ? $banner['badge_icon'] : '🔔'; $bl = !empty($banner['badge_label']) ? $banner['badge_label'] : 'Aviso'; ?>
                            <div class="banner-badge bg-purple-600 text-white text-xs font-bold px-3 py-2.5 rounded-t-2xl text-center truncate max-w-full" title="<?php echo htmlspecialchars($bi . ' ' . $bl); ?>"><?php echo htmlspecialchars($bi . ' ' . $bl); ?></div>
                            <div class="banner-image w-full overflow-hidden bg-gray-900 flex items-center justify-center min-h-[200px] p-6 md:p-10" style="aspect-ratio: 3/2;">
                                <img src="<?php echo htmlspecialchars($banner_img); ?>" alt="<?php echo htmlspecialchars($banner_titulo); ?>"
                                     class="max-w-full max-h-full w-auto h-auto object-contain transition-transform duration-300 group-hover:scale-105"
                                     onerror="this.onerror=null; this.src='https://placehold.co/1200x400/4c1d95/9ca3af?text=Banner';">
                            </div>
                            <?php if ($is_clickable): ?>
                            <div class="banner-cta flex items-center justify-center gap-2 bg-gray-900/90 text-green-400 text-sm font-semibold px-4 py-6">
                                <i data-lucide="external-link" class="w-4 h-4"></i>
                                <span>Ver oferta</span>
                            </div>
                            <?php endif; ?>
                            <div class="bg-gray-800/80 px-6 py-5">
                                <h3 class="banner-title-effect text-lg font-bold text-white"><?php echo htmlspecialchars($banner_titulo); ?></h3>
                            </div>
                        <?php if ($is_clickable): ?>
                        </a>
                        <?php else: ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            </div><!-- #biblioteca-content -->
        <?php endif; ?>

        <!-- 
            SEÇÃO DE OFERTAS EXCLUSIVAS (GRID 4 COLUNAS)
        -->
        <h2 class="text-3xl font-extrabold text-gray-100 mb-8 mt-12">Ofertas Exclusivas para Você</h2>
        
        <div id="exclusive-offers-loading" class="bg-gray-800 p-8 rounded-lg shadow-md text-center text-gray-400 border border-gray-700" style="display: block;">
            <svg class="animate-spin h-8 w-8 text-green-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
            </svg>
            <p class="text-lg font-semibold">Carregando ofertas...</p>
        </div>

        <div id="exclusive-offers-empty" class="bg-gray-800 p-8 rounded-lg shadow-md text-center text-gray-400 border border-gray-700" style="display: none;">
            <i data-lucide="tag-off" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
            <p class="text-lg font-semibold text-white">Nenhuma oferta exclusiva disponível.</p>
            <p class="mt-2 text-sm">Fique atento para futuras oportunidades!</p>
        </div>

        <!-- Grid de Ofertas Exclusivas (4 por linha) -->
        <div id="exclusive-offers-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" style="display: none;">
            <!-- Offers will be loaded here by JavaScript -->
        </div>

    </main>

    <script>
        lucide.createIcons();
        
        // Filtro de categorias (localStorage + aplicação)
        const CATEGORY_FILTER_KEY = 'member_dashboard_category_filter';
        function getCategoryFilter() {
            try {
                return localStorage.getItem(CATEGORY_FILTER_KEY) || '';
            } catch (_) { return ''; }
        }
        function setCategoryFilter(value) {
            try {
                localStorage.setItem(CATEGORY_FILTER_KEY, value);
            } catch (_) {}
        }
        function applyCategoryFilter() {
            const filterVal = document.getElementById('category-filter')?.value || '';
            const filterSelect = document.getElementById('category-filter');
            if (filterSelect) filterSelect.value = filterVal;
            const showAll = !filterVal;
            const courseCards = document.querySelectorAll('.biblioteca-course-card');
            const banners = document.querySelectorAll('.biblioteca-banner');
            const offerCards = document.querySelectorAll('#exclusive-offers-grid [data-product-type]');
            const exclusiveBanners = document.querySelectorAll('.exclusive-offer-banner');
            const emptyState = document.getElementById('biblioteca-filter-empty');
            const bibliotecaContent = document.getElementById('biblioteca-content');
            let visibleCount = 0;
            courseCards.forEach(function(card) {
                const pt = (card.getAttribute('data-product-type') || '').trim();
                const match = showAll || pt === filterVal;
                card.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });
            banners.forEach(function(b) {
                b.classList.toggle('hidden', !showAll);
            });
            exclusiveBanners.forEach(function(b) {
                b.style.display = showAll ? '' : 'none';
            });
            offerCards.forEach(function(card) {
                const pt = (card.getAttribute('data-product-type') || '').trim();
                const match = showAll || pt === filterVal;
                const wrapper = card.closest('#exclusive-offers-grid > div') || card.parentElement;
                if (wrapper) wrapper.style.display = match ? '' : 'none';
            });
            if (emptyState && bibliotecaContent) {
                const hasCourses = courseCards.length > 0;
                if (hasCourses && !showAll && visibleCount === 0) {
                    emptyState.classList.remove('hidden');
                    bibliotecaContent.classList.add('hidden');
                } else {
                    emptyState.classList.add('hidden');
                    bibliotecaContent.classList.remove('hidden');
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const catFilter = document.getElementById('category-filter');
            const resetBtn = document.getElementById('biblioteca-reset-filter');
            if (catFilter) {
                catFilter.value = getCategoryFilter();
                catFilter.addEventListener('change', function() {
                    setCategoryFilter(this.value);
                    applyCategoryFilter();
                });
                applyCategoryFilter();
            }
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (catFilter) {
                        catFilter.value = '';
                        setCategoryFilter('');
                        applyCategoryFilter();
                    }
                });
            }
        });
        
        // Dropdown do Perfil
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profile-dropdown');
            const arrow = document.getElementById('dropdown-arrow');
            dropdown.classList.toggle('hidden');
            arrow.style.transform = dropdown.classList.contains('hidden') ? '' : 'rotate(180deg)';
        }
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            const container = document.getElementById('profile-dropdown-container');
            if (container && !container.contains(e.target)) {
                document.getElementById('profile-dropdown').classList.add('hidden');
                document.getElementById('dropdown-arrow').style.transform = '';
            }
        });
        
        // Modal de Perfil
        function openProfileModal() {
            document.getElementById('profile-modal').classList.remove('hidden');
            document.getElementById('profile-dropdown').classList.add('hidden');
            document.getElementById('dropdown-arrow').style.transform = '';
            document.getElementById('profile-error').classList.add('hidden');
            document.getElementById('profile-success').classList.add('hidden');
            document.getElementById('profile-senha-atual').value = '';
            document.getElementById('profile-nova-senha').value = '';
            document.getElementById('profile-confirmar-senha').value = '';
            lucide.createIcons();
        }
        
        function closeProfileModal() {
            document.getElementById('profile-modal').classList.add('hidden');
        }
        
        // Submit do formulário de perfil
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btn-save-profile');
            const errorDiv = document.getElementById('profile-error');
            const successDiv = document.getElementById('profile-success');
            
            const novaSenha = document.getElementById('profile-nova-senha').value;
            const confirmarSenha = document.getElementById('profile-confirmar-senha').value;
            
            // Validação de senha
            if (novaSenha && novaSenha !== confirmarSenha) {
                errorDiv.textContent = 'As senhas não coincidem.';
                errorDiv.classList.remove('hidden');
                successDiv.classList.add('hidden');
                return;
            }
            
            btn.disabled = true;
            btn.textContent = 'Salvando...';
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            
            const formData = {
                nome: document.getElementById('profile-nome').value,
                email: document.getElementById('profile-email').value,
                senha_atual: document.getElementById('profile-senha-atual').value,
                nova_senha: novaSenha
            };
            
            fetch('/api/member_api.php?action=update_member_profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message || 'Perfil atualizado com sucesso!';
                    successDiv.classList.remove('hidden');
                    
                    // Atualiza o nome no header se mudou
                    if (data.nome) {
                        const headerName = document.querySelector('#profile-dropdown-container button span');
                        if (headerName) {
                            headerName.textContent = 'Olá, ' + data.nome + '!';
                        }
                    }
                    
                    // Se o email mudou, redireciona para login
                    if (data.email_changed) {
                        setTimeout(() => {
                            alert('Email alterado. Por favor, faça login novamente.');
                            window.location.href = '/member_login';
                        }, 1500);
                    } else {
                        setTimeout(() => closeProfileModal(), 2000);
                    }
                } else {
                    errorDiv.textContent = data.error || 'Erro ao atualizar perfil.';
                    errorDiv.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                errorDiv.textContent = 'Erro ao atualizar perfil.';
                errorDiv.classList.remove('hidden');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Salvar';
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadDir = '<?php echo $upload_dir; ?>';
            const blockedOffersGrayscale = <?php echo $blocked_offers_grayscale ? 'true' : 'false'; ?>;
            const tagIcons = <?php echo json_encode(getProductTypeIcons()); ?>;

            const exclusiveOffersLoading = document.getElementById('exclusive-offers-loading');
            const exclusiveOffersEmpty = document.getElementById('exclusive-offers-empty');
            const exclusiveOffersGrid = document.getElementById('exclusive-offers-grid');

            function formatCurrency(value) {
                return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }

            async function fetchExclusiveOffers() {
                exclusiveOffersLoading.style.display = 'block';
                exclusiveOffersEmpty.style.display = 'none';
                exclusiveOffersGrid.style.display = 'none';
                exclusiveOffersGrid.innerHTML = '';
                // Remove container de banners anterior (se existir)
                const oldBannersContainer = document.getElementById('exclusive-banners-container');
                if (oldBannersContainer) oldBannersContainer.remove();

                try {
                    const response = await fetch('/api/api?action=get_member_exclusive_offers');
                    let data;
                    try {
                        data = await response.json();
                    } catch (_) {
                        data = { error: 'Resposta inválida do servidor.' };
                    }
                    if (!response.ok) {
                        exclusiveOffersLoading.style.display = 'none';
                        exclusiveOffersEmpty.style.display = 'block';
                        exclusiveOffersEmpty.innerHTML = '<i data-lucide="cloud-off" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i><p class="text-lg font-semibold text-red-500">Erro ao carregar ofertas</p><p class="mt-2 text-sm text-gray-400">' + (data.error || 'Status ' + response.status) + '</p>';
                        lucide.createIcons();
                        return;
                    }
                    if (data.error) {
                        exclusiveOffersLoading.style.display = 'none';
                        exclusiveOffersEmpty.style.display = 'block';
                        exclusiveOffersEmpty.innerHTML = '<i data-lucide="cloud-off" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i><p class="text-lg font-semibold text-red-500">Erro ao carregar ofertas</p><p class="mt-2 text-sm text-gray-400">' + (data.error || '') + '</p>';
                        lucide.createIcons();
                        return;
                    }
                    const items = data.items || [];
                    const offers = data.offers || [];
                    const banners = data.banners || [];
                    const hasContent = items.length > 0 || (offers.length > 0 || banners.length > 0);

                    if (hasContent) {
                        const escapeHtml = (str) => {
                            if (!str) return '';
                            const div = document.createElement('div');
                            div.textContent = str;
                            return div.innerHTML;
                        };

                        function renderBannerHtml(banner) {
                            const bannerImg = banner.image_url || (banner.image_path ? ('/' + (banner.image_path || '').replace(/^\/+/, '')) : 'https://placehold.co/1200x400/4c1d95/9ca3af?text=Banner');
                            const isClickable = banner.click_url && banner.click_url.trim() !== '';
                            const linkTarget = banner.open_new_tab == 1 ? 'target="_blank" rel="noopener noreferrer"' : '';
                            const titulo = escapeHtml(banner.titulo || 'Promoção');
                            const link = (banner.click_url || '').trim();
                            const badgeIcon = (banner.badge_icon && String(banner.badge_icon).trim()) ? banner.badge_icon : '🔔';
                            const badgeLabel = (banner.badge_label && String(banner.badge_label).trim()) ? banner.badge_label : 'Aviso';
                            const badgeText = escapeHtml(badgeIcon + ' ' + badgeLabel);
                            const ctaHtml = isClickable ? `<div class="banner-cta flex items-center justify-center gap-2 bg-gray-900/90 text-green-400 text-sm font-semibold px-4 py-6 rounded-b-2xl"><i data-lucide="external-link" class="w-4 h-4"></i><span>Ver oferta</span></div>` : '';
                            if (isClickable) {
                                return `<a href="${link}" ${linkTarget} class="banner-card block group rounded-2xl overflow-hidden border border-purple-500/50 shadow-lg transition-all duration-300 hover:shadow-2xl hover:border-purple-500">
                                    <div class="banner-badge bg-purple-600 text-white text-xs font-bold px-3 py-2.5 rounded-t-2xl text-center truncate max-w-full" title="${badgeText}">${badgeText}</div>
                                    <div class="banner-image w-full overflow-hidden bg-gray-900 flex items-center justify-center min-h-[200px] p-6 md:p-10" style="aspect-ratio: 3/2;">
                                        <img src="${bannerImg}" alt="${titulo}" class="max-w-full max-h-full w-auto h-auto object-contain transition-transform duration-300 group-hover:scale-105" onerror="this.onerror=null;this.src='https://placehold.co/1200x400/4c1d95/9ca3af?text=Banner';">
                                    </div>
                                    ${ctaHtml}
                                    <div class="bg-gray-800/80 px-6 py-5">
                                        <h3 class="banner-title-effect text-lg font-bold text-white">${titulo}</h3>
                                    </div>
                                </a>`;
                            }
                            return `<div class="banner-card block rounded-2xl overflow-hidden border border-purple-500/50 shadow-lg">
                                <div class="banner-badge bg-purple-600 text-white text-xs font-bold px-3 py-2.5 rounded-t-2xl text-center truncate max-w-full" title="${badgeText}">${badgeText}</div>
                                <div class="banner-image w-full overflow-hidden bg-gray-900 flex items-center justify-center min-h-[200px] p-6 md:p-10" style="aspect-ratio: 3/2;">
                                    <img src="${bannerImg}" alt="${titulo}" class="max-w-full max-h-full w-auto h-auto object-contain" onerror="this.onerror=null;this.src='https://placehold.co/1200x400/4c1d95/9ca3af?text=Banner';">
                                </div>
                                <div class="bg-gray-800/80 px-6 py-5">
                                    <h3 class="banner-title-effect text-lg font-bold text-white">${titulo}</h3>
                                </div>
                            </div>`;
                        }

                        function renderProductCard(offer) {
                            const isLocked = offer.has_access !== true;
                            const useGrayscale = isLocked && blockedOffersGrayscale;
                            const lockedImgCls = useGrayscale ? ' grayscale brightness-75 contrast-125' : '';
                            const overlayHtml = useGrayscale ? '<div class="absolute inset-0 bg-black/35 pointer-events-none" aria-hidden="true"></div>' : '';
                            const lockedBadgeHtml = isLocked ? '<span class="absolute top-2 right-2 bg-gray-900/90 text-gray-300 text-xs font-semibold px-2 py-1 rounded flex items-center gap-1.5 pointer-events-none"><i data-lucide="lock" class="w-3.5 h-3.5 flex-shrink-0"></i> Bloqueado</span>' : '';
                            const productPhoto = (offer.product_photo && (offer.product_photo.startsWith('http://') || offer.product_photo.startsWith('https://'))) ? offer.product_photo : (offer.product_photo ? uploadDir + offer.product_photo : 'https://placehold.co/280x160/1f2937/d1d5db?text=Produto');
                            const productPrice = formatCurrency(offer.product_price);
                            const hasSalesPageUrl = offer.sales_page_url && String(offer.sales_page_url).trim().length > 0;
                            const checkoutLink = hasSalesPageUrl ? String(offer.sales_page_url).trim() : (offer.custom_link ? offer.custom_link : `/checkout?p=${offer.checkout_hash}`);
                            const linkTarget = (hasSalesPageUrl || offer.custom_link) ? 'target="_blank" rel="noopener noreferrer"' : '';
                            const buttonText = hasSalesPageUrl ? 'Ver detalhes' : (offer.custom_button_text ? offer.custom_button_text : `Comprar por ${productPrice}`);
                            const productDescription = offer.product_description || 'Oferta exclusiva para você.';
                            let tagLine = '';
                            if (offer.product_type && tagIcons[offer.product_type]) {
                                tagLine = tagIcons[offer.product_type] + ' ' + offer.product_type + (offer.product_tagline ? ' • ' + String(offer.product_tagline).substring(0, 40) : '');
                            } else if (offer.product_tagline) {
                                tagLine = String(offer.product_tagline).substring(0, 40);
                            }
                            const tagHtml = tagLine ? `<p class="text-xs text-gray-400 mb-2 truncate" title="${escapeHtml(tagLine)}">${escapeHtml(tagLine)}</p>` : '';
                            const productType = (offer.product_type || '').trim();
                            const currentPriceNum = parseFloat(offer.product_price);
                            const isFree = !isNaN(currentPriceNum) && currentPriceNum === 0;
                            const prevPrice = parseFloat(offer.product_previous_price);
                            const hasPreviousPrice = !isFree && !isNaN(prevPrice) && prevPrice > 0 && prevPrice > currentPriceNum;
                            const prevPriceFormatted = hasPreviousPrice ? formatCurrency(prevPrice) : '';
                            const discountPercent = hasPreviousPrice ? Math.round(((prevPrice - currentPriceNum) / prevPrice) * 100) : 0;
                            const discountLine = hasPreviousPrice && discountPercent > 0 ? `<p class="text-[0.8em] font-normal text-[#4ade80]/90 mt-1">💰 Economize ${discountPercent}%</p>` : '';
                            let priceHtml;
                            if (isFree) {
                                priceHtml = '<div class="mb-2"><span class="text-lg font-semibold text-green-400">Grátis</span></div>';
                            } else if (hasPreviousPrice) {
                                priceHtml = `<div class="mb-2 text-[0.82em] font-normal text-gray-400"><span>De </span><span class="line-through">${prevPriceFormatted}</span><span> por </span><span class="text-green-500/90">${productPrice}</span>${discountLine}</div>`;
                            } else {
                                priceHtml = `<div class="mb-2 text-lg font-semibold text-green-400">${productPrice}</div>`;
                            }
                            return `<a href="${checkoutLink}" ${linkTarget} class="group bg-gray-800 rounded-xl overflow-hidden border border-gray-700 hover:border-green-500 hover:-translate-y-1 hover:shadow-[0_12px_40px_-8px_rgba(0,0,0,0.5),0_0_16px_rgba(74,222,128,0.12)] transition-all duration-[0.22s] ease-out flex flex-col h-full" data-product-type="${escapeHtml(productType)}">
                                <div class="relative overflow-hidden">
                                    <img src="${productPhoto}" alt="${escapeHtml(offer.product_name)}" class="w-full h-40 object-cover transition-transform duration-[0.22s] ease-out group-hover:scale-[1.035]${lockedImgCls}" onerror="this.onerror=null;this.src='https://placehold.co/280x160/1f2937/d1d5db?text=Produto';">
                                    ${overlayHtml}
                                    ${lockedBadgeHtml}
                                    <span class="absolute top-2 left-2 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded-full uppercase">Exclusivo</span>
                                </div>
                                <div class="p-4 flex flex-col flex-grow">
                                    <h3 class="text-lg font-bold text-white mb-2 line-clamp-2">${escapeHtml(offer.product_name)}</h3>
                                    ${tagHtml}
                                    <p class="text-gray-400 text-sm mb-4 line-clamp-2 flex-grow">${escapeHtml(productDescription)}</p>
                                    ${priceHtml}
                                    <span class="mt-auto inline-flex items-center justify-center bg-green-600 text-white font-semibold py-2.5 px-4 rounded-lg hover:bg-green-700 transition duration-300 text-sm">${escapeHtml(buttonText)}</span>
                                </div>
                            </a>`;
                        }

                        if (items.length > 0) {
                            items.forEach(it => {
                                if (it.type === 'banner') {
                                    const cell = document.createElement('div');
                                    cell.className = 'col-span-full w-full mb-12 exclusive-offer-banner';
                                    cell.innerHTML = renderBannerHtml(it.data);
                                    exclusiveOffersGrid.appendChild(cell);
                                } else {
                                    const card = document.createElement('div');
                                    card.innerHTML = renderProductCard(it.data);
                                    exclusiveOffersGrid.appendChild(card);
                                }
                            });
                        } else {
                            banners.forEach(banner => {
                                const cell = document.createElement('div');
                                cell.className = 'col-span-full w-full mb-12 exclusive-offer-banner';
                                cell.innerHTML = renderBannerHtml(banner);
                                exclusiveOffersGrid.appendChild(cell);
                            });
                            offers.forEach(offer => {
                                const card = document.createElement('div');
                                card.innerHTML = renderProductCard(offer);
                                exclusiveOffersGrid.appendChild(card);
                            });
                        }
                        
                        exclusiveOffersLoading.style.display = 'none';
                        exclusiveOffersGrid.style.display = 'grid';
                        lucide.createIcons();
                        if (typeof applyCategoryFilter === 'function') applyCategoryFilter();
                    } else {
                        exclusiveOffersLoading.style.display = 'none';
                        exclusiveOffersEmpty.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Error fetching exclusive offers:', error);
                    exclusiveOffersLoading.style.display = 'none';
                    exclusiveOffersEmpty.style.display = 'block';
                    exclusiveOffersEmpty.innerHTML = `<i data-lucide="cloud-off" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i><p class="text-lg font-semibold text-red-500">Erro ao carregar ofertas!</p><p class="mt-2 text-sm text-gray-400">Tente novamente mais tarde ou entre em contato com o suporte.</p>`;
                    lucide.createIcons();
                }
            }

            fetchExclusiveOffers();
        });
    </script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/pwa/sw.js').then(function(reg) {
                    console.log('ServiceWorker registrado com sucesso:', reg.scope);
                }, function(err) {
                    console.log('Falha no registro do ServiceWorker:', err);
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
            var isMobile = window.matchMedia('(max-width: 768px)').matches || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            var show = deferredPrompt || (window.PwaPush && window.PwaPush.isRequestable) || isMobile;
            if (show) welcomeCard.classList.remove('hidden');
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
            } else if (installHint) {
                installHint.classList.remove('hidden');
            }
        });
        if (closeBtn) closeBtn.addEventListener('click', closeWelcome);
        if (dismissBtn) dismissBtn.addEventListener('click', closeWelcome);

        window.addEventListener('pwa-push-state', function() {
            if (window.PwaPush && window.PwaPush.isRequestable) showWelcomeIfNeeded();
        });
        setTimeout(showWelcomeIfNeeded, 2000);
    })();
    </script>
    <?php 
    $mp_path = __DIR__ . '/../includes/member_protection.php';
    if (file_exists($mp_path)) require_once $mp_path; 
    ?>
</body>
</html>
