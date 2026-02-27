# Plano de Implementação: Checkout BR + Internacional

## 1. Decisões e definição de pronto

### 1.1 Roteamento de gateway

| País | Gateway principal | Fallback |
|------|-------------------|----------|
| **BR** | Efí (Pix + Cartão) | — |
| **Internacional** (country != BR) | Stripe (cartão) | PayPal |

### 1.2 Moeda

| Região | Moeda | Estratégia |
|--------|-------|------------|
| BR | BRL | `preco` (campo existente) |
| Internacional | USD | `price_usd` ou conversão: `preco * taxa_usd` quando `price_usd` NULL |

**Conversão**: Produtos ganham `price_usd` e `price_eur` opcionais. Se ausentes, usar taxa fixa configurável (ex.: `usd_rate` em configuracoes_sistema). Valor enviado ao Stripe em centavos (USD: amount * 100).

### 1.3 3DS / requires_action

| Gateway | Tratamento |
|---------|------------|
| **Mercado Pago** | Se `status=in_process` e houver `transaction_details.external_resource_url`, retornar `redirect_url_3ds`; frontend redireciona |
| **Stripe** | Backend cria PaymentIntent (não confirma); retorna `client_secret`; frontend usa `stripe.confirmCardPayment(clientSecret)` — Stripe JS cuida do 3DS |
| **Efí** | Sem fluxo 3DS explícito; manter como está |

### 1.4 Billing

- **Mínimo**: `country`, `zip` (obrigatórios para internacional; opcionais para BR com default `BR`).
- **Opcional fase 2**: `address`, `state` para Stripe `billing_details`.

### 1.5 Logs

- Formato: JSON Lines em `payment_decline_log.txt`.
- Campos: `ts`, `gateway`, `payment_id`, `status`, `status_detail`, `decline_code`, `country`, `ip`, `product_id`.
- Sem dados sensíveis (PAN, CVV, etc.).

---

## 2. Arquivos alterados por commit

| Commit | Arquivos |
|--------|----------|
| **1** | `migrations/checkout_internacional.sql`, `config/config.php` (leitura de flags) |
| **2** | `checkout.php` (country, zip, validateForm), `process_payment.php` (aceitar country/zip, repassar billing) |
| **3** | `process_payment.php` (helper `log_payment_decline`), `gateways/beehive.php`, `gateways/hypercash.php`, `gateways/efi.php` |
| **4** | `process_payment.php` (MP redirect_url_3ds), `checkout.php` (onSubmit MP redirect) |
| **5** | `gateways/stripe.php` (novo), `process_payment.php` (fluxo Stripe), `checkout.php` (Stripe Elements) |
| **6** | `gateways/paypal.php` (novo), `process_payment.php` (fluxo PayPal), `checkout.php` (PayPal fallback UX) |
| **7** | `process_payment.php` (função `decide_gateway`), `checkout.php` (roteamento UI) |

---

## 3. Patches por commit

### COMMIT 1 — Base de dados + configs

**Arquivo:** `migrations/checkout_internacional.sql`

