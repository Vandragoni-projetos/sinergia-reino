<?php
/**
 * Gateway PayPal - Integração com PayPal Orders API v2
 * Suporta BRL e USD, modo sandbox e validação de webhook
 */

/**
 * Obtém access token OAuth2 do PayPal
 *
 * @param string $client_id
 * @param string $client_secret
 * @param bool $sandbox
 * @return string|null Token ou null em erro
 */
function paypal_get_access_token($client_id, $client_secret, $sandbox = false) {
    $base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    $url = $base . '/v1/oauth2/token';
    $auth = base64_encode($client_id . ':' . $client_secret);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    error_log("PayPal get_access_token HTTP $http_code: " . substr($response, 0, 200));
    return null;
}

/**
 * Cria uma ordem PayPal
 *
 * @param array $params [client_id, client_secret, amount, currency, product_name, return_url, cancel_url, metadata, sandbox]
 * @return array|null ['order_id' => string, 'approval_url' => string] ou null
 */
function create_paypal_order($params) {
    $client_id = trim($params['client_id'] ?? '');
    $client_secret = trim($params['client_secret'] ?? '');
    if (empty($client_id) || empty($client_secret)) {
        return null;
    }

    $sandbox = !empty($params['sandbox']);
    $token = paypal_get_access_token($client_id, $client_secret, $sandbox);
    if (!$token) {
        return null;
    }

    $currency = strtoupper($params['currency'] ?? 'BRL');
    if (!in_array($currency, ['BRL', 'USD', 'EUR'])) {
        $currency = 'BRL';
    }
    $amount = (float)($params['amount'] ?? 0);
    if ($amount <= 0) {
        return null;
    }

    $base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    $url = $base . '/v2/checkout/orders';

    $body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'description' => $params['product_name'] ?? 'Produto Digital',
                'custom_id' => $params['metadata']['checkout_session_uuid'] ?? '',
            ],
        ],
        'application_context' => [
            'return_url' => $params['return_url'] ?? '',
            'cancel_url' => $params['cancel_url'] ?? '',
            'brand_name' => $params['brand_name'] ?? 'Checkout',
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        $order_id = $data['id'] ?? null;
        $approval_url = null;
        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approval_url = $link['href'] ?? null;
                break;
            }
        }
        if ($order_id && $approval_url) {
            return ['order_id' => $order_id, 'approval_url' => $approval_url];
        }
    }
    error_log("PayPal create_order HTTP $http_code: " . substr($response, 0, 300));
    return null;
}

/**
 * Captura um pedido PayPal aprovado
 *
 * @param string $order_id ID da ordem
 * @param string $client_id
 * @param string $client_secret
 * @param bool $sandbox
 * @return array|null ['capture_id' => string, 'status' => string] ou null
 */
function capture_paypal_order($order_id, $client_id, $client_secret, $sandbox = false) {
    $token = paypal_get_access_token($client_id, $client_secret, $sandbox);
    if (!$token) {
        return null;
    }

    $base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    $url = $base . '/v2/checkout/orders/' . urlencode($order_id) . '/capture';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        $status = $data['status'] ?? null;
        $capture_id = null;
        foreach ($data['purchase_units'] ?? [] as $pu) {
            foreach ($pu['payments']['captures'] ?? [] as $cap) {
                $capture_id = $cap['id'] ?? null;
                break;
            }
            if ($capture_id) break;
        }
        return ['capture_id' => $capture_id, 'status' => $status];
    }
    error_log("PayPal capture HTTP $http_code: " . substr($response, 0, 300));
    return null;
}

/**
 * Verifica assinatura do webhook PayPal
 *
 * @param array $headers Headers da requisição (ex: getallheaders())
 * @param string $raw_body Corpo bruto da requisição
 * @param string $webhook_id Webhook ID do PayPal
 * @param string $client_id
 * @param string $client_secret
 * @param bool $sandbox
 * @return bool
 */
function verify_paypal_webhook($headers, $raw_body, $webhook_id, $client_id, $client_secret, $sandbox = false) {
    if (empty($raw_body) || empty($webhook_id)) {
        return false;
    }

    $token = paypal_get_access_token($client_id, $client_secret, $sandbox);
    if (!$token) {
        return false;
    }

    $base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    $url = $base . '/v1/notifications/verify-webhook-signature';

    $transmission_id = $headers['PAYPAL-TRANSMISSION-ID'] ?? $headers['Paypal-Transmission-Id'] ?? '';
    $transmission_time = $headers['PAYPAL-TRANSMISSION-TIME'] ?? $headers['Paypal-Transmission-Time'] ?? '';
    $cert_url = $headers['PAYPAL-CERT-URL'] ?? $headers['Paypal-Cert-Url'] ?? '';
    $auth_algo = $headers['PAYPAL-AUTH-ALGO'] ?? $headers['Paypal-Auth-Algo'] ?? 'SHA256withRSA';
    $transmission_sig = $headers['PAYPAL-TRANSMISSION-SIG'] ?? $headers['Paypal-Transmission-Sig'] ?? '';

    if (empty($transmission_id) || empty($transmission_sig)) {
        return false;
    }

    $body = [
        'auth_algo' => $auth_algo,
        'cert_url' => $cert_url,
        'transmission_id' => $transmission_id,
        'transmission_sig' => $transmission_sig,
        'transmission_time' => $transmission_time,
        'webhook_id' => $webhook_id,
        'webhook_event' => json_decode($raw_body, true),
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        return ($data['verification_status'] ?? '') === 'SUCCESS';
    }
    return false;
}
