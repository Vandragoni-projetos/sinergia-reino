<?php
/**
 * Helper de gamificação: verifica e desbloqueia conquistas do aluno
 */

/**
 * Calcula progresso do aluno no curso (aulas desbloqueadas e concluídas)
 * @param PDO $pdo
 * @param string $aluno_email
 * @param int $curso_id
 * @param string $data_concessao
 * @return array ['aulas_concluidas' => int, 'progresso_percentual' => int, 'modulos_progresso' => [modulo_id => ['total' => int, 'concluidas' => int]]]
 */
function calcular_progresso_gamificacao($pdo, $aluno_email, $curso_id, $data_concessao) {
    $data_obj = new DateTime($data_concessao);
    $hoje = new DateTime();
    $dias_desde_compra = $data_obj->diff($hoje)->days;

    $stmt_aulas = $pdo->prepare("
        SELECT a.id, a.modulo_id, a.release_days
        FROM aulas a
        INNER JOIN modulos m ON a.modulo_id = m.id
        WHERE m.curso_id = ?
    ");
    $stmt_aulas->execute([$curso_id]);
    $aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);

    $aulas_concluidas = 0;
    $total_aulas = 0;
    $modulos_progresso = [];

    foreach ($aulas as $aula) {
        $desbloqueada = ($aula['release_days'] <= $dias_desde_compra);
        if (!$desbloqueada) continue;

        $total_aulas++;
        $modulo_id = $aula['modulo_id'];
        if (!isset($modulos_progresso[$modulo_id])) {
            $modulos_progresso[$modulo_id] = ['total' => 0, 'concluidas' => 0];
        }
        $modulos_progresso[$modulo_id]['total']++;

        $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM aluno_progresso WHERE LOWER(TRIM(aluno_email)) = LOWER(TRIM(?)) AND aula_id = ?");
        $stmt_p->execute([$aluno_email, $aula['id']]);
        if ($stmt_p->fetchColumn() > 0) {
            $aulas_concluidas++;
            $modulos_progresso[$modulo_id]['concluidas']++;
        }
    }

    $progresso_percentual = $total_aulas > 0 ? round(($aulas_concluidas / $total_aulas) * 100) : 0;

    return [
        'aulas_concluidas' => $aulas_concluidas,
        'progresso_percentual' => $progresso_percentual,
        'modulos_progresso' => $modulos_progresso
    ];
}

/**
 * Verifica se o aluno desbloqueou alguma conquista e retorna as novas
 * @param PDO $pdo
 * @param string $aluno_email
 * @param int $curso_id
 * @param int $produto_id
 * @param array $contexto ['data_concessao' => string, 'acabou_marcar_aula' => bool, 'acabou_comentar' => bool]
 * @return array ['novas_conquistas' => [...], 'todas_desbloqueadas' => [...]]
 */
