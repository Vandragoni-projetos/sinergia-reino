# Relatório ETAPA 1 — Rebranding Gateway Pro → SinergIA Core

**Data:** Levantamento (sem alterações)  
**Objetivo:** Mapear e classificar todas as ocorrências de "Gateway Pro" / "GatewayPro" / "Gateway" / "gatewaypro" / "gateway" para rebranding seguro para **SinergIA Core**.

---

## Recomendação adotada: mudar somente o que fica visível

**Alterar:**
- **A) Branding visual** — títulos, meta tags, textos de UI, e-mails (assunto/corpo/remetente padrão), README e comentários de apresentação.
- **B) Assets** — URLs padrão de logo/favicon (ou usar sempre o valor do banco/config).
- **C) Apenas defaults visíveis** — valor default de `nome_plataforma` e `logo_url` (ex.: "SinergIA Core" e novo logo); comentários em `.env.example`. **Não** renomear chaves (`GATEWAYPRO_MASTER_SECRET`, `nome_plataforma`, etc.).

**Não alterar (manter como está):**
- **D) Lógica / integrações** — tabelas, colunas, rotas, ações de API, variáveis de ambiente usadas no código, prefixo de licença, headers HTTP, localStorage, classes/IDs de tracking. Nada que possa quebrar rotas, integrações ou banco.

Assim o usuário vê apenas "SinergIA Core" na interface, e-mails e documentação, sem risco de quebra.

---

## Resumo por categoria

| Categoria | Descrição | Arquivos | Risco geral |
|-----------|-----------|----------|-------------|
| **A** | Branding visual (UI, meta, mensagens, e-mails, README) | 18 | Baixo |
| **B** | Assets (logos, favicon, URLs de imagem) | 12 | Médio |
| **C** | Configurações (nome_plataforma, env, defaults) | 10 | Médio |
| **D** | Lógica / integrações (não alterar automaticamente) | 15+ | Alto |

**Importante:** Todas as ocorrências da palavra "gateway" no sentido de **gateway de pagamento** (coluna `gateway`, variáveis `$gateway`, `gateway_choice`, rotas `/gateways/`, etc.) foram **excluídas** do rebranding — são D e não devem ser renomeadas.

---

## A) BRANDING VISUAL — Seguro para trocar

Textos de UI, meta tags, mensagens, e-mails, README e comentários de apresentação. Troca segura por "SinergIA Core" (ou variante definida).

### A.1 — Títulos e meta tags (UI)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `views/member/member_area_dashboard.php` | 219 | `<title>Meus Cursos - Área de Membros GatewayPro</title>` | Baixo |
| `views/member/member_area_dashboard.php` | 258 | `alt="GatewayPro Logo"` | Baixo |
| `views/member/member_licenses.php` | 38 | `<title>Minhas Licenças - GatewayPro</title>` | Baixo |
| `views/member/member_licenses.php` | 90 | Texto: "Gere licenças de ativação para usar o GatewayPro em suas instalações." | Baixo |

### A.2 — E-mails (assunto, corpo, remetente padrão)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `api/notification.php` | 193 | `$config['smtp_from_name'] ?? 'GatewayPro'` — nome do remetente padrão | Baixo |
| `api/admin_api.php` | 75 | `'from_name' => $input_data['smtp_from_name'] ?? 'GatewayPro'` | Baixo |
| `api/admin_api.php` | 878 | `$mail->Subject = 'Email de Teste GatewayPro SMTP'` | Baixo |
| `api/admin_api.php` | 880-881 | Corpo e AltBody do e-mail de teste: "plataforma GatewayPro" | Baixo |
| `api/api.php` | 171 | `'from_name' => $smtp_configs_raw['smtp_from_name'] ?? 'GatewayPro'` | Baixo |
| `vendas_actions.php` | 89 | `$conf['smtp_from_name'] ?? 'GatewayPro'` | Baixo |
| `api/vendas_actions.php` | 144 | `$conf['smtp_from_name'] ?? 'GatewayPro'` | Baixo |
| `api/notifications_api.php` | 503 | `'from_name' => $smtp_configs_raw['smtp_from_name'] ?? 'GatewayPro'` | Baixo |
| `views/admin/admin_smtp_config.php` | 229 | `data.smtp_from_name \|\| 'GatewayPro'` (valor padrão no JS) | Baixo |

