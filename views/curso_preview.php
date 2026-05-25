<?php
require __DIR__ . '/../config/config.php';
if (file_exists(__DIR__ . '/../helpers/image_helper.php')) {
    require_once __DIR__ . '/../helpers/image_helper.php';
}
if (file_exists(__DIR__ . '/../helpers/html_sanitizer.php')) {
    require_once __DIR__ . '/../helpers/html_sanitizer.php';
}

// Protege a página, apenas usuários logados (admin) podem ver o preview
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /login");
    exit;
}

$mensagem_erro = '';
$curso = null;
$modulos_com_aulas = [];
$total_aulas = 0;
$aulas_concluidas = 0; // Para a preview, o progresso é 0
$progresso_percentual = 0;
$upload_dir = 'uploads/'; // Pasta onde as imagens estão salvas
$aula_files_dir_public = 'uploads/aula_files/'; // Caminho público para arquivos de aula

// Valida o ID do produto
if (!isset($_GET['produto_id']) || !is_numeric($_GET['produto_id'])) {
    $mensagem_erro = "ID do curso inválido.";
} else {
    $produto_id = (int)$_GET['produto_id'];

    try {
        // Busca o curso correspondente ao produto
        $stmt_curso = $pdo->prepare("
            SELECT c.* FROM cursos c
            JOIN produtos p ON c.produto_id = p.id
            WHERE p.id = ? AND p.tipo_entrega = 'area_membros'
        ");
        $stmt_curso->execute([$produto_id]);
        $curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);

        if (!$curso) {
            $mensagem_erro = "Curso não encontrado ou não está configurado como 'Área de Membros'.";
        } else {
            // SIMULAÇÃO: Para a preview, consideramos a "data de compra" como AGORA.
            // Isso permite que módulos/aulas com release_days = 0 apareçam liberados,
            // e os com release_days > 0 apareçam bloqueados com uma data futura.
            $simulated_purchase_date = new DateTime(); // Use new DateTime() for simulation
            $current_date = new DateTime(); // Current date for comparison

            // Busca os módulos do curso (inclui release_days)
            $stmt_modulos = $pdo->prepare("SELECT id, curso_id, titulo, imagem_capa_url, ordem, release_days FROM modulos WHERE curso_id = ? ORDER BY ordem ASC, id ASC");
            $stmt_modulos->execute([$curso['id']]);
            $modulos = $stmt_modulos->fetchAll(PDO::FETCH_ASSOC);

            // Para cada módulo, busca as aulas (inclui release_days, tipo_conteudo) e seus arquivos
            foreach ($modulos as $modulo) {
                // Calcula a data de liberação do módulo para a pré-visualização
                $module_release_date = (clone $simulated_purchase_date);
                $module_release_date->modify("+{$modulo['release_days']} days");
                $modulo['is_locked'] = ($current_date < $module_release_date);
                $modulo['available_at'] = $module_release_date->format('d/m/Y H:i');

                // Incluir tipo_conteudo e origem_video na consulta das aulas
                $aulas_cols = "id, modulo_id, titulo, url_video, descricao, ordem, release_days, tipo_conteudo";
                $chk_origem = @$pdo->query("SHOW COLUMNS FROM aulas LIKE 'origem_video'");
                if ($chk_origem && $chk_origem->rowCount() > 0) {
                    $aulas_cols .= ", origem_video";
                }
                $stmt_aulas = $pdo->prepare("SELECT $aulas_cols FROM aulas WHERE modulo_id = ? ORDER BY ordem ASC, id ASC");
                $stmt_aulas->execute([$modulo['id']]);
                $aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);
                
                $total_aulas += count($aulas);
                
                $aulas_com_status = [];
                foreach ($aulas as $aula) {
                    // Calcula a data de liberação da aula para a pré-visualização
                    $lesson_release_date = (clone $simulated_purchase_date);
                    $lesson_release_date->modify("+{$aula['release_days']} days");
                    $aula['is_locked'] = ($current_date < $lesson_release_date);
                    $aula['available_at'] = $lesson_release_date->format('d/m/Y H:i');

                    // Sanitizar descrição para exibição segura (HTML do Quill)
                    $desc_raw = $aula['descricao'] ?? '';
                    if ($desc_raw !== '' && function_exists('sanitize_lesson_html')) {
                        $aula['descricao'] = sanitize_lesson_html($desc_raw);
                    }

                    // NOVO: Busca arquivos da aula
                    $stmt_files = $pdo->prepare("SELECT id, nome_original, nome_salvo FROM aula_arquivos WHERE aula_id = ? ORDER BY ordem ASC, id ASC");
                    $stmt_files->execute([$aula['id']]);
                    $aula['files'] = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

                    $aulas_com_status[] = $aula;
                }

                $modulos_com_aulas[] = [
                    'modulo' => $modulo,
                    'aulas' => $aulas_com_status
                ];
            }
            if ($total_aulas > 0) {
                // Para o preview, o progresso é sempre 0
                $progresso_percentual = 0;
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
    <title>Preview: <?php echo htmlspecialchars($curso['titulo'] ?? 'Curso'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .prose { --tw-prose-body: #d1d5db; --tw-prose-headings: #f9fafb; --tw-prose-links: #2DD05E; } /* Estilos para o texto da descrição */
        .module-card.active { border-color: #2DD05E; box-shadow: 0 0 15px #2DD05E; transform: scale(1.05); }
        .lesson-item.active { background-color: #289b4aff; color: #ffedd5; font-weight: 600; }
        .lesson-item.active .lucide-play-circle { color: #ffffff; }
        .aspect-video { aspect-ratio: 16 / 9; }
        .module-card.disabled, .lesson-item.locked { 
            cursor: not-allowed; 
            opacity: 0.6; 
        }
        .module-card.disabled:hover, .lesson-item.locked:hover {
            border-color: #2d3748; /* Mantém a cor da borda padrão ou similar ao bloqueado */
            box-shadow: none;
            transform: none;
            background-color: #2d3748;
        }
        .lesson-item.locked { 
            background-color: #2d3748; /* Mais escuro para indicar bloqueio */
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

        /* Menus de Velocidade e Qualidade (igual à Área de Membros) */
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
        button.module-card {
            -webkit-appearance: none !important;
            appearance: none !important;
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
        }
        button.module-card:disabled { cursor: not-allowed; }
    </style>
</head>
<body class="bg-gray-900 text-gray-200 antialiased">
    
    <?php if ($mensagem_erro): ?>
        <div class="flex h-screen items-center justify-center p-8">
            <div class="bg-red-900 border border-red-700 text-red-200 px-6 py-4 rounded-lg text-center max-w-lg">
                <p class="font-bold text-lg">Ocorreu um Erro</p>
                <p><?php echo $mensagem_erro; ?></p>
                 <a href="/index?pagina=area_membros" class="mt-4 inline-block bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 transition">Voltar</a>
            </div>
        </div>
    <?php elseif (!$curso): ?>
        <div class="flex h-screen items-center justify-center p-8">
             <div class="bg-gray-800 border border-gray-700 text-gray-300 px-6 py-4 rounded-lg text-center">
                <p>Carregando...</p>
            </div>
        </div>
    <?php else: ?>
    <div id="course-container" class="min-h-screen">
        <!-- Banner do Topo -->
        <header class="relative h-64 md:h-80 bg-gray-800 bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($curso['banner_url'] ?? ''); ?>')">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/70 to-transparent"></div>
            <div class="relative h-full flex flex-col justify-end p-6 md:p-10 max-w-7xl mx-auto">
                 <a href="/index?pagina=gerenciar_curso&produto_id=<?php echo $produto_id; ?>" class="absolute top-4 right-4 bg-black/50 text-white font-semibold py-2 px-4 rounded-lg hover:bg-black/80 transition duration-300 flex items-center space-x-2 text-sm z-10">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Voltar ao Gerenciador</span>
                </a>
                <h1 class="text-3xl md:text-5xl font-extrabold text-white drop-shadow-lg"><?php echo htmlspecialchars($curso['titulo']); ?></h1>
                <p class="mt-2 text-lg text-gray-300 max-w-2xl drop-shadow-md"><?php echo htmlspecialchars($curso['descricao']); ?></p>
            </div>
        </header>

        <main class="max-w-7xl mx-auto p-4 md:p-8 w-full">
            <?php if (empty($modulos_com_aulas) || $total_aulas === 0): ?>
                <div class="bg-gray-800 border border-gray-700 p-8 rounded-lg text-center text-gray-400">
                    <i data-lucide="video-off" class="mx-auto w-16 h-16 text-gray-600"></i>
                    <p class="mt-4 font-semibold text-lg text-gray-200">Este curso ainda não tem conteúdo.</p>
                    <p>Adicione módulos e aulas no gerenciador para visualizar a área de membros.</p>
                </div>
            <?php else: ?>

                <!-- Player e Aulas (Oculto por padrão) -->
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
                    </div>

                    <!-- Player e Lista de Aulas -->
                    <div id="player-section" class="flex flex-col lg:flex-row gap-8 mb-12">
                        <!-- Coluna Esquerda: Player e Detalhes -->
                        <div class="lg:w-2/3 w-full">

                            <!-- [INÍCIO DA MUDANÇA] Container do Player YMin -->
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
                            $total_lessons = is_array($item['aulas'] ?? null) ? count($item['aulas']) : 0;
                            $module_progress_percent = 0; // Preview: progresso sempre 0
                            $is_module_locked = $module['is_locked'];
                            $module_button_classes = "module-card group relative flex flex-col rounded-lg overflow-hidden border-2 border-gray-700 bg-transparent p-0 m-0 hover:border-green-500 focus:outline-none focus:ring-4 focus:ring-green-500/50 transition-all duration-300 text-left appearance-none";
                            $module_button_classes .= $is_module_locked ? ' opacity-50 cursor-not-allowed' : '';
                            ?>
                            <button class="<?php echo $module_button_classes; ?>" 
                                    data-module-id="<?php echo $module['id']; ?>" 
                                    data-module-index="<?php echo $index; ?>"
                                    <?php echo $is_module_locked ? 'disabled' : ''; ?>
                                    >
                                <div class="relative aspect-[2/3] bg-gray-700 overflow-hidden">
                                    <?php 
                                    $imagem_capa = !empty($module['imagem_capa_url'])
                                        ? resolve_product_image_url_protected($module['imagem_capa_url'], $upload_dir ?? 'uploads/', $produto_id)
                                        : '';
                                    $locked_cap = $is_module_locked ? 'grayscale brightness-75 contrast-125' : '';
                                    ?>
                                    <?php if (!empty($imagem_capa)): ?>
                                        <img src="<?php echo htmlspecialchars($imagem_capa); ?>" alt="Capa do <?php echo htmlspecialchars($module['titulo']); ?>" class="absolute inset-0 w-full h-full object-cover transition-all duration-300 group-hover:scale-110 block <?php echo $locked_cap; ?>">
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
                                <div class="px-4 pt-2">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-[11px] text-white/70"><?php echo (int)$module_progress_percent; ?>%</span>
                                    </div>
                                    <div class="w-full h-[2px] bg-white/10 rounded-full overflow-hidden">
                                        <div class="h-[2px] rounded-full transition-all duration-300" style="width: <?php echo (int)$module_progress_percent; ?>%; background-color: #2DD05E;"></div>
                                    </div>
                                </div>
                                <div class="p-4 pt-3 leading-normal">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="text-sm font-medium text-white/90 leading-snug"><?php echo htmlspecialchars($module['titulo']); ?></h4>
                                    </div>
                                    <?php if ($is_module_locked): ?>
                                        <span class="text-xs text-red-400 flex items-center mt-2"><i data-lucide="lock" class="w-4 h-4 mr-1"></i> Disponível em: <?php echo htmlspecialchars($module['available_at']); ?></span>
                                    <?php else: ?>
                                        <div class="flex items-center justify-between mt-2">
                                            <span class="text-xs text-green-300"><?php echo $total_lessons; ?> aulas</span>
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
        const ICONS = { 
          back5: "https://iili.io/KCUAyMJ.png", 
          fwd5: "https://iili.io/KCU5QhF.png", 
          play: "https://iili.io/KCUYGS4.png", 
          fs: "https://iili.io/KCUaDBe.png",
          settings: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z'%3E%3C/path%3E%3Ccircle cx='12' cy='12' r='3'%3E%3C/circle%3E%3C/svg%3E"
        };
        const HIDE_DELAY_MS = 2200;
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
        function mountYMinHTML(root){
         const mountId='yt-mount-'+Math.random().toString(36).slice(2,8);
         root.innerHTML=`
         <div class="frame">
           <div class="clickzone" aria-hidden="true"></div>
           <div id="${mountId}"></div>
           <div class="veil" aria-hidden="true"></div>
           <div class="overlay start"><div class="cover"><img class="icon" src="${ICONS.play}" alt="Play"></div></div>
           <div class="overlay paused" hidden><div class="cover"><img class="icon" src="${ICONS.play}" alt="Play"></div></div>
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
           <button class="btn back5" type="button" aria-label="Voltar 5 segundos" title="Voltar 5s"><img src="${ICONS.back5}" alt="Voltar 5s"></button>
           <button class="btn fwd5" type="button" aria-label="Avançar 5 segundos" title="Avançar 5s"><img src="${ICONS.fwd5}" alt="Avançar 5s"></button>
           <button class="btn speed-btn" type="button" aria-label="Velocidade de reprodução" title="Velocidade de reprodução (S)"><span style="font-size: 14px; font-weight: 600;">1x</span></button>
           <button class="btn quality-btn" type="button" aria-label="Qualidade do vídeo" title="Qualidade do vídeo (Q)"><span style="font-size: 12px; font-weight: 600;">Auto</span></button>
           <button class="btn fsbtn" type="button" aria-label="Tela cheia" title="Tela cheia"><img src="${ICONS.fs}" alt="Tela cheia"></button>
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

          settingsBtn.addEventListener('click', (e)=>{
            e.stopPropagation();
            showControls(root);
            const isVisible = qualityMenu.style.display === 'flex';
            qualityMenu.style.display = isVisible ? 'none' : 'flex';
            if (!isVisible) updateQualityMenu();
          });

          document.addEventListener('click', () => { if(qualityMenu) qualityMenu.style.display = 'none'; });

          function updateQualityMenu() {
            if (!yminPlayer || !yminPlayer.getAvailableQualityLevels) {
                console.error("YMin: Player não pronto ou API indisponível");
                return;
            }
            const levels = yminPlayer.getAvailableQualityLevels();
            const current = manualQuality || yminPlayer.getPlaybackQuality();
            console.log("YMin: Níveis disponíveis:", levels, "Qualidade atual (API):", yminPlayer.getPlaybackQuality(), "Qualidade Manual:", manualQuality);
            
            if (!levels || levels.length <= 1) {
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
            const nextLessonBtn = document.getElementById('next-lesson-btn');
            
            let currentModuleId = null;
            let currentModuleIndex = null;
            let currentLessonData = null;

            // REMOVIDO: function getYoutubeEmbedUrl(url)
            
            // Descarrega iframes antes de remover (evita "executou uma vez e não mais")
            function unloadIframes(container) {
                if (!container) return;
                container.querySelectorAll('iframe').forEach(iframe => {
                    try { iframe.src = 'about:blank'; } catch(e) {}
                });
            }

            // [INÍCIO DA MUDANÇA] Função loadLesson atualizada para usar YMin
            function loadLesson(lessonData) {
                 // 1. Destrói qualquer player YMin anterior
                destroyYMin();
                // 1b. Descarrega iframes (Vimeo, URL externa, embed) para permitir recarregar
                unloadIframes(playerHost);

                if (!lessonData) { // Reset player if no lesson
                    playerHost.innerHTML = initialPlaceholderHTML; // Restaura placeholder inicial
                    lucide.createIcons();
                    lessonTitle.textContent = 'Nenhuma aula selecionada';
                    lessonDescription.innerHTML = '<p>Selecione uma aula na lista ao lado.</p>';
                    currentLessonData = null;
                    if (typeof updateNextLessonButton === 'function') updateNextLessonButton();
                    return;
                }

                // 2. Lida com aula bloqueada (simulação)
                if (lessonData.is_locked) {
                    playerHost.innerHTML = `<div class="w-full aspect-video bg-black flex flex-col items-center justify-center text-gray-500 rounded-xl">
                                                <i data-lucide="lock" class="w-16 h-16 text-gray-600 mb-4"></i>
                                                <p class="text-lg font-semibold">Aula Bloqueada (Preview)</p>
                                                <p class="text-sm">Disponível em: ${lessonData.available_at}</p>
                                            </div>`;
                    lucide.createIcons();
                    lessonTitle.textContent = 'Aula Bloqueada';
                    lessonDescription.innerHTML = `<p class="text-red-400 flex items-center"><i data-lucide="lock" class="w-5 h-5 mr-2"></i> Esta aula estará disponível em: ${lessonData.available_at}.</p>`;
                    lucide.createIcons(); // Render the lock icon in the description
                    return;
                }

                // 3. Lógica de exibição: branch por origem_video (Fase 1: youtube, vimeo, self_hosted) — espelha member_course_view
                const hasVideo = (lessonData.tipo_conteudo === 'video' || lessonData.tipo_conteudo === 'mixed') && lessonData.url_video;
                const origem = (lessonData.origem_video || 'youtube').toLowerCase();
                let videoId = null;
                let isShort = false;
                if (hasVideo && (origem === 'youtube' || origem === '')) {
                    const match = lessonData.url_video.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/i);
                    if (match && match[1]) { videoId = match[1]; isShort = /youtube\.com\/shorts\//i.test(lessonData.url_video); }
                }

                // 4. Carrega o player conforme origem
                playerHost.innerHTML = '';
                const wrap = document.createElement('div');
                wrap.className = 'w-full aspect-video rounded-xl overflow-hidden bg-black';
                if (videoId) {
                    const playerDiv = document.createElement('div');
                    playerDiv.className = `ymin controls-hidden ${isShort ? 'vertical' : ''}`;
                    wrap.appendChild(playerDiv);
                    createYMin(playerDiv, videoId);
                } else if (hasVideo && origem === 'vimeo') {
                    const vimeoMatch = lessonData.url_video.match(/vimeo\.com\/(?:video\/)?(\d+)/i) || lessonData.url_video.match(/player\.vimeo\.com\/video\/(\d+)/i);
                    const vimeoId = vimeoMatch && vimeoMatch[1] ? vimeoMatch[1] : null;
                    if (vimeoId) {
                        const iframe = document.createElement('iframe');
                        iframe.src = 'https://player.vimeo.com/video/' + vimeoId + '?badge=0&autopause=0';
                        iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
                        iframe.setAttribute('allowfullscreen', '');
                        iframe.title = 'Vídeo Vimeo';
                        iframe.className = 'w-full h-full';
                        wrap.appendChild(iframe);
                    } else {
                        wrap.innerHTML = '<div class="w-full h-full flex items-center justify-center text-gray-500"><p>URL do Vimeo inválida.</p></div>';
                    }
                } else if (hasVideo && origem === 'self_hosted') {
                    let src = (lessonData.url_video || '').trim();
                    if (src && !src.startsWith('/')) src = '/' + src;
                    if (src && src.indexOf('uploads/') !== -1 && src.toLowerCase().indexOf('.mp4') !== -1) {
                        const video = document.createElement('video');
                        video.controls = true;
                        video.className = 'w-full h-full';
                        video.setAttribute('playsinline', '');
                        video.src = src;
                        wrap.appendChild(video);
                    } else {
                        wrap.innerHTML = '<div class="w-full h-full flex items-center justify-center text-gray-500"><p>Vídeo self-hosted inválido.</p></div>';
                    }
                } else if (hasVideo) {
                    wrap.innerHTML = '<div class="w-full h-full flex items-center justify-center text-gray-500"><p>Vídeo indisponível.</p></div>';
                } else {
                    const isTextOnly = lessonData.tipo_conteudo === 'text';
                    wrap.innerHTML = isTextOnly
                        ? '<div class="w-full h-full flex flex-col items-center justify-center text-gray-400"><i data-lucide="align-left" class="w-16 h-16 text-gray-600 mb-4"></i><p class="text-lg font-semibold">Conteúdo em texto</p><p class="text-sm">Leia o material na descrição abaixo.</p></div>'
                        : '<div class="w-full h-full flex flex-col items-center justify-center text-gray-500"><i data-lucide="video-off" class="w-16 h-16 text-gray-600 mb-4"></i><p class="text-lg font-semibold">Esta aula não contém vídeo.</p><p class="text-sm">Verifique os materiais de apoio abaixo.</p></div>';
                }
                playerHost.appendChild(wrap);
                lucide.createIcons();

                // 5. Carrega Título, Descrição e Arquivos (descrição já vem HTML do Quill, sanitizada no PHP)
                lessonTitle.textContent = lessonData.titulo;

                let descriptionHtml = (lessonData.descricao && lessonData.descricao.trim()) ? lessonData.descricao : '<p class="text-gray-500">Esta aula não possui descrição.</p>';
                
                // NOVO: Adicionar arquivos de apoio como botões CTA
                if ((lessonData.tipo_conteudo === 'files' || lessonData.tipo_conteudo === 'mixed') && lessonData.files && lessonData.files.length > 0) {
                    descriptionHtml += '<h4 class="text-lg font-bold text-white mt-6 mb-3">Materiais de Apoio</h4>';
                    descriptionHtml += '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">'; // Responsive grid container
                    lessonData.files.forEach(file => {
                        const filePath = `${aulaFilesDirPublic}${file.nome_salvo}`;
                        descriptionHtml += `
                            <a href="${filePath}" target="_blank" class="bg-green-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-green-700 transition duration-300 text-base flex items-center justify-center space-x-2">
                                <i data-lucide="download" class="w-5 h-5 flex-shrink-0"></i>
                                <span>${file.nome_original}</span>
                            </a>
                        `;
                    });
                    descriptionHtml += '</div>'; // Close the grid div
                } else if ((lessonData.tipo_conteudo === 'files' || lessonData.tipo_conteudo === 'mixed') && (!lessonData.files || lessonData.files.length === 0)) {
                    descriptionHtml += '<p class="text-gray-500 mt-4">Nenhum material de apoio disponível para esta aula.</p>';
                }


                lessonDescription.innerHTML = descriptionHtml;
                lucide.createIcons(); // Re-render icons if new ones were added in descriptionHtml

                // 6. Highlight na aula ativa
                document.querySelectorAll('.lesson-item').forEach(item => {
                    item.classList.toggle('active', item.dataset.lessonId == lessonData.id);
                });
                // 7. Atualiza o botão "Próxima Aula"
                if (typeof updateNextLessonButton === 'function') updateNextLessonButton();
            }
            // [FIM DA MUDANÇA] Função loadLesson

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
                
                let firstAvailableLesson = null; // Track the first unlocked lesson

                moduleData.aulas.forEach(aula => {
                    const lessonButton = document.createElement('button');
                    let iconHtml = '';
                    let textClass = 'text-gray-300';

                    if (aula.is_locked) {
                        lessonButton.className = 'lesson-item w-full text-left flex items-center space-x-3 p-3 rounded-lg locked';
                        iconHtml = `<i data-lucide="lock" class="w-5 h-5 flex-shrink-0 text-gray-500"></i>`;
                        textClass = 'text-gray-500'; // Make text dimmer for locked lessons
                    } else {
                        lessonButton.className = 'lesson-item w-full text-left flex items-center space-x-3 p-3 rounded-lg text-gray-300 hover:bg-gray-700 transition';
                        
                        let videoIcon = '';
                        let fileIcon = '';

                        if (aula.tipo_conteudo === 'text') {
                            iconHtml = `<i data-lucide="align-left" class="w-5 h-5 text-gray-500 flex-shrink-0"></i>`;
                        } else {
                        if (aula.tipo_conteudo === 'video' || aula.tipo_conteudo === 'mixed') {
                            videoIcon = `<i data-lucide="play-circle" class="w-5 h-5 text-gray-500 flex-shrink-0"></i>`;
                        }
                        if (aula.tipo_conteudo === 'files' || aula.tipo_conteudo === 'mixed') {
                            fileIcon = `<i data-lucide="file-text" class="w-5 h-5 text-gray-500 flex-shrink-0"></i>`;
                        }
                        iconHtml = videoIcon + (videoIcon && fileIcon ? '<span class="w-1"></span>' : '') + fileIcon;
                        }


                        if (!firstAvailableLesson) { // Keep track of the first unlocked lesson
                            firstAvailableLesson = aula;
                        }
                    }

                    lessonButton.dataset.lessonId = aula.id;
                    lessonButton.innerHTML = `
                        <div class="flex items-center space-x-1">
                            ${iconHtml}
                        </div>
                        <span class="${textClass}">${aula.titulo}</span>
                        ${aula.is_locked ? `<span class="ml-auto text-xs text-gray-500">Disp. ${aula.available_at}</span>` : ''}
                    `;
                    lessonButton.addEventListener('click', () => loadLesson(aula));
                    lessonListContainer.appendChild(lessonButton);
                });
                lucide.createIcons();
                
                if (lessonToSelect && moduleData.aulas.some(a => a.id === lessonToSelect.id)) {
                    loadLesson(lessonToSelect);
                } else {
                    loadLesson(firstAvailableLesson || moduleData.aulas[0]);
                }
            }
            
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

            // Event listeners for module cards
            moduleCards.forEach(card => {
                card.addEventListener('click', () => {
                    // Only allow click if module is not disabled
                    if (card.disabled) return;

                    playerWrapper.classList.remove('hidden'); // Make the player section visible
                    
                    const moduleIndex = parseInt(card.dataset.moduleIndex, 10);
                    displayLessonsForModule(moduleIndex);

                    // Scroll to player
                    playerWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        });
    </script>
</body>
</html>
