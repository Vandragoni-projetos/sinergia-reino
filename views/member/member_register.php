<?php
/**
 * Página de Registro Gratuito para Área de Membros
 * Permite que usuários criem conta e recebam acesso ao produto vitrine
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

// Se já está logado, redireciona
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
$sucesso = '';

// Busca produto vitrine ativo (is_showcase = 1)
$produto_vitrine = null;
try {
    $stmt = $pdo->prepare("SELECT p.*, u.nome as infoprodutor_nome FROM produtos p 
                           JOIN usuarios u ON p.usuario_id = u.id 
                           WHERE p.is_showcase = 1 AND p.tipo_entrega = 'area_membros' 
                           ORDER BY p.ordem ASC, p.id DESC
                           LIMIT 1");
    $stmt->execute();
    $produto_vitrine = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar produto vitrine: " . $e->getMessage());
}

// Processa o formulário de registro
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $client_ip = get_client_ip();
        $nome = trim($_POST["nome"] ?? '');
        $email = trim($_POST["email"] ?? '');
        $senha = trim($_POST["senha"] ?? '');
        $confirmar_senha = trim($_POST["confirmar_senha"] ?? '');
        
        // Validações
        if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
            $erro = "Por favor, preencha todos os campos.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Por favor, informe um e-mail válido.";
        } elseif (strlen($senha) < 6) {
            $erro = "A senha deve ter no mínimo 6 caracteres.";
        } elseif ($senha !== $confirmar_senha) {
            $erro = "As senhas não coincidem.";
        } else {
            // e-mail na coluna usuario é UNIQUE (qualquer tipo)
            $stmt = $pdo->prepare("SELECT id, tipo FROM usuarios WHERE usuario = ? LIMIT 1");
            $stmt->execute([$email]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                $erro = (($existente['tipo'] ?? '') === 'usuario')
                    ? "Este e-mail já está cadastrado. Faça login ou use outro e-mail."
                    : "Este e-mail já está em uso por outro tipo de conta na plataforma. Use outro e-mail para a área de membros.";
            } else {
                // Cria o usuário
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, nome, senha, tipo) VALUES (?, ?, ?, 'usuario')");
                $stmt->execute([$email, $nome, $senha_hash]);
                $novo_usuario_id = $pdo->lastInsertId();
                
                // Se existe produto vitrine, libera acesso (idempotente: já pode existir linha de compra/liberação prévia)
                if ($produto_vitrine) {
                    $stmt = $pdo->prepare("SELECT id FROM alunos_acessos WHERE aluno_email = ? AND produto_id = ? LIMIT 1");
                    $stmt->execute([$email, $produto_vitrine['id']]);
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO alunos_acessos (aluno_email, produto_id, criado_manualmente) VALUES (?, ?, 1)");
                        $stmt->execute([$email, $produto_vitrine['id']]);
                    }
                    
                    // Cria uma "venda" gratuita para registro (community_id para multi-tenant)
                    $transaction_id = 'FREE_REG_' . uniqid() . '_' . bin2hex(random_bytes(4));
                    $cid = isset($produto_vitrine['community_id']) ? (int)$produto_vitrine['community_id'] : 1;
                    $stmt = $pdo->prepare("INSERT INTO vendas (produto_id, community_id, comprador_nome, comprador_email, valor, status_pagamento, transacao_id, metodo_pagamento, email_entrega_enviado) VALUES (?, ?, ?, ?, 0, 'approved', ?, 'Registro Grátis', 1)");
                    $stmt->execute([$produto_vitrine['id'], $cid, $nome, $email, $transaction_id]);
                }
                
                $pdo->commit();
                
                // Faz login automático (mesmo padrão que login.php: token único evita enforce_single_session derrubar na 1ª página)
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $novo_usuario_id;
                $_SESSION["usuario"] = $email;
                $_SESSION["nome"] = $nome;
                $_SESSION["tipo"] = 'usuario';
                $_SESSION['is_infoprodutor'] = false;
                $st = set_user_session_token((int) $novo_usuario_id);
                if ($st !== '') {
                    $_SESSION['session_token'] = $st;
                }
                
                header("location: /member_area_dashboard");
                exit();
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro no registro (member_register): " . $e->getMessage());
        $sqlState = $e->errorInfo[0] ?? '';
        $mysqlErr = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
        if ($sqlState === '23000' || $mysqlErr === 1062) {
            $erro = "Não foi possível concluir: este e-mail ou acesso já está registrado. Tente fazer login ou use outro e-mail.";
        } else {
            $erro = "Erro ao criar conta. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta Grátis - Área de Membros</title>
    <?php include __DIR__ . '/../../config/load_settings.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style> 
        body { font-family: 'Plus Jakarta Sans', sans-serif; } 
        
        .modern-input-group { position: relative; transition: all 0.3s ease; }
        
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
        
        .modern-input::placeholder { color: #6b7280; }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            transition: color 0.3s ease;
        }

        .modern-input:focus + .input-icon,
        .modern-input:focus ~ .input-icon { color: var(--accent-primary); }

        .btn-primary {
            background: var(--accent-primary);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: var(--accent-primary-hover);
            z-index: -1;
            transition: opacity 0.3s ease;
            opacity: 0;
        }

        .btn-primary:hover::before { opacity: 1; }

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
        
        .glass-effect {
            background: rgba(15, 20, 25, 0.7);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.1);
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
                     class="w-full h-full object-cover opacity-90" alt="Background">
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
            </div>
            <?php endif; ?>
            
            <div id="notifications-wrapper" class="absolute inset-0 pointer-events-none z-20 p-8 flex flex-col justify-start items-start gap-4" style="padding-top: 8rem;"></div>

            <div class="relative z-20 mb-8 max-w-lg">
                <h1 class="text-5xl font-bold text-white mb-4 leading-tight">
                    Comece sua jornada <br>
                    <span class="text-transparent bg-clip-text" style="background-image: linear-gradient(to right, var(--accent-primary), rgba(50, 231, 104, 0.6));">gratuitamente.</span>
                </h1>
                <p class="text-gray-300 text-lg leading-relaxed">
                    Crie sua conta e tenha acesso imediato ao conteúdo exclusivo da nossa plataforma.
                </p>
            </div>
        </div>

        <!-- Coluna da Direita -->
        <div class="flex items-center justify-center p-8" style="background-color: var(--theme-bg, #07090d);">
            <div class="w-full max-w-[420px] space-y-6">
                
                <div class="text-center">
                    <div class="inline-flex justify-center mb-6 p-4 rounded-3xl">
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo" class="w-auto h-16 object-contain">
                    </div>
                    <h2 class="text-3xl font-bold text-white tracking-tight">Crie sua Conta</h2>
                    <p class="text-gray-400 mt-2">Preencha os dados abaixo para começar.</p>
                </div>

                <?php if ($produto_vitrine): ?>
                <!-- Card do Produto Vitrine -->
                <div class="bg-gradient-to-r from-purple-500/10 to-indigo-500/10 border border-purple-500/30 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg overflow-hidden bg-dark-elevated flex-shrink-0 relative">
                            <?php if ($produto_vitrine['foto']): ?>
                                <img src="/uploads/<?php echo htmlspecialchars($produto_vitrine['foto']); ?>" alt="<?php echo htmlspecialchars($produto_vitrine['nome']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i data-lucide="star" class="w-6 h-6 text-purple-400"></i>
                                </div>
                            <?php endif; ?>
                            <span class="absolute -top-1 -left-1 bg-purple-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded shadow">VITRINE</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-purple-400 font-semibold uppercase tracking-wide">Acesso Vitrine</p>
                            <p class="text-white font-bold truncate"><?php echo htmlspecialchars($produto_vitrine['nome']); ?></p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="bg-purple-500/20 text-purple-300 text-xs font-bold px-2 py-1 rounded-full flex items-center gap-1"><i data-lucide="star" class="w-3 h-3"></i> VITRINE</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($erro)): ?>
                    <div id="error-alert" class="bg-red-500 border border-red-600 text-white px-4 py-4 rounded-xl flex items-center gap-3 shadow-lg" role="alert">
                        <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0 text-white"></i>
                        <div class="flex-1">
                            <p class="text-sm font-semibold"><?php echo htmlspecialchars($erro); ?></p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-white hover:text-red-200 transition-colors">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <form id="member-register-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-5">
                    
                    <div class="modern-input-group">
                        <label for="nome" class="block text-gray-300 text-sm font-bold mb-2 ml-1">Nome Completo</label>
                        <div class="relative">
                            <i data-lucide="user" class="input-icon w-5 h-5"></i>
                            <input type="text" name="nome" id="nome" 
                                   class="modern-input" 
                                   value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" 
                                   required 
                                   placeholder="Seu nome completo"
                                   autocomplete="name">
                        </div>
                    </div>

                    <div class="modern-input-group">
                        <label for="email" class="block text-gray-300 text-sm font-bold mb-2 ml-1">E-mail</label>
                        <div class="relative">
                            <i data-lucide="mail" class="input-icon w-5 h-5" id="email-icon"></i>
                            <input type="email" name="email" id="email" 
                                   class="modern-input" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   required 
                                   placeholder="seuemail@exemplo.com"
                                   autocomplete="email">
                            <!-- Indicador de loading/status -->
                            <div id="email-status" class="absolute right-3 top-1/2 -translate-y-1/2 hidden">
                                <i data-lucide="loader-2" class="w-5 h-5 text-gray-400 animate-spin" id="email-loading"></i>
                                <i data-lucide="check-circle" class="w-5 h-5 text-green-500 hidden" id="email-ok"></i>
                                <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 hidden" id="email-error"></i>
                            </div>
                        </div>
                        <!-- Mensagem de feedback -->
                        <div id="email-feedback" class="mt-2 text-sm hidden"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="modern-input-group">
                            <label for="senha" class="block text-gray-300 text-sm font-bold mb-2 ml-1">Senha</label>
                            <div class="relative">
                                <i data-lucide="lock" class="input-icon w-5 h-5"></i>
                                <input type="password" name="senha" id="senha" 
                                       class="modern-input" 
                                       required 
                                       placeholder="Mínimo 6 caracteres"
                                       autocomplete="new-password"
                                       minlength="6">
                            </div>
                        </div>

                        <div class="modern-input-group">
                            <label for="confirmar_senha" class="block text-gray-300 text-sm font-bold mb-2 ml-1">Confirmar</label>
                            <div class="relative">
                                <i data-lucide="lock" class="input-icon w-5 h-5"></i>
                                <input type="password" name="confirmar_senha" id="confirmar_senha" 
                                       class="modern-input" 
                                       required 
                                       placeholder="Repita a senha"
                                       autocomplete="new-password"
                                       minlength="6">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary w-full text-white font-bold py-4 px-6 rounded-xl shadow-lg transform transition-all duration-300 hover:-translate-y-0.5 flex items-center justify-center gap-2 group" style="box-shadow: 0 10px 15px -3px rgba(50, 231, 104, 0.3), 0 4px 6px -2px rgba(50, 231, 104, 0.2);">
                        <span>Criar Conta Grátis</span>
                        <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </form>

                <!-- Divisor -->
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-700"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 text-gray-400" style="background-color: var(--theme-bg, #07090d);">Já tem uma conta?</span>
                    </div>
                </div>

                <!-- Link para Login -->
                <a href="/member_login" class="w-full border-2 border-gray-700 hover:border-gray-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 flex items-center justify-center gap-2 group">
                    <i data-lucide="log-in" class="w-5 h-5"></i>
                    <span>Fazer Login</span>
                </a>
            </div>
        </div>

    </div>

    <script>
        lucide.createIcons();

        const wrapper = document.getElementById('notifications-wrapper');
        const names = ['Ana S.', 'Carlos M.', 'Beatriz R.', 'João C.', 'Maria P.', 'Pedro L.'];
        const notificationImageUrl = '<?php echo htmlspecialchars($notification_image_url ?? $logo_url); ?>';

        // ========== VERIFICAÇÃO DE E-MAIL EM TEMPO REAL ==========
        let emailCheckTimeout = null;
        let emailExists = false;
        const emailInput = document.getElementById('email');
        const emailStatus = document.getElementById('email-status');
        const emailLoading = document.getElementById('email-loading');
        const emailOk = document.getElementById('email-ok');
        const emailError = document.getElementById('email-error');
        const emailFeedback = document.getElementById('email-feedback');
        const submitBtn = document.querySelector('button[type="submit"]');
        const memberRegisterForm = document.getElementById('member-register-form');
        if (memberRegisterForm) {
            memberRegisterForm.addEventListener('submit', function(e) {
                if (emailExists) {
                    e.preventDefault();
                }
            });
        }

        function showEmailStatus(type) {
            emailStatus.classList.remove('hidden');
            emailLoading.classList.add('hidden');
            emailOk.classList.add('hidden');
            emailError.classList.add('hidden');
            
            if (type === 'loading') {
                emailLoading.classList.remove('hidden');
            } else if (type === 'ok') {
                emailOk.classList.remove('hidden');
            } else if (type === 'error') {
                emailError.classList.remove('hidden');
            }
        }

        function hideEmailStatus() {
            emailStatus.classList.add('hidden');
            emailFeedback.classList.add('hidden');
        }

        function showEmailFeedback(message, type) {
            emailFeedback.classList.remove('hidden', 'text-green-400', 'text-red-400', 'text-yellow-400');
            emailFeedback.classList.add(type === 'success' ? 'text-green-400' : type === 'warning' ? 'text-yellow-400' : 'text-red-400');
            emailFeedback.innerHTML = message;
        }

        async function checkEmail(email) {
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                hideEmailStatus();
                return;
            }

            showEmailStatus('loading');

            try {
                const response = await fetch('/api/check_email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email, for_member_register: true })
                });

                const result = await response.json();

                if (result.success) {
                    if (result.exists) {
                        emailExists = true;
                        showEmailStatus('error');
                        const esc = (s) => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        const txt = result.message ? esc(result.message) : 'Este e-mail já está em uso.';
                        showEmailFeedback('<i data-lucide="alert-circle" class="w-4 h-4 inline mr-1"></i> ' + txt + ' <a href="/member_login" class="underline font-semibold" style="color: var(--accent-primary);">Fazer login</a>', 'error');
                        emailInput.classList.add('border-red-500');
                        emailInput.classList.remove('border-green-500');
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    } else {
                        emailExists = false;
                        showEmailStatus('ok');
                        showEmailFeedback('<i data-lucide="check-circle" class="w-4 h-4 inline mr-1"></i> E-mail disponível!', 'success');
                        emailInput.classList.remove('border-red-500');
                        emailInput.classList.add('border-green-500');
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                    lucide.createIcons();
                } else {
                    hideEmailStatus();
                }
            } catch (e) {
                console.error('Erro ao verificar e-mail:', e);
                hideEmailStatus();
            }
        }

        emailInput.addEventListener('input', function() {
            clearTimeout(emailCheckTimeout);
            const email = this.value.trim();
            
            // Reset visual
            this.classList.remove('border-red-500', 'border-green-500');
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            
            if (email.length > 5 && email.includes('@')) {
                showEmailStatus('loading');
                emailCheckTimeout = setTimeout(() => checkEmail(email), 500);
            } else {
                hideEmailStatus();
            }
        });

        emailInput.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                checkEmail(email);
            }
        });

        // ========== NOTIFICAÇÕES ANIMADAS ==========
        function createNotification() {
            if (!wrapper || window.innerWidth < 1024) return;
            if (wrapper.children.length > 3) wrapper.removeChild(wrapper.firstChild);

            const randomName = names[Math.floor(Math.random() * names.length)];
            const actions = ['acabou de se cadastrar', 'criou uma conta', 'começou a estudar'];
            const randomAction = actions[Math.floor(Math.random() * actions.length)];

            const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-primary').trim();
            const rgbMatch = primaryColor.match(/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i);
            let rgbValues = '50, 231, 104';
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
                        <p class="text-xs font-bold" style="color: var(--accent-primary);">Novo Membro</p>
                        <span class="text-[10px] text-gray-400 flex-shrink-0 ml-2">Agora</span>
                    </div>
                    <p class="text-sm font-extrabold text-white mt-0.5">${randomName}</p>
                    <p class="text-[10px] text-gray-400 truncate">${randomAction}</p>
                </div>
            `;

            wrapper.appendChild(notif);
            setTimeout(() => { if(notif.parentNode === wrapper) wrapper.removeChild(notif); }, 4000);
        }

        function startNotificationLoop() {
            createNotification();
            setTimeout(startNotificationLoop, Math.random() * 2000 + 1500);
        }

        if (window.innerWidth >= 1024) {
            setTimeout(startNotificationLoop, 1000);
        }
    </script>
</body>
</html>
