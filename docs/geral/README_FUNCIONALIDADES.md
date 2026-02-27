# Funcionalidades e Fluxos do Sistema

Documentação das funcionalidades, módulos e fluxos do sistema GatewayPro/SinergIA (checkout próprio, área de membros, gestão de infoprodutos).

---

## 1. Módulos do Sistema

O sistema é dividido em três perfis de acesso:

### 1.1 Módulo Admin (Painel Administrativo)

- **Acesso:** `/admin` (após login com usuário `tipo = admin`).
- **Funções:**
  - **Dashboard:** visão geral da plataforma (métricas, usuários, vendas).
  - **Usuários:** criar e editar infoprodutores (login, senha, tipo).
  - **Configurações:** SMTP, template de e-mail de entrega, nome da plataforma, URL de login da área de membros.
  - **Configurações visuais:** cor primária, logo, favicon, imagem de fundo do login, tema (theme_json).
  - **Plugins:** ativar/desativar plugins (ex.: Modo SaaS).
  - **Relatórios e logs:** vendas, segurança (security_logs), auditoria.
  - **Revenda autorizada:** gestão de revendedores.
  - **Licenças (painel master):** gerar chaves de ativação (escopos SYSTEM, COMMUNITY, PRODUCT, USER_LIMIT; tipos vitalício, mensal, anual, semestral).

### 1.2 Módulo Infoprodutor (Painel do Infoprodutor)

- **Acesso:** `/index` com `?pagina=...` (após login com usuário `tipo = infoprodutor`). Páginas: dashboard, produtos, configuracoes, produto_config, vendas, area_membros, gerenciar_curso, profile, tracking, integracoes, integracoes_webhooks, integracoes_utmfy, integracoes_evolution, integracoes_api, clonar_site, planos, clientes, infoprodutor_member_offers.
- **Funções:**
  - **Dashboard:** vendas totais, quantidade de vendas, ticket médio, total de produtos.
  - **Produtos:** CRUD de produtos (link, PDF, área de membros); ordenação no feed (drag-and-drop).
  - **Configuração de produto:** abas geral, checkout, links, métodos de pagamento, order bumps, rastreamento (pixels); ofertas exclusivas (product_exclusive_offers).
  - **Área de membros:** por produto — cursos, módulos, aulas (vídeo YouTube/Vimeo, arquivos); ordenação de módulos e aulas.
  - **Vendas:** listagem, filtros, reenvio de e-mail de entrega (via API `/api/vendas_actions`).
  - **Clientes:** lista de compradores/alunos.
  - **Integrações:** gateways de pagamento (Mercado Pago, PushinPay, Efí, HyperCash, etc.), webhooks, UTMfy, Evolution API.
  - **Tracking:** produtos rastreados (gatewaypro_tracking_products) e eventos (gatewaypro_tracking_events).
  - **Banners:** CRUD de banners; exibição no feed e no dashboard do cliente; badges (banner_badges); ordenação no feed (drag-and-drop).
  - **Clonar site:** páginas clonadas com editor HTML (cloned_sites, cloned_site_settings).
  - **Planos (Modo SaaS):** planos Free/Premium, assinaturas (saas_assinaturas), limites de uso (saas_limites_uso).
  - **Perfil:** nome, e-mail, foto.

### 1.3 Módulo Cliente Final (Área de Membros)

- **Acesso:** `/member_login` → após login com `tipo = usuario` redireciona para `/member_area_dashboard`. Curso: `member_course_view.php` (por produto/curso).
- **Funções:**
  - **Login/registro:** tela de login e registro (member_login, member_register).
  - **Dashboard:** produtos adquiridos, ofertas exclusivas (product_exclusive_offers), banners do feed (products_feed_items com item_type = banner).
  - **Curso:** listagem de módulos e aulas; reprodução de vídeo (YouTube/Vimeo) ou download de arquivos; registro de progresso (aluno_progresso).
  - **Licenças:** tela "Minhas licenças" (member_licenses) quando habilitado — exibe licenças geradas associadas ao cliente.
  - **Proteção:** watermark, anti-print, anti-devtools (configurável por comunidade em configuracoes_sistema: PROTECT_MEMBER_AREA, PROTECT_MEMBER_AREA_BY_COMMUNITY).

---

## 2. Fluxo Produto → Curso → Módulos → Aulas

### 2.1 Modelo de Dados

- **produtos** (1) — um produto pode ter entrega tipo `area_membros`.  
- **cursos** (N por produto) — `curso.produto_id` → produto. Um produto tem um curso (na prática 1:1 para área de membros).  
- **modulos** (N por curso) — `modulo.curso_id` → curso. Ordem por `ordem` e `release_days`.  
- **aulas** (N por módulo) — `aula.modulo_id` → módulo. Ordem por `ordem` e `release_days`; tipos: vídeo (YouTube/Vimeo/URL/código), arquivos (aula_arquivos), misto.

### 2.2 Fluxo de Uso

