<?php
/**
 * Web Push Helper
 * Funções para gerenciar notificações push PWA
 */

// Previne múltiplas inclusões - verifica tanto a constante quanto se as funções já existem
if (defined('PWA_WEB_PUSH_HELPER_LOADED') || function_exists('pwa_get_or_generate_vapid_keys')) {
    return;
}
define('PWA_WEB_PUSH_HELPER_LOADED', true);

// Garante que config.php está carregado
if (!isset($pdo) && !isset($GLOBALS['pdo']) && file_exists(__DIR__ . '/../../config/config.php')) {
    require_once __DIR__ . '/../../config/config.php';
}

// Garante que $pdo está disponível globalmente
if (!isset($pdo) && isset($GLOBALS['pdo'])) {
    $pdo = $GLOBALS['pdo'];
}

// Carrega biblioteca web-push
$GLOBALS['web_push_available'] = false;
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        // Verifica se as classes estão disponíveis após carregar autoload
        if (class_exists('Minishlink\WebPush\VAPID')) {
            $GLOBALS['web_push_available'] = true;
        } else {
            error_log("PWA Push: Classe VAPID não encontrada após carregar autoload");
        }
    } catch (\Exception $e) {
        error_log("PWA Push: Erro ao carregar autoload: " . $e->getMessage());
    } catch (\Error $e) {
        error_log("PWA Push: Erro fatal ao carregar autoload: " . $e->getMessage());
    }
} else {
    error_log("PWA Push: vendor/autoload.php não encontrado em " . __DIR__ . '/../../vendor/autoload.php');
}

// Nota: Não usamos 'use' aqui porque a biblioteca pode não estar disponível
// Usaremos o namespace completo (\Minishlink\WebPush\...) em todas as chamadas

/**
 * Normaliza chave base64url/base64 para formato aceito pela biblioteca Web Push
 * @param string $key Chave em base64 ou base64url
 * @return string Chave normalizada
 */
if (!function_exists('pwa_normalize_push_key')) {
function pwa_normalize_push_key($key) {
    $key = trim((string) $key);
    if ($key === '') return '';
    // base64url -> base64 (a biblioteca pode esperar base64 padrão)
    $key = strtr($key, '-_', '+/');
    $pad = strlen($key) % 4;
    if ($pad) $key .= str_repeat('=', 4 - $pad);
    return $key;
}
}

/**
 * Gera ou obtém chaves VAPID
 * @return array|false Array com 'publicKey' e 'privateKey' ou false se erro
 */
if (!function_exists('pwa_get_or_generate_vapid_keys')) {
function pwa_get_or_generate_vapid_keys() {
    // Tenta obter PDO de múltiplas fontes
    $pdo = null;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        global $pdo;
    }
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("PWA Push: PDO não disponível em pwa_get_or_generate_vapid_keys");
        return false;
    }
    
    try {
        // Verifica se a tabela existe
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'pwa_config'");
        if ($stmt_check->rowCount() == 0) {
            error_log("PWA Push: Tabela pwa_config não existe");
            return false;
        }
        
        // Verifica se as colunas VAPID existem
        try {
            $stmt_cols = $pdo->query("SHOW COLUMNS FROM pwa_config LIKE 'vapid_public_key'");
            if ($stmt_cols->rowCount() == 0) {
                error_log("PWA Push: Colunas VAPID não existem. Execute a migration SQL: SQL Atualizações/pwa_push_migration.sql");
                // Tenta adicionar as colunas automaticamente
                try {
                    $pdo->exec("ALTER TABLE pwa_config ADD COLUMN vapid_public_key TEXT NULL AFTER push_enabled");
                    $pdo->exec("ALTER TABLE pwa_config ADD COLUMN vapid_private_key TEXT NULL AFTER vapid_public_key");
                    error_log("PWA Push: Colunas VAPID adicionadas automaticamente");
                } catch (PDOException $e2) {
                    error_log("PWA Push: Não foi possível adicionar colunas automaticamente: " . $e2->getMessage());
                    return false;
                }
            }
        } catch (PDOException $e) {
            error_log("PWA Push: Erro ao verificar colunas: " . $e->getMessage());
            return false;
        }
        
        // Busca configuração existente
        try {
            $stmt = $pdo->query("SELECT vapid_public_key, vapid_private_key FROM pwa_config ORDER BY id DESC LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Se der erro, pode ser que as colunas não existam
            error_log("PWA Push: Erro ao buscar chaves VAPID: " . $e->getMessage());
            $config = null;
        }
        
        // Se já tem chaves, retorna
        if ($config && !empty($config['vapid_public_key']) && !empty($config['vapid_private_key'])) {
            return [
                'publicKey' => $config['vapid_public_key'],
                'privateKey' => $config['vapid_private_key']
            ];
        }
        
        // Gera novas chaves
        if (!class_exists('Minishlink\WebPush\VAPID')) {
            error_log("PWA Push: Classe VAPID não disponível para gerar chaves");
            return false;
        }
        
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        
        // Salva no banco
        try {
            $stmt = $pdo->prepare("UPDATE pwa_config SET vapid_public_key = ?, vapid_private_key = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$keys['publicKey'], $keys['privateKey']]);
            
            // Se não atualizou nenhuma linha, insere nova
            if ($stmt->rowCount() == 0) {
                $stmt = $pdo->prepare("INSERT INTO pwa_config (vapid_public_key, vapid_private_key) VALUES (?, ?)");
                $stmt->execute([$keys['publicKey'], $keys['privateKey']]);
            }
        } catch (PDOException $e) {
            error_log("PWA Push: Erro ao salvar chaves VAPID: " . $e->getMessage());
            return false;
        }
        
        return $keys;
    } catch (Exception $e) {
        error_log("PWA Push: Erro geral em pwa_get_or_generate_vapid_keys: " . $e->getMessage());
        return false;
    }
}
} // Fim do if function_exists para pwa_get_or_generate_vapid_keys