```sql
-- Feature flags e moeda (configuracoes_sistema)
-- configuracoes_sistema tem UNIQUE(chave). ON DUPLICATE KEY atualiza valor.
INSERT INTO configuracoes_sistema (chave, valor, tipo, descricao) VALUES
('payment_routing_enabled', '0', 'bool', 'Ativa roteamento BR=Efí / Internacional=Stripe'),
('stripe_enabled', '0', 'bool', 'Habilita Stripe para checkout internacional'),
('paypal_enabled', '0', 'bool', 'Habilita PayPal como fallback internacional'),
('default_currency', 'BRL', 'text', 'Moeda padrão'),
('allowed_currencies', 'BRL,USD,EUR', 'text', 'Moedas permitidas'),
('usd_rate', '5.00', 'text', 'Taxa BRL->USD quando price_usd não definido')
ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo), descricao = VALUES(descricao);

-- Preços multi-moeda em produtos (opcional; MySQL < 8 não suporta IF NOT EXISTS - execute manualmente se já existir)
ALTER TABLE produtos
  ADD COLUMN price_usd DECIMAL(10,2) NULL DEFAULT NULL AFTER preco,
  ADD COLUMN price_eur DECIMAL(10,2) NULL DEFAULT NULL AFTER price_usd;

-- Tabela de logs de recusa (opcional)
CREATE TABLE IF NOT EXISTS payment_decline_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  gateway VARCHAR(50) NOT NULL,
  payment_id VARCHAR(255) DEFAULT NULL,
  status VARCHAR(50) DEFAULT NULL,
  status_detail VARCHAR(100) DEFAULT NULL,
  decline_code VARCHAR(100) DEFAULT NULL,
  product_id INT DEFAULT NULL,
  country VARCHAR(2) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  raw JSON DEFAULT NULL,
  INDEX idx_gateway_ts (gateway, ts),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Nota MySQL < 8.0**: Remover `IF NOT EXISTS` do ALTER TABLE; executar manualmente se coluna já existir.

---

### COMMIT 2 — Billing mínimo no checkout

**checkout.php** — Após o bloco phone/cpf (linha ~965), inserir:

```html
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div><label for="country" class="block text-sm font-medium text-gray-700">País</label>
                                    <select id="country" name="country" class="checkout-input mt-1 block w-full pl-3 pr-4 py-3 bg-white border border-gray-300 rounded-lg" required>
                                        <option value="BR" selected>Brasil</option>
                                        <option value="US">Estados Unidos</option>
                                        <option value="PT">Portugal</option>
                                        <option value="ES">Espanha</option>
                                        <option value="DE">Alemanha</option>
                                        <option value="FR">França</option>
                                        <option value="GB">Reino Unido</option>
                                        <option value="IT">Itália</option>
                                        <option value="OTHER">Outro</option>
                                    </select>
                                </div>
                                <div><label for="zip" class="block text-sm font-medium text-gray-700">CEP / Código postal</label>
                                    <input type="text" id="zip" name="zip" class="checkout-input mt-1 block w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-lg" placeholder="00000-000 ou 12345">
                                </div>
                            </div>
```

**checkout.php** — Em `validateForm()` (linha ~1693), adicionar ao payerData:

```javascript
country: document.getElementById('country')?.value || 'BR',
zip: document.getElementById('zip')?.value || ''
```

E validação para internacional:

```javascript
const country = document.getElementById('country')?.value || 'BR';
if (country !== 'BR' && country !== 'OTHER') {
    const zip = document.getElementById('zip')?.value || '';
    if (!zip || zip.length < 3) { showAlert('Para compras internacionais, informe o CEP/código postal.'); return null; }
}
```

**process_payment.php** — Após linha 130 (required_fields), adicionar:

```php
$data['country'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['country'] ?? 'BR'), 0, 2)) ?: 'BR';
$data['zip'] = preg_replace('/[^0-9A-Za-z\-]/', '', substr($data['zip'] ?? '', 0, 20));
```

**process_payment.php** — Repassar billing aos gateways. Exemplo para Beehive (em `beehive_create_payment` call):

```php
$customer_data['country'] = $data['country'] ?? 'BR';
$customer_data['zip'] = $data['zip'] ?? '';
```

(E cada gateway deve incluir esses campos no payload quando a API suportar.)

---

### COMMIT 3 — Logs padronizados

**process_payment.php** — Adicionar helper após `log_process`:

```php
function log_payment_decline($gateway, $payment_id, $status, $status_detail, $decline_code, $product_id, $country, $raw = null) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip && strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
    $entry = [
        'ts' => date('c'),
        'gateway' => $gateway,
        'payment_id' => $payment_id,
        'status' => $status,
        'status_detail' => $status_detail,
        'decline_code' => $decline_code,
        'product_id' => $product_id,
        'country' => $country,
        'ip' => $ip,
    ];
    if ($raw !== null) $entry['raw'] = $raw;
    $log_file = __DIR__ . '/payment_decline_log.txt';
    @file_put_contents($log_file, json_encode($entry) . "\n", FILE_APPEND);
    // Opcional: inserir em payment_decline_logs se tabela existir
    global $pdo;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO payment_decline_logs (gateway, payment_id, status, status_detail, decline_code, product_id, country, ip, raw) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$gateway, $payment_id, $status, $status_detail, $decline_code, $product_id, $country, $raw ? json_encode($raw) : null]);
        } catch (PDOException $e) { /* tabela pode não existir */ }
    }
}
```

**process_payment.php** — Em cada bloco de recusa (ex.: Mercado Pago, Beehive, Efí):

```php
// Exemplo Mercado Pago (após detectar status rejected)
log_payment_decline('mercadopago', $payment_id ?? null, $status, $status_detail ?? null, 
    isset($res_data['cause'][0]['code']) ? $res_data['cause'][0]['code'] : null, 
    $main_product_id, $data['country'] ?? 'BR', $res_data);
