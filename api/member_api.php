<?php
require_once __DIR__ . '/../config/config.php';

// Configura os cabeçalhos para JSON
header('Content-Type: application/json');

// Permite requisições de origens diferentes (CORS) se necessário.
// Para uma API no mesmo domínio da aplicação, pode não ser estritamente necessário,
// mas é uma boa prática para APIs. Descomente e ajuste se a API for acessada de outro domínio.
/*
header("Access-Control-Allow-Origin: *"); // Altere '*' para o domínio específico do frontend em produção
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
*/

// Função auxiliar para enviar resposta JSON
function sendJsonResponse($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    // [MUDANÇA] Adicionado JSON_UNESCAPED_SLASHES para URLs legíveis, se houver
    echo json_encode(['success' => $success] + $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Verifica se o usuário está logado e é um cliente ('usuario')
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || (isset($_SESSION["tipo"]) && $_SESSION["tipo"] !== 'usuario')) {
    sendJsonResponse(false, ['error' => 'Acesso não autorizado. Você precisa estar logado como cliente.'], 401);
}

$aluno_email_logado = $_SESSION['usuario']; // E-mail do cliente logado

// Obtém a ação da requisição
$action = $_GET['action'] ?? '';

// [MUDANÇA] Obtém os dados do corpo da requisição POST
$input = json_decode(file_get_contents('php://input'), true);

switch ($action) {
    case 'mark_lesson_complete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, ['error' => 'Método não permitido. Use POST.'], 405);
        }

        $aula_id = $input['aula_id'] ?? null;
        $aluno_email_request = $input['aluno_email'] ?? null; // E-mail enviado na requisição

        if (!$aula_id || !is_numeric($aula_id)) {
            sendJsonResponse(false, ['error' => 'ID da aula inválido.'], 400);
        }

        // Validação de segurança: o e-mail na requisição deve ser o mesmo do usuário logado
        if ($aluno_email_request !== $aluno_email_logado) {
            sendJsonResponse(false, ['error' => 'Erro de segurança: e-mail de aluno não corresponde ao logado.'], 403);
        }

        try {
            // Usa INSERT IGNORE para evitar duplicatas se a aula já foi marcada
            $stmt = $pdo->prepare("INSERT IGNORE INTO aluno_progresso (aluno_email, aula_id, data_conclusao) VALUES (?, ?, NOW())");
            $stmt->execute([$aluno_email_logado, $aula_id]);

            // Se rowCount() > 0, um novo registro foi inserido. Se for 0, o registro já existia.
            if ($stmt->rowCount() > 0) {
                sendJsonResponse(true, ['message' => 'Aula marcada como concluída.']);
            } else {
                sendJsonResponse(true, ['message' => 'Aula já estava marcada como concluída.']);
            }

        } catch (PDOException $e) {
            // Em ambiente de produção, logar o erro e retornar uma mensagem genérica
            error_log("Erro de DB ao marcar aula: " . $e->getMessage());
            sendJsonResponse(false, ['error' => 'Erro interno do servidor ao marcar aula.'], 500);
        }
        break;

    // [NOVO CASE ADICIONADO]
    case 'unmark_lesson_complete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, ['error' => 'Método não permitido. Use POST.'], 405);
        }

        $aula_id = $input['aula_id'] ?? null;
        $aluno_email_request = $input['aluno_email'] ?? null; // E-mail enviado na requisição

        if (!$aula_id || !is_numeric($aula_id)) {
            sendJsonResponse(false, ['error' => 'ID da aula inválido.'], 400);
        }

        // Validação de segurança: o e-mail na requisição deve ser o mesmo do usuário logado
        if ($aluno_email_request !== $aluno_email_logado) {
            sendJsonResponse(false, ['error' => 'Erro de segurança: e-mail de aluno não corresponde ao logado.'], 403);
        }

        try {
            // Deleta o registro de progresso para este aluno e esta aula
            $stmt = $pdo->prepare("DELETE FROM aluno_progresso WHERE aluno_email = ? AND aula_id = ?");
            $stmt->execute([$aluno_email_logado, $aula_id]);

            if ($stmt->rowCount() > 0) {
                sendJsonResponse(true, ['message' => 'Aula desmarcada como concluída.']);
            } else {
                // Isso pode acontecer se o usuário clicar rápido ou se o registro já não existia
                sendJsonResponse(true, ['message' => 'Aula já não estava marcada como concluída.']);
            }

        } catch (PDOException $e) {
            // Em ambiente de produção, logar o erro e retornar uma mensagem genérica
            error_log("Erro de DB ao desmarcar aula: " . $e->getMessage());
            sendJsonResponse(false, ['error' => 'Erro interno do servidor ao desmarcar aula.'], 500);
        }
        break;


    // [NOVO CASE] Atualizar perfil do membro
    case 'update_member_profile':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, ['error' => 'Método não permitido. Use POST.'], 405);
        }

        $nome = trim($input['nome'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha_atual = $input['senha_atual'] ?? '';
        $nova_senha = $input['nova_senha'] ?? '';

        // Validações básicas
        if (empty($nome)) {
            sendJsonResponse(false, ['error' => 'Nome é obrigatório.'], 400);
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, ['error' => 'Email inválido.'], 400);
        }

        try {
            // Busca dados atuais do usuário
            $stmt = $pdo->prepare("SELECT id, usuario, nome, senha FROM usuarios WHERE usuario = ? AND tipo = 'usuario'");
            $stmt->execute([$aluno_email_logado]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                sendJsonResponse(false, ['error' => 'Usuário não encontrado.'], 404);
            }

            // Verifica se há alterações que requerem senha
            $email_changed = ($email !== $aluno_email_logado);
            $senha_changed = !empty($nova_senha);
            $nome_changed = ($nome !== $usuario['nome']);

            // Se vai alterar email ou senha, precisa da senha atual
            if (($email_changed || $senha_changed) && empty($senha_atual)) {
                sendJsonResponse(false, ['error' => 'Digite sua senha atual para confirmar as alterações.'], 400);
            }

            // Verifica senha atual se necessário
            if (!empty($senha_atual)) {
                if (!password_verify($senha_atual, $usuario['senha'])) {
                    sendJsonResponse(false, ['error' => 'Senha atual incorreta.'], 400);
                }
            }

            // Se vai mudar o email, verifica se já existe
            if ($email_changed) {
                $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
                $stmt_check->execute([$email, $usuario['id']]);
                if ($stmt_check->fetch()) {
                    sendJsonResponse(false, ['error' => 'Este email já está em uso.'], 400);
                }
            }

            // Validação da nova senha
            if ($senha_changed && strlen($nova_senha) < 6) {
                sendJsonResponse(false, ['error' => 'A nova senha deve ter pelo menos 6 caracteres.'], 400);
            }

            // Monta a query de atualização
            $updates = [];
            $params = [];

            if ($nome_changed) {
                $updates[] = "nome = ?";
                $params[] = $nome;
            }

            if ($email_changed) {
                $updates[] = "usuario = ?";
                $params[] = $email;
            }

            if ($senha_changed) {
                $updates[] = "senha = ?";
                $params[] = password_hash($nova_senha, PASSWORD_DEFAULT);
            }

            if (empty($updates)) {
                sendJsonResponse(true, ['message' => 'Nenhuma alteração detectada.', 'nome' => $nome]);
            }

            $params[] = $usuario['id'];
            $sql = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt_update = $pdo->prepare($sql);
            $stmt_update->execute($params);

            // Atualiza a sessão
            if ($nome_changed) {
                $_SESSION['nome'] = $nome;
            }

            // Se o email mudou, atualiza também na tabela alunos_acessos
            if ($email_changed) {
                $stmt_acessos = $pdo->prepare("UPDATE alunos_acessos SET aluno_email = ? WHERE aluno_email = ?");
                $stmt_acessos->execute([$email, $aluno_email_logado]);
                
                // Atualiza progresso também
                $stmt_progresso = $pdo->prepare("UPDATE aluno_progresso SET aluno_email = ? WHERE aluno_email = ?");
                $stmt_progresso->execute([$email, $aluno_email_logado]);
                
                $_SESSION['usuario'] = $email;
            }

            sendJsonResponse(true, [
                'message' => 'Perfil atualizado com sucesso!',
                'nome' => $nome,
                'email_changed' => $email_changed
            ]);

        } catch (PDOException $e) {
            error_log("Erro ao atualizar perfil do membro: " . $e->getMessage());
            sendJsonResponse(false, ['error' => 'Erro interno ao atualizar perfil.'], 500);
        }
        break;


    // ========================================
    // SISTEMA DE GERAÇÃO DE LICENÇAS (MASTER PANEL)
    // ========================================
    
    case 'get_license_info':
        // Retorna informações sobre o direito do aluno de gerar licenças
        // Só funciona se for o painel master legítimo
        
        require_once __DIR__ . '/../helpers/master_helper.php';
        if (!isMasterPanel()) {
            sendJsonResponse(false, ['error' => 'Funcionalidade não disponível.'], 403);
        }
        
        try {
            // Busca os produtos do aluno que dão direito a licença
            // Apenas produtos com gera_licenca = 1
            $stmt = $pdo->prepare("
                SELECT 
                    aa.produto_id,
                    aa.data_concessao,
                    aa.data_expiracao,
                    aa.oferta_id,
                    p.nome AS produto_nome,
                    po.tipo_acesso,
                    po.nome AS oferta_nome
                FROM alunos_acessos aa
                JOIN produtos p ON aa.produto_id = p.id
                LEFT JOIN produto_ofertas po ON aa.oferta_id = po.id
                WHERE aa.aluno_email = ?
                AND p.gera_licenca = 1
                AND (aa.data_expiracao IS NULL OR aa.data_expiracao > NOW())
                ORDER BY aa.data_concessao DESC
            ");
            $stmt->execute([$aluno_email_logado]);
            $acessos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mapeia tipo_acesso para tipo de licença
            $licenseMap = [
                'vitalicio' => ['tipo' => 'VITALICIO', 'dias' => null, 'nome' => 'Vitalício'],
                'anual' => ['tipo' => 'ANUAL', 'dias' => 365, 'nome' => 'Anual'],
                'semestral' => ['tipo' => 'SEMESTRAL', 'dias' => 180, 'nome' => 'Semestral'],
                'mensal' => ['tipo' => 'MENSAL', 'dias' => 30, 'nome' => 'Mensal']
            ];
            
            $licenseRights = [];
            foreach ($acessos as $acesso) {
                // Primeiro tenta pegar da oferta
                $tipoAcesso = $acesso['tipo_acesso'];
                
                // Se não tem oferta, infere pelo data_expiracao
                if (empty($tipoAcesso)) {
                    if (empty($acesso['data_expiracao'])) {
                        $tipoAcesso = 'vitalicio';
                    } else {
                        // Calcula dias entre concessão e expiração
                        $dataConcessao = new DateTime($acesso['data_concessao']);
                        $dataExpiracao = new DateTime($acesso['data_expiracao']);
                        $dias = $dataConcessao->diff($dataExpiracao)->days;
                        
                        if ($dias <= 35) {
                            $tipoAcesso = 'mensal';
                        } elseif ($dias <= 190) {
                            $tipoAcesso = 'semestral';
                        } elseif ($dias <= 370) {
                            $tipoAcesso = 'anual';
                        } else {
                            $tipoAcesso = 'vitalicio';
                        }
                    }
                }
                
                $licenseInfo = $licenseMap[$tipoAcesso] ?? $licenseMap['vitalicio'];
                
                $licenseRights[] = [
                    'produto_id' => $acesso['produto_id'],
                    'produto_nome' => $acesso['produto_nome'],
                    'oferta_nome' => $acesso['oferta_nome'],
                    'tipo_acesso' => $tipoAcesso,
                    'tipo_licenca' => $licenseInfo['tipo'],
                    'dias_validade' => $licenseInfo['dias'],
                    'nome_licenca' => $licenseInfo['nome'],
                    'data_concessao' => $acesso['data_concessao'],
                    'data_expiracao' => $acesso['data_expiracao']
                ];
            }
            
            // Busca licenças já geradas pelo aluno
            $stmt2 = $pdo->prepare("
                SELECT 
                    id,
                    chave_licenca,
                    tipo_licenca,
                    dias_validade,
                    produto_id,
                    data_geracao,
                    data_ativacao,
                    data_expiracao,
                    status
                FROM licencas_geradas
                WHERE aluno_email = ?
                ORDER BY data_geracao DESC
                LIMIT 50
            ");
            $stmt2->execute([$aluno_email_logado]);
            $licensesGenerated = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse(true, [
                'license_rights' => $licenseRights,
                'licenses_generated' => $licensesGenerated
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar info de licenças: " . $e->getMessage());
            sendJsonResponse(false, ['error' => 'Erro ao buscar informações.'], 500);
        }
        break;
        
    case 'generate_license':
        // Gera uma nova licença para o aluno
        // Só funciona se for o painel master legítimo
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, ['error' => 'Método não permitido. Use POST.'], 405);
        }
        
        require_once __DIR__ . '/../helpers/master_helper.php';
        if (!isMasterPanel()) {
            sendJsonResponse(false, ['error' => 'Funcionalidade não disponível.'], 403);
        }
        
        $produto_id = $input['produto_id'] ?? null;
        
        if (!$produto_id || !is_numeric($produto_id)) {
            sendJsonResponse(false, ['error' => 'ID do produto inválido.'], 400);
        }
        
        try {
            // Verifica se o aluno tem acesso ao produto E se o produto gera licença
            $stmt = $pdo->prepare("
                SELECT 
                    aa.produto_id,
                    aa.oferta_id,
                    aa.data_concessao,
                    aa.data_expiracao,
                    p.nome AS produto_nome,
                    p.gera_licenca,
                    po.tipo_acesso
                FROM alunos_acessos aa
                JOIN produtos p ON aa.produto_id = p.id
                LEFT JOIN produto_ofertas po ON aa.oferta_id = po.id
                WHERE aa.aluno_email = ?
                AND aa.produto_id = ?
                AND p.gera_licenca = 1
                AND (aa.data_expiracao IS NULL OR aa.data_expiracao > NOW())
            ");
            $stmt->execute([$aluno_email_logado, $produto_id]);
            $acesso = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$acesso) {
                sendJsonResponse(false, ['error' => 'Você não tem permissão para gerar licenças com este produto.'], 403);
            }
            
            // Mapeia tipo_acesso para tipo de licença
            $licenseMap = [
                'vitalicio' => ['tipo' => 'VITALICIO', 'dias' => null],
                'anual' => ['tipo' => 'ANUAL', 'dias' => 365],
                'semestral' => ['tipo' => 'SEMESTRAL', 'dias' => 180],
                'mensal' => ['tipo' => 'MENSAL', 'dias' => 30]
            ];
            
            // Primeiro tenta pegar da oferta
            $tipoAcesso = $acesso['tipo_acesso'];
            
            // Se não tem oferta, infere pelo data_expiracao
            if (empty($tipoAcesso)) {
                if (empty($acesso['data_expiracao'])) {
                    $tipoAcesso = 'vitalicio';
                } else {
                    // Calcula dias entre concessão e expiração
                    $dataConcessao = new DateTime($acesso['data_concessao']);
                    $dataExpiracao = new DateTime($acesso['data_expiracao']);
                    $dias = $dataConcessao->diff($dataExpiracao)->days;
                    
                    // Infere baseado nos dias totais do plano
                    if ($dias <= 35) {
                        $tipoAcesso = 'mensal';
                    } elseif ($dias <= 190) {
                        $tipoAcesso = 'semestral';
                    } elseif ($dias <= 370) {
                        $tipoAcesso = 'anual';
                    } else {
                        $tipoAcesso = 'vitalicio';
                    }
                }
            }
            
            $licenseInfo = $licenseMap[$tipoAcesso] ?? $licenseMap['vitalicio'];

            // Busca usuario_id do aluno (owner)
            $stmtUser = $pdo->prepare("SELECT id, nome FROM usuarios WHERE usuario = ?");
            $stmtUser->execute([$aluno_email_logado]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $ownerUserId = $userRow ? (int) $userRow['id'] : null;
            $alunoNome = $userRow['nome'] ?? $aluno_email_logado;

            // Usa license_service se disponível (evolução), senão lógica legada
            if (file_exists(__DIR__ . '/../helpers/license_service.php')) {
                require_once __DIR__ . '/../helpers/license_service.php';
                $result = license_generate([
                    'tipo' => $licenseInfo['tipo'],
                    'escopo' => 'SYSTEM',
                    'owner_user_id' => $ownerUserId,
                    'produto_id' => $produto_id,
                    'aluno_email' => $aluno_email_logado,
                    'aluno_nome' => $alunoNome,
                ]);
                if ($result['success']) {
                    $lic = $result['license'];
                    sendJsonResponse(true, [
                        'message' => 'Licença gerada com sucesso!',
                        'license' => [
                            'chave' => $lic['chave'],
                            'tipo' => $lic['tipo'],
                            'dias_validade' => $lic['dias_validade'],
                            'produto_nome' => $acesso['produto_nome']
                        ]
                    ]);
                }
                sendJsonResponse(false, ['error' => $result['error'] ?? 'Erro ao gerar licença.'], 500);
            }

            // Fallback: lógica legada
            $LICENSE_SECRET_KEY = getSystemSetting('license_api_token', '');
            if (empty($LICENSE_SECRET_KEY)) {
                sendJsonResponse(false, ['error' => 'Token de licença não configurado no sistema.'], 500);
            }
            $uniqueId = strtoupper(bin2hex(random_bytes(4)));
            $dataToSign = "GATEWAYPRO-{$licenseInfo['tipo']}-{$uniqueId}";
            $signature = strtoupper(substr(hash('sha256', $LICENSE_SECRET_KEY . $dataToSign), 0, 8));
            $chave = "{$dataToSign}-{$signature}";

            $stmt2 = $pdo->prepare("
                INSERT INTO licencas_geradas 
                (chave_licenca, tipo_licenca, dias_validade, aluno_email, aluno_nome, produto_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'disponivel')
            ");
            $stmt2->execute([
                $chave, $licenseInfo['tipo'], $licenseInfo['dias'],
                $aluno_email_logado, $alunoNome, $produto_id
            ]);

            sendJsonResponse(true, [
                'message' => 'Licença gerada com sucesso!',
                'license' => [
                    'chave' => $chave,
                    'tipo' => $licenseInfo['tipo'],
                    'dias_validade' => $licenseInfo['dias'],
                    'produto_nome' => $acesso['produto_nome']
                ]
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao gerar licença: " . $e->getMessage());
            sendJsonResponse(false, ['error' => 'Erro ao gerar licença.'], 500);
        }
        break;

    default:
        sendJsonResponse(false, ['error' => 'Ação desconhecida ou não especificada.'], 400);
        break;
}

?>