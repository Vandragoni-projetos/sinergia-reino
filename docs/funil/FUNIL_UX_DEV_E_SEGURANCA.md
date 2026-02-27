# Funil de vendas — UX, anti-duplicidade, modo DEV e segurança

Este documento descreve as melhorias implementadas e como usá-las.

---

## 1. Melhorias de UX (funnel_offer.php)

- **Botão primário:** "🔓 Sim, quero desbloquear agora — R$ X,XX"
- **Botão secundário (upsell):** "Não agora, continuar para meu acesso"
- **Botão secundário (downsell):** "Não, ir para meu acesso"
- **Microcopy (só no upsell), abaixo do botão secundário:** "Sem problemas — você ainda vai receber seu acesso normalmente."
- **Escassez, perto do preço:** "🔥 Oferta disponível somente nesta página"

Nenhuma lógica nem layout estrutural foi alterado; apenas textos e microcopy.

---

## 2. Proteção anti-duplicidade (feature flag)

- **Variável:** `FUNNEL_PREVENT_DUPLICATE` no `.env`
- **Valores:** `true` ou `false` (padrão: `false`)

Quando `true`, antes de exibir a oferta (upsell ou downsell) o sistema verifica se o **mesmo comprador** já possui uma **venda aprovada** do **produto da oferta** (não do produto principal).  
Identificação do comprador: preferência por `comprador_email`; se não houver, uso de `comprador_nome`.  
Se já tiver comprado aquele produto de oferta, a página não exibe a oferta e segue para o próximo passo (downsell ou obrigado).

**Ativar no .env:**
```env
FUNNEL_PREVENT_DUPLICATE=true
```

---

## 3. Modo DEV (teste sem produção)

### 3.1 Variáveis no .env

```env
FUNNEL_DEV_MODE=true
FUNNEL_DEV_TOKEN=sua-string-secreta-aqui
```

- **FUNNEL_DEV_MODE:** `true` para ativar modo dev; `false` ou ausente = desligado (padrão seguro).
- **FUNNEL_DEV_TOKEN:** string secreta obrigatória. Sem token correto na URL, o modo dev não é ativado e o simulador retorna 403.

**Importante:** em produção deixe `FUNNEL_DEV_MODE=false` ou não defina. Não coloque o token em HTML nem em logs.

### 3.2 Uso da página de oferta em modo dev

Com modo dev ativo, você pode passar na URL:

- `dev=1&token=SUA_STRING_SECRETA`

Exemplo (após ter um `payment_id` válido, por exemplo gerado pelo simulador):

```
/funnel_offer.php?payment_id=DEV-xxxxx&step=upsell&dev=1&token=SUA_STRING_SECRETA
```

**Auto-step (opcional):** adicione `&autostep=1` para simular o clique no botão primário após 1,2 s:

```
/funnel_offer.php?payment_id=DEV-xxxxx&step=upsell&dev=1&token=SUA_STRING_SECRETA&autostep=1
```

Se o JavaScript estiver desabilitado, o botão continua clicável normalmente.

### 3.3 Modo teste (test=1) — Etapa 6

Com modo dev ativo (`dev=1&token=...`), você pode adicionar **`test=1`** na URL da página de oferta. Nesse caso, a página **não exige** que a venda esteja com status aprovado para exibir o funil. Isso permite testar a tela de oferta mesmo com vendas pendentes ou em outros status (útil para debug).

Exemplo:
```
/funnel_offer.php?payment_id=QUALQUER_ID&step=upsell&dev=1&token=SUA_STRING_SECRETA&test=1
```

**Recomendação:** Para fluxo completo (aceitar oferta e ir ao checkout), use o **simulador** (3.4), que cria uma venda já aprovada. O `test=1` serve para visualizar a oferta sem exigir aprovação.

### 3.4 Simulador: criar venda de teste e abrir a oferta

**Arquivo:** `dev_funnel_simulator.php` (na raiz do projeto)

**Pré-requisitos:** `FUNNEL_DEV_MODE=true` e `FUNNEL_DEV_TOKEN` definido no `.env`.

**URL de uso:**

```
https://seusite.com/dev_funnel_simulator.php?main_product_id=67&token=SUA_STRING_SECRETA
```

- **main_product_id:** ID do produto principal que tem funil configurado (obrigatório).
- **token:** mesmo valor de `FUNNEL_DEV_TOKEN` (obrigatório).
- **autostep (opcional):** `&autostep=1` para redirecionar já com autostep na página de oferta.

**Exemplo com autostep:**

```
https://seusite.com/dev_funnel_simulator.php?main_product_id=67&token=SUA_STRING_SECRETA&autostep=1
```

**Comportamento:**

1. Valida modo dev e token.
2. Cria um registro de venda **fake** na tabela `vendas` (status aprovado, `transacao_id` no formato `DEV-` + uniqid, comprador de teste).
3. Redireciona para `funnel_offer.php?payment_id=...&step=upsell&dev=1&token=...` (e `autostep=1` se informado).

Se a tabela `vendas` ou colunas esperadas não existirem, o simulador exibe mensagem de erro e não redireciona.

---

## 4. Segurança

- Modo dev **nunca** é ativado sem `token` na URL igual a `FUNNEL_DEV_TOKEN` (comparação com `hash_equals`).
- `payment_id` e `step` são sanitizados (caracteres permitidos e tamanho limitado).
- Flags têm padrão seguro: `FUNNEL_PREVENT_DUPLICATE=false`, `FUNNEL_DEV_MODE=false`, `FUNNEL_DEV_TOKEN` vazio se não definido.
- O token **não** é impresso em HTML (apenas usado na comparação e, no simulador, na URL de redirect para a página de oferta em dev).

---

## 5. Arquivos alterados / criados

| Arquivo | Descrição |
|---------|-----------|
| `config/funnel_config.php` | **Novo.** Lê env e define constantes do funil (flags e token). |
| `funnel_offer.php` | UX (textos, escassez, microcopy), sanitização GET, anti-duplicidade, modo dev + autostep. |
| `dev_funnel_simulator.php` | **Novo.** Simulador de venda para teste do funil (requer modo dev + token). |
| `docs/FUNIL_UX_DEV_E_SEGURANCA.md` | Este documento. |
| `funnel_action.php` | Etapa 3: processa accept/decline e redireciona para checkout com prefill ou próximo step. |
| `checkout.php` | Etapa 4: prefill (nome, email, telefone, CPF) via `prefill_token` e envia `funnel_main_payment_id` e `funnel_step` no pagamento. |
| `helpers/funnel_helper.php` | Etapa 5: função `build_funnel_redirect_after_offer_payment` para redirect pós-pagamento do funil. |
| `process_payment.php` / `api/process_payment.php` | Etapa 5: usam funnel_main e funnel_step para redirecionar para downsell ou obrigado. |

---

## 6. Resumo rápido

1. **Ativar modo DEV (só em ambiente de teste):** no `.env`: `FUNNEL_DEV_MODE=true` e `FUNNEL_DEV_TOKEN=sua-string-secreta`.
2. **Simulador:** abrir `dev_funnel_simulator.php?main_product_id=ID_DO_PRODUTO&token=SUA_STRING_SECRETA` (opcional: `&autostep=1`).
3. **Anti-duplicidade (opcional):** no `.env`: `FUNNEL_PREVENT_DUPLICATE=true`.