```

Repetir padrão para Beehive, Hypercash, Efí (usar campos equivalentes de erro).

---

### COMMIT 4 — 3DS completo / requires_action

**process_payment.php** — No fluxo Mercado Pago, após montar `$response_front`:

```php
// 3DS: se in_process e houver URL de autenticação, incluir para redirect
if ($status === 'in_process' && isset($res_data['transaction_details']['external_resource_url']) && !empty($res_data['transaction_details']['external_resource_url'])) {
    $response_front['redirect_url_3ds'] = $res_data['transaction_details']['external_resource_url'];
}
```

**checkout.php** — No onSubmit do Payment Brick (após `result.status === 'rejected'`), adicionar antes de `pix_created`:

```javascript
if (response.ok && result.status === 'in_process' && result.redirect_url_3ds) {
    window.location.href = result.redirect_url_3ds;
    return;
}
```

---

### COMMIT 5 — Stripe internacional

**gateways/stripe.php** (novo):

```php
<?php
/**
 * Gateway Stripe - PaymentIntents para cartão internacional (USD/EUR)
 * 3DS tratado no frontend via stripe.confirmCardPayment(clientSecret)
 */
function stripe_create_payment_intent($secret_key, $amount_cents, $currency, $customer_email, $description, $metadata = []) {
    $url = 'https://api.stripe.com/v1/payment_intents';
    $params = [
        'amount' => $amount_cents,
        'currency' => strtolower($currency),
        'description' => substr($description, 0, 500),
        'receipt_email' => $customer_email,
        'automatic_payment_methods[enabled]' => 'true',
    ];
    foreach ($metadata as $k => $v) $params['metadata[' . $k . ']'] = $v;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret_key, 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true);
    if ($code >= 200 && $code < 300 && isset($data['client_secret'])) {
        return ['client_secret' => $data['client_secret'], 'id' => $data['id'], 'status' => $data['status']];
    }
    return ['error' => true, 'message' => $data['error']['message'] ?? 'Stripe error', 'raw' => $data];
}
```

**process_payment.php** — Novo bloco (antes do Mercado Pago):

```php
} elseif ($gateway_choice === 'stripe' && (getSystemSetting('stripe_enabled', '0') === '1')) {
    require_once __DIR__ . '/gateways/stripe.php';
    $secret = $credentials['stripe_secret_key'] ?? '';
    if (empty($secret)) throw new Exception("Stripe não configurado.");
    $country = $data['country'] ?? 'US';
    $currency = ($country === 'BR') ? 'brl' : 'usd';
    $amount = (float)($data['transaction_amount'] ?? 0);
    $amount_cents = (int)round($amount * 100);
    $result = stripe_create_payment_intent($secret, $amount_cents, $currency, $data['email'], 'Compra: ' . $main_product_name, ['product_id' => $main_product_id]);
    if (!empty($result['error'])) {
        log_payment_decline('stripe', null, 'error', null, $result['message'] ?? null, $main_product_id, $country, $result);
        throw new Exception($result['message'] ?? 'Erro Stripe');
    }
    returnJsonSuccess([
        'status' => 'requires_payment_method',
        'client_secret' => $result['client_secret'],
        'payment_intent_id' => $result['id'],
        'stripe_publishable_key' => $credentials['stripe_publishable_key'] ?? '',
        'redirect_url' => $redirect_url_computed($result['id']),
    ]);
}
```

**checkout.php** — Adicionar container Stripe Elements e lógica para:
1. Quando `country !== 'BR'` e `stripe_enabled`, mostrar cartão Stripe.
2. Ao submeter, chamar `/process_payment` com `gateway: 'stripe'` para obter `client_secret`.
3. Chamar `stripe.confirmCardPayment(clientSecret, {payment_method: {card: cardElement}})`.
4. Se sucesso, redirecionar para `redirect_url`.

(O patch completo do checkout Stripe é extenso; acima é o esqueleto. A UI pode reutilizar o padrão dos outros gateways.)

---

### COMMIT 6 — PayPal fallback

**gateways/paypal.php** (novo) — Criar order, retornar `approval_url`. Frontend redireciona usuário; ao voltar, capturar.

**process_payment.php** — Adicionar fluxo `gateway_choice === 'paypal'`.

**checkout.php** — Quando Stripe retornar recusa, mostrar botão "Pagar com PayPal" que submete com `gateway: 'paypal'`.

---

### COMMIT 7 — Roteamento inteligente + UI

**process_payment.php** — Função:

```php
function decide_gateway($country, $payment_method_preference, $stripe_enabled, $paypal_enabled) {
    $country = strtoupper($country ?? 'BR');
    if ($country === 'BR') return ['primary' => 'efi', 'fallback' => null];
    if ($stripe_enabled) return ['primary' => 'stripe', 'fallback' => $paypal_enabled ? 'paypal' : null];
    return ['primary' => 'efi', 'fallback' => null]; // fallback para BR se Stripe off
}
```

**checkout.php** — Antes de renderizar métodos:
- Ler `country` do form (ou default BR).
- Se BR: mostrar Efí (Pix + Cartão) como hoje.
- Se internacional: mostrar apenas Stripe (cartão); em caso de recusa, mostrar PayPal.

---

## 4. Configuração (ENV / config)

### Chaves necessárias

| Chave | Onde | Exemplo |
|-------|------|---------|
| `stripe_publishable_key` | usuarios (por infoprodutor) ou saas_config_admin | pk_live_xxx |
| `stripe_secret_key` | usuarios ou saas_config_admin | sk_live_xxx |
| `paypal_client_id` | usuarios | xxx |
| `paypal_client_secret` | usuarios | xxx |

### configuracoes_sistema

```sql
UPDATE configuracoes_sistema SET valor = '1' WHERE chave = 'payment_routing_enabled';
UPDATE configuracoes_sistema SET valor = '1' WHERE chave = 'stripe_enabled';
UPDATE configuracoes_sistema SET valor = '1' WHERE chave = 'paypal_enabled';
```

### Webhooks

| Gateway | URL sugerida | Eventos |
|---------|--------------|---------|
| **Stripe** | `https://seudominio.com/api/stripe_webhook.php` | `payment_intent.succeeded`, `payment_intent.payment_failed` |
| **PayPal** | Configurar no app PayPal | `CHECKOUT.ORDER.APPROVED` |
| **Efí** | Já existe `notification.php` | Manter |

