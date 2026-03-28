<?php
/**
 * Certificado de conclusão do curso
 * Requer login como aluno (tipo usuario). Valida acesso e % de conclusão.
 */
require_once __DIR__ . '/config/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /member_login');
    exit;
}
if (isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin') {
    header('Location: /admin');
    exit;
}
if (isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'infoprodutor') {
    header('Location: /');
    exit;
}

$produto_id = (int)($_GET['produto_id'] ?? 0);
$cliente_email = trim($_SESSION['usuario'] ?? '');
$cliente_nome = $_SESSION['nome'] ?? $cliente_email;

if ($produto_id <= 0 || empty($cliente_email)) {
    header('Location: /member_area_dashboard');
    exit;
}

require_once __DIR__ . '/helpers/acesso_helper.php';
$acesso_info = verificar_acesso_aluno($pdo, $cliente_email, $produto_id);
if (!$acesso_info['tem_acesso']) {
    header('Location: /member_area_dashboard');
    exit;
}

$chk_cert = @$pdo->query("SHOW COLUMNS FROM cursos LIKE 'certificado_habilitado'");
if (!$chk_cert || $chk_cert->rowCount() === 0) {
    header('Location: /member_course_view?produto_id=' . $produto_id);
    exit;
}

$stmt_curso = $pdo->prepare("SELECT c.*, p.nome as produto_nome FROM cursos c JOIN produtos p ON c.produto_id = p.id WHERE c.produto_id = ?");
$stmt_curso->execute([$produto_id]);
$curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);
if (!$curso || !($curso['certificado_habilitado'] ?? 0)) {
    header('Location: /member_course_view?produto_id=' . $produto_id);
    exit;
}

$curso_id = $curso['id'];
$conclusao_minima = (int)($curso['certificado_conclusao_minima'] ?? 100);

$stmt_aulas = $pdo->prepare("
    SELECT a.id, a.release_days
    FROM aulas a
    INNER JOIN modulos m ON a.modulo_id = m.id
    WHERE m.curso_id = ?
");
$stmt_aulas->execute([$curso_id]);
$aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);

$data_concessao = $acesso_info['data_concessao'] ?? date('Y-m-d H:i:s');
$data_concessao_obj = new DateTime($data_concessao);
$hoje = new DateTime();
$dias_desde_compra = $data_concessao_obj->diff($hoje)->days;

$total_aulas = 0;
$aulas_concluidas = 0;
foreach ($aulas as $aula) {
    if ($aula['release_days'] <= $dias_desde_compra) {
        $total_aulas++;
        $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM aluno_progresso WHERE LOWER(TRIM(aluno_email)) = LOWER(TRIM(?)) AND aula_id = ?");
        $stmt_p->execute([$cliente_email, $aula['id']]);
        if ($stmt_p->fetchColumn() > 0) $aulas_concluidas++;
    }
}

$progresso = $total_aulas > 0 ? round(($aulas_concluidas / $total_aulas) * 100) : 0;
if ($progresso < $conclusao_minima) {
    header('Location: /member_course_view?produto_id=' . $produto_id . '&certificado=pendente');
    exit;
}

$nome_curso = $curso['titulo'] ?? $curso['produto_nome'] ?? 'Curso';
$duracao = trim($curso['certificado_duracao'] ?? '');
$texto_assinatura = trim($curso['certificado_texto_assinatura'] ?? '');
$nome_plataforma = trim($curso['certificado_nome_plataforma'] ?? '');
if (empty($nome_plataforma) && function_exists('getSystemSetting')) {
    $nome_plataforma = getSystemSetting('nome_plataforma', 'Plataforma');
}
$cor_primaria = $curso['certificado_cor_primaria'] ?? '#32e768';
if (empty($cor_primaria) || $cor_primaria[0] !== '#') $cor_primaria = '#32e768';

$logo_url = '';
if (function_exists('getSystemSetting')) {
    $logo_url = getSystemSetting('logo_url', '');
}
if (!empty($logo_url) && strpos($logo_url, 'http') !== 0) {
    $logo_url = '/' . ltrim($logo_url, '/');
}
if (empty($logo_url)) {
    $logo_url = 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png';
}

