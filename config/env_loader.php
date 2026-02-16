<?php
/**
 * Carregador simples de .env (sem dependências)
 * Carrega o arquivo .env na raiz do projeto e disponibiliza env().
 */
if (!function_exists('env')) {
    /**
     * Retorna variável de ambiente (do .env, $_ENV ou getenv).
     * @param string $key Nome da chave
     * @param mixed $default Valor padrão se não existir
     * @return mixed
     */
    function env($key, $default = null) {
        if (isset($_ENV[$key])) {
            $value = $_ENV[$key];
            return $value === '' ? $default : $value;
        }
        $value = getenv($key);
        if ($value !== false) {
            return $value === '' ? $default : $value;
        }
        return $default;
    }
}

$env_file = defined('ROOT_PATH') ? (rtrim(ROOT_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env') : (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env');

if (!file_exists($env_file) || !is_readable($env_file)) {
    return;
}

$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    return;
}

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0) {
        continue;
    }
    if (strpos($line, '=') === false) {
        continue;
    }
    list($name, $value) = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value);
    if ($name === '') {
        continue;
    }
    if (preg_match('/^["\'](.*)["\']\s*$/', $value, $m)) {
        $value = $m[1];
    }
    $_ENV[$name] = $value;
    putenv("$name=$value");
}