/**
 * Salva uma subscription de push
 * @param int $usuario_id ID do usuário
 * @param string $endpoint Endpoint da subscription
 * @param string $p256dh Chave pública
 * @param string $auth Chave de autenticação
 * @param string|null $user_agent User agent (opcional)
 * @return bool True se sucesso, False se erro
 */
if (!function_exists('pwa_save_subscription')) {
function pwa_save_subscription($usuario_id, $endpoint, $p256dh, $auth, $user_agent = null) {
    // Tenta obter PDO de múltiplas fontes
    $pdo = null;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        global $pdo;
    }
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("PWA Push: PDO não disponível em pwa_save_subscription");
        return false;
    }
    
    try {
        // Verifica se a tabela existe
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'pwa_push_subscriptions'");
        if ($stmt_check->rowCount() == 0) {
            error_log("PWA Push: Tabela pwa_push_subscriptions não existe. Execute a migração SQL.");
            return false;
        }
        
        // Verifica se já existe
        $stmt_check = $pdo->prepare("SELECT id FROM pwa_push_subscriptions WHERE endpoint = ?");
        $stmt_check->execute([$endpoint]);
        $exists = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Atualiza
            $stmt = $pdo->prepare("
                UPDATE pwa_push_subscriptions 
                SET usuario_id = ?, p256dh = ?, auth = ?, user_agent = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE endpoint = ?
            ");
            $stmt->execute([$usuario_id, $p256dh, $auth, $user_agent, $endpoint]);
        } else {
            // Insere nova
            $stmt = $pdo->prepare("
                INSERT INTO pwa_push_subscriptions (usuario_id, endpoint, p256dh, auth, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $endpoint, $p256dh, $auth, $user_agent]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("PWA Push: Erro ao salvar subscription: " . $e->getMessage());
        return false;
    }
}
} // Fim do if function_exists para pwa_save_subscription

/**
 * Remove uma subscription de push
 * @param string $endpoint Endpoint da subscription
 * @return bool True se sucesso, False se erro
 */
if (!function_exists('pwa_remove_subscription')) {
function pwa_remove_subscription($endpoint) {
    // Tenta obter PDO de múltiplas fontes
    $pdo = null;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        global $pdo;
    }
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("PWA Push: PDO não disponível em pwa_remove_subscription");
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM pwa_push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
        return true;
    } catch (PDOException $e) {
        error_log("PWA Push: Erro ao remover subscription: " . $e->getMessage());
        return false;
    }
}
} // Fim do if function_exists para pwa_remove_subscription

/**
 * Obtém todas as subscriptions ativas
 * @param int|null $usuario_id Se fornecido, retorna apenas do usuário
 * @param string|null $user_type Filtro por tipo de usuário ('infoprodutor', 'usuario', 'admin'). Se null, retorna todos os tipos
 * @return array Array de subscriptions
 */
if (!function_exists('pwa_get_subscriptions')) {
function pwa_get_subscriptions($usuario_id = null, $user_type = null) {
    // Tenta obter PDO de múltiplas fontes
    $pdo = null;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        global $pdo;
    }
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("PWA Push: PDO não disponível em pwa_get_subscriptions");
        return [];
    }
    
    try {
        // Se user_type foi especificado, precisa fazer JOIN com tabela usuarios
        $needs_join = ($user_type !== null);
        
        if ($usuario_id) {
            // Filtro por usuario_id específico
            if ($needs_join) {
                // Filtra por usuario_id E tipo de usuário
                $stmt = $pdo->prepare("
                    SELECT ps.* 
                    FROM pwa_push_subscriptions ps
                    INNER JOIN usuarios u ON ps.usuario_id = u.id
                    WHERE ps.usuario_id = ? AND u.tipo = ?
                    ORDER BY ps.created_at DESC
                ");
                $stmt->execute([$usuario_id, $user_type]);
            } else {
                // Apenas filtro por usuario_id
                $stmt = $pdo->prepare("SELECT * FROM pwa_push_subscriptions WHERE usuario_id = ? ORDER BY created_at DESC");
                $stmt->execute([$usuario_id]);
            }
        } else {
            // Sem filtro de usuario_id
            if ($needs_join) {
                // Filtra apenas por tipo de usuário
                $stmt = $pdo->prepare("
                    SELECT ps.* 
                    FROM pwa_push_subscriptions ps
                    INNER JOIN usuarios u ON ps.usuario_id = u.id
                    WHERE u.tipo = ?
                    ORDER BY ps.created_at DESC
                ");
                $stmt->execute([$user_type]);
            } else {
                // Sem filtros - retorna todas as subscriptions
                $stmt = $pdo->query("SELECT * FROM pwa_push_subscriptions ORDER BY created_at DESC");
            }
        }
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log para debug
        if ($needs_join) {
            error_log("PWA Push: pwa_get_subscriptions - usuario_id: " . ($usuario_id ?? 'null') . " | user_type: {$user_type} | Total encontrado: " . count($result));
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("PWA Push: Erro ao buscar subscriptions: " . $e->getMessage());
        return [];
    }
}
} // Fim do if function_exists para pwa_get_subscriptions

/**
 * Conta o número de subscriptions ativas
 * @return int Número de subscriptions
 */
if (!function_exists('pwa_count_subscriptions')) {
function pwa_count_subscriptions() {
    // Tenta obter PDO de múltiplas fontes
    $pdo = null;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        global $pdo;
    }
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("PWA Push: PDO não disponível em pwa_count_subscriptions");
        return 0;
    }
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM pwa_push_subscriptions");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("PWA Push: Erro ao contar subscriptions: " . $e->getMessage());
        return 0;
    }
}
} // Fim do if function_exists para pwa_count_subscriptions

