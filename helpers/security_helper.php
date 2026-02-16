<?php
/**
 * Security Helper Functions
 * Funções auxiliares para segurança: rate limiting, CSRF, logs de segurança
 */

if (!function_exists('get_client_ip')) {
    /**
     * Obtém o endereço IP real do cliente
     * @return string IP address
     */
    function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

if (!function_exists('check_login_attempts')) {
    /**
     * Verifica se o IP/email pode tentar fazer login (rate limiting)
     * @param string $ip Endereço IP
     * @param string|null $email Email do usuário (opcional)
     * @return array ['allowed' => bool, 'blocked_until' => datetime|null, 'attempts' => int]
     */
    function check_login_attempts($ip, $email = null) {
        global $pdo;
        
        try {
            // Limpa tentativas antigas (mais de 24 horas)
            $pdo->exec("DELETE FROM login_attempts WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            
            // Verifica bloqueio por IP
            $stmt = $pdo->prepare("
                SELECT attempts, blocked_until 
                FROM login_attempts 
                WHERE ip_address = ? 
                ORDER BY last_attempt DESC 
                LIMIT 1
            ");
            $stmt->execute([$ip]);
            $ip_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ip_record) {
                // Verifica se está bloqueado
                if ($ip_record['blocked_until'] && strtotime($ip_record['blocked_until']) > time()) {
                    return [
                        'allowed' => false,
                        'blocked_until' => $ip_record['blocked_until'],
                        'attempts' => $ip_record['attempts'],
                        'reason' => 'IP bloqueado temporariamente'
                    ];
                }
            }
            
            // Se email fornecido, verifica também por email
            if ($email) {
                $stmt = $pdo->prepare("
                    SELECT attempts, blocked_until 
                    FROM login_attempts 
                    WHERE email = ? 
                    ORDER BY last_attempt DESC 
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $email_record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($email_record && $email_record['blocked_until'] && strtotime($email_record['blocked_until']) > time()) {
                    return [
                        'allowed' => false,
                        'blocked_until' => $email_record['blocked_until'],
                        'attempts' => $email_record['attempts'],
                        'reason' => 'Email bloqueado temporariamente'
                    ];
                }
            }
            
            return ['allowed' => true, 'blocked_until' => null, 'attempts' => $ip_record['attempts'] ?? 0];
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar tentativas de login: " . $e->getMessage());
            // Em caso de erro, permite tentativa (fail-open para não bloquear usuários legítimos)
            return ['allowed' => true, 'blocked_until' => null, 'attempts' => 0];
        }
    }
}

if (!function_exists('record_failed_login')) {
    /**
     * Registra uma tentativa de login falha
     * @param string $ip Endereço IP
     * @param string|null $email Email do usuário (opcional)
     * @return void
     */
    function record_failed_login($ip, $email = null) {
        global $pdo;
        
        try {
            // Busca registro existente
            $stmt = $pdo->prepare("
                SELECT id, attempts, blocked_until 
                FROM login_attempts 
                WHERE ip_address = ? " . ($email ? "AND email = ?" : "AND email IS NULL") . "
                ORDER BY last_attempt DESC 
                LIMIT 1
            ");
            
            if ($email) {
                $stmt->execute([$ip, $email]);
            } else {
                $stmt->execute([$ip]);
            }
            
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $new_attempts = ($existing ? $existing['attempts'] : 0) + 1;
            $blocked_until = null;
            
            // Define bloqueio baseado no número de tentativas
            if ($new_attempts >= 10) {
                // 10 tentativas = 1 hora de bloqueio
                $blocked_until = date('Y-m-d H:i:s', strtotime('+1 hour'));
            } elseif ($new_attempts >= 5) {
                // 5 tentativas = 15 minutos de bloqueio
                $blocked_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            }
            
            if ($existing) {
                // Atualiza registro existente
                $stmt = $pdo->prepare("
                    UPDATE login_attempts 
                    SET attempts = ?, last_attempt = NOW(), blocked_until = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_attempts, $blocked_until, $existing['id']]);
            } else {
                // Cria novo registro
                $stmt = $pdo->prepare("
                    INSERT INTO login_attempts (ip_address, email, attempts, last_attempt, blocked_until)
                    VALUES (?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$ip, $email, $new_attempts, $blocked_until]);
            }
            
            // Log de segurança
            log_security_event('failed_login_attempt', [
                'ip' => $ip,
                'email' => $email,
                'attempts' => $new_attempts,
                'blocked_until' => $blocked_until
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar tentativa de login falha: " . $e->getMessage());
        }
    }
}

if (!function_exists('clear_login_attempts')) {
    /**
     * Limpa tentativas de login após login bem-sucedido
     * @param string $ip Endereço IP
     * @param string|null $email Email do usuário (opcional)
     * @return void
     */
    function clear_login_attempts($ip, $email = null) {
        global $pdo;
        
        try {
            if ($email) {
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR email = ?");
                $stmt->execute([$ip, $email]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                $stmt->execute([$ip]);
            }
        } catch (PDOException $e) {
            error_log("Erro ao limpar tentativas de login: " . $e->getMessage());
        }
    }
}

if (!function_exists('is_ip_blocked')) {
    /**
     * Verifica se um IP está bloqueado
     * @param string $ip Endereço IP
     * @return bool
     */
    function is_ip_blocked($ip) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT blocked_until 
                FROM login_attempts 
                WHERE ip_address = ? AND blocked_until IS NOT NULL AND blocked_until > NOW()
                LIMIT 1
            ");
            $stmt->execute([$ip]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erro ao verificar bloqueio de IP: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('generate_csrf_token')) {
    /**
     * Gera um token CSRF único
     * @return string Token CSRF
     */
    function generate_csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verifica se o token CSRF é válido
     * @param string $token Token a verificar
     * @return bool True se válido, False caso contrário
     */
    function verify_csrf_token($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('log_security_event')) {
    /**
     * Registra um evento de segurança
     * @param string $event_type Tipo do evento (ex: 'failed_login', 'unauthorized_access')
     * @param array $details Detalhes do evento
     * @param int|null $user_id ID do usuário (se aplicável)
     * @return void
     */
    function log_security_event($event_type, $details = [], $user_id = null) {
        global $pdo;
        
        try {
            $ip = get_client_ip();
            $details_json = json_encode($details);
            
            $stmt = $pdo->prepare("
                INSERT INTO security_logs (event_type, user_id, ip_address, details, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$event_type, $user_id, $ip, $details_json]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar evento de segurança: " . $e->getMessage());
        }
    }
}

if (!function_exists('check_session_timeout')) {
    /**
     * Verifica e atualiza timeout de sessão
     * @param int $timeout_seconds Tempo limite em segundos (padrão: 7200 = 2 horas)
     * @return bool True se sessão válida, False se expirada
     */
    function check_session_timeout($timeout_seconds = 7200) {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        
        if (isset($_SESSION['last_activity'])) {
            if ((time() - $_SESSION['last_activity']) > $timeout_seconds) {
                // Sessão expirada
                session_destroy();
                return false;
            }
        }
        
        // Atualiza última atividade
        $_SESSION['last_activity'] = time();
        return true;
    }
}

if (!function_exists('is_https')) {
    /**
     * Verifica se a conexão é HTTPS
     * @return bool
     */
    function is_https() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}