1. **Infoprodutor:** em "Área de membros" (gerenciar_curso) cria/edita o curso do produto → adiciona módulos → adiciona aulas (título, URL do vídeo ou upload de arquivos, tipo_conteudo: video/files/mixed).
2. **Cliente:** após compra ou acesso concedido, em "Meus produtos" acessa o curso → vê módulos e aulas (respeitando release_days); ao concluir aula, o sistema registra em **aluno_progresso** (aluno_email, aula_id).
3. **Acesso:** **alunos_acessos** (aluno_email, produto_id, oferta_id, data_expiracao) define quem tem acesso a qual produto; a área de membros e o curso usam esse vínculo.

### 2.3 Ordenação

- Módulos e aulas têm campo `ordem`. A reordenação de aulas é feita via API (api.php, action relacionada a reordenar aulas), com persistência no banco.

---

## 3. Ofertas, Checkout e Gateway de Pagamento

### 3.1 Ofertas

- **produto_ofertas:** ofertas por produto (nome, preço, tipo_acesso: mensal/semestral/anual/vitalicio, hash). O link de checkout pode usar o hash da oferta.
- **order_bumps:** produto ofertado no checkout de outro produto (main_product_id, offer_product_id).
- **product_exclusive_offers:** ofertas exclusivas para quem já possui um produto (source_product_id, offer_product_id); exibidas no dashboard do cliente.

### 3.2 Checkout

- **Página:** `/checkout?p=<hash_do_produto>` (checkout_hash em produtos). Opcional: parâmetros de oferta.
- **Processamento:**  
  - Pagamento pago: `process_payment.php` (gateways de pagamento).  
  - Grátis/registro grátis: `process_free.php`.  
- **Vendas:** registradas em **vendas** (produto_id, oferta_id, valor, status_pagamento, comprador_*, transacao_id, metodo_pagamento, etc.).

### 3.3 Gateway de Pagamento

- O sistema utiliza **gateways de pagamento** (Mercado Pago, PushinPay, Efí, HyperCash, etc.) para Pix, cartão e boleto.
- Configuração por infoprodutor (usuarios: mp_public_key, mp_access_token, pushinpay_token, etc.) ou global (saas_config_admin quando Modo SaaS).
- Webhooks dos gateways notificam aprovação/pendência/rejeição; o sistema atualiza **vendas** e dispara e-mail de entrega (template em **configuracoes**).
- **Não se altera** nomenclatura de colunas/tabelas ligadas a pagamento (vendas, produto_ofertas, gateways) para não quebrar integrações.

### 3.4 Um banco, N áreas de membros (multi-tenant por subdomínio)

- **Objetivo:** um único banco de dados servindo várias “áreas” (ex.: core.sinergia.club, kids.sinergia.club), sem duplicar dados nem ter um banco por subdomínio.
- **Tabela `communities`:** define as comunidades (slug = subdomínio, name, theme_json, primary_color). Ex.: slug `club`, `kids`, `flow`, `core`.
- **Resolução do contexto:** `helpers/community_helper.php` usa `HTTP_HOST` → extrai o primeiro label (subdomínio) → busca em `communities.slug` → obtém `community_id`. Hosts desconhecidos usam fallback `slug = 'club'`.
- **Tabelas com `community_id`:** produtos, cursos, módulos, aulas, products_feed_items, vendas, configuracoes_sistema (NULL = global), banners, security_events, etc. As consultas filtram por `community_id` do request (via `getCommunityFilter()` / `getCommunityId()`).
- **Checkout:** em `/checkout?p=<hash>` o produto é buscado já com filtro de comunidade; a venda é registrada com o `community_id` do produto.
- **Área de membros:** em `member_area_dashboard` os “Meus produtos” são listados fazendo JOIN com `produtos` e filtrando `p.community_id = ?` (comunidade atual). O mesmo usuário em **kids.sinergia.club** vê só produtos da comunidade Kids; em **core** ou **club** vê só os daquela comunidade.
- **Painel do infoprodutor:** listagem de produtos, feed, gerenciar curso e checkout editor usam o mesmo filtro por comunidade conforme o subdomínio em que o infoprodutor está.
- **DNS:** cada subdomínio (club, kids, core, etc.) deve apontar para o mesmo servidor/aplicação; o PHP usa `$_SERVER['HTTP_HOST']` para decidir o contexto.
- **Resumo:** um banco, N subdomínios → N “áreas” isoladas por comunidade; sem retrabalho de manter vários bancos.

---

## 4. Banners no Feed e Ordenação Drag-and-Drop

### 4.1 Banners

- **banners:** por infoprodutor (usuario_id); título, badge_id (FK para banner_badges), image_path/image_url, click_url; exibição em grid de produtos, dashboard do cliente e/ou seção de ofertas (show_in_products_grid, show_in_member_dashboard, show_in_offers_section).
- **banner_badges:** catálogo de badges (slug, icon, label) usados no dropdown ao criar/editar banner.

### 4.2 Feed (Produtos + Banners)

- **products_feed_items:** define a ordem de exibição no feed do infoprodutor e no dashboard do cliente. Campos: usuario_id, item_type ('product' | 'banner'), item_id (id do produto ou do banner), sort_order.
- **Ordenação drag-and-drop:** na tela de produtos (views/produtos.php) o infoprodutor reordena itens do feed (produtos e banners). A API (api.php ou banners_api.php) recebe a nova ordem e atualiza **products_feed_items** (sort_order). Ao criar um novo produto, o trigger **after_produto_insert** insere automaticamente o produto no feed com sort_order = max + 1.

