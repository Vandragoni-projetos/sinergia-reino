<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/license_helper.php';
if (isSystemActivated() && isLicenseValid()) {
    header("location: /login");
    exit;
}

$erro = '';
$sucesso = '';
if (isset($_SESSION['license_error'])) {
    $erro = $_SESSION['license_error'];
    unset($_SESSION['license_error']);
}
$licenseInfo = getLicenseInfo();
$currentKey = $licenseInfo['license_key'] ?? '';
$expiration = $licenseInfo['license_expiration'] ?? '';
$expirationFormatted = '';
if (!empty($expiration) && $expiration !== 'lifetime') {
    $date = DateTime::createFromFormat('Y-m-d', $expiration);
    if ($date) {
        $expirationFormatted = $date->format('d/m/Y');
    } else {
        $expirationFormatted = $expiration;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $activationKey = trim($_POST['activation_key'] ?? '');
    
    if (empty($activationKey)) {
        $erro = 'Por favor, insira a chave de ativação.';
    } else {
        error_log("ATIVACAO: Tentando ativar com chave: " . $activationKey);
        $result = activateLicense($activationKey);
        error_log("ATIVACAO: Resultado: " . print_r($result, true));
        
        if ($result['valid']) {
            $sucesso = $result['message'] ?? 'Sistema ativado com sucesso!';
            header("refresh:2;url=/login");
        } else {
            $erro = $result['reason'] ?? 'Chave de ativação inválida.';
        }
    }
}
include __DIR__ . '/config/load_settings.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativação do Sistema - Hub SinergIA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .modern-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background-color: #0f1419;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modern-input:focus {
            outline: none;
            background-color: #1a1f24;
            border-color: var(--accent-primary, #32e768);
            box-shadow: 0 4px 20px -2px rgba(50, 231, 104, 0.15);
        }

        .btn-primary {
            background: var(--accent-primary, #32e768);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            background: var(--accent-primary-hover, #28c75a);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(50, 231, 104, 0.3); }
            50% { box-shadow: 0 0 40px rgba(50, 231, 104, 0.5); }
        }

        .glow-animation {
            animation: pulse-glow 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center" style="background-color: #07090d;">
    
    <div class="w-full max-w-md p-8">
        <div class="text-center mb-8">
            <?php if (!empty($expiration) && $expiration !== 'lifetime'): ?>
            <!-- Licença expirada - ícone de renovação -->
            <div class="inline-flex justify-center mb-6 p-4 rounded-3xl" style="background: rgba(251, 146, 60, 0.1); border: 1px solid rgba(251, 146, 60, 0.3);">
                <i data-lucide="refresh-cw" class="w-16 h-16 text-orange-400"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Renovar Licença</h1>
            <p class="text-gray-400">Sua licença expirou. Insira uma nova chave para continuar usando o sistema.</p>
            <?php elseif (!empty($currentKey)): ?>
            <!-- Licença inválida -->
            <div class="inline-flex justify-center mb-6 p-4 rounded-3xl" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
                <i data-lucide="alert-triangle" class="w-16 h-16 text-red-400"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Licença Inválida</h1>
            <p class="text-gray-400">Sua licença foi revogada ou é inválida. Insira uma nova chave válida.</p>
            <?php else: ?>
            <!-- Primeira ativação -->
            <div class="inline-flex justify-center mb-6 p-4 rounded-3xl glow-animation" style="background: rgba(50, 231, 104, 0.1); border: 1px solid rgba(50, 231, 104, 0.2);">
                <i data-lucide="key" class="w-16 h-16" style="color: var(--accent-primary, #32e768);"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Ativação do Sistema</h1>
            <p class="text-gray-400">Insira sua chave de ativação para começar a usar o Hub SinergIA.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($expiration) && $expiration !== 'lifetime'): ?>
        <!-- Info da licença expirada -->
        <div class="bg-orange-500/10 border border-orange-500/30 text-orange-300 px-4 py-4 rounded-xl mb-6">
            <div class="flex items-center gap-3 mb-2">
                <i data-lucide="clock" class="w-5 h-5 flex-shrink-0"></i>
                <p class="text-sm font-semibold">Licença expirada em <?php echo htmlspecialchars($expirationFormatted); ?></p>
            </div>
            <p class="text-xs text-orange-400/80 ml-8">Adquira uma nova licença ou entre em contato com o suporte para renovar.</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-4 rounded-xl flex items-center gap-3 mb-6">
            <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
            <p class="text-sm"><?php echo htmlspecialchars($erro); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($sucesso)): ?>
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-4 rounded-xl flex items-center gap-3 mb-6">
            <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
            <p class="text-sm"><?php echo htmlspecialchars($sucesso); ?> Redirecionando...</p>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="activation_key" class="block text-gray-300 text-sm font-bold mb-2 ml-1">Chave de Ativação</label>
                <div class="relative">
                    <i data-lucide="key" class="input-icon w-5 h-5"></i>
                    <input type="text" 
                           name="activation_key" 
                           id="activation_key" 
                           class="modern-input"
                           placeholder="XXXXXXXX-XXXXXXXX"
                           required
                           autocomplete="off"
                           spellcheck="false">
                </div>
            </div>

            <button type="submit" 
                    class="btn-primary w-full text-white font-bold py-4 px-6 rounded-xl shadow-lg transform hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center gap-2">
                <?php if (!empty($expiration) && $expiration !== 'lifetime'): ?>
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                <span>Renovar Licença</span>
                <?php elseif (!empty($currentKey)): ?>
                <i data-lucide="key" class="w-5 h-5"></i>
                <span>Reativar Sistema</span>
                <?php else: ?>
                <i data-lucide="unlock" class="w-5 h-5"></i>
                <span>Ativar Sistema</span>
                <?php endif; ?>
            </button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-gray-500 text-sm">
                Não tem uma chave? 
                <a href="https://sinergia.club" target="_blank" class="hover:underline" style="color: var(--accent-primary, #32e768);">
                    Adquira sua licença
                </a>
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
