# Funil de Vendas – Alterações para replicar em produção

Este documento lista **todos os arquivos** criados ou alterados para o funil de vendas (Upsell/Downsell), para você replicar em produção.

---

## 1. Arquivos NOVOS (criar na raiz ou nas pastas indicadas)

### 1.1 `helpers/funnel_helper.php` (criar na pasta `helpers/`)

Arquivo **inteiro** – criar novo:

```php
<?php
/**
 * Funil de vendas (Upsell/Downsell): define para onde redirecionar após pagamento aprovado.
 * Se o produto principal tiver funil ativo com upsell, retorna URL da página de oferta (upsell).
 * Caso contrário retorna null para usar o redirecionamento padrão (obrigado ou redirectUrl).
 *
 * @param PDO $pdo
 * @param int $main_product_id ID do produto que foi comprado
 * @param string $payment_id transacao_id (payment_id) da venda aprovada
 * @param string $base_url URL base do site (ex: https://dominio.com/)
 * @return string|null URL completa para funnel_offer?payment_id=X&step=upsell ou null
 */
function get_funnel_redirect_url_after_approval($pdo, $main_product_id, $payment_id, $base_url) {
    $base_url = rtrim($base_url, '/');
    try {
        $stmt = $pdo->prepare("
            SELECT pf.upsell_product_id, pf.downsell_product_id
            FROM product_funnels pf
            WHERE pf.main_product_id = ? AND pf.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$main_product_id]);
        $funnel = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$funnel || empty($funnel['upsell_product_id'])) {
            return null;
        }
        return $base_url . '/funnel_offer.php?payment_id=' . urlencode($payment_id) . '&step=upsell';
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Retorna a URL final de redirecionamento após pagamento aprovado:
 * se houver funil ativo com upsell, retorna a página de oferta; senão retorna a URL padrão (obrigado).
 */
function build_final_redirect_url($pdo, $main_product_id, $payment_id, $default_redirect_url, $base_url) {
    $funnel_url = get_funnel_redirect_url_after_approval($pdo, $main_product_id, $payment_id, rtrim($base_url, '/'));
    return $funnel_url !== null ? $funnel_url : $default_redirect_url;
}
```

---

### 1.2 `funnel_offer.php` (criar na **raiz** do projeto, junto de `checkout.php` e `obrigado.php`)

Arquivo **inteiro** – criar novo. Conteúdo completo em: [funnel_offer.php](../funnel_offer.php) (copiar do repositório).

Resumo: página que recebe `payment_id` e `step=upsell|downsell`, carrega a venda e o funil, exibe o produto de oferta e botões "Sim, quero aproveitar" (checkout do produto) e "Não, obrigado" (downsell ou obrigado).

---

## 2. Arquivos ALTERADOS

### 2.1 `migrations/funnel_tables.sql`

- **Comentários no topo:** trocar as duas primeiras linhas por:
  - `-- No phpMyAdmin: selecione o banco no painel esquerdo e use a aba SQL (evita erro 1046).`
  - `-- Pela linha de comando: mysql -u usuario -p nome_do_banco < migrations/funnel_tables.sql`
- **Tipos das colunas** (compatíveis com `produtos.id` que é signed):
  - `main_product_id`: `int(11) NOT NULL` (sem UNSIGNED), com COMMENT `'FK produtos.id (signed no schema original)'`
  - `upsell_product_id`: `int(11) DEFAULT NULL` com COMMENT `'FK produtos.id'`
  - `downsell_product_id`: `int(11) DEFAULT NULL` com COMMENT `'FK produtos.id'`
- **Execução:** rodar no banco **já selecionado** no phpMyAdmin (ou informar o nome do banco na linha de comando).

---

### 2.2 `process_payment.php`

**A) Após definir `$redirect_url_after_approval` (por volta da linha onde está o comentário "URL Obrigado"):**

- Adicionar **antes** de `log_process("Webhook URL gerada:"...)`:

```php
    $base_url_funnel = 'https://' . $domainName . ($path ? $path . '/' : '/');
    if (file_exists(__DIR__ . '/helpers/funnel_helper.php')) {
        require_once __DIR__ . '/helpers/funnel_helper.php';
    }
```

- O comentário da linha da URL Obrigado pode ficar: `// URL Obrigado (ou funil de vendas se ativo)`.

**B) Em TODOS os pontos que devolvem redirect após aprovação ou pix_created**, trocar:

- De: `$redirect_url_after_approval . '?payment_id=' . $payment_id`
- Para: `build_final_redirect_url($pdo, $main_product_id, $payment_id, $redirect_url_after_approval . '?payment_id=' . $payment_id, $base_url_funnel)`

Onde isso aparece:

1. **PushinPay** – resposta `pix_created`: chave `'redirect_url_after_approval'`
2. **Efí (Pix)** – resposta `pix_created`: chave `'redirect_url_after_approval'`
3. **Beehive** – quando `$status === 'approved'`: `$response_data['redirect_url']`
4. **Hypercash** – quando `$status === 'approved'`: `$response_data['redirect_url']`
5. **Efí Cartão** – quando `$status === 'approved'`: `$response_data['redirect_url']`
6. **Mercado Pago** – resposta `pix_created`: chave `'redirect_url_after_approval'`; e quando `$status == 'approved'`: `$response_front['redirect_url']`

Ou seja: em **todos** os `returnJsonSuccess` ou atribuições de `redirect_url` / `redirect_url_after_approval` que usam a URL de obrigado, passar a usar `build_final_redirect_url(...)` com os 5 argumentos acima.

---

### 2.3 `process_free.php`

**Substituir** o bloco que monta `$redirect_url` e chama `returnJsonSuccess` (após aprovado produto grátis) por:

```php
    $protocol = $is_https ? 'https://' : 'https://'; // Força HTTPS sempre
    $base_url = $protocol . $domainName . '/';
    $default_redirect = $base_url . 'obrigado?payment_id=' . urlencode($transaction_id);
    $checkout_config = json_decode($produto['checkout_config'] ?? '{}', true);
    if (!empty($checkout_config['redirectUrl'])) {
        $default_redirect = rtrim($checkout_config['redirectUrl'], '?&') . '?payment_id=' . urlencode($transaction_id);
    }
    if (file_exists(__DIR__ . '/helpers/funnel_helper.php')) {
        require_once __DIR__ . '/helpers/funnel_helper.php';
        $redirect_url = build_final_redirect_url($pdo, (int)$product_id, $transaction_id, $default_redirect, $base_url);
    } else {
        $redirect_url = $default_redirect;
    }
    
    returnJsonSuccess([
        'status' => 'approved',
        'payment_id' => $transaction_id,
        'redirect_url' => $redirect_url,
        'message' => 'Acesso liberado com sucesso!'
    ]);
```

(Mantendo o restante do arquivo igual.)

---

### 2.4 `api/process_payment.php`

**A) Após definir `$redirect_url_after_approval`:**

- Adicionar:
  - `$base_url_funnel = 'https://' . $domainName . '/';`
  - Carregar o helper: `if (file_exists(dirname(__DIR__) . '/helpers/funnel_helper.php')) { require_once dirname(__DIR__) . '/helpers/funnel_helper.php'; }`

**B) Onde devolve `redirect_url_after_approval` ou `redirect_url` para aprovado:**

- Trocar o valor por:  
  `function_exists('build_final_redirect_url') ? build_final_redirect_url($pdo, $main_product_id, $payment_id, $redirect_url_after_approval . '?payment_id=' . $payment_id, $base_url_funnel) : ($redirect_url_after_approval . '?payment_id=' . $payment_id)`

Aplicar nos pontos de resposta **pix_created** e **approved** (redirect para o front).

---

### 2.5 `checkout.php`

**A) Função `showPixModal`**

- Assinatura: adicionar 5º parâmetro: `redirectUrlAfterApproval`
  - De: `function showPixModal(qrCodeBase64, pixCode, paymentId, gatewayUsed)`
  - Para: `function showPixModal(qrCodeBase64, pixCode, paymentId, gatewayUsed, redirectUrlAfterApproval)`
- Onde chama `startPaymentCheck`: passar esse 5º parâmetro como 4º argumento de `startPaymentCheck`:
  - `startPaymentCheck(paymentId, infoprodutorId, gatewayUsed, redirectUrlAfterApproval)`

**B) Função `startPaymentCheck`**

- Assinatura: adicionar 4º parâmetro: `redirectUrlAfterApproval`
  - De: `function startPaymentCheck(paymentId, sellerId, gatewayUsed)`
  - Para: `function startPaymentCheck(paymentId, sellerId, gatewayUsed, redirectUrlAfterApproval)`
- Quando `result.status === 'approved'` (ou `'paid'`): ao montar a URL de redirect, usar **primeiro** `redirectUrlAfterApproval`; se não existir, aí sim `customRedirectUrl` e por último `/obrigado?payment_id=...`. Exemplo de lógica:
  - `const redirectTo = redirectUrlAfterApproval || (customRedirectUrl ? ... : null) || '/obrigado?payment_id=' + paymentId;`
  - `setTimeout(() => { window.location.href = redirectTo; }, 2000);`

**C) Todas as chamadas a `showPixModal`** (quando a resposta é `pix_created`):

- Adicionar 5º argumento: `result.redirect_url_after_approval || null`
- Gateways: PushinPay, Efí, Pagar.me, Stripe, Mercado Pago (tanto no branch `pix_created` quanto no branch que chama `startPaymentCheck` + `showPixModal` para status pendente).

**D) Chamada direta a `startPaymentCheck`** (Mercado Pago, status pendente):

- Incluir 4º argumento: `result.redirect_url_after_approval || null`.

