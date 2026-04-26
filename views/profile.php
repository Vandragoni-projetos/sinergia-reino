<?php
require_once __DIR__ . '/../config/config.php';

// Proteção de página: verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /login");
    exit;
}

$mensagem = ''; // Variável para erros que impedem o redirecionamento
$usuario_id_logado = $_SESSION['id']; // ID do usuário logado
$upload_dir = 'uploads/';

// Garante que o diretório de uploads exista
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fetch current user data for initial display and for POST processing
$stmt_user_data = $pdo->prepare("SELECT nome, usuario, foto_perfil FROM usuarios WHERE id = ?");
$stmt_user_data->execute([$usuario_id_logado]);
$user_data_fetched = $stmt_user_data->fetch(PDO::FETCH_ASSOC);

$current_name = htmlspecialchars($user_data_fetched['nome'] ?? '');
$current_email = htmlspecialchars($user_data_fetched['usuario'] ?? ''); // 'usuario' é o campo de email
$current_foto_perfil = htmlspecialchars($user_data_fetched['foto_perfil'] ?? '');

// Initialize success messages array for JavaScript display
$profile_feedback_messages = [];

// Processar o formulário de atualização de perfil
if (isset($_POST['salvar_perfil'])) {
    $new_name = trim($_POST['nome'] ?? '');
    $remove_foto = isset($_POST['remove_foto_perfil']) && $_POST['remove_foto_perfil'] == '1';
    $foto_perfil_update = $current_foto_perfil; // Assume que a foto atual será mantida

    try {
        // --- Lógica de Upload de Foto de Perfil ---
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == UPLOAD_ERR_OK) {
            $arquivo_tmp = $_FILES['foto_perfil']['tmp_name'];
            $nome_original = $_FILES['foto_perfil']['name'];
            $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
            $allowed_img_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($extensao, $allowed_img_ext)) {
                // Deleta a foto antiga se existir
                if (!empty($current_foto_perfil) && file_exists($upload_dir . $current_foto_perfil)) {
                    unlink($upload_dir . $current_foto_perfil);
                }

                $novo_nome_foto = uniqid('profile_') . '.' . $extensao;
                if (move_uploaded_file($arquivo_tmp, $upload_dir . $novo_nome_foto)) {
                    $foto_perfil_update = $novo_nome_foto;
                    $profile_feedback_messages[] = "Foto de perfil atualizada!";
                } else {
                    $profile_feedback_messages[] = "Erro ao fazer upload da imagem.";
                }
            } else {
                $profile_feedback_messages[] = "Formato de imagem inválido. Apenas JPG, PNG, WEBP, GIF.";
            }
        } elseif ($remove_foto && !empty($current_foto_perfil)) {
            // Remove a foto existente se solicitado e se ela existir
            if (file_exists($upload_dir . $current_foto_perfil)) {
                unlink($upload_dir . $current_foto_perfil);
            }
            $foto_perfil_update = null; // Define como NULL no banco
            $profile_feedback_messages[] = "Foto de perfil removida!";
        }
        // Se nenhum arquivo foi enviado e não foi solicitado remover, mantém a foto atual

        // --- Atualiza Nome e Foto no Banco de Dados ---
        $stmt_update = $pdo->prepare("UPDATE usuarios SET nome = ?, foto_perfil = ? WHERE id = ?");
        $stmt_update->execute([$new_name, $foto_perfil_update, $usuario_id_logado]);

        // Se o nome foi alterado e não houve erro no upload de foto, adiciona mensagem
        if ($new_name !== htmlspecialchars_decode($current_name)) {
            $profile_feedback_messages[] = "Nome atualizado!";
        }

        // Se nenhuma mensagem foi adicionada (ex: sem alteração de foto e nome igual), adiciona sucesso genérico
        if (empty($profile_feedback_messages)) {
            $profile_feedback_messages[] = "Nenhuma alteração detectada ou perfil atualizado!";
        }
        
        // Atualiza os dados da sessão (nome pode ter mudado)
        $_SESSION['usuario'] = $new_name; // Atualiza o nome de exibição na sessão para refletir a mudança imediatamente
        
        // Store messages in session to be consumed by JavaScript on this page
        $_SESSION['profile_feedback_for_js'] = $profile_feedback_messages;

        // No header redirect here, JavaScript will handle it.

    } catch (PDOException $e) {
        $mensagem = "<div class='bg-red-900/20 border border-red-500/30 text-red-300 px-4 py-3 rounded relative mb-4' role='alert'>Erro ao atualizar perfil: " . $e->getMessage() . "</div>";
        // Do not redirect on PDOException, the error message should stay visible.
    }

    // After POST, re-fetch the latest data if there was no redirect (i.e., on error)
    if (!empty($mensagem)) {
        $stmt_user_data->execute([$usuario_id_logado]);
        $user_data_fetched = $stmt_user_data->fetch(PDO::FETCH_ASSOC);

        $current_name = htmlspecialchars($user_data_fetched['nome'] ?? '');
        $current_email = htmlspecialchars($user_data_fetched['usuario'] ?? '');
        $current_foto_perfil = htmlspecialchars($user_data_fetched['foto_perfil'] ?? '');
    }
}
?>

