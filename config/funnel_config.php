<?php
/**
 * Configurações do funil de vendas (Upsell/Downsell).
 * Flags via env; defaults seguros para produção (tudo desligado).
 */
if (!defined('FUNNEL_PREVENT_DUPLICATE')) {
    define('FUNNEL_PREVENT_DUPLICATE', filter_var(
        function_exists('env') ? env('FUNNEL_PREVENT_DUPLICATE', 'false') : 'false',
        FILTER_VALIDATE_BOOLEAN
    ));
}
if (!defined('FUNNEL_DEV_MODE')) {
    $devMode = filter_var(
        function_exists('env') ? env('FUNNEL_DEV_MODE', 'false') : 'false',
        FILTER_VALIDATE_BOOLEAN
    );
    define('FUNNEL_DEV_MODE', $devMode);
}
if (!defined('FUNNEL_DEV_TOKEN')) {
    define('FUNNEL_DEV_TOKEN', function_exists('env') ? (string) env('FUNNEL_DEV_TOKEN', '') : '');
}
