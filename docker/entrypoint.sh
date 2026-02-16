#!/bin/bash
set -e

DB_HOST="${DB_HOST:-db}"
DB_USER="${DB_USER:-gatewaypro}"
DB_PASSWORD="${DB_PASSWORD:-gatewaypro_secret_2024}"
DB_NAME="${DB_NAME:-checkout}"

echo "============================================"
echo "GatewayPro - Inicializando..."
echo "============================================"

# Gerar config.php dinamicamente
CONFIG_FILE="/var/www/html/config/config.php"

cat > "$CONFIG_FILE" << EOFCONFIG
<?php
define('DB_HOST', '${DB_HOST}');
define('DB_USER', '${DB_USER}');
define('DB_PASS', '${DB_PASSWORD}');
define('DB_NAME', '${DB_NAME}');

date_default_timezone_set('America/Sao_Paulo');

try {
    \$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->exec("SET time_zone = '-03:00';");
} catch (PDOException \$e) {
    die("ERRO: Não foi possível conectar ao banco de dados. " . \$e->getMessage());
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getSystemSetting(\$chave, \$default = '') {
    global \$pdo;
    try {
        \$stmt = \$pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ?");
        \$stmt->execute([\$chave]);
        \$result = \$stmt->fetch(PDO::FETCH_ASSOC);
        return \$result ? \$result['valor'] : \$default;
    } catch (PDOException \$e) {
        return \$default;
    }
}

function setSystemSetting(\$chave, \$valor) {
    global \$pdo;
    if (!\$pdo) return false;
    try {
        \$stmt_check = \$pdo->prepare("SELECT id FROM configuracoes_sistema WHERE chave = ?");
        \$stmt_check->execute([\$chave]);
        \$exists = \$stmt_check->fetch(PDO::FETCH_ASSOC);
        if (\$exists) {
            \$stmt = \$pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = ?");
            \$stmt->execute([\$valor, \$chave]);
        } else {
            \$stmt = \$pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?)");
            \$stmt->execute([\$chave, \$valor]);
        }
        return true;
    } catch (PDOException \$e) {
        return false;
    }
}

function getAllSystemSettings() {
    global \$pdo;
    try {
        \$stmt = \$pdo->prepare("SELECT chave, valor FROM configuracoes_sistema");
        \$stmt->execute();
        \$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);
        \$settings = [];
        foreach (\$results as \$row) {
            \$settings[\$row['chave']] = \$row['valor'];
        }
        return \$settings;
    } catch (PDOException \$e) {
        return [];
    }
}

require_once __DIR__ . '/../helpers/plugin_hooks.php';
require_once __DIR__ . '/../helpers/plugin_loader.php';
?>
EOFCONFIG

chown www-data:www-data "$CONFIG_FILE"
echo "Config gerado!"

echo "============================================"
echo "GatewayPro pronto!"
echo "============================================"

exec "$@"
