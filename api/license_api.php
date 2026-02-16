<?php
/**
 * API Pública de Validação de Licenças
 * Este endpoint é usado pelos painéis clientes para validar licenças
 * Só funciona no painel master legítimo
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/master_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success] + $data);
    exit;
}

// Verifica se é o painel master LEGÍTIMO
if (!isMasterPanel()) {
    sendResponse(false, ['error' => 'Este painel não é o servidor de licenças.'], 403);
}

// Verifica autenticação via token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$apiToken = getSystemSetting('license_api_token', '');

if (empty($apiToken)) {
    // Gera um token se não existir
    $apiToken = bin2hex(random_bytes(32));
    setSystemSetting('license_api_token', $apiToken);
}

// Extrai o token do header
$providedToken = '';
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $providedToken = $matches[1];
}

if ($providedToken !== $apiToken) {
    sendResponse(false, ['error' => 'Token de autenticação inválido.'], 401);
}

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, ['error' => 'Método não permitido.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$activationKey = $input['activationKey'] ?? '';
$extensionId = $input['extensionId'] ?? '';

if ($action !== 'validate') {
    sendResponse(false, ['reason' => 'Ação inválida. Use: validate'], 400);
}

if (empty($activationKey)) {
    sendResponse(false, [
        'valid' => false,
        'reason' => 'Chave de ativação é obrigatória.'
    ]);
}

try {
    // Usa license_service se disponível (evolução), senão lógica legada
    if (file_exists(__DIR__ . '/../helpers/license_service.php')) {
        require_once __DIR__ . '/../helpers/license_service.php';

        $validation = license_validate_local($activationKey);

        if ($validation['valid']) {
            sendResponse(true, [
                'valid' => true,
                'activationKey' => $activationKey,
                'licenseType' => $validation['licenseType'] ?? '',
                'licenseDays' => $validation['licenseDays'] ?? null,
                'expirationDate' => $validation['expirationDate'] ?? null,
                'message' => 'Licença válida.'
            ]);
        }

        // Se disponivel (needs_activation), ativa agora
        if (!empty($validation['needs_activation'])) {
            $activationResult = license_activate_local($activationKey, $extensionId);
            if ($activationResult['valid']) {
                sendResponse(true, [
                    'valid' => true,
                    'activationKey' => $activationKey,
                    'licenseType' => $activationResult['licenseType'],
                    'licenseDays' => $activationResult['licenseDays'] ?? null,
                    'expirationDate' => $activationResult['expirationDate'] ?? null,
                    'message' => 'Licença ativada com sucesso!'
                ]);
            }
        }

        sendResponse(true, [
            'valid' => false,
            'reason' => $validation['reason'] ?? 'Licença inválida.',
            'expirationDate' => $validation['expirationDate'] ?? null
        ]);
    }

    // Fallback: lógica legada (se license_service não existir)
    $stmt = $pdo->prepare("SELECT * FROM licencas_geradas WHERE chave_licenca = ?");
    $stmt->execute([$activationKey]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$license) {
        sendResponse(true, ['valid' => false, 'reason' => 'Chave de ativação não encontrada.']);
    }
    if (in_array($license['status'], ['revogada', 'bloqueada'])) {
        sendResponse(true, ['valid' => false, 'reason' => 'Esta licença foi revogada ou bloqueada.']);
    }
    if (in_array($license['status'], ['ativada', 'ativa']) && !empty($license['data_expiracao'])) {
        $expDate = new DateTime($license['data_expiracao']);
        if (new DateTime() > $expDate) {
            $pdo->prepare("UPDATE licencas_geradas SET status = 'expirada' WHERE id = ?")->execute([$license['id']]);
            sendResponse(true, ['valid' => false, 'reason' => 'Licença expirada em ' . $expDate->format('d/m/Y'), 'expirationDate' => $license['data_expiracao']]);
        }
    }
    if ($license['status'] === 'disponivel') {
        $dataExpiracao = null;
        if (!empty($license['dias_validade'])) {
            $exp = new DateTime();
            $exp->modify("+{$license['dias_validade']} days");
            $dataExpiracao = $exp->format('Y-m-d');
        }
        $pdo->prepare("UPDATE licencas_geradas SET status = 'ativada', data_ativacao = ?, data_expiracao = ?, instalacao_id = ?, ip_ativacao = ? WHERE id = ?")
            ->execute([date('Y-m-d H:i:s'), $dataExpiracao, $extensionId, $_SERVER['REMOTE_ADDR'] ?? null, $license['id']]);
        sendResponse(true, ['valid' => true, 'activationKey' => $activationKey, 'licenseType' => $license['tipo_licenca'], 'licenseDays' => $license['dias_validade'], 'expirationDate' => $dataExpiracao, 'message' => 'Licença ativada com sucesso!']);
    }
    if (in_array($license['status'], ['ativada', 'ativa'])) {
        sendResponse(true, ['valid' => true, 'activationKey' => $activationKey, 'licenseType' => $license['tipo_licenca'], 'licenseDays' => $license['dias_validade'], 'expirationDate' => $license['data_expiracao'] ?? null, 'message' => 'Licença válida.']);
    }
    sendResponse(true, ['valid' => false, 'reason' => 'Status de licença inválido: ' . $license['status']]);

} catch (PDOException $e) {
    error_log("LICENSE_API Error: " . $e->getMessage());
    sendResponse(false, ['error' => 'Erro interno do servidor.'], 500);
}
