<?php
/**
 * Helper de internacionalização para checkout.
 * Uso: t('chave') retorna a tradução ou fallback (pt) ou a própria chave.
 * Idioma: ?lang=es|fr|en|pt na URL do checkout.
 */
if (!function_exists('checkout_t')) {
    function checkout_t($key) {
        global $CHECKOUT_LANG_STRINGS, $CHECKOUT_LANG_FALLBACK;
        if (isset($CHECKOUT_LANG_STRINGS[$key])) return $CHECKOUT_LANG_STRINGS[$key];
        if (isset($CHECKOUT_LANG_FALLBACK[$key])) return $CHECKOUT_LANG_FALLBACK[$key];
        return $key;
    }
}
