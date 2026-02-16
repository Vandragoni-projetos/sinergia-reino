<?php
/**
 * Configuração para ambiente Docker
 * Copie este arquivo para config.php ao usar Docker
 */

define('DB_HOST', 'db');           // Nome do serviço no docker-compose
define('DB_USER', 'gatewaypro');   // Usuário definido no docker-compose
define('DB_PASS', 'gatewaypro_secret'); // Senha definida no docker-compose
define('DB_NAME', 'checkout');     // Nome do banco definido no docker-compose

// Define o fuso horário padrão para o PHP para 'America/Sao_Paulo' (Horário de Brasília)
date_default_timezone_set('America/Sao_Paulo');

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
        error_log("Erro ao buscar configuração: " . $e->getMessage());
        return $default;
    }
}

function setSystemSetting($chave, $valor) {
    global $pdo;
    
    if (!$pdo) {
        error_log("setSystemSetting: PDO não está disponível");
        return false;
    }
    
    try {
        $stmt_check = $pdo->prepare("SELECT id FROM configuracoes_sistema WHERE chave = ?");
        $stmt_check->execute([$chave]);
        $exists = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            try {
                $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ?, updated_at = CURRENT_TIMESTAMP WHERE chave = ?");
                $stmt->execute([$valor, $chave]);
            } catch (PDOException $e) {
                $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = ?");
                $stmt->execute([$valor, $chave]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?)");
            $stmt->execute([$chave, $valor]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("setSystemSetting: EXCEÇÃO ao salvar configuração '$chave': " . $e->getMessage());
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
        error_log("Erro ao buscar todas as configurações: " . $e->getMessage());
        return [];
    }
}

require_once __DIR__ . '/../helpers/plugin_hooks.php';
require_once __DIR__ . '/../helpers/plugin_loader.php';
?>
