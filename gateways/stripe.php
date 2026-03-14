<?php
/**
 * Gateway Stripe - Integração com Stripe Checkout
 * Suporta BRL e USD, modo de teste e validação de webhook
 */

if (!class_exists('\Stripe\Stripe')) {
    $autoload_paths = [
        __DIR__ . '/../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
    ];
    $loaded = false;
    foreach ($autoload_paths as $path) {
        if (is_file($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    if (!$loaded && !class_exists('\Stripe\Stripe')) {
        error_log('Stripe: vendor/autoload.php não encontrado. Execute composer install.');
        throw new \RuntimeException('Stripe SDK não disponível. Execute composer install no servidor.');
    }
}

/**
 * Cria uma sessão de checkout do Stripe
 *
 * @param array $params Parâmetros:
 *   - secret_key: string
 *   - amount: float (valor em unidade, ex: 29.99)
 *   - currency: string (brl ou usd)
 *   - product_name: string
 *   - success_url: string
 *   - cancel_url: string
 *   - customer_email: string
 *   - metadata: array (produto_id, email_cliente, checkout_session_uuid)
 *   - test_mode: bool (opcional)
 * @return array ['checkout_url' => string, 'session_id' => string, 'payment_intent_id' => string|null] ou null em erro
 */
function create_stripe_checkout_session($params) {
    $secret_key = trim($params['secret_key'] ?? '');
    if (empty($secret_key)) {
        return null;
    }

    try {
        \Stripe\Stripe::setApiKey($secret_key);
        $test_mode = !empty($params['test_mode']) && $params['test_mode'];
        if ($test_mode) {
            \Stripe\Stripe::setApiKey($secret_key); // Chave de teste já começa com sk_test_
        }

        $currency = strtolower($params['currency'] ?? 'brl');
        if (!in_array($currency, ['brl', 'usd', 'eur'])) {
            $currency = 'brl';
        }

        // Stripe usa valores em centavos (USD: cents, BRL: centavos)
        $amount_unit = (float)($params['amount'] ?? 0);
        $amount_cents = (int)round($amount_unit * 100, 0);
        if ($amount_cents <= 0) {
            return null;
        }

        $line_items = [
            [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => $params['product_name'] ?? 'Produto Digital',
                        'description' => 'Compra: ' . ($params['product_name'] ?? 'Produto'),
                    ],
                    'unit_amount' => $amount_cents,
                ],
                'quantity' => 1,
            ],
        ];

        $session_params = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'success_url' => $params['success_url'] ?? '',
            'cancel_url' => $params['cancel_url'] ?? '',
            'customer_email' => $params['customer_email'] ?? null,
            'metadata' => $params['metadata'] ?? [],
        ];

        if (!empty($params['customer_email'])) {
            $session_params['customer_email'] = $params['customer_email'];
        }

        $session = \Stripe\Checkout\Session::create($session_params);

        $payment_intent_id = null;
        if ($session->payment_intent) {
            $payment_intent_id = is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent->id;
        }

        return [
            'checkout_url' => $session->url,
            'session_id' => $session->id,
            'payment_intent_id' => $payment_intent_id,
        ];
    } catch (\Exception $e) {
        error_log("Stripe create_stripe_checkout_session error: " . $e->getMessage());
        return null;
    }
}

/**
 * Verifica a assinatura do webhook do Stripe
 *
 * @param string $payload Raw body da requisição
 * @param string $signature_header Valor do header Stripe-Signature
 * @param string $webhook_secret Webhook signing secret (whsec_...)
 * @return string|null ID do evento se válido, null se inválido
 */
function verify_stripe_webhook($payload, $signature_header, $webhook_secret) {
    if (empty($payload) || empty($signature_header) || empty($webhook_secret)) {
        return null;
    }

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $signature_header, $webhook_secret);
        return $event->id ?? null;
    } catch (\UnexpectedValueException $e) {
        error_log("Stripe webhook verify: Invalid payload - " . $e->getMessage());
        return null;
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        error_log("Stripe webhook verify: Invalid signature - " . $e->getMessage());
        return null;
    } catch (\Exception $e) {
        error_log("Stripe webhook verify error: " . $e->getMessage());
        return null;
    }
}

/**
 * Extrai payment_intent id de um evento Stripe
 *
 * @param object $event Objeto Stripe Event
 * @return string|null payment_intent id ou session id
 */
function stripe_extract_payment_id($event) {
    if (!$event || !isset($event->type)) {
        return null;
    }

    $type = $event->type;
    $obj = $event->data->object ?? null;
    if (!$obj) {
        return null;
    }

    if ($type === 'checkout.session.completed') {
        // Usamos session.id (cs_xxx) pois é o que armazenamos em transacao_id ao criar a venda
        return $obj->id ?? null;
    }

    if ($type === 'payment_intent.succeeded') {
        return $obj->id ?? null;
    }

    return null;
}