/**
 * Envia notificação push para todas as subscriptions
 * @param string $title Título da notificação
 * @param string $message Mensagem da notificação
 * @param string|null $url URL para abrir ao clicar (opcional)
 * @param string|null $icon URL do ícone (opcional)
 * @param int|null $created_by ID do usuário que criou a notificação
 * @return array Array com 'sent' e 'failed' contendo contadores
 */
if (!function_exists('pwa_send_push_notification')) {
function pwa_send_push_notification($title, $message, $url = null, $icon = null, $created_by = null) {
    // Tenta obter PDO de múltiplas fontes
    $pdo = null;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        global $pdo;
    }
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("PWA Push: PDO não disponível em pwa_send_push_notification");
        return ['sent' => 0, 'failed' => 0, 'total' => 0, 'error' => 'PDO não disponível'];
    }
    
    // Verifica se biblioteca está disponível
    if (empty($GLOBALS['web_push_available']) || !class_exists('Minishlink\WebPush\WebPush')) {
        return ['sent' => 0, 'failed' => 0, 'error' => 'Biblioteca web-push não disponível. Execute: composer require minishlink/web-push'];
    }
    
    // Obtém chaves VAPID
    $vapidKeys = pwa_get_or_generate_vapid_keys();
    if (!$vapidKeys) {
        return ['sent' => 0, 'failed' => 0, 'error' => 'Erro ao obter chaves VAPID'];
    }
    
    // Determina se é notificação global do admin
    $is_admin_global_notification = false;
    if ($created_by !== null) {
        try {
            $stmt_admin = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ? LIMIT 1");
            $stmt_admin->execute([$created_by]);
            $admin_data = $stmt_admin->fetch(PDO::FETCH_ASSOC);
            if ($admin_data && ($admin_data['tipo'] === 'admin')) {
                $is_admin_global_notification = true;
                error_log("PWA Push: Notificação global do admin detectada (created_by: {$created_by})");
            }
        } catch (PDOException $e) {
            error_log("PWA Push: Erro ao verificar se é admin: " . $e->getMessage());
            // Em caso de erro, assume que não é admin para segurança
        }
    }
    
    // Obtém subscriptions
    // Se for notificação global do admin, envia para todos
    // Caso contrário, filtra apenas infoprodutores (para evitar enviar para clientes finais)
    if ($is_admin_global_notification) {
        $subscriptions = pwa_get_subscriptions();
        error_log("PWA Push: Notificação global - buscando TODAS as subscriptions (admin)");
    } else {
        $subscriptions = pwa_get_subscriptions(null, 'infoprodutor');
        error_log("PWA Push: Notificação não-global - filtrando apenas infoprodutores (excluindo clientes finais)");
    }
    
    if (empty($subscriptions)) {
        $error_msg = $is_admin_global_notification 
            ? 'Nenhuma subscription encontrada' 
            : 'Nenhuma subscription de infoprodutor encontrada';
        error_log("PWA Push: {$error_msg}");
        return ['sent' => 0, 'failed' => 0, 'error' => $error_msg];
    }
    
    error_log("PWA Push: Total de subscriptions a processar: " . count($subscriptions) . ($is_admin_global_notification ? " (todas)" : " (apenas infoprodutores)"));
    
    // Detecta protocolo (HTTPS se disponível)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                $_SERVER['SERVER_PORT'] == 443 ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    $protocol = $is_https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Busca ícone do PWA configurado (prioridade sobre o ícone passado como parâmetro)
    $pwa_icon_path = null;
    try {
        if (function_exists('pwa_get_config')) {
            $pwa_config = pwa_get_config();
            $pwa_icon_path = $pwa_config['icon_path'] ?? null;
        } else {
            // Tenta buscar diretamente do banco
            $stmt_icon = $pdo->query("SELECT icon_path FROM pwa_config ORDER BY id DESC LIMIT 1");
            $icon_config = $stmt_icon->fetch(PDO::FETCH_ASSOC);
            if ($icon_config && !empty($icon_config['icon_path'])) {
                $pwa_icon_path = $icon_config['icon_path'];
            }
        }
    } catch (Exception $e) {
        // Ignora erro ao buscar ícone do PWA
    }
    
    // Usa ícone do PWA se disponível, senão usa o ícone passado como parâmetro, senão usa padrão
    $icon_to_use = $pwa_icon_path ?: $icon;
    if (empty($icon_to_use)) {
        // Tenta buscar favicon ou logo do sistema como fallback
        if (function_exists('getSystemSetting')) {
            $favicon_url = getSystemSetting('favicon_url', '');
            $logo_url = getSystemSetting('logo_url', '');
            $icon_to_use = $favicon_url ?: $logo_url ?: '/assets/pix.svg';
        } else {
            $icon_to_use = '/assets/pix.svg';
        }
    }
    
    // Converte ícone para URL absoluta (necessário para mobile, especialmente Android)
    $icon_url = $icon_to_use;
    if (!empty($icon_url) && !preg_match('/^https?:\/\//', $icon_url)) {
        // Remove barra inicial se houver para evitar duplicação
        $icon_path_clean = ltrim($icon_url, '/');
        $icon_url = $protocol . $host . '/' . $icon_path_clean;
    }
    
    // Garante que o ícone seja acessível (Android precisa de URL válida)
    // Android funciona melhor com PNG do que SVG
    // Se o ícone for SVG, tenta encontrar uma versão PNG
    $final_icon_url = $icon_url;
    if (strpos($icon_url, '.svg') !== false) {
        // Tenta encontrar versão PNG do mesmo arquivo
        $png_version = str_replace('.svg', '.png', $icon_url);
        // Verifica se o arquivo PNG existe no servidor
        $png_path_relative = str_replace($protocol . $host . '/', '', $png_version);
        $png_path_absolute = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($png_path_relative, '/');
        if (file_exists($png_path_absolute)) {
            $final_icon_url = $png_version;
            error_log("PWA Push: Usando versão PNG do ícone: " . $final_icon_url);
        } else {
            error_log("PWA Push: AVISO - Ícone SVG detectado. Android pode não exibir corretamente. Considere usar PNG (192x192 ou 512x512).");
        }
    }
    
    // Badge (ícone pequeno que aparece na notificação)
    $badge_url = $final_icon_url;
    
    // Busca nome do app para incluir na notificação
    $app_name = null;
    try {
        if (function_exists('pwa_get_config')) {
            $pwa_config = pwa_get_config();
            $app_name = $pwa_config['app_name'] ?? $pwa_config['short_name'] ?? null;
        } else {
            // Tenta buscar diretamente do banco
            $stmt_app = $pdo->query("SELECT app_name, short_name FROM pwa_config ORDER BY id DESC LIMIT 1");
            $app_config = $stmt_app->fetch(PDO::FETCH_ASSOC);
            if ($app_config) {
                $app_name = $app_config['app_name'] ?? $app_config['short_name'] ?? null;
            }
        }
    } catch (Exception $e) {
        // Ignora erro ao buscar nome do app
    }
    
    // Prepara título da notificação incluindo nome do app para evitar "from [app]"
    $notification_title = $title;
    if ($app_name && strpos($title, $app_name) === false) {
        $notification_title = "{$app_name} - {$title}";
    }
    
    // Prepara payload da notificação
    // Formato compatível com mobile (iOS/Android) e desktop
    $payload = json_encode([
        'title' => $notification_title,
        'body' => $message,
        'message' => $message, // Duplicado para compatibilidade
        'icon' => $final_icon_url, // Usa ícone final (pode ser PNG se disponível)
        'badge' => $badge_url,
        'url' => $url ?: '/',
        'tag' => 'pwa-notification-' . time(), // Tag única para evitar duplicatas
        'data' => [
            'url' => $url ?: '/',
            'timestamp' => time()
        ],
        // Campos adicionais para mobile
        'sound' => 'default', // Som padrão (iOS/Android)
        'vibrate' => [200, 100, 200], // Padrão de vibração (Android)
        'requireInteraction' => false,
        'timestamp' => time() * 1000, // Timestamp em milissegundos
        // Adiciona nome do app nos dados para referência (não será exibido como "from")
        'app_name' => $app_name
    ]);
    
    error_log("PWA Push: Payload preparado: " . substr($payload, 0, 200) . "...");
    
    // Configura WebPush com subject correto (deve ser URL válida para Apple)
    // Apple requer que o subject seja uma URL (mailto: ou https://)
    $vapid_subject = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^(https?:\/\/|mailto:)/', $vapid_subject)) {
        // Se não começa com http://, https:// ou mailto:, adiciona https://
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    $_SERVER['SERVER_PORT'] == 443 ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
        $protocol = $is_https ? 'https://' : 'http://';
        $vapid_subject = $protocol . $vapid_subject;
    }
    
    // Configura WebPush
    $auth = [
        'VAPID' => [
            'subject' => $vapid_subject,
            'publicKey' => $vapidKeys['publicKey'],
            'privateKey' => $vapidKeys['privateKey'],
        ],
    ];
    
    error_log("PWA Push: VAPID subject configurado: " . $vapid_subject);
    
    try {
        $webPush = new \Minishlink\WebPush\WebPush($auth);
    } catch (Exception $e) {
        error_log("PWA Push: Erro ao criar WebPush: " . $e->getMessage());
        return ['sent' => 0, 'failed' => 0, 'error' => 'Erro ao inicializar WebPush: ' . $e->getMessage()];
    }
    
    $sent = 0;
    $failed = 0;
    $invalid_subscriptions = [];
    
    // Envia para cada subscription
    foreach ($subscriptions as $index => $sub_data) {
        try {
            $endpoint = $sub_data['endpoint'];
            $user_agent = $sub_data['user_agent'] ?? 'N/A';
            $sub_usuario_id = $sub_data['usuario_id'] ?? 'N/A';
            
            // Busca tipo de usuário para log detalhado
            $sub_user_type = 'N/A';
            if ($sub_usuario_id !== 'N/A' && is_numeric($sub_usuario_id)) {
                try {
                    $stmt_sub_user = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ? LIMIT 1");
                    $stmt_sub_user->execute([$sub_usuario_id]);
                    $sub_user_data = $stmt_sub_user->fetch(PDO::FETCH_ASSOC);
                    if ($sub_user_data) {
                        $sub_user_type = $sub_user_data['tipo'] ?? 'N/A';
                    }
                } catch (PDOException $e) {
                    // Ignora erro ao buscar tipo
                }
            }
            
            // Detecta tipo de endpoint para logs
            $endpoint_type = 'Unknown';
            if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
                $endpoint_type = 'FCM (Android)';
            } elseif (strpos($endpoint, 'web.push.apple.com') !== false) {
                $endpoint_type = 'APNs (iOS)';
            } elseif (strpos($endpoint, 'updates.push.services.mozilla.com') !== false) {
                $endpoint_type = 'Mozilla';
            }
            
            error_log("PWA Push: Processando subscription " . ($index + 1) . " de " . count($subscriptions) . " | usuario_id: {$sub_usuario_id} | tipo: {$sub_user_type}");
            error_log("PWA Push: Tipo endpoint: " . $endpoint_type . " | Endpoint: " . substr($endpoint, 0, 50) . "...");
            error_log("PWA Push: User Agent: " . $user_agent);
            
            $p256dh = pwa_normalize_push_key($sub_data['p256dh'] ?? '');
            $auth = pwa_normalize_push_key($sub_data['auth'] ?? '');
            if ($p256dh === '' || $auth === '') {
                error_log("PWA Push: Chaves p256dh/auth vazias ou inválidas para subscription " . ($index + 1));
                $failed++;
                continue;
            }
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => $p256dh,
                    'auth' => $auth
                ]
            ]);
            
            $webPush->queueNotification($subscription, $payload);
            error_log("PWA Push: Subscription " . ($index + 1) . " (" . $endpoint_type . ") adicionada à fila com sucesso");
        } catch (Exception $e) {
            error_log("PWA Push: Erro ao criar subscription " . ($index + 1) . ": " . $e->getMessage());
            error_log("PWA Push: Stack trace: " . $e->getTraceAsString());
            $failed++;
            $invalid_subscriptions[] = $sub_data['id'] ?? null;
        } catch (Error $e) {
            error_log("PWA Push: Erro fatal ao criar subscription " . ($index + 1) . ": " . $e->getMessage());
            error_log("PWA Push: Stack trace: " . $e->getTraceAsString());
            $failed++;
            $invalid_subscriptions[] = $sub_data['id'] ?? null;
        }
    }
    
    error_log("PWA Push: Iniciando envio de " . count($subscriptions) . " notificações...");
    
    // Processa todas as notificações
    $reportIndex = 0;
    foreach ($webPush->flush() as $report) {
        $reportIndex++;
        $failed_endpoint = $report->getEndpoint();
        
        // Detecta tipo de endpoint que falhou
        $failed_type = 'Unknown';
        if (strpos($failed_endpoint, 'fcm.googleapis.com') !== false) {
            $failed_type = 'FCM (Android)';
        } elseif (strpos($failed_endpoint, 'web.push.apple.com') !== false) {
            $failed_type = 'APNs (iOS)';
        } elseif (strpos($failed_endpoint, 'updates.push.services.mozilla.com') !== false) {
            $failed_type = 'Mozilla';
        }
        
        if ($report->isSuccess()) {
            $sent++;
            // Log detalhado do sucesso
            $success_endpoint = $report->getEndpoint();
            $success_usuario_id = 'N/A';
            foreach ($subscriptions as $sub) {
                if ($sub['endpoint'] === $success_endpoint) {
                    $success_usuario_id = $sub['usuario_id'] ?? 'N/A';
                    break;
                }
            }
            error_log("PWA Push: Notificação " . $reportIndex . " (" . $failed_type . ") enviada com sucesso | usuario_id: {$success_usuario_id}");
        } else {
            $failed++;
            $errorMsg = $report->getReason() ?? 'Erro desconhecido';
            $httpCode = method_exists($report, 'getStatusCode') ? $report->getStatusCode() : 'N/A';
            
            error_log("PWA Push: Notificação " . $reportIndex . " (" . $failed_type . ") falhou");
            error_log("PWA Push: Erro: " . $errorMsg);
            error_log("PWA Push: HTTP Code: " . $httpCode);
            error_log("PWA Push: Endpoint que falhou: " . substr($failed_endpoint, 0, 80) . "...");
            
            // Tratamento específico para Apple Push (BadJwtToken)
            if (strpos($failed_endpoint, 'web.push.apple.com') !== false) {
                if (strpos($errorMsg, 'BadJwtToken') !== false || strpos($errorMsg, '403') !== false) {
                    error_log("PWA Push: ERRO ESPECÍFICO iOS - BadJwtToken detectado!");
                    error_log("PWA Push: Verifique se o VAPID subject está no formato correto (https:// ou mailto:)");
                    error_log("PWA Push: VAPID subject atual: " . $vapid_subject);
                }
            }
            
            // Tratamento específico para FCM
            if (strpos($failed_endpoint, 'fcm.googleapis.com') !== false) {
                if (strpos($errorMsg, 'InvalidRegistration') !== false || strpos($errorMsg, 'NotRegistered') !== false) {
                    error_log("PWA Push: ERRO ESPECÍFICO Android - Subscription inválida ou não registrada");
                }
            }
            
            // Se subscription é inválida (410 Gone), remove do banco
            if ($report->isSubscriptionExpired()) {
                if ($failed_endpoint) {
                    error_log("PWA Push: Removendo subscription expirada (" . $failed_type . "): " . substr($failed_endpoint, 0, 50) . "...");
                    pwa_remove_subscription($failed_endpoint);
                }
            }
        }
    }
    
    error_log("PWA Push: Resultado final - Enviadas: $sent, Falhadas: $failed, Total: " . count($subscriptions));
    
    // Salva histórico da notificação
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pwa_push_notifications (title, message, url, icon, sent_count, failed_count, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $message, $url, $icon, $sent, $failed, $created_by]);
    } catch (PDOException $e) {
        error_log("PWA Push: Erro ao salvar histórico: " . $e->getMessage());
    }
    
    $result = [
        'sent' => $sent,
        'failed' => $failed,
        'total' => count($subscriptions)
    ];
    
    error_log("PWA Push: Retornando resultado: " . json_encode($result));
    
    return $result;
}
} // Fim do if function_exists para pwa_send_push_notification

