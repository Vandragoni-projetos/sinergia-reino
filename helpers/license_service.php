<?php
/**
 * License Service - Evolução do Sistema de Licenças
 *
 * Funções para gerar, ativar, validar e verificar licenças.
 * Suporta: tipos (vitalicia, mensal, anual), escopo (SYSTEM, PRODUCT, etc.),
 * owner/assigned, expiração automática.
 *
 * @see docs/DOC_LICENCAS_ANALISE.md
 * @see migrations/add_licencas_evolucao.sql
 */

if (!function_exists('getSystemSetting')) {
    require_once __DIR__ . '/../config/config.php';
}

/** Tipos de licença suportados */
define('LICENSE_TYPE_VITALICIA', 'VITALICIO');
define('LICENSE_TYPE_MENSAL', 'MENSAL');
define('LICENSE_TYPE_ANUAL', 'ANUAL');
define('LICENSE_TYPE_SEMESTRAL', 'SEMESTRAL');

/** Escopos de licença */
define('LICENSE_SCOPE_SYSTEM', 'SYSTEM');
define('LICENSE_SCOPE_COMMUNITY', 'COMMUNITY');
define('LICENSE_SCOPE_PRODUCT', 'PRODUCT');
define('LICENSE_SCOPE_USER_LIMIT', 'USER_LIMIT');

/** Status de licença */
define('LICENSE_STATUS_DISPONIVEL', 'disponivel');
define('LICENSE_STATUS_ATIVA', 'ativa');
define('LICENSE_STATUS_ATIVADA', 'ativada'); // retrocompat
define('LICENSE_STATUS_EXPIRADA', 'expirada');
define('LICENSE_STATUS_BLOQUEADA', 'bloqueada');
define('LICENSE_STATUS_REVOGADA', 'revogada');

/** Dias por tipo */
const LICENSE_DAYS_MAP = [
    'VITALICIO' => null,
    'VITALICIA' => null,
    'MENSAL'    => 30,
    'ANUAL'     => 365,
    'SEMESTRAL' => 180,
];

/**
 * Gera uma nova licença.
 *
 * @param array $opts [tipo, escopo, escopo_ref_id, owner_user_id, produto_id, observacoes]
 * @return array [success, license|error]
 */
function license_generate($opts = []) {
    global $pdo;

    $tipo = strtoupper($opts['tipo'] ?? 'VITALICIO');
    $escopo = $opts['escopo'] ?? LICENSE_SCOPE_SYSTEM;
    $escopoRefId = isset($opts['escopo_ref_id']) ? (int) $opts['escopo_ref_id'] : null;
    $ownerUserId = isset($opts['owner_user_id']) ? (int) $opts['owner_user_id'] : null;
    $produtoId = isset($opts['produto_id']) ? (int) $opts['produto_id'] : null;
    $observacoes = trim($opts['observacoes'] ?? '');
    $alunoEmail = $opts['aluno_email'] ?? null;
    $alunoNome = $opts['aluno_nome'] ?? null;

    $dias = LICENSE_DAYS_MAP[$tipo] ?? null;
    if ($tipo === 'VITALICIA') $tipo = 'VITALICIO'; // normaliza

    $allowedTypes = ['VITALICIO', 'MENSAL', 'ANUAL', 'SEMESTRAL'];
    if (!in_array($tipo, $allowedTypes)) {
        return ['success' => false, 'error' => 'Tipo de licença inválido.'];
    }

    $allowedScopes = [LICENSE_SCOPE_SYSTEM, LICENSE_SCOPE_COMMUNITY, LICENSE_SCOPE_PRODUCT, LICENSE_SCOPE_USER_LIMIT];
    if (!in_array($escopo, $allowedScopes)) {
        return ['success' => false, 'error' => 'Escopo inválido.'];
    }

    try {
        $secret = getSystemSetting('license_api_token', '');
        if (empty($secret)) {
            return ['success' => false, 'error' => 'Token de licença não configurado.'];
        }

        $uniqueId = strtoupper(bin2hex(random_bytes(4)));
        $dataToSign = "GATEWAYPRO-{$tipo}-{$uniqueId}";
        $signature = strtoupper(substr(hash('sha256', $secret . $dataToSign), 0, 8));
        $chave = "{$dataToSign}-{$signature}";

        $stmt = $pdo->prepare("
            INSERT INTO licencas_geradas 
            (chave_licenca, tipo_licenca, dias_validade, escopo, escopo_ref_id, status, owner_user_id, aluno_email, aluno_nome, produto_id, observacoes)
            VALUES (?, ?, ?, ?, ?, 'disponivel', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $chave, $tipo, $dias, $escopo, $escopoRefId ?: null,
            $ownerUserId ?: null, $alunoEmail ?: null, $alunoNome ?: null,
            $produtoId ?: null, $observacoes ?: null
        ]);

        $id = $pdo->lastInsertId();
        $dataExpiracao = null;
        if ($dias) {
            $exp = new DateTime();
            $exp->modify("+{$dias} days");
            $dataExpiracao = $exp->format('Y-m-d');
        }

        return [
            'success' => true,
            'license' => [
                'id' => $id,
                'chave' => $chave,
                'tipo' => $tipo,
                'dias_validade' => $dias,
                'escopo' => $escopo,
                'data_expiracao' => $dataExpiracao,
            ],
        ];
    } catch (PDOException $e) {
        error_log("license_generate: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao gerar licença.'];
    }
}

/**
 * Valida se uma licença existe e está válida (no banco local).
 * Usado pelo license_api.php no master.
 *
 * @param string $chave
 * @return array [valid, reason?, licenseType?, licenseDays?, expirationDate?]
 */
function license_validate_local($chave) {
    global $pdo;

    $chave = trim($chave);
    if (empty($chave)) {
        return ['valid' => false, 'reason' => 'Chave obrigatória.'];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM licencas_geradas WHERE chave_licenca = ?");
        $stmt->execute([$chave]);
        $lic = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lic) {
            return ['valid' => false, 'reason' => 'Chave não encontrada.'];
        }

        if (in_array($lic['status'], [LICENSE_STATUS_REVOGADA, LICENSE_STATUS_BLOQUEADA])) {
            return ['valid' => false, 'reason' => 'Licença revogada ou bloqueada.'];
        }

        // Disponivel: ainda não foi ativada - retorna flag para ativar
        if ($lic['status'] === LICENSE_STATUS_DISPONIVEL) {
            return ['valid' => false, 'reason' => 'Licença disponível para ativação.', 'needs_activation' => true, 'license' => $lic];
        }

        if ($lic['status'] === LICENSE_STATUS_EXPIRADA) {
            return [
                'valid' => false,
                'reason' => 'Licença expirada.',
                'expirationDate' => $lic['data_expiracao'] ?? null,
            ];
        }

        // Verifica expiração se ativa/ativada
        if (in_array($lic['status'], [LICENSE_STATUS_ATIVA, LICENSE_STATUS_ATIVADA]) && !empty($lic['data_expiracao'])) {
            $exp = new DateTime($lic['data_expiracao']);
            if (new DateTime() > $exp) {
                $pdo->prepare("UPDATE licencas_geradas SET status = ? WHERE id = ?")
                    ->execute([LICENSE_STATUS_EXPIRADA, $lic['id']]);
                return [
                    'valid' => false,
                    'reason' => 'Licença expirada em ' . $exp->format('d/m/Y'),
                    'expirationDate' => $lic['data_expiracao'],
                ];
            }
        }

        return [
            'valid' => true,
            'licenseType' => $lic['tipo_licenca'],
            'licenseDays' => $lic['dias_validade'],
            'expirationDate' => $lic['data_expiracao'] ?? null,
            'escopo' => $lic['escopo'] ?? LICENSE_SCOPE_SYSTEM,
        ];
    } catch (PDOException $e) {
        error_log("license_validate_local: " . $e->getMessage());
        return ['valid' => false, 'reason' => 'Erro ao validar licença.'];
    }
}