$imagem_fundo = $curso['certificado_imagem_fundo'] ?? '';
if (!empty($imagem_fundo) && strpos($imagem_fundo, 'http') !== 0) {
    $imagem_fundo = '/' . ltrim($imagem_fundo, '/');
}

$data_conclusao = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado - <?php echo htmlspecialchars($nome_curso); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Georgia', serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; background: #1a1a2e; }
        .certificado { position: relative; width: 100%; max-width: 800px; aspect-ratio: 1.414; background: #fff; border-radius: 12px; padding: 48px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); overflow: hidden; }
        .certificado-bg { position: absolute; inset: 0; opacity: 0.06; background-size: cover; background-position: center; }
        .certificado-content { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: space-between; text-align: center; }
        .logo { max-width: 120px; max-height: 80px; object-fit: contain; margin-bottom: 16px; }
        .titulo-cert { font-size: 1.5rem; font-weight: 700; letter-spacing: 0.1em; color: #333; margin-bottom: 8px; }
        .nome-curso { font-size: 1.8rem; font-weight: 700; margin-bottom: 24px; }
        .texto-cert { font-size: 1rem; color: #444; line-height: 1.6; max-width: 560px; margin-bottom: 16px; }
        .nome-aluno { font-size: 1.4rem; font-weight: 700; text-decoration: underline; text-underline-offset: 4px; margin: 8px 0; }
        .texto-plataforma { font-size: 0.95rem; color: #555; margin-top: 8px; }
        .data { font-size: 0.9rem; color: #666; margin: 16px 0; }
        .assinatura { margin-top: 24px; font-family: 'Dancing Script', 'Georgia', cursive; font-size: 1.2rem; color: #333; }
        .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 6rem; font-weight: 900; color: rgba(0,0,0,0.03); transform: rotate(-30deg); pointer-events: none; }
        @media print { body { background: #fff; } .certificado { box-shadow: none; } .no-print { display: none !important; } }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="no-print" style="position: fixed; top: 16px; right: 16px; z-index: 9999;">
        <button onclick="window.print()" style="background: <?php echo htmlspecialchars($cor_primaria); ?>; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">Imprimir / Salvar PDF</button>
        <a href="/member_course_view?produto_id=<?php echo $produto_id; ?>" style="display: inline-block; margin-left: 8px; color: #fff; background: #4b5563; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">Voltar ao curso</a>
    </div>
    <div class="certificado">
        <?php if (!empty($imagem_fundo)): ?>
        <div class="certificado-bg" style="background-image: url('<?php echo htmlspecialchars($imagem_fundo); ?>');"></div>
        <?php endif; ?>
        <div class="watermark"><?php echo htmlspecialchars($nome_plataforma); ?></div>
        <div class="certificado-content">
            <div>
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($nome_plataforma); ?>" class="logo">
                <h1 class="titulo-cert">CERTIFICADO DE CONCLUSÃO</h1>
                <p class="nome-curso" style="color: <?php echo htmlspecialchars($cor_primaria); ?>;"><?php echo htmlspecialchars($nome_curso); ?></p>
                <p class="texto-cert">Certificamos que</p>
                <p class="nome-aluno" style="color: <?php echo htmlspecialchars($cor_primaria); ?>;"><?php echo htmlspecialchars($cliente_nome); ?></p>
                <p class="texto-cert">completou com sucesso o curso em <strong><?php echo htmlspecialchars($nome_plataforma); ?></strong></p>
                <?php if (!empty($duracao)): ?>
                <p class="texto-cert">com carga horária de <strong><?php echo htmlspecialchars($duracao); ?></strong></p>
                <?php endif; ?>
                <p class="data">em <?php echo $data_conclusao; ?></p>
            </div>
            <div>
                <?php if (!empty($texto_assinatura)): ?>
                <p class="assinatura"><?php echo htmlspecialchars($texto_assinatura); ?></p>
                <?php endif; ?>
                <p class="texto-plataforma"><strong><?php echo htmlspecialchars($nome_plataforma); ?></strong></p>
            </div>
        </div>
    </div>
</body>
</html>
