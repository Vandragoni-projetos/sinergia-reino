<?php
/**
 * Evolution API Helper
 * Funções para enviar mensagens via WhatsApp usando Evolution API
 */

/**
 * Envia mensagem via Evolution API
 * 
 * @param string $phone Número do telefone (com código do país, ex: 5511999999999)
 * @param string $message Mensagem a ser enviada
 * @param array $config Configurações da Evolution API (server_url, api_key, instance)
 * @return array ['success' => bool, 'message' => string, 'response' => mixed]
 */
function send_evolution_message($phone, $message, $config) {
    if (empty($config['server_url']) || empty($config['api_key']) || empty($config['instance'])) {
        return ['success' => false, 'message' => 'Configuração da Evolution API incompleta'];
    }

    // Limpa o número de telefone (remove caracteres não numéricos)
    $phone = preg_replace('/\D/', '', $phone);
    
    // Se não começar com código do país, adiciona 55 (Brasil)
    if (strlen($phone) <= 11) {
        $phone = '55' . $phone;
    }

    $url = rtrim($config['server_url'], '/') . '/message/sendText/' . $config['instance'];
    
    $payload = [
        'number' => $phone,
        'text' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $config['api_key']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("Evolution API Error (cURL): " . $curl_error);
        return ['success' => false, 'message' => 'Erro de conexão: ' . $curl_error, 'response' => null];
    }

    $response_data = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        error_log("Evolution API: Mensagem enviada com sucesso para $phone");
        return ['success' => true, 'message' => 'Mensagem enviada', 'response' => $response_data];
    } else {
        $error_msg = $response_data['message'] ?? $response_data['error'] ?? 'Erro desconhecido';
        error_log("Evolution API Error: HTTP $http_code - $error_msg - Response: $response");
        return ['success' => false, 'message' => $error_msg, 'response' => $response_data];
    }
}

/**
 * Processa e envia mensagens da Evolution API para um evento de venda
 * 
 * @param PDO $pdo Conexão com o banco de dados
 * @param array $sale_data Dados da venda
 * @param string $event_type Tipo do evento (approved, pending, rejected, refunded, charged_back)
 * @return void
 */
