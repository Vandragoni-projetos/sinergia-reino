<?php
// Este arquivo é incluído a partir do index.php,
// então a verificação de login e a conexão com o banco ($pdo) já existem.

// Obter o ID do usuário logado
$usuario_id_logado = $_SESSION['id'] ?? 0;

// Se por algum motivo o ID do usuário não estiver definido, redireciona para o login
if ($usuario_id_logado === 0) {
    header("location: /login");
    exit;
}

$mensagem = '';
$msg_type = '';

// Pega a mensagem da sessão, se houver, e depois limpa
if (isset($_SESSION['flash_message'])) {
    $mensagem = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Fetch current user data para gateways
try {
    $stmt_user_data = $pdo->prepare("SELECT mp_public_key, mp_access_token, pushinpay_token, efi_client_id, efi_client_secret, efi_certificate_path, efi_pix_key, efi_payee_code, beehive_secret_key, beehive_public_key, hypercash_secret_key, hypercash_public_key, pagarme_api_key, pagarme_api_secret, pagarme_webhook_secret, paypal_client_id, paypal_client_secret, paypal_webhook_secret, stripe_publishable_key, stripe_secret_key, stripe_webhook_secret FROM usuarios WHERE id = ?");
    $stmt_user_data->execute([$usuario_id_logado]);
    $user_data_fetched = $stmt_user_data->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt_user_data = $pdo->prepare("SELECT mp_public_key, mp_access_token, pushinpay_token, efi_client_id, efi_client_secret, efi_certificate_path, efi_pix_key, efi_payee_code, beehive_secret_key, beehive_public_key, hypercash_secret_key, hypercash_public_key FROM usuarios WHERE id = ?");
        $stmt_user_data->execute([$usuario_id_logado]);
        $user_data_fetched = $stmt_user_data->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $stmt_user_data = $pdo->prepare("SELECT mp_public_key, mp_access_token, pushinpay_token FROM usuarios WHERE id = ?");
        $stmt_user_data->execute([$usuario_id_logado]);
        $user_data_fetched = $stmt_user_data->fetch(PDO::FETCH_ASSOC);
    }
    $user_data_fetched['efi_client_id'] = $user_data_fetched['efi_client_id'] ?? null;
    $user_data_fetched['efi_client_secret'] = $user_data_fetched['efi_client_secret'] ?? null;
    $user_data_fetched['efi_certificate_path'] = $user_data_fetched['efi_certificate_path'] ?? null;
    $user_data_fetched['efi_pix_key'] = $user_data_fetched['efi_pix_key'] ?? null;
    $user_data_fetched['efi_payee_code'] = $user_data_fetched['efi_payee_code'] ?? null;
    $user_data_fetched['beehive_secret_key'] = $user_data_fetched['beehive_secret_key'] ?? null;
    $user_data_fetched['beehive_public_key'] = $user_data_fetched['beehive_public_key'] ?? null;
    $user_data_fetched['hypercash_secret_key'] = $user_data_fetched['hypercash_secret_key'] ?? null;
    $user_data_fetched['hypercash_public_key'] = $user_data_fetched['hypercash_public_key'] ?? null;
    $user_data_fetched['pagarme_api_key'] = $user_data_fetched['pagarme_api_key'] ?? null;
    $user_data_fetched['pagarme_api_secret'] = $user_data_fetched['pagarme_api_secret'] ?? null;
    $user_data_fetched['pagarme_webhook_secret'] = $user_data_fetched['pagarme_webhook_secret'] ?? null;
    $user_data_fetched['paypal_client_id'] = $user_data_fetched['paypal_client_id'] ?? null;
    $user_data_fetched['paypal_client_secret'] = $user_data_fetched['paypal_client_secret'] ?? null;
    $user_data_fetched['paypal_webhook_secret'] = $user_data_fetched['paypal_webhook_secret'] ?? null;
    $user_data_fetched['stripe_publishable_key'] = $user_data_fetched['stripe_publishable_key'] ?? null;
    $user_data_fetched['stripe_secret_key'] = $user_data_fetched['stripe_secret_key'] ?? null;
    $user_data_fetched['stripe_webhook_secret'] = $user_data_fetched['stripe_webhook_secret'] ?? null;
}

$mercado_pago_public_key = $user_data_fetched['mp_public_key'] ?? '';
$mercado_pago_access_token = $user_data_fetched['mp_access_token'] ?? '';
$pushinpay_token = $user_data_fetched['pushinpay_token'] ?? '';
$efi_client_id = $user_data_fetched['efi_client_id'] ?? '';
$efi_client_secret = $user_data_fetched['efi_client_secret'] ?? '';
$efi_certificate_path = $user_data_fetched['efi_certificate_path'] ?? '';
$efi_pix_key = $user_data_fetched['efi_pix_key'] ?? '';
$efi_payee_code = $user_data_fetched['efi_payee_code'] ?? '';
$beehive_secret_key = $user_data_fetched['beehive_secret_key'] ?? '';
$beehive_public_key = $user_data_fetched['beehive_public_key'] ?? '';
$hypercash_secret_key = $user_data_fetched['hypercash_secret_key'] ?? '';
$hypercash_public_key = $user_data_fetched['hypercash_public_key'] ?? '';
$pagarme_api_key = $user_data_fetched['pagarme_api_key'] ?? '';
$pagarme_api_secret = $user_data_fetched['pagarme_api_secret'] ?? '';
$pagarme_webhook_secret = $user_data_fetched['pagarme_webhook_secret'] ?? '';
$paypal_client_id = $user_data_fetched['paypal_client_id'] ?? '';
$paypal_client_secret = $user_data_fetched['paypal_client_secret'] ?? '';
$paypal_webhook_secret = $user_data_fetched['paypal_webhook_secret'] ?? '';
$stripe_publishable_key = $user_data_fetched['stripe_publishable_key'] ?? '';
$stripe_secret_key = $user_data_fetched['stripe_secret_key'] ?? '';
$stripe_webhook_secret = $user_data_fetched['stripe_webhook_secret'] ?? '';

$mp_configured = !empty($mercado_pago_access_token);
$pagarme_configured = !empty($pagarme_api_key) && !empty($pagarme_api_secret);
$paypal_configured = !empty($paypal_client_id) && !empty($paypal_client_secret);
$stripe_configured = !empty($stripe_publishable_key) && !empty($stripe_secret_key);
$pp_configured = !empty($pushinpay_token);
$efi_configured = !empty($efi_client_id) && !empty($efi_client_secret) && !empty($efi_certificate_path) && !empty($efi_pix_key);
$beehive_configured = !empty($beehive_secret_key) && !empty($beehive_public_key);
$hypercash_configured = !empty($hypercash_secret_key) && !empty($hypercash_public_key);

// Fetch Evolution API data
$stmt_evolution = $pdo->prepare("SELECT evolution_name, evolution_server_url, evolution_api_key, evolution_instance FROM usuarios WHERE id = ?");
$stmt_evolution->execute([$usuario_id_logado]);
$evolution_data = $stmt_evolution->fetch(PDO::FETCH_ASSOC);

$evolution_name = $evolution_data['evolution_name'] ?? '';
$evolution_server_url = $evolution_data['evolution_server_url'] ?? '';
$evolution_api_key = $evolution_data['evolution_api_key'] ?? '';
$evolution_instance = $evolution_data['evolution_instance'] ?? '';

$evolution_configured = !empty($evolution_server_url) && !empty($evolution_api_key) && !empty($evolution_instance);

// --- URL DE WEBHOOK ---
$domainName = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['PHP_SELF']);
$path = rtrim(str_replace('\\', '/', $scriptDir), '/');
$webhook_url = "https://" . $domainName . $path . '/notification.php';

$efi_webhook_registered_url = '';
$efi_webhook_status_message = '';

function integracoes_efi_cert_full_path($relative_path) {
    return __DIR__ . '/../' . ltrim(str_replace('\\', '/', (string) $relative_path), '/');
}

