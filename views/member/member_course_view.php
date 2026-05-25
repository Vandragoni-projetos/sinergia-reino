<?php
require_once __DIR__ . '/../../config/config.php';
if (file_exists(__DIR__ . '/../../helpers/html_sanitizer.php')) {
    require_once __DIR__ . '/../../helpers/html_sanitizer.php';
}
if (file_exists(__DIR__ . '/../../helpers/member_protection_helper.php')) {
    require_once __DIR__ . '/../../helpers/member_protection_helper.php';
}

// Proteção da página: apenas usuários logados DO TIPO 'usuario' (cliente) podem acessar.
// Administradores são redirecionados para o painel de admin.
// Usuários não logados são redirecionados para a tela de login da área de membros.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /member_login");
    exit;
}

// Se for um administrador logado, redireciona para o painel de admin, pois não deve acessar a área de membros.
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] === 'admin') {
    header("location: /admin");
    exit;
}

// Assume que o usuário logado é um cliente (tipo 'usuario')
$cliente_email = $_SESSION['usuario']; 
$cliente_nome = $_SESSION['nome'] ?? $cliente_email; 

$mensagem_erro = '';
$curso = null;
$modulos_com_aulas = [];
$total_aulas_desbloqueadas = 0; // Total de aulas DESBLOQUEADAS para cálculo de progresso
$aulas_concluidas_desbloqueadas = 0; // Aulas concluídas que estão DESBLOQUEADAS
$progresso_percentual = 0;
$upload_dir = 'uploads/';
$aula_files_dir_public = 'uploads/aula_files/'; // Caminho público para download

