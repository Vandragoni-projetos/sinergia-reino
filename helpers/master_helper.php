<?php
/**
 * Retorna true se esta instalação é o Painel Master (não exige chave de ativação).
 * - Bypass em instalação nova: se GATEWAYPRO_MASTER_SECRET estiver no .env e license_key
 *   estiver vazio, considera como master para permitir primeiro acesso e habilitar o painel no admin.
 */
function isMasterPanel() {
    $envSecret = getenv('GATEWAYPRO_MASTER_SECRET');
    if (empty($envSecret)) {
        return false;
    }
    $licenseKey = getSystemSetting('license_key', '');
    // Instalação nova: secret no .env e ainda sem chave → tratar como master para primeiro acesso
    if ($licenseKey === '') {
        return true;
    }
    $isMasterFlag = getSystemSetting('is_master_panel', '0') === '1';
    if (!$isMasterFlag) {
        return false;
    }
    $masterSecretKey = getSystemSetting('master_secret_key', '');
    if (empty($masterSecretKey) || !hash_equals($envSecret, $masterSecretKey)) {
        return false;
    }
    return true;
}
function licensesTableExists() {
    global $pdo;
    try {
        $result = $pdo->query("SELECT 1 FROM licencas_geradas LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