**E) Em todos os `fetch('/process_payment', ...)`** (todos os gateways):

- No `body` do `JSON.stringify`, incluir **sempre** `product_id: mainProductId` (além do que já enviam), para garantir que o backend use o produto principal para o funil:
  - PushinPay, Efí Pix, Efí Cartão, Pagar.me (Pix e cartão), Stripe (Pix e cartão), Beehive, Hypercash, Mercado Pago.

---

## 3. Checklist para produção

- [ ] Banco: executar `migrations/funnel_tables.sql` (com banco selecionado).
- [ ] Banco: executar `migrations/funnel_custom_config.sql` e `migrations/funnel_offer_theme.sql` (personalização e tema).
- [ ] Criar `helpers/funnel_helper.php` (conteúdo completo acima).
- [ ] Criar `funnel_offer.php` na raiz (copiar a versão atual do repositório, com custom config e theme).
- [ ] Atualizar `process_payment.php` (helper + `$base_url_funnel` + `build_final_redirect_url` em todos os redirects).
- [ ] Atualizar `process_free.php` (bloco de redirect com funil).
- [ ] Atualizar `api/process_payment.php` (helper + `build_final_redirect_url` nas respostas).
- [ ] Atualizar `checkout.php` (showPixModal, startPaymentCheck, passar `redirect_url_after_approval`, usar no redirect aprovado, e `product_id: mainProductId` em todos os gateways).
- [ ] Atualizar `views/produto_config/aba_funil.php` (formulário com Aparência + Personalizar Upsell/Downsell).
- [ ] Atualizar `views/produto_config.php` (salvamento de custom config e offer_theme no POST da aba funil).
- [ ] Configurar funil no painel: produto → Funil de Vendas → ativar, escolher upsell/downsell, preencher Aparência (cores/logo/títulos) e opcionalmente banners/descrição → Salvar.
- [ ] Testar compra com o gateway que você usa (ex.: Efí Pix); após aprovação deve abrir a página de oferta (upsell) com o visual configurado.

---

## 4. Referência rápida de arquivos

| Tipo     | Caminho |
|----------|---------|
| Novo     | `helpers/funnel_helper.php` |
| Novo     | `funnel_offer.php` (raiz) |
| Migração | `migrations/funnel_tables.sql` |
| Migração | `migrations/funnel_custom_config.sql` |
| Migração | `migrations/funnel_offer_theme.sql` |
| Alterado | `process_payment.php` |
| Alterado | `process_free.php` |
| Alterado | `api/process_payment.php` |
| Alterado | `checkout.php` |
| Alterado | `views/produto_config/aba_funil.php` |
| Alterado | `views/produto_config.php` |

A configuração do funil (quais produtos são upsell/downsell) continua sendo feita na **Configuração do Produto** → aba **Funil de Vendas**, e a tabela `product_funnels` é preenchida por essa tela.

---

## 5. Personalização e tema (Upsell/Downsell + aparência)

Para que cada produto possa ter **banners, descrição, capa** personalizados e **cores, logo e títulos** configuráveis na página de oferta:

### 5.1 Migrations adicionais (rodar após `funnel_tables.sql`)

- **`migrations/funnel_custom_config.sql`** – adiciona colunas `upsell_custom_config` e `downsell_custom_config` (JSON) em `product_funnels`.
- **`migrations/funnel_offer_theme.sql`** – adiciona coluna `offer_theme` (JSON) em `product_funnels`.

Execute cada uma no banco (uma vez). Se a coluna já existir, ignore o erro.

### 5.2 Arquivos alterados (personalização + tema)

- **`views/produto_config/aba_funil.php`** – Aba Funil de Vendas: formulário com seção **Aparência da página de oferta** (logo, cor primária/secundária, fundo, títulos Upsell/Downsell) no topo; blocos **Personalizar oferta Upsell** e **Personalizar oferta Downsell** (banner cabeçalho, banner lateral, capa, descrição HTML). Substituir pelo arquivo atual do repositório.
- **`views/produto_config.php`** – No POST da aba `funil`: monta e grava `upsell_custom_config`, `downsell_custom_config` e `offer_theme` (incluindo uploads de imagens do funil). Usar a versão atual do repositório.
- **`funnel_offer.php`** – Já listado como novo; deve ser a **versão atual** que lê `upsell_custom_config` / `downsell_custom_config` e `offer_theme` e aplica na página (cores, logo, textos do cabeçalho, banner, descrição, capa).

### 5.3 Checklist adicional (personalização/tema)

- [ ] Executar `migrations/funnel_custom_config.sql`.
- [ ] Executar `migrations/funnel_offer_theme.sql`.
- [ ] Atualizar `views/produto_config/aba_funil.php` (conteúdo completo do repositório).
- [ ] Atualizar `views/produto_config.php` (salvamento do funil com custom config e theme).
- [ ] Garantir que `funnel_offer.php` na raiz é a versão que usa custom config e offer_theme.