// Valida o ID do produto
if (!isset($_GET['produto_id']) || !is_numeric($_GET['produto_id'])) {
    $mensagem_erro = "ID do curso inválido. Por favor, volte ao painel.";
} else {
    $produto_id = (int)$_GET['produto_id'];

    try {
        // 1. Verifica se o cliente tem acesso a este produto/curso E busca a data de concessão
        // Usando o helper de acesso para verificar expiração
        if (function_exists('verificar_acesso_aluno')) {
            $acesso_info = verificar_acesso_aluno($pdo, $cliente_email, $produto_id);
            
            if (!$acesso_info['tem_acesso']) {
                if ($acesso_info['expirado']) {
                    $data_exp = new DateTime($acesso_info['data_expiracao']);
                    $mensagem_erro = "Seu acesso a este curso expirou em " . $data_exp->format('d/m/Y') . ". Entre em contato com o suporte para renovar.";
                } else {
                    $mensagem_erro = "Você não tem acesso a este curso. Se acredita que é um erro, entre em contato com o suporte.";
                }
            } else {
                $data_concessao = new DateTime($acesso_info['data_concessao']);
                $current_date = new DateTime();
                $acesso_vitalicio = $acesso_info['vitalicio'] ?? false;
                $data_expiracao_acesso = $acesso_info['data_expiracao'] ?? null;
            }
        } else {
            // Fallback para o método antigo se o helper não estiver disponível
            $stmt_acesso = $pdo->prepare("SELECT data_concessao, data_expiracao FROM alunos_acessos WHERE aluno_email = ? AND produto_id = ?");
            $stmt_acesso->execute([$cliente_email, $produto_id]);
            $acesso_info_raw = $stmt_acesso->fetch(PDO::FETCH_ASSOC);
            
            if (!$acesso_info_raw) {
                $mensagem_erro = "Você não tem acesso a este curso. Se acredita que é um erro, entre em contato com o suporte.";
            } else {
                // Verificar expiração
                if ($acesso_info_raw['data_expiracao'] !== null) {
                    $agora = new DateTime();
                    $expiracao = new DateTime($acesso_info_raw['data_expiracao']);
                    if ($agora > $expiracao) {
                        $mensagem_erro = "Seu acesso a este curso expirou em " . $expiracao->format('d/m/Y') . ". Entre em contato com o suporte para renovar.";
                    }
                }
                
                if (empty($mensagem_erro)) {
                    $data_concessao = new DateTime($acesso_info_raw['data_concessao']);
                    $current_date = new DateTime();
                    $acesso_vitalicio = ($acesso_info_raw['data_expiracao'] === null);
                    $data_expiracao_acesso = $acesso_info_raw['data_expiracao'];
                }
            }
        }
        
        // Continua apenas se não houver erro
        if (empty($mensagem_erro)) {

            // 2. Busca os detalhes do curso e o produto associado
            $stmt_curso = $pdo->prepare("
                SELECT c.*, p.nome as produto_nome, p.descricao as produto_descricao, p.foto as produto_foto 
                FROM cursos c
                JOIN produtos p ON c.produto_id = p.id
                WHERE p.id = ? AND p.tipo_entrega = 'area_membros'
            ");
            $stmt_curso->execute([$produto_id]);
            $curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);

            if (!$curso) {
                $mensagem_erro = "Curso não encontrado ou não está configurado como 'Área de Membros'.";
            } else {
                // 3. Busca os módulos do curso
                $stmt_modulos = $pdo->prepare("SELECT id, curso_id, titulo, imagem_capa_url, ordem, release_days FROM modulos WHERE curso_id = ? ORDER BY ordem ASC, id ASC");
                $stmt_modulos->execute([$curso['id']]);
                $modulos = $stmt_modulos->fetchAll(PDO::FETCH_ASSOC);

                // 4. Busca aulas, progresso e arquivos em batch (evita N+1)
                $chk_origem = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'origem_video'");
                $chk_cover = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'lesson_cover_type'");
                $chk_termo = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'require_download_terms'");
                $aulas_cols = "a.id, a.modulo_id, a.titulo, a.url_video, a.descricao, a.ordem, a.release_days, a.tipo_conteudo";
                if ($chk_origem && $chk_origem->rowCount() > 0) $aulas_cols .= ", a.origem_video";
                if ($chk_cover && $chk_cover->rowCount() > 0) $aulas_cols .= ", a.lesson_cover_type, a.lesson_cover_url, a.lesson_cover_path";
                if ($chk_termo && $chk_termo->rowCount() > 0) $aulas_cols .= ", a.require_download_terms, a.download_terms_text";
                $stmt_aulas_all = $pdo->prepare("
                    SELECT $aulas_cols
                    FROM aulas a
                    INNER JOIN modulos m ON a.modulo_id = m.id
                    WHERE m.curso_id = ?
                    ORDER BY m.ordem ASC, m.id ASC, a.ordem ASC, a.id ASC
                ");
                $stmt_aulas_all->execute([$curso['id']]);
                $aulas_raw = $stmt_aulas_all->fetchAll(PDO::FETCH_ASSOC);
                $aula_ids = array_column($aulas_raw, 'id');
                $progresso_map = [];
                $files_map = [];
                if (!empty($aula_ids)) {
                    $ph = implode(',', array_fill(0, count($aula_ids), '?'));
                    $stmt_prog = $pdo->prepare("SELECT aula_id FROM aluno_progresso WHERE aluno_email = ? AND aula_id IN ($ph)");
                    $stmt_prog->execute(array_merge([$cliente_email], $aula_ids));
                    while ($r = $stmt_prog->fetch(PDO::FETCH_ASSOC)) {
                        $progresso_map[(int)$r['aula_id']] = true;
                    }
                    $chk_ordem = @$pdo->query("SHOW COLUMNS FROM aula_arquivos LIKE 'ordem'");
                    $files_order = ($chk_ordem && $chk_ordem->rowCount() > 0) ? "ORDER BY ordem ASC, id ASC" : "ORDER BY id ASC";
                    $stmt_files = $pdo->prepare("SELECT id, aula_id, nome_original, nome_salvo FROM aula_arquivos WHERE aula_id IN ($ph) $files_order");
                    $stmt_files->execute($aula_ids);
                    while ($r = $stmt_files->fetch(PDO::FETCH_ASSOC)) {
                        $aid = (int)$r['aula_id'];
                        if (!isset($files_map[$aid])) $files_map[$aid] = [];
                        $files_map[$aid][] = $r;
                    }
                }
                $aulas_por_modulo = [];
                foreach ($aulas_raw as $aula) {
                    $mid = (int)$aula['modulo_id'];
                    if (!isset($aulas_por_modulo[$mid])) $aulas_por_modulo[$mid] = [];
                    $aulas_por_modulo[$mid][] = $aula;
                }

                foreach ($modulos as $modulo) {
                    $module_release_date = clone $data_concessao;
                    $module_release_date->modify("+{$modulo['release_days']} days");
                    $modulo['is_locked'] = ($current_date < $module_release_date);
                    $modulo['available_at'] = $module_release_date->format('d/m/Y H:i');
                    $aulas_com_progresso = [];
                    foreach ($aulas_por_modulo[$modulo['id']] ?? [] as $aula) {
                        $lesson_release_date = clone $data_concessao;
                        $lesson_release_date->modify("+{$aula['release_days']} days");
                        $aula['is_locked'] = ($current_date < $lesson_release_date);
                        $aula['available_at'] = $lesson_release_date->format('d/m/Y H:i');
                        if (!$aula['is_locked']) $total_aulas_desbloqueadas++;
                        $aula['concluida'] = !empty($progresso_map[(int)$aula['id']]);
                        if ($aula['concluida'] && !$aula['is_locked']) $aulas_concluidas_desbloqueadas++;
                        $aula['files'] = $files_map[(int)$aula['id']] ?? [];
                        $desc_raw = $aula['descricao'] ?? '';
                        if (trim($desc_raw) !== '') {
                            if (function_exists('sanitize_lesson_html')) {
                                $desc = sanitize_lesson_html($desc_raw);
                            } else {
                                $desc = htmlspecialchars($desc_raw);
                            }
                            if (strpos($desc, '<a ') === false && preg_match('/https?:\/\//', $desc)) {
                                $desc = preg_replace('/(https?:\/\/[^\s<]+)/', '<a href="$1" target="_blank" rel="noopener noreferrer nofollow" class="text-green-500 hover:underline">$1</a>', $desc);
                            }
                            if (strpos($desc, '<') === false) {
                                $desc = nl2br($desc);
                            }
                            $aula['descricao'] = $desc;
                        } else {
                            $aula['descricao'] = '';
                        }
                        $aulas_com_progresso[] = $aula;
                    }
                    $modulos_com_aulas[] = ['modulo' => $modulo, 'aulas' => $aulas_com_progresso];
                }
                if ($total_aulas_desbloqueadas > 0) {
                    $progresso_percentual = round(($aulas_concluidas_desbloqueadas / $total_aulas_desbloqueadas) * 100);
                }
            }
        }
    } catch (PDOException $e) {
        $mensagem_erro = "Erro de banco de dados: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($curso['titulo'] ?? 'Curso'); ?> - Área de Membros</title>
    <?php
    // Adiciona favicon se configurado
    require_once __DIR__ . '/../../config/config.php';
    $favicon_url_raw = getSystemSetting('favicon_url', '');
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
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .prose { /* TailwindCSS Typography plugin classes can be added here or in global CSS */
            --tw-prose-body: #d1d5db; 
            --tw-prose-headings: #f9fafb; 
            --tw-prose-links: #2DD05E; 
        }
        .module-card.active { border-color: #2DD05E; box-shadow: 0 0 15px #2DD05E; transform: scale(1.05); }
        .lesson-item.active { background-color: #289b4aff; color: #ffedd5; font-weight: 600; }
        .lesson-item.active .lucide-play-circle { color: #ffffff; }
        .aspect-video { aspect-ratio: 16 / 9; }
        .lesson-cover-wrap { width: 100%; aspect-ratio: 16/9; background: #000; overflow: hidden; }
        .lesson-cover-img { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; }
        .header-bg {
            background-image: linear-gradient(to right, #2DD05E, #24dd5bff);
        }
        .lesson-item.locked { 
            cursor: not-allowed; 
            opacity: 0.6; 
            background-color: #2d3748; /* Mais escuro para indicar bloqueio */
        }
        .lesson-item.locked:hover {
            background-color: #2d3748; /* Não muda ao hover */
        }
        .lesson-item.locked .lucide-play-circle, .lesson-item.locked .lucide-lock, .lesson-item.locked .lucide-file-text {
            color: #718096; /* Cinza para ícones bloqueados */
        }
        
        /* ===== INÍCIO: PLAYER YOUTUBE CUSTOMIZADO (CSS DO YMin) ===== */
        .ymin{
         --aspect:16/9; --crop:2000px; --accent:#2DD05E; --bar-color:var(--accent); --track-color:#202532; /* <-- COR LARANJA PRINCIPAL AJUSTADA AQUI */
         position:relative; width:100%; aspect-ratio:var(--aspect); background:#000; overflow:hidden;
         /* Adicionado para se encaixar no layout */
         border-radius: 0.75rem; /* 12px */
         box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        .ymin .frame{position:relative;width:100%;height:100%;background:#000;overflow:hidden}
        .ymin iframe{position:absolute;inset:0;width:100%;height:calc(100% + var(--crop));top:calc(var(--crop)*-0.5);border:0;display:block;opacity:0;transition:opacity .18s ease}
        .ymin.ready iframe{opacity:1}
        .ymin .veil{position:absolute;inset:0;background:#000;z-index:8;opacity:1;transition:opacity .18s ease}
        .ymin.ready .veil{opacity:0;pointer-events:none}
        .ymin .clickzone{position:absolute;inset:0;z-index:9}

        /* Capas (com ícone) */
        .ymin .overlay{position:absolute;inset:0;z-index:10;display:grid;place-items:center;background:rgba(0,0,0,.5);pointer-events:none}
        .ymin .overlay[hidden]{display:none}
.ymin .cover{display:grid;place-items:center;text-align:center}
.ymin .icon{width:110px;max-width:26vw;height:auto;filter:drop-shadow(0 10px 28px rgba(0,0,0,.6));animation:pulse 1.6s ease-in-out infinite;
         filter: brightness(0) invert(1); /* <-- FORÇA O ÍCONE GRANDE DE PLAY A SER BRANCO */
        }
@keyframes pulse{0%{transform:scale(1)}50%{transform:scale(1.06)}100%{transform:scale(1)}}

        /* HUD + barra (interativa) */
        .ymin .hud.ui{position:absolute;left:0;right:0;bottom:0;z-index:12;height:10px;pointer-events:auto}
        .ymin .progress{position:absolute;left:0;right:0;bottom:0;height:10px;background:var(--track-color);border:0;overflow:hidden;cursor:pointer}
        .ymin .progress .bar{position:absolute;left:0;top:0;bottom:0;width:0;background:var(--bar-color);transition:width .08s linear}

        .ymin .timecode.ui{
         position:absolute; left:12px; bottom:14px; z-index:13;
         padding:4px 8px; border-radius:8px; background:rgba(0,0,0,.55);
         color:#fff; font:600 12px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,"Noto Sans","Apple Color Emoji","Segoe UI Emoji"; /* <-- COR DO TEXTO (BRANCO) */
        }

        .ymin .ctrls-right.ui{
         position:absolute; right:10px; bottom:12px; z-index:13; display:flex; gap:8px;
        }
        .ymin .btn{
         width:40px; height:40px; border:0; border-radius:10px; background:var(--accent); color:#fff; /* <-- COR DO BOTÃO (LARANJA) E ÍCONE (BRANCO) */
         display:grid; place-items:center; cursor:pointer; box-shadow:0 6px 18px rgba(0,0,0,.35);
         transition:transform .12s ease, filter .12s ease;
        }
        .ymin .btn:hover{transform:translateY(-1px);filter:brightness(.9)}
.ymin .btn img{width:22px;height:22px;display:block;pointer-events:none;
         filter: brightness(0) invert(1); /* <-- FORÇA OS ÍCONES DOS BOTÕES A SEREM BRANCOS */
        }

:fullscreen .ymin .frame{aspect-ratio:auto;height:100vh}
        :-webkit-full-screen .ymin .frame{aspect-ratio:auto;height:100vh}

        .ymin .ui{opacity:1;transition:opacity .18s ease, transform .18s ease}
        .ymin.controls-hidden .ui{opacity:0; transform:translateY(12px); pointer-events:none}

        /* ===== Vertical (Shorts) ===== */
        .ymin.vertical{
         --aspect:9/16;
         width:min(520px, 100%);
         max-height:84vh;
         margin:0 auto;
         border-radius:14px;
        }
        .ymin.vertical iframe{
         width:calc(100% + var(--crop));
         height:100%;
         left:calc(var(--crop)*-0.5);
         top:0;
        }

        /* Menus de Velocidade e Qualidade */
        .ymin .speed-menu, .ymin .quality-menu {
            position: absolute;
            right: 10px;
            bottom: 60px;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 8px;
            display: none;
            flex-direction: column;
            z-index: 50;
            min-width: 120px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
        }
        .ymin .speed-menu.show, .ymin .quality-menu.show {
            display: flex;
        }
        .ymin .speed-menu button, .ymin .quality-menu button {
            background: transparent;
            border: none;
            color: #d1d5db;
            padding: 8px 12px;
            text-align: left;
            cursor: pointer;
            font-size: 14px;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ymin .speed-menu button:hover, .ymin .quality-menu button:hover {
            background: rgba(45, 208, 94, 0.2);
            color: #fff;
        }
        .ymin .speed-menu button.active, .ymin .quality-menu button.active {
            color: #2DD05E;
            font-weight: 700;
        }
        .ymin .speed-menu button.active::after, .ymin .quality-menu button.active::after {
            content: '✓';
            margin-left: 8px;
        }
        /* ===== FIM: PLAYER YOUTUBE CUSTOMIZADO (CSS DO YMin) ===== */
    
        /* Reset do estilo nativo do <button> para evitar "faixas" e caracteres estranhos (ex: '>') */
        button.module-card{
            -webkit-appearance: none !important;
            appearance: none !important;
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
        }
        button.module-card:disabled{
            cursor: not-allowed;
        }

</style>
</head>
<body class="bg-gray-900 text-gray-200 antialiased">
    
    <!-- Cabeçalho da Área de Membros -->
    <header class="sticky top-0 z-50 w-full border-b border-gray-700/50 bg-gray-900/70 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
            <div class="flex items-center space-x-4">
                <a href="/member_area_dashboard" class="text-white/90 hover:text-white transition-colors">
                    <i data-lucide="arrow-left-circle" class="w-7 h-7"></i>
                </a>
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($curso['titulo'] ?? 'Detalhes do Curso'); ?></h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="font-medium hidden md:block text-gray-300">Olá, <?php echo htmlspecialchars($cliente_nome); ?>!</span>
                <a href="/member_logout" class="flex items-center space-x-2 text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                    <span class="hidden sm:block font-medium">Sair</span>
                </a>
            </div>
            </div>
        </div>
    </header>

    <?php if ($mensagem_erro): ?>
        <div class="flex h-[calc(100vh-64px)] items-center justify-center p-8">
            <div class="bg-red-900 border border-red-700 text-red-200 px-6 py-4 rounded-lg text-center max-w-lg">
                <p class="font-bold text-lg">Ocorreu um Erro</p>
                <p><?php echo $mensagem_erro; ?></p>
                 <a href="/member_area_dashboard" class="mt-4 inline-block bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 transition">Voltar aos Meus Cursos</a>
            </div>
        </div>
    <?php elseif (!$curso): ?>
        <div class="flex h-[calc(100vh-64px)] items-center justify-center p-8">
             <div class="bg-gray-800 border border-gray-700 text-gray-300 px-6 py-4 rounded-lg text-center">
                <p>Carregando...</p>
            </div>
        </div>
    <?php else: ?>
    <?php
    $comentarios_ativos = 0;
    $chk_comentarios = @$pdo->query("SHOW COLUMNS FROM cursos LIKE 'comentarios_ativos'");
    if ($chk_comentarios && $chk_comentarios->rowCount() > 0) {
        $comentarios_ativos = (int)($curso['comentarios_ativos'] ?? 0);
    }
    $certificado_habilitado = false;
    $certificado_conclusao_minima = 100;
    $pode_baixar_certificado = false;
    $chk_cert = @$pdo->query("SHOW COLUMNS FROM cursos LIKE 'certificado_habilitado'");
    if ($chk_cert && $chk_cert->rowCount() > 0) {
        $certificado_habilitado = (int)($curso['certificado_habilitado'] ?? 0) === 1;
        $certificado_conclusao_minima = (int)($curso['certificado_conclusao_minima'] ?? 100);
        $pode_baixar_certificado = $certificado_habilitado && $progresso_percentual >= $certificado_conclusao_minima;
    }
    $gamificacao_habilitado = false;
    $chk_gam = @$pdo->query("SHOW TABLES LIKE 'curso_gamificacao'");
    if ($chk_gam && $chk_gam->rowCount() > 0) {
        $stmt_g = $pdo->prepare("SELECT habilitado FROM curso_gamificacao WHERE curso_id = ?");
        $stmt_g->execute([$curso['id']]);
        $g = $stmt_g->fetch(PDO::FETCH_ASSOC);
        $gamificacao_habilitado = $g && (int)($g['habilitado'] ?? 0) === 1;
    }
    ?>
    <div id="course-container" class="min-h-screen member-protected-content" data-comentarios-ativos="<?php echo $comentarios_ativos; ?>" data-aluno-email="<?php echo htmlspecialchars($cliente_email); ?>" data-gamificacao="<?php echo $gamificacao_habilitado ? '1' : '0'; ?>" data-produto-id="<?php echo (int)$produto_id; ?>" data-initial-aula-id="<?php echo (int)($_GET['aula_id'] ?? 0); ?>">
        <?php
        $banner_raw = $curso['banner_url'] ?? $curso['produto_foto'] ?? '';
        // Acesso já validado em verificar_acesso_aluno. Usa caminho direto para evitar 403 em /media.
        $banner_src = !empty($banner_raw) ? resolve_product_image_url($banner_raw, $upload_dir ?? 'uploads/') : '';
        $banner_src = $banner_src ? htmlspecialchars($banner_src) : '';
        ?>
        <!-- Banner do Topo do Curso (URL protegida) -->
        <header class="relative h-64 md:h-80 bg-gray-800 bg-cover bg-center" style="<?php echo $banner_src ? "background-image: url('$banner_src');" : ''; ?>">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/70 to-transparent"></div>
            <div class="relative h-full flex flex-col justify-end p-6 md:p-10 max-w-7xl mx-auto">
                <h1 class="text-3xl md:text-5xl font-extrabold text-white drop-shadow-lg"><?php echo htmlspecialchars($curso['titulo']); ?></h1>
                <p class="mt-2 text-lg text-gray-300 max-w-2xl drop-shadow-md"><?php echo htmlspecialchars($curso['descricao'] ?? $curso['produto_descricao']); ?></p>
            </div>
        </header>

        <main class="max-w-7xl mx-auto p-4 md:p-8 w-full">
            <?php if ($certificado_habilitado && !empty($modulos_com_aulas) && $total_aulas_desbloqueadas > 0): ?>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 p-4 bg-gray-800/80 border border-gray-700 rounded-xl">
                <?php if ($pode_baixar_certificado): ?>
                <a href="/certificado_curso?produto_id=<?php echo (int)$produto_id; ?>" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-500 text-white font-bold py-2.5 px-5 rounded-lg transition">
                    <i data-lucide="award" class="w-5 h-5"></i>
                    <span>Baixar Certificado</span>
                </a>
                <?php else: ?>
                <p class="text-gray-400 text-sm">
                    <i data-lucide="award" class="w-4 h-4 inline-block align-middle mr-1"></i>
                    Complete <?php echo $certificado_conclusao_minima; ?>% do curso para desbloquear o certificado (<?php echo $progresso_percentual; ?>% concluído).
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (isset($_GET['certificado']) && $_GET['certificado'] === 'pendente'): ?>
            <div class="mb-6 p-4 bg-amber-900/30 border border-amber-600/50 rounded-xl text-amber-200 text-sm">
                Complete <?php echo $certificado_conclusao_minima; ?>% do curso para liberar o certificado. Seu progresso atual: <?php echo $progresso_percentual; ?>%.
            </div>
            <?php endif; ?>
            <?php if ($gamificacao_habilitado && !empty($modulos_com_aulas) && $total_aulas_desbloqueadas > 0): ?>
            <div id="conquistas-minhas-wrap" class="mb-6 p-4 bg-gray-800/80 border border-gray-700 rounded-xl">
                <p class="text-sm font-semibold text-green-400 mb-2 flex items-center gap-2"><i data-lucide="trophy" class="w-4 h-4"></i> MINHAS CONQUISTAS</p>
                <div id="conquistas-grid" class="flex flex-wrap gap-3">
                    <span class="text-gray-500 text-sm">Carregando...</span>
                </div>
            </div>
            <?php endif; ?>
            <?php if (empty($modulos_com_aulas) || $total_aulas_desbloqueadas === 0): ?>
                <div class="bg-gray-800 border border-gray-700 p-8 rounded-lg text-center text-gray-400">
                    <i data-lucide="video-off" class="mx-auto w-16 h-16 text-gray-600"></i>
                    <p class="mt-4 font-semibold text-lg text-gray-200">Este curso ainda não tem conteúdo disponível.</p>
                    <p>Entre em contato com o suporte se isso for um erro ou verifique as datas de liberação.</p>
                </div>
            <?php else: ?>

                <!-- Player e Aulas (Oculto por padrão, visível após selecionar um módulo) -->
                <div id="player-wrapper" class="hidden">
                    <!-- Barra de Progresso -->
                    <div class="mb-8">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-semibold text-green-400">SEU PROGRESSO</span>
                            <span class="text-sm font-bold text-white"><?php echo $progresso_percentual; ?>% Completo</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2.5">
                            <div class="bg-green-500 h-2.5 rounded-full" style="width: <?php echo $progresso_percentual; ?>%"></div>
                        </div>
                        <?php if ($certificado_habilitado && $pode_baixar_certificado): ?>
                        <div class="mt-3">
                            <a href="/certificado_curso?produto_id=<?php echo (int)$produto_id; ?>" class="inline-flex items-center gap-2 text-green-400 hover:text-green-300 font-semibold text-sm">
                                <i data-lucide="award" class="w-4 h-4"></i>
                                Baixar Certificado
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Player e Lista de Aulas -->
                    <div id="player-section" class="flex flex-col lg:flex-row gap-8 mb-12">
                        <!-- Coluna Esquerda: Player e Detalhes -->
                        <div class="lg:w-2/3 w-full">
                            
                            <!-- [INÍCIO DA MUDANÇA] Container do Player YMin -->
                            <!-- Este div será o "host" para o player YMin ou para o placeholder. -->
                            <!-- Removido 'aspect-video' daqui, pois o YMin ou o placeholder interno controlarão o aspecto. -->
                            <div id="player-host" class="bg-black rounded-xl shadow-2xl mb-6">
                                <!-- Placeholder inicial que será substituído -->
                                <div class="w-full aspect-video bg-black flex flex-col items-center justify-center text-gray-500 rounded-xl">
                                    <i data-lucide="play-circle" class="w-16 h-16 text-gray-600 mb-4"></i>
                                    <p class="text-lg font-semibold">Selecione um módulo e uma aula para começar.</p>
                                </div>
                            </div>
                            <!-- [FIM DA MUDANÇA] Container do Player YMin -->

                            <div class="bg-gray-800 p-6 rounded-xl shadow-lg">
                                <h2 id="lesson-title" class="text-2xl font-bold text-white mb-4">Selecione um módulo para começar</h2>
                                <div id="lesson-description" class="prose max-w-none">
                                    <p>A descrição e materiais da aula aparecerão aqui.</p>
                                </div>
                                <div class="mt-6 pt-4 border-t border-gray-700 flex flex-wrap justify-between items-center gap-4">
                                    <button id="next-lesson-btn" class="border-2 border-red-500 text-red-500 font-bold py-2.5 px-5 rounded-lg transition duration-300 flex items-center space-x-2 hover:bg-red-500 hover:text-white disabled:opacity-50 disabled:cursor-not-allowed hidden">
                                        <i data-lucide="skip-forward" class="w-5 h-5"></i>
                                        <span>Próxima Aula</span>
                                    </button>
                                    <button id="mark-as-complete-btn" class="border-2 border-green-500 text-green-500 font-bold py-2.5 px-5 rounded-lg transition duration-300 flex items-center space-x-2 hover:bg-green-500 hover:text-white disabled:opacity-50 disabled:cursor-not-allowed hidden ml-auto">
                                        <i data-lucide="check-square" class="w-5 h-5"></i>
                                        <span>Marcar como Concluída</span>
                                    </button>
                                </div>
                            </div>
                            <!-- Comentários da Aula -->
                            <div id="comentarios-section" class="mt-6 bg-gray-800 p-6 rounded-xl shadow-lg hidden">
                                <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                                    <i data-lucide="message-circle" class="w-5 h-5"></i>
                                    Comentários
                                </h3>
                                <div id="comentarios-list" class="space-y-3 mb-4 max-h-60 overflow-y-auto">
                                    <p class="text-gray-500 text-sm">Carregando...</p>
                                </div>
                                <div id="comentarios-feedback" class="hidden mb-4 p-4 rounded-lg border text-sm"></div>
                                <div id="comentarios-form-wrap" class="border-t border-gray-700 pt-4">
                                    <textarea id="comentario-texto" rows="3" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Escreva seu comentário ou dúvida sobre esta aula..." maxlength="2000"></textarea>
                                    <p class="text-xs text-gray-500 mt-1"><span id="comentario-char-count">0</span>/2000</p>
                                    <button id="comentario-submit-btn" type="button" class="mt-2 bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition">
                                        Enviar comentário
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Coluna Direita: Lista de Aulas do Módulo Ativo -->
                        <aside class="lg:w-1/3 w-full bg-gray-800 rounded-xl shadow-lg p-4 flex-shrink-0 h-fit lg:sticky top-20">
                            <h3 id="module-title-aside" class="font-bold text-xl text-white mb-4 px-2">Aulas do Módulo</h3>
                            <div id="lesson-list-container" class="space-y-2 max-h-[70vh] overflow-y-auto pr-2">
                               <p class="text-gray-400 px-2">Selecione um módulo abaixo para ver as aulas.</p>
                            </div>
                        </aside>
                    </div>
                </div>

                <!-- Seção de Módulos (Sempre visível) -->
                <div>
                    <h2 class="text-3xl font-bold text-white mb-6">Módulos do Curso</h2>
                    <div id="modules-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                        <?php foreach ($modulos_com_aulas as $index => $item): ?>
                            <?php
                            $module = $item['modulo'];

                            // Progresso do módulo (aulas concluídas / total de aulas)
                            $total_lessons = is_array($item['aulas'] ?? null) ? count($item['aulas']) : 0;
                            $completed_lessons = 0;
                            if ($total_lessons > 0) {
                                foreach ($item['aulas'] as $aula_tmp) {
                                    if (!empty($aula_tmp['concluida'])) {
                                        $completed_lessons++;
                                    }
                                }
                            }
                            $module_progress_percent = ($total_lessons > 0) ? (int) round(($completed_lessons / $total_lessons) * 100) : 0;

                            $is_module_locked = $module['is_locked'];
                            $module_button_classes = "module-card group relative flex flex-col rounded-lg overflow-hidden border-2 border-gray-700 bg-transparent p-0 m-0 hover:border-green-500 focus:outline-none focus:ring-4 focus:ring-green-500/50 transition-all duration-300 text-left appearance-none";
                            $module_button_classes .= $is_module_locked ? ' opacity-50 cursor-not-allowed' : '';
                            ?>
                            <button class="<?php echo $module_button_classes; ?>" 
                                    data-module-id="<?php echo $module['id']; ?>" 
                                    data-module-index="<?php echo $index; ?>"
                                    <?php echo $is_module_locked ? 'disabled' : ''; ?>
                                    >
                                <!-- IMAGEM (banner do módulo - vertical 2:3, ex: 1080x1620) -->
                                <div class="relative aspect-[2/3] bg-gray-700 overflow-hidden">
                                    <?php 
                                    // Acesso já validado em verificar_acesso_aluno. Usa caminho direto para evitar 403 em /media.
                                    $module_img_url = !empty($module['imagem_capa_url'])
                                        ? resolve_product_image_url($module['imagem_capa_url'], $upload_dir ?? 'uploads/')
                                        : '';
                                    $locked_cap = $is_module_locked ? 'grayscale brightness-75 contrast-125' : '';
                                    ?>
                                    <?php if (!empty($module_img_url)): ?>
                                        <img src="<?php echo htmlspecialchars($module_img_url); ?>" alt="Capa do <?php echo htmlspecialchars($module['titulo']); ?>" class="absolute inset-0 w-full h-full object-cover transition-all duration-300 group-hover:scale-110 block <?php echo $locked_cap; ?>">
                                    <?php else: ?>
                                        <div class="absolute inset-0 bg-gray-700 flex items-center justify-center <?php echo $is_module_locked ? 'opacity-70' : ''; ?>">
                                            <i data-lucide="image" class="w-12 h-12 text-gray-500"></i>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($is_module_locked): ?>
                                        <div class="absolute inset-0 bg-black/35 pointer-events-none" aria-hidden="true"></div>
                                        <span class="absolute top-2 right-2 bg-gray-900/90 text-gray-300 text-xs font-semibold px-2 py-1 rounded flex items-center gap-1.5 pointer-events-none">
                                            <i data-lucide="lock" class="w-3.5 h-3.5 flex-shrink-0"></i> Bloqueado
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- PROGRESSO DO MÓDULO (barra + %) -->
                                <div class="px-4 pt-2">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[11px] text-white/70"><?php echo (int)$module_progress_percent; ?>%</span>
                                    </div>
                                    <div class="w-full h-[2px] bg-white/10 rounded-full overflow-hidden">
                                        <div class="h-[2px] rounded-full transition-all duration-300"
                                             style="width: <?php echo (int)$module_progress_percent; ?>%; background-color: #2DD05E;">
                                        </div>
                                    </div>
                                </div>

                                <!-- INFORMAÇÕES DO MÓDULO (abaixo do banner) -->
                                <div class="p-4 pt-3 leading-normal">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="text-sm font-medium text-white/90 leading-snug">
                                            <?php echo htmlspecialchars($module['titulo']); ?>
                                        </h4>
                                        <?php if ($module_progress_percent >= 100): ?>
                                            <span class="text-green-400 text-sm font-semibold" title="Módulo concluído">✓</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($is_module_locked): ?>
                                        <span class="text-xs text-red-400 flex items-center mt-2">
                                            <i data-lucide="lock" class="w-4 h-4 mr-1"></i>
                                            Disponível em: <?php echo htmlspecialchars($module['available_at']); ?>
                                        </span>
                                    <?php else: ?>
                                        <div class="flex items-center justify-between mt-2">
                                            <span class="text-xs text-green-300"><?php echo $total_lessons; ?> aulas</span>
                                            <?php if ($module_progress_percent >= 100): ?>
                                                <span class="text-xs text-green-300">Concluído</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <?php endif; ?>

    <script>
        /* =================================================================== */
        /* ====== INÍCIO: TECNOLOGIA DO PLAYER (COPIADO DO YMin) ====== */
        /* =================================================================== */

        /* ÍCONES personalizados (PNG) */
        const ICONS = {
         back5: "https://iili.io/KCUAyMJ.png",
         fwd5: "https://iili.io/KCU5QhF.png",
         play: "https://iili.io/KCUYGS4.png",
         fs: "https://iili.io/KCUaDBe.png",
         settings: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z'%3E%3C/path%3E%3Ccircle cx='12' cy='12' r='3'%3E%3C/circle%3E%3C/svg%3E"
        };

        /* Tempo para auto-ocultar controles (ms) */
        const HIDE_DELAY_MS = 2200;

        /* ===================== YOUTUBE PLAYER API (Carregador) ===================== */
        (function(){
         if (!window._ytApi) {
         window._ytApi = {};
         window._ytApi.promise = new Promise((resolve) => {
           window._ytApi._resolve = resolve;
           const s = document.createElement('script');
           s.src = 'https://www.youtube.com/iframe_api';
           document.head.appendChild(s);
           const prev = window.onYouTubeIframeAPIReady;
           window.onYouTubeIframeAPIReady = function(){
           if (typeof prev === 'function') try { prev(); } catch {}
           window._ytApi._resolve();
           };
         });
         }})();
        const ytApiReady = window._ytApi.promise;

        let yminPlayer=null, yminRaf=0, yminRoot=null, yminPlaying=false, yminFirst=false, idleTimer=0, scrubbing=false, manualQuality = null;

        /* Barra "fake" pra UX */
        const REACH_AT = 0.90, PEAK_AT = 0.70, ACCEL_SHAPE = 0.6;
        function fakeFromReal(p){
         p=Math.max(0,Math.min(1,p));
         if(p<=REACH_AT){ const t=p/REACH_AT; return PEAK_AT*Math.pow(t,ACCEL_SHAPE); }
         const t=(p-REACH_AT)/(1-REACH_AT); return PEAK_AT+(1-PEAK_AT)*(1-Math.pow(1-t,3));
        }
        function formatTime(s){
         s = Math.max(0, Math.floor(s||0));
         const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), sec = s%60;
         if (h>0) return `${h}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
         return `${m}:${String(sec).padStart(2,'0')}`;}

        /* ====== MOUNT COM IMAGENS (ícones customizados) ====== */
        function mountYMinHTML(root){
         const mountId='yt-mount-'+Math.random().toString(36).slice(2,8);
         root.innerHTML=`
         <div class="frame">
           <div class="clickzone" aria-hidden="true"></div>
           <div id="${mountId}"></div>
           <div class="veil" aria-hidden="true"></div>

           <div class="overlay start"><div class="cover">
           <img class="icon" src="${ICONS.play}" alt="Play">
           </div></div>

           <div class="overlay paused" hidden><div class="cover">
           <img class="icon" src="${ICONS.play}" alt="Play">
           </div></div>

           <div class="hud ui"><div class="progress"><div class="bar"></div></div></div>
           <div class="timecode ui"><span class="cur">0:00</span> / <span class="dur">0:00</span></div>

           <div class="speed-menu">
             <button data-speed="0.5" type="button">0.5x</button>
             <button data-speed="0.75" type="button">0.75x</button>
             <button data-speed="1" type="button" class="active">1x</button>
             <button data-speed="1.25" type="button">1.25x</button>
             <button data-speed="1.5" type="button">1.5x</button>
             <button data-speed="1.75" type="button">1.75x</button>
             <button data-speed="2" type="button">2x</button>
           </div>
           <div class="quality-menu"></div>
           <div class="ctrls-right ui">
           <button class="btn back5" type="button" aria-label="Voltar 5 segundos" title="Voltar 5s">
             <img src="${ICONS.back5}" alt="Voltar 5s">
           </button>
           <button class="btn fwd5" type="button" aria-label="Avançar 5 segundos" title="Avançar 5s">
             <img src="${ICONS.fwd5}" alt="Avançar 5s">
           </button>
           <button class="btn speed-btn" type="button" aria-label="Velocidade de reprodução" title="Velocidade de reprodução (S)">
             <span style="font-size: 14px; font-weight: 600;">1x</span>
           </button>
           <button class="btn quality-btn" type="button" aria-label="Qualidade do vídeo" title="Qualidade do vídeo (Q)">
             <span style="font-size: 12px; font-weight: 600;">Auto</span>
           </button>
           <button class="btn fsbtn" type="button" aria-label="Tela cheia" title="Tela cheia">
             <img src="${ICONS.fs}" alt="Tela cheia">
           </button>
           </div>
         </div>
         `;
         return mountId;
        }
        function destroyYMin(){
         cancelAnimationFrame(yminRaf); yminRaf=0;
         try{ yminPlayer && yminPlayer.destroy && yminPlayer.destroy(); }catch{}
         yminPlayer=null; yminRoot=null; yminPlaying=false; yminFirst=false; scrubbing=false;
         clearTimeout(idleTimer);
        }
        function showControls(root){
         root.classList.remove('controls-hidden');
         clearTimeout(idleTimer);
         idleTimer = setTimeout(()=>{ if (!scrubbing) root.classList.add('controls-hidden'); }, HIDE_DELAY_MS);
        }
        function clamp01(x){ return Math.max(0, Math.min(1, x)); }

        let currentVideoId = null;

        async function createYMin(root, videoId, forceQuality = null){
         destroyYMin(); yminRoot=root;
         currentVideoId = videoId;
         const mountId = mountYMinHTML(root);
         const isVertical = root.classList.contains('vertical') || root.dataset.vertical === '1';
         if (isVertical) { root.style.setProperty('--aspect','9/16'); }
         const frame   = root.querySelector('.frame');
         const clickzone = root.querySelector('.clickzone');
         const startOv  = root.querySelector('.overlay.start');
         const pausedOv = root.querySelector('.overlay.paused');
         const barEl   = root.querySelector('.progress .bar');
         const progress = root.querySelector('.progress');
         const curEl   = root.querySelector('.timecode .cur');
         const durEl   = root.querySelector('.timecode .dur');
         const fsBtn   = root.querySelector('.fsbtn');
         const back5Btn = root.querySelector('.back5');
         const fwd5Btn  = root.querySelector('.fwd5');
         const speedBtn = root.querySelector('.speed-btn');
         const speedMenu = root.querySelector('.speed-menu');
         const qualityBtn = root.querySelector('.quality-btn');
         const qualityMenu = root.querySelector('.quality-menu');

         let currentSpeed = parseFloat(localStorage.getItem('ymin-speed') || '1');
         function applySpeed(speed) {
           try {
             if (yminPlayer && typeof yminPlayer.setPlaybackRate === 'function') {
               yminPlayer.setPlaybackRate(speed);
               currentSpeed = speed;
               if (speedBtn && speedBtn.querySelector('span')) speedBtn.querySelector('span').textContent = speed + 'x';
               localStorage.setItem('ymin-speed', speed.toString());
               if (speedMenu) speedMenu.querySelectorAll('button').forEach(btn => {
                 btn.classList.toggle('active', parseFloat(btn.dataset.speed) === speed);
               });
             }
           } catch(e) { console.warn('Erro ao aplicar velocidade:', e); }
         }

         setTimeout(() => { try { root.classList.add('ready'); } catch {} }, 1500);
         showControls(root);
         await ytApiReady;
         yminPlayer = new YT.Player(mountId,{
         videoId, host:'https://www.youtube-nocookie.com',
         playerVars:{autoplay:1,mute:1,controls:0,disablekb:1,fs:0,modestbranding:1,rel:0,iv_load_policy:3,playsinline:1},
         events:{
           onReady(){
           if (forceQuality) {
             manualQuality = forceQuality;
             yminPlayer.setPlaybackQuality(forceQuality);
             yminFirst = true;
             startOv.hidden = true;
             pausedOv.hidden = true;
             const qLabels = { auto: 'Auto', small: '240p', medium: '360p', large: '480p', hd720: '720p', hd1080: '1080p', hd1440: '1440p', hd2160: '4K', highres: 'HD+', tiny: '144p' };
             if (qualityBtn && qualityBtn.querySelector('span')) qualityBtn.querySelector('span').textContent = qLabels[forceQuality] || 'Auto';
           } else {
             manualQuality = null;
           }
           try{yminPlayer.mute();yminPlayer.playVideo();}catch{}
           const savedSpeed = parseFloat(localStorage.getItem('ymin-speed') || '1');
           if (yminPlayer && typeof yminPlayer.setPlaybackRate === 'function') {
             yminPlayer.setPlaybackRate(savedSpeed);
             currentSpeed = savedSpeed;
             if (speedBtn && speedBtn.querySelector('span')) speedBtn.querySelector('span').textContent = savedSpeed + 'x';
             if (speedMenu) speedMenu.querySelectorAll('button').forEach(btn => {
               btn.classList.toggle('active', parseFloat(btn.dataset.speed) === savedSpeed);
             });
           }
           requestAnimationFrame(()=>root.classList.add('ready'));
           setTimeout(()=>{ try { root.classList.add('ready'); } catch {} }, 400);
           loop();
           },
           onStateChange(e){
           if(e.data===YT.PlayerState.PLAYING){
             yminPlaying=true; if(yminFirst){ startOv.hidden=true; pausedOv.hidden=true; }
           }else if(e.data===YT.PlayerState.PAUSED){
             yminPlaying=false; if(yminFirst){ pausedOv.hidden=false; }
           }else if(e.data===YT.PlayerState.ENDED){
             yminPlaying=false; try{yminPlayer.seekTo(0,true);yminPlayer.pauseVideo();}catch{} pausedOv.hidden=false;
           }
           }
         }
         });
         function firstPlay(){ yminFirst=true; startOv.hidden=true; try{yminPlayer.seekTo(0,true);yminPlayer.unMute();}catch{} play(); }
         function play(){ try{yminPlayer.playVideo();}catch{} }
         function pause(){ try{yminPlayer.pauseVideo();}catch{} }
         function toggle(){ showControls(root); yminPlaying ? pause() : (yminFirst ? play() : firstPlay()); }
         clickzone.addEventListener('click', toggle);
         root.addEventListener('mousemove', ()=>showControls(root), {passive:true});
         root.addEventListener('touchstart', ()=>showControls(root), {passive:true});
         root.addEventListener('touchmove', ()=>showControls(root), {passive:true});
         function enterFs(el){ (el.requestFullscreen||el.webkitRequestFullscreen||el.msRequestFullscreen||el.mozRequestFullScreen)?.call(el); }
         function exitFs(){ (document.exitFullscreen||document.webkitExitFullscreen||document.msExitFullscreen||document.mozCancelFullScreen)?.call(document); }
         function isFs(){ return document.fullscreenElement||document.webkitFullscreenElement||document.msFullscreenElement||document.mozFullScreenElement; }
         fsBtn.addEventListener('click', e=>{ e.stopPropagation(); showControls(root); isFs()?exitFs():enterFs(frame); });
         function seekBy(delta){
         try{
           const cur = yminPlayer?.getCurrentTime?.()||0;
           const dur = yminPlayer?.getDuration?.()||0;
           if (dur>0){
           let t = Math.max(0, Math.min(dur-0.1, cur + delta));
           yminPlayer.seekTo(t, true);
           }
         }catch{}
         }
         back5Btn.addEventListener('click', (e)=>{ e.stopPropagation(); showControls(root); seekBy(-5); });
         fwd5Btn .addEventListener('click', (e)=>{ e.stopPropagation(); showControls(root); seekBy(+5); });

          speedBtn.addEventListener('click', (e)=>{
            e.stopPropagation();
            showControls(root);
            const isShowing = speedMenu.classList.contains('show');
            root.querySelectorAll('.speed-menu, .quality-menu').forEach(m => m.classList.remove('show'));
            if (!isShowing) speedMenu.classList.add('show');
          });
          speedMenu.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', (e) => {
              e.stopPropagation();
              applySpeed(parseFloat(btn.dataset.speed));
              speedMenu.classList.remove('show');
            });
          });

          qualityBtn.addEventListener('click', (e)=>{
            e.stopPropagation();
            showControls(root);
            const isShowing = qualityMenu.classList.contains('show');
            root.querySelectorAll('.speed-menu, .quality-menu').forEach(m => m.classList.remove('show'));
            if (!isShowing) { qualityMenu.classList.add('show'); updateQualityMenu(); }
          });

          document.addEventListener('click', (e) => {
            if (qualityMenu && !root.contains(e.target)) root.querySelectorAll('.speed-menu, .quality-menu').forEach(m => m.classList.remove('show'));
          });

          function updateQualityMenu() {
            if (!yminPlayer || !yminPlayer.getAvailableQualityLevels) {
                console.error("YMin: Player não pronto ou API indisponível");
                return;
            }
            const levels = yminPlayer.getAvailableQualityLevels();
            const current = manualQuality || yminPlayer.getPlaybackQuality();
            console.log("YMin: Níveis disponíveis:", levels, "Qualidade atual (API):", yminPlayer.getPlaybackQuality(), "Qualidade Manual:", manualQuality);
            
            if (!levels || levels.length <= 1) { // 1 pois normalmente sempre tem 'auto'
                qualityMenu.innerHTML = '<p class="text-[11px] text-center text-gray-400 py-2">Qualidade Automática</p>';
                return;
            }

            qualityMenu.innerHTML = '<div class="text-[10px] font-bold text-gray-400 mb-1 px-2 uppercase tracking-wider">Qualidade</div>';
            levels.forEach(level => {
                const btn = document.createElement('button');
                let label = level;
                if (level === 'auto') label = manualQuality ? 'Automático' : 'Automático ✓';
                else if (level === 'hd2160') label = '4K';
                else if (level === 'hd1440') label = '1440p';
                else if (level === 'hd1080') label = '1080p';
                else if (level === 'hd720') label = '720p';
                else if (level === 'large') label = '480p';
                else if (level === 'medium') label = '360p';
                else if (level === 'small') label = '240p';
                else if (level === 'tiny') label = '144p';
                
                btn.textContent = label;
                if (level === current) btn.classList.add('active');
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const resumeTime = yminPlayer.getCurrentTime();
                    const wasMuted = yminPlayer.isMuted();
                    manualQuality = (level === 'auto') ? null : level;
                    qualityMenu.classList.remove('show');
                    
                    // Recarrega o player com a nova qualidade
                    if (currentVideoId && yminRoot) {
                        createYMin(yminRoot, currentVideoId, level === 'auto' ? null : level).then(() => {
                            // Espera o player estar pronto e pula para o tempo anterior
                            const waitForReady = setInterval(() => {
                                if (yminPlayer && yminPlayer.getPlayerState) {
                                    clearInterval(waitForReady);
                                    yminPlayer.seekTo(resumeTime, true);
                                    if (!wasMuted) yminPlayer.unMute();
                                    yminPlayer.playVideo();
                                    console.log("YMin: Player recarregado com qualidade:", level, "em", resumeTime, "segundos");
                                }
                            }, 100);
                        });
                    }
                });
                qualityMenu.appendChild(btn);
            });
          }

         function pctFromEvent(ev){
         const r = progress.getBoundingClientRect();
         const x = (ev.touches ? ev.touches[0].clientX : ev.clientX) - r.left;
         return clamp01(x / r.width);
         }
         function preview(p){ barEl.style.width = (fakeFromReal(p)*100).toFixed(2)+'%'; }
         function seekToPct(p){
         const dur = yminPlayer?.getDuration?.() || 0;
         if (dur>0) yminPlayer.seekTo(dur * clamp01(p), true);
         }
         function startScrub(ev){
         ev.preventDefault(); scrubbing = true; showControls(root);
         const p = pctFromEvent(ev); preview(p); seekToPct(p);
         window.addEventListener('mousemove', moveScrub);
         window.addEventListener('touchmove', moveScrub, {passive:false});
         window.addEventListener('mouseup', endScrub);
         window.addEventListener('touchend', endScrub);
         }
         function moveScrub(ev){
         ev.preventDefault();
         if(!scrubbing) return;
         const p = pctFromEvent(ev); preview(p); seekToPct(p);
         }
         function endScrub(ev){
         if(!scrubbing) return;
         scrubbing=false;
         const p = pctFromEvent(ev); preview(p); seekToPct(p);
         window.removeEventListener('mousemove', moveScrub);
         window.removeEventListener('touchmove', moveScrub);
         window.removeEventListener('mouseup', endScrub);
         window.removeEventListener('touchend', endScrub);
         showControls(root);
         }
         progress.addEventListener('mousedown', startScrub);
         progress.addEventListener('touchstart', startScrub, {passive:true});

         function loop(){
         cancelAnimationFrame(yminRaf);
         const tick=()=>{
           try{
           const cur=yminPlayer?.getCurrentTime?.()||0;
           const dur=yminPlayer?.getDuration?.()||0;
           if(dur>0){
             curEl.textContent = formatTime(cur);
             durEl.textContent = formatTime(dur);
             if(!scrubbing){
             const pReal = cur/dur;
             barEl.style.width = (fakeFromReal(pReal)*100).toFixed(2)+'%';
             }
           }
           }catch{}
           yminRaf=requestAnimationFrame(tick);
         };
         yminRaf=requestAnimationFrame(tick);
         }
        }
        /* =================================================================== */
        /* ====== FIM: TECNOLOGIA DO PLAYER (COPIADO DO YMin) ======== */
        /* =================================================================== */


        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            
            const allModulesData = <?php echo json_encode($modulos_com_aulas); ?>;
            const clienteEmail = "<?php echo htmlspecialchars($cliente_email); ?>";
            const currentProductId = "<?php echo htmlspecialchars($produto_id); ?>";
            const courseContainer = document.getElementById('course-container');
            const comentariosAtivos = courseContainer && courseContainer.dataset.comentariosAtivos === '1';
            const alunoEmail = courseContainer ? (courseContainer.dataset.alunoEmail || '') : '';
            const aulaFilesDirPublic = "<?php echo htmlspecialchars($aula_files_dir_public); ?>";
            
            if (!allModulesData || allModulesData.length === 0) return;

            const playerWrapper = document.getElementById('player-wrapper');
            // [INÍCIO DA MUDANÇA] Referências do Player
            const playerHost = document.getElementById('player-host'); // Novo container do player
            const initialPlaceholderHTML = playerHost.innerHTML; // Salva o placeholder inicial
            // [FIM DA MUDANÇA]
            
            const lessonTitle = document.getElementById('lesson-title');
            const lessonDescription = document.getElementById('lesson-description');
            const lessonListContainer = document.getElementById('lesson-list-container');
            const moduleCards = document.querySelectorAll('.module-card');
            const moduleTitleAside = document.getElementById('module-title-aside');
            const markAsCompleteBtn = document.getElementById('mark-as-complete-btn');
            const nextLessonBtn = document.getElementById('next-lesson-btn');
            
            let currentModuleId = null;
            let currentModuleIndex = null; // Índice do módulo ativo em allModulesData
            let currentLessonData = null; // Guarda os dados da aula atualmente carregada

            
            // [INÍCIO DA MUDANÇA] Função loadLesson atualizada para usar YMin
            function loadLesson(lesson) {
                // 1. Destrói qualquer player YMin anterior
                destroyYMin(); 

                if (!lesson) { // Reset player if no lesson
                    playerHost.innerHTML = initialPlaceholderHTML; // Restaura placeholder inicial
                    lucide.createIcons();
                    lessonTitle.textContent = 'Nenhuma aula selecionada';
                    lessonDescription.innerHTML = '<p>Selecione uma aula na lista ao lado.</p>';
                    markAsCompleteBtn.classList.add('hidden');
                    currentLessonData = null;
                    if (typeof toggleComentariosSection === 'function') toggleComentariosSection(false);
                    return;
                }
                
                // 2. Lida com aula bloqueada
                if (lesson.is_locked) {
                    playerHost.innerHTML = `<div class="w-full aspect-video bg-black flex flex-col items-center justify-center text-gray-500 rounded-xl">
                                                <i data-lucide="lock" class="w-16 h-16 text-gray-600 mb-4"></i>
                                                <p class="text-lg font-semibold">Aula Bloqueada</p>
                                                <p class="text-sm">Disponível em: ${lesson.available_at}</p>
                                            </div>`;
                    lucide.createIcons();
                    lessonTitle.textContent = 'Aula Bloqueada';
                    lessonDescription.innerHTML = `<p class="text-red-400 flex items-center"><i data-lucide="lock" class="w-5 h-5 mr-2"></i> Esta aula estará disponível em: ${lesson.available_at}.</p><p>Volte mais tarde para acessá-la!</p>`;
                    markAsCompleteBtn.classList.add('hidden');
                    currentLessonData = null;
                    updateNextLessonButton();
                    lucide.createIcons(); // Render the lock icon in the description
                    if (typeof toggleComentariosSection === 'function') toggleComentariosSection(false);
                    return;
                }

                currentLessonData = lesson;
                // Salva última aula assistida (continuar de onde parou) - apenas se desbloqueada
                if (lesson && !lesson.is_locked) {
                    fetch('/api/member_api?action=save_last_lesson', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ aula_id: lesson.id, produto_id: parseInt(currentProductId, 10) }),
                    credentials: 'same-origin'
                }).catch(function() {});
                }
                // Garante que currentModuleIndex aponta para o módulo desta aula (importante ao navegar por "Próxima Aula")
                for (let m = 0; m < allModulesData.length; m++) {
                    if ((allModulesData[m].aulas || []).some(a => a.id === lesson.id)) {
                        currentModuleIndex = m;
                        currentModuleId = allModulesData[m].modulo.id;
                        break;
                    }
                }

                // 3. Lógica de exibição: branch por origem_video (Fase 1: youtube, vimeo, self_hosted)
                const hasVideoContent = (lesson.tipo_conteudo === 'video' || lesson.tipo_conteudo === 'mixed') && lesson.url_video;
                const origem = (lesson.origem_video || 'youtube').toLowerCase();
                let videoId = null;
                let isShort = false;
                if (hasVideoContent && (origem === 'youtube' || origem === '')) {
                    const match = lesson.url_video.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/i);
                    if (match && match[1]) {
                        videoId = match[1];
                        isShort = /youtube\.com\/shorts\//i.test(lesson.url_video);
                    }
                }

                // 4. Carrega o Player conforme origem (YMin para YouTube; iframe para Vimeo; <video> para self_hosted)
                if (videoId) {
                    playerHost.innerHTML = '';
                    const playerDiv = document.createElement('div');
                    playerDiv.className = `ymin controls-hidden ${isShort ? 'vertical' : ''}`;
                    playerHost.appendChild(playerDiv);
                    createYMin(playerDiv, videoId);
                } else if (hasVideoContent && origem === 'vimeo') {
                    const vimeoMatch = lesson.url_video.match(/vimeo\.com\/(?:video\/)?(\d+)/i) || lesson.url_video.match(/player\.vimeo\.com\/video\/(\d+)/i);
                    const vimeoId = vimeoMatch && vimeoMatch[1] ? vimeoMatch[1] : null;
                    if (vimeoId) {
                        playerHost.innerHTML = '';
                        const wrap = document.createElement('div');
                        wrap.className = 'w-full aspect-video rounded-xl overflow-hidden bg-black';
                        const iframe = document.createElement('iframe');
                        iframe.src = 'https://player.vimeo.com/video/' + vimeoId + '?badge=0&autopause=0';
                        iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
                        iframe.setAttribute('allowfullscreen', '');
                        iframe.title = 'Vídeo Vimeo';
                        iframe.className = 'w-full h-full';
                        wrap.appendChild(iframe);
                        playerHost.appendChild(wrap);
                    } else {
                        playerHost.innerHTML = `<div class="w-full aspect-video bg-black flex flex-col items-center justify-center text-gray-500 rounded-xl"><p class="text-lg font-semibold">URL do Vimeo inválida.</p></div>`;
                        lucide.createIcons();
                    }
                } else if (hasVideoContent && origem === 'self_hosted') {
                    let src = (lesson.url_video || '').trim();
                    if (src && !src.startsWith('/')) src = '/' + src;
                    if (src && src.indexOf('uploads/') !== -1 && src.toLowerCase().indexOf('.mp4') !== -1) {
                        playerHost.innerHTML = '';
                        const wrap = document.createElement('div');
                        wrap.className = 'w-full aspect-video rounded-xl overflow-hidden bg-black';
                        const video = document.createElement('video');
                        video.controls = true;
                        video.className = 'w-full h-full';
                        video.setAttribute('playsinline', '');
                        video.src = src;
                        wrap.appendChild(video);
                        playerHost.appendChild(wrap);
                    } else {
                        playerHost.innerHTML = `<div class="w-full aspect-video bg-black flex flex-col items-center justify-center text-gray-500 rounded-xl"><p class="text-lg font-semibold">Vídeo self-hosted inválido.</p></div>`;
                        lucide.createIcons();
                    }
                } else {
                    const isTextOnly = lesson.tipo_conteudo === 'text';
                    const placeholderHtml = isTextOnly
                        ? `<div class="w-full aspect-video bg-black flex flex-col items-center justify-center text-gray-400 rounded-xl">
                        <i data-lucide="align-left" class="w-16 h-16 text-gray-600 mb-4"></i>
                        <p class="text-lg font-semibold">Conteúdo em texto</p>
                        <p class="text-sm">Leia o material na descrição abaixo.</p>
                    </div>`
                        : `<div class="w-full aspect-video bg-black flex flex-col items-center justify-center text-gray-500 rounded-xl">
                        <i data-lucide="video-off" class="w-16 h-16 text-gray-600 mb-4"></i>
                        <p class="text-lg font-semibold">Esta aula não contém vídeo.</p>
                        <p class="text-sm">Verifique os materiais de apoio abaixo.</p>
                    </div>`;
                    let coverImg = null;
                    // Banner (opcional): aceito para "Somente Arquivos", "Vídeo e Arquivos" e "Somente texto".
                    // Substitui o placeholder genérico por um banner personalizado (anúncio, aviso, identidade visual).
                    const tiposComBanner = ['files', 'mixed', 'text'];
                    if (tiposComBanner.includes(lesson.tipo_conteudo) && (lesson.lesson_cover_url || lesson.lesson_cover_path)) {
                        coverImg = lesson.lesson_cover_url || ('/' + (lesson.lesson_cover_path || '').replace(/^\/+/, ''));
                    }
                    if (coverImg) {
                        const wrap = document.createElement('div');
                        wrap.className = 'lesson-cover-wrap w-full rounded-xl';
                        const img = document.createElement('img');
                        img.src = coverImg;
                        img.alt = 'Banner da aula';
                        img.className = 'lesson-cover-img';
                        img.onerror = function() {
                            wrap.innerHTML = placeholderHtml;
                            if (typeof lucide !== 'undefined') lucide.createIcons();
                        };
                        wrap.appendChild(img);
                        playerHost.innerHTML = '';
                        playerHost.appendChild(wrap);
                    } else {
                        playerHost.innerHTML = placeholderHtml;
                    }
                    lucide.createIcons();
                }


                // 5. Carrega Título, Descrição e Arquivos (lógica original mantida)
                lessonTitle.textContent = lesson.titulo;

                // Descrição já vem sanitizada do PHP (HTML seguro ou texto com links)
                let descriptionHtml = (lesson.descricao && lesson.descricao.trim()) ? lesson.descricao : '<p class="text-gray-500">Esta aula não possui descrição.</p>';
                
                // Adicionar arquivos de apoio como botões CTA
                if ((lesson.tipo_conteudo === 'files' || lesson.tipo_conteudo === 'mixed') && lesson.files && lesson.files.length > 0) {
                    descriptionHtml += '<h4 class="text-lg font-bold text-white mt-6 mb-3">Materiais de Apoio</h4>';
                    descriptionHtml += '<div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="lesson-files-grid">';
                    const requireTerms = !!(lesson.require_download_terms);
                    const termText = (lesson.download_terms_text || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    lesson.files.forEach(file => {
                        const displayName = (file.nome_original && file.nome_original.trim()) ? file.nome_original.trim() : (file.nome_salvo || 'Download');
                        const safeName = displayName.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        if (requireTerms) {
                            descriptionHtml += `<button type="button" class="download-term-btn bg-green-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-green-700 transition duration-300 text-base flex items-center justify-center space-x-2 w-full" data-file-id="${file.id}" data-aula-id="${lesson.id}" data-file-name="${safeName}" data-term-text="${termText}"><i data-lucide="download" class="w-5 h-5 flex-shrink-0"></i><span class="truncate" title="${safeName}">${safeName}</span></button>`;
                        } else {
                            const downloadUrl = `/media?file_id=${file.id}&produto_id=${currentProductId}`;
                            descriptionHtml += `<a href="${downloadUrl}" target="_blank" rel="noopener noreferrer" class="bg-green-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-green-700 transition duration-300 text-base flex items-center justify-center space-x-2"><i data-lucide="download" class="w-5 h-5 flex-shrink-0"></i><span class="truncate" title="${safeName}">${safeName}</span></a>`;
                        }
                    });
                    descriptionHtml += '</div>'; // Close the grid div
                } else if ((lesson.tipo_conteudo === 'files' || lesson.tipo_conteudo === 'mixed') && (!lesson.files || lesson.files.length === 0)) {
                    descriptionHtml += '<p class="text-gray-500 mt-4">Nenhum material de apoio disponível para esta aula.</p>';
                }


                lessonDescription.innerHTML = descriptionHtml;
                lucide.createIcons(); // Re-render icons if new ones were added in descriptionHtml

                // 6. Highlight na aula ativa (lógica original mantida)
                document.querySelectorAll('.lesson-item').forEach(item => {
                    item.classList.toggle('active', item.dataset.lessonId == lesson.id);
                });

                // 7. Atualiza o botão "Marcar como Concluída" (lógica original mantida)
                updateMarkAsCompleteButton(lesson.concluida);
                // 8. Atualiza o botão "Próxima Aula"
                updateNextLessonButton();
                // 9. Comentários da aula
                if (typeof toggleComentariosSection === 'function') toggleComentariosSection(true, lesson.id);
            }
            // [FIM DA MUDANÇA] Função loadLesson

            // [INÍCIO DA MUDANÇA] Função de atualização do botão
            function updateMarkAsCompleteButton(isConcluida) {
                if (!markAsCompleteBtn || !currentLessonData || currentLessonData.is_locked) { 
                    markAsCompleteBtn.classList.add('hidden'); // Oculta se a aula estiver bloqueada ou nenhuma aula selecionada
                    return;
                }
                markAsCompleteBtn.classList.remove('hidden'); // Mostra o botão
                markAsCompleteBtn.disabled = false; // Garante que o botão está habilitado

                if (isConcluida) {
                    // Estado "Concluída" - Pronta para DESMARCAR (estilo vazado, igual "Próxima Aula")
                    markAsCompleteBtn.innerHTML = '<i data-lucide="x-square" class="w-5 h-5"></i><span>Desmarcar Conclusão</span>';
                    markAsCompleteBtn.classList.remove('border-green-500', 'text-green-500', 'hover:bg-green-500', 'hover:text-white', 'bg-green-600', 'hover:bg-green-700', 'bg-gray-600', 'cursor-not-allowed');
                    markAsCompleteBtn.classList.add('border-yellow-500', 'text-yellow-500', 'hover:bg-yellow-500', 'hover:text-white');
                } else {
                    // Estado "Não Concluída" - Pronta para MARCAR (estilo vazado, igual "Próxima Aula")
                    markAsCompleteBtn.innerHTML = '<i data-lucide="check-square" class="w-5 h-5"></i><span>Marcar como Concluída</span>';
                    markAsCompleteBtn.classList.remove('border-yellow-500', 'text-yellow-500', 'hover:bg-yellow-500', 'hover:text-white', 'bg-yellow-600', 'hover:bg-yellow-700', 'bg-gray-600', 'cursor-not-allowed');
                    markAsCompleteBtn.classList.add('border-green-500', 'text-green-500', 'hover:bg-green-500', 'hover:text-white');
                }
                lucide.createIcons(); // Renderiza os novos ícones (check-square ou x-square)
            }
            // [FIM DA MUDANÇA] Função de atualização do botão

            // Termo de aceite antes do download: delegação de eventos
            let pendingDownload = null;
            function isTermScrolledToBottom() {
                const el = document.getElementById('download-term-text');
                if (!el) return true;
                const sh = el.scrollHeight, ch = el.clientHeight, st = el.scrollTop;
                // Conteúdo curto (sem scroll): considera no final
                if (sh <= ch + 20) return true;
                // Mobile: threshold maior (40px) por diferenças de layout/arredondamento em iOS/Android
                const threshold = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ? 40 : 15;
                return st + ch >= sh - threshold;
            }
            function updateDownloadTermAcceptButton() {
                const acceptBtn = document.getElementById('download-term-accept');
                const checkbox = document.getElementById('download-term-checkbox');
                if (!acceptBtn || !checkbox) return;
                const ready = checkbox.checked && isTermScrolledToBottom();
                acceptBtn.disabled = !ready;
                // Oculta o botão até o usuário marcar "Li e concordo"
                if (ready) {
                    acceptBtn.classList.remove('hidden');
                } else {
                    acceptBtn.classList.add('hidden');
                }
            }
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.download-term-btn');
                if (!btn) return;
                e.preventDefault();
                const fileId = btn.dataset.fileId;
                const aulaId = btn.dataset.aulaId;
                const rawTerm = (btn.dataset.termText || '').replace(/&quot;/g,'"').replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>');
                const safeHtml = rawTerm.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
                pendingDownload = { file_id: parseInt(fileId,10), aula_id: parseInt(aulaId,10), produto_id: parseInt(currentProductId,10) };
                const modal = document.getElementById('download-term-modal');
                const textEl = document.getElementById('download-term-text');
                const checkbox = document.getElementById('download-term-checkbox');
                if (modal && textEl && checkbox) {
                    textEl.innerHTML = safeHtml || 'Este material é disponibilizado exclusivamente para uso pessoal e individual.';
                    checkbox.checked = false;
                    const acceptBtn = document.getElementById('download-term-accept');
                    if (acceptBtn) { acceptBtn.disabled = true; acceptBtn.textContent = 'Aceitar e baixar'; acceptBtn.classList.add('hidden'); }
                    modal.classList.remove('hidden');
                    textEl.scrollTop = 0;
                    // Mobile: layout pode demorar mais; touchend para recalc após scroll touch
                    textEl.onscroll = updateDownloadTermAcceptButton;
                    textEl.ontouchend = function() { requestAnimationFrame(updateDownloadTermAcceptButton); };
                    setTimeout(() => updateDownloadTermAcceptButton(), 50);
                    setTimeout(() => updateDownloadTermAcceptButton(), 200);
                }
            });
            const termCb = document.getElementById('download-term-checkbox');
            if (termCb) {
                termCb.addEventListener('change', updateDownloadTermAcceptButton);
                termCb.addEventListener('input', updateDownloadTermAcceptButton);
                termCb.addEventListener('click', function() { setTimeout(updateDownloadTermAcceptButton, 0); });
            }
            document.getElementById('download-term-cancel')?.addEventListener('click', function() {
                document.getElementById('download-term-modal')?.classList.add('hidden');
                pendingDownload = null;
            });
            document.getElementById('download-term-accept')?.addEventListener('click', async function() {
                const checkbox = document.getElementById('download-term-checkbox');
                if (!checkbox?.checked) return;
                if (!pendingDownload) return;
                const acceptBtn = document.getElementById('download-term-accept');
                if (acceptBtn) { acceptBtn.disabled = true; acceptBtn.textContent = 'Processando...'; }
                try {
                    const r = await fetch('/api/member_api?action=register_download_term_acceptance', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(pendingDownload),
                        credentials: 'same-origin'
                    });
                    const data = await r.json();
                    document.getElementById('download-term-modal')?.classList.add('hidden');
                    pendingDownload = null;
                    if (data.success && data.download_url) {
                        window.open(data.download_url, '_blank');
                    } else {
                        alert(data.error || 'Erro ao registrar aceite.');
                    }
                } catch (err) {
                    alert('Erro de conexão. Tente novamente.');
                } finally {
                    if (acceptBtn) { acceptBtn.disabled = false; acceptBtn.textContent = 'Aceitar e baixar'; }
                }
            });

            /** Retorna a próxima aula desbloqueada após a aula atual (no mesmo módulo ou no próximo). */
            function getNextLesson() {
                if (!currentLessonData || currentModuleIndex == null) return null;
                const moduleData = allModulesData[currentModuleIndex];
                if (!moduleData || !moduleData.aulas) return null;
                const aulas = moduleData.aulas;
                let found = false;
                for (let i = 0; i < aulas.length; i++) {
                    if (found && !aulas[i].is_locked) return aulas[i];
                    if (aulas[i].id === currentLessonData.id) found = true;
                }
                for (let m = currentModuleIndex + 1; m < allModulesData.length; m++) {
                    const mod = allModulesData[m];
                    if (mod.modulo.is_locked) continue;
                    for (let i = 0; i < (mod.aulas || []).length; i++) {
                        if (!mod.aulas[i].is_locked) return mod.aulas[i];
                    }
                }
                return null;
            }

            function updateNextLessonButton() {
                if (!nextLessonBtn) return;
                const next = getNextLesson();
                if (next && currentLessonData && !currentLessonData.is_locked) {
                    nextLessonBtn.classList.remove('hidden');
                    nextLessonBtn.disabled = false;
                } else {
                    nextLessonBtn.classList.add('hidden');
                    nextLessonBtn.disabled = true;
                }
                lucide.createIcons();
            }

            // Comentários nas aulas
            function toggleComentariosSection(show, aulaId) {
                const section = document.getElementById('comentarios-section');
                if (!section) return;
                if (!show || !comentariosAtivos) {
                    section.classList.add('hidden');
                    return;
                }
                section.classList.remove('hidden');
                if (aulaId) loadComentariosForAula(aulaId);
            }
            function loadComentariosForAula(aulaId) {
                const listEl = document.getElementById('comentarios-list');
                const formWrap = document.getElementById('comentarios-form-wrap');
                if (!listEl) return;
                listEl.innerHTML = '<p class="text-gray-500 text-sm">Carregando...</p>';
                if (formWrap) formWrap.style.display = comentariosAtivos ? 'block' : 'none';
                fetch(`/api/member_api?action=list_aula_comentarios&aula_id=${aulaId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            listEl.innerHTML = '<p class="text-red-400 text-sm">Erro ao carregar comentários.</p>';
                            return;
                        }
                        if (!data.comentarios_ativos) {
                            listEl.innerHTML = '';
                            if (formWrap) formWrap.style.display = 'none';
                            return;
                        }
                        const comentarios = data.comentarios || [];
                        if (comentarios.length === 0) {
                            listEl.innerHTML = '<p class="text-gray-500 text-sm">Nenhum comentário ainda. Seja o primeiro!</p>';
                        } else {
                            listEl.innerHTML = comentarios.map(c => {
                                const nome = (c.nome_aluno || c.aluno_email || 'Anônimo').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                const texto = (c.texto || '').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                                const dataStr = c.created_at ? new Date(c.created_at).toLocaleString('pt-BR') : '';
                                const resposta = (c.resposta_infoprodutor || '').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                                const respostaBlock = resposta ? `<div class="mt-3 pt-3 border-t border-gray-600"><p class="text-xs text-green-400 font-medium mb-1">Resposta do instrutor:</p><p class="text-gray-300 text-sm">${resposta}</p></div>` : '';
                                return `<div class="p-3 bg-gray-700 rounded-lg"><p class="font-medium text-white">${nome}</p><p class="text-gray-300 text-sm mt-1">${texto}</p><p class="text-xs text-gray-500 mt-2">${dataStr}</p>${respostaBlock}</div>`;
                            }).join('');
                        }
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    })
                    .catch(() => { listEl.innerHTML = '<p class="text-red-400 text-sm">Erro ao carregar.</p>'; });
            }

            function displayLessonsForModule(moduleIndex, lessonToSelect) {
                const moduleData = allModulesData[moduleIndex];
                if (!moduleData) return;

                currentModuleId = moduleData.modulo.id;
                currentModuleIndex = moduleIndex;

                // Highlight active module card
                moduleCards.forEach(card => {
                    card.classList.toggle('active', card.dataset.moduleId == currentModuleId);
                });
                
                moduleTitleAside.textContent = moduleData.modulo.titulo;
                lessonListContainer.innerHTML = ''; // Clear previous lessons

                if (moduleData.aulas.length === 0) {
                    lessonListContainer.innerHTML = '<p class="text-gray-400 px-2">Este módulo não possui aulas.</p>';
                    loadLesson(null); // Clear the player
                    return;
                }

                let firstAvailableLesson = null;

                moduleData.aulas.forEach(aula => {
                    const lessonButton = document.createElement('button');
                    let iconHtml = '';
                    let textClass = 'text-gray-300'; // Default class for unlocked, not completed lessons

                    if (aula.is_locked) {
                        lessonButton.className = 'lesson-item w-full text-left flex items-center space-x-3 p-3 rounded-lg locked';
                        iconHtml = `<i data-lucide="lock" class="w-5 h-5 flex-shrink-0 text-gray-500"></i>`;
                        textClass = 'text-gray-500'; // Make text dimmer for locked lessons
                    } else {
                        lessonButton.className = 'lesson-item w-full text-left flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-700 transition';
                        
                        // Determine icon(s) based on content type
                        let videoIcon = '';
                        let fileIcon = '';
                        const doneCls = aula.concluida ? 'text-green-500' : 'text-gray-500';

                        if (aula.tipo_conteudo === 'text') {
                            iconHtml = `<i data-lucide="align-left" class="w-5 h-5 flex-shrink-0 ${doneCls}"></i>`;
                        } else {
                        if (aula.tipo_conteudo === 'video' || aula.tipo_conteudo === 'mixed') {
                            videoIcon = `<i data-lucide="play-circle" class="w-5 h-5 flex-shrink-0 ${doneCls}"></i>`;
                        }
                        if (aula.tipo_conteudo === 'files' || aula.tipo_conteudo === 'mixed') {
                            fileIcon = `<i data-lucide="file-text" class="w-5 h-5 flex-shrink-0 ${doneCls}"></i>`;
                        }
                        iconHtml = videoIcon + (videoIcon && fileIcon ? '<span class="w-1"></span>' : '') + fileIcon;
                        }


                        if (aula.concluida) {
                            textClass = 'text-gray-400 line-through'; // [MUDANÇA] Mantém o line-through para concluídas
                        } else {
                             textClass = 'text-gray-300';
                             if (!firstAvailableLesson) { // Keep track of the first unlocked lesson
                                 firstAvailableLesson = aula;
                             }
                        }
                    }

                    lessonButton.dataset.lessonId = aula.id;
                    lessonButton.innerHTML = `
                        <div class="flex items-center space-x-1">
                            ${iconHtml}
                        </div>
                        <span class="${textClass}">${aula.titulo}</span>
                        ${aula.concluida && !aula.is_locked ? '<i data-lucide="check" class="w-4 h-4 text-green-500 ml-auto flex-shrink-0"></i>' : ''}
                        ${aula.is_locked ? `<span class="ml-auto text-xs text-gray-500">Disp. ${aula.available_at}</span>` : ''}
                    `;
                    
                    // [MUDANÇA] A aula é carregada ao clicar, mesmo se bloqueada (a loadLesson tratará o bloqueio)
                    lessonButton.addEventListener('click', () => loadLesson(aula));
                    
                    lessonListContainer.appendChild(lessonButton);
                });
                lucide.createIcons();
                
                // Auto-load: lessonToSelect (URL ?aula_id=), first unlocked, or first of module
                const toLoad = lessonToSelect || firstAvailableLesson || moduleData.aulas[0];
                loadLesson(toLoad);
            }
            
            // Inicialização: se ?aula_id= na URL, abrir módulo e aula correspondentes
            const initialAulaId = courseContainer && parseInt(courseContainer.dataset.initialAulaId || '0', 10);
            if (initialAulaId > 0) {
                for (let m = 0; m < allModulesData.length; m++) {
                    const found = (allModulesData[m].aulas || []).find(a => a.id == initialAulaId);
                    if (found && !found.is_locked) {
                        playerWrapper.classList.remove('hidden');
                        displayLessonsForModule(m, found);
                        playerWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        break;
                    }
                }
            }

            // Event listeners for module cards
            moduleCards.forEach(card => {
                card.addEventListener('click', () => {
                    // Only allow click if module is not locked
                    if (card.disabled) return; 

                    playerWrapper.classList.remove('hidden'); // Make the player section visible
                    
                    const moduleIndex = parseInt(card.dataset.moduleIndex, 10);
                    displayLessonsForModule(moduleIndex);

                    // Scroll to player
                    playerWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            // [INÍCIO DA MUDANÇA] Lógica de clique do botão "Marcar/Desmarcar"
            markAsCompleteBtn.addEventListener('click', async () => {
                // Checa se há uma aula carregada, se o botão está desabilitado (ex: durante uma chamada de API) ou se a aula está bloqueada
                if (!currentLessonData || markAsCompleteBtn.disabled || currentLessonData.is_locked) return;

                // Desabilita o botão temporariamente para evitar cliques duplos
                markAsCompleteBtn.disabled = true;

                const isCompleted = currentLessonData.concluida;
                const action = isCompleted ? 'unmark_lesson_complete' : 'mark_lesson_complete';
                const newConcluidaState = !isCompleted;

                try {
                    const response = await fetch(`/api/member_api?action=${action}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            aluno_email: clienteEmail,
                            aula_id: currentLessonData.id
                        })
                    });
                    const result = await response.json();

                    if (result.success) {
                        // 1. Atualiza o estado da aula atual
                        currentLessonData.concluida = newConcluidaState; 
                        
                        // 2. Atualiza o estado global (em allModulesData)
                        allModulesData.forEach(moduleItem => {
                            moduleItem.aulas.forEach(aula => {
                                if (aula.id === currentLessonData.id) {
                                    aula.concluida = newConcluidaState;
                                }
                            });
                        });

                        // 3. Atualiza o visual do botão
                        updateMarkAsCompleteButton(newConcluidaState);
                        
                        // 4. Re-renderiza a lista de aulas para refletir a mudança (ex: ícone, line-through)
                        const activeModuleIndex = Array.from(moduleCards).findIndex(card => card.classList.contains('active'));
                        if (activeModuleIndex !== -1) {
                            displayLessonsForModule(activeModuleIndex);
                        }

                        // 5. Atualiza a barra de progresso geral
                        updateOverallProgress();

                        // 6. Gamificação: exibir modal de conquistas se houver novas
                        if (result.novas_conquistas && result.novas_conquistas.length > 0 && typeof exibirNovasConquistas === 'function') {
                            exibirNovasConquistas(result.novas_conquistas);
                        }

                    } else {
                        console.error(`Erro ao ${action}: ` + (result.error || 'Erro desconhecido.'));
                        // Se a ação de desmarcar falhar, avisa o usuário (pois pode ser problema de backend)
                        if (action === 'unmark_lesson_complete') {
                            console.warn('Atenção: A ação "unmark_lesson_complete" falhou. Verifique se ela foi implementada no seu "member_api.php".');
                            // Não reverta o estado aqui, apenas re-habilite o botão
                        }
                    }
                } catch (error) {
                    console.error(`Erro de rede/API ao ${action}:`, error);
                } finally {
                    // Re-habilita o botão após a conclusão da API (com sucesso ou falha)
                    // A função updateMarkAsCompleteButton já faz isso, mas podemos garantir
                    if (currentLessonData && !currentLessonData.is_locked) {
                         markAsCompleteBtn.disabled = false;
                         // Garante que o botão está no estado correto caso a API falhe e não atualize
                         updateMarkAsCompleteButton(currentLessonData.concluida);
                    }
                }
            });
            // [FIM DA MUDANÇA] Lógica de clique do botão "Marcar/Desmarcar"

            nextLessonBtn.addEventListener('click', () => {
                const next = getNextLesson();
                if (!next) return;
                const nextModuleIndex = allModulesData.findIndex(m => (m.aulas || []).some(a => a.id === next.id));
                if (nextModuleIndex !== -1 && nextModuleIndex !== currentModuleIndex) {
                    displayLessonsForModule(nextModuleIndex, next);
                } else {
                    loadLesson(next);
                }
            });

            // Comentário: contador de caracteres e envio
            const comentarioTexto = document.getElementById('comentario-texto');
            const comentarioCharCount = document.getElementById('comentario-char-count');
            const comentarioSubmitBtn = document.getElementById('comentario-submit-btn');
            if (comentarioTexto && comentarioCharCount) {
                comentarioTexto.addEventListener('input', () => {
                    comentarioCharCount.textContent = comentarioTexto.value.length;
                });
            }
            if (comentarioSubmitBtn && comentarioTexto) {
                comentarioSubmitBtn.addEventListener('click', () => {
                    const texto = comentarioTexto.value.trim();
                    if (!texto) return;
                    if (!currentLessonData || !currentLessonData.id) return;
                    comentarioSubmitBtn.disabled = true;
                    comentarioSubmitBtn.textContent = 'Enviando...';
                    fetch('/api/member_api?action=create_aula_comentario', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ aula_id: currentLessonData.id, texto: texto, aluno_email: alunoEmail })
                    })
                    .then(r => r.json())
                    .then(data => {
                        comentarioSubmitBtn.disabled = false;
                        comentarioSubmitBtn.textContent = 'Enviar comentário';
                        if (data.success) {
                            comentarioTexto.value = '';
                            if (comentarioCharCount) comentarioCharCount.textContent = '0';
                            loadComentariosForAula(currentLessonData.id);
                            if (data.novas_conquistas && data.novas_conquistas.length > 0 && typeof exibirNovasConquistas === 'function') {
                                exibirNovasConquistas(data.novas_conquistas);
                            }
                            const feedbackEl = document.getElementById('comentarios-feedback');
                            if (feedbackEl) {
                                feedbackEl.classList.remove('hidden');
                                if (data.status === 'approved') {
                                    feedbackEl.className = 'mb-4 p-4 rounded-lg border border-green-500/50 bg-green-500/10 text-green-300 text-sm';
                                    feedbackEl.textContent = 'Comentário publicado com sucesso!';
                                } else {
                                    feedbackEl.className = 'mb-4 p-4 rounded-lg border border-amber-500/50 bg-amber-500/10 text-amber-200 text-sm';
                                    feedbackEl.textContent = 'Seu comentário foi recebido e será analisado. Você receberá uma resposta em breve.';
                                }
                                setTimeout(() => { feedbackEl.classList.add('hidden'); }, 5000);
                            }
                        } else {
                            alert(data.error || 'Erro ao enviar comentário.');
                        }
                    })
                    .catch(() => {
                        comentarioSubmitBtn.disabled = false;
                        comentarioSubmitBtn.textContent = 'Enviar comentário';
                        alert('Erro de conexão. Tente novamente.');
                    });
                });
            }

            function updateOverallProgress() {
                let currentTotalAulas = 0;
                let currentAulasConcluidas = 0;

                allModulesData.forEach(moduleItem => {
                    moduleItem.aulas.forEach(aula => {
                        // Only count UNLOCKED lessons for overall progress
                        if (!aula.is_locked) { 
                            currentTotalAulas++;
                            if (aula.concluida) {
                                currentAulasConcluidas++;
                            }
                        }
                    });
                });

                const newProgressPercent = currentTotalAulas > 0 ? Math.round((currentAulasConcluidas / currentTotalAulas) * 100) : 0;
                
                document.querySelector('#player-wrapper .font-bold.text-white').textContent = `${newProgressPercent}% Completo`;
                document.querySelector('#player-wrapper .bg-green-500').style.width = `${newProgressPercent}%`;
            }

            // Initial call to update progress bar on page load
            updateOverallProgress();

            // Gamificação: carregar conquistas e modal
            const gamificacaoAtivo = courseContainer && courseContainer.dataset.gamificacao === '1';
            const produtoIdGam = courseContainer ? (courseContainer.dataset.produtoId || '') : '';
            let filaConquistasModal = [];

            function showConquistaModalCelebracao(conquista) {
                const modal = document.getElementById('conquista-modal');
                if (!modal || !conquista) return;
                const badgeEl = document.getElementById('conquista-badge');
                const tituloEl = document.getElementById('conquista-titulo');
                const descEl = document.getElementById('conquista-descricao');
                const cupomWrap = document.getElementById('conquista-cupom-wrap');
                const cupomCodigoEl = document.getElementById('conquista-cupom-codigo');
                const cupomValidadeEl = document.getElementById('conquista-cupom-validade');
                const msgUrgenciaWrap = document.getElementById('conquista-msg-urgencia-wrap');
                const msgUrgenciaEl = document.getElementById('conquista-msg-urgencia');
                if (badgeEl) badgeEl.src = conquista.badge_url || '';
                if (badgeEl) badgeEl.alt = conquista.titulo || '';
                if (tituloEl) tituloEl.textContent = conquista.titulo || '';
                if (descEl) descEl.textContent = conquista.descricao || '';
                if (cupomWrap) cupomWrap.classList.add('hidden');
                if (msgUrgenciaWrap) msgUrgenciaWrap.classList.add('hidden');
                if (conquista.cupom_codigo && cupomWrap && cupomCodigoEl && cupomValidadeEl) {
                    cupomCodigoEl.textContent = conquista.cupom_codigo;
                    cupomValidadeEl.textContent = conquista.cupom_valido_ate ? 'Válido até ' + new Date(conquista.cupom_valido_ate).toLocaleDateString('pt-BR') : '';
                    cupomWrap.classList.remove('hidden');
                }
                if (conquista.mensagem_urgencia && msgUrgenciaWrap && msgUrgenciaEl) {
                    msgUrgenciaEl.textContent = conquista.mensagem_urgencia;
                    msgUrgenciaWrap.classList.remove('hidden');
                }
                modal.classList.remove('hidden');
                lucide.createIcons();
            }
            window.fecharModalConquistaCelebracao = function() {
                const modal = document.getElementById('conquista-modal');
                if (modal) modal.classList.add('hidden');
                if (filaConquistasModal.length > 0) {
                    const next = filaConquistasModal.shift();
                    showConquistaModalCelebracao(next);
                }
            };
            function exibirNovasConquistas(novas) {
                if (!novas || novas.length === 0) return;
                filaConquistasModal = [...novas];
                showConquistaModalCelebracao(filaConquistasModal.shift());
                if (typeof loadConquistas === 'function') loadConquistas();
            }
            function loadConquistas() {
                const grid = document.getElementById('conquistas-grid');
                if (!grid || !gamificacaoAtivo || !produtoIdGam) return;
                fetch('/api/member_api?action=check_conquistas', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ produto_id: parseInt(produtoIdGam, 10) })
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const todas = data.todas_desbloqueadas || [];
                    const novas = data.novas_conquistas || [];
                    if (novas.length > 0) exibirNovasConquistas(novas);
                    grid.innerHTML = todas.length === 0
                        ? '<span class="text-gray-500 text-sm">Complete aulas para desbloquear conquistas!</span>'
                        : todas.map(c => `<div class="flex flex-col items-center" title="${(c.descricao || c.titulo || '').replace(/"/g, '&quot;')}"><img src="${c.badge_url || ''}" alt="${(c.titulo || '').replace(/"/g, '&quot;')}" class="w-12 h-12 object-contain rounded-lg"><span class="text-xs text-gray-400 mt-1 max-w-[60px] truncate">${(c.titulo || '').replace(/</g, '&lt;')}</span></div>`).join('');
                })
                .catch(() => { grid.innerHTML = '<span class="text-gray-500 text-sm">Erro ao carregar.</span>'; });
            }
            if (gamificacaoAtivo && produtoIdGam) {
                loadConquistas();
            }
        });
    </script>
    <!-- Modal de Celebração de Conquista -->
    <div id="conquista-modal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/70" onclick="fecharModalConquistaCelebracao()"></div>
        <div class="relative flex items-center justify-center min-h-screen p-4">
            <div class="bg-gray-800 rounded-xl p-8 max-w-md w-full text-center border border-gray-600 shadow-2xl">
                <img id="conquista-badge" src="" alt="" class="w-24 h-24 mx-auto mb-4 object-contain">
                <h3 id="conquista-titulo" class="text-xl font-bold text-white mb-2"></h3>
                <p id="conquista-descricao" class="text-gray-400 mb-4"></p>
                <div id="conquista-cupom-wrap" class="hidden mb-4 p-4 bg-green-900/30 border border-green-600/50 rounded-lg">
                    <p class="text-sm text-green-300 mb-1">Seu cupom de desconto:</p>
                    <p id="conquista-cupom-codigo" class="text-xl font-mono font-bold text-green-400 tracking-wider"></p>
                    <p id="conquista-cupom-validade" class="text-xs text-gray-400 mt-1"></p>
                </div>
                <div id="conquista-msg-urgencia-wrap" class="hidden mb-4 p-3 bg-amber-900/30 border border-amber-600/50 rounded-lg text-left">
                    <p id="conquista-msg-urgencia" class="text-sm text-amber-200"></p>
                </div>
                <button type="button" onclick="fecharModalConquistaCelebracao()" class="bg-green-600 hover:bg-green-500 text-white font-bold py-2 px-6 rounded-lg transition">Continuar</button>
            </div>
        </div>
    </div>
    <!-- Modal: Termo de aceite antes do download -->
    <div id="download-term-modal" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full border border-gray-600 overflow-hidden">
            <div class="p-6">
                <h3 class="text-xl font-bold text-white mb-4">Termos de Uso do Material</h3>
                <div id="download-term-text" class="text-gray-300 text-sm whitespace-pre-wrap mb-4 max-h-48 overflow-y-auto" style="-webkit-overflow-scrolling:touch"></div>
                <label class="flex items-start gap-2 mb-4 cursor-pointer">
                    <input type="checkbox" id="download-term-checkbox" class="mt-1 h-4 w-4 text-green-600 rounded focus:ring-green-500">
                    <span class="text-sm text-gray-300">Li e concordo com os termos de uso do material</span>
                </label>
                <p class="text-xs text-gray-500 mb-4">Se preferir, você pode entrar em contato com o produtor para esclarecer qualquer dúvida antes de acessar o material.</p>
            </div>
            <div class="px-6 py-4 bg-gray-700 flex justify-end gap-3">
                <button type="button" id="download-term-cancel" class="px-4 py-2 text-gray-300 hover:text-white font-medium">Cancelar</button>
                <button type="button" id="download-term-accept" disabled class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-green-600">Aceitar e baixar</button>
            </div>
        </div>
    </div>

    <?php 
    $mp_path = __DIR__ . '/../includes/member_protection.php';
    if (file_exists($mp_path)) require_once $mp_path; 
    ?>
</body>
</html>
