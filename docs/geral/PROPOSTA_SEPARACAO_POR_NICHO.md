# Proposta: Separação por Nicho (Vitrine Segmentada)

## 1. Diagnóstico de dificuldade: **MÉDIA**

| Fator | Avaliação |
|-------|-----------|
| Estrutura existente | Já existe `communities` (subdomínio) e `produtos.community_id`; nicho é conceito distinto (acesso por usuário) |
| Pontos de listagem | Vários arquivos com queries diretas em `produtos`; sem camada repository/service centralizada |
| Entitlement atual | `alunos_acessos` + `product_exclusive_offers`; precisa combinar com filtro de nicho |
| Vitrine para cliente | Não existe página dedicada de “catálogo para comprar”; ofertas vêm de `get_member_exclusive_offers` e área de membros |

**Por que média:** Não há repository centralizado; é preciso injetar o filtro em vários pontos. Por outro lado, o padrão `getCommunityFilter()` já existe e pode ser espelhado para nicho.

---

## 2. Auditoria

### 2.1 Onde produtos são listados

| Local | Arquivo | Contexto | Quem vê |
|-------|---------|----------|---------|
| Meus Produtos | `views/produtos.php` | Feed (products_feed_items + produtos) | Infoprodutor |
| Ofertas exclusivas | `api/api.php` → `get_member_exclusive_offers` | Produtos ofertados com base em produtos possuídos | Cliente |
| Área de membros | `views/member/member_area_dashboard.php` | Cursos/produtos via alunos_acessos | Cliente |
| Checkout | `checkout.php` | Produto por `checkout_hash` | Qualquer |
| Infoprodutor member offers | `views/infoprodutor_member_offers.php` | Lista de produtos para oferta | Infoprodutor |
| Vendas (dropdown) | `views/vendas.php` | Produtos do infoprodutor | Infoprodutor |

### 2.2 Associação usuário ↔ permissões hoje

| Tipo usuário | Associação | Controle de acesso |
|--------------|------------|--------------------|
| admin | `tipo = 'admin'` | Vê tudo |
| infoprodutor | `tipo = 'infoprodutor'`, `usuario_id` em produtos | Vê seus produtos |
| cliente (usuario) | `tipo = 'usuario'`, `alunos_acessos` por email | Vê produtos com acesso concedido |

Não existe hoje: `usuarios.nicho_id` ou vínculo usuário → nicho.

### 2.3 Ponto ideal para filtro de nicho

- Centralizar em um helper `getNichoFilter($table, $user_id = null)` no `helpers/nicho_helper.php`.
- Chamar onde a listagem é para cliente: `member_area_dashboard`, `get_member_exclusive_offers`, vitrine (nova).
- Admin: sem filtro; infoprodutor: filtro opcional por nicho (quando “Meus Produtos” for nichado).

---

## 3. Modelagem mínima

### 3.1 Reaproveitamento

- **produtos**: Já existe `community_id` (subdomínio). Nicho é outro conceito → adicionar `nicho_id`.
- **communities**: Manter para subdomínio/tema; não usar como nicho.
- **usuarios**: Não há `nicho_id` → adicionar.

### 3.2 Schema proposto

```
nichos (nova)
├── id INT PK
├── slug VARCHAR(64) UNIQUE  -- ex: marketing, financeiro, saude, educacao
├── nome VARCHAR(255)
├── descricao TEXT
└── sort_order INT DEFAULT 0

categorias (nova) — subcategorias dentro do nicho
├── id INT PK
├── nicho_id INT FK → nichos
├── nome VARCHAR(255)
├── slug VARCHAR(64)
└── sort_order INT DEFAULT 0

produtos (alterar)
└── nicho_id INT NULL FK → nichos  -- NULL = sem nicho (ocultar por padrão)

produto_categorias (nova) — N:N
├── produto_id INT FK → produtos
└── categoria_id INT FK → categorias
    PRIMARY KEY (produto_id, categoria_id)

usuarios (alterar)
└── nicho_id INT NULL FK → nichos  -- Cliente: nicho permitido; admin/infoprodutor: NULL
```