/**
 * Envia notificação push para um vendedor específico
 * @param int $usuario_id ID do vendedor
 * @param string $title Título da notificação
 * @param string $message Mensagem da notificação
 * @param string|null $url URL para abrir ao clicar (opcional)
 * @param string|null $icon URL do ícone (opcional)
 * @return array Array com 'sent' e 'failed' contendo contadores
 */
if (!function_exists('pwa_send_push_to_vendor')) {
function pwa_send_push_to_vendor($usuario_id, $title, $message, $url = null, $icon = null) {
    // Tenta obter PDO de múltiplas fontes
    $pdo = null;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        global $pdo;
    }
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("PWA Push: PDO não disponível em pwa_send_push_to_vendor");
        return ['sent' => 0, 'failed' => 0, 'total' => 0, 'error' => 'PDO não disponível'];
    }
    
    // Verifica se biblioteca está disponível
    if (empty($GLOBALS['web_push_available']) || !class_exists('Minishlink\WebPush\WebPush')) {
        return ['sent' => 0, 'failed' => 0, 'error' => 'Biblioteca web-push não disponível'];
    }
    
    // Obtém chaves VAPID
    $vapidKeys = pwa_get_or_generate_vapid_keys();
    if (!$vapidKeys) {
        return ['sent' => 0, 'failed' => 0, 'error' => 'Erro ao obter chaves VAPID'];
    }
    
    // Valida que o usuario_id é um infoprodutor (não cliente final)
    try {
        $stmt_user = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ? LIMIT 1");
        $stmt_user->execute([$usuario_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_data) {
            error_log("PWA Push (Vendor): ERRO - Usuario_id {$usuario_id} não encontrado na tabela usuarios");
            return ['sent' => 0, 'failed' => 0, 'total' => 0, 'error' => 'Usuário não encontrado'];
        }
        
        $user_type = $user_data['tipo'] ?? '';
        
        // Apenas infoprodutores e admins podem receber notificações de vendas
        // Clientes finais (tipo = 'usuario') NÃO devem receber
        if ($user_type === 'usuario') {
            error_log("PWA Push (Vendor): ERRO - Tentativa de enviar notificação de venda para cliente final (usuario_id: {$usuario_id}, tipo: {$user_type})");
            return ['sent' => 0, 'failed' => 0, 'total' => 0, 'error' => 'Notificações de vendas são apenas para infoprodutores'];
        }
        
        error_log("PWA Push (Vendor): Validado usuario_id: {$usuario_id} | Tipo: {$user_type}");
    } catch (PDOException $e) {
        error_log("PWA Push (Vendor): Erro ao validar tipo de usuário: " . $e->getMessage());
        // Continua mesmo com erro na validação, mas loga o erro
    }
    
    // Obtém subscriptions apenas do vendedor (filtra por infoprodutor para garantir)
    $subscriptions = pwa_get_subscriptions($usuario_id, 'infoprodutor');
    error_log("PWA Push (Vendor): Buscando subscriptions para usuario_id: {$usuario_id} (tipo: infoprodutor) | Total encontrado: " . count($subscriptions));
    
    if (empty($subscriptions)) {
        error_log("PWA Push (Vendor): Nenhuma subscription encontrada para usuario_id: {$usuario_id}");
        return ['sent' => 0, 'failed' => 0, 'total' => 0, 'error' => 'Nenhuma subscription encontrada para este vendedor'];
    }
    
    error_log("PWA Push (Vendor): Encontradas " . count($subscriptions) . " subscriptions para usuario_id: {$usuario_id} (infoprodutor)");
    
    // Detecta protocolo (HTTPS se disponível)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                $_SERVER['SERVER_PORT'] == 443 ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    $protocol = $is_https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Busca ícone do PWA configurado
    $pwa_icon_path = null;
    try {
        if (function_exists('pwa_get_config')) {
            $pwa_config = pwa_get_config();
            $pwa_icon_path = $pwa_config['icon_path'] ?? null;
        } else {
            $stmt_icon = $pdo->query("SELECT icon_path FROM pwa_config ORDER BY id DESC LIMIT 1");
            $icon_config = $stmt_icon->fetch(PDO::FETCH_ASSOC);
            if ($icon_config && !empty($icon_config['icon_path'])) {
                $pwa_icon_path = $icon_config['icon_path'];
            }
        }
    } catch (Exception $e) {
        // Ignora erro
    }
    
    // Usa ícone do PWA se disponível, senão usa o ícone passado como parâmetro, senão usa padrão
    $icon_to_use = $pwa_icon_path ?: $icon;
    if (empty($icon_to_use)) {
        if (function_exists('getSystemSetting')) {
            $favicon_url = getSystemSetting('favicon_url', '');
            $logo_url = getSystemSetting('logo_url', '');
            $icon_to_use = $favicon_url ?: $logo_url ?: '/assets/pix.svg';
        } else {
            $icon_to_use = '/assets/pix.svg';
        }
    }
    
    // Converte ícone para URL absoluta
    $icon_url = $icon_to_use;
    if (!empty($icon_url) && !preg_match('/^https?:\/\//', $icon_url)) {
        $icon_path_clean = ltrim($icon_url, '/');
        $icon_url = $protocol . $host . '/' . $icon_path_clean;
    }
    
    $final_icon_url = $icon_url;
    if (strpos($icon_url, '.svg') !== false) {
        $png_version = str_replace('.svg', '.png', $icon_url);
        $png_path_relative = str_replace($protocol . $host . '/', '', $png_version);
        $png_path_absolute = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($png_path_relative, '/');
        if (file_exists($png_path_absolute)) {
            $final_icon_url = $png_version;
        }
    }
    
    $badge_url = $final_icon_url;
    
    // Busca nome do app
    $app_name = null;
    try {
        if (function_exists('pwa_get_config')) {
            $pwa_config = pwa_get_config();
            $app_name = $pwa_config['app_name'] ?? $pwa_config['short_name'] ?? null;
        } else {
            $stmt_app = $pdo->query("SELECT app_name, short_name FROM pwa_config ORDER BY id DESC LIMIT 1");
            $app_config = $stmt_app->fetch(PDO::FETCH_ASSOC);
            if ($app_config) {
                $app_name = $app_config['app_name'] ?? $app_config['short_name'] ?? null;
            }
        }
    } catch (Exception $e) {
        // Ignora erro
    }
    
    // Prepara título incluindo nome do app para evitar "from [app]"
    $notification_title = $title;
    if ($app_name && strpos($title, $app_name) === false) {
        $notification_title = "{$app_name} - {$title}";
    }
    
    // Prepara payload da notificação
    $payload = json_encode([
        'title' => $notification_title,
        'body' => $message,
        'message' => $message,
        'icon' => $final_icon_url,
        'badge' => $badge_url,
        'url' => $url ?: '/',
        'tag' => 'pedido-notification-' . time(),
        'data' => [
            'url' => $url ?: '/',
            'timestamp' => time()
        ],
        'sound' => 'default',
        'vibrate' => [200, 100, 200],
        'requireInteraction' => false,
        'timestamp' => time() * 1000,
        'app_name' => $app_name
    ]);
    
    error_log("PWA Push (Vendor): Payload preparado - Título: {$title} | Mensagem: {$message} | URL: {$url}");
    
    // Configura VAPID subject
    $vapid_subject = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^(https?:\/\/|mailto:)/', $vapid_subject)) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    $_SERVER['SERVER_PORT'] == 443 ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
        $protocol = $is_https ? 'https://' : 'http://';
        $vapid_subject = $protocol . $vapid_subject;
    }
    
    // Configura WebPush
    $auth = [
        'VAPID' => [
            'subject' => $vapid_subject,
            'publicKey' => $vapidKeys['publicKey'],
            'privateKey' => $vapidKeys['privateKey'],
        ],
    ];
    
    try {
        $webPush = new \Minishlink\WebPush\WebPush($auth);
    } catch (Exception $e) {
        error_log("PWA Push: Erro ao criar WebPush em pwa_send_push_to_vendor: " . $e->getMessage());
        return ['sent' => 0, 'failed' => 0, 'error' => 'Erro ao inicializar WebPush'];
    }
    
    $sent = 0;
    $failed = 0;
    
    // Envia para cada subscription do vendedor
    foreach ($subscriptions as $index => $sub_data) {
        try {
            $endpoint = $sub_data['endpoint'];
            $sub_usuario_id = $sub_data['usuario_id'] ?? 'N/A';
            
            error_log("PWA Push (Vendor): Enviando para subscription " . ($index + 1) . " de " . count($subscriptions) . " | usuario_id: {$sub_usuario_id} | endpoint: " . substr($endpoint, 0, 50) . "...");
            
            $p256dh = pwa_normalize_push_key($sub_data['p256dh'] ?? '');
            $auth = pwa_normalize_push_key($sub_data['auth'] ?? '');
            if ($p256dh === '' || $auth === '') {
                error_log("PWA Push (Vendor): Chaves inválidas para subscription " . ($index + 1));
                $failed++;
                continue;
            }
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => $p256dh,
                    'auth' => $auth
                ]
            ]);
            
            $webPush->queueNotification($subscription, $payload);
            error_log("PWA Push (Vendor): Subscription " . ($index + 1) . " adicionada à fila com sucesso");
        } catch (Exception $e) {
            error_log("PWA Push: Erro ao criar subscription em pwa_send_push_to_vendor: " . $e->getMessage());
            $failed++;
        } catch (Error $e) {
            error_log("PWA Push: Erro fatal ao criar subscription em pwa_send_push_to_vendor: " . $e->getMessage());
            $failed++;
        }
    }
    
    // Processa todas as notificações
    error_log("PWA Push (Vendor): Iniciando envio de " . count($subscriptions) . " notificações para usuario_id: {$usuario_id}");
    
    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            $sent++;
            error_log("PWA Push (Vendor): Notificação enviada com sucesso para usuario_id: {$usuario_id}");
        } else {
            $failed++;
            $error_reason = $report->getReason() ?? 'Erro desconhecido';
            error_log("PWA Push (Vendor): Falha ao enviar notificação para usuario_id: {$usuario_id} | Erro: {$error_reason}");
            
            // Se subscription é inválida, remove do banco
            if ($report->isSubscriptionExpired()) {
                $failed_endpoint = $report->getEndpoint();
                if ($failed_endpoint) {
                    error_log("PWA Push (Vendor): Removendo subscription expirada para usuario_id: {$usuario_id}");
                    pwa_remove_subscription($failed_endpoint);
                }
            }
        }
    }
    
    $result = [
        'sent' => $sent,
        'failed' => $failed,
        'total' => count($subscriptions)
    ];
    
    error_log("PWA Push (Vendor): Resultado final para usuario_id: {$usuario_id} - " . json_encode($result));
    
    return $result;
}
} // Fim do if function_exists para pwa_send_push_to_vendor

