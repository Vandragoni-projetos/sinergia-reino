<?php
/**
 * Log Viewer Helper
 * Funções auxiliares para visualização e gerenciamento de logs
 */

if (!function_exists('get_log_config')) {
    /**
     * Retorna configuração de todos os logs do sistema
     * @return array Configuração dos logs por categoria
     */
    function get_log_config() {
        $base_path = __DIR__ . '/..';
        
        return [
            'errors' => [
                'admin_api_errors.log' => [
                    'name' => 'API Administrativa',
                    'icon' => 'alert-circle',
                    'color' => 'red',
                    'path' => $base_path . '/admin_api_errors.log',
                    'description' => 'Logs da API administrativa'
                ],
                'api_errors.log' => [
                    'name' => 'API Geral',
                    'icon' => 'alert-triangle',
                    'color' => 'orange',
                    'path' => $base_path . '/api_errors.log',
                    'description' => 'Logs gerais das APIs'
                ],
                'vendas_actions_error.log' => [
                    'name' => 'Ações de Vendas (Logs)',
                    'icon' => 'shopping-cart',
                    'color' => 'red',
                    'path' => $base_path . '/vendas_actions_error.log',
                    'description' => 'Logs nas ações de vendas'
                ],
                'notification_api_errors.log' => [
                    'name' => 'API de Notificações (Logs)',
                    'icon' => 'bell',
                    'color' => 'orange',
                    'path' => $base_path . '/api/notification_api_errors.log',
                    'description' => 'Logs na API de notificações'
                ],
                'utmfy_debug.log' => [
                    'name' => 'UTM Debug',
                    'icon' => 'bug',
                    'color' => 'yellow',
                    'path' => $base_path . '/helpers/utmfy_debug.log',
                    'description' => 'Debug do sistema de rastreamento UTM'
                ]
            ],
            'activities' => [
                'check_status_log.txt' => [
                    'name' => 'Verificação de Status',
                    'icon' => 'check-circle',
                    'color' => 'blue',
                    'path' => $base_path . '/check_status_log.txt',
                    'description' => 'Verificações de status de pagamento'
                ],
                'process_payment_log.txt' => [
                    'name' => 'Processamento de Pagamentos',
                    'icon' => 'credit-card',
                    'color' => 'green',
                    'path' => $base_path . '/process_payment_log.txt',
                    'description' => 'Log de processamento de pagamentos'
                ],
                'vendas_actions_log.txt' => [
                    'name' => 'Ações de Vendas',
                    'icon' => 'activity',
                    'color' => 'blue',
                    'path' => $base_path . '/vendas_actions_log.txt',
                    'description' => 'Registro de ações realizadas em vendas'
                ],
                'webhook_log.txt' => [
                    'name' => 'Webhooks Recebidos',
                    'icon' => 'webhook',
                    'color' => 'purple',
                    'path' => $base_path . '/webhook_log.txt',
                    'description' => 'Log de webhooks recebidos'
                ]
            ]
        ];
    }
}

if (!function_exists('get_log_stats')) {
    /**
     * Retorna estatísticas de um arquivo de log
     * @param string $file_path Caminho do arquivo
     * @return array Estatísticas do log
     */
    function get_log_stats($file_path) {
        if (!file_exists($file_path)) {
            return [
                'exists' => false,
                'lines' => 0,
                'size' => 0,
                'size_formatted' => '0 B',
                'last_modified' => null,
                'last_modified_formatted' => 'Nunca'
            ];
        }
        
        $size = filesize($file_path);
        $lines = 0;
        
        // Contar linhas de forma eficiente
        $fp = fopen($file_path, 'r');
        if ($fp) {
            while (!feof($fp)) {
                if (fgets($fp) !== false) {
                    $lines++;
                }
            }
            fclose($fp);
        }
        
        $last_modified = filemtime($file_path);
        
        return [
            'exists' => true,
            'lines' => $lines,
            'size' => $size,
            'size_formatted' => format_bytes($size),
            'last_modified' => $last_modified,
            'last_modified_formatted' => format_time_ago($last_modified)
        ];
    }
}

if (!function_exists('read_log_lines')) {
    /**
     * Lê linhas de um arquivo de log com paginação
     * @param string $file_path Caminho do arquivo
     * @param int $page Página atual
     * @param int $per_page Linhas por página
     * @param string $search Termo de busca (opcional)
     * @param string $date_filter Filtro de data (opcional)
     * @return array Array com linhas e informações de paginação
     */
    function read_log_lines($file_path, $page = 1, $per_page = 50, $search = '', $date_filter = '') {
        if (!file_exists($file_path)) {
            return [
                'lines' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $per_page,
                'total_pages' => 0
            ];
        }
        
        $all_lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($all_lines === false) {
            $all_lines = [];
        }
        
        // Inverter para mostrar mais recentes primeiro
        $all_lines = array_reverse($all_lines);
        
        // Aplicar filtros
        $filtered_lines = [];
        foreach ($all_lines as $index => $line) {
            $parsed = parse_log_line($line);
            
            // Filtro de busca
            if (!empty($search) && stripos($line, $search) === false) {
                continue;
            }
            
            // Filtro de data
            if (!empty($date_filter) && !empty($parsed['timestamp'])) {
                $line_date = date('Y-m-d', strtotime($parsed['timestamp']));
                $today = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $week_ago = date('Y-m-d', strtotime('-7 days'));
                $month_ago = date('Y-m-d', strtotime('-30 days'));
                
                switch ($date_filter) {
                    case 'today':
                        if ($line_date !== $today) continue 2;
                        break;
                    case 'yesterday':
                        if ($line_date !== $yesterday) continue 2;
                        break;
                    case 'week':
                        if ($line_date < $week_ago) continue 2;
                        break;
                    case 'month':
                        if ($line_date < $month_ago) continue 2;
                        break;
                }
            }
            
            $filtered_lines[] = [
                'index' => $index,
                'raw' => $line,
                'parsed' => $parsed
            ];
        }
        
        $total = count($filtered_lines);
        $total_pages = ceil($total / $per_page);
        $page = max(1, min($page, $total_pages ?: 1));
        
        // Paginação
        $offset = ($page - 1) * $per_page;
        $paginated_lines = array_slice($filtered_lines, $offset, $per_page);
        
        return [
            'lines' => $paginated_lines,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages
        ];
    }
}