function verificar_conquistas_aluno($pdo, $aluno_email, $curso_id, $produto_id, $contexto) {
    $novas_conquistas = [];
    $todas_desbloqueadas = [];
    if (file_exists(__DIR__ . '/badge_helper.php')) require_once __DIR__ . '/badge_helper.php';

    $chk = @$pdo->query("SHOW TABLES LIKE 'curso_gamificacao'");
    if (!$chk || $chk->rowCount() === 0) {
        return ['novas_conquistas' => [], 'todas_desbloqueadas' => []];
    }

    $stmt_g = $pdo->prepare("SELECT habilitado FROM curso_gamificacao WHERE curso_id = ?");
    $stmt_g->execute([$curso_id]);
    $gam = $stmt_g->fetch(PDO::FETCH_ASSOC);
    if (!$gam || !($gam['habilitado'] ?? 0)) {
        return ['novas_conquistas' => [], 'todas_desbloqueadas' => []];
    }

    $data_concessao = $contexto['data_concessao'] ?? date('Y-m-d H:i:s');
    $progresso = calcular_progresso_gamificacao($pdo, $aluno_email, $curso_id, $data_concessao);
    $aulas_concluidas = $progresso['aulas_concluidas'];
    $progresso_percentual = $progresso['progresso_percentual'];
    $modulos_progresso = $progresso['modulos_progresso'];

    $stmt_curso = $pdo->prepare("SELECT certificado_habilitado, certificado_conclusao_minima FROM cursos WHERE id = ?");
    $stmt_curso->execute([$curso_id]);
    $curso_row = $stmt_curso->fetch(PDO::FETCH_ASSOC);
    $certificado_minima = (int)($curso_row['certificado_conclusao_minima'] ?? 100);
    $certificado_habilitado = (int)($curso_row['certificado_habilitado'] ?? 0);

    $chk_comentarios = @$pdo->query("SHOW TABLES LIKE 'aula_comentarios'");
    $tem_comentario = false;
    if ($chk_comentarios && $chk_comentarios->rowCount() > 0 && !empty($contexto['acabou_comentar'])) {
        $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM aula_comentarios WHERE LOWER(TRIM(aluno_email)) = LOWER(TRIM(?))");
        $stmt_c->execute([$aluno_email]);
        $tem_comentario = $stmt_c->fetchColumn() > 0;
    }

    $chk_cols = @$pdo->query("SHOW COLUMNS FROM curso_conquistas LIKE 'recompensa_tipo'");
    $tem_recompensa = $chk_cols && $chk_cols->rowCount() > 0;
    $cols_conq = "id, titulo, descricao, gatilho_tipo, gatilho_valor, modulo_id, badge_url";
    if ($tem_recompensa) $cols_conq .= ", recompensa_tipo, cupom_id, mensagem_urgencia";
    $stmt_conq = $pdo->prepare("SELECT $cols_conq FROM curso_conquistas WHERE curso_id = ? ORDER BY ordem ASC, id ASC");
    $stmt_conq->execute([$curso_id]);
    $conquistas = $stmt_conq->fetchAll(PDO::FETCH_ASSOC);

    $stmt_ja = $pdo->prepare("SELECT 1 FROM aluno_conquistas WHERE LOWER(TRIM(aluno_email)) = LOWER(TRIM(?)) AND conquista_id = ?");
    $stmt_ins = $pdo->prepare("INSERT IGNORE INTO aluno_conquistas (aluno_email, conquista_id) VALUES (?, ?)");

    foreach ($conquistas as $c) {
        $atingido = false;
        $tipo = $c['gatilho_tipo'];
        $valor = (int)($c['gatilho_valor'] ?? 0);
        $modulo_id = (int)($c['modulo_id'] ?? 0);

        switch ($tipo) {
            case 'primeira_aula':
                $atingido = $aulas_concluidas >= 1;
                break;
            case 'aulas_concluidas':
                $atingido = $aulas_concluidas >= $valor && $valor > 0;
                break;
            case 'modulo_completo':
                if ($modulo_id > 0 && isset($modulos_progresso[$modulo_id])) {
                    $mp = $modulos_progresso[$modulo_id];
                    $atingido = $mp['total'] > 0 && $mp['concluidas'] >= $mp['total'];
                }
                break;
            case 'progresso_50':
                $atingido = $progresso_percentual >= 50;
                break;
            case 'progresso_70':
                $atingido = $progresso_percentual >= 70;
                break;
            case 'progresso_75':
                $atingido = $progresso_percentual >= 75;
                break;
            case 'progresso_100':
                $atingido = $progresso_percentual >= 100;
                break;
            case 'certificado':
                $atingido = $certificado_habilitado && $progresso_percentual >= $certificado_minima;
                break;
            case 'primeiro_comentario':
                $atingido = $tem_comentario && !empty($contexto['acabou_comentar']);
                break;
            default:
                break;
        }

        if (!$atingido) continue;

        $stmt_ja->execute([$aluno_email, $c['id']]);
        if ($stmt_ja->fetch()) continue;

        $stmt_ins->execute([$aluno_email, $c['id']]);
        if ($stmt_ins->rowCount() > 0) {
            $badge_url = $c['badge_url'] ?? '';
            if (function_exists('get_badge_display_url')) {
                $resolved = get_badge_display_url($badge_url);
                if ($resolved !== null) $badge_url = $resolved;
            }
            if (empty($badge_url)) {
                $badge_url = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="%2332e768" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>');
            } elseif (strpos($badge_url, 'http') !== 0 && strpos($badge_url, 'data:') !== 0) {
                $badge_url = '/' . ltrim($badge_url, '/');
            }
            $item = [
                'id' => (int)$c['id'],
                'titulo' => $c['titulo'],
                'descricao' => $c['descricao'] ?? '',
                'badge_url' => $badge_url
            ];
            if ($tem_recompensa) {
                $recompensa_tipo = $c['recompensa_tipo'] ?? 'badge';
                $cupom_id = (int)($c['cupom_id'] ?? 0);
                $mensagem_urgencia = trim($c['mensagem_urgencia'] ?? '');
                if (in_array($recompensa_tipo, ['cupom', 'cupom_mensagem']) && $cupom_id > 0) {
                    $stmt_cup = $pdo->prepare("SELECT codigo, valido_ate, ativo FROM cupons WHERE id = ?");
                    $stmt_cup->execute([$cupom_id]);
                    $cupom = $stmt_cup->fetch(PDO::FETCH_ASSOC);
                    $now = date('Y-m-d H:i:s');
                    if ($cupom && (int)$cupom['ativo'] === 1 && (!$cupom['valido_ate'] || $cupom['valido_ate'] >= $now)) {
                        $item['cupom_codigo'] = $cupom['codigo'];
                        $item['cupom_valido_ate'] = $cupom['valido_ate'];
                    }
                }
                if (!empty($mensagem_urgencia) && in_array($recompensa_tipo, ['mensagem', 'cupom_mensagem'])) {
                    $item['mensagem_urgencia'] = $mensagem_urgencia;
                }
            }
            $novas_conquistas[] = $item;
        }
    }

    $stmt_todas = $pdo->prepare("
        SELECT cc.id, cc.titulo, cc.descricao, cc.badge_url
        FROM aluno_conquistas ac
        JOIN curso_conquistas cc ON ac.conquista_id = cc.id
        WHERE LOWER(TRIM(ac.aluno_email)) = LOWER(TRIM(?)) AND cc.curso_id = ?
        ORDER BY ac.data_desbloqueio ASC
    ");
    $stmt_todas->execute([$aluno_email, $curso_id]);
    while ($row = $stmt_todas->fetch(PDO::FETCH_ASSOC)) {
        $badge_url = $row['badge_url'] ?? '';
        if (function_exists('get_badge_display_url')) {
            $resolved = get_badge_display_url($badge_url);
            if ($resolved !== null) $badge_url = $resolved;
        }
        if (empty($badge_url)) {
            $badge_url = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="%2332e768" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>');
        } elseif (strpos($badge_url, 'http') !== 0 && strpos($badge_url, 'data:') !== 0) {
            $badge_url = '/' . ltrim($badge_url, '/');
        }
        $todas_desbloqueadas[] = [
            'id' => (int)$row['id'],
            'titulo' => $row['titulo'],
            'descricao' => $row['descricao'] ?? '',
            'badge_url' => $badge_url
        ];
    }

    return [
        'novas_conquistas' => $novas_conquistas,
        'todas_desbloqueadas' => $todas_desbloqueadas
    ];
}
