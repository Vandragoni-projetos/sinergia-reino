<?php
// Inicia o buffer de saída no início do script para capturar qualquer saída indesejada,
// como espaços em branco antes da tag <?php ou de arquivos incluídos.
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Ativar log de erros detalhado (em produção, os logs verbosos são controlados por APP_DEBUG)
error_reporting(E_ALL); // Exibe todos os erros no log
ini_set('display_errors', 0); // DESABILITAR exibição de erros no navegador para APIs
ini_set('log_errors', 1); // Habilita o log de erros
ini_set('error_log', __DIR__ . '/../admin_api_errors.log'); // Opcional: log personalizado para esta API, ou use o padrão do PHP

$adminApiDebug = (defined('APP_DEBUG') && APP_DEBUG);

if ($adminApiDebug) {
    error_log("ADMIN_API: Script iniciado.");
}

// CORREÇÃO: Ajustar os caminhos para o PHPMailer com base na informação do usuário.
// O usuário informou que a pasta 'PHPMailer' está diretamente em 'GatewayPro 10000/'.
// __DIR__ garante um caminho absoluto.
$phpmailer_path = __DIR__ . '/../PHPMailer/src/';

if (file_exists($phpmailer_path . 'Exception.php')) {
    require_once $phpmailer_path . 'Exception.php';
    if ($adminApiDebug) {
        error_log("ADMIN_API: Exception.php carregado com sucesso.");
    }
} else {
    error_log("ADMIN_API: ERRO: Exception.php não encontrado em " . $phpmailer_path . 'Exception.php');
}

if (file_exists($phpmailer_path . 'PHPMailer.php')) {
    require_once $phpmailer_path . 'PHPMailer.php';
    if ($adminApiDebug) {
        error_log("ADMIN_API: PHPMailer.php carregado com sucesso.");
    }
} else {
    error_log("ADMIN_API: ERRO: PHPMailer.php não encontrado em " . $phpmailer_path . 'PHPMailer.php');
}

if (file_exists($phpmailer_path . 'SMTP.php')) {
    require_once $phpmailer_path . 'SMTP.php';
    if ($adminApiDebug) {
        error_log("ADMIN_API: SMTP.php carregado com sucesso.");
    }
} else {
    error_log("ADMIN_API: ERRO: SMTP.php não encontrado em " . $phpmailer_path . 'SMTP.php');
}