function process_evolution_messages($pdo, $sale_data, $event_type) {
    // Verifica se tem telefone do comprador
    if (empty($sale_data['comprador_telefone'])) {
        error_log("Evolution API: Telefone do comprador não disponível para venda ID " . ($sale_data['id'] ?? 'N/A'));
        return;
    }

    // Busca o usuario_id do produto
    $stmt_produto = $pdo->prepare("SELECT usuario_id FROM produtos WHERE id = ?");
    $stmt_produto->execute([$sale_data['produto_id']]);
    $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        error_log("Evolution API: Produto não encontrado para venda ID " . ($sale_data['id'] ?? 'N/A'));
        return;
    }

    $usuario_id = $produto['usuario_id'];

    // Busca configurações da Evolution API do usuário
    $stmt_config = $pdo->prepare("SELECT evolution_server_url, evolution_api_key, evolution_instance FROM usuarios WHERE id = ?");
    $stmt_config->execute([$usuario_id]);
    $user_config = $stmt_config->fetch(PDO::FETCH_ASSOC);

    if (empty($user_config['evolution_server_url']) || empty($user_config['evolution_api_key']) || empty($user_config['evolution_instance'])) {
        // Evolution API não configurada para este usuário
        return;
    }

    $config = [
        'server_url' => $user_config['evolution_server_url'],
        'api_key' => $user_config['evolution_api_key'],
        'instance' => $user_config['evolution_instance']
    ];

    // Busca mensagens ativas para este evento
    $stmt_messages = $pdo->prepare("
        SELECT * FROM evolution_messages 
        WHERE usuario_id = :usuario_id 
        AND event_type = :event_type 
        AND is_active = 1 
        AND (produto_id IS NULL OR produto_id = :produto_id)
        ORDER BY produto_id DESC
    ");
    $stmt_messages->execute([
        ':usuario_id' => $usuario_id,
        ':event_type' => $event_type,
        ':produto_id' => $sale_data['produto_id']
    ]);
    $messages = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);

    if (empty($messages)) {
        // Nenhuma mensagem configurada para este evento
        return;
    }

    // Busca nome do produto
    $stmt_produto_nome = $pdo->prepare("SELECT nome FROM produtos WHERE id = ?");
    $stmt_produto_nome->execute([$sale_data['produto_id']]);
    $produto_nome = $stmt_produto_nome->fetchColumn() ?: 'Produto';

    // Link de checkout (recuperação de carrinho)
    $link_checkout = $sale_data['link_checkout'] ?? '';
    if (empty($link_checkout) && !empty($sale_data['checkout_hash'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $link_checkout = $host ? ($protocol . '://' . $host . '/checkout?p=' . $sale_data['checkout_hash']) : '';
    }
    if (empty($link_checkout)) {
        $stmt_ch = $pdo->prepare("SELECT checkout_hash FROM produtos WHERE id = ?");
        $stmt_ch->execute([$sale_data['produto_id']]);
        $ch = $stmt_ch->fetchColumn();
        if ($ch) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $link_checkout = $host ? ($protocol . '://' . $host . '/checkout?p=' . $ch) : '';
        }
    }

    // Prepara as substituições de variáveis
    $replacements = [
        '{cliente_nome}' => $sale_data['comprador_nome'] ?? 'Cliente',
        '{cliente_email}' => $sale_data['comprador_email'] ?? '',
        '{cliente_telefone}' => $sale_data['comprador_telefone'] ?? '',
        '{produto_nome}' => $produto_nome,
        '{valor}' => 'R$ ' . number_format($sale_data['valor'] ?? 0, 2, ',', '.'),
        '{transacao_id}' => $sale_data['transacao_id'] ?? '',
        '{data_compra}' => date('d/m/Y H:i', strtotime($sale_data['data_venda'] ?? 'now')),
        '{link_checkout}' => $link_checkout
    ];

    // Envia cada mensagem configurada
    // Usa apenas a primeira mensagem específica do produto, ou a global se não houver específica
    $message_to_send = null;
    foreach ($messages as $msg) {
        if ($msg['produto_id'] == $sale_data['produto_id']) {
            // Mensagem específica do produto tem prioridade
            $message_to_send = $msg;
            break;
        } elseif ($msg['produto_id'] === null && $message_to_send === null) {
            // Mensagem global (fallback)
            $message_to_send = $msg;
        }
    }

    if ($message_to_send) {
        $message_text = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $message_to_send['message_template']
        );

        $result = send_evolution_message($sale_data['comprador_telefone'], $message_text, $config);
        
        if ($result['success']) {
            error_log("Evolution API: Mensagem '{$message_to_send['name']}' enviada para {$sale_data['comprador_telefone']} (Venda ID: {$sale_data['id']})");
        } else {
            error_log("Evolution API: Falha ao enviar mensagem '{$message_to_send['name']}' para {$sale_data['comprador_telefone']}: {$result['message']}");
        }
    }
}

/**
 * Mapeia status de pagamento para event_type da Evolution
 * 
 * @param string $status Status do pagamento
 * @return string|null Event type ou null se não mapeado
 */
function map_payment_status_to_evolution_event($status) {
    $mapping = [
        'approved' => 'approved',
        'paid' => 'approved',
        'completed' => 'approved',
        'pending' => 'pending',
        'pix_created' => 'pending',
        'in_process' => 'pending',
        'rejected' => 'rejected',
        'cancelled' => 'rejected',
        'refunded' => 'refunded',
        'charged_back' => 'charged_back',
        'chargedback' => 'charged_back'
    ];

    return $mapping[strtolower($status)] ?? null;
}
