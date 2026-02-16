<?php
// Carrega .env da raiz do projeto (se existir)
require_once __DIR__ . '/env_loader.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'gatewaypro'));
define('DB_PASS', env('DB_PASS', 'gatewaypro_secret_2024'));
define('DB_NAME', env('DB_NAME', 'checkout'));

date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '-03:00';");
} catch (PDOException $e) {
    die("ERRO: Não foi possível conectar ao banco de dados. " . $e->getMessage());
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getSystemSetting($chave, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ?");
        $stmt->execute([$chave]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['valor'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function setSystemSetting($chave, $valor) {
    global $pdo;
    if (!$pdo) return false;
    try {
        $stmt_check = $pdo->prepare("SELECT id FROM configuracoes_sistema WHERE chave = ?");
        $stmt_check->execute([$chave]);
        $exists = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = ?");
            $stmt->execute([$valor, $chave]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?)");
            $stmt->execute([$chave, $valor]);
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function getAllSystemSettings() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes_sistema");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['chave']] = $row['valor'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

require_once __DIR__ . '/../helpers/plugin_hooks.php';
require_once __DIR__ . '/../helpers/plugin_loader.php';
require_once __DIR__ . '/../helpers/community_helper.php';
?>