/**
 * Registra uma subscription de push (wrapper para pwa_save_subscription)
 * Esta função atualiza automaticamente o usuario_id se o endpoint já existir
 * @param int $usuario_id ID do usuário
 * @param array $subscription Dados da subscription (endpoint, keys.p256dh, keys.auth)
 * @return bool True se sucesso, False se erro
 */
if (!function_exists('pwa_register_subscription')) {
function pwa_register_subscription($usuario_id, $subscription) {
    if (empty($subscription['endpoint']) || empty($subscription['keys']['p256dh']) || empty($subscription['keys']['auth'])) {
        error_log("PWA Push: Dados de subscription incompletos");
        return false;
    }
    
    // As chaves já vêm em base64 do JavaScript
    $p256dh = $subscription['keys']['p256dh'];
    $auth = $subscription['keys']['auth'];
    
    // Obtém user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    error_log("PWA Push: Registrando subscription para usuario_id: {$usuario_id} | Endpoint: " . substr($subscription['endpoint'], 0, 50) . "...");
    
    // A função pwa_save_subscription já atualiza o usuario_id se o endpoint existir
    $result = pwa_save_subscription($usuario_id, $subscription['endpoint'], $p256dh, $auth, $user_agent);
    
    if ($result) {
        error_log("PWA Push: Subscription registrada/atualizada com sucesso para usuario_id: {$usuario_id}");
    } else {
        error_log("PWA Push: Falha ao registrar subscription para usuario_id: {$usuario_id}");
    }
    
    return $result;
}
} // Fim do if function_exists para pwa_register_subscription
?>
