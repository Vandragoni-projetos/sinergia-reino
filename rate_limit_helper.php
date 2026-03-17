<?php
/**
 * Rate limiting para APIs (ex: member_api).
 * Usa arquivos em sys_get_temp_dir() para contar requisições por janela de tempo.
 *
 * @param string $identifier Identificador único (ex: email_ip)
 * @param int $limit Máximo de requisições na janela
 * @param int $window_seconds Duração da janela em segundos
 * @return bool true se dentro do limite, false se excedeu
 */
function check_rate_limit_member_api($identifier, $limit = 120, $window_seconds = 60) {
    $key = 'member_api_' . md5($identifier);
    $file = sys_get_temp_dir() . '/rl_' . $key . '.json';
    $now = time();
    $data = ['count' => 0, 'window_start' => $now];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if ($now - $data['window_start'] > $window_seconds) {
            $data = ['count' => 0, 'window_start' => $now];
        }
    }
    $data['count']++;
    @file_put_contents($file, json_encode($data));
    return $data['count'] <= $limit;
}