<div class="container mx-auto">
    <h1 class="text-3xl font-bold text-white mb-6">Meu Perfil</h1>

    <?php 
    // This area displays direct errors that prevented a redirect.
    // Success messages are handled by the JavaScript below.
    echo $mensagem; 
    ?>

    <div class="bg-dark-card p-8 rounded-lg shadow-md border border-[#32e768]">
        <form action="/index?pagina=profile" method="post" enctype="multipart/form-data">
            <div class="mb-8 flex flex-col items-center">
                <h2 class="text-2xl font-semibold mb-4 text-white">Sua Foto de Perfil</h2>
                
                <div class="relative w-40 h-40 rounded-full bg-dark-elevated flex items-center justify-center overflow-hidden mb-4 border-4 border-[#32e768] shadow-lg">
                    <?php if (!empty($current_foto_perfil)): ?>
                        <img src="<?php echo $upload_dir . $current_foto_perfil; ?>" alt="Foto de Perfil Atual" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i data-lucide="user-circle" class="w-24 h-24 text-gray-500"></i>
                    <?php endif; ?>
                    <label for="foto_perfil" class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 opacity-0 hover:opacity-100 transition-opacity duration-300 cursor-pointer">
                        <i data-lucide="camera" class="w-10 h-10 text-white"></i>
                        <span class="sr-only">Alterar foto de perfil</span>
                    </label>
                </div>
                
                <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" class="hidden">
                <p class="text-sm text-gray-400 mb-4">Clique na foto para alterar. Max: 2MB.</p>

                <?php if (!empty($current_foto_perfil)): ?>
                    <div class="flex items-center mb-6">
                        <input type="checkbox" id="remove_foto_perfil" name="remove_foto_perfil" value="1" class="h-4 w-4 text-red-400 focus:ring-red-500 border-dark-border rounded mr-2 bg-dark-elevated">
                        <label for="remove_foto_perfil" class="text-sm text-red-400 font-medium">Remover foto de perfil</label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-6">
                <label for="nome" class="block text-gray-300 text-sm font-semibold mb-2">Seu Nome</label>
                <input type="text" id="nome" name="nome"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#32e768] transition duration-200 text-white"
                       value="<?php echo $current_name; ?>" required placeholder="Seu nome completo">
            </div>

            <div class="mb-6">
                <label for="email" class="block text-gray-300 text-sm font-semibold mb-2">Seu E-mail (Usuário de Login)</label>
                <input type="email" id="email" name="email"
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-gray-400 cursor-not-allowed"
                       value="<?php echo $current_email; ?>" readonly disabled>
                <p class="text-xs text-gray-400 mt-1">Seu e-mail não pode ser alterado por aqui.</p>
            </div>
            
            <div class="mt-8 pt-6 border-t border-dark-border">
                <button type="submit" name="salvar_perfil" class="bg-[#32e768] text-white font-bold py-3 px-6 rounded-lg hover:bg-[#28d15e] transition duration-300">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<div id="profile-success-alert" class="hidden fixed top-4 left-1/2 -translate-x-1/2 bg-[#32e768] text-white p-4 rounded-lg shadow-xl z-50 border border-[#28d15e]"></div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();

        // PHP injects messages here if available
        const successMessages = <?php 
            echo json_encode($_SESSION['profile_feedback_for_js'] ?? []); 
            // Clear the session variable after reading it for JS
            unset($_SESSION['profile_feedback_for_js']); 
        ?>;

        if (successMessages.length > 0 && successMessages[0].length > 0) {
            const alertDiv = document.getElementById('profile-success-alert');
            alertDiv.innerHTML = successMessages.map(msg => `<p>${msg}</p>`).join(''); // Wrap each message in a paragraph
            alertDiv.classList.remove('hidden');
            
            // Add a small delay for the CSS transition to work
            setTimeout(() => {
                alertDiv.style.opacity = '1';
                alertDiv.style.transform = 'translate(-50%, 0)';
            }, 10); 

            // Redirect after 2 seconds
            setTimeout(() => {
                window.location.href = '/'; 
            }, 2000);
        }
    });
</script>
<style>
    #profile-success-alert {
        opacity: 0;
        transform: translate(-50%, -20px); /* Start slightly above for fade-in effect */
        transition: opacity 0.3s ease-out, transform 0.3s ease-out;
    }
    input[type="text"]::placeholder,
    input[type="email"]::placeholder {
        color: #6b7280;
    }
    input[type="text"],
    input[type="email"] {
        color-scheme: dark;
    }
</style>
