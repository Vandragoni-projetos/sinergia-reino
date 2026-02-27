# Auditoria: Checkout e Taxa de Aprovação (Payment Acceptance)

## Diagnóstico geral

**Nível de risco atual: MÉDIO–ALTO** para público internacional (EUA/Europa).

| Fator | Situação atual |
|-------|----------------|
| Gateways internacionais | Stripe, PayPal, Pagar.me marcados como "em desenvolvimento" — **não utilizáveis** |
| Moeda | Fixa em BRL; sem suporte a USD/EUR |
| 3D Secure | Parcialmente tratado (Beehive); fluxo MP/Efí sem tratamento explícito de `requires_action` |
| Campos de billing | Sem país, CEP, endereço — crítico para cross-border |
| Logs de recusa | Estrutura básica; falta padronização e códigos de erro |
| Antifraude | Sem validação IP vs país; sem descritor configurável |

---

## 1. Fluxo técnico do pagamento

### 1.1 Onde o pagamento é iniciado

| Gateway | Arquivo | Entrada |
|---------|---------|---------|
| Mercado Pago | `checkout.php` (Payment Brick) → `process_payment.php` | `onSubmit` do Brick envia `formData` (token, payment_method_id) |
| Beehive | `checkout.php` (card form) → `process_payment.php` | POST com `card_token` ou `card_data` |
| Hypercash | Idem Beehive | POST com `card_token` + `card_data` |
| Efí Cartão | `checkout.php` (EfiPay SDK) → `process_payment.php` | POST com `payment_token` (EfiPay.CreditCard.getPaymentToken) |
| PushinPay / Efí Pix | `checkout.php` → `process_payment.php` | POST com dados do cliente; backend gera cobrança |

### 1.2 Geração do token do cartão

| Gateway | Método |
|---------|--------|
| **Mercado Pago** | Payment Brick coleta cartão e gera token internamente; `formData` contém `token` |
| **Beehive** | `BeehivePay.encrypt()` no frontend; envia hash ou dados diretos |
| **Hypercash** | `FastSoft.encrypt()`; prioriza `card_data` direto (API não aceita só token) |
| **Efí** | `EfiPay.CreditCard.setCreditCardData().getPaymentToken()` no frontend |

### 1.3 3D Secure (3DS)

| Gateway | Situação |
|---------|----------|
| **Mercado Pago** | Payment Brick pode disparar 3DS; backend não trata `in_process` + redirect 3DS. Se MP retornar URL de autenticação, o fluxo atual mostra apenas "pendente" sem redirecionar. |
| **Beehive** | Tratamento de erro 3DS; envio de IP e `card_data` para evitar falhas; mensagem específica para erros 3DS. |
| **Hypercash** | Envia IP; usa `card_data` direto; sem tratamento explícito de `requires_action`. |
| **Efí** | Sem tratamento explícito de 3DS/autenticação adicional. |
| **Stripe/PayPal** | Inativo ("em desenvolvimento"). |

### 1.4 Validação de país / IP

- **IP**: Beehive e Hypercash enviam IP via `get_client_ip()` / `hypercash_get_client_ip()`.
- **País**: Não coletado nem validado no checkout.
- **IP vs país**: Sem checagem de inconsistência.

### 1.5 Moeda

- Todos os valores em **BRL** (hardcoded/implícito).
- Mercado Pago: sem `currency` explícita; MP Brasil usa BRL por padrão.
- Stripe/PayPal: não implementados — sem suporte a USD/EUR.

### 1.6 Campos obrigatórios no checkout

**Coletados hoje** (`validateForm()` em `checkout.php`):
- `name`, `email`, `phone`, `cpf` (condicional)

**Ausentes para internacional:**
- `country` (país)
- `zip` / `postal_code` (CEP)
- `address` (endereço de cobrança)
- `state` (estado)

---

## 2. Possíveis causas de recusa

| Causa | Probabilidade | Comentário |
|-------|---------------|------------|
| Falta de 3DS quando exigido | **Alta** | Cartões europeus exigem SCA; fluxo atual pode não redirecionar para challenge. |
| Merchant cross-border | **Alta** | Conta BR processando EUA/EUR sem conta/descriptor adequados. |
| Descritor confuso | **Média** | Descrição "Compra: {nome_produto}"; sem DBA configurável. |
| Antifraude rígido | **Média** | Gateways BR costumam ter regras mais restritivas; sem dados de billing agrava. |
| Inconsistência IP vs país | **Média** | IP não validado; VPN/proxy pode gerar recusa. |
| Falta de captura assíncrona | **Baixa** | Fluxo síncrono; Pix/boleto já são assíncronos por natureza. |
| `requires_action` não tratado | **Alta** | Se MP/Efí retornarem status de autenticação, usuário não completa o fluxo. |
| Campos de billing incompletos | **Alta** | Várias bandeiras exigem nome, país e CEP no billing. |

