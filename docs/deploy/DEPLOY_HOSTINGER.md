# Deploy na Hostinger (código vindo de VPS/Easypanel)

Este projeto foi organizado e testado em **VPS + Easypanel + Cloudflare + GitHub**. Ao subir o **mesmo código** na **Hostinger**, é possível encontrar **problemas diferentes** dos já corrigidos em produção — não por duplicidade de código, mas por **diferença de ambiente**.

Este guia lista os pontos de atenção para reduzir surpresas.

---

## 1. O que tende a funcionar igual

- **Lógica de negócio e fluxos**: login, checkout, área de membros, produtos, vendas.
- **Banco de dados**: mesmo schema; importar `Base_de_Dados_Instalacao.sql` e ajustar `.env` com as credenciais do MySQL da Hostinger.
- **Estrutura de pastas**: `legal/`, `uploads/`, `config/`, `views/`, etc. — mantida.
- **Links Legais**: o fallback para `uploads/legal/` foi feito justamente para servidores em que `legal/` na raiz não é gravável (caso típico de shared hosting).

---

## 2. Onde costumam aparecer diferenças (Hostinger)

### 2.1 Permissões de escrita

| O quê | Risco | O que fazer |
|-------|--------|-------------|
| `legal/` | Em shared hosting às vezes não é gravável. | O código já usa **`uploads/legal/`** se `legal/` falhar; garantir que **`uploads/`** tenha permissão de escrita (755 ou 775). |
| `uploads/` | Sem escrita = falha em banners, config, aulas. | No painel Hostinger, conferir permissões da pasta `uploads/` (e subpastas se houver). |
| `config/` | Só leitura; não precisa escrever. | Manter `.htaccess` que bloqueia acesso direto. |

### 2.2 Limites de PHP (upload e tempo)

Em produção (VPS) esses limites costumam ser maiores. Na Hostinger, valores baixos podem causar:

- **“Salvar e Atualizar Preview”** demorar ou dar timeout ao subir **banners grandes ou vários**.
- Upload de arquivos falhar sem mensagem clara.

**Onde ajustar (Hostinger):**  
Painel → **PHP Configuration** (ou **.user.ini** / **php.ini**), se disponível:

- `upload_max_filesize` (ex.: 32M ou 64M)
- `post_max_size` (maior que `upload_max_filesize`)
- `max_execution_time` (ex.: 120 ou 300 para evitar timeout no save do checkout)

Se não der para alterar, reduzir tamanho/número dos banners no editor.

### 2.3 Arquivo `.env`

- O projeto usa **`.env`** na **raiz** (carregado por `config/env_loader.php`).
- Em alguns planos Hostinger o **document root** é `public_html` e o `.env` fica em `public_html/.env`. Verificar se o PHP consegue **ler** esse arquivo (sem expô-lo na URL).
- Se o painel não permitir criar/editar `.env`, usar **Variáveis de ambiente** do painel (se existirem) e garantir que `config.php` continue recebendo os valores (hoje vem de `env()`, que lê `.env` ou `getenv`).
- **Nunca** commitar `.env` no Git; na Hostinger, criar o `.env` manualmente a partir do `.env.example`.

### 2.4 Base URL / HTTPS

- Redirecionamentos e links usam caminhos relativos ou configurações do sistema (`configuracoes_sistema`, URLs de login, etc.).
- Após subir na Hostinger, conferir em **Configurações**:
  - **URL de login da área de membros** (`member_area_login_url`) com o domínio correto (ex.: `https://seudominio.com/member_login`).
  - Uso de **HTTPS** consistente (evitar misturar http/https).
- Se usar Cloudflare na Hostinger, manter “SSL/TLS” em Full (estrito) e base URL em HTTPS.

### 2.5 Cron e tarefas agendadas

- Se em produção (VPS) você usa **cron** para recuperação de carrinho, e-mails ou outros jobs, na Hostinger será preciso configurar o **Agendador de tarefas** (Cron Jobs) do painel com as mesmas URLs ou scripts e intervalos.
- Ver em **DOC_INSTALACAO_E_ESTRUTURA.md** (seção 4.6) quais scripts/endpoints são usados para isso.

### 2.6 Banco de dados (MySQL/MariaDB)

- Hostinger costuma oferecer MySQL/MariaDB compatível. Usar **utf8mb4** e o mesmo schema.
- Se aparecer erro de `sql_mode` ou charset, conferir collation do banco e das tabelas (utf8mb4_unicode_ci).

---

## 3. Checklist rápido antes de “subir de novo” na Hostinger

1. [ ] **.env** na raiz (ou env vars do painel) com `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `APP_TIMEZONE`.
2. [ ] **Permissões**: `uploads/` gravável; testar “Gerar Página” nos Links Legais (se falhar, o fallback para `uploads/legal/` deve funcionar).
3. [ ] **PHP**: `upload_max_filesize`, `post_max_size` e `max_execution_time` compatíveis com uso de banners e save do checkout.
4. [ ] **Configurações no painel**: URL de login da área de membros, SMTP, domínio e HTTPS.
5. [ ] **Cron**: replicar tarefas agendadas que existem na VPS.
6. [ ] **Document root**: apontando para a raiz onde estão `index.php`, `checkout.php`, `config/`, etc. (no caso Hostinger, em geral `public_html` ou a pasta do domínio).

---

## 4. Resumo

- **Chance de ter problemas *diferentes*:** média — principalmente limites PHP, permissões e forma de carregar `.env`/variáveis.
- **Problemas já corrigidos** (Links Legais, feedback de save, fallback `uploads/legal`) **continuam valendo** e ajudam também na Hostinger.
- Seguir este checklist e os passos de **DOC_INSTALACAO_E_ESTRUTURA.md** (banco, .env, arquivos, configurações) reduz bastante o risco ao baixar a estrutura de produção para local e subir novamente na Hostinger.