### 4.3 APIs Relacionadas

- **banners_api.php:** CRUD de banners; inclusão no feed; reordenação do feed (drag-and-drop) — atualiza sort_order em products_feed_items.

---

## 5. Licenças (Escopos, Tipos, Expiração)

### 5.1 Tabela e Conceitos

- **licencas_geradas:** chave_licenca, tipo_licenca, dias_validade, escopo, escopo_ref_id, status, owner_user_id, assigned_user_id, data_ativacao, data_expiracao, instalacao_id, etc.

### 5.2 Tipos de Licença

- **VITALICIO:** dias_validade = NULL, sem data de expiração.  
- **MENSAL:** 30 dias.  
- **ANUAL:** 365 dias.  
- **SEMESTRAL:** 180 dias.

### 5.3 Escopos

- **SYSTEM:** ativa o sistema como um todo (instalação).  
- **COMMUNITY:** por comunidade (community_id).  
- **PRODUCT:** por produto (escopo_ref_id = produto_id).  
- **USER_LIMIT:** limite de usuários (uso específico).

### 5.4 Status e Expiração

- Status: disponivel, ativa, ativada, expirada, bloqueada, revogada.  
- **data_expiracao:** preenchida conforme tipo (vitalício = NULL). O **license_service** e **license_api** tratam ativação e expiração; jobs/cron podem marcar licenças expiradas (status = expirada).

### 5.5 Onde São Usadas

- **Painel admin (painel master):** geração de chaves (admin_configuracoes, admin_api, license_service).  
- **Ativação da instalação:** ativacao.php e license_api (validação e ativação por chave).  
- **Cliente:** tela "Minhas licenças" (member_licenses, member_api) lista licenças associadas ao cliente.

### 5.6 Helpers/Serviços

- **helpers/license_service.php:** geração, ativação, validação; constantes LICENSE_TYPE_*, LICENSE_SCOPE_*, LICENSE_STATUS_*, LICENSE_DAYS_MAP.  
- **helpers/license_helper.php:** funções de alto nível para login/ativação.  
- **api/license_api.php:** endpoints de ativação/validação.  
- **api/admin_api.php** e **views/admin/admin_configuracoes.php:** UI para gerar e listar licenças (painel master).

---

## 6. Duplicidades e Organização (Relatório)

*Não refatorar agressivamente; apenas relatar e sugerir melhorias.*

### 6.1 Telas / Rotas

- **vendas_actions.php:** existe na **raiz** e em **api/**. As telas **views/vendas.php** e **vendas.php** (raiz) chamam **/api/vendas_actions**. Portanto o endpoint em uso é **api/vendas_actions.php**. O arquivo na raiz **vendas_actions.php** parece legado e não é referenciado pelas views; sugere-se marcar como obsoleto ou remover após confirmação em produção.
- **Arquivos na raiz vs views:** vários arquivos na raiz (admin_configuracoes.php, admin_dashboard.php, admin_usuarios.php, member_login.php, member_area_dashboard.php, vendas.php, etc.) funcionam como **entradas** que incluem ou redirecionam para arquivos em **views/**. Não é duplicação de lógica, mas vale manter documentado para não criar rotas duplicadas.

### 6.2 Helpers / Includes

- **master_helper.php:** incluído em vários pontos (admin_configuracoes, admin_api, member_api, member_licenses, produto_config/aba_geral, etc.). Centralizado em um único helper — ok.
- **license_helper.php** e **license_service.php:** o primeiro usa o segundo em fluxos de login/ativação; separação coerente (helper = uso geral, service = regras de negócio).
- **theme_helper.php:** incluído por load_settings.php e admin_api (e admin_visual_config); pode ser carregado uma vez no bootstrap do admin para evitar múltiplos includes — melhoria opcional.
- **acesso_helper.php** e **utmfy_helper.php:** usados em api/vendas_actions.php, api/process_payment.php, api/notification.php e no root vendas_actions.php. No root vendas_actions.php a lógica é duplicada em relação à api/; ao remover o root, manter apenas nos endpoints da api/.

### 6.3 Sugestões Resumidas

1. **Remover ou depreciar** o arquivo **vendas_actions.php** na raiz, mantendo apenas **api/vendas_actions.php** como endpoint de vendas (entrega, reenvio de e-mail).
2. **Documentar** na estrutura do projeto quais arquivos na raiz são apenas “entradas” (include/redirect) para views/ ou api/.
3. **Revisar** se todas as rotas de API passam por **api.php** (roteamento central) ou se há chamadas diretas a arquivos em api/ (ex.: /api/vendas_actions) — e manter consistente.
4. **Evitar** novos includes redundantes de theme_helper e load_settings; considerar um único ponto de carregamento para o painel admin.

---

*Documento gerado para o pacote de instalação e manutenção do sistema. Não alterar nomes de colunas/tabelas que impactem integrações de pagamento.*