---

## 3. Logs

### 3.1 Onde estão os logs

| Arquivo | Tipo | Uso |
|---------|------|-----|
| `process_payment_log.txt` (raiz) | `log_process()` | Fluxo de pagamento em `process_payment.php` |
| `api_errors.log` | `error_log()` | Erros da API |
| `gateways/beehive.php`, `hypercash.php`, `efi.php` | `error_log()` | Erros específicos dos gateways |

### 3.2 Códigos de erro registrados

- **Mercado Pago** (`api/process_payment.php`): `status_detail`, `cause[].code`, `cause[].description` usados em `getMercadoPagoErrorMessage()`; resposta completa não fica em log estruturado.
- **Beehive**: `error_log` com resposta da API e mensagem; mensagem 3DS específica.
- **Hypercash**: `error_log` com resposta.
- **Efí**: `log_process` com status; erros genéricos.

### 3.3 Lacunas nos logs

1. Ausência de esquema padronizado (ex.: JSON com `gateway`, `status`, `decline_code`, `payment_id`).
2. `status_detail` e `cause` do MP não gravados de forma consistente para análise.
3. Sem persistência em tabela (ex.: `payment_decline_logs`) para métricas.
4. IP do cliente e país (se disponível) não entram nos logs de recusa.

---

## 4. Melhorias recomendadas (sem refatoração ampla)

### 4.1 Impacto alto

| # | Melhoria | Onde | Esforço |
|---|----------|------|---------|
| 1 | Tratar `requires_action` / fluxo 3DS | `checkout.php` (onSubmit MP), `process_payment.php` | Médio |
| 2 | Habilitar Stripe/PayPal para internacional | `process_payment.php`, `checkout.php` | Alto |
| 3 | Incluir país e CEP no checkout | `checkout.php` (form + validateForm) | Baixo |
| 4 | Enviar billing (address, zip, country) ao gateway | `process_payment.php`, gateways | Médio |

### 4.2 Impacto médio

| # | Melhoria | Onde | Esforço |
|---|----------|------|---------|
| 5 | 3DS automático quando suportado | Configuração MP/Stripe; manter fluxo de redirect | Baixo |
| 6 | Descritor configurável (DBA/statement_descriptor) | Produto/config, `process_payment.php` | Baixo |
| 7 | Padronizar logs de recusa (JSON + decline_code) | `process_payment.php`, gateways | Médio |
| 8 | Validar IP e opcionalmente país (geo-IP) | Helper; `process_payment.php` | Baixo |

### 4.3 Impacto baixo

| # | Melhoria | Onde | Esforço |
|---|----------|------|---------|
| 9 | Apple Pay / Google Pay (Stripe) | Quando Stripe estiver ativo | Médio |
| 10 | Fallback cartão → PayPal | Checkout UX | Médio |
| 11 | Tabela `payment_decline_logs` | Migration + `process_payment.php` | Baixo |

---

## 5. Arquivos a alterar

| Arquivo | Alterações principais |
|---------|------------------------|
| `checkout.php` | Campos country, zip; `validateForm`; tratamento de redirect 3DS no onSubmit |
| `process_payment.php` | Campos billing; fluxo 3DS; log padronizado; (futuro) Stripe/PayPal |
| `api/process_payment.php` | Sincronizar com `process_payment.php` se ambos forem usados |
| `gateways/beehive.php` | Billing opcional; log estruturado |
| `gateways/hypercash.php` | Billing opcional; log estruturado |
| `gateways/efi.php` | Billing se suportado; log estruturado |
| `views/produto_config/aba_metodos_pagamento.php` | Config de statement descriptor (se disponível) |

---

## 6. Patches mínimos sugeridos

### 6.1 Campos country e zip no checkout

**`checkout.php`** — incluir no HTML do form (junto a phone/cpf):

```html
<!-- Country (necessário para internacional) -->
<div>
  <label>País</label>
  <select name="country" id="country" required>
    <option value="BR">Brasil</option>
    <option value="US">Estados Unidos</option>
    <option value="PT">Portugal</option>
    <!-- ... -->
  </select>
</div>
<!-- CEP / Código postal -->
<div>
  <label>CEP / Código postal</label>
  <input type="text" name="zip" id="zip" placeholder="00000-000 ou 12345">
</div>
```

**`validateForm()`** — adicionar:

```javascript
country: document.getElementById('country')?.value || 'BR',
zip: document.getElementById('zip')?.value || ''
```