### A.3 — Comentários e documentação (apresentação / README)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `api/admin_api.php` | 25 | Comentário: "pasta 'PHPMailer' está diretamente em 'GatewayPro 10000/'" | Baixo |
| `api/admin_api.php` | 616-618, 658-675 | Comentários sobre substituição "GatewayPro" no copyright; regex que procura "GatewayPro" no HTML para trocar por nome dinâmico — **só trocar comentários e textos fixos nos regex se houver fallback com nome_plataforma** | Baixo (comentários) / Médio (regex: ver C) |
| `DIAGNOSTICO_AMBIENTE_NAO_DOCKER.md` | 1 | Título: "# Diagnóstico GatewayPro – Ambiente fora do Docker" | Baixo |
| `docs/DOC_LICENCAS_PROPOSTA.md` | 40 | Texto: "Gateway Pro white-label" | Baixo |
| `helpers/community_helper.php` | 7, 25 | Comentários: "gatewaypro1.vitrineacademy.com.br" (exemplo de host) | Baixo |
| `.htaccess` | 2-3 | Comentários: "subdiretório (ex.: public_html/gatewaypro/)", "RewriteBase /gatewaypro1/" | Baixo (só comentários; RewriteBase é C) |

### A.4 — Páginas de conteúdo (jornada, textos visíveis)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `jornada_starfy.php` | 14, 17, 22, 50, 62-71, 78, 88, 92, 96, 100, 103-112, 119, 130, 151, 164, 195, 233 | Títulos e textos: "Sua Jornada GatewayPro", "Seu Caminho Estelar no GatewayPro", "universo GatewayPro", descrições das etapas, IDs/classes JS `jornada-GatewayPro-container`, `GatewayPro_STAGES`, `fetchJornadaGatewayProData`, `renderGatewayProJourney`, etc. — **textos e labels = A; IDs/names de ação API e variáveis JS = D** | Baixo (textos) / Alto (nomes de ação API e constantes JS se alterados sem alterar backend) |

### A.5 — Integrações (textos de UI, não chaves)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `views/integracoes.php` | 366-367, 377, 391, 474 | Títulos de card "Gateways de Pagamento" e "Configurar Gateways" — **sentido "gateway de pagamento", NÃO trocar por marca** | N/A (manter "Gateways") |
| `views/integracoes.php` | 814, 897, 1074, 1120 | Frases "Este gateway processa..." — gateway de pagamento, **não trocar** | N/A |

---

## B) ASSETS — Trocar com cuidado

Logos, favicon, URLs de imagem padrão. Trocar para assets de SinergIA Core e garantir que `nome_plataforma` / `logo_url` no banco substituam onde for possível.

### B.1 — URL padrão do logo (CDN)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `index.php` | 138, 604, 981 | `apple-touch-icon` e imagem de notificação: `https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png` | Médio (substituir por URL do novo logo ou por variável dinâmica) |
| `views/member/member_area_dashboard.php` | 38 | `$logo_url = 'https://cdn.jsdelivr.net/gh/.../logo-gatewaypro.png'` (fallback antes de buscar no banco) | Médio |
| `admin.php` | 35 | Mesmo fallback `logo-gatewaypro.png` | Médio |
| `api/admin_api.php` | 528, 622, 910, 919 | `getSystemSetting('logo_url', 'https://.../logo-gatewaypro.png')` e fallback normalizado | Médio |
| `config/load_settings.php` | 69, 81 | Fallback `logo-gatewaypro.png` | Médio |
| `api/generate_legal_page.php` | 35 | `getSystemSetting('logo_url', '...logo-gatewaypro.png')` | Médio |

**Recomendação:** Ter um único default (ex.: em `load_settings.php` ou config) e usar `nome_plataforma`/`logo_url` do banco em todos os pontos; trocar a URL padrão apenas onde for literal (B).

---

## C) CONFIGURAÇÕES — Trocar com cuidado

Defaults de nome da plataforma, variáveis de ambiente “de nome”, e comentários de configuração. Não alterar nomes de variáveis que sejam chaves técnicas (ex.: `GATEWAYPRO_MASTER_SECRET` = D).

