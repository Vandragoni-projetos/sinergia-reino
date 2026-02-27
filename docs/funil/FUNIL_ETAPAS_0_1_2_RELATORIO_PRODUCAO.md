# Funil de Vendas — Relatório de alterações para produção (Etapas 0, 1 e 2)

Use este documento para atualizar o ambiente de produção com as melhorias de segurança e controle de duplicidade do funil (Upsell/Downsell), **sem quebrar** o fluxo atual.

---

## Resumo do que foi feito

| Etapa | Objetivo |
|-------|----------|
| **0** | Garantir redirect pós-pagamento: aprovado → `funnel_offer.php` (se funil ativo com upsell) ou `/obrigado`. |
| **1** | Segurança em `funnel_offer.php`: validar venda existente, status aprovado e funil ativo; falha → redirect `/obrigado`. Remoção de bloqueio por nome/email. |
| **2** | Controle de duplicidade: tabela `funnel_events`; registrar “shown” e não reexibir oferta se já houver decisão (accepted/declined/skipped). |

---

## 1. Migration (banco de dados)

### 1.1 Nova tabela `funnel_events` (Etapa 2)

**Arquivo:** `migrations/funnel_events.sql`

**Ação:** Executar o SQL no banco (phpMyAdmin ou linha de comando), com o banco já selecionado.

```sql
CREATE TABLE IF NOT EXISTS `funnel_events` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `community_id` INT(11) NOT NULL DEFAULT 1,
  `main_payment_id` VARCHAR(255) NOT NULL COMMENT 'transacao_id da compra principal',
  `step` ENUM('upsell','downsell') NOT NULL,
  `offer_product_id` INT(11) NOT NULL,
  `decision` ENUM('shown','accepted','declined','skipped') NOT NULL DEFAULT 'shown',
  `offer_payment_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'transacao_id do pagamento do upsell/downsell quando existir',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_main_step_offer` (`main_payment_id`,`step`,`offer_product_id`),
  KEY `idx_main_payment` (`main_payment_id`),
  KEY `idx_community` (`community_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Eventos do funil: shown=exibiu oferta, accepted/declined/skipped=decisão final';
```

**Observação:** Se a tabela `funnel_events` não existir, a página do funil continua funcionando; apenas o controle de “já decidido” não será aplicado (e os erros serão apenas logados).

---

## 2. Arquivos alterados

### 2.1 `helpers/funnel_helper.php`

**Alteração (Etapa 0):** Apenas **comentário** no início do arquivo, documentando o fluxo de redirect.

**Trecho adicionado no docblock do topo:**

```php
/**
 * Funil de vendas (Upsell/Downsell): define para onde redirecionar após pagamento aprovado.
 *
 * Fluxo (Etapa 0):
 * - Pagamento aprovado -> redirect para /funnel_offer.php?payment_id=TRANSACTION_ID&step=upsell
 *   quando existir funil ativo (product_funnels.is_active=1) e upsell_product_id configurado.
 * - Se não tiver funil ou upsell -> redirect para /obrigado?payment_id=... (default).
 *
 * Se o produto principal tiver funil ativo com upsell...
 */