if (!function_exists('parse_log_line')) {
    /**
     * Parseia uma linha de log e extrai informações
     * @param string $line Linha do log
     * @return array Informações parseadas
     */
    function parse_log_line($line) {
        $parsed = [
            'timestamp' => null,
            'level' => 'INFO',
            'message' => $line,
            'color' => 'gray'
        ];
        
        // Tentar extrair timestamp (formato: YYYY-MM-DD HH:MM:SS)
        if (preg_match('/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $matches)) {
            $parsed['timestamp'] = $matches[1];
            $line = substr($line, strlen($matches[1]));
        }
        
        // Detectar nível de log
        if (preg_match('/\b(ERROR|ERRO|CRITICAL|FATAL)\b/i', $line)) {
            $parsed['level'] = 'ERROR';
            $parsed['color'] = 'red';
        } elseif (preg_match('/\b(WARNING|WARN|AVISO)\b/i', $line)) {
            $parsed['level'] = 'WARNING';
            $parsed['color'] = 'yellow';
        } elseif (preg_match('/\b(SUCCESS|SUCESSO|OK)\b/i', $line)) {
            $parsed['level'] = 'SUCCESS';
            $parsed['color'] = 'green';
        } elseif (preg_match('/\b(DEBUG)\b/i', $line)) {
            $parsed['level'] = 'DEBUG';
            $parsed['color'] = 'gray';
        } elseif (preg_match('/\b(INFO)\b/i', $line)) {
            $parsed['level'] = 'INFO';
            $parsed['color'] = 'blue';
        }
        
        $parsed['message'] = trim($line);
        
        return $parsed;
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Formata bytes em formato legível
     * @param int $bytes Tamanho em bytes
     * @return string Tamanho formatado
     */
    function format_bytes($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}

if (!function_exists('format_time_ago')) {
    /**
     * Formata timestamp em "tempo atrás"
     * @param int $timestamp Unix timestamp
     * @return string Tempo formatado
     */
    function format_time_ago($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'há ' . $diff . ' segundo' . ($diff != 1 ? 's' : '');
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return 'há ' . $mins . ' minuto' . ($mins != 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return 'há ' . $hours . ' hora' . ($hours != 1 ? 's' : '');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return 'há ' . $days . ' dia' . ($days != 1 ? 's' : '');
        } else {
            return date('d/m/Y H:i', $timestamp);
        }
    }
}

if (!function_exists('clear_log_file')) {
    /**
     * Limpa um arquivo de log (com backup)
     * @param string $file_path Caminho do arquivo
     * @return array Resultado da operação
     */
    function clear_log_file($file_path) {
        try {
            if (!file_exists($file_path)) {
                return ['success' => false, 'message' => 'Arquivo não encontrado'];
            }
            
            // Criar backup antes de limpar
            $backup_dir = dirname($file_path) . '/logs_backup';
            if (!file_exists($backup_dir)) {
                if (!mkdir($backup_dir, 0755, true)) {
                    return ['success' => false, 'message' => 'Erro ao criar diretório de backup'];
                }
            }
            
            $backup_file = $backup_dir . '/' . basename($file_path) . '.' . date('Y-m-d_H-i-s') . '.bak';
            
            if (!copy($file_path, $backup_file)) {
                return ['success' => false, 'message' => 'Erro ao criar backup'];
            }
            
            // Limpar arquivo
            if (file_put_contents($file_path, '') === false) {
                return ['success' => false, 'message' => 'Erro ao limpar arquivo'];
            }
            
            return [
                'success' => true,
                'message' => 'Log limpo com sucesso',
                'backup' => $backup_file
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao limpar log: ' . $e->getMessage()
            ];
        }
    }
}

if (!function_exists('get_log_level_icon')) {
    /**
     * Retorna ícone para nível de log
     * @param string $level Nível do log
     * @return string Nome do ícone Lucide
     */
    function get_log_level_icon($level) {
        $icons = [
            'ERROR' => 'x-circle',
            'WARNING' => 'alert-triangle',
            'SUCCESS' => 'check-circle',
            'INFO' => 'info',
            'DEBUG' => 'bug'
        ];
        
        return $icons[$level] ?? 'circle';
    }
}

if (!function_exists('get_log_level_color')) {
    /**
     * Retorna cor para nível de log
     * @param string $level Nível do log
     * @return string Classe de cor Tailwind
     */
    function get_log_level_color($level) {
        $colors = [
            'ERROR' => 'text-red-400',
            'WARNING' => 'text-yellow-400',
            'SUCCESS' => 'text-green-400',
            'INFO' => 'text-blue-400',
            'DEBUG' => 'text-gray-400'
        ];
        
        return $colors[$level] ?? 'text-gray-400';
    }
}