**`process_payment.php`** — aceitar e repassar:

```php
$required_fields = ['transaction_amount', 'email', 'cpf', 'name', 'phone', 'product_id'];
// country e zip opcionais mas recomendados
$data['country'] = $data['country'] ?? 'BR';
$data['zip'] = preg_replace('/[^0-9A-Za-z\-]/', '', $data['zip'] ?? '');
```

### 6.2 Log padronizado de recusa

**`process_payment.php`** — criar helper:

```php
function log_payment_decline($gateway, $payment_id, $status, $status_detail, $cause = null, $client_ip = null) {
    $entry = [
        'ts' => date('c'),
        'gateway' => $gateway,
        'payment_id' => $payment_id ?? 'n/a',
        'status' => $status,
        'status_detail' => $status_detail,
        'cause' => $cause,
        'client_ip' => $client_ip ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null),
    ];
    $log_file = __DIR__ . '/payment_decline_log.txt';
    @file_put_contents($log_file, json_encode($entry) . "\n", FILE_APPEND);
}
```

Chamar em cada gateway ao detectar `rejected` / `declined` antes do `throw`.

### 6.3 Tratamento de redirect 3DS (Mercado Pago)

Se a API do MP retornar `status: 'in_process'` com `transaction_details.external_resource_url` ou `three_ds_info`, o frontend precisa redirecionar. Exemplo no `onSubmit`:

```javascript
// Após receber result do backend
if (result.status === 'in_process' && result.redirect_url_3ds) {
    window.location.href = result.redirect_url_3ds;
    return;
}
```

**`process_payment.php`** — ao processar MP, incluir no JSON de resposta:

```php
if ($status === 'in_process' && !empty($res_data['transaction_details']['external_resource_url'])) {
    $response_front['redirect_url_3ds'] = $res_data['transaction_details']['external_resource_url'];
}
```

(Valores exatos dependem da estrutura real da resposta do MP; validar na documentação.)

---

## 7. Testes sugeridos

### 7.1 Checklist

| Cenário | Como testar | Resultado esperado |
|---------|-------------|--------------------|
| Cartão válido | Cartão de teste do gateway | Aprovação e redirect para obrigado |
| Cartão com 3DS | Cartão que dispara 3DS (ex.: 4000 0027 6000 3184 no Stripe) | Redirect para challenge e conclusão do fluxo |
| Cartão internacional | Cartão US/EU em conta BR | Pode recusar por cross-border; validar mensagem |
| Saldo insuficiente | Cartão de teste com recusa (ex.: 4000 0000 0000 9995) | Recusa com mensagem clara |
| IP diferente do país | VPN ou proxy de outro país | Pode recusar; log deve registrar IP |
| Campos incompletos | Enviar sem country/zip | Validação ou erro do gateway; log sem dados sensíveis |

### 7.2 Sandbox

| Gateway | Cartões de teste |
|---------|------------------|
| Mercado Pago | https://www.mercadopago.com.br/developers/pt/docs/checkout-api/integration-test/test-cards |
| Stripe | https://stripe.com/docs/testing#cards (ex.: 4242 4242 4242 4242) |
| Beehive | Consultar documentação Beehive |
| Efí | Consultar documentação Efí |

---

## 8. Métrica de aprovação (benchmark)

Para **low-ticket digital** (produtos digitais até ~US$ 50 / R$ 250):

| Métrica | Meta | Nota |
|---------|------|------|
| Taxa de aprovação (cartão) | 85–92% | Média de mercado para low-ticket |
| Taxa de conversão checkout | 60–75% | Depende de UX e métodos disponíveis |
| Recusas por 3DS não concluído | < 5% | Se 3DS estiver bem implementado |
| Recusas por dados incompletos | < 3% | Com billing completo |

**O que costuma reduzir aprovação:**
- Sem 3DS quando exigido: −5 a −15%
- Billing incompleto: −3 a −8%
- Cross-border sem conta/descriptor adequado: −10 a −25%
- Descritor confuso: −2 a −5%

---

## 9. Resumo executivo

1. **Prioridade 1**: Implementar Stripe (e/ou PayPal) para EUA/Europa; incluir país e CEP no checkout.
2. **Prioridade 2**: Garantir tratamento de 3DS/`requires_action` em todos os gateways de cartão.
3. **Prioridade 3**: Padronizar logs de recusa e adicionar tabela opcional para análise.
4. **Prioridade 4**: Configurar statement descriptor e revisar regras de antifraude no painel de cada gateway.

Com as alterações acima, a tendência é aumentar a taxa de aprovação em 10–20 pontos percentuais em cenários internacionais.