### C.1 — Nome da plataforma (default)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `api/admin_api.php` | 523, 603, 618, 984 | `getSystemSetting('nome_plataforma', 'GatewayPro')` — default quando não há valor no banco | Médio |
| `config/load_settings.php` | 72 | `$nome_plataforma = getSystemSetting('nome_plataforma', 'GatewayPro')` | Médio |
| `api/generate_legal_page.php` | 36 | `getSystemSetting('nome_plataforma', 'GatewayPro')` | Médio |

Trocar o **segundo argumento** (default) para `'SinergIA Core'`; não alterar a chave `nome_plataforma`.

### C.2 — Variáveis de ambiente e config (apenas “nome” ou comentário)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `.env.example` | 1 | Comentário: "# GatewayPro - Variáveis de ambiente" | Baixo |
| `.env.example` | 6, 8 | `DB_USER=...`, `DB_NAME=...` — **valores de exemplo (u527060234_gatewaypro1)** — se forem só exemplo, trocar para algo como `sinergia_core`; se forem credenciais reais de referência, avaliar | Médio |
| `.env.example` | 17 | `GATEWAYPRO_MASTER_SECRET=` — **nome da variável é usado em código (D)**; só o comentário ao lado pode ser “SinergIA” se quiser | Alto (não renomear a chave sem alterar todo o código que a lê) |
| `config/config.php` | 6-7 | `env('DB_USER', 'gatewaypro')`, `env('DB_PASS', 'gatewaypro_secret_2024')` — **defaults de exemplo**; trocar apenas os valores default se desejado; não alterar `DB_USER`/`DB_PASS` | Médio |
| `config/config.docker.php` | 9 | `define('DB_PASS', 'gatewaypro_secret')` — default Docker | Médio |
| `docker/entrypoint.sh` | 6 | `DB_PASSWORD="${DB_PASSWORD:-gatewaypro_secret_2024}"` — default | Médio |

### C.3 — Regex / padrões que substituem “GatewayPro” no HTML

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `api/admin_api.php` | 662, 668, 674 | Regex que buscam "GatewayPro" no copyright para substituir pelo `nome_plataforma` — se usuários já tiverem "SinergIA Core" no banco, não precisa do regex com "GatewayPro"; se quiser compatibilidade com templates antigos, manter regex com "GatewayPro" **ou** adicionar outro com "SinergIA Core" | Médio |

---

## D) LÓGICA / INTEGRAÇÕES — NÃO alterar automaticamente

Nomes de tabelas/colunas, rotas, chaves de API, assinaturas de licença, headers HTTP e variáveis de ambiente usadas como identificadores. Alterar exige migração, mudança de integrações e testes.

### D.1 — Banco de dados (tabelas)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `Banco_de_Dados.sql` | 332-350, 944-956, 1185-1193, 1351-1359 | Tabelas `gatewaypro_tracking_events`, `gatewaypro_tracking_products` (CREATE, ALTER, FK) | **Alto** — renomear exige migration + alterar todo código que referencia |
| `api/api.php` | 1506, 1513, 1545, 1568, 1582, 1617, 1847, 1866, 1906, 1923, 1957, 1971, 1990, 2017, 2117, 2130 | Todas as queries que usam `gatewaypro_tracking_products` e `gatewaypro_tracking_events` | **Alto** |
| `views/tracking.php` | 64, 87, 333 | Verificação e SELECT em `gatewaypro_tracking_products` | **Alto** |
| `api/track.php` | 58 | SELECT em `gatewaypro_tracking_products` | **Alto** |
| `api/track_beacon.php` | 51 | Idem | **Alto** |
| `obrigado.php` | 372-374 | Variável `$stmt_get_GatewayPro_tracking` e query em `gatewaypro_tracking_products` | **Alto** (nome da tabela); nome da variável PHP pode ser renomeado por estilo) |

**Recomendação:** Não renomear tabelas na Etapa 1; manter `gatewaypro_tracking_*` ou planejar migration dedicada depois.