function integracoes_try_register_efi_webhook($client_id, $client_secret, $certificate_path, $pix_key, $webhook_url) {
    require_once __DIR__ . '/../gateways/efi.php';

    $cert_full = integracoes_efi_cert_full_path($certificate_path);
    $token_data = efi_get_access_token(trim((string) $client_id), trim((string) $client_secret), $cert_full);
    if (!$token_data || empty($token_data['access_token'])) {
        return ['success' => false, 'message' => 'Não foi possível autenticar na Efí. Verifique Client ID, Client Secret e certificado P12.'];
    }

    return efi_register_webhook(
        $token_data['access_token'],
        trim((string) $pix_key),
        $webhook_url,
        $cert_full
    );
}

function integracoes_get_efi_webhook_status($client_id, $client_secret, $certificate_path, $pix_key) {
    require_once __DIR__ . '/../gateways/efi.php';

    $cert_full = integracoes_efi_cert_full_path($certificate_path);
    $token_data = efi_get_access_token(trim((string) $client_id), trim((string) $client_secret), $cert_full);
    if (!$token_data || empty($token_data['access_token'])) {
        return ['success' => false, 'webhook_url' => '', 'message' => 'Não foi possível consultar webhook na Efí (falha de autenticação).'];
    }

    return efi_get_webhook($token_data['access_token'], trim((string) $pix_key), $cert_full);
}

// Salvar configurações de gateway
if (isset($_POST['registrar_webhook_efi'])) {
    try {
        $stmt_current = $pdo->prepare("SELECT efi_client_id, efi_client_secret, efi_certificate_path, efi_pix_key FROM usuarios WHERE id = ?");
        $stmt_current->execute([$usuario_id_logado]);
        $current_efi = $stmt_current->fetch(PDO::FETCH_ASSOC) ?: [];

        if (empty($current_efi['efi_client_id']) || empty($current_efi['efi_client_secret']) || empty($current_efi['efi_certificate_path']) || empty($current_efi['efi_pix_key'])) {
            $mensagem = 'Preencha Client ID, Client Secret, certificado P12 e Chave Pix antes de registrar o webhook.';
            $msg_type = 'error';
        } else {
            $register_result = integracoes_try_register_efi_webhook(
                $current_efi['efi_client_id'],
                $current_efi['efi_client_secret'],
                $current_efi['efi_certificate_path'],
                $current_efi['efi_pix_key'],
                $webhook_url
            );

            if (!empty($register_result['success'])) {
                $mensagem = $register_result['message'];
                $msg_type = 'success';
                $efi_webhook_registered_url = $webhook_url;
                $efi_webhook_status_message = 'Webhook Pix registrado na Efí.';
            } else {
                $mensagem = $register_result['message'] ?? 'Falha ao registrar webhook Pix na Efí.';
                $msg_type = 'error';
            }
        }
    } catch (PDOException $e) {
        $mensagem = 'Erro ao registrar webhook na Efí: ' . $e->getMessage();
        $msg_type = 'error';
    }
}

