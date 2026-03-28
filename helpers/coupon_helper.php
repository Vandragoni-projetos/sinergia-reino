<?php
/**
 * Helper para validação e cálculo de cupons de desconto
 */

if (!function_exists('validarCupom')) {
    /**
     * Valida um cupom para um produto e valor total.
     * @param string $codigo Código do cupom
     * @param int $produto_id ID do produto principal
     * @param float $valor_total Valor total do carrinho
     * @param int $usuario_id ID do infoprodutor (dono do produto)
     * @return array ['valid' => bool, 'cupom_id' => int|null, 'valor_desconto' => float, 'mensagem' => string]
     */
    function validarCupom($codigo, $produto_id, $valor_total, $usuario_id) {
        global $pdo;
        $result = ['valid' => false, 'cupom_id' => null, 'valor_desconto' => 0.0, 'mensagem' => ''];

        if (empty($codigo) || !isset($pdo)) {
            $result['mensagem'] = 'Código inválido.';
            return $result;
        }

        $codigo = strtoupper(trim($codigo));
        if (strlen($codigo) < 2) {
            $result['mensagem'] = 'Código inválido.';
            return $result;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT c.* FROM cupons c
                WHERE c.usuario_id = ? AND UPPER(TRIM(c.codigo)) = ? AND c.ativo = 1
            ");
            $stmt->execute([$usuario_id, $codigo]);
            $cupom = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cupom) {
                $result['mensagem'] = 'Cupom não encontrado ou inativo.';
                return $result;
            }

            $now = date('Y-m-d H:i:s');
            if ($cupom['valido_de'] && $cupom['valido_de'] > $now) {
                $result['mensagem'] = 'Este cupom ainda não está válido.';
                return $result;
            }
            if ($cupom['valido_ate'] && $cupom['valido_ate'] < $now) {
                $result['mensagem'] = 'Este cupom expirou.';
                return $result;
            }

            if ($cupom['max_usos'] !== null && (int)$cupom['usos_atual'] >= (int)$cupom['max_usos']) {
                $result['mensagem'] = 'Este cupom atingiu o limite de usos.';
                return $result;
            }

            $pedido_minimo = $cupom['pedido_minimo'] !== null ? (float)$cupom['pedido_minimo'] : null;
            if ($pedido_minimo !== null && $valor_total < $pedido_minimo) {
                $result['mensagem'] = 'Pedido mínimo de R$ ' . number_format($pedido_minimo, 2, ',', '.') . ' para usar este cupom.';
                return $result;
            }

            // Verificar se o cupom vale para este produto
            $stmt_cp = $pdo->prepare("SELECT COUNT(*) FROM cupom_produtos WHERE cupom_id = ? AND produto_id = ?");
            $stmt_cp->execute([$cupom['id'], $produto_id]);
            $tem_restricao = $stmt_cp->fetchColumn() > 0;

            $stmt_all = $pdo->prepare("SELECT COUNT(*) FROM cupom_produtos WHERE cupom_id = ?");
            $stmt_all->execute([$cupom['id']]);
            $total_produtos = $stmt_all->fetchColumn();

            if ($total_produtos > 0 && !$tem_restricao) {
                $result['mensagem'] = 'Este cupom não é válido para este produto.';
                try {
                    $sn = $pdo->prepare('
                        SELECT p.nome FROM cupom_produtos cp
                        INNER JOIN produtos p ON p.id = cp.produto_id AND p.usuario_id = ?
                        WHERE cp.cupom_id = ?
                        ORDER BY p.nome ASC
                    ');
                    $sn->execute([(int) $usuario_id, $cupom['id']]);
                    $nomes = $sn->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($nomes)) {
                        $result['mensagem'] .= ' Válido somente para: ' . implode(', ', array_map(function ($n) {
                            return '"' . $n . '"';
                        }, $nomes)) . '.';
                    }
                } catch (PDOException $e) {
                    /* mantém mensagem curta */
                }
                return $result;
            }

            // Calcular desconto
            $valor_desconto = 0.0;
            if ($cupom['tipo'] === 'percentual') {
                $pct = min(100, max(0, (float)$cupom['valor']));
                $valor_desconto = round($valor_total * ($pct / 100), 2);
            } else {
                $valor_desconto = min((float)$cupom['valor'], $valor_total);
            }

            $result['valid'] = true;
            $result['cupom_id'] = (int)$cupom['id'];
            $result['valor_desconto'] = $valor_desconto;
            $result['mensagem'] = 'Cupom aplicado! Desconto de R$ ' . number_format($valor_desconto, 2, ',', '.');
            return $result;
        } catch (PDOException $e) {
            error_log("coupon_helper validarCupom: " . $e->getMessage());
            $result['mensagem'] = 'Erro ao validar cupom.';
            return $result;
        }
    }
}

if (!function_exists('incrementarUsoCupom')) {
    function incrementarUsoCupom($cupom_id) {
        global $pdo;
        if (!$cupom_id || !isset($pdo)) return false;
        try {
            $stmt = $pdo->prepare("UPDATE cupons SET usos_atual = usos_atual + 1 WHERE id = ?");
            $stmt->execute([$cupom_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("coupon_helper incrementarUsoCupom: " . $e->getMessage());
            return false;
        }
    }
}
