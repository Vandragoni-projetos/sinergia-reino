<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/license_helper.php';
require_once __DIR__ . '/../../helpers/security_helper.php';
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] == 'admin') {
        header("location: /admin"); 
        exit;
    } elseif (isset($_SESSION["tipo"]) && $_SESSION["tipo"] == 'infoprodutor') {
        header("location: /"); 
        exit;
    } else { 
        header("location: /member_area_dashboard"); 
        exit;
    }
}

$erro = '';
$email_input = '';
if (isset($_COOKIE['remember_member'])) {
    $email_input = $_COOKIE['remember_member'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $erro = "Sessão expirada ou formulário inválido. Atualize a página e tente novamente.";
    } else {
        try {
            $client_ip = get_client_ip();
            $email_input = trim($_POST["email"] ?? '');
            $rate_check = check_login_attempts($client_ip, $email_input);
            if (!$rate_check['allowed']) {
                $blocked_time = strtotime($rate_check['blocked_until']) - time();
                $minutes = ceil($blocked_time / 60);
                $erro = "Muitas tentativas de login. Tente novamente em {$minutes} minuto(s).";
                log_security_event('blocked_login_attempt', [
                    'ip' => $client_ip,
                    'email' => $email_input,
                    'reason' => $rate_check['reason']
                ]);
            } elseif (empty(trim($_POST["email"] ?? '')) || empty(trim($_POST["senha"] ?? ''))) {
                $erro = "Por favor, preencha o e-mail e a senha.";
            } else {
                $senha_input = trim($_POST["senha"]);
                $sql = "SELECT id, usuario, nome, senha, tipo FROM usuarios WHERE usuario = :email";
                
                $stmt = $pdo->prepare($sql);
                if ($stmt) {
                    $stmt->bindParam(":email", $email_input, PDO::PARAM_STR);
                    
                    if ($stmt->execute()) {
                        if ($stmt->rowCount() == 1) {
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($row && isset($row["senha"]) && password_verify($senha_input, $row["senha"])) {
                                clear_login_attempts($client_ip, $email_input);
                                if ($row["tipo"] == 'admin' || $row["tipo"] == 'infoprodutor') {
                                    $licenseCheck = checkLicenseOnLogin();
                                    if (!$licenseCheck['valid']) {
                                        $_SESSION['license_error'] = $licenseCheck['reason'] ?? 'Licença inválida ou expirada.';
                                        header("location: /ativacao");
                                        exit();
                                    } else {
                                        if (session_status() === PHP_SESSION_ACTIVE) {
                                            session_regenerate_id(true);
                                        }
                                        $_SESSION["loggedin"] = true;
                                        $_SESSION["id"] = $row["id"];
                                        $_SESSION["usuario"] = $row["usuario"]; 
                                        $_SESSION["nome"] = $row["nome"]; 
                                        $_SESSION["tipo"] = $row["tipo"];
                                        $st = set_user_session_token($row['id']);
                                        if ($st !== '') {
                                            $_SESSION['session_token'] = $st;
                                        }
                                        if (isset($_POST['remember'])) {
                                            setcookie('remember_member', $email_input, time() + (86400 * 30), "/");
                                        } else {
                                            if(isset($_COOKIE['remember_member'])) {
                                                setcookie('remember_member', "", time() - 3600, "/");
                                            }
                                        }
                                        if ($row["tipo"] == 'admin') {
                                            header("location: /admin");
                                        } else {
                                            header("location: /");
                                        }
                                        exit();
                                    }
                                } else {
                                    if (session_status() === PHP_SESSION_ACTIVE) {
                                        session_regenerate_id(true);
                                    }
                                    $_SESSION["loggedin"] = true;
                                    $_SESSION["id"] = $row["id"];
                                    $_SESSION["usuario"] = $row["usuario"]; 
                                    $_SESSION["nome"] = $row["nome"]; 
                                    $_SESSION["tipo"] = $row["tipo"];
                                    $st = set_user_session_token($row['id']);
                                    if ($st !== '') {
                                        $_SESSION['session_token'] = $st;
                                    }
                                    if (isset($_POST['remember'])) {
                                        setcookie('remember_member', $email_input, time() + (86400 * 30), "/");
                                    } else {
                                        if(isset($_COOKIE['remember_member'])) {
                                            setcookie('remember_member', "", time() - 3600, "/");
                                        }
                                    }

                                    header("location: /member_area_dashboard");
                                    exit();
                                }
                                
                            } else {
                                record_failed_login($client_ip, $email_input);
                                $erro = "E-mail ou senha incorretos. Verifique suas credenciais.";
                            }
                        } else {
                            record_failed_login($client_ip, $email_input);
                            $erro = "E-mail não cadastrado.";
                        }
                    } else {
                        $erro = "Erro no sistema. Tente novamente mais tarde.";
                    }
                    unset($stmt);
                } else {
                    $erro = "Erro ao preparar consulta. Tente novamente.";
                }
            }
        } catch (PDOException $e) {
            error_log("Erro no login de membros: " . $e->getMessage());
            $erro = "Erro no sistema. Tente novamente mais tarde.";
        } catch (Exception $e) {
            error_log("Erro geral no login de membros: " . $e->getMessage());
            $erro = "Erro no sistema. Tente novamente mais tarde.";
        }
    }
}
$fp_script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$forgot_password_api_url = (in_array($fp_script_dir, ['/', '.', ''], true) ? '' : rtrim($fp_script_dir, '/')) . '/api/forgot_password.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área de Membros - Acesso aos Cursos</title>
    <?php include __DIR__ . '/../../config/load_settings.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style> 
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
        } 
        
        .modern-input-group {
            position: relative;
            transition: all 0.3s ease;
        }
        
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
            border-color: var(--accent-primary);
            box-shadow: 0 4px 20px -2px rgba(50, 231, 104, 0.15);
            transform: translateY(-1px);
        }
        
        .modern-input::placeholder {
            color: #6b7280;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            transition: color 0.3s ease;
        }

        .modern-input:focus + .input-icon,
        .modern-input:focus ~ .input-icon {
            color: var(--accent-primary);
        }

        .btn-primary {
            background: var(--accent-primary);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--accent-primary-hover);
            z-index: -1;
            transition: opacity 0.3s ease;
            opacity: 0;
        }

        .btn-primary:hover::before {
            opacity: 1;
        }

        @keyframes float-up {
            0% { opacity: 0; transform: translateY(40px) scale(0.9); }
            10% { opacity: 1; transform: translateY(0) scale(1); }
            90% { opacity: 1; transform: translateY(0) scale(1); }
            100% { opacity: 0; transform: translateY(-40px) scale(0.9); }
        }

        .notification-card {
            animation: float-up 4s ease-in-out forwards;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .notification-card:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                0 0 20px rgba(var(--accent-primary-rgb, 50, 231, 104), 0.2);
        }
        
        .glass-effect {
            background: rgba(15, 20, 25, 0.7);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        /* Checkbox customizado */
        .custom-checkbox input:checked + div {
            background-color: var(--accent-primary) !important;
            border-color: var(--accent-primary) !important;
        }
        .custom-checkbox input:checked + div svg {
            display: block;
        }
    </style>
</head>
<body class="min-h-screen" style="background-color: var(--theme-bg, #07090d);">

    <div class="min-h-screen grid lg:grid-cols-2">
            
        <!-- Coluna da Esquerda -->
        <div class="hidden lg:flex relative flex-col justify-end p-12 overflow-hidden bg-slate-900" <?php echo !empty($login_image_url) ? 'style="background-image: url(' . htmlspecialchars($login_image_url) . '); background-size: cover; background-position: center;"' : ''; ?>>
            <?php if (!empty($login_image_url)): ?>
            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
            <?php else: ?>
            <div class="absolute inset-0 z-0">
                <img src="https://img.freepik.com/fotos-premium/estudante-universitario-sorridente-segurando-livros-e-mochila-isolado-no-fundo-branco_185193-75114.jpg" 
                     class="w-full h-full object-cover opacity-90" 
                     alt="Background">
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
            </div>
            <?php endif; ?>
            
            <div id="notifications-wrapper" class="absolute inset-0 pointer-events-none z-20 p-8 flex flex-col justify-start items-start gap-4" style="padding-top: 8rem;"></div>

            <div class="relative z-20 mb-8 max-w-lg">
                <h1 class="text-5xl font-bold text-white mb-4 leading-tight">
                    Acesse seus <br>
                    <span class="text-transparent bg-clip-text" style="background-image: linear-gradient(to right, var(--accent-primary), rgba(50, 231, 104, 0.6));">cursos exclusivos.</span>
                </h1>
                <p class="text-gray-300 text-lg leading-relaxed">
                    Sua jornada de aprendizado continua aqui. Acesse todo o conteúdo que você adquiriu.
                </p>
            </div>
        </div>

        <!-- Coluna da Direita -->
        <div class="flex items-center justify-center p-8 bg-dark-base">
            <div class="w-full max-w-[420px] space-y-8">
                
                <div class="text-center">
                    <div class="inline-flex justify-center mb-6 p-4 rounded-3xl mb-6">
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo" class="w-auto h-16 object-contain">
                    </div>
                    <h2 class="text-3xl font-bold text-white tracking-tight">Área de Membros</h2>
                    <p class="text-gray-400 mt-2">Acesse seus cursos e conteúdos exclusivos.</p>
                </div>

                <!-- Aviso sobre senha por e-mail -->
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-4 rounded-xl flex items-start gap-3 shadow-sm">
                    <i data-lucide="info" class="w-5 h-5 flex-shrink-0 text-blue-600 mt-0.5"></i>
                    <div class="text-sm">
                        <p class="font-semibold mb-1">Primeira vez aqui?</p>
                        <p class="leading-relaxed">Sua senha de acesso foi enviada para o <strong>e-mail de compra</strong>. Não esqueça de verificar a <strong>caixa de spam</strong> caso não encontre a mensagem.</p>
                    </div>
                </div>
                
                <?php if(!empty($erro)): ?>
                    <div id="error-alert" class="bg-red-500 border border-red-600 text-white px-4 py-4 rounded-xl flex items-center gap-3 shadow-lg animate-pulse mb-6" role="alert">
                        <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 text-white"></i>
                        <div class="flex-1">
                            <p class="text-sm font-semibold"><?php echo htmlspecialchars($erro); ?></p>
                        </div>
                        <button onclick="closeAlert()" class="text-white hover:text-red-200 transition-colors">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="space-y-5">
                        <div class="modern-input-group">
                            <label for="email" class="block text-gray-300 text-sm font-bold mb-2 ml-1">E-mail</label>
                            <div class="relative">
                                <i data-lucide="mail" class="input-icon w-5 h-5"></i>
                                <input type="email" name="email" id="email" 
                                       class="modern-input" 
                                       value="<?php echo htmlspecialchars($email_input); ?>" 
                                       required 
                                       placeholder="seuemail@exemplo.com"
                                       autocomplete="username">
                            </div>
                        </div>

                        <div class="modern-input-group">
                            <label for="senha" class="block text-gray-300 text-sm font-bold mb-2 ml-1">Senha</label>
                            <div class="relative">
                                <i data-lucide="lock" class="input-icon w-5 h-5"></i>
                                <input type="password" name="senha" id="senha" 
                                       class="modern-input pr-12" 
                                       required 
                                       placeholder="••••••••"
                                       autocomplete="current-password">
                                <button type="button" onclick="togglePasswordVisibility()" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors">
                                    <i data-lucide="eye" id="eye-icon" class="w-5 h-5"></i>
                                    <i data-lucide="eye-off" id="eye-off-icon" class="w-5 h-5 hidden"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Checkbox "Lembrar-me" -->
                        <div class="flex items-center justify-between">
                            <label class="custom-checkbox flex items-center gap-3 cursor-pointer group">
                                <div class="relative">
                                    <input type="checkbox" name="remember" class="peer sr-only" <?php echo isset($_COOKIE['remember_member']) ? 'checked' : ''; ?>>
                                    <div class="w-5 h-5 border-2 rounded-md transition-all duration-200 flex items-center justify-center" style="border-color: rgba(255, 255, 255, 0.1); background-color: #0f1419;" onmouseover="this.style.borderColor='var(--accent-primary)'" onmouseout="this.style.borderColor='rgba(255, 255, 255, 0.1)'">
                                        <svg class="w-3.5 h-3.5 text-white hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                </div>
                                <span class="text-sm font-medium text-gray-400 group-hover:text-gray-300 select-none">Lembrar meu acesso</span>
                            </label>
                            <button type="button" onclick="openForgotPasswordModal()" class="text-sm font-medium transition-colors hover:underline" style="color: var(--accent-primary);">
                                Esqueci minha senha
                            </button>
                        </div>
                    </div>

                    <!-- Verificação "Não sou um robô" -->
                    <div id="robot-check" class="border-2 rounded-xl p-4 transition-all duration-300 cursor-pointer hover:scale-[1.02]" style="border-color: rgba(255, 255, 255, 0.1); background-color: rgba(15, 20, 25, 0.5);" onclick="toggleRobotCheck()">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div id="robot-checkbox" class="w-8 h-8 border-2 rounded-lg transition-all duration-300 flex items-center justify-center" style="border-color: rgba(255, 255, 255, 0.2); background-color: #0f1419;">
                                    <svg id="robot-check-icon" class="w-5 h-5 text-white hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <span id="robot-text" class="text-sm font-medium text-gray-300">Não sou um robô</span>
                            </div>
                            <i data-lucide="shield" class="w-5 h-5 text-gray-500"></i>
                        </div>
                    </div>

                    <button type="submit" id="submit-btn" disabled class="btn-primary w-full text-white font-bold py-4 px-6 rounded-xl shadow-lg transform transition-all duration-300 flex items-center justify-center gap-2 group opacity-50 cursor-not-allowed" style="box-shadow: 0 10px 15px -3px rgba(50, 231, 104, 0.3), 0 4px 6px -2px rgba(50, 231, 104, 0.2);">
                        <span>Acessar Área de Membros</span>
                        <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </form>

                <!-- Divisor e Link para Registro -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-700"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-4 text-gray-400" style="background-color: var(--theme-bg, #07090d);">Ainda não tem uma conta?</span>
                        </div>
                    </div>
                    
                    <a href="/member_register" class="mt-4 w-full border-2 border-gray-700 hover:border-gray-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 flex items-center justify-center gap-2 group">
                        <i data-lucide="user-plus" class="w-5 h-5"></i>
                        <span>Criar Conta Grátis</span>
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal Esqueci Minha Senha -->
    <div id="forgot-password-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeForgotPasswordModal()"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative bg-[#0f1419] border border-white/10 rounded-2xl shadow-2xl w-full max-w-md p-8 transform transition-all">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-white">Recuperar Senha</h3>
                    <button onclick="closeForgotPasswordModal()" class="text-gray-400 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <!-- Conteúdo -->
                <div id="forgot-password-form-container">
                    <p class="text-gray-400 text-sm mb-6">
                        Digite seu e-mail abaixo e enviaremos uma nova senha de acesso.
                    </p>
                    
                    <div class="modern-input-group mb-6">
                        <label for="forgot-email" class="block text-gray-300 text-sm font-bold mb-2 ml-1">E-mail</label>
                        <div class="relative">
                            <i data-lucide="mail" class="input-icon w-5 h-5"></i>
                            <input type="email" id="forgot-email" 
                                   class="modern-input" 
                                   placeholder="seuemail@exemplo.com">
                        </div>
                    </div>
                    <input type="hidden" id="forgot-csrf" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <button type="button" onclick="submitForgotPassword()" id="forgot-submit-btn" class="btn-primary w-full text-white font-bold py-4 px-6 rounded-xl shadow-lg transform transition-all duration-300 flex items-center justify-center gap-2">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        <span>Enviar Nova Senha</span>
                    </button>
                </div>
                
                <!-- Mensagem de Sucesso -->
                <div id="forgot-password-success" class="hidden text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(34, 197, 94, 0.1);">
                        <i data-lucide="check-circle" class="w-8 h-8 text-green-500"></i>
                    </div>
                    <h4 class="text-lg font-bold text-white mb-2">E-mail Enviado!</h4>
                    <p class="text-gray-400 text-sm mb-6">
                        Se o e-mail estiver cadastrado, você receberá uma nova senha em instantes. Não esqueça de verificar a caixa de spam.
                    </p>
                    <button type="button" onclick="closeForgotPasswordModal()" class="btn-primary w-full text-white font-bold py-3 px-6 rounded-xl">
                        Fechar
                    </button>
                </div>
                
                <!-- Mensagem de Erro -->
                <div id="forgot-password-error" class="hidden mt-4 p-4 bg-red-500/10 border border-red-500/20 rounded-xl">
                    <p class="text-red-400 text-sm flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4"></i>
                        <span id="forgot-error-message">Erro ao processar solicitação.</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Verificação de robô
        let isVerified = false;

        function toggleRobotCheck() {
            const checkbox = document.getElementById('robot-checkbox');
            const checkIcon = document.getElementById('robot-check-icon');
            const robotText = document.getElementById('robot-text');
            const robotCheck = document.getElementById('robot-check');
            const submitBtn = document.getElementById('submit-btn');
            
            if (!isVerified) {
                // Ativar verificação
                isVerified = true;
                checkbox.style.backgroundColor = 'var(--accent-primary)';
                checkbox.style.borderColor = 'var(--accent-primary)';
                checkIcon.classList.remove('hidden');
                robotText.textContent = 'Verificado';
                robotText.style.color = 'var(--accent-primary)';
                robotCheck.style.borderColor = 'var(--accent-primary)';
                robotCheck.style.backgroundColor = 'rgba(50, 231, 104, 0.05)';
                
                // Habilitar botão de submit
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.add('hover:-translate-y-0.5');
                submitBtn.onmouseover = function() {
                    this.style.boxShadow = '0 10px 15px -3px rgba(50, 231, 104, 0.4), 0 4px 6px -2px rgba(50, 231, 104, 0.3)';
                };
                submitBtn.onmouseout = function() {
                    this.style.boxShadow = '0 10px 15px -3px rgba(50, 231, 104, 0.3), 0 4px 6px -2px rgba(50, 231, 104, 0.2)';
                };
            }
        }

        // Função para fechar alerta
        function closeAlert() {
            const alert = document.getElementById('error-alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }
        }

        // Auto-hide do alerta após 8 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.getElementById('error-alert');
            if (alert) {
                setTimeout(() => {
                    closeAlert();
                }, 8000);
            }
        });

        const wrapper = document.getElementById('notifications-wrapper');
        const names = ['Ana S.', 'Carlos M.', 'Beatriz R.', 'João C.', 'Maria P.', 'Pedro L.'];
        const notificationImageUrl = '<?php echo htmlspecialchars($notification_image_url); ?>';
        const actions = [
            { type: 'Novo Acesso', icon: 'play-circle', color: 'text-green-500', courses: ['Curso de Marketing Digital', 'Masterclass de Vendas', 'Treinamento Completo'] },
            { type: 'Aula Concluída', icon: 'check-circle', color: 'text-blue-500', courses: ['Módulo 1 - Fundamentos', 'Módulo 2 - Estratégias', 'Módulo 3 - Prática'] },
            { type: 'Certificado', icon: 'award', color: 'text-orange-500', courses: ['Certificação Digital', 'Curso Avançado', 'Especialização'] }
        ];

        function createNotification() {
            if (wrapper.children.length > 3) wrapper.removeChild(wrapper.firstChild);

            const randomName = names[Math.floor(Math.random() * names.length)];
            const randomAction = actions[Math.floor(Math.random() * actions.length)];
            const randomCourse = randomAction.courses[Math.floor(Math.random() * randomAction.courses.length)];

            // Obtém a cor primária para o gradiente
            const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-primary').trim();
            const rgbMatch = primaryColor.match(/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i);
            let rgbValues = '50, 231, 104'; // fallback
            if (rgbMatch) {
                rgbValues = `${parseInt(rgbMatch[1], 16)}, ${parseInt(rgbMatch[2], 16)}, ${parseInt(rgbMatch[3], 16)}`;
            }

            const notif = document.createElement('div');
            notif.className = 'notification-card glass-effect rounded-2xl p-4 flex items-center gap-4 w-72 transform transition-all shadow-xl';
            notif.style.borderLeft = '4px solid var(--accent-primary)';
            
            notif.innerHTML = `
                <div class="p-2 rounded-full flex-shrink-0" style="background: linear-gradient(135deg, rgba(${rgbValues}, 0.2), rgba(${rgbValues}, 0.1)); border: 1px solid rgba(${rgbValues}, 0.3);">
                    <img src="${notificationImageUrl}" alt="Logo" class="w-8 h-8 object-contain">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start mb-1">
                        <p class="text-xs font-bold text-white truncate" style="color: var(--accent-primary);">${randomAction.type}</p>
                        <span class="text-[10px] text-gray-400 flex-shrink-0 ml-2">Agora</span>
                    </div>
                    <p class="text-sm font-extrabold text-white mt-0.5">${randomCourse}</p>
                    <p class="text-[10px] text-gray-400 truncate">${randomName} está estudando</p>
                </div>
            `;

            wrapper.appendChild(notif);

            setTimeout(() => {
                if(notif.parentNode === wrapper) wrapper.removeChild(notif);
            }, 4000);
        }

        function startNotificationLoop() {
            createNotification();
            const nextTime = Math.random() * 2000 + 1500;
            setTimeout(startNotificationLoop, nextTime);
        }

        if (window.innerWidth >= 1024) {
            setTimeout(startNotificationLoop, 1000);
        }

        // Funções do Modal Esqueci Minha Senha
        function openForgotPasswordModal() {
            const modal = document.getElementById('forgot-password-modal');
            modal.classList.remove('hidden');
            document.getElementById('forgot-email').value = document.getElementById('email').value || '';
            document.getElementById('forgot-password-form-container').classList.remove('hidden');
            document.getElementById('forgot-password-success').classList.add('hidden');
            document.getElementById('forgot-password-error').classList.add('hidden');
            lucide.createIcons();
        }

        function closeForgotPasswordModal() {
            const modal = document.getElementById('forgot-password-modal');
            modal.classList.add('hidden');
        }

        async function submitForgotPassword() {
            const email = document.getElementById('forgot-email').value.trim();
            const submitBtn = document.getElementById('forgot-submit-btn');
            const errorDiv = document.getElementById('forgot-password-error');
            const errorMsg = document.getElementById('forgot-error-message');
            
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errorDiv.classList.remove('hidden');
                errorMsg.textContent = 'Por favor, informe um e-mail válido.';
                return;
            }
            
            errorDiv.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i><span>Enviando...</span>';
            lucide.createIcons();
            
            try {
                const csrfToken = document.getElementById('forgot-csrf').value;
                const response = await fetch(<?php echo json_encode($forgot_password_api_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ email: email, user_type: 'usuario', csrf_token: csrfToken })
                });
                
                const raw = await response.text();
                let result;
                try {
                    result = raw ? JSON.parse(raw) : {};
                } catch (parseErr) {
                    errorDiv.classList.remove('hidden');
                    errorMsg.textContent = 'Resposta inválida do servidor. Atualize a página ou tente mais tarde.';
                    return;
                }
                
                if (result.success) {
                    document.getElementById('forgot-password-form-container').classList.add('hidden');
                    document.getElementById('forgot-password-success').classList.remove('hidden');
                    lucide.createIcons();
                } else {
                    errorDiv.classList.remove('hidden');
                    errorMsg.textContent = result.error || 'Erro ao processar solicitação.';
                }
            } catch (e) {
                errorDiv.classList.remove('hidden');
                errorMsg.textContent = 'Erro de conexão. Verifique sua internet, atualize a página e tente novamente.';
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="send" class="w-5 h-5"></i><span>Enviar Nova Senha</span>';
                lucide.createIcons();
            }
        }

        // Função para mostrar/ocultar senha
        function togglePasswordVisibility() {
            const senhaInput = document.getElementById('senha');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeOffIcon = document.getElementById('eye-off-icon');
            
            if (senhaInput.type === 'password') {
                senhaInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                senhaInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }

        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeForgotPasswordModal();
            }
        });
    </script>
</body>
</html>