---

## 5. Checklist de teste (sandbox)

### Brasil (Efí)

- [ ] Pix Efí: gera QR, paga, webhook atualiza venda
- [ ] Cartão Efí: aprovação direta
- [ ] Cartão Efí recusado: log em payment_decline_log.txt

### Internacional (Stripe)

- [ ] Cartão aprovado: `4242 4242 4242 4242`
- [ ] Cartão 3DS (requer autenticação): `4000 0027 6000 3184` — challenge exibido e concluído
- [ ] Cartão recusado: `4000 0000 0000 9995` — fallback PayPal aparece
- [ ] Cartão saldo insuficiente: `4000 0000 0000 9995`
- [ ] Log de recusa com country e IP

**Cartões teste Stripe**: https://stripe.com/docs/testing#cards

### PayPal

- [ ] Checkout com PayPal aprovado
- [ ] Retorno para obrigado com payment_id

### Logs

- [ ] Cada recusa gera 1 linha JSON com status_detail + IP + country
- [ ] Nenhum dado sensível (PAN, CVV) nos logs

---

## 6. Ordem de execução

1. **Commit 1** — Rodar migration
2. **Commit 2** — Billing (baixo risco)
3. **Commit 3** — Logs (não altera fluxo)
4. **Commit 4** — 3DS MP (melhora aprovação)
5. **Commit 5** — Stripe (novo gateway)
6. **Commit 6** — PayPal fallback
7. **Commit 7** — Roteamento (ativar com `payment_routing_enabled=1`)

Manter `payment_routing_enabled=0` e `stripe_enabled=0` até Commits 5–7 estarem testados.