try {
    require_once __DIR__ . '/../config/config.php';
    if ($adminApiDebug) {
        error_log("ADMIN_API: config.php carregado com sucesso.");
    }

    // Verificação de segurança: Apenas admins logados podem acessar esta API
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo'] !== 'admin') {
        http_response_code(403);
        // Limpa o buffer antes de enviar o JSON
        ob_clean();
        echo json_encode(['error' => 'Acesso não autorizado']);
        exit;
    }

    $action = $_GET['action'] ?? '';
    if ($adminApiDebug) {
        error_log("ADMIN_API: Ação recebida: " . $action); // Log da ação recebida
    }

    // Função auxiliar para obter configurações SMTP, incluindo a senha do BD se não fornecida
    function getSmtpConfigFromRequest($pdo, $input_data) {
        global $adminApiDebug;

        $smtp_config = [
            'host' => $input_data['smtp_host'] ?? '',
            'port' => (int)($input_data['smtp_port'] ?? 587),
            'username' => $input_data['smtp_username'] ?? '',
            'encryption' => $input_data['smtp_encryption'] ?? 'tls',
            'from_email' => $input_data['smtp_from_email'] ?? '',
            'from_name' => $input_data['smtp_from_name'] ?? 'Hub SinergIA',
        ];

        if (!empty($adminApiDebug)) {
            error_log("ADMIN_API: getSmtpConfigFromRequest - input_data['smtp_password'] está vazio: " . (empty($input_data['smtp_password']) ? 'true' : 'false'));
        }

        // Se a senha não foi fornecida no POST (frontend deixou em branco), busca a do BD
        if (empty($input_data['smtp_password'])) {
            $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'smtp_password'");
            $db_password = $stmt->fetchColumn() ?? '';
            $smtp_config['password'] = $db_password;
            if (!empty($adminApiDebug)) {
                error_log("ADMIN_API: getSmtpConfigFromRequest - Senha buscada do BD (comprimento: " . strlen($db_password) . ")");
                if (empty($db_password)) {
                    error_log("ADMIN_API: getSmtpConfigFromRequest - Aviso: Senha 'smtp_password' está vazia no banco de dados.");
                }
            }
        } else {
            $smtp_config['password'] = $input_data['smtp_password'];
            if (!empty($adminApiDebug)) {
                error_log("ADMIN_API: getSmtpConfigFromRequest - Senha do input (comprimento: " . strlen($input_data['smtp_password']) . ")");
            }
        }
        return $smtp_config;
    }


    if ($action == 'get_admin_dashboard_data') {
        $response = [
            'kpis' => [],
            'chart' => [],
            'top_products' => [],
            'recent_users' => [],
            'top_sellers' => [] // Novo array para o ranking de vendedores
        ];

        // --- KPIs ---
        // ALTERAÇÃO: Contar usuários do tipo 'infoprodutor'
        $response['kpis']['total_usuarios'] = $pdo->query("SELECT COUNT(id) FROM usuarios WHERE tipo = 'infoprodutor'")->fetchColumn();
        $response['kpis']['produtos_ativos'] = $pdo->query("SELECT COUNT(id) FROM produtos")->fetchColumn();
        
        $stmt_vendas = $pdo->query("SELECT COUNT(id) as total, COALESCE(SUM(valor), 0) as faturamento FROM vendas WHERE status_pagamento = 'approved'");
        $vendas_data = $stmt_vendas->fetch(PDO::FETCH_ASSOC);
        $response['kpis']['vendas_aprovadas'] = $vendas_data['total'];
        $response['kpis']['faturamento_total'] = $vendas_data['faturamento'];

        // --- Dados do Gráfico (Últimos 30 dias) ---
        $chart_labels = [];
        $chart_data_template = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chart_labels[] = date('d/m', strtotime($date));
            $chart_data_template[$date] = 0;
        }

        $sql_chart = "SELECT CAST(data_venda AS DATE) as dia, SUM(valor) as total_dia 
                      FROM vendas 
                      WHERE status_pagamento = 'approved' AND data_venda >= CURDATE() - INTERVAL 29 DAY 
                      GROUP BY dia ORDER BY dia ASC";
        $stmt_chart = $pdo->query($sql_chart);
        $vendas_chart_data = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($vendas_chart_data as $dia => $total) {
            if (array_key_exists($dia, $chart_data_template)) {
                $chart_data_template[$dia] = (float)$total;
            }
        }
        $response['chart']['labels'] = $chart_labels;
        $response['chart']['data'] = array_values($chart_data_template);

        // --- Produtos Mais Vendidos ---
        $sql_top_products = "SELECT p.nome, p.foto, COUNT(v.id) as total_vendas, SUM(v.valor) as faturamento_total
                             FROM vendas v
                             JOIN produtos p ON v.produto_id = p.id
                             WHERE v.status_pagamento = 'approved'
                             GROUP BY v.produto_id, p.nome, p.foto
                             ORDER BY total_vendas DESC, faturamento_total DESC
                             LIMIT 5";
        $response['top_products'] = $pdo->query($sql_top_products)->fetchAll(PDO::FETCH_ASSOC);

        // --- Usuários Recentes ---
        $sql_recent_users = "SELECT id, usuario, nome, telefone, tipo FROM usuarios ORDER BY id DESC LIMIT 5";
        $response['recent_users'] = $pdo->query($sql_recent_users)->fetchAll(PDO::FETCH_ASSOC);
        
        // --- NOVO: Ranking de Vendedores ---
        $sql_top_sellers = "SELECT 
                                u.id, 
                                u.usuario, 
                                u.nome, 
                                u.foto_perfil, 
                                COUNT(v.id) as total_vendas, 
                                SUM(v.valor) as faturamento_total
                            FROM vendas v
                            JOIN produtos p ON v.produto_id = p.id
                            JOIN usuarios u ON p.usuario_id = u.id
                            WHERE v.status_pagamento = 'approved'
                            GROUP BY u.id, u.usuario, u.nome, u.foto_perfil
                            ORDER BY faturamento_total DESC
                            LIMIT 5";
        $response['top_sellers'] = $pdo->query($sql_top_sellers)->fetchAll(PDO::FETCH_ASSOC);

        error_log("ADMIN_API: Preparing JSON response for action: get_admin_dashboard_data");
        // Limpa o buffer antes de enviar o JSON
        ob_clean();
        echo json_encode($response);
        exit;
    } 
    
    // NOVO: GET_USERS
    elseif ($action == 'get_users') {
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $role = $_GET['role'] ?? 'all'; // Capture the role filter

        $where_conditions = [];
        $params = [];

        // Apply search filter
        // Alias 'usuarios' as 'u' for consistency
        if (!empty($search)) {
            $where_conditions[] = "(u.nome LIKE :search OR u.usuario LIKE :search)";
            $params[':search'] = "%" . $search . "%";
        }

        // ALTERAÇÃO: Lógica de filtro de função atualizada
        // Apply role filter
        switch ($role) {
            case 'infoproducer':
                // Um infoprodutor agora é 'infoprodutor'
                $where_conditions[] = "u.tipo = 'infoprodutor'";
                break;
            case 'client':
                // Um cliente agora é 'usuario'
                $where_conditions[] = "u.tipo = 'usuario'";
                break;
            case 'all':
                // 'all' inclui admin, infoprodutor, e usuario (cliente)
                // Nenhuma condição extra necessária.
                break;
            default:
                // Se um papel inválido for passado, o padrão é 'all' (sem condições extras)
                break;
        }
        // FIM DA ALTERAÇÃO

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        // Contar total de registros
        // Use alias 'u' for 'usuarios' table
        $stmt_count = $pdo->prepare("SELECT COUNT(u.id) FROM usuarios u {$where_clause}");
        $stmt_count->execute($params);
        $total_records = $stmt_count->fetchColumn();
        $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

        // Buscar usuários
        // Use alias 'u' for 'usuarios' table
        $sql = "SELECT u.id, u.usuario, u.nome, u.telefone, u.tipo FROM usuarios u {$where_clause} ORDER BY u.id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("ADMIN_API: Preparing JSON response for action: get_users with role: {$role}");
        // Limpa o buffer antes de enviar o JSON
        ob_clean();
        echo json_encode([
            'users' => $users,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $total_pages,
                'totalRecords' => $total_records
            ]
        ]);
        exit;
    }

    // NOVO: GET_USER_DETAILS
    elseif ($action == 'get_user_details') {
        $user_id = $_GET['id'] ?? null;
        if (!$user_id) {
            http_response_code(400);
            error_log("ADMIN_API: Erro (get_user_details): ID do usuário ausente.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'ID do usuário ausente.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, usuario, nome, telefone, tipo FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("ADMIN_API: Preparing JSON response for action: get_user_details");
        // Limpa o buffer antes de enviar o JSON
        ob_clean();
        if ($user) {
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Usuário não encontrado.']);
        }
        exit;
    }

    // NOVO: CREATE_USER
    elseif ($action == 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ADMIN_API: Erro ao decodificar JSON para 'create_user': " . json_last_error_msg());
            http_response_code(400);
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Dados JSON inválidos.']);
            exit;
        }

        $nome = trim($input['nome'] ?? '');
        $email = trim($input['email'] ?? '');
        $telefone = trim($input['telefone'] ?? '');
        $senha = trim($input['senha'] ?? '');
        // ALTERAÇÃO: O padrão agora é 'infoprodutor' para corresponder ao formulário do admin
        $tipo = trim($input['tipo'] ?? 'infoprodutor');

        if (empty($nome) || empty($email) || empty($tipo)) {
            http_response_code(400);
            error_log("ADMIN_API: Erro (create_user): Nome, e-mail e tipo são obrigatórios.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Nome, e-mail e tipo são obrigatórios.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            error_log("ADMIN_API: Erro (create_user): Formato de e-mail inválido.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Formato de e-mail inválido.']);
            exit;
        }

        // Verifica se o e-mail já existe
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :email");
        $stmt_check->bindParam(':email', $email);
        $stmt_check->execute();
        if ($stmt_check->rowCount() > 0) {
            http_response_code(409); // Conflict
            error_log("ADMIN_API: Erro (create_user): E-mail já cadastrado.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Este e-mail já está cadastrado.']);
            exit;
        }

        // Gera uma senha padrão se não for fornecida
        if (empty($senha)) {
            $senha = bin2hex(random_bytes(8)); // Senha aleatória
            error_log("ADMIN_API: (create_user): Senha padrão gerada para novo usuário.");
        }
        $hashed_password = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, usuario, telefone, senha, tipo) VALUES (:nome, :email, :telefone, :senha, :tipo)");
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->bindParam(':senha', $hashed_password);
        $stmt->bindParam(':tipo', $tipo);

        error_log("ADMIN_API: Preparing JSON response for action: create_user");
        // Limpa o buffer antes de enviar o JSON
        ob_clean();
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Usuário criado com sucesso!', 'id' => $pdo->lastInsertId()]);
        } else {
            http_response_code(500);
            error_log("ADMIN_API: Erro (create_user): Erro ao criar usuário no banco de dados.");
            echo json_encode(['error' => 'Erro ao criar usuário no banco de dados.']);
        }
        exit;
    }

    // NOVO: UPDATE_USER
    elseif ($action == 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ADMIN_API: Erro ao decodificar JSON para 'update_user': " . json_last_error_msg());
            http_response_code(400);
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Dados JSON inválidos.']);
            exit;
        }

        $user_id = $input['user_id'] ?? null;
        $nome = trim($input['nome'] ?? '');
        $email = trim($input['email'] ?? ''); // Email é importante para saber qual usuário atualizar
        $telefone = trim($input['telefone'] ?? '');
        $senha = trim($input['senha'] ?? '');
        $tipo = trim($input['tipo'] ?? 'usuario'); // O padrão 'usuario' (cliente) é seguro aqui

        if (empty($user_id) || empty($nome) || empty($email) || empty($tipo)) {
            http_response_code(400);
            error_log("ADMIN_API: Erro (update_user): ID do usuário, nome, e-mail e tipo são obrigatórios.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'ID do usuário, nome, e-mail e tipo são obrigatórios.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            error_log("ADMIN_API: Erro (update_user): Formato de e-mail inválido.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Formato de e-mail inválido.']);
            exit;
        }

        // Verifica se o usuário a ser editado existe
        $stmt_check = $pdo->prepare("SELECT id, tipo FROM usuarios WHERE id = :id");
        $stmt_check->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt_check->execute();
        $existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$existing_user) {
            http_response_code(404);
            error_log("ADMIN_API: Erro (update_user): Usuário não encontrado para atualização (ID: $user_id).");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Usuário não encontrado para atualização.']);
            exit;
        }
        
        // Impede que um admin edite a si mesmo para um tipo não-admin
        if ($user_id == $_SESSION['id'] && $existing_user['tipo'] === 'admin' && $tipo !== 'admin') {
             http_response_code(403);
             error_log("ADMIN_API: Erro (update_user): Tentativa de alterar o tipo do próprio admin (ID: $user_id).");
             ob_clean();
             echo json_encode(['error' => 'Não é permitido alterar o tipo do próprio usuário administrador.']);
             exit;
        }


        $update_fields = ['nome = :nome', 'usuario = :email', 'telefone = :telefone', 'tipo = :tipo']; // Adicionado 'usuario = :email'
        $params = [
            ':nome' => $nome,
            ':email' => $email, // Adicionado :email
            ':telefone' => $telefone,
            ':tipo' => $tipo,
            ':id' => $user_id
        ];

        if (!empty($senha)) {
            // Se uma nova senha for fornecida, hash e adicione ao update
            $hashed_password = password_hash($senha, PASSWORD_DEFAULT);
            $update_fields[] = 'senha = :senha';
            $params[':senha'] = $hashed_password;
            error_log("ADMIN_API: (update_user): Senha atualizada para usuário ID: $user_id.");
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $update_fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        error_log("ADMIN_API: Preparing JSON response for action: update_user");
        // Limpa o buffer antes de enviar o JSON
        ob_clean();
        if ($stmt->execute($params)) {
            // Se o admin logado atualizou o próprio e-mail, atualiza a sessão
            if ($user_id == $_SESSION['id'] && $existing_user['tipo'] === 'admin') {
                $_SESSION['usuario'] = $email;
                error_log("ADMIN_API: E-mail do admin logado atualizado na sessão para: " . $email);
            }
            echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso!']);
        } else {
            http_response_code(500);
            error_log("ADMIN_API: Erro (update_user): Erro ao atualizar usuário no banco de dados (ID: $user_id).");
            echo json_encode(['error' => 'Erro ao atualizar usuário no banco de dados.']);
        }
        exit;
    }

    // NOVO: DELETE_USER
    elseif ($action == 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ADMIN_API: Erro ao decodificar JSON para 'delete_user': " . json_last_error_msg());
            http_response_code(400);
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Dados JSON inválidos.']);
            exit;
        }

        $user_id = $input['user_id'] ?? null;

        if (empty($user_id)) {
            http_response_code(400);
            error_log("ADMIN_API: Erro (delete_user): ID do usuário ausente.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'ID do usuário ausente.']);
            exit;
        }

        // Impede que o próprio admin logado seja deletado
        if ($user_id == $_SESSION['id']) {
            http_response_code(403);
            error_log("ADMIN_API: Erro (delete_user): Tentativa de deletar o próprio usuário administrador (ID: $user_id).");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['error' => 'Não é permitido deletar o próprio usuário administrador.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);

        error_log("ADMIN_API: Preparing JSON response for action: delete_user");
        // Limpa o buffer antes de enviar o JSON
        ob_clean();
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Usuário deletado com sucesso!']);
            } else {
                http_response_code(404);
                error_log("ADMIN_API: Erro (delete_user): Usuário não encontrado para deletar (ID: $user_id).");
                echo json_encode(['error' => 'Usuário não encontrado.']);
            }
        } else {
            http_response_code(500);
            error_log("ADMIN_API: Erro (delete_user): Erro ao deletar usuário no banco de dados (ID: $user_id).");
            echo json_encode(['error' => 'Erro ao deletar usuário no banco de dados.']);
        }
        exit;
    }

    // NOVO: Ação para obter configurações de e-mail e entrega
    elseif ($action === 'get_email_settings') {
        error_log("ADMIN_API: Recebida ação get_email_settings.");
        $configs = [];
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN (
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name',
            'email_template_delivery_subject', 'email_template_delivery_html', 'member_area_login_url'
        )");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $configs[$row['chave']] = $row['valor'];
        }

        // Definir valores padrão para o template e o assunto, se não existirem no DB
        $nome_plataforma_default = getSystemSetting('nome_plataforma', 'Hub SinergIA');
        $default_subject = "Acesso ao seu Produto " . $nome_plataforma_default . "!";
        
        // Obtém a URL da logo do checkout (ou logo padrão se não houver)
        $logo_checkout_url_raw = getSystemSetting('logo_checkout_url', '');
        $logo_url_raw = getSystemSetting('logo_url', 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png');
        
        // Normaliza a URL da logo do checkout
        $logo_checkout_final = '';
        if (empty($logo_checkout_url_raw)) {
            // Se não tem logo checkout, usa a logo padrão
            $logo_checkout_final = $logo_url_raw;
        } else {
            $logo_checkout_final = $logo_checkout_url_raw;
        }
        
        // Remove barra inicial se houver
        $logo_checkout_final = ltrim($logo_checkout_final, '/');
        
        // Se não começa com http, adiciona o domínio base
        if (!empty($logo_checkout_final) && strpos($logo_checkout_final, 'http') !== 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domainName = $_SERVER['HTTP_HOST'];
            $logo_checkout_final = $protocol . $domainName . '/' . $logo_checkout_final;
        }
        
        $default_html_template = '
            <html>
            <head>
                <style>
                    body { font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; background-color: #f7f7f7; color: #333; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 20px auto; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
                    .header { background-color: #2DD05E; padding: 30px; text-align: center; color: #fff; }
                    .header img { max-width: 200px; height: auto; margin-bottom: 20px; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { padding: 30px; line-height: 1.6; font-size: 16px; }
                    .content p { margin-bottom: 15px; }
                    .product-section { background-color: #f0fdf4; border-left: 5px solid #22c55e; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                    .product-section h3 { margin-top: 0; color: #16a34a; }
                    .button { display: inline-block; background-color: #16a34a; color: #fff; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: bold; margin-top: 10px; }
                    .footer { background-color: #f0f0f0; padding: 20px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        ' . (!empty($logo_checkout_final) ? '<img src="' . htmlspecialchars($logo_checkout_final) . '" alt="' . htmlspecialchars($nome_plataforma_default) . '" />' : '') . '
                        <h1>Parabéns, {CLIENT_NAME}!</h1>
                    </div>
                    <div class="content">
                        <p>Seus produtos adquiridos na ' . htmlspecialchars($nome_plataforma_default) . ' foram liberados com sucesso!</p>
                        <p>Abaixo estão os detalhes de acesso para cada um deles:</p>
                        
                        <!-- LOOP_PRODUCTS_START -->
                        <div class="product-section">
                            <h3>{PRODUCT_NAME}</h3>
                            <!-- IF_PRODUCT_TYPE_LINK -->
                            <p><strong>Link de Acesso:</strong></p>
                            <p style="text-align: center;"><a href="{PRODUCT_LINK}" class="button">Acessar {PRODUCT_NAME}</a></p>
                            <p style="word-break: break-all; font-size: 14px;">Se o botão não funcionar, copie e cole o link: <a href="{PRODUCT_LINK}">{PRODUCT_LINK}</a></p>
                            <!-- END_IF_PRODUCT_TYPE_LINK -->

                            <!-- IF_PRODUCT_TYPE_PDF -->
                            <p>Seu PDF está anexado a este e-mail. Faça o download para começar a aproveitar!</p>
                            <!-- END_IF_PRODUCT_TYPE_PDF -->

                            <!-- IF_PRODUCT_TYPE_MEMBER_AREA -->
                            <p>Este produto está disponível em sua área de membros.</p>
                            <p>Seu login é seu e-mail: <strong>{CLIENT_EMAIL}</strong></p>
                            <p>Sua senha: <strong>{MEMBER_AREA_PASSWORD}</strong> (foi gerada automaticamente)</p>
                            <p style="text-align: center;"><a href="{MEMBER_AREA_LOGIN_URL}" class="button">Acessar sua Área de Membros</a></p>
                            <!-- END_IF_PRODUCT_TYPE_MEMBER_AREA -->
                        </div>
                        <!-- LOOP_PRODUCTS_END -->

                        <p>Caso tenha alguma dúvida ou precise de suporte, entre em contato conosco.</p>
                        <p>Obrigado e aproveite seus novos produtos!</p>
                    </div>
                    <div class="footer">
                        <p>Este é um e-mail automático, por favor, não responda.</p>
                        <p>&copy; ' . date("Y") . ' ' . getSystemSetting('nome_plataforma', 'Hub SinergIA') . '. Todos os direitos reservados.</p>
                    </div>
                </div>
            </body>
            </html>
        ';

        // Obtém a URL base para a URL de login da área de membros padrão
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        $default_member_area_login_url = $protocol . $domainName . '/member_login';


        // Substitui "GatewayPro" pelo nome dinâmico da plataforma no template HTML (se existir)
        $email_template_html = $configs['email_template_delivery_html'] ?? $default_html_template;
        $nome_plataforma_atual = getSystemSetting('nome_plataforma', 'Hub SinergIA');
        
        // Obtém a URL da logo do checkout para substituir imagens quebradas
        $logo_checkout_url_raw_db = getSystemSetting('logo_checkout_url', '');
        $logo_url_raw_db = getSystemSetting('logo_url', 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png');
        $logo_checkout_final_db = empty($logo_checkout_url_raw_db) ? $logo_url_raw_db : $logo_checkout_url_raw_db;
        $logo_checkout_final_db = ltrim($logo_checkout_final_db, '/');
        if (!empty($logo_checkout_final_db) && strpos($logo_checkout_final_db, 'http') !== 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domainName = $_SERVER['HTTP_HOST'];
            $logo_checkout_final_db = $protocol . $domainName . '/' . $logo_checkout_final_db;
        }
        
        // Substitui imagens quebradas ou não encontradas pela logo do checkout
        if (!empty($logo_checkout_final_db)) {
            // Substitui imagens com src contendo "not found", "broken", etc.
            $email_template_html = preg_replace(
                '/<img([^>]*?)src=["\']?[^"\']*(?:not[_-]?found|broken|404|error|missing)[^"\']*["\']?([^>]*?)>/i',
                '<img$1src="' . htmlspecialchars($logo_checkout_final_db) . '" alt="' . htmlspecialchars($nome_plataforma_atual) . '" style="max-width: 200px; height: auto; margin-bottom: 20px;"$2>',
                $email_template_html
            );
            // Se não houver imagem no header, adiciona uma
            if (strpos($email_template_html, '<div class="header">') !== false) {
                $header_pos = strpos($email_template_html, '<div class="header">');
                $h1_pos = strpos($email_template_html, '<h1>', $header_pos);
                if ($h1_pos !== false) {
                    $header_content = substr($email_template_html, $header_pos, $h1_pos - $header_pos);
                    if (stripos($header_content, '<img') === false) {
                        // Não há imagem no header, adiciona
                        $email_template_html = substr_replace(
                            $email_template_html,
                            '<img src="' . htmlspecialchars($logo_checkout_final_db) . '" alt="' . htmlspecialchars($nome_plataforma_atual) . '" style="max-width: 200px; height: auto; margin-bottom: 20px;" />',
                            $h1_pos,
                            0
                        );
                    }
                }
            }
        }
        
        // Substitui ocorrências de "GatewayPro" no copyright do template pelo nome dinâmico
        // Procura por padrões como "© YYYY GatewayPro" ou "GatewayPro. Todos os direitos"
        // Formato 1: &copy; YYYY GatewayPro. Todos os direitos reservados.
        $email_template_html = preg_replace(
            '/(&copy;|©)\s*(\d{4})\s+Hub SinergIA\s*\.\s*Todos os direitos reservados\./i',
            '$1 $2 ' . htmlspecialchars($nome_plataforma_atual) . '. Todos os direitos reservados.',
            $email_template_html
        );
        // Formato 2: &copy; YYYY GatewayPro.
        $email_template_html = preg_replace(
            '/(&copy;|©)\s*(\d{4})\s+Hub SinergIA\s*\./i',
            '$1 $2 ' . htmlspecialchars($nome_plataforma_atual) . '.',
            $email_template_html
        );
        // Formato 3: GatewayPro © YYYY
        $email_template_html = preg_replace(
            '/Hub SinergIA\s*(&copy;|©)\s*(\d{4})/i',
            htmlspecialchars($nome_plataforma_atual) . ' $1 $2',
            $email_template_html
        );
        
        $smtp_password_configured = !empty($configs['smtp_password'] ?? '');

        $response_configs = [
            'smtp_host' => $configs['smtp_host'] ?? '',
            'smtp_port' => $configs['smtp_port'] ?? '587',
            'smtp_username' => $configs['smtp_username'] ?? '',
            'smtp_encryption' => $configs['smtp_encryption'] ?? 'tls',
            'smtp_from_email' => $configs['smtp_from_email'] ?? '',
            'smtp_from_name' => $configs['smtp_from_name'] ?? $nome_plataforma_atual,
            'email_template_delivery_subject' => $configs['email_template_delivery_subject'] ?? $default_subject,
            'email_template_delivery_html' => $email_template_html,
            'member_area_login_url' => $configs['member_area_login_url'] ?? $default_member_area_login_url,
            // Nunca retorna a senha; só indica se já existe no banco
            'smtp_password_configured' => $smtp_password_configured,
        ];

        ob_clean();
        echo json_encode(['success' => true, 'data' => $response_configs]);
        exit;
    }

    // NOVO: Ação para salvar configurações de e-mail e entrega
    elseif ($action === 'save_email_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("ADMIN_API: Recebida ação save_email_settings.");
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ADMIN_API: Erro ao decodificar JSON para 'save_email_settings': " . json_last_error_msg());
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados JSON inválidos.']);
            exit;
        }
        error_log("ADMIN_API: Dados de input para save_email_settings: " . print_r($input, true));

        $pdo->beginTransaction();
        try {
            $fields_to_save = [
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name',
                'email_template_delivery_subject', 'email_template_delivery_html', 'member_area_login_url'
            ];

            foreach ($fields_to_save as $chave) {
                $valor = $input[$chave] ?? '';
                $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                $stmt->execute([$chave, $valor]);
            }

            // A senha só é atualizada se for explicitamente fornecida
            if (isset($input['smtp_password']) && !empty($input['smtp_password'])) {
                $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('smtp_password', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                $stmt->execute([$input['smtp_password']]);
                error_log("ADMIN_API: Senha SMTP atualizada.");
            } else {
                error_log("ADMIN_API: Senha SMTP não fornecida ou vazia, mantendo a senha existente.");
            }

            $pdo->commit();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Configurações de e-mail e entrega salvas com sucesso!']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            error_log("ADMIN_API: Erro ao salvar configurações de e-mail/entrega: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar configurações no banco de dados: ' . $e->getMessage()]);
        }
        exit;
    }


    // NOVO: Ação para testar conexão SMTP
    elseif ($action === 'test_smtp_connection' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("ADMIN_API: Recebida ação test_smtp_connection.");
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ADMIN_API: Erro ao decodificar JSON para 'test_smtp_connection': " . json_last_error_msg());
            http_response_code(400);
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados JSON inválidos.']);
            exit;
        }
        error_log("ADMIN_API: Dados de input para test_smtp_connection: " . print_r($input, true));

        $smtp_config = getSmtpConfigFromRequest($pdo, $input);
        error_log("ADMIN_API: Configuração SMTP após processamento para test_smtp_connection (comprimento da senha: " . strlen($smtp_config['password']) . "): " . print_r($smtp_config, true));

        $mail = new PHPMailer(true);
        try {
            error_log("ADMIN_API: Instanciando PHPMailer para teste de conexão.");
            // Configurar SMTP
            $mail->isSMTP();
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Ativa saída de depuração verbosa (para error_log do PHP)
            $mail->Debugoutput = 'error_log'; // EXPLICITAMENTE direciona o debug para o log de erros
            $mail->Host = $smtp_config['host'];
            $mail->Port = $smtp_config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_config['username'];
            $mail->Password = $smtp_config['password'];
            
            // SMTPOptions para aceitar certificados autoassinados (cuidado em produção)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            if ($smtp_config['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtp_config['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            error_log("ADMIN_API: Tentando conectar ao SMTP: Host=" . $mail->Host . ", Port=" . $mail->Port . ", User=" . $mail->Username);
            // Apenas tenta conectar, sem enviar e-mail
            $mail->smtpConnect();
            $mail->smtpClose(); // Fecha a conexão imediatamente após o teste

            error_log("ADMIN_API: Teste de conexão SMTP bem-sucedido. Preparando resposta JSON.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Conexão SMTP testada com sucesso!']);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("ADMIN_API: Teste de conexão SMTP falhou: " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            error_log("ADMIN_API: Preparando resposta JSON de erro para teste de conexão.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Falha na conexão SMTP: ' . $e->getMessage()]);
        }
        exit;
    }

    // NOVO: Ação para enviar e-mail de teste
    elseif ($action === 'send_test_email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("ADMIN_API: Recebida ação send_test_email.");
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ADMIN_API: Erro ao decodificar JSON para 'send_test_email': " . json_last_error_msg());
            http_response_code(400);
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados JSON inválidos.']);
            exit;
        }
        error_log("ADMIN_API: Dados de input para send_test_email: " . print_r($input, true));

        $smtp_config = getSmtpConfigFromRequest($pdo, $input);
        error_log("ADMIN_API: Configuração SMTP após processamento para send_test_email (comprimento da senha: " . strlen($smtp_config['password']) . "): " . print_r($smtp_config, true));
        $test_email = $input['test_email'] ?? '';

        if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            error_log("ADMIN_API: Erro (send_test_email): E-mail de teste inválido ou ausente.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'E-mail de teste inválido ou ausente.']);
            exit;
        }

        $mail = new PHPMailer(true);
        try {
            error_log("ADMIN_API: Instanciando PHPMailer para envio de e-mail de teste.");
            // Configurar SMTP
            $mail->isSMTP();
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Ativa saída de depuração verbosa (para error_log do PHP)
            $mail->Debugoutput = 'error_log'; // EXPLICITAMENTE direciona o debug para o log de erros
            $mail->Host = $smtp_config['host'];
            $mail->Port = $smtp_config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_config['username'];
            $mail->Password = $smtp_config['password'];
            
            // SMTPOptions para aceitar certificados autoassinados (cuidado em produção)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            if ($smtp_config['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtp_config['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            // Configurar e-mail
            $mail->CharSet = 'UTF-8';
            // CORREÇÃO: Usar o username como 'From' address para evitar "Sender address rejected"
            $mail->setFrom($smtp_config['username'], $smtp_config['from_name']);
            $mail->addAddress($test_email);
            $mail->Subject = 'Email de Teste Hub SinergIA SMTP';
            $mail->isHTML(true);
            $mail->Body = 'Olá! Este é um e-mail de teste enviado da sua configuração SMTP na plataforma Hub SinergIA. Se você recebeu esta mensagem, suas configurações estão funcionando corretamente.';
            $mail->AltBody = 'Olá! Este é um e-mail de teste enviado da sua configuração SMTP na plataforma Hub SinergIA. Se você recebeu esta mensagem, suas configurações estão funcionando corretamente.';

            error_log("ADMIN_API: Tentando enviar e-mail de teste para " . $test_email . " usando SMTP: Host=" . $mail->Host . ", Port=" . $mail->Port);
            $mail->send();
            error_log("ADMIN_API: E-mail de teste enviado com sucesso para " . $test_email . ". Preparando resposta JSON.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'E-mail de teste enviado com sucesso para ' . $test_email . '!']);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("ADMIN_API: Falha ao enviar e-mail de teste para " . $test_email . ": " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            error_log("ADMIN_API: Preparando resposta JSON de erro para envio de e-mail de teste.");
            // Limpa o buffer antes de enviar o JSON
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Falha ao enviar e-mail de teste: ' . $e->getMessage()]);
        }
        exit;
    }

    // ========== CONFIGURAÇÕES DO SISTEMA ==========
    elseif ($action === 'get_system_settings') {
        require_once __DIR__ . '/../config/config.php';
        if (!function_exists('getSystemSetting')) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Função getSystemSetting não encontrada']);
            exit;
        }
        
        // Busca valores brutos do banco
        $logo_url_raw = getSystemSetting('logo_url', 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png');
        $login_image_url_raw = getSystemSetting('login_image_url', '');
        $logo_checkout_url_raw = getSystemSetting('logo_checkout_url', '');
        $favicon_url_raw = getSystemSetting('favicon_url', '');
        
        // Normaliza URLs para retornar (igual ao load_settings.php)
        // Remove barra inicial se houver
        $logo_url_normalized = ltrim($logo_url_raw, '/');
        if (empty($logo_url_normalized)) {
            $logo_url_normalized = 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png';
        } elseif (strpos($logo_url_normalized, 'http') === 0) {
            // URL completa, mantém como está
        } elseif (strpos($logo_url_normalized, 'uploads/') === 0) {
            // Adiciona barra inicial (igual às imagens dos módulos)
            $logo_url_normalized = '/' . $logo_url_normalized;
        } else {
            $logo_url_normalized = '/' . $logo_url_normalized;
        }
        
        $login_image_url_normalized = ltrim($login_image_url_raw, '/');
        if (!empty($login_image_url_normalized) && strpos($login_image_url_normalized, 'http') !== 0) {
            if (strpos($login_image_url_normalized, 'uploads/') === 0) {
                $login_image_url_normalized = '/' . $login_image_url_normalized;
            } else {
                $login_image_url_normalized = '/' . $login_image_url_normalized;
            }
        }
        
        $logo_checkout_url_normalized = ltrim($logo_checkout_url_raw, '/');
        if (empty($logo_checkout_url_normalized)) {
            $logo_checkout_url_normalized = $logo_url_normalized;
        } elseif (strpos($logo_checkout_url_normalized, 'http') === 0) {
            // URL completa, mantém como está
        } elseif (strpos($logo_checkout_url_normalized, 'uploads/') === 0) {
            $logo_checkout_url_normalized = '/' . $logo_checkout_url_normalized;
        } else {
            $logo_checkout_url_normalized = '/' . $logo_checkout_url_normalized;
        }
        
        $favicon_url_normalized = ltrim($favicon_url_raw, '/');
        if (!empty($favicon_url_normalized) && strpos($favicon_url_normalized, 'http') !== 0) {
            if (strpos($favicon_url_normalized, 'uploads/') === 0) {
                $favicon_url_normalized = '/' . $favicon_url_normalized;
            } else {
                $favicon_url_normalized = '/' . $favicon_url_normalized;
            }
        }
        
        // Imagem das notificações
        $notification_image_url_raw = getSystemSetting('notification_image_url', '');
        $notification_image_url_normalized = ltrim($notification_image_url_raw, '/');
        if (!empty($notification_image_url_normalized) && strpos($notification_image_url_normalized, 'http') !== 0) {
            if (strpos($notification_image_url_normalized, 'uploads/') === 0) {
                $notification_image_url_normalized = '/' . $notification_image_url_normalized;
            } else {
                $notification_image_url_normalized = '/' . $notification_image_url_normalized;
            }
        }
        
        // Selo de segurança do checkout
        $security_seal_url_raw = getSystemSetting('security_seal_url', '');
        $security_seal_url_normalized = ltrim($security_seal_url_raw, '/');
        if (!empty($security_seal_url_normalized) && strpos($security_seal_url_normalized, 'http') !== 0) {
            if (strpos($security_seal_url_normalized, 'uploads/') === 0) {
                $security_seal_url_normalized = '/' . $security_seal_url_normalized;
            } else {
                $security_seal_url_normalized = '/' . $security_seal_url_normalized;
            }
        }
        
        $settings = [
            'cor_primaria' => getSystemSetting('cor_primaria', '#32e768'),
            'logo_url' => $logo_url_normalized,
            'login_image_url' => $login_image_url_normalized,
            'nome_plataforma' => getSystemSetting('nome_plataforma', 'Hub SinergIA'),
            'logo_checkout_url' => $logo_checkout_url_normalized,
            'favicon_url' => $favicon_url_normalized,
            'notification_image_url' => $notification_image_url_normalized,
            'security_seal_url' => $security_seal_url_normalized
        ];
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $settings]);
        exit;
    }
    elseif ($action === 'save_system_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/config.php';
        
        // Verifica se PDO está disponível
        if (!isset($pdo) || !$pdo) {
            ob_clean();
            error_log("ADMIN_API: PDO não está disponível!");
            echo json_encode(['success' => false, 'error' => 'Erro de conexão com banco de dados']);
            exit;
        }
        
        if (!function_exists('setSystemSetting')) {
            ob_clean();
            error_log("ADMIN_API: Função setSystemSetting não encontrada!");
            echo json_encode(['success' => false, 'error' => 'Função setSystemSetting não encontrada']);
            exit;
        }
        
        $raw_input = file_get_contents('php://input');
        error_log("ADMIN_API save_system_settings: Raw input: " . $raw_input);
        
        $data = json_decode($raw_input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            ob_clean();
            error_log("ADMIN_API: Erro ao decodificar JSON: " . json_last_error_msg());
            echo json_encode(['success' => false, 'error' => 'Erro ao processar dados: ' . json_last_error_msg()]);
            exit;
        }
        
        // Debug: log dos dados recebidos
        error_log("ADMIN_API save_system_settings: Dados recebidos: " . json_encode($data));
        
        if (!isset($data['logo_url']) && !isset($data['login_image_url']) && !isset($data['nome_plataforma']) && !isset($data['logo_checkout_url']) && !isset($data['favicon_url']) && !isset($data['notification_image_url']) && !isset($data['security_seal_url'])) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Nenhuma configuração fornecida']);
            exit;
        }
        
        $updated = [];
        if (isset($data['logo_url'])) {
            $logo_url_val = filter_var($data['logo_url'], FILTER_SANITIZE_URL);
            if (setSystemSetting('logo_url', $logo_url_val)) {
                $updated[] = 'logo_url';
            } else {
                error_log("ADMIN_API: Erro ao salvar logo_url");
            }
        }
        if (isset($data['login_image_url'])) {
            $login_img_val = filter_var($data['login_image_url'], FILTER_SANITIZE_URL);
            if (setSystemSetting('login_image_url', $login_img_val)) {
                $updated[] = 'login_image_url';
            } else {
                error_log("ADMIN_API: Erro ao salvar login_image_url");
            }
        }
        if (isset($data['nome_plataforma'])) {
            $nome = trim($data['nome_plataforma']);
            if (!empty($nome)) {
                $nome_sanitizado = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
                error_log("ADMIN_API: Tentando salvar nome_plataforma: " . $nome_sanitizado);
                if (setSystemSetting('nome_plataforma', $nome_sanitizado)) {
                    $updated[] = 'nome_plataforma';
                    error_log("ADMIN_API: nome_plataforma salvo com sucesso");
                } else {
                    error_log("ADMIN_API: Erro ao salvar nome_plataforma: " . $nome_sanitizado);
                }
            } else {
                error_log("ADMIN_API: nome_plataforma está vazio após trim");
            }
        } else {
            error_log("ADMIN_API: nome_plataforma não está definido no data");
        }
        if (isset($data['logo_checkout_url'])) {
            $logo_checkout_val = filter_var($data['logo_checkout_url'], FILTER_SANITIZE_URL);
            if (setSystemSetting('logo_checkout_url', $logo_checkout_val)) {
                $updated[] = 'logo_checkout_url';
            } else {
                error_log("ADMIN_API: Erro ao salvar logo_checkout_url");
            }
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso', 'updated' => $updated]);
        exit;
    }
    // save_theme: persiste theme_json (Configurações Visuais White-label)
    elseif ($action === 'save_theme' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/theme_helper.php';
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!is_array($data)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
            exit;
        }
        $theme = [
            'primary' => preg_match('/^#?[0-9A-Fa-f]{6}$/', $data['primary'] ?? '') ? (strpos($data['primary'], '#') === 0 ? $data['primary'] : '#' . $data['primary']) : '#32e768',
            'primaryHover' => preg_match('/^#?[0-9A-Fa-f]{6}$/', $data['primaryHover'] ?? '') ? (strpos($data['primaryHover'], '#') === 0 ? $data['primaryHover'] : '#' . $data['primaryHover']) : '#28d15e',
            'bg' => trim($data['bg'] ?? '#07090d'),
            'text' => trim($data['text'] ?? 'rgba(255, 255, 255, 0.9)'),
            'textMuted' => trim($data['textMuted'] ?? 'rgba(255, 255, 255, 0.5)'),
            'card' => trim($data['card'] ?? '#1a1f24'),
            'cardElevated' => trim($data['cardElevated'] ?? '#0f1419'),
            'border' => trim($data['border'] ?? 'rgba(255, 255, 255, 0.1)'),
            'radius' => trim($data['radius'] ?? '0.5rem'),
            'shadow' => trim($data['shadow'] ?? ''),
            'fontSans' => trim($data['fontSans'] ?? "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"),
        ];
        if (empty($theme['shadow'])) $theme['shadow'] = '0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -1px rgba(0,0,0,0.2)';
        // Mantém logo e login_banner em sync com configuracoes_sistema (retrocompat)
        $theme['logo_url'] = getSystemSetting('logo_url', '');
        $theme['login_banner_url'] = getSystemSetting('login_image_url', '');
        if (set_theme_json($theme)) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Tema salvo com sucesso']);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar tema no banco']);
        }
        exit;
    }
    elseif ($action === 'upload_logo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/config.php';
        $upload_dir = 'uploads/config/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo']);
            exit;
        }
        
        $file = $_FILES['logo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, WEBP ou SVG']);
            exit;
        }
        
        if ($file['size'] > $max_size) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo 2MB']);
            exit;
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = 'logo_' . time() . '.' . $ext;
        $target_path = $upload_dir . $new_name;
        $target_path_absoluto = __DIR__ . '/../' . $target_path;
        
        // Debug
        error_log("ADMIN_API upload_logo: target_path (relativo)=$target_path");
        error_log("ADMIN_API upload_logo: target_path (absoluto)=$target_path_absoluto");
        error_log("ADMIN_API upload_logo: upload_dir exists=" . (is_dir($upload_dir) ? 'YES' : 'NO'));
        error_log("ADMIN_API upload_logo: upload_dir writable=" . (is_writable($upload_dir) ? 'YES' : 'NO'));
        
        // Deleta logo antiga se existir
        $old_logo = getSystemSetting('logo_url', '');
        if (!empty($old_logo) && strpos($old_logo, 'http') !== 0) {
            // Remove barra inicial se houver
            $old_path = ltrim($old_logo, '/');
            $old_path_absoluto = __DIR__ . '/../' . $old_path;
            if (file_exists($old_path_absoluto)) {
                @unlink($old_path_absoluto);
                error_log("ADMIN_API upload_logo: Antiga logo deletada: $old_path_absoluto");
            }
        }
        
        // Usa caminho absoluto para move_uploaded_file
        if (move_uploaded_file($file['tmp_name'], $target_path_absoluto)) {
            error_log("ADMIN_API upload_logo: Arquivo movido com sucesso para: $target_path_absoluto");
            // Verifica se arquivo existe após mover
            if (file_exists($target_path_absoluto)) {
                error_log("ADMIN_API upload_logo: Arquivo confirmado existente após mover");
            } else {
                error_log("ADMIN_API upload_logo: ERRO - Arquivo NÃO existe após mover!");
            }
            // Salva no banco SEM barra inicial (igual às imagens dos módulos)
            // Será acessada como /uploads/config/logo_xxx.jpg quando exibida
            $logo_url = $target_path; // Sem barra inicial
            if (setSystemSetting('logo_url', $logo_url)) {
                // Sincroniza theme_json com logo_url (White-label)
                if (file_exists(__DIR__ . '/../config/theme_helper.php')) {
                    require_once __DIR__ . '/../config/theme_helper.php';
                    $t = get_theme_json();
                    $t['logo_url'] = $logo_url;
                    set_theme_json($t);
                }
                error_log("ADMIN_API upload_logo: Configuração salva no banco: $logo_url");
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Logo enviada com sucesso', 'url' => '/' . $logo_url]);
            } else {
                error_log("ADMIN_API upload_logo: Erro ao salvar configuração no banco");
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar configuração no banco de dados']);
            }
        } else {
            $error = error_get_last();
            error_log("ADMIN_API upload_logo: Erro ao mover arquivo. Detalhes: " . ($error ? $error['message'] : 'Erro desconhecido'));
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo: ' . ($error ? $error['message'] : 'Erro desconhecido')]);
        }
        exit;
    }
    elseif ($action === 'upload_login_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/config.php';
        $upload_dir = 'uploads/config/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (!isset($_FILES['login_image']) || $_FILES['login_image']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo']);
            exit;
        }
        
        $file = $_FILES['login_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG ou WEBP']);
            exit;
        }
        
        if ($file['size'] > $max_size) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo 5MB']);
            exit;
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = 'login_bg_' . time() . '.' . $ext;
        $target_path = $upload_dir . $new_name;
        $target_path_absoluto = __DIR__ . '/../' . $target_path;
        
        // Deleta imagem antiga se existir
        $old_image = getSystemSetting('login_image_url', '');
        if (!empty($old_image) && strpos($old_image, 'http') !== 0) {
            // Remove barra inicial se houver
            $old_path = ltrim($old_image, '/');
            $old_path_absoluto = __DIR__ . '/../' . $old_path;
            if (file_exists($old_path_absoluto)) {
                @unlink($old_path_absoluto);
            }
        }
        
        // Usa caminho absoluto para move_uploaded_file
        if (move_uploaded_file($file['tmp_name'], $target_path_absoluto)) {
            // Salva no banco SEM barra inicial (igual às imagens dos módulos)
            $image_url = $target_path; // Sem barra inicial
            if (setSystemSetting('login_image_url', $image_url)) {
                // Sincroniza theme_json com login_banner (White-label)
                if (file_exists(__DIR__ . '/../config/theme_helper.php')) {
                    require_once __DIR__ . '/../config/theme_helper.php';
                    $t = get_theme_json();
                    $t['login_banner_url'] = $image_url;
                    set_theme_json($t);
                }
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Imagem de login enviada com sucesso', 'url' => '/' . $image_url]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar configuração no banco de dados']);
            }
        } else {
            $error = error_get_last();
            error_log("ADMIN_API upload_login_image: Erro ao mover arquivo. Detalhes: " . ($error ? $error['message'] : 'Erro desconhecido'));
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo: ' . ($error ? $error['message'] : 'Erro desconhecido')]);
        }
        exit;
    }
    elseif ($action === 'upload_logo_checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/config.php';
        $upload_dir = 'uploads/config/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (!isset($_FILES['logo_checkout']) || $_FILES['logo_checkout']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo']);
            exit;
        }
        
        $file = $_FILES['logo_checkout'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, WEBP ou SVG']);
            exit;
        }
        
        if ($file['size'] > $max_size) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo 2MB']);
            exit;
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = 'logo_checkout_' . time() . '.' . $ext;
        $target_path = $upload_dir . $new_name;
        $target_path_absoluto = __DIR__ . '/../' . $target_path;
        
        // Deleta logo antiga se existir
        $old_logo = getSystemSetting('logo_checkout_url', '');
        if (!empty($old_logo) && strpos($old_logo, 'http') !== 0) {
            // Remove barra inicial se houver
            $old_path = ltrim($old_logo, '/');
            $old_path_absoluto = __DIR__ . '/../' . $old_path;
            if (file_exists($old_path_absoluto)) {
                @unlink($old_path_absoluto);
            }
        }
        
        // Usa caminho absoluto para move_uploaded_file
        if (move_uploaded_file($file['tmp_name'], $target_path_absoluto)) {
            // Salva no banco SEM barra inicial (igual às imagens dos módulos)
            $logo_checkout_url = $target_path; // Sem barra inicial
            if (setSystemSetting('logo_checkout_url', $logo_checkout_url)) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Logo do checkout enviada com sucesso', 'url' => '/' . $logo_checkout_url]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar configuração no banco de dados']);
            }
        } else {
            $error = error_get_last();
            error_log("ADMIN_API upload_logo_checkout: Erro ao mover arquivo. Detalhes: " . ($error ? $error['message'] : 'Erro desconhecido'));
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo: ' . ($error ? $error['message'] : 'Erro desconhecido')]);
        }
        exit;
    }
    elseif ($action === 'upload_favicon' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/config.php';
        $upload_dir = 'uploads/config/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo']);
            exit;
        }
        
        $file = $_FILES['favicon'];
        $allowed_types = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use ICO, PNG ou SVG']);
            exit;
        }
        
        if ($file['size'] > $max_size) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo 2MB']);
            exit;
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = 'favicon_' . time() . '.' . $ext;
        $target_path = $upload_dir . $new_name;
        $target_path_absoluto = __DIR__ . '/../' . $target_path;
        
        // Deleta favicon antigo se existir
        $old_favicon = getSystemSetting('favicon_url', '');
        if (!empty($old_favicon) && strpos($old_favicon, 'http') !== 0) {
            // Remove barra inicial se houver
            $old_path = ltrim($old_favicon, '/');
            $old_path_absoluto = __DIR__ . '/../' . $old_path;
            if (file_exists($old_path_absoluto)) {
                @unlink($old_path_absoluto);
            }
        }
        
        // Usa caminho absoluto para move_uploaded_file
        if (move_uploaded_file($file['tmp_name'], $target_path_absoluto)) {
            // Salva no banco SEM barra inicial (igual às imagens dos módulos)
            $favicon_url = $target_path; // Sem barra inicial
            if (setSystemSetting('favicon_url', $favicon_url)) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Favicon enviado com sucesso', 'url' => '/' . $favicon_url]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar configuração no banco de dados']);
            }
        } else {
            $error = error_get_last();
            error_log("ADMIN_API upload_favicon: Erro ao mover arquivo. Detalhes: " . ($error ? $error['message'] : 'Erro desconhecido'));
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo: ' . ($error ? $error['message'] : 'Erro desconhecido')]);
        }
        exit;
    }
    elseif ($action === 'upload_notification_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/config.php';
        $upload_dir = 'uploads/config/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (!isset($_FILES['notification_image']) || $_FILES['notification_image']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo']);
            exit;
        }
        
        $file = $_FILES['notification_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, WEBP ou SVG']);
            exit;
        }
        
        if ($file['size'] > $max_size) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo 2MB']);
            exit;
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = 'notification_image_' . time() . '.' . $ext;
        $target_path = $upload_dir . $new_name;
        $target_path_absoluto = __DIR__ . '/../' . $target_path;
        
        // Deleta imagem antiga se existir
        $old_image = getSystemSetting('notification_image_url', '');
        if (!empty($old_image) && strpos($old_image, 'http') !== 0) {
            $old_path = ltrim($old_image, '/');
            $old_path_absoluto = __DIR__ . '/../' . $old_path;
            if (file_exists($old_path_absoluto)) {
                @unlink($old_path_absoluto);
            }
        }
        
        if (move_uploaded_file($file['tmp_name'], $target_path_absoluto)) {
            $notification_image_url = $target_path;
            if (setSystemSetting('notification_image_url', $notification_image_url)) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Imagem das notificações enviada com sucesso', 'url' => '/' . $notification_image_url]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar configuração no banco de dados']);
            }
        } else {
            $error = error_get_last();
            error_log("ADMIN_API upload_notification_image: Erro ao mover arquivo. Detalhes: " . ($error ? $error['message'] : 'Erro desconhecido'));
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo: ' . ($error ? $error['message'] : 'Erro desconhecido')]);
        }
        exit;
    }
    elseif ($action === 'upload_security_seal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/config.php';
        $upload_dir = 'uploads/config/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (!isset($_FILES['security_seal']) || $_FILES['security_seal']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo']);
            exit;
        }
        
        $file = $_FILES['security_seal'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, WEBP ou SVG']);
            exit;
        }
        
        if ($file['size'] > $max_size) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo 2MB']);
            exit;
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = 'security_seal_' . time() . '.' . $ext;
        $target_path = $upload_dir . $new_name;
        $target_path_absoluto = __DIR__ . '/../' . $target_path;
        
        // Deleta selo antigo se existir
        $old_seal = getSystemSetting('security_seal_url', '');
        if (!empty($old_seal) && strpos($old_seal, 'http') !== 0) {
            $old_path = ltrim($old_seal, '/');
            $old_path_absoluto = __DIR__ . '/../' . $old_path;
            if (file_exists($old_path_absoluto)) {
                @unlink($old_path_absoluto);
            }
        }
        
        if (move_uploaded_file($file['tmp_name'], $target_path_absoluto)) {
            $security_seal_url = $target_path;
            if (setSystemSetting('security_seal_url', $security_seal_url)) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Selo de segurança enviado com sucesso', 'url' => '/' . $security_seal_url]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar configuração no banco de dados']);
            }
        } else {
            $error = error_get_last();
            error_log("ADMIN_API upload_security_seal: Erro ao mover arquivo. Detalhes: " . ($error ? $error['message'] : 'Erro desconhecido'));
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo: ' . ($error ? $error['message'] : 'Erro desconhecido')]);
        }
        exit;
    }
    elseif ($action === 'delete_security_seal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../config/config.php';
        
        // Deleta selo se existir
        $old_seal = getSystemSetting('security_seal_url', '');
        if (!empty($old_seal) && strpos($old_seal, 'http') !== 0) {
            $old_path = ltrim($old_seal, '/');
            $old_path_absoluto = __DIR__ . '/../' . $old_path;
            if (file_exists($old_path_absoluto)) {
                @unlink($old_path_absoluto);
            }
        }
        
        // Remove do banco
        if (setSystemSetting('security_seal_url', '')) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Selo de segurança removido com sucesso']);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao remover configuração do banco de dados']);
        }
        exit;
    }

    // ==================== ENDPOINTS DE LICENÇA ====================
    elseif ($action === 'get_license_info') {
        require_once __DIR__ . '/../helpers/license_helper.php';
        
        $licenseInfo = getLicenseInfo();
        $isValid = isLicenseValid();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => [
                'license_key' => $licenseInfo['license_key'] ?? '',
                'status' => $licenseInfo['license_status'] ?? 'inactive',
                'expiration' => $licenseInfo['license_expiration'] ?? null,
                'activated_at' => $licenseInfo['license_activated_at'] ?? null,
                'last_check' => $licenseInfo['license_last_check'] ?? null,
                'is_valid' => $isValid
            ]
        ]);
        exit;
    }
    elseif ($action === 'activate_license' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../helpers/license_helper.php';
        
        $raw_input = file_get_contents('php://input');
        $data = json_decode($raw_input, true);
        
        if (!isset($data['activation_key']) || empty(trim($data['activation_key']))) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Chave de ativação é obrigatória']);
            exit;
        }
        
        $activationKey = trim($data['activation_key']);
        $result = activateLicense($activationKey);
        
        ob_clean();
        if ($result['valid']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message'] ?? 'Licença ativada com sucesso!'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => $result['reason'] ?? 'Chave de ativação inválida'
            ]);
        }
        exit;
    }

    // ==================== PWA ====================
    $pwa_activated_paths = [
        __DIR__ . '/../pwa_activated.key',
        isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/pwa_activated.key' : '',
        dirname(__DIR__) . '/pwa_activated.key',
    ];
    $pwa_activated = false;
    foreach ($pwa_activated_paths as $p) {
        if ($p !== '' && file_exists($p)) {
            $pwa_activated = true;
            break;
        }
    }
    if (!$pwa_activated && function_exists('getSystemSetting') && getSystemSetting('pwa_activated', '0') === '1') {
        $pwa_activated = true;
    }

    if ($action === 'activate_pwa_module' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (function_exists('setSystemSetting')) {
            setSystemSetting('pwa_activated', '1');
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Módulo PWA ativado (banco de dados).']);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Função setSystemSetting não disponível.']);
        }
        exit;
    }

    if ($action === 'get_pwa_status') {
        ob_clean();
        echo json_encode(['success' => true, 'activated' => $pwa_activated]);
        exit;
    }

    if ($action === 'get_pwa_config' && file_exists(__DIR__ . '/../pwa/pwa_config.php')) {
        require_once __DIR__ . '/../pwa/pwa_config.php';
        $config = function_exists('pwa_get_config') ? pwa_get_config() : null;
        ob_clean();
        echo json_encode(['success' => true, 'data' => $config ?: []]);
        exit;
    }

    if ($action === 'save_pwa_config' && $_SERVER['REQUEST_METHOD'] === 'POST' && file_exists(__DIR__ . '/../pwa/pwa_config.php')) {
        require_once __DIR__ . '/../pwa/pwa_config.php';
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
        $current = function_exists('pwa_get_config') ? pwa_get_config() : [];
        if (!is_array($current)) $current = [];
        $config = array_merge($current, [
            'app_name' => $input['app_name'] ?? $current['app_name'] ?? 'Plataforma',
            'short_name' => $input['short_name'] ?? $current['short_name'] ?? 'App',
            'description' => $input['description'] ?? $current['description'] ?? '',
            'icon_path' => $input['icon_path'] ?? $current['icon_path'] ?? '',
            'theme_color' => $input['theme_color'] ?? $current['theme_color'] ?? '#32e768',
            'background_color' => $input['background_color'] ?? $current['background_color'] ?? '#ffffff',
            'display_mode' => $input['display_mode'] ?? $current['display_mode'] ?? 'standalone',
            'start_url' => $input['start_url'] ?? $current['start_url'] ?? '/',
            'scope' => $input['scope'] ?? $current['scope'] ?? '/',
            'push_enabled' => isset($input['push_enabled']) ? (int)$input['push_enabled'] : (isset($current['push_enabled']) ? (int)$current['push_enabled'] : 0)
        ]);
        $ok = function_exists('pwa_save_config') && pwa_save_config($config);
        ob_clean();
        echo json_encode($ok ? ['success' => true] : ['success' => false, 'error' => 'Erro ao salvar configuração PWA']);
        exit;
    }

    if ($action === 'upload_pwa_icon' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['pwa_icon']['tmp_name']) || !is_uploaded_file($_FILES['pwa_icon']['tmp_name'])) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado']);
            exit;
        }
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['pwa_icon']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Formato não permitido. Use PNG, JPG ou WEBP.']);
            exit;
        }
        if ($_FILES['pwa_icon']['size'] > 2 * 1024 * 1024) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Arquivo maior que 2MB']);
            exit;
        }
        $uploads_dir = __DIR__ . '/../uploads';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        $ext = pathinfo($_FILES['pwa_icon']['name'], PATHINFO_EXTENSION) ?: 'png';
        $name = 'pwa_icon_' . time() . '.' . (preg_match('/^[a-z0-9]+$/i', $ext) ? $ext : 'png');
        $path = $uploads_dir . '/' . $name;
        if (!move_uploaded_file($_FILES['pwa_icon']['tmp_name'], $path)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao mover arquivo']);
            exit;
        }
        $icon_url = '/uploads/' . $name;
        if (file_exists(__DIR__ . '/../pwa/pwa_config.php')) {
            require_once __DIR__ . '/../pwa/pwa_config.php';
            $current = function_exists('pwa_get_config') ? pwa_get_config() : [];
            if (!is_array($current)) $current = [];
            $current['icon_path'] = $icon_url;
            if (function_exists('pwa_save_config')) pwa_save_config($current);
        }
        ob_clean();
        echo json_encode(['success' => true, 'icon_url' => $icon_url]);
        exit;
    }

    if ($action === 'get_pwa_push_info') {
        $vapid_preview = '';
        $subscriptions_count = 0;
        if (file_exists(__DIR__ . '/../pwa/pwa_config.php')) {
            require_once __DIR__ . '/../pwa/pwa_config.php';
            $cfg = function_exists('pwa_get_config') ? pwa_get_config() : null;
            if ($cfg && !empty($cfg['vapid_public_key'])) {
                $vapid_preview = substr($cfg['vapid_public_key'], 0, 20) . '...';
            }
        }
        if (file_exists(__DIR__ . '/../pwa/api/web_push_helper.php')) {
            require_once __DIR__ . '/../pwa/api/web_push_helper.php';
            if (function_exists('pwa_count_subscriptions')) {
                $subscriptions_count = pwa_count_subscriptions();
            }
        }
        ob_clean();
        echo json_encode([
            'success' => true,
            'vapid_configured' => !empty($vapid_preview),
            'vapid_preview' => $vapid_preview,
            'subscriptions_count' => (int) $subscriptions_count
        ]);
        exit;
    }

    if ($action === 'send_pwa_push' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
        $title = trim($input['title'] ?? '');
        $message = trim($input['message'] ?? '');
        if (empty($title) || empty($message)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Título e mensagem são obrigatórios.']);
            exit;
        }
        $url = trim($input['url'] ?? '') ?: null;
        $icon = trim($input['icon'] ?? '') ?: null;
        $created_by = (int)($_SESSION['id'] ?? 0);
        if (!file_exists(__DIR__ . '/../pwa/api/web_push_helper.php')) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Módulo de push não disponível.']);
            exit;
        }
        require_once __DIR__ . '/../pwa/api/web_push_helper.php';
        if (!function_exists('pwa_send_push_notification')) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Função de envio não disponível.']);
            exit;
        }
        $result = pwa_send_push_notification($title, $message, $url, $icon, $created_by);
        $err = isset($result['error']) ? $result['error'] : null;
        ob_clean();
        if ($err) {
            echo json_encode(['success' => false, 'error' => $err, 'sent' => (int)($result['sent'] ?? 0), 'failed' => (int)($result['failed'] ?? 0)]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Notificação enviada.',
                'sent' => (int)($result['sent'] ?? 0),
                'failed' => (int)($result['failed'] ?? 0),
                'total' => (int)($result['total'] ?? 0)
            ]);
        }
        exit;
    }

    // ========================================
    // ENDPOINTS DO PAINEL MASTER (LICENÇAS)
    // ========================================
    
    // Atualizar perfil do admin (Editar Perfil)
    if ($action === 'update_admin_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $nome = trim($input['nome'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha_atual = $input['senha_atual'] ?? '';
        $nova_senha = $input['nova_senha'] ?? '';
        $admin_id = (int)($_SESSION['id'] ?? 0);
        if (empty($nome)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Nome é obrigatório.']);
            exit;
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Email inválido.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT id, usuario, nome, senha FROM usuarios WHERE id = ? AND tipo = 'admin'");
            $stmt->execute([$admin_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Usuário não encontrado.']);
                exit;
            }
            $email_changed = ($email !== $usuario['usuario']);
            $senha_changed = !empty($nova_senha);
            $nome_changed = ($nome !== $usuario['nome']);
            if (($email_changed || $senha_changed) && empty($senha_atual)) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Digite sua senha atual para confirmar as alterações.']);
                exit;
            }
            if (!empty($senha_atual) && !password_verify($senha_atual, $usuario['senha'])) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Senha atual incorreta.']);
                exit;
            }
            if ($email_changed) {
                $chk = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
                $chk->execute([$email, $usuario['id']]);
                if ($chk->fetch()) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Este email já está em uso.']);
                    exit;
                }
            }
            if ($senha_changed && strlen($nova_senha) < 6) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres.']);
                exit;
            }
            $updates = [];
            $params = [];
            if ($nome_changed) { $updates[] = "nome = ?"; $params[] = $nome; }
            if ($email_changed) { $updates[] = "usuario = ?"; $params[] = $email; }
            if ($senha_changed) { $updates[] = "senha = ?"; $params[] = password_hash($nova_senha, PASSWORD_DEFAULT); }
            if (empty($updates)) {
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Nenhuma alteração.', 'nome' => $nome]);
                exit;
            }
            $params[] = $usuario['id'];
            $pdo->prepare("UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id = ?")->execute($params);
            if ($nome_changed) $_SESSION['nome'] = $nome;
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso!', 'nome' => $nome, 'email_changed' => $email_changed]);
            exit;
        } catch (PDOException $e) {
            error_log("ADMIN_API update_admin_profile: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar perfil.']);
            exit;
        }
    }

    // Habilitar Painel Master (requer GATEWAYPRO_MASTER_SECRET no .env)
    if ($action === 'enable_master_panel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $envSecret = getenv('GATEWAYPRO_MASTER_SECRET');
        if (empty($envSecret)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Adicione GATEWAYPRO_MASTER_SECRET no arquivo .env']);
            exit;
        }
        setSystemSetting('is_master_panel', '1');
        setSystemSetting('master_secret_key', $envSecret);
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Painel Master habilitado. Recarregue a página.']);
        exit;
    }

    if ($action == 'regenerate_license_token') {
        // Gera um novo token para a API de licenças
        $newToken = bin2hex(random_bytes(32));
        setSystemSetting('license_api_token', $newToken);
        ob_clean();
        echo json_encode(['success' => true, 'token' => $newToken]);
        exit;
    }
    
    if ($action == 'toggle_produto_licenca') {
        // Ativa/desativa a geração de licença para um produto
        $input = json_decode(file_get_contents('php://input'), true);
        $produtoId = (int)($input['produto_id'] ?? 0);
        $geraLicenca = (int)($input['gera_licenca'] ?? 0);
        
        if ($produtoId <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'ID do produto inválido']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE produtos SET gera_licenca = ? WHERE id = ?");
        $stmt->execute([$geraLicenca, $produtoId]);
        
        ob_clean();
        echo json_encode(['success' => true]);
        exit;
    }

    // generate_license (Admin Master) - Gerar licença direto
    if ($action === 'generate_license' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../helpers/master_helper.php';
        if (!isMasterPanel()) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Acesso negado. Apenas painel master.']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $tipo = strtoupper($input['tipo'] ?? 'VITALICIO');
        $escopo = $input['escopo'] ?? 'SYSTEM';
        $escopoRefId = !empty($input['escopo_ref_id']) ? (int) $input['escopo_ref_id'] : null;
        $observacoes = trim($input['observacoes'] ?? '');
        $ownerUserId = isset($input['owner_user_id']) ? (int) $input['owner_user_id'] : ($_SESSION['id'] ?? null);

        if (file_exists(__DIR__ . '/../helpers/license_service.php')) {
            require_once __DIR__ . '/../helpers/license_service.php';
            $result = license_generate([
                'tipo' => $tipo,
                'escopo' => $escopo,
                'escopo_ref_id' => $escopoRefId,
                'owner_user_id' => $ownerUserId,
                'observacoes' => $observacoes,
            ]);
            ob_clean();
            if ($result['success']) {
                echo json_encode(['success' => true, 'license' => $result['license']]);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Erro ao gerar.']);
            }
        } else {
            // Fallback: gera licença sem license_service (compatível com instalações antigas)
            $LICENSE_SECRET = getSystemSetting('license_api_token', '');
            if (empty($LICENSE_SECRET)) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Token de licença não configurado. Gere um token na seção acima.']);
                exit;
            }
            $diasMap = ['VITALICIO' => null, 'VITALICIA' => null, 'MENSAL' => 30, 'ANUAL' => 365, 'SEMESTRAL' => 180];
            $dias = $diasMap[$tipo] ?? null;
            if ($tipo === 'VITALICIA') $tipo = 'VITALICIO';
            $uniqueId = strtoupper(bin2hex(random_bytes(4)));
            $dataToSign = "GATEWAYPRO-{$tipo}-{$uniqueId}";
            $signature = strtoupper(substr(hash('sha256', $LICENSE_SECRET . $dataToSign), 0, 8));
            $chave = "{$dataToSign}-{$signature}";
            try {
                $stmt = $pdo->prepare("INSERT INTO licencas_geradas (chave_licenca, tipo_licenca, dias_validade, aluno_email, aluno_nome, produto_id, status) VALUES (?, ?, ?, NULL, NULL, NULL, 'disponivel')");
                $stmt->execute([$chave, $tipo, $dias]);
                $dataExpiracao = null;
                if ($dias) {
                    $exp = new DateTime();
                    $exp->modify("+{$dias} days");
                    $dataExpiracao = $exp->format('Y-m-d');
                }
                ob_clean();
                echo json_encode(['success' => true, 'license' => ['chave' => $chave, 'tipo' => $tipo, 'dias_validade' => $dias, 'data_expiracao' => $dataExpiracao]]);
            } catch (PDOException $e) {
                error_log("generate_license fallback: " . $e->getMessage());
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Erro ao inserir licença. Execute migrations/add_licencas_evolucao_ALTER_ONLY.sql']);
            }
        }
        exit;
    }

    // revoke_license (Admin Master) - Revogar ou bloquear licença
    if ($action === 'revoke_license' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../helpers/master_helper.php';
        if (!isMasterPanel()) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $chave = trim($input['chave'] ?? '');
        $bloquear = !empty($input['bloquear']);
        if (empty($chave)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Chave obrigatória.']);
            exit;
        }
        if (file_exists(__DIR__ . '/../helpers/license_service.php')) {
            require_once __DIR__ . '/../helpers/license_service.php';
            $ok = license_revoke($chave, $bloquear);
        } else {
            $status = $bloquear ? 'bloqueada' : 'revogada';
            try {
                $stmt = $pdo->prepare("UPDATE licencas_geradas SET status = ? WHERE chave_licenca = ?");
                $stmt->execute([$status, $chave]);
                $ok = $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                $ok = false;
            }
        }
        ob_clean();
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Licença ' . ($bloquear ? 'bloqueada' : 'revogada') . '.' : 'Licença não encontrada.']);
        exit;
    }

    http_response_code(400);
    error_log("ADMIN_API: Ação inválida recebida: " . $action);
    // Limpa o buffer antes de enviar o JSON
    ob_clean();
    echo json_encode(['error' => 'Ação inválida']);

} catch (Throwable $e) { // Captura Exception e Error
    http_response_code(500);
    error_log('ADMIN_API: Erro Fatal na API de Admin: ' . $e->getMessage() . ' no arquivo ' . $e->getFile() . ' na linha ' . $e->getLine());
    // Limpa o buffer antes de enviar o JSON
    ob_clean();
    echo json_encode(['error' => 'Ocorreu um erro interno no servidor. Verifique os logs de erro do PHP em ' . __DIR__ . '/admin_api_errors.log para mais detalhes.']);
}