/**
 * Ativa uma licença (primeira ativação - status disponivel -> ativa).
 *
 * @param string $chave
 * @param string $instalacaoId system_id da instalação
 * @param int|null $assignedUserId usuario_id que está ativando (opcional)
 * @return array [valid, reason?, licenseType?, expirationDate?]
 */
function license_activate_local($chave, $instalacaoId = '', $assignedUserId = null) {
    global $pdo;

    $chave = trim($chave);
    if (empty($chave)) {
        return ['valid' => false, 'reason' => 'Chave obrigatória.'];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM licencas_geradas WHERE chave_licenca = ?");
        $stmt->execute([$chave]);
        $lic = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lic) {
            return ['valid' => false, 'reason' => 'Chave não encontrada.'];
        }

        if ($lic['status'] !== LICENSE_STATUS_DISPONIVEL) {
            return license_validate_local($chave); // Já ativada ou inválida - revalida
        }

        $dataAtivacao = date('Y-m-d H:i:s');
        $dataExpiracao = null;
        if (!empty($lic['dias_validade'])) {
            $exp = new DateTime();
            $exp->modify("+{$lic['dias_validade']} days");
            $dataExpiracao = $exp->format('Y-m-d');
        }

        $stmtUp = $pdo->prepare("
            UPDATE licencas_geradas 
            SET status = 'ativa', data_ativacao = ?, data_expiracao = ?, instalacao_id = ?, ip_ativacao = ?, assigned_user_id = ?
            WHERE id = ?
        ");
        $stmtUp->execute([
            $dataAtivacao,
            $dataExpiracao,
            $instalacaoId ?: null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $assignedUserId ?: null,
            $lic['id'],
        ]);

        return [
            'valid' => true,
            'licenseType' => $lic['tipo_licenca'],
            'licenseDays' => $lic['dias_validade'],
            'expirationDate' => $dataExpiracao,
        ];
    } catch (PDOException $e) {
        error_log("license_activate_local: " . $e->getMessage());
        return ['valid' => false, 'reason' => 'Erro ao ativar licença.'];
    }
}

/**
 * Bloqueia ou revoga uma licença (admin master).
 */
function license_revoke($chave, $bloquear = false) {
    global $pdo;
    $status = $bloquear ? LICENSE_STATUS_BLOQUEADA : LICENSE_STATUS_REVOGADA;
    try {
        $stmt = $pdo->prepare("UPDATE licencas_geradas SET status = ? WHERE chave_licenca = ?");
        $stmt->execute([$status, trim($chave)]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Lista licenças conforme permissão do usuário.
 * Admin master: todas. Infoprodutor: só as que ele gerou.
 */
function license_list($ownerUserId = null, $limit = 50) {
    global $pdo;
    try {
        if ($ownerUserId !== null) {
            $stmt = $pdo->prepare("
                SELECT * FROM licencas_geradas 
                WHERE owner_user_id = ? 
                ORDER BY data_geracao DESC 
                LIMIT ?
            ");
            $stmt->execute([$ownerUserId, $limit]);
        } else {
            $stmt = $pdo->query("
                SELECT * FROM licencas_geradas 
                ORDER BY data_geracao DESC 
                LIMIT " . (int) $limit
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