### D.2 — Rotas e paths

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `.htaccess` | 16 | `RewriteRule ^gateways(/.*)?$ - [F,L,NC]` — **rota da pasta gateways (gateway de pagamento)** — não alterar | **Alto** |

`RewriteBase /gatewaypro1/` em comentário é exemplo de subdiretório (C se for só documentação; não ativar sem necessidade).

### D.3 — Variáveis de ambiente (chaves técnicas)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `api/admin_api.php` | 1683-1688 | Comentário e `getenv('GATEWAYPRO_MASTER_SECRET')` — usado para Painel Master | **Alto** — renomear exige alterar .env e todo lugar que lê a variável |
| `.env.example` | 17 | `GATEWAYPRO_MASTER_SECRET=` — nome da chave | **Alto** |

**Recomendação:** Manter `GATEWAYPRO_MASTER_SECRET` como nome da variável (evita quebrar instalações e documentação de licenças) ou planejar mudança coordenada (código + .env + docs).

### D.4 — Licenças e assinaturas

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `api/member_api.php` | 468 | `$dataToSign = "GATEWAYPRO-{$licenseInfo['tipo']}-{$uniqueId}"` — formato da string assinada | **Alto** — mudar quebra validação de licenças já emitidas |
| `api/admin_api.php` | 1769 | `$dataToSign = "GATEWAYPRO-{$tipo}-{$uniqueId}"` | **Alto** |
| `helpers/license_service.php` | 84 | `$dataToSign = "GATEWAYPRO-{$tipo}-{$uniqueId}"` | **Alto** |
| `docs/DOC_LICENCAS_ANALISE.md` | 6-7, 27 | Referências a GATEWAYPRO_MASTER_SECRET e formato de chave (GATEWAYPRO-VITALICIO-...) | Documentação — alinhar só após decidir se muda ou não o formato |

**Recomendação:** Não alterar o prefixo `GATEWAYPRO-` nas strings de assinatura de licença sem planejamento de compatibilidade (ex.: aceitar ambos os prefixos na validação).

### D.5 — Headers e webhooks

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `api/notification.php` | 125 | `'X-GatewayPro-Event: ' . $trigger_event` — header HTTP enviado em webhook | **Alto** — integrações externas podem depender do nome do header |
| `api/notification.php` | 297 | Comentário "WEBHOOK (Gateway)" — aqui "Gateway" é contexto de gateway de pagamento; não trocar | N/A |

### D.6 — Ações de API (query string)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `api/api.php` | 609, 625-626 | `$action == 'get_jornada_GatewayPro_data'` e mensagens de log | **Alto** — frontend chama `api.php?action=get_jornada_GatewayPro_data`; renomear exige alterar backend + jornada_starfy.php |
| `api/api.php` | 1500, 1522, 1528, 1534, 1565, 1579, 1594, 1599, 1889, 1895, 1920, 1951, 2091, 2096 | `get_GatewayPro_tracked_products`, `add_GatewayPro_tracked_product`, `get_GatewayPro_tracking_data` | **Alto** — usado em `views/tracking.php` (fetch) |
| `jornada_starfy.php` | 80 | `fetch('api.php?action=get_jornada_GatewayPro_data')` | **Alto** |
| `views/tracking.php` | 380, 403, 540 | fetch com `action=add_GatewayPro_tracked_product`, `get_GatewayPro_tracked_products`, `get_GatewayPro_tracking_data` | **Alto** |

**Recomendação:** Não alterar nomes de ações na Etapa 1; ou alterar em conjunto (api.php + todos os frontends que chamam).

### D.7 — localStorage e classes CSS / JS (tracking)

| Arquivo | Linha(s) | Trecho / contexto | Risco |
|---------|----------|-------------------|-------|
| `checkout.php` | 1331 | `localStorage.setItem('GatewayPro_checkout_session_uuid', ...)` | **Médio** — trocar quebra sessões em andamento; pode trocar em nova versão com migração de chave |
| `api/api.php` | 1644, 1651, 1667, 1671, 1683, 1694, 1702, 1704, 1707, 1721, 1724, 1733, 1768, 1774, 1789, 1815 | `GatewayPro_TRACK_ID`, `GatewayPro_session_id`, `GatewayPro-checkout-btn`, logs "GatewayPro Track..." | **Médio** — IDs e classes usados em JS; renomear exige alterar script injetado e possivelmente páginas de checkout que usam a classe |

