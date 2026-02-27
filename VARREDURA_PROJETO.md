# Varredura do Projeto – SinergiaCore

Documento gerado a partir de uma análise geral da estrutura, rotas, segurança e módulos do projeto.

---

## 1. Estrutura de pastas (raiz)

| Pasta / arquivo | Descrição |
|-----------------|-----------|
| **api/** | APIs (api.php principal, admin_api.php, vendas, notificações, etc.) |
| **config/** | Configuração (config.php, load_settings, theme_helper, .htaccess) |
| **views/** | Views PHP (admin, member, páginas do infoprodutor) |
| **pwa/** | Módulo PWA (manifest, SW, push, VAPID, web_push_helper) |
| **helpers/** | Helpers (plugin_loader, member_protection, master, evolution, etc.) |
| **migrations/** | SQL de migração (pwa_tables.sql, etc.) |
| **gateways/** | Gateways de pagamento (beehive, efi, hypercash) |
| **PHPMailer/** | Envio de e-mail |
| **uploads/** | Arquivos enviados (imagens, PDFs, etc.) |
| **vendor/** | Dependências Composer (minishlink/web-push, Guzzle, etc.) |
| **assets/** | Assets estáticos |
| **legal/** | Páginas legais (HTML) |
| **docker/** | Configuração Docker |
| **docs/** | Documentação |

**Arquivos importantes na raiz:**  
`index.php` (painel infoprodutor), `admin.php` (painel admin), `login.php`, `member_login.php`, `member_area_dashboard.php`, `checkout.php`, `obrigado.php`, `composer.json`, `.htaccess`, `manifest.json`, `sw.js`.

---

## 2. Roteamento e entrada de usuários

- **.htaccess**
  - Protege pastas sensíveis: `config`, `migrations`, `helpers`, `gateways`, `PHPMailer`, `docker`, etc.
  - Bloqueia acesso direto a `.env`, `.git`, `composer.json`, arquivos `.sql`, `.log`, etc.
  - Bloqueia execução de PHP em `uploads/`.
  - URLs limpas: `/.php` vira redirect 301 para sem `.php`.
  - APIs: `/api/*` mapeado para `api/*.php`.
  - **Roteador central:** qualquer URL não arquivo/pasta → `index.php?url=$1`.

- **Fluxo de acesso**
  - **Login geral:** `login.php` → sessão → redirecionamento por tipo:
    - `tipo === 'admin'` → `/admin`
    - `tipo === 'usuario'` → `/member_area_dashboard`
    - `tipo === 'infoprodutor'` → `/index` (painel)
  - **Área de membros:** `member_login.php` → login de cliente → `member_area_dashboard.php` (view em `views/member/`).
  - **Admin:** `admin.php` → verificação `tipo === 'admin'` → `$pagina = $_GET['pagina']` → include de `views/admin/{pagina}.php` (lista em `$paginas_permitidas_admin`).
  - **Painel infoprodutor:** `index.php` → `$pagina = $_GET['pagina']` → include de `views/{pagina}.php` (lista em `$paginas_permitidas`).

---

## 3. Configuração e banco

- **config/config.php**
  - Carrega `.env` via `env_loader.php`.
  - Define `DB_*`, timezone, cria `$pdo`.
  - Inicia sessão.
  - Funções: `getSystemSetting()`, `setSystemSetting()`, `getAllSystemSettings()` (tabela `configuracoes_sistema`).
  - Inclui `plugin_hooks.php`, `plugin_loader.php`, `community_helper.php`.

- **Banco**
  - Tabelas esperadas (além das do negócio): `usuarios`, `configuracoes_sistema`, `configuracoes`, e para PWA: `pwa_config`, `pwa_push_subscriptions`, `pwa_push_notifications` (ver `migrations/pwa_tables.sql`).

---

## 4. Módulo PWA – pontos de uso no projeto

Arquivos que referenciam PWA (ativação, config ou push):

- **index.php** – Manifest/theme condicional, banner push, card “Melhore sua experiência”, SW `/pwa/sw.js`, script de inscrição.
- **views/member/member_area_dashboard.php** – Meta/manifest PWA quando ativo, banner push, card boas-vindas, SW e script de inscrição.
- **views/admin/admin_pwa.php** – Tela de configuração PWA e envio de notificações.
- **api/api.php** – Endpoints `get_pwa_vapid_public` e `register_pwa_push`.
- **api/admin_api.php** – Ações admin PWA (status, config, ícone, push info, enviar push, ativar módulo).
- **pwa/** – `pwa_config.php`, `pwa_functions.php`, `manifest.php`, `sw.js`, `pwa_push_register.js`, `api/web_push_helper.php`, `generate_vapid_keys.php`.

Ativação do módulo: chave `pwa_activated` = `1` em `configuracoes_sistema` (e/ou arquivo de ativação, conforme implementação).

---

## 5. APIs principais

- **api/api.php** – API geral (usuário logado): dashboard, vendas, perfil, reordenação, ofertas, notificações, **PWA (vapid + register push)**, etc.
- **api/admin_api.php** – Ações exclusivas do admin: usuários, config, SMTP, relatórios, **PWA (config, push, ativação)**, etc.
- **api/vendas_actions.php**, **api/process_payment.php**, **api/notification.php**, **api/member_api.php**, entre outros, para fluxos específicos.

---

## 6. Segurança (resumo)

- .htaccess bloqueia acesso direto a config, migrations, helpers, gateways, .env, composer, etc.
- Admin exige `$_SESSION['tipo'] === 'admin'`.
- API principal (api.php) exige usuário logado (`$_SESSION['loggedin']`, `$_SESSION['id']`).
- Uploads: PHP não executável em `uploads/`.
- Views não são acessíveis diretamente por URL (regra no .htaccess para `/views/`).

---

## 7. Observações

- **manifest.json** e **sw.js** na raiz podem coexistir com o módulo PWA; quando PWA está ativo, o layout usa `/pwa/manifest.php` e `/pwa/sw.js` onde implementado.
- **default.php** – Página padrão (ex. Hostinger); não faz parte do fluxo principal do app.
- **plugins** – Sistema de plugins (hooks, loader) em `helpers/`; existe plugin SaaS (planos, acesso).
- **PWA** – Migração em `migrations/pwa_tables.sql`; dependência `minishlink/web-push` no Composer.

---

## 8. Arquivos alterados recentemente (módulo PWA / notificações)

Para referência, os arquivos que foram modificados na implementação do fluxo de push e instalação como app:

1. **api/api.php** – Endpoints get_pwa_vapid_public e register_pwa_push.
2. **pwa/pwa_push_register.js** – Inscrição em push e objeto PwaPush.
3. **index.php** – Manifest/theme, banner, card boas-vindas, scripts PWA.
4. **views/member/member_area_dashboard.php** – Meta/manifest PWA, banner, card, scripts.
5. **views/admin/admin_pwa.php** – Mensagens de envio (enviadas/falhas) e dicas.
6. **pwa/api/web_push_helper.php** – Normalização de chaves (base64url → base64) e uso nas funções de envio.
7. **admin.php** – Meta tag `mobile-web-app-capable` (evitar aviso de depreciação no console).

---

*Varredura concluída. Para detalhes de cada arquivo, use o código-fonte e os comentários no projeto.*
