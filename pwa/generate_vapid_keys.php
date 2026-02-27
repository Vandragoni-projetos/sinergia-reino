<?php
/**
 * Script para gerar chaves VAPID manualmente (notificações push PWA)
 */
require_once __DIR__ . '/../config/config.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['tipo'] ?? '') !== 'admin') {
    header('Location: /admin?pagina=admin_pwa');
    exit;
}

$error = null;
$success = false;
$keys = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate']) && class_exists('Minishlink\WebPush\VAPID')) {
    try {
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        if ($keys && !empty($keys['publicKey']) && !empty($keys['privateKey'])) {
            $stmt = $pdo->query("SELECT id FROM pwa_config ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $st = $pdo->prepare("UPDATE pwa_config SET vapid_public_key = ?, vapid_private_key = ? WHERE id = ?");
                $st->execute([$keys['publicKey'], $keys['privateKey'], $existing['id']]);
            } else {
                $st = $pdo->prepare("INSERT INTO pwa_config (vapid_public_key, vapid_private_key, app_name) VALUES (?, ?, 'Plataforma')");
                $st->execute([$keys['publicKey'], $keys['privateKey']]);
            }
            $success = true;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerar Chaves VAPID - PWA</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a1a; color: #fff; max-width: 800px; margin: 0 auto; }
        .success { color: #4ade80; background: #1a3a1a; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: #f87171; background: #3a1a1a; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #60a5fa; background: #1a2a3a; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #2a2a2a; padding: 10px; border-radius: 5px; overflow-x: auto; }
        button { background: #4ade80; color: #000; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        a { color: #4ade80; }
    </style>
</head>
<body>
    <h1>Gerador de Chaves VAPID</h1>
    <?php if ($success && $keys): ?>
        <div class="success">
            <h2>Chaves VAPID geradas e salvas.</h2>
            <p><a href="/admin?pagina=admin_pwa"><button>Voltar para Configurações PWA</button></a></p>
        </div>
    <?php elseif ($error): ?>
        <div class="error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <div class="info"><p>Instale a dependência: <code>composer require minishlink/web-push</code> na raiz do projeto.</p></div>
    <?php elseif (!class_exists('Minishlink\WebPush\VAPID')): ?>
        <div class="error"><p>Biblioteca web-push não encontrada. Execute na raiz do projeto: <code>composer require minishlink/web-push</code></p></div>
    <?php else: ?>
        <div class="info"><p>Gera chaves VAPID para notificações push.</p></div>
        <form method="POST"><button type="submit" name="generate">Gerar Chaves VAPID</button></form>
    <?php endif; ?>
    <p><a href="/admin?pagina=admin_pwa">← Voltar para Configurações PWA</a></p>
</body>
</html>
