# Multi‑comunidade / multi‑projeto – visão geral

Este documento resume a lógica atual de **multi‑comunidade** (multi‑tenant) usada no projeto.

---

## 1. Conceito geral

- A mesma base de código e o mesmo banco de dados podem atender **N projetos** (comunidades).
- Cada comunidade é identificada por um **`community_id`** e, em alguns casos, por um **slug** (subdomínio).
- As principais estratégias são:
  - Resolver `community_id` a partir do **host** (subdomínio) – helper `community_helper.php`.
  - Manter colunas `community_id` em tabelas onde os dados são “por projeto”.
  - Em alguns pontos, **não filtrar por `community_id`** de propósito, para permitir compartilhamento.

---

## 2. Resolução de `community_id` (helper `helpers/community_helper.php`)

Arquivo: `helpers/community_helper.php`

- Função principal: **`getCommunityContext()`**
  - Lê `$_SERVER['HTTP_HOST']`.
  - Extrai o **subdomínio** (primeiro label).
  - Tabela alvo: **`communities`**:
    - `slug` – mapeado a partir do subdomínio.
    - `id` – vira o `community_id`.
  - Se não encontrar, usa um **fallback**:
    - `slug` padrão: `'club'`.
    - `community_id` padrão: **1** (ajustado se houver registro `slug = 'club'`).

- Atalho: **`getCommunityId()`**
  - Retorna apenas `getCommunityContext()['community_id']`.

- Função de compatibilidade: **`_table_has_community_column($table)`**
  - Verifica se a tabela possui coluna `community_id` (via `SHOW COLUMNS`), com cache por request.

- Filtro pronto: **`getCommunityFilter($table)`**
  - Se a tabela tiver `community_id`, retorna:
    - `[' AND community_id = ?', getCommunityId()]`
  - Caso contrário, retorna `['', null]` (sem filtro).

> Isso permite migrar tabelas gradualmente para `community_id` sem quebrar as consultas existentes.

---

## 3. Onde `community_id` é usado (exemplos)

### 3.1. API de vendas (exemplo em `api/api.php`)

Trecho (síntese) em torno de **L1200**:

- Ao registrar uma venda:
  - Determina o `community_id` default 1.
  - Se o produto tiver `community_id` definido, usa o do produto (consulta em `produtos`).
  - Insere em `vendas` com a coluna `community_id` preenchida:
    - `INSERT INTO vendas (produto_id, community_id, ...) VALUES (?, ?, ...)`.

Objetivo:
- A mesma tabela de `vendas` pode armazenar transações de múltiplas comunidades, filtradas por `community_id` em relatórios.

### 3.2. Cadastro de produtos (`views/produtos.php`)

- Em `produtos.php`:
  - Obtém o `community_id` atual com:
    - `$community_id = function_exists('getCommunityId') ? getCommunityId() : 1;`
  - Na criação de produtos, inclui `community_id`:
    - `INSERT INTO produtos (..., community_id) VALUES (..., ?, ...)`.
  - Em consultas que suportam multi‑comunidade:
    - Usa condições do tipo:
      - `AND (community_id IS NULL OR community_id = ?)`
    - Permitindo compatibilidade com registros antigos sem `community_id`.

### 3.3. Registro de membros / vitrine (`views/member/member_register.php`)

- Ao criar uma “venda” gratuita para registro:
  - Lê `community_id` do produto vitrine:
    - `$cid = isset($produto_vitrine['community_id']) ? (int)$produto_vitrine['community_id'] : 1;`
  - Insere em `vendas` com este `community_id`.

### 3.4. Área de membros / cursos (`views/member/member_area_dashboard.php`)

- Há comentários explícitos indicando **quando NÃO filtrar por `community_id`**:
  - Exemplo:
    - `// Não filtrar por community_id: o cliente deve ver todos os produtos a que tem acesso (alunos_acessos), inclusive Vitrine de outra comunidade.`
  - Objetivo:
    - Permitir que o aluno veja cursos/produtos que foram concedidos em outra comunidade (ex.: vitrine/cross sell).

### 3.5. Gerenciamento de cursos (`views/gerenciar_curso.php`)

- Comentário semelhante:
  - `// Não filtrar por community_id: o infoprodutor pode gerenciar qualquer produto seu (ex.: criado em club e acessado de core).`
- Em outros pontos da view, quando precisa criar registros novos relacionados ao curso/produto:
  - Usa `getCommunityId()` para preencher `community_id` nas tabelas associadas, quando elas têm essa coluna.

### 3.6. Member protection (`helpers/member_protection_helper.php`)

- Função **`isMemberProtectionEnabled($community_id = null)`**:
  - Se `$community_id` for `null`, tenta usar `getCommunityContext()`:
    - `$community_id = $ctx['community_id'] ?? 1;`
  - Lê um JSON de configuração (por comunidade) e decide se a proteção está ativa.

---

## 4. Regras práticas para novas features

Quando for adicionar ou alterar código:

1. **Consultas em tabelas “multi‑projeto”** (vendas, produtos, feeds, etc.):
   - Se a tabela tem `community_id`:
     - Filtrar por `community_id = getCommunityId()` **quando o dado for exclusivo da comunidade atual** (dashboards, relatórios internos, etc.).
   - Não filtrar por `community_id` nos casos documentados:
     - Quando o aluno/infoprodutor deve enxergar dados de outras comunidades (ex.: cursos concedidos de outra vitrine).

2. **Novas tabelas que sejam específicas de um projeto/comunidade**:
   - Incluir coluna `community_id` **desde o início**.
   - Usar `getCommunityId()` ao inserir.
   - Ao consultar, usar:
     - Diretamente `WHERE community_id = ?`, ou
     - Helper `getCommunityFilter('nome_tabela')` para manter compatibilidade.

3. **Tabelas realmente globais** (ex.: `configuracoes_sistema`, logs centrais, tabela de comunidades em si):
   - Não precisam de `community_id`.

4. **Evitar “hardcode” de `community_id`**:
   - Sempre que possível, usar `getCommunityId()` ou `getCommunityContext()` em vez de colocar valor fixo no código.
   - Exceto em pontos claramente marcados como fallback (ex.: configuração default, migração, scripts de instalação).

---

## 5. Pontos de atenção

- Documentação de regras especiais (onde **não** filtrar por `community_id`) deve ser mantida próxima ao código:
  - Comentários como os já existentes em `member_area_dashboard.php` e `gerenciar_curso.php` são importantes.
  - Este arquivo consolida a visão geral, mas não substitui os comentários locais.

- Antes de refatorar consultas em `api/api.php` ou views:
  - Verificar se a tabela em questão é multi‑comunidade.
  - Checar se já existe comportamento especial documentado para aquele fluxo.

---

*Este documento é um resumo do que o código já faz hoje. Qualquer mudança futura em multi‑comunidade deve manter estas regras ou atualizar este arquivo explicitamente.*