```

Nenhuma alteração de lógica; o restante do arquivo permanece igual.

---

### 2.2 `funnel_offer.php`

**Alterações:**

1. **SELECT da venda (Etapa 1 + 2)**  
   Incluir `v.status_pagamento` e `v.community_id`:

   - Antes: `SELECT v.transacao_id, v.produto_id, v.comprador_nome, v.comprador_email, ...`
   - Depois: `SELECT v.transacao_id, v.produto_id, v.community_id, v.comprador_nome, v.comprador_email, v.status_pagamento, ...`

2. **Lista de status aprovado (Etapa 1)**  
   Logo após definição de `$obrigado_base`:

   ```php
   $funnel_status_approved = ['approved', 'paid', 'APROVADO', 'Paid', 'Approved'];
   ```

3. **Validações com redirect para `/obrigado` (Etapa 1)**  
   - Se `payment_id` não existir em `vendas`: `header('Location: ' . $obrigado_base . '?payment_id=...'); exit;`
   - Se `status_pagamento` não estiver em `$funnel_status_approved`: mesmo redirect.
   - Se não houver funil ativo para o produto principal: mesmo redirect.
   - Em qualquer exceção no `try`: `error_log('[FUNNEL] ' . $e->getMessage()); header(obrigado); exit;`  
   Em todos os casos a mensagem ao usuário é apenas o redirect; nada de detalhes de erro na tela.

4. **Remoção (Etapa 1)**  
   Todo o bloco de “anti-duplicidade” por `FUNNEL_PREVENT_DUPLICATE` (checagem por `comprador_email` / `comprador_nome`) foi removido.

5. **Etapa 2 – Uso de `funnel_events`**  
   Depois de montar `$custom` e antes de fechar o `else`:

   - **Checar decisão:**  
     `SELECT decision FROM funnel_events WHERE main_payment_id = ? AND step = ? AND offer_product_id = ? AND decision IN ('accepted','declined','skipped')`.  
     Se existir linha → `$offer_product = null` (não reexibir oferta).
   - **Registrar “shown”:**  
     Se `$offer_product` ainda estiver setado:  
     `INSERT INTO funnel_events (community_id, main_payment_id, step, offer_product_id, decision) VALUES (?,?,?,?,'shown') ON DUPLICATE KEY UPDATE decision = 'shown', updated_at = CURRENT_TIMESTAMP`.

   `community_id` vem de `$sale_details['community_id']` (fallback 1).  
   Tanto a consulta quanto o insert estão em `try/catch`; em falha (ex.: tabela inexistente) só registra log e segue.

6. **Logs**  
   Mensagens de erro do funil com prefixo `[FUNNEL]` em `error_log`.

---

## 3. Checklist para produção

- [ ] **Banco:** Executar `migrations/funnel_events.sql` (criar tabela `funnel_events`).
- [ ] **Código:** Substituir/copiar os arquivos conforme abaixo:
  - [ ] `helpers/funnel_helper.php` (apenas docblock alterado).
  - [ ] `funnel_offer.php` (versão atual com Etapas 0, 1 e 2).
- [ ] **Tabela `vendas`:** Garantir que existe a coluna `community_id` (já usada em outros fluxos). Se não existir, o SELECT em `funnel_offer.php` pode falhar; nesse caso inclua a coluna ou adapte o SELECT para não usá-la e use `community_id = 1` em `funnel_events`.
- [ ] **Teste:** Fazer uma compra de teste com funil ativo e upsell configurado; após aprovação deve ir para `funnel_offer.php?payment_id=...&step=upsell`. Acessar de novo a mesma URL e, após implementar Etapa 3 (accept/decline), a decisão ficará em `funnel_events` e a reexibição será controlada.

---

## 4. Referência rápida de arquivos

| Tipo      | Caminho |
|-----------|---------|
| Migration | `migrations/funnel_events.sql` (novo) |
| Alterado  | `helpers/funnel_helper.php` |
| Alterado  | `funnel_offer.php` |

---

## 5. Etapas 3 a 6 (implementadas)

### Etapa 3 — Accept/decline e botões

- **funnel_offer.php:** Os botões "Sim, quero desbloquear" e "Não agora" passam a apontar para `funnel_action.php?payment_id=...&step=...&action=accept` e `action=decline`.
- **funnel_action.php (novo):** Recebe `payment_id`, `step`, `action=accept|decline`. Valida venda, status e funil; grava decisão em `funnel_events`; em **decline** redireciona para downsell ou obrigado; em **accept** gera `prefill_token`, grava dados em `$_SESSION['checkout_prefill'][$prefill_token]` e redireciona para `checkout?p=HASH&funnel_main=...&funnel_step=...&prefill_token=...`.

### Etapa 4 — Prefill no checkout

- **checkout.php:** Lê `prefill_token`, `funnel_main` e `funnel_step` do GET. Se `prefill_token` for válido, inicia sessão, preenche nome, email, telefone e CPF a partir de `$_SESSION['checkout_prefill'][$token]` e remove o token (uso único). Campos continuam editáveis. No submit do pagamento (JSON), inclui `funnel_main_payment_id` e `funnel_step` no body quando vindos do funil.

### Etapa 5 — Redirect pós-pagamento do funil

- **helpers/funnel_helper.php:** Nova função `build_funnel_redirect_after_offer_payment($pdo, $funnel_main_payment_id, $funnel_step, $base_url, $obrigado_url)`. Se step foi **upsell** e existe downsell → retorna URL para `funnel_offer.php?payment_id=MAIN&step=downsell`; senão → obrigado com `payment_id=MAIN`.
- **process_payment.php e api/process_payment.php:** Leem `funnel_main_payment_id` e `funnel_step` do body. Ao montar a URL de redirect após aprovação, se ambos estiverem presentes usam `build_funnel_redirect_after_offer_payment`; caso contrário mantêm `build_final_redirect_url` (comportamento atual para compra principal).

### Etapa 6 — Modo teste

- **funnel_offer.php:** Com `test=1` e modo dev ativo (`dev=1&token=...`), não exige `status_pagamento` aprovado para exibir a oferta (permite testar com venda pendente). Documentado em `docs/FUNIL_UX_DEV_E_SEGURANCA.md` (sec. 3.3). Uso recomendado do `dev_funnel_simulator.php` para fluxo completo (venda aprovada).

### Checklist adicional (Etapas 3–6)

- [ ] **Arquivos:** `funnel_action.php` (novo), `funnel_offer.php`, `checkout.php`, `helpers/funnel_helper.php`, `process_payment.php`, `api/process_payment.php`.
- [ ] **Teste:** Simulador → funnel_offer → Accept → checkout com dados preenchidos → pagamento → redirect para downsell ou obrigado conforme step.