if (isset($_POST['salvar_gateways'])) {
    $public_key = $_POST['mercado_pago_public_key'] ?? '';
    $access_token = $_POST['mercado_pago_access_token'] ?? '';
    $pp_token = $_POST['pushinpay_token'] ?? '';
    $efi_client_id_post = $_POST['efi_client_id'] ?? '';
    $efi_client_secret_post = $_POST['efi_client_secret'] ?? '';
    $efi_pix_key_post = $_POST['efi_pix_key'] ?? '';
    $efi_payee_code_post = $_POST['efi_payee_code'] ?? '';
    $beehive_secret_key_post = $_POST['beehive_secret_key'] ?? '';
    $beehive_public_key_post = $_POST['beehive_public_key'] ?? '';
    $hypercash_secret_key_post = $_POST['hypercash_secret_key'] ?? '';
    $hypercash_public_key_post = $_POST['hypercash_public_key'] ?? '';
    $pagarme_api_key_post = $_POST['pagarme_api_key'] ?? '';
    $pagarme_api_secret_post = $_POST['pagarme_api_secret'] ?? '';
    $pagarme_webhook_secret_post = $_POST['pagarme_webhook_secret'] ?? '';
    $paypal_client_id_post = $_POST['paypal_client_id'] ?? '';
    $paypal_client_secret_post = $_POST['paypal_client_secret'] ?? '';
    $paypal_webhook_secret_post = $_POST['paypal_webhook_secret'] ?? '';
    $stripe_publishable_key_post = $_POST['stripe_publishable_key'] ?? '';
    $stripe_secret_key_post = $_POST['stripe_secret_key'] ?? '';
    $stripe_webhook_secret_post = $_POST['stripe_webhook_secret'] ?? '';
    
    // Processar upload de certificado P12 para Efí
    $certificate_path = $efi_certificate_path; // Manter o existente se não houver novo upload
    
    if (isset($_FILES['efi_certificate']) && $_FILES['efi_certificate']['error'] === UPLOAD_ERR_OK) {
        $cert_file = $_FILES['efi_certificate'];
        $cert_ext = strtolower(pathinfo($cert_file['name'], PATHINFO_EXTENSION));
        
        if ($cert_ext === 'p12') {
            // Validar tamanho (máximo 5MB)
            if ($cert_file['size'] > 5 * 1024 * 1024) {
                $mensagem = "Erro: Certificado muito grande. Máximo 5MB.";
                $msg_type = 'error';
            } else {
                $cert_dir = __DIR__ . '/../uploads/certificados/';
                if (!is_dir($cert_dir)) {
                    mkdir($cert_dir, 0755, true);
                }
                
                // Remover certificado antigo se existir
                if (!empty($efi_certificate_path)) {
                    $old_cert_full_path = __DIR__ . '/../' . $efi_certificate_path;
                    if (file_exists($old_cert_full_path)) {
                        @unlink($old_cert_full_path);
                    }
                }
                
                // Gerar nome único
                $cert_filename = 'efi_cert_' . $usuario_id_logado . '_' . time() . '.p12';
                $cert_path = $cert_dir . $cert_filename;
                
                if (move_uploaded_file($cert_file['tmp_name'], $cert_path)) {
                    $certificate_path = 'uploads/certificados/' . $cert_filename;
                } else {
                    $mensagem = "Erro ao fazer upload do certificado.";
                    $msg_type = 'error';
                }
            }
        } else {
            $mensagem = "Erro: Apenas arquivos .p12 são permitidos.";
            $msg_type = 'error';
        }
    }

    try {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET mp_public_key = ?, mp_access_token = ?, pushinpay_token = ?, efi_client_id = ?, efi_client_secret = ?, efi_certificate_path = ?, efi_pix_key = ?, efi_payee_code = ?, beehive_secret_key = ?, beehive_public_key = ?, hypercash_secret_key = ?, hypercash_public_key = ?, pagarme_api_key = ?, pagarme_api_secret = ?, pagarme_webhook_secret = ?, paypal_client_id = ?, paypal_client_secret = ?, paypal_webhook_secret = ?, stripe_publishable_key = ?, stripe_secret_key = ?, stripe_webhook_secret = ? WHERE id = ?");
            $stmt->execute([$public_key, $access_token, $pp_token, $efi_client_id_post, $efi_client_secret_post, $certificate_path, $efi_pix_key_post, $efi_payee_code_post, $beehive_secret_key_post, $beehive_public_key_post, $hypercash_secret_key_post, $hypercash_public_key_post, $pagarme_api_key_post, $pagarme_api_secret_post, $pagarme_webhook_secret_post, $paypal_client_id_post, $paypal_client_secret_post, $paypal_webhook_secret_post, $stripe_publishable_key_post, $stripe_secret_key_post, $stripe_webhook_secret_post, $usuario_id_logado]);
        } catch (PDOException $col_err) {
            $stmt = $pdo->prepare("UPDATE usuarios SET mp_public_key = ?, mp_access_token = ?, pushinpay_token = ?, efi_client_id = ?, efi_client_secret = ?, efi_certificate_path = ?, efi_pix_key = ?, efi_payee_code = ?, beehive_secret_key = ?, beehive_public_key = ?, hypercash_secret_key = ?, hypercash_public_key = ? WHERE id = ?");
            $stmt->execute([$public_key, $access_token, $pp_token, $efi_client_id_post, $efi_client_secret_post, $certificate_path, $efi_pix_key_post, $efi_payee_code_post, $beehive_secret_key_post, $beehive_public_key_post, $hypercash_secret_key_post, $hypercash_public_key_post, $usuario_id_logado]);
        }

        // Mensagem de sucesso personalizada se certificado foi enviado
        if (isset($_FILES['efi_certificate']) && $_FILES['efi_certificate']['error'] === UPLOAD_ERR_OK && !empty($certificate_path) && $certificate_path !== $efi_certificate_path) {
            $mensagem = "Configurações de gateway salvas com sucesso! Certificado P12 enviado e salvo.";
        } else {
            $mensagem = "Configurações de gateway salvas com sucesso.";
        }
        $msg_type = 'success';
        
        // Recarrega os dados
        try {
            $stmt_reload = $pdo->prepare("SELECT mp_public_key, mp_access_token, pushinpay_token, efi_client_id, efi_client_secret, efi_certificate_path, efi_pix_key, efi_payee_code, beehive_secret_key, beehive_public_key, hypercash_secret_key, hypercash_public_key, pagarme_api_key, pagarme_api_secret, pagarme_webhook_secret, paypal_client_id, paypal_client_secret, paypal_webhook_secret, stripe_publishable_key, stripe_secret_key, stripe_webhook_secret FROM usuarios WHERE id = ?");
            $stmt_reload->execute([$usuario_id_logado]);
            $user_data_fetched = $stmt_reload->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt_reload = $pdo->prepare("SELECT mp_public_key, mp_access_token, pushinpay_token, efi_client_id, efi_client_secret, efi_certificate_path, efi_pix_key, efi_payee_code, beehive_secret_key, beehive_public_key, hypercash_secret_key, hypercash_public_key FROM usuarios WHERE id = ?");
            $stmt_reload->execute([$usuario_id_logado]);
            $user_data_fetched = $stmt_reload->fetch(PDO::FETCH_ASSOC);
        }

        $mercado_pago_public_key = $user_data_fetched['mp_public_key'] ?? '';
        $mercado_pago_access_token = $user_data_fetched['mp_access_token'] ?? '';
        $pushinpay_token = $user_data_fetched['pushinpay_token'] ?? '';
        $efi_client_id = $user_data_fetched['efi_client_id'] ?? '';
        $efi_client_secret = $user_data_fetched['efi_client_secret'] ?? '';
        $efi_certificate_path = $user_data_fetched['efi_certificate_path'] ?? '';
        $efi_pix_key = $user_data_fetched['efi_pix_key'] ?? '';
        $efi_payee_code = $user_data_fetched['efi_payee_code'] ?? '';
        $beehive_secret_key = $user_data_fetched['beehive_secret_key'] ?? '';
        $beehive_public_key = $user_data_fetched['beehive_public_key'] ?? '';
        $hypercash_secret_key = $user_data_fetched['hypercash_secret_key'] ?? '';
        $hypercash_public_key = $user_data_fetched['hypercash_public_key'] ?? '';
        $pagarme_api_key = $user_data_fetched['pagarme_api_key'] ?? '';
        $pagarme_api_secret = $user_data_fetched['pagarme_api_secret'] ?? '';
        $pagarme_webhook_secret = $user_data_fetched['pagarme_webhook_secret'] ?? '';
        $paypal_client_id = $user_data_fetched['paypal_client_id'] ?? '';
        $paypal_client_secret = $user_data_fetched['paypal_client_secret'] ?? '';
        $paypal_webhook_secret = $user_data_fetched['paypal_webhook_secret'] ?? '';
        $stripe_publishable_key = $user_data_fetched['stripe_publishable_key'] ?? '';
        $stripe_secret_key = $user_data_fetched['stripe_secret_key'] ?? '';
        $stripe_webhook_secret = $user_data_fetched['stripe_webhook_secret'] ?? '';
        
        $mp_configured = !empty($mercado_pago_access_token);
        $pagarme_configured = !empty($pagarme_api_key) && !empty($pagarme_api_secret);
        $paypal_configured = !empty($paypal_client_id) && !empty($paypal_client_secret);
        $stripe_configured = !empty($stripe_publishable_key) && !empty($stripe_secret_key);
        $pp_configured = !empty($pushinpay_token);
        $efi_configured = !empty($efi_client_id) && !empty($efi_client_secret) && !empty($efi_certificate_path) && !empty($efi_pix_key);
        $beehive_configured = !empty($beehive_secret_key) && !empty($beehive_public_key);
        $hypercash_configured = !empty($hypercash_secret_key) && !empty($hypercash_public_key);

        if ($efi_configured && $msg_type !== 'error') {
            $register_result = integracoes_try_register_efi_webhook(
                $efi_client_id,
                $efi_client_secret,
                $efi_certificate_path,
                $efi_pix_key,
                $webhook_url
            );

            if (!empty($register_result['success'])) {
                $mensagem .= ' Webhook Pix registrado automaticamente na Efí.';
                $efi_webhook_registered_url = $webhook_url;
                $efi_webhook_status_message = 'Webhook Pix registrado na Efí.';
            } elseif ($msg_type === 'success') {
                $msg_type = 'warning';
                $mensagem .= ' Atenção: não foi possível registrar o webhook Pix na Efí automaticamente. ' . ($register_result['message'] ?? '');
            }
        }
        
    } catch (PDOException $e) {
        $mensagem = "Erro ao salvar: " . $e->getMessage();
        $msg_type = 'error';
    }
}

if ($efi_configured && $efi_webhook_registered_url === '') {
    $webhook_status = integracoes_get_efi_webhook_status($efi_client_id, $efi_client_secret, $efi_certificate_path, $efi_pix_key);
    if (!empty($webhook_status['success'])) {
        $efi_webhook_registered_url = $webhook_status['webhook_url'] ?? '';
        $efi_webhook_status_message = $webhook_status['message'] ?? '';
    }
}