### 3.3 Migration SQL incremental e reversível

```sql
-- migrations/nicho_separation.sql
-- ============================================================
-- Separação por Nicho — implementação incremental
-- Reversível: ver seção DOWN no final
-- ============================================================

-- 1. Tabela nichos
CREATE TABLE IF NOT EXISTS `nichos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela categorias (por nicho)
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nicho_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_categorias_nicho` (`nicho_id`),
  CONSTRAINT `fk_categorias_nicho` FOREIGN KEY (`nicho_id`) REFERENCES `nichos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Pivot produto_categorias
CREATE TABLE IF NOT EXISTS `produto_categorias` (
  `produto_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  PRIMARY KEY (`produto_id`, `categoria_id`),
  KEY `fk_pc_categoria` (`categoria_id`),
  CONSTRAINT `fk_pc_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Coluna nicho_id em produtos
ALTER TABLE `produtos`
  ADD COLUMN `nicho_id` int(11) NULL DEFAULT NULL COMMENT 'Nicho do produto (NULL = oculto em vitrine por padrão)' AFTER `community_id`,
  ADD KEY `idx_produtos_nicho` (`nicho_id`);

-- 5. Coluna nicho_id em usuarios
ALTER TABLE `usuarios`
  ADD COLUMN `nicho_id` int(11) NULL DEFAULT NULL COMMENT 'Nicho permitido para cliente (NULL = admin/infoprodutor)' AFTER `tipo`,
  ADD KEY `idx_usuarios_nicho` (`nicho_id`);

-- Seed: 4 nichos exemplo
INSERT INTO `nichos` (`slug`, `nome`, `descricao`, `sort_order`) VALUES
('marketing', 'Marketing Digital', 'Produtos de marketing e vendas online', 1),
('financeiro', 'Financeiro', 'Educação financeira e investimentos', 2),
('saude', 'Saúde e Bem-estar', 'Produtos de saúde e autocuidado', 3),
('educacao', 'Educação', 'Cursos e formação', 4)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- ============================================================
-- DOWN (reversão manual)
-- ============================================================
-- ALTER TABLE produtos DROP FOREIGN KEY fk_produtos_nicho, DROP COLUMN nicho_id;
-- ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_nicho, DROP COLUMN nicho_id;
-- DROP TABLE IF EXISTS produto_categorias;
-- DROP TABLE IF EXISTS categorias;
-- DROP TABLE IF EXISTS nichos;
```

---

## 4. Regras de negócio

| Regra | Implementação |
|-------|---------------|
| Ver vitrine | Apenas produtos com `nicho_id IN (nichos_permitidos_do_usuario)` |
| Acessar produto (checkout/detalhe) | Nicho + entitlement (compra/liberação) como já existe |
| Admin | Ignora filtro de nicho |
| Infoprodutor | Em “Meus Produtos”: opcionalmente filtra por nicho; admin pode filtrar |
| Produtos sem `nicho_id` | Ocultar na vitrine por padrão; em “Meus Produtos” do dono, mostrar |

---

## 5. Lista de arquivos a alterar

### Commit 1 — Migrations
- `migrations/nicho_separation.sql` (criar)

### Commit 2 — Filtro de nicho nas queries
- `helpers/nicho_helper.php` (criar) — `getNichoFilter()`, `getUserNichoIds()`, `canUserSeeProduct()`
- `api/api.php` — `get_member_exclusive_offers`: adicionar `AND p_offer.nicho_id IN (...)` e `AND p_source.nicho_id IN (...)`
- `views/member/member_area_dashboard.php` — join/filtro por nicho nas queries de `alunos_acessos` + feed
- `views/infoprodutor_member_offers.php` — filtro por nicho na lista de produtos candidatos (opcional, se infoprodutor tiver nicho)

### Commit 3 — UI vitrine nichada
- `views/member/vitrine_nichada.php` ou `views/vitrine.php` (criar) — home do nicho
- `config/config.php` ou roteador — rota `/vitrine` ou `/member_area_dashboard?vitrine=1`
- `index.php` ou `member_area_dashboard.php` — incluir vitrine como aba/seção ou página separada
- Partial: `views/includes/vitrine_header.php` — header do nicho
- Partial: `views/includes/vitrine_filters.php` — categoria, busca, ordenação
- Partial: `views/includes/vitrine_product_card.php` — card com badges (novo, mais vendido, bônus)
- `api/api.php` — ação `get_vitrine_produtos` (listagem paginada com filtros)

### Commit 4 — Proteção backend
- `checkout.php` — antes de exibir: validar `canUserSeeProduct($user_id, $produto)` (se logado)
- `api/api.php` — em endpoints que retornam produto: validar nicho
- `views/member/member_course_view.php` — validar nicho ao acessar curso
- Criar `helpers/nicho_helper.php` → `canUserSeeProduct($user_id, $produto)`

---

## 6. Patch sugerido (Commit 2 — listagem + detalhe)

### 6.1 Criar `helpers/nicho_helper.php`

Carregar em `config/config.php` após `community_helper.php`:
```php
require_once __DIR__ . '/../helpers/nicho_helper.php';
```

```php
<?php
/**
 * Nicho Helper — Separação por nicho na vitrine
 */
if (!function_exists('nichoFeatureEnabled')) {
    function nichoFeatureEnabled() {
        static $enabled = null;
        if ($enabled !== null) return $enabled;
        global $pdo;
        try {
            $chk = $pdo->query("SHOW TABLES LIKE 'nichos'");
            if (!$chk || $chk->rowCount() === 0) { $enabled = false; return false; }
            $chk2 = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'nicho_id'");
            $enabled = $chk2 && $chk2->rowCount() > 0;
        } catch (Exception $e) { $enabled = false; }
        return $enabled;
    }
}

if (!function_exists('getUserNichoIds')) {
    function getUserNichoIds($user_id = null) {
        if (!nichoFeatureEnabled()) return null; // antes da migration: sem filtro
        global $pdo;
        if ($user_id === null) $user_id = $_SESSION['id'] ?? null;
        if (!$user_id) return [];
        if (($_SESSION['tipo'] ?? '') === 'admin') return null;
        try {
            $stmt = $pdo->prepare("SELECT nicho_id FROM usuarios WHERE id = ? AND nicho_id IS NOT NULL");
            $stmt->execute([(int)$user_id]);
            $nicho_id = $stmt->fetchColumn();
            return $nicho_id ? [(int)$nicho_id] : [];
        } catch (PDOException $e) {
            return [];
        }
    }
}

/** Retorna [sql_fragment, params] para um único alias. Ex: getNichoFilter('p') -> [" AND p.nicho_id IN (?)", [1]] */
if (!function_exists('getNichoFilter')) {
    function getNichoFilter($table_alias = '') {
        $ids = getUserNichoIds();
        if ($ids === null) return ['', []];
        if (empty($ids)) return [' AND 1=0', []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $col = $table_alias ? $table_alias . '.nicho_id' : 'nicho_id';
        return [" AND $col IN ($ph)", $ids];
    }
}

/** Para múltiplos aliases (ex: p_offer e p_source). Retorna fragmento combinado. */
if (!function_exists('getNichoFilterMulti')) {
    function getNichoFilterMulti(array $aliases) {
        $ids = getUserNichoIds();
        if ($ids === null) return ['', []];
        if (empty($ids)) return [' AND 1=0', []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $parts = [];
        $params = [];
        foreach ($aliases as $a) {
            $col = $a ? $a . '.nicho_id' : 'nicho_id';
            $parts[] = "$col IN ($ph)";
            $params = array_merge($params, $ids);
        }
        return [' AND ' . implode(' AND ', $parts), $params];
    }
}

if (!function_exists('canUserSeeProduct')) {
    function canUserSeeProduct($user_id, $produto) {
        $nicho_ids = getUserNichoIds($user_id);
        if ($nicho_ids === null) return true;
        $prod_nicho = (int)($produto['nicho_id'] ?? 0);
        if ($prod_nicho === 0) return false;
        return in_array($prod_nicho, $nicho_ids);
    }
}
```

### 6.2 Alteração em `api/api.php` — `get_member_exclusive_offers`

Adicionar no topo do bloco `if ($action == 'get_member_exclusive_offers')` (após require/config):

```php
require_once __DIR__ . '/../helpers/nicho_helper.php';
```

Antes da query de ofertas (aprox. linha 1430):

```php
[$nicho_filter, $nicho_params] = getNichoFilterMulti(['p_offer', 'p_source']);
```

Na SQL de ofertas, incluir `$nicho_filter` no WHERE e `$nicho_params` em `$params_offers`:

```php
$params_offers = array_merge($owned_product_ids, $owned_product_ids, $nicho_params);
// Na cláusula WHERE, após: AND p_offer.id NOT IN ({$owned_product_ids_placeholder})
// adicionar: {$nicho_filter}
```

### 6.3 Alteração em `checkout.php` — validação de nicho

Após obter `$produto` (linha ~35), antes de exibir a página:

```php
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] && ($_SESSION['tipo'] ?? '') === 'usuario') {
    if (function_exists('canUserSeeProduct') && !canUserSeeProduct($_SESSION['id'], $produto)) {
        header('Location: /login?error=nicho');
        exit;
    }
}
```

---

## 7. UI — Layout sugerido da vitrine nichada

### 7.1 Estrutura

```
+--------------------------------------------------+
| Header do Nicho (nome, descrição curta)          |
+--------------------------------------------------+
| [Categoria ▼] [Busca...] [Ordenar: Relevância ▼] |
+--------------------------------------------------+
| +--------+  +--------+  +--------+  +--------+   |
| | Card   |  | Card   |  | Card   |  | Card   |   |
| | [NOVO] |  |[VENDIDO|  | [BÔNUS]|  |        |   |
| +--------+  +--------+  +--------+  +--------+   |
| ... (grid responsivo)                            |
+--------------------------------------------------+
| [Paginação]                                      |
+--------------------------------------------------+
```

### 7.2 Partials sugeridos

| Partial | Descrição |
|---------|-----------|
| `vitrine_header.php` | Recebe `$nicho` (row de nichos); exibe nome e descrição |
| `vitrine_filters.php` | Form com categoria, input busca, select ordenação |
| `vitrine_product_card.php` | Card com foto, nome, preço, badges (novo, mais vendido, bônus) |

### 7.3 Badges

- **Novo**: `data_criacao` nos últimos 30 dias
- **Mais vendido**: contar em `vendas` WHERE `status_pagamento='approved'` e `produto_id`
- **Bônus**: campo em produto (ex. `is_bonus` ou tag em `checkout_config`)

---

## 8. Testes recomendados

| Cenário | Resultado esperado |
|---------|--------------------|
| Usuário nicho 1 em listagem | Só produtos com nicho_id=1 |
| Usuário nicho 1 em busca | Só produtos nicho 1 |
| Usuário nicho 1 acessa URL checkout de produto nicho 2 | 403 ou redirect /login?error=nicho |
| Produto sem nicho_id | Oculto na vitrine; visível para dono em “Meus Produtos” |
| Admin | Vê todos os produtos |
| Índices | `idx_produtos_nicho`, `idx_usuarios_nicho` presentes |

---

## 9. Considerações de performance

- Índices em `produtos.nicho_id` e `usuarios.nicho_id`
- Em `get_member_exclusive_offers`, o filtro de nicho é feito no JOIN; avaliar EXPLAIN
- Se a vitrine tiver muitos produtos, usar paginação (ex. 24 por página)
