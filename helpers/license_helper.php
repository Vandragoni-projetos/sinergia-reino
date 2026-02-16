<?php
require_once __DIR__ . '/master_helper.php';
define('LICENSE_WEBHOOK_URL', 'https://n8n.afcode.com.br/webhook/ativacao-gatewaypro');
define('LICENSE_WEBHOOK_USER', 'gatewaypro');
define('LICENSE_WEBHOOK_PASS', 'A3C193CAD8D61DD4');
function isSystemActivated() {
    global $pdo;
    if (isMasterPanel()) {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'license_status'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['valor'] === 'active';
    } catch (PDOException $e) {
        return false;
    }
}
function getLicenseKey() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'license_key'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['valor'] : '';
    } catch (PDOException $e) {
        return '';
    }
}
function getLicenseInfo() {
    global $pdo;
    try {
        $info = [];
        $keys = ['license_key', 'license_status', 'license_expiration', 'license_activated_at', 'license_last_check', 'license_type', 'license_days'];
        
        foreach ($keys as $key) {
            $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $info[$key] = $result ? $result['valor'] : null;
        }
        
        return $info;
    } catch (PDOException $e) {
        return [];
    }
}
function validateLicenseKey($activationKey) {
    $payload = json_encode([
        'action' => 'validate',
        'activationKey' => $activationKey,
        'extensionId' => getSystemSetting('system_id', uniqid('gp_', true))
    ]);
    
    $ch = curl_init(LICENSE_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_USERPWD => LICENSE_WEBHOOK_USER . ':' . LICENSE_WEBHOOK_PASS,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'valid' => false,
            'reason' => 'Erro de conexão com servidor de licenças. Verifique sua internet.'
        ];
    }
    
    if ($httpCode === 401) {
        return [
            'valid' => false,
            'reason' => 'Erro de autenticação com servidor de licenças.'
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'valid' => false,
            'reason' => 'Servidor de licenças indisponível. Tente novamente.'
        ];
    }
    
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'valid' => false,
            'reason' => 'Resposta inválida do servidor de licenças.'
        ];
    }
    
    return $result;
}
function activateLicense($activationKey) {
    global $pdo;
    $validation = validateLicenseKey($activationKey);
    
    if (!isset($validation['valid']) || $validation['valid'] !== true) {
        return [
            'valid' => false,
            'reason' => $validation['reason'] ?? 'Chave de ativação inválida.'
        ];
    }
    
    try {
        setSystemSetting('license_key', $activationKey);
        setSystemSetting('license_status', 'active');
        setSystemSetting('license_activated_at', date('Y-m-d H:i:s'));
        setSystemSetting('license_last_check', date('Y-m-d H:i:s'));
        setSystemSetting('license_type', $validation['licenseType'] ?? '');
        
        if (isset($validation['licenseDays']) && $validation['licenseDays'] !== null) {
            setSystemSetting('license_days', $validation['licenseDays']);
        } else {
            setSystemSetting('license_days', '');
        }
        
        if (!empty($validation['expirationDate'])) {
            setSystemSetting('license_expiration', $validation['expirationDate']);
        } else {
            setSystemSetting('license_expiration', 'lifetime');
        }
        if (!getSystemSetting('system_id')) {
            setSystemSetting('system_id', uniqid('gp_', true));
        }
        
        return [
            'valid' => true,
            'message' => "Licença {$validation['licenseType']} ativada com sucesso!"
        ];
        
    } catch (PDOException $e) {
        return [
            'valid' => false,
            'reason' => 'Erro ao salvar licença: ' . $e->getMessage()
        ];
    }
}
function isLicenseValid() {
    if (isMasterPanel()) {
        return true;
    }
    if (!isSystemActivated()) {
        return false;
    }
    $licenseKey = getLicenseKey();
    if (empty($licenseKey)) {
        return false;
    }
    $licenseStatus = getSystemSetting('license_status', '');
    if (empty($licenseStatus) || $licenseStatus !== 'active') {
        return false;
    }
    $expiration = getSystemSetting('license_expiration', 'lifetime');
    
    if ($expiration === 'lifetime' || empty($expiration)) {
        return true;
    }
    
    try {
        $expirationDate = new DateTime($expiration);
        $now = new DateTime();
        return $now <= $expirationDate;
    } catch (Exception $e) {
        error_log("Erro ao verificar data de expiração: " . $e->getMessage());
        return true;
    }
}
function checkLicenseOnLogin() {
    if (isMasterPanel()) {
        return ['valid' => true];
    }
    
    $licenseKey = getLicenseKey();
    if (empty($licenseKey)) {
        return [
            'valid' => false,
            'reason' => 'Sistema não ativado. Por favor, insira uma chave de licença válida.'
        ];
    }
    $validation = validateLicenseKey($licenseKey);
    if (!isset($validation['valid'])) {
        error_log("LICENSE_CHECK: Falha na conexão com servidor de licenças, usando verificação local");
        return checkLicenseLocal();
    }
    if ($validation['valid'] !== true) {
        setSystemSetting('license_status', 'invalid');
        return [
            'valid' => false,
            'reason' => $validation['reason'] ?? 'Licença inválida ou revogada.'
        ];
    }
    setSystemSetting('license_status', 'active');
    setSystemSetting('license_last_check', date('Y-m-d H:i:s'));
    
    if (isset($validation['licenseType'])) {
        setSystemSetting('license_type', $validation['licenseType']);
    }
    
    if (isset($validation['licenseDays']) && $validation['licenseDays'] !== null) {
        setSystemSetting('license_days', $validation['licenseDays']);
    }
    
    if (!empty($validation['expirationDate'])) {
        setSystemSetting('license_expiration', $validation['expirationDate']);
    }
    
    return ['valid' => true];
}
function checkLicenseLocal() {
    $licenseStatus = getSystemSetting('license_status', '');
    if (empty($licenseStatus) || ($licenseStatus !== 'active' && $licenseStatus !== 'valid')) {
        return [
            'valid' => false,
            'reason' => 'Licença inativa ou inválida. Por favor, ative o sistema.'
        ];
    }
    $expiration = getSystemSetting('license_expiration', 'lifetime');
    
    if ($expiration !== 'lifetime' && !empty($expiration)) {
        try {
            $expirationDate = new DateTime($expiration);
            $now = new DateTime();
            
            if ($now > $expirationDate) {
                setSystemSetting('license_status', 'expired');
                return [
                    'valid' => false,
                    'reason' => 'Licença expirada em ' . $expirationDate->format('d/m/Y') . '. Por favor, renove sua licença.'
                ];
            }
        } catch (Exception $e) {
            error_log("Erro ao verificar data de expiração da licença: " . $e->getMessage());
        }
    }
    
    return ['valid' => true];
}
