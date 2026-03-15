<?php
/**
 * Helper para gerenciamento de acesso de alunos
 * Calcula data de expiração baseada no tipo de acesso da oferta
 */

/**
 * Calcula a data de expiração baseada no tipo de acesso
 * 
 * @param string $tipo_acesso Tipo de acesso: 'mensal', 'semestral', 'anual', 'vitalicio'
 * @param DateTime|null $data_base Data base para cálculo (default: agora)
 * @return string|null Data de expiração no formato MySQL ou NULL para vitalício
 */
function calcular_data_expiracao($tipo_acesso, $data_base = null) {
    if ($tipo_acesso === 'vitalicio' || empty($tipo_acesso)) {
        return null;
    }
    
    $data = $data_base ? clone $data_base : new DateTime();
    
    switch ($tipo_acesso) {
        case 'mensal':
            $data->modify('+30 days');
            break;
        case 'semestral':
            $data->modify('+180 days');
            break;
        case 'anual':
            $data->modify('+365 days');
            break;
        default:
            return null;
    }
    
    return $data->format('Y-m-d H:i:s');
}

/**
 * Busca o tipo de acesso de uma oferta
 * 
 * @param PDO $pdo Conexão PDO
 * @param int|null $oferta_id ID da oferta (NULL = preço padrão = vitalício)
 * @return string Tipo de acesso
 */
function get_tipo_acesso_oferta($pdo, $oferta_id) {
    if (empty($oferta_id)) {
        return 'vitalicio';
    }
    
    try {
        $stmt = $pdo->prepare("SELECT tipo_acesso FROM produto_ofertas WHERE id = ?");
        $stmt->execute([$oferta_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['tipo_acesso'] ?? 'vitalicio';
    } catch (PDOException $e) {
        error_log("Erro ao buscar tipo de acesso da oferta: " . $e->getMessage());
        return 'vitalicio';
    }
}

/**
 * Concede acesso ao aluno com data de expiração baseada na oferta
 * 
 * @param PDO $pdo Conexão PDO
 * @param string $aluno_email Email do aluno
 * @param int $produto_id ID do produto
 * @param int|null $oferta_id ID da oferta (NULL = preço padrão)
 * @return bool True se o acesso foi concedido com sucesso
 */
function conceder_acesso_aluno($pdo, $aluno_email, $produto_id, $oferta_id = null) {
    try {
        // Buscar tipo de acesso da oferta
        $tipo_acesso = get_tipo_acesso_oferta($pdo, $oferta_id);
        $data_expiracao = calcular_data_expiracao($tipo_acesso);
        
        // Verificar se já existe acesso
        $stmt_check = $pdo->prepare("SELECT id, data_expiracao FROM alunos_acessos WHERE aluno_email = ? AND produto_id = ?");
        $stmt_check->execute([$aluno_email, $produto_id]);
        $acesso_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($acesso_existente) {
            // Se já existe acesso, atualizar a data de expiração se a nova for maior ou se for vitalício
            if ($data_expiracao === null) {
                // Nova oferta é vitalícia - atualizar para vitalício
                $stmt_update = $pdo->prepare("UPDATE alunos_acessos SET data_expiracao = NULL, oferta_id = ? WHERE id = ?");
                $stmt_update->execute([$oferta_id, $acesso_existente['id']]);
                error_log("Acesso atualizado para vitalício: $aluno_email, produto $produto_id");
            } elseif ($acesso_existente['data_expiracao'] === null) {
                // Acesso existente é vitalício - manter vitalício
                error_log("Acesso já é vitalício, mantendo: $aluno_email, produto $produto_id");
            } else {
                // Comparar datas e usar a maior
                $data_existente = new DateTime($acesso_existente['data_expiracao']);
                $data_nova = new DateTime($data_expiracao);
                
                if ($data_nova > $data_existente) {
                    $stmt_update = $pdo->prepare("UPDATE alunos_acessos SET data_expiracao = ?, oferta_id = ? WHERE id = ?");
                    $stmt_update->execute([$data_expiracao, $oferta_id, $acesso_existente['id']]);
                    error_log("Data de expiração estendida: $aluno_email, produto $produto_id, nova data: $data_expiracao");
                } else {
                    error_log("Acesso existente tem data maior, mantendo: $aluno_email, produto $produto_id");
                }
            }
            return true;
        }
        
        // Inserir novo acesso
        $stmt_insert = $pdo->prepare("INSERT INTO alunos_acessos (aluno_email, produto_id, oferta_id, data_expiracao) VALUES (?, ?, ?, ?)");
        $stmt_insert->execute([$aluno_email, $produto_id, $oferta_id, $data_expiracao]);
        
        $expiracao_log = $data_expiracao ?? 'vitalício';
        error_log("Acesso concedido: $aluno_email, produto $produto_id, oferta $oferta_id, expiração: $expiracao_log");
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao conceder acesso: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se o aluno tem acesso válido ao produto
 * 
 * @param PDO $pdo Conexão PDO
 * @param string $aluno_email Email do aluno
 * @param int $produto_id ID do produto
 * @return array ['tem_acesso' => bool, 'data_expiracao' => string|null, 'expirado' => bool]
 */
function verificar_acesso_aluno($pdo, $aluno_email, $produto_id) {
    try {
        $stmt = $pdo->prepare("SELECT data_concessao, data_expiracao FROM alunos_acessos WHERE LOWER(TRIM(aluno_email)) = LOWER(TRIM(?)) AND produto_id = ?");
        $stmt->execute([$aluno_email, $produto_id]);
        $acesso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$acesso) {
            return ['tem_acesso' => false, 'data_expiracao' => null, 'expirado' => false];
        }
        
        // Se data_expiracao é NULL, é vitalício
        if ($acesso['data_expiracao'] === null) {
            return [
                'tem_acesso' => true, 
                'data_expiracao' => null, 
                'expirado' => false,
                'data_concessao' => $acesso['data_concessao'],
                'vitalicio' => true
            ];
        }
        
        // Verificar se expirou
        $agora = new DateTime();
        $expiracao = new DateTime($acesso['data_expiracao']);
        $expirado = $agora > $expiracao;
        
        return [
            'tem_acesso' => !$expirado, 
            'data_expiracao' => $acesso['data_expiracao'], 
            'expirado' => $expirado,
            'data_concessao' => $acesso['data_concessao'],
            'vitalicio' => false
        ];
    } catch (PDOException $e) {
        error_log("Erro ao verificar acesso: " . $e->getMessage());
        return ['tem_acesso' => false, 'data_expiracao' => null, 'expirado' => false];
    }
}