$efi_webhook_matches_platform = !empty($efi_webhook_registered_url)
    && rtrim($efi_webhook_registered_url, '/') === rtrim($webhook_url, '/');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrações</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #07090d; /* Dark base */
        }
        
        /* Animações e Efeitos */
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        
        .animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .selected-ring { 
            ring-width: 2px; 
            ring-color: #32e768;
            background-color: rgba(50, 231, 104, 0.1);
            border-color: #32e768;
        }
        
        /* Input Styles */
        .custom-input:focus-within { box-shadow: 0 0 0 4px rgba(50, 231, 104, 0.1); border-color: #32e768; }
    </style>
</head>
<body class="min-h-screen text-white pb-20">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-4">
            <div>
                <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-white">
                    Central de Integrações
                </h1>
                <p class="mt-2 text-gray-400 text-lg">Conecte sua plataforma a ferramentas externas e automatize seus processos.</p>
            </div>
        </div>

        <!-- Mensagens Flutuantes -->
        <?php if(!empty($mensagem)): ?>
            <div id='toast-msg' class='fixed top-5 right-5 z-50 animate-fade-in flex items-center w-full max-w-xs p-4 text-gray-300 bg-dark-card rounded-lg shadow-xl border border-dark-border' role='alert'>
                <div class='inline-flex items-center justify-center flex-shrink-0 w-8 h-8 <?php echo ($msg_type == "success" ? "text-green-400 bg-green-900/30" : ($msg_type == "error" ? "text-red-400 bg-red-900/30" : ($msg_type == "warning" ? "text-yellow-400 bg-yellow-900/30" : "text-blue-400 bg-blue-900/30"))); ?> rounded-lg'>
                    <i data-lucide='<?php echo ($msg_type == "success" ? "check" : ($msg_type == "error" ? "alert-circle" : ($msg_type == "warning" ? "alert-triangle" : "info"))); ?>' class='w-5 h-5'></i>
                </div>
                <div class='ml-3 text-sm font-medium'><?php echo $mensagem; ?></div>
                <button type='button' class='ml-auto -mx-1.5 -my-1.5 bg-dark-card text-gray-400 hover:text-gray-300 rounded-lg focus:ring-2 focus:ring-dark-border p-1.5 hover:bg-dark-elevated inline-flex h-8 w-8' onclick='this.parentElement.remove()'>
                    <i data-lucide='x' class='w-4 h-4'></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Grid de Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- Card Webhooks -->
            <a href="/index?pagina=integracoes_webhooks" class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 flex flex-col justify-between h-full hover:border-[#32e768] overflow-hidden cursor-pointer">
                <!-- Background Decoration -->
                <div class="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-[#32e768]/10 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-5 mb-6">
                        <div class="h-16 w-16 rounded-2xl bg-[#32e768]/20 flex items-center justify-center border border-[#32e768]/30 shadow-sm group-hover:bg-[#32e768]/30 transition-colors">
                            <img src="https://res.cloudinary.com/hevo/image/upload/v1636351137/hevo-learn/webhooks.png" alt="Webhook" class="h-10 w-10 object-contain">
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white group-hover:text-[#32e768] transition-colors">Webhooks</h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#32e768]/20 text-[#32e768] mt-1">
                                Automação
                            </span>
                        </div>
                    </div>
                    
                    <p class="text-gray-400 text-base leading-relaxed mb-8">
                        Envie dados de vendas em tempo real para outras plataformas como Zapier, Make.com ou seu próprio sistema. Notifique eventos instantaneamente.
                    </p>
                </div>

                <div class="relative z-10 mt-auto pt-6 border-t border-dark-border">
                    <span class="flex items-center text-sm font-bold text-[#32e768] group-hover:text-[#28d15e] transition-colors">
                        Configurar Webhooks <i data-lucide="arrow-right" class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </div>
            </a>

            <!-- Card UTMfy -->
            <a href="/index?pagina=integracoes_utmfy" class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 flex flex-col justify-between h-full hover:border-[#32e768] overflow-hidden cursor-pointer">
                <!-- Background Decoration -->
                <div class="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-[#32e768]/10 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-5 mb-6">
                        <div class="h-16 w-16 rounded-2xl bg-[#32e768]/20 flex items-center justify-center border border-[#32e768]/30 shadow-sm group-hover:bg-[#32e768]/30 transition-colors">
                            <img src="https://is1-ssl.mzstatic.com/image/thumb/Purple221/v4/a5/ca/21/a5ca2115-6efd-59cd-6724-475031a69400/AppIcon-1x_U007emarketing-0-8-0-85-220-0.png/434x0w.webp" alt="UTMfy" class="h-10 w-10 object-contain rounded-md">
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white group-hover:text-[#32e768] transition-colors">UTMfy</h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#32e768]/20 text-[#32e768] mt-1">
                                Rastreamento
                            </span>
                        </div>
                    </div>
                    
                    <p class="text-gray-400 text-base leading-relaxed mb-8">
                        Integre com a UTMfy para rastrear suas campanhas de marketing (Facebook Ads, Google Ads) e descobrir a origem exata de cada venda.
                    </p>
                </div>

                <div class="relative z-10 mt-auto pt-6 border-t border-dark-border">
                    <span class="flex items-center text-sm font-bold text-[#32e768] group-hover:text-[#28d15e] transition-colors">
                        Configurar UTMfy <i data-lucide="arrow-right" class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </div>
            </a>

            <!-- Card Gateways de Pagamento -->
            <div id="card-gateways" onclick="showGatewayConfig()" class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 flex flex-col justify-between h-full hover:border-[#32e768] overflow-hidden cursor-pointer">
                <!-- Background Decoration -->
                <div class="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-[#32e768]/10 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-5 mb-6">
                        <div class="h-16 w-16 rounded-2xl bg-[#32e768]/20 flex items-center justify-center border border-[#32e768]/30 shadow-sm group-hover:bg-[#32e768]/30 transition-colors">
                            <i data-lucide="credit-card" class="w-8 h-8 text-[#32e768]"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white group-hover:text-[#32e768] transition-colors">Gateways de Pagamento</h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#32e768]/20 text-[#32e768] mt-1">
                                Pagamentos
                            </span>
                        </div>
                    </div>
                    
                    <p class="text-gray-400 text-base leading-relaxed mb-8">
                        Configure suas credenciais de Mercado Pago, PushinPay, Efí, Pagar.me, PayPal e Stripe para processar pagamentos. Gerencie suas chaves de API e métodos de recebimento.
                    </p>
                </div>

                <div class="relative z-10 mt-auto pt-6 border-t border-dark-border">
                    <span class="flex items-center text-sm font-bold text-[#32e768] group-hover:text-[#28d15e] transition-colors">
                        Configurar Gateways <i data-lucide="arrow-right" class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </div>
            </div>

            <!-- Card Evolution API -->
            <a href="/index?pagina=integracoes_evolution" class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 flex flex-col justify-between h-full hover:border-[#32e768] overflow-hidden cursor-pointer">
                <!-- Background Decoration -->
                <div class="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-[#32e768]/10 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>

                <!-- Badge Beta -->
                <div class="absolute top-4 left-4 z-20">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-yellow-500 text-black shadow-lg">
                        <i data-lucide="flask-conical" class="w-3 h-3"></i>
                        Beta
                    </span>
                </div>

                <!-- Status Badge -->
                <?php if($evolution_configured): ?>
                <div class="absolute top-4 right-4 z-20">
                    <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                        <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                    </div>
                </div>
                <?php endif; ?>

                <div class="relative z-10">
                    <div class="flex items-center gap-5 mb-6">
                        <div class="h-16 w-16 rounded-2xl bg-[#25D366]/20 flex items-center justify-center border border-[#25D366]/30 shadow-sm group-hover:bg-[#25D366]/30 transition-colors">
                            <i data-lucide="message-circle" class="w-8 h-8 text-[#25D366]"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white group-hover:text-[#32e768] transition-colors">Evolution API</h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#25D366]/20 text-[#25D366] mt-1">
                                WhatsApp
                            </span>
                        </div>
                    </div>
                    
                    <p class="text-gray-400 text-base leading-relaxed mb-8">
                        Conecte com a Evolution API para enviar notificações automáticas via WhatsApp. Avise seus clientes sobre compras aprovadas, boletos e mais.
                    </p>
                </div>

                <div class="relative z-10 mt-auto pt-6 border-t border-dark-border">
                    <span class="flex items-center text-sm font-bold text-[#32e768] group-hover:text-[#28d15e] transition-colors">
                        Configurar Mensagens <i data-lucide="arrow-right" class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </div>
            </a>

            <!-- Card API -->
            <a href="/index?pagina=integracoes_api" class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 flex flex-col justify-between h-full hover:border-[#32e768] overflow-hidden cursor-pointer">
                <div class="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-[#32e768]/10 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-5 mb-6">
                        <div class="h-16 w-16 rounded-2xl bg-[#32e768]/20 flex items-center justify-center border border-[#32e768]/30 shadow-sm group-hover:bg-[#32e768]/30 transition-colors">
                            <i data-lucide="code-2" class="w-8 h-8 text-[#32e768]"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white group-hover:text-[#32e768] transition-colors">API Interna</h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#32e768]/20 text-[#32e768] mt-1">
                                Desenvolvedor
                            </span>
                        </div>
                    </div>
                    
                    <p class="text-gray-400 text-base leading-relaxed mb-8">
                        Acesse a documentação dos endpoints da nossa API interna. Integre seu sistema diretamente para gerenciar produtos, vendas e configurações.
                    </p>
                </div>

                <div class="relative z-10 mt-auto pt-8 border-t border-dark-border">
                    <span class="flex items-center text-sm font-bold text-[#32e768] group-hover:text-[#28d15e] transition-colors">
                        Documentação API <i data-lucide="arrow-right" class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </div>
            </a>

        </div>

        <!-- Formulário de Configuração de Gateways -->
        <form action="/index?pagina=integracoes" method="post" enctype="multipart/form-data" class="space-y-8 mt-8">
            
            <!-- Seleção de Gateway (Cards) -->
            <div id="gateway-selection-cards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 hidden">
                
                <!-- Card Mercado Pago -->
                <div id="card-mp" onclick="showGateway('mp')"
                     class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 cursor-pointer overflow-hidden h-full flex flex-col justify-between">
                    
                    <div class="absolute top-0 right-0 p-6">
                         <?php if($mp_configured): ?>
                            <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-dark-elevated text-gray-500 p-1.5 rounded-full group-hover:bg-dark-card transition-colors">
                                <i data-lucide="circle" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-5 mb-4">
                        <div class="h-16 w-16 rounded-2xl bg-blue-900/20 flex items-center justify-center border border-blue-500/30 shadow-sm group-hover:bg-blue-900/30 transition-colors">
                            <img src="https://logodownload.org/wp-content/uploads/2019/06/mercado-pago-logo-1.png" alt="MP" class="h-8 object-contain">
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-[#32e768] transition-colors">Mercado Pago</h3>
                            <p class="text-gray-400 text-sm">Cartão, Boleto e Pix</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-dark-border">
                        <span class="text-sm font-semibold text-[#32e768] flex items-center group-hover:translate-x-1 transition-transform">
                            Configurar <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </span>
                    </div>
                </div>

                <!-- Card PushinPay -->
                <div id="card-pp" onclick="showGateway('pp')"
                     class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 cursor-pointer overflow-hidden h-full flex flex-col justify-between">
                    
                    <div class="absolute top-0 right-0 p-6">
                         <?php if($pp_configured): ?>
                            <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-dark-elevated text-gray-500 p-1.5 rounded-full group-hover:bg-dark-card transition-colors">
                                <i data-lucide="circle" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-5 mb-4">
                        <div class="h-16 w-16 rounded-2xl bg-[#32e768]/20 flex items-center justify-center border border-[#32e768]/30 shadow-sm group-hover:bg-[#32e768]/30 transition-colors">
                            <img src="https://play-lh.googleusercontent.com/rZ3iKAteqcYZLSnMvVW66rqqlQdRQh9JXPFdLXkcBR3VxZ0jXz6T8ARRHzGKS72GYSMB" alt="PushinPay" class="h-9 w-9 object-contain rounded-md">
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-[#32e768] transition-colors">PushinPay</h3>
                            <p class="text-gray-400 text-sm">Pix Instantâneo</p>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-dark-border">
                        <span class="text-sm font-semibold text-[#32e768] flex items-center group-hover:translate-x-1 transition-transform">
                            Configurar <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </span>
                    </div>
                </div>

                <!-- Card Efí -->
                <div id="card-efi" onclick="showGateway('efi')"
                     class="card-hover group relative bg-dark-card border-2 border-[#32e768]/50 rounded-2xl p-8 cursor-pointer overflow-hidden h-full flex flex-col justify-between">
                    
                    <!-- Badge Recomendado -->
                    <div class="absolute top-4 left-4 z-20">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-[#32e768] text-black shadow-lg">
                            <i data-lucide="star" class="w-3 h-3"></i>
                            Recomendado
                        </span>
                    </div>
                    
                    <div class="absolute top-0 right-0 p-6">
                         <?php if($efi_configured): ?>
                            <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-dark-elevated text-gray-500 p-1.5 rounded-full group-hover:bg-dark-card transition-colors">
                                <i data-lucide="circle" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-5 mb-4 mt-6">
                        <div class="h-16 w-16 rounded-2xl bg-[#32e768]/20 flex items-center justify-center border border-[#32e768]/30 shadow-sm group-hover:bg-[#32e768]/30 transition-colors">
                            <i data-lucide="zap" class="w-8 h-8 text-[#32e768]"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-[#32e768] transition-colors">Efí</h3>
                            <p class="text-gray-400 text-sm">Pix e Cartão</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-dark-border">
                        <span class="text-sm font-semibold text-[#32e768] flex items-center group-hover:translate-x-1 transition-transform">
                            Configurar <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </span>
                    </div>
                </div>

                <!-- Card Pagar.me -->
                <div id="card-pagarme" onclick="showGateway('pagarme')"
                     class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 cursor-pointer overflow-hidden h-full flex flex-col justify-between">
                    <div class="absolute top-0 right-0 p-6">
                         <?php if($pagarme_configured): ?>
                            <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-dark-elevated text-gray-500 p-1.5 rounded-full group-hover:bg-dark-card transition-colors">
                                <i data-lucide="circle" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-5 mb-4">
                        <div class="h-16 w-16 rounded-2xl bg-teal-900/20 flex items-center justify-center border border-teal-500/30 shadow-sm group-hover:bg-teal-900/30 transition-colors">
                            <i data-lucide="building-2" class="w-8 h-8 text-teal-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-[#32e768] transition-colors">Pagar.me</h3>
                            <p class="text-gray-400 text-sm">Pagarme</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-dark-border">
                        <span class="text-sm font-semibold text-[#32e768] flex items-center group-hover:translate-x-1 transition-transform">
                            Configurar <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </span>
                    </div>
                </div>

                <!-- Card PayPal -->
                <div id="card-paypal" onclick="showGateway('paypal')"
                     class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 cursor-pointer overflow-hidden h-full flex flex-col justify-between">
                    <div class="absolute top-0 right-0 p-6">
                         <?php if($paypal_configured): ?>
                            <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-dark-elevated text-gray-500 p-1.5 rounded-full group-hover:bg-dark-card transition-colors">
                                <i data-lucide="circle" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-5 mb-4">
                        <div class="h-16 w-16 rounded-2xl bg-blue-900/20 flex items-center justify-center border border-blue-500/30 shadow-sm group-hover:bg-blue-900/30 transition-colors">
                            <i data-lucide="globe" class="w-8 h-8 text-blue-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-[#32e768] transition-colors">PayPal</h3>
                            <p class="text-gray-400 text-sm">Paypal</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-dark-border">
                        <span class="text-sm font-semibold text-[#32e768] flex items-center group-hover:translate-x-1 transition-transform">
                            Configurar <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </span>
                    </div>
                </div>

                <!-- Card Stripe -->
                <div id="card-stripe" onclick="showGateway('stripe')"
                     class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 cursor-pointer overflow-hidden h-full flex flex-col justify-between">
                    <div class="absolute top-0 right-0 p-6">
                         <?php if($stripe_configured): ?>
                            <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-dark-elevated text-gray-500 p-1.5 rounded-full group-hover:bg-dark-card transition-colors">
                                <i data-lucide="circle" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-5 mb-4">
                        <div class="h-16 w-16 rounded-2xl bg-indigo-900/20 flex items-center justify-center border border-indigo-500/30 shadow-sm group-hover:bg-indigo-900/30 transition-colors">
                            <i data-lucide="diamond" class="w-8 h-8 text-indigo-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-[#32e768] transition-colors">Stripe</h3>
                            <p class="text-gray-400 text-sm">Stripe</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-dark-border">
                        <span class="text-sm font-semibold text-[#32e768] flex items-center group-hover:translate-x-1 transition-transform">
                            Configurar <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </span>
                    </div>
                </div>

                <!-- Card Beehive - DESABILITADO TEMPORARIAMENTE
                <div id="card-beehive" onclick="showGateway('beehive')"
                     class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 cursor-pointer overflow-hidden h-full flex flex-col justify-between">
                    
                    <div class="absolute top-0 right-0 p-6">
                         <?php if($beehive_configured): ?>
                            <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-dark-elevated text-gray-500 p-1.5 rounded-full group-hover:bg-dark-card transition-colors">
                                <i data-lucide="circle" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-5 mb-4">
                        <div class="h-16 w-16 rounded-2xl bg-amber-900/20 flex items-center justify-center border border-amber-500/30 shadow-sm group-hover:bg-amber-900/30 transition-colors">
                            <i data-lucide="hexagon" class="w-8 h-8 text-amber-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-[#32e768] transition-colors">Beehive</h3>
                            <p class="text-gray-400 text-sm">Cartão de Crédito</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-dark-border">
                        <span class="text-sm font-semibold text-[#32e768] flex items-center group-hover:translate-x-1 transition-transform">
                            Configurar <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </span>
                    </div>
                </div>
                -->

                <!-- Card Hypercash - DESABILITADO TEMPORARIAMENTE
                <div id="card-hypercash" onclick="showGateway('hypercash')"
                     class="card-hover group relative bg-dark-card border border-dark-border rounded-2xl p-8 cursor-pointer overflow-hidden h-full flex flex-col justify-between">
                    
                    <div class="absolute top-0 right-0 p-6">
                         <?php if($hypercash_configured): ?>
                            <div class="bg-green-900/30 text-green-300 p-1.5 rounded-full">
                                <i data-lucide="check" class="w-4 h-4 stroke-[3]"></i>
                            </div>
                        <?php else: ?>
                            <div class="bg-dark-elevated text-gray-500 p-1.5 rounded-full group-hover:bg-dark-card transition-colors">
                                <i data-lucide="circle" class="w-4 h-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-5 mb-4">
                        <div class="h-16 w-16 rounded-2xl bg-indigo-900/20 flex items-center justify-center border border-indigo-500/30 shadow-sm group-hover:bg-indigo-900/30 transition-colors">
                            <i data-lucide="credit-card" class="w-8 h-8 text-indigo-400"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-[#32e768] transition-colors">Hypercash</h3>
                            <p class="text-gray-400 text-sm">Cartão de Crédito</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-dark-border">
                        <span class="text-sm font-semibold text-[#32e768] flex items-center group-hover:translate-x-1 transition-transform">
                            Configurar <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                        </span>
                    </div>
                </div>
                -->

            </div>

            <!-- Área de Configuração (Painel Expansível) -->
            <div id="gateway-forms-container" class="hidden animate-fade-in">
                <div class="bg-dark-card rounded-2xl shadow-xl border border-dark-border overflow-hidden">
                    
                    <div class="p-8 md:p-10">
                        
                        <!-- Formulário MP -->
                        <div id="fields-mp" class="hidden gateway-section">
                            <div class="flex items-center gap-4 mb-8 border-b border-dark-border pb-6">
                                <div class="h-12 w-12 rounded-xl bg-blue-900/20 flex items-center justify-center text-blue-400 border border-blue-500/30">
                                    <i data-lucide="key" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white">Credenciais Mercado Pago</h3>
                                    <p class="text-gray-400">Insira suas chaves de produção (Live Keys).</p>
                                </div>
                            </div>

                            <div class="grid gap-6">
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Public Key</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="unlock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="mercado_pago_public_key" value="<?php echo htmlspecialchars($mercado_pago_public_key); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="APP_USR-xxxxxxxx...">
                                    </div>
                                </div>

                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Access Token</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="lock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="mercado_pago_access_token" value="<?php echo htmlspecialchars($mercado_pago_access_token); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="APP_USR-xxxxxxxx...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formulário PP -->
                        <div id="fields-pp" class="hidden gateway-section">
                            <div class="flex items-center gap-4 mb-8 border-b border-dark-border pb-6">
                                <div class="h-12 w-12 rounded-xl bg-[#32e768]/20 flex items-center justify-center text-[#32e768] border border-[#32e768]/30">
                                    <i data-lucide="zap" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white">Credenciais PushinPay</h3>
                                    <p class="text-gray-400">Token de acesso para API Pix.</p>
                                </div>
                            </div>

                            <div class="group mb-6">
                                <label class="block text-sm font-semibold text-gray-300 mb-2">API Token (Bearer)</label>
                                <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                    <i data-lucide="shield-check" class="text-gray-400 w-5 h-5 mr-3"></i>
                                    <input type="text" name="pushinpay_token" value="<?php echo htmlspecialchars($pushinpay_token); ?>" 
                                        class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                        placeholder="Cole seu token aqui...">
                                </div>
                            </div>

                            <div class="rounded-xl bg-orange-900/20 border border-orange-500/30 p-4 flex gap-4 items-start">
                                <i data-lucide="info" class="text-orange-400 w-5 h-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-bold text-orange-300">Nota Importante</h4>
                                    <p class="text-sm text-orange-200 mt-1">Este gateway processa exclusivamente pagamentos via <strong>Pix</strong>. O checkout será adaptado automaticamente.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Formulário Efí -->
                        <div id="fields-efi" class="hidden gateway-section">
                            <div class="flex items-center gap-4 mb-8 border-b border-dark-border pb-6">
                                <div class="h-12 w-12 rounded-xl bg-purple-900/20 flex items-center justify-center text-purple-400 border border-purple-500/30">
                                    <i data-lucide="zap" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white">Credenciais Efí</h3>
                                    <p class="text-gray-400">Configure sua integração com a API Efí.</p>
                                </div>
                            </div>

                            <div class="grid gap-6">
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Client ID</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="key" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="efi_client_id" value="<?php echo htmlspecialchars($efi_client_id); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="Seu Client ID da aplicação Efí">
                                    </div>
                                </div>

                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Client Secret</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="lock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="password" name="efi_client_secret" value="<?php echo htmlspecialchars($efi_client_secret); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="Seu Client Secret da aplicação Efí">
                                    </div>
                                </div>

                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Certificado P12</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="file" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="file" name="efi_certificate" accept=".p12" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-700 file:cursor-pointer">
                                    </div>
                                    <?php if (!empty($efi_certificate_path)): ?>
                                        <div class="flex items-center gap-2 mt-2">
                                            <i data-lucide="check-circle" class="text-green-400 w-4 h-4"></i>
                                            <p class="text-xs text-green-400 font-medium">Certificado carregado: <span class="text-gray-300"><?php echo htmlspecialchars(basename($efi_certificate_path)); ?></span></p>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">Envie um novo arquivo para substituir o certificado atual.</p>
                                    <?php else: ?>
                                        <p class="text-xs text-gray-400 mt-1">Faça upload do certificado P12 gerado na sua conta Efí (máximo 5MB).</p>
                                    <?php endif; ?>
                                </div>

                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Chave Pix</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="qr-code" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="efi_pix_key" value="<?php echo htmlspecialchars($efi_pix_key); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="Cole sua chave Pix (E-mail, CPF, CNPJ ou chave aleatória)">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">A chave Pix cadastrada na sua conta Efí.</p>
                                </div>

                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Identificador de Conta (Payee Code)</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="hash" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="efi_payee_code" value="<?php echo htmlspecialchars($efi_payee_code); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="Seu Identificador de conta (payee_code) da Efí">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Necessário para pagamentos via cartão de crédito. Encontre em: API > Introdução > Identificador de conta.</p>
                                </div>
                            </div>

                            <div class="rounded-xl bg-purple-900/20 border border-purple-500/30 p-4 flex gap-4 items-start mt-6">
                                <i data-lucide="info" class="text-purple-400 w-5 h-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-bold text-purple-300">Nota Importante</h4>
                                    <p class="text-sm text-purple-200 mt-1">Este gateway processa pagamentos via <strong>Pix</strong> e <strong>Cartão de Crédito</strong>. Você precisa ter uma conta Efí e gerar o certificado P12 na seção de API da sua conta.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Formulário Pagar.me -->
                        <div id="fields-pagarme" class="hidden gateway-section">
                            <div class="flex items-center gap-4 mb-8 border-b border-dark-border pb-6">
                                <div class="h-12 w-12 rounded-xl bg-teal-900/20 flex items-center justify-center text-teal-400 border border-teal-500/30">
                                    <i data-lucide="building-2" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white">Credenciais Pagar.me</h3>
                                    <p class="text-gray-400">Configure as credenciais globais para este gateway</p>
                                </div>
                            </div>
                            <div class="grid gap-6">
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">API Key (Pública)</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="unlock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="pagarme_api_key" value="<?php echo htmlspecialchars($pagarme_api_key); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="Sua API Key pública">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">API Secret (Privada)</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="lock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="password" name="pagarme_api_secret" value="<?php echo htmlspecialchars($pagarme_api_secret); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="Sua API Secret">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Webhook Secret</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="shield-check" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="pagarme_webhook_secret" value="<?php echo htmlspecialchars($pagarme_webhook_secret); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="whsec_...">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Configure no painel Pagar.me para validar webhooks.</p>
                                </div>
                            </div>
                            <div class="rounded-xl bg-teal-900/20 border border-teal-500/30 p-4 flex gap-4 items-start mt-6">
                                <i data-lucide="info" class="text-teal-400 w-5 h-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-bold text-teal-300">Nota Importante</h4>
                                    <p class="text-sm text-teal-200 mt-1">Pagar.me processa <strong>Pix</strong>, <strong>Boleto</strong> e <strong>Cartão de Crédito</strong>. Obtenha suas credenciais em: <a href="https://dashboard.pagar.me" target="_blank" class="text-teal-400 hover:underline">dashboard.pagar.me</a></p>
                                </div>
                            </div>
                        </div>

                        <!-- Formulário PayPal -->
                        <div id="fields-paypal" class="hidden gateway-section">
                            <div class="flex items-center gap-4 mb-8 border-b border-dark-border pb-6">
                                <div class="h-12 w-12 rounded-xl bg-blue-900/20 flex items-center justify-center text-blue-400 border border-blue-500/30">
                                    <i data-lucide="globe" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white">Credenciais PayPal</h3>
                                    <p class="text-gray-400">Configure as credenciais globais para este gateway</p>
                                </div>
                            </div>
                            <div class="grid gap-6">
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">API Key (Pública / Client ID)</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="unlock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="paypal_client_id" value="<?php echo htmlspecialchars($paypal_client_id); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="Seu Client ID PayPal">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">API Secret (Privada / Client Secret)</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="lock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="password" name="paypal_client_secret" value="<?php echo htmlspecialchars($paypal_client_secret); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="Seu Client Secret">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Webhook Secret</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="shield-check" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="paypal_webhook_secret" value="<?php echo htmlspecialchars($paypal_webhook_secret); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="whsec_...">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Configure no painel PayPal Developer para validar webhooks.</p>
                                </div>
                            </div>
                            <div class="rounded-xl bg-blue-900/20 border border-blue-500/30 p-4 flex gap-4 items-start mt-6">
                                <i data-lucide="info" class="text-blue-400 w-5 h-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-bold text-blue-300">Nota Importante</h4>
                                    <p class="text-sm text-blue-200 mt-1">PayPal processa pagamentos via <strong>conta PayPal</strong> e <strong>Cartão de Crédito</strong>. Obtenha suas credenciais em: <a href="https://developer.paypal.com/dashboard" target="_blank" class="text-blue-400 hover:underline">developer.paypal.com</a></p>
                                </div>
                            </div>
                        </div>

                        <!-- Formulário Stripe -->
                        <div id="fields-stripe" class="hidden gateway-section">
                            <div class="flex items-center gap-4 mb-8 border-b border-dark-border pb-6">
                                <div class="h-12 w-12 rounded-xl bg-indigo-900/20 flex items-center justify-center text-indigo-400 border border-indigo-500/30">
                                    <i data-lucide="diamond" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white">Credenciais Stripe</h3>
                                    <p class="text-gray-400">Configure as credenciais globais para este gateway</p>
                                </div>
                            </div>
                            <div class="grid gap-6">
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">API Key (Pública / Publishable Key)</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="unlock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="stripe_publishable_key" value="<?php echo htmlspecialchars($stripe_publishable_key); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="pk_live_... ou pk_test_...">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">API Secret (Privada / Secret Key)</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="lock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="password" name="stripe_secret_key" value="<?php echo htmlspecialchars($stripe_secret_key); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="sk_live_... ou sk_test_...">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Webhook Secret</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="shield-check" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="stripe_webhook_secret" value="<?php echo htmlspecialchars($stripe_webhook_secret); ?>" class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm" placeholder="whsec_...">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Configure no painel Stripe para validar webhooks.</p>
                                </div>
                            </div>
                            <div class="rounded-xl bg-indigo-900/20 border border-indigo-500/30 p-4 flex gap-4 items-start mt-6">
                                <i data-lucide="info" class="text-indigo-400 w-5 h-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-bold text-indigo-300">Nota Importante</h4>
                                    <p class="text-sm text-indigo-200 mt-1">Stripe processa <strong>Cartão de Crédito</strong>, <strong>Pix</strong> e outros métodos. Obtenha suas credenciais em: <a href="https://dashboard.stripe.com/apikeys" target="_blank" class="text-indigo-400 hover:underline">dashboard.stripe.com</a></p>
                                </div>
                            </div>
                        </div>

                        <!-- Formulário Beehive - DESABILITADO TEMPORARIAMENTE
                        <div id="fields-beehive" class="hidden gateway-section">
                            <div class="flex items-center gap-4 mb-8 border-b border-dark-border pb-6">
                                <div class="h-12 w-12 rounded-xl bg-amber-900/20 flex items-center justify-center text-amber-400 border border-amber-500/30">
                                    <i data-lucide="hexagon" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white">Credenciais Beehive</h3>
                                    <p class="text-gray-400">Configure sua integração com a API Beehive para pagamentos via Cartão de Crédito.</p>
                                </div>
                            </div>

                            <div class="grid gap-6">
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Secret Key</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="lock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="password" name="beehive_secret_key" value="<?php echo htmlspecialchars($beehive_secret_key); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="Sua Secret Key da Beehive (sk_...)">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Chave secreta para autenticação na API Beehive.</p>
                                </div>

                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Public Key</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="unlock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="beehive_public_key" value="<?php echo htmlspecialchars($beehive_public_key); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="Sua Public Key da Beehive (pk_...)">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Chave pública usada para tokenização no frontend.</p>
                                </div>
                            </div>

                            <div class="rounded-xl bg-amber-900/20 border border-amber-500/30 p-4 flex gap-4 items-start mt-6">
                                <i data-lucide="info" class="text-amber-400 w-5 h-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-bold text-amber-300">Nota Importante</h4>
                                    <p class="text-sm text-amber-200 mt-1">Este gateway processa exclusivamente pagamentos via <strong>Cartão de Crédito</strong>. Você precisa ter uma conta Beehive e obter suas credenciais na seção de API.</p>
                                </div>
                            </div>
                        </div>
                        -->

                        <!-- Formulário Hypercash - DESABILITADO TEMPORARIAMENTE
                        <div id="fields-hypercash" class="hidden gateway-section">
                            <div class="flex items-center gap-4 mb-8 border-b border-dark-border pb-6">
                                <div class="h-12 w-12 rounded-xl bg-indigo-900/20 flex items-center justify-center text-indigo-400 border border-indigo-500/30">
                                    <i data-lucide="credit-card" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white">Credenciais Hypercash</h3>
                                    <p class="text-gray-400">Configure sua integração com a API Hypercash para pagamentos via Cartão de Crédito.</p>
                                </div>
                            </div>

                            <div class="grid gap-6">
                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Credencial Secreta</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="lock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="password" name="hypercash_secret_key" value="<?php echo htmlspecialchars($hypercash_secret_key); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="Sua Credencial Secreta da Hypercash (sk_...)">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Chave secreta para autenticação na API Hypercash.</p>
                                </div>

                                <div class="group">
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Credencial Pública</label>
                                    <div class="custom-input flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated transition-all">
                                        <i data-lucide="unlock" class="text-gray-400 w-5 h-5 mr-3"></i>
                                        <input type="text" name="hypercash_public_key" value="<?php echo htmlspecialchars($hypercash_public_key); ?>" 
                                            class="w-full bg-transparent border-none focus:ring-0 text-white placeholder-gray-500 font-medium sm:text-sm"
                                            placeholder="Sua Credencial Pública da Hypercash (pk_...)">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">Chave pública usada para tokenização no frontend.</p>
                                </div>
                            </div>

                            <div class="rounded-xl bg-indigo-900/20 border border-indigo-500/30 p-4 flex gap-4 items-start mt-6">
                                <i data-lucide="info" class="text-indigo-400 w-5 h-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-bold text-indigo-300">Nota Importante</h4>
                                    <p class="text-sm text-indigo-200 mt-1">Este gateway processa exclusivamente pagamentos via <strong>Cartão de Crédito</strong>. Você precisa ter uma conta Hypercash e obter suas credenciais na seção de API.</p>
                                </div>
                            </div>
                        </div>
                        -->

                        <!-- Webhook Section (Estilo Terminal/Code) -->
                        <div class="mt-10 pt-8 border-t border-dark-border">
                            <label class="block text-sm font-semibold text-gray-300 mb-3 flex justify-between items-center">
                                <span>Webhook URL <span class="font-normal text-gray-400 ml-2 text-xs">Para notificações automáticas</span></span>
                                <span class="text-xs font-mono bg-dark-elevated text-gray-400 px-2 py-1 rounded border border-dark-border">POST</span>
                            </label>
                            
                            <div class="relative group">
                                <div class="relative bg-dark-base rounded-xl p-1 flex items-center shadow-lg overflow-hidden border border-dark-border">
                                    <div class="pl-4 pr-2 py-3 flex-1 font-mono text-sm text-[#32e768] overflow-x-auto whitespace-nowrap">
                                        <span class="text-gray-500 select-none mr-2">$</span><?php echo htmlspecialchars($webhook_url); ?>
                                        <input type="hidden" id="webhook_url" value="<?php echo htmlspecialchars($webhook_url); ?>">
                                    </div>
                                    <button type="button" onclick="copyWebhookUrl()" id="copy-webhook-btn" 
                                            class="bg-dark-elevated hover:bg-dark-card text-gray-300 hover:text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors mr-1 flex items-center gap-2 border border-dark-border">
                                        <i data-lucide="copy" class="w-4 h-4"></i> <span class="hidden sm:inline">Copiar</span>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border p-4 <?php echo $efi_webhook_matches_platform ? 'bg-green-900/20 border-green-500/30' : 'bg-amber-900/20 border-amber-500/30'; ?>">
                                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                    <div>
                                        <h4 class="text-sm font-bold <?php echo $efi_webhook_matches_platform ? 'text-green-300' : 'text-amber-300'; ?>">
                                            Webhook Pix na Efí
                                        </h4>
                                        <p class="text-sm text-gray-300 mt-1">
                                            A Efí <strong>não possui tela manual</strong> para cadastrar webhook Pix por chave. O cadastro é feito via API.
                                            Ao salvar as credenciais, a plataforma tenta registrar automaticamente.
                                        </p>
                                        <?php if (!empty($efi_webhook_registered_url)): ?>
                                            <p class="text-xs mt-2 font-mono text-gray-400 break-all">
                                                Cadastrado na Efí: <?php echo htmlspecialchars($efi_webhook_registered_url); ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="text-xs mt-2 text-amber-200">
                                                Nenhum webhook Pix encontrado na Efí para a chave <?php echo htmlspecialchars($efi_pix_key ?: 'informada'); ?>.
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <button type="submit" name="registrar_webhook_efi" value="1"
                                        class="w-full lg:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-5 rounded-lg transition duration-300 flex items-center justify-center gap-2">
                                        <i data-lucide="link" class="w-5 h-5"></i>
                                        Registrar Webhook na Efí
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Footer Actions -->
                    <div class="bg-dark-elevated px-8 py-6 border-t border-dark-border flex flex-col sm:flex-row items-center justify-end gap-4">
                        <button type="submit" name="salvar_gateways" 
                            class="w-full sm:w-auto bg-[#32e768] hover:bg-[#28d15e] text-white font-bold py-3.5 px-8 rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-5 h-5"></i>
                            Salvar Alterações
                        </button>
                    </div>

                </div>
            </div>

        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            
            // Remove toast automaticamente após 4 segundos
            const toast = document.getElementById('toast-msg');
            if(toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => toast.remove(), 500);
                }, 4000);
            }
        });

        function showGatewayConfig() {
            const selectionCards = document.getElementById('gateway-selection-cards');
            const formsContainer = document.getElementById('gateway-forms-container');
            
            selectionCards.classList.remove('hidden');
            formsContainer.classList.remove('hidden');
            
            // Scroll suave
            setTimeout(() => {
                selectionCards.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
            
            lucide.createIcons();
            
            // Auto-selecionar gateway se já estiver configurado
            const hasMp = "<?php echo $mercado_pago_access_token; ?>";
            const hasPp = "<?php echo $pushinpay_token; ?>";
            const hasEfi = "<?php echo $efi_configured ? '1' : ''; ?>";
            const hasPagarme = "<?php echo $pagarme_configured ? '1' : ''; ?>";
            const hasPaypal = "<?php echo $paypal_configured ? '1' : ''; ?>";
            const hasStripe = "<?php echo $stripe_configured ? '1' : ''; ?>";
            
            if(hasStripe) {
                setTimeout(() => showGateway('stripe'), 300);
            } else if(hasPaypal) {
                setTimeout(() => showGateway('paypal'), 300);
            } else if(hasPagarme) {
                setTimeout(() => showGateway('pagarme'), 300);
            } else if(hasEfi) {
                setTimeout(() => showGateway('efi'), 300);
            } else if(hasPp && !hasMp) {
                setTimeout(() => showGateway('pp'), 300);
            } else if (hasMp) {
                setTimeout(() => showGateway('mp'), 300);
            }
        }

        function showGateway(gateway) {
            // Visual reset
            const allCards = document.querySelectorAll('#card-mp, #card-pp, #card-efi, #card-pagarme, #card-paypal, #card-stripe');
            allCards.forEach(card => {
                card.classList.remove('selected-ring', 'border-[#32e768]', 'bg-[#32e768]/10');
                card.classList.add('border-dark-border', 'bg-dark-card');
            });

            // Active visual
            const selectedCard = document.getElementById('card-' + gateway);
            if (selectedCard) {
                selectedCard.classList.remove('border-dark-border', 'bg-dark-card');
                selectedCard.classList.add('selected-ring');
            }

            // Show container and specific form
            const container = document.getElementById('gateway-forms-container');
            container.classList.remove('hidden');
            
            document.querySelectorAll('.gateway-section').forEach(el => el.classList.add('hidden'));
            const formElement = document.getElementById('fields-' + gateway);
            if (formElement) {
                formElement.classList.remove('hidden');
            }
            
            lucide.createIcons();
            
            // Scroll suave
            if(window.innerWidth < 768) {
                setTimeout(() => {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }

        function copyWebhookUrl() {
            const webhookInput = document.getElementById('webhook_url');
            const copyBtn = document.getElementById('copy-webhook-btn');
            
            navigator.clipboard.writeText(webhookInput.value).then(() => {
                const originalContent = copyBtn.innerHTML;
                copyBtn.innerHTML = `<i data-lucide="check" class="w-4 h-4 text-[#32e768]"></i> <span class="text-[#32e768]">Copiado!</span>`;
                copyBtn.classList.remove('border-dark-border');
                copyBtn.classList.add('border-[#32e768]');
                
                lucide.createIcons();
                
                setTimeout(() => {
                    copyBtn.innerHTML = originalContent;
                    copyBtn.classList.add('border-dark-border');
                    copyBtn.classList.remove('border-[#32e768]');
                    lucide.createIcons();
                }, 2000);
            }).catch(err => {
                alert('Erro ao copiar. Tente manualmente.');
            });
        }
    </script>
</body>
</html>