### D.8 — Produto/configuração de pagamento (coluna e variáveis)

Todos os usos de **`gateway`** como coluna da tabela `produtos`, variáveis `$gateway`, `$pix_gateway`, `gateway_choice`, `gateway_pushinpay_enabled`, etc., são **gateway de pagamento**. Não devem ser renomeados para “SinergIA” — manter "gateway" no sentido de payment gateway.

Arquivos envolvidos (apenas referência): `produto_config.php`, `views/produto_config.php`, `api/process_payment.php`, `views/integracoes.php`, `checkout.php`, `process_payment.php`, `Banco_de_Dados.sql`, etc.

---

## Resumo de arquivos por categoria

### A) Branding visual (seguro trocar)
- `views/member/member_area_dashboard.php`
- `views/member/member_licenses.php`
- `api/notification.php` (smtp_from_name default)
- `api/admin_api.php` (from_name, assunto/corpo e-mail teste, comentários)
- `api/api.php` (from_name default)
- `vendas_actions.php`
- `api/vendas_actions.php`
- `api/notifications_api.php`
- `views/admin/admin_smtp_config.php`
- `DIAGNOSTICO_AMBIENTE_NAO_DOCKER.md`
- `docs/DOC_LICENCAS_PROPOSTA.md`
- `helpers/community_helper.php` (comentários)
- `.htaccess` (comentários)
- `jornada_starfy.php` (apenas textos e labels visíveis; não nomes de ação API/constantes JS)

### B) Assets (trocar com cuidado)
- `index.php` (URLs do logo/favicon)
- `views/member/member_area_dashboard.php` (fallback logo)
- `admin.php`
- `api/admin_api.php` (default logo_url)
- `config/load_settings.php`
- `api/generate_legal_page.php`

### C) Configurações (trocar com cuidado)
- `api/admin_api.php` (default nome_plataforma)
- `config/load_settings.php` (nome_plataforma default)
- `api/generate_legal_page.php` (nome_plataforma default)
- `.env.example` (comentário e exemplos de valor; não renomear GATEWAYPRO_MASTER_SECRET)
- `config/config.php` (valores default de DB_*)
- `config/config.docker.php`
- `docker/entrypoint.sh`
- `api/admin_api.php` (regex de substituição de copyright — avaliar compatibilidade)

### D) Lógica / integrações (não alterar na Etapa 1)
- `Banco_de_Dados.sql` (tabelas gatewaypro_tracking_*)
- `api/api.php` (tabelas, ações get_jornada_GatewayPro_data, get_GatewayPro_tracked_*, add_GatewayPro_tracked_product, script tracking)
- `views/tracking.php` (tabela, ações API)
- `api/track.php`, `api/track_beacon.php`
- `obrigado.php`
- `api/member_api.php`, `api/admin_api.php`, `helpers/license_service.php` (GATEWAYPRO- no dataToSign)
- `api/notification.php` (X-GatewayPro-Event)
- `.env.example` / código que lê `GATEWAYPRO_MASTER_SECRET`
- `checkout.php` (localStorage GatewayPro_checkout_session_uuid)
- `jornada_starfy.php` (action get_jornada_GatewayPro_data e constantes/IDs JS se forem alterados)
- `.htaccess` (RewriteRule gateways — não alterar)
- Todos os arquivos que usam coluna/variável `gateway` (payment gateway) — não alterar

---

## Próximos passos sugeridos (Etapa 2 — só o visível)

1. **Alterar somente:** itens **A**, **B** e, em **C**, apenas valores default de nome/logo e comentários.
2. **Não alterar:** qualquer item da categoria **D** (tabelas, API actions, env keys, licenças, headers, localStorage).
3. Trabalhar em **branch separado** e testar: login, títulos das páginas, e-mails, área de membros, jornada.
4. Gerar **relatório final** com arquivos alterados e trechos após a Etapa 2.

---

*Relatório gerado na ETAPA 1 (levantamento). Nenhum código foi alterado. Recomendação: mudar somente o que fica visível (A + B + defaults visíveis em C).*
