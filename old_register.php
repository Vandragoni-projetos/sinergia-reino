<?php
require_once __DIR__ . '/config/config.php';

// Este arquivo (register.php) é a página de registro para novos INFOPRODUTORES
// que desejam criar uma conta na plataforma principal.

// Se o usuário já estiver logado, redireciona para o painel apropriado
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] == 'admin') {
        header("location: /admin");
    } else {
        header("location: /");
    }
    exit;
}

$nome = $email = $senha = $confirm_senha = '';
$nome_err = $email_err = $senha_err = $confirm_senha_err = '';
$cadastro_sucesso = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Valida nome
    if (empty(trim($_POST["nome"]))) {
        $nome_err = "Por favor, digite seu nome completo.";
    } else {
        $nome = trim($_POST["nome"]);
    }

    // Valida e-mail
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor, digite seu e-mail.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Formato de e-mail inválido.";
    } else {
        $sql = "SELECT id FROM usuarios WHERE usuario = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
        $param_email = trim($_POST["email"]);
        if ($stmt->execute()) {
            if ($stmt->rowCount() == 1) {
                $email_err = "Este e-mail já está em uso.";
            } else {
                $email = trim($_POST["email"]);
            }
        } else {
            echo "Oops! Algo deu errado. Por favor, tente novamente mais tarde.";
        }
        unset($stmt);
    }

    // Valida senha
    if (empty(trim($_POST["senha"]))) {
        $senha_err = "Por favor, digite uma senha.";
    } elseif (strlen(trim($_POST["senha"])) < 6) {
        $senha_err = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        $senha = trim($_POST["senha"]);
    }

    // Valida confirmação
    if (empty(trim($_POST["confirm_senha"]))) {
        $confirm_senha_err = "Por favor, confirme a senha.";
    } else {
        $confirm_senha = trim($_POST["confirm_senha"]);
        if (empty($senha_err) && ($senha != $confirm_senha)) {
            $confirm_senha_err = "As senhas não coincidem.";
        }
    }

    // Insere no banco
    if (empty($nome_err) && empty($email_err) && empty($senha_err) && empty($confirm_senha_err)) {
        $sql = "INSERT INTO usuarios (nome, usuario, senha, tipo) VALUES (:nome, :usuario, :senha, 'infoprodutor')";

        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":nome", $param_nome, PDO::PARAM_STR);
            $stmt->bindParam(":usuario", $param_usuario, PDO::PARAM_STR);
            $stmt->bindParam(":senha", $param_senha, PDO::PARAM_STR);

            $param_nome = $nome;
            $param_usuario = $email;
            $param_senha = password_hash($senha, PASSWORD_DEFAULT);

            if ($stmt->execute()) {
                $user_id = $pdo->lastInsertId();
                
                // Executa hook após registro (SaaS - atribui plano free)
                require_once __DIR__ . '/helpers/plugin_hooks.php';
                require_once __DIR__ . '/helpers/plugin_loader.php';
                do_action('after_user_registration', $user_id, ['nome' => $nome, 'email' => $email]);
                
                $cadastro_sucesso = "Conta criada com sucesso! Redirecionando...";
                header("refresh:2;url=/login");
            } else {
                echo "Ops! Algo deu errado. Por favor, tente novamente mais tarde.";
            }
            unset($stmt);
        }
    }
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Comece Agora</title>
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
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            color: #1e293b;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modern-input:focus {
            outline: none;
            background-color: #ffffff;
            border-color: #2DD05E;
            box-shadow: 0 4px 20px -2px rgba(249, 115, 22, 0.15);
            transform: translateY(-1px);
        }
        
        /* Estado de Erro no Input */
        .modern-input.error {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        .modern-input.error:focus {
            box-shadow: 0 4px 20px -2px rgba(239, 68, 68, 0.15);
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
            color: #2DD05E;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2DD05E 0%, #2DD05E 100%);
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
            background: linear-gradient(135deg, #2DD05E 0%, #2DD05E 100%);
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
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <div class="min-h-screen grid lg:grid-cols-2">
            
        <!-- Coluna da Esquerda: Marketing e Animações -->
        <div class="hidden lg:flex relative flex-col justify-end p-12 overflow-hidden bg-slate-900 order-2 lg:order-1">
            <div class="absolute inset-0 z-0">
                <img src="https://img.freepik.com/fotos-premium/cabelo-encaracolado-de-jovem-feliz-sorrindo-e-rindo-ela-esta-feliz-em-estudio-isolado-com-solido-brilhante_39704-6416.jpg" 
                     class="w-full h-full object-cover opacity-90" 
                     alt="Background">
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>
            </div>
            
            <div id="notifications-wrapper" class="absolute inset-0 pointer-events-none z-10 p-8 flex flex-col justify-center items-start gap-4"></div>

            <div class="relative z-20 mb-8 max-w-lg">
                <div class="inline-block px-3 py-1 mb-4 rounded-full bg-green-500/20 border border-green-500/30 backdrop-blur-md">
                    <span class="text-green-400 text-xs font-bold tracking-wider uppercase">Junte-se à Elite</span>
                </div>
                <h1 class="text-5xl font-bold text-white mb-4 leading-tight">
                    Comece a vender <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-green-400 to-green-200">em minutos.</span>
                </h1>
                <p class="text-gray-300 text-lg leading-relaxed">
                    Crie sua conta gratuitamente e descubra porque somos a escolha número 1 dos infoprodutores.
                </p>
            </div>
        </div>

        <!-- Coluna da Direita: Formulário -->
        <div class="flex items-center justify-center p-8 bg-white order-1 lg:order-2">
            <div class="w-full max-w-[480px] space-y-6">
                
                <div class="text-center">
                    <div class="inline-flex justify-center mb-6 p-4 rounded-3xl bg-green-50 mb-4">
                        <img src="https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png" alt="Logo" class="w-auto h-12 object-contain">
                    </div>
                    <h2 class="text-3xl font-bold text-slate-900 tracking-tight">Crie sua Conta</h2>
                    <p class="text-slate-500 mt-2">Preencha os dados abaixo para começar.</p>
                </div>
                
                <!-- Mensagem de Sucesso -->
                <?php if(!empty($cadastro_sucesso)): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center gap-3 animate-pulse">
                        <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($cadastro_sucesso); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Mensagens de Erro Globais (opcional, já que validamos inline, mas bom para garantir) -->
                <?php if((!empty($nome_err) || !empty($email_err) || !empty($senha_err) || !empty($confirm_senha_err)) && empty($cadastro_sucesso)): ?>
                    <div class="bg-red-50 border border-red-100 text-red-600 px-4 py-3 rounded-xl flex items-center gap-3">
                        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
                        <div class="text-sm font-medium">
                            Verifique os campos destacados abaixo.
                        </div>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
                    
                    <!-- Campo Nome -->
                    <div class="modern-input-group">
                        <label for="nome" class="block text-slate-700 text-sm font-bold mb-1 ml-1">Nome Completo</label>
                        <div class="relative">
                            <i data-lucide="user" class="input-icon w-5 h-5"></i>
                            <input type="text" name="nome" id="nome" 
                                   class="modern-input <?php echo (!empty($nome_err)) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($nome); ?>" 
                                   required 
                                   placeholder="Seu nome completo">
                        </div>
                        <?php if(!empty($nome_err)): ?>
                            <p class="text-red-500 text-xs mt-1 ml-1"><?php echo $nome_err; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Campo Email -->
                    <div class="modern-input-group">
                        <label for="email" class="block text-slate-700 text-sm font-bold mb-1 ml-1">E-mail</label>
                        <div class="relative">
                            <i data-lucide="mail" class="input-icon w-5 h-5"></i>
                            <input type="email" name="email" id="email" 
                                   class="modern-input <?php echo (!empty($email_err)) ? 'error' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($email); ?>" 
                                   required 
                                   placeholder="seuemail@exemplo.com">
                        </div>
                        <?php if(!empty($email_err)): ?>
                            <p class="text-red-500 text-xs mt-1 ml-1"><?php echo $email_err; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Grid para Senhas (em telas maiores ficam lado a lado) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Campo Senha -->
                        <div class="modern-input-group">
                            <label for="senha" class="block text-slate-700 text-sm font-bold mb-1 ml-1">Senha</label>
                            <div class="relative">
                                <i data-lucide="lock" class="input-icon w-5 h-5"></i>
                                <input type="password" name="senha" id="senha" 
                                       class="modern-input <?php echo (!empty($senha_err)) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($senha); ?>" 
                                       required 
                                       placeholder="Mínimo 6 caracteres">
                            </div>
                            <?php if(!empty($senha_err)): ?>
                                <p class="text-red-500 text-xs mt-1 ml-1"><?php echo $senha_err; ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Campo Confirmar Senha -->
                        <div class="modern-input-group">
                            <label for="confirm_senha" class="block text-slate-700 text-sm font-bold mb-1 ml-1">Confirmar</label>
                            <div class="relative">
                                <i data-lucide="lock-keyhole" class="input-icon w-5 h-5"></i>
                                <input type="password" name="confirm_senha" id="confirm_senha" 
                                       class="modern-input <?php echo (!empty($confirm_senha_err)) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($confirm_senha); ?>" 
                                       required 
                                       placeholder="Repita a senha">
                            </div>
                             <?php if(!empty($confirm_senha_err)): ?>
                                <p class="text-red-500 text-xs mt-1 ml-1"><?php echo $confirm_senha_err; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary w-full text-white font-bold py-4 px-6 rounded-xl shadow-lg shadow-green-500/30 hover:shadow-green-500/40 transform hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center gap-2 group mt-6">
                        <span>Criar Conta Grátis</span>
                        <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </form>
                
                <div class="pt-6 border-t border-slate-100 text-center">
                    <p class="text-slate-500 text-sm">
                        Já tem uma conta? 
                        <a href="/login" class="text-green-600 font-semibold hover:text-green-700 transition-colors">Fazer Login</a>
                    </p>
                </div>
            </div>
        </div>

    </div>

    <script>
        lucide.createIcons();

        // Mesma lógica de notificações da tela de login para consistência
        const wrapper = document.getElementById('notifications-wrapper');
        const names = ['Gabriel S.', 'Amanda M.', 'Lucas R.', 'Beatriz C.', 'João P.', 'Fernanda L.'];
        const actions = [
            { type: 'Nova Conta', icon: 'user-plus', color: 'text-green-500', valueRange: [0, 0] }, // Exclusivo para página de registro
            { type: 'Venda Aprovada', icon: 'check-circle', color: 'text-green-500', valueRange: [47, 297] },
            { type: 'PIX Gerado', icon: 'qr-code', color: 'text-blue-500', valueRange: [97, 197] }
        ];

        function formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
        }

        function createNotification() {
            if (wrapper.children.length > 3) wrapper.removeChild(wrapper.firstChild);

            const randomName = names[Math.floor(Math.random() * names.length)];
            const randomAction = actions[Math.floor(Math.random() * actions.length)];
            
            let valueText = "";
            if(randomAction.type === 'Nova Conta') {
                valueText = "Acabou de se registrar";
            } else {
                const randomValue = Math.floor(Math.random() * (randomAction.valueRange[1] - randomAction.valueRange[0]) + randomAction.valueRange[0]) + 0.90;
                valueText = formatCurrency(randomValue);
            }

            const notif = document.createElement('div');
            notif.className = 'notification-card glass-effect rounded-2xl p-4 flex items-center gap-4 w-72 transform transition-all shadow-xl border-l-4 border-green-500';
            
            notif.innerHTML = `
                <div class="bg-white/50 p-2 rounded-full">
                    <img src="https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png" class="w-8 h-8 object-contain">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <p class="text-xs font-bold text-slate-800 truncate">${randomAction.type}</p>
                        <span class="text-[10px] text-slate-500">Agora</span>
                    </div>
                    <p class="text-sm font-extrabold text-slate-900 mt-0.5">${valueText}</p>
                    <p class="text-[10px] text-slate-500 truncate">${randomName}</p>
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
    </script>
</body>
</html>