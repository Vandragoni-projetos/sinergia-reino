# Diagnóstico GatewayPro – Ambiente fora do Docker

## 1. Diagnóstico técnico

O projeto foi pensado para rodar em Docker (EasyPanel) e usa **config/config.php** (com `env_loader` e `.env`) em hospedagem tradicional, ou **config/config.docker.php** no Docker (variáveis fixas, ex.: `DB_HOST=db`). Em ambiente **não-Docker**, é essencial usar **config/config.php** e ter um `.env` na raiz; do contrário a conexão com o banco e várias funções globais ficam indefinidas.

Não há WordPress no ambiente; funções como `getSystemSetting`, `plugin_active`, `do_action` e `apply_filters` são **do próprio projeto** (definidas em config e helpers), não do WP.

---

## 2. Funções globais e onde são definidas

| Função | Definida em | Carregada por |
|--------|-------------|----------------|
| `getSystemSetting` | config/config.php, config/config.docker.php | config.php |
| `setSystemSetting` | config/config.php, config.config.docker.php | config.php |
| `getAllSystemSettings` | idem | config.php |
| `env` | config/env_loader.php | config.php (que faz require em env_loader) |
| `plugin_active` | helpers/plugin_loader.php | config.php |
| `load_active_plugins`, `get_plugin_info` | helpers/plugin_loader.php | config.php |
| `do_action` | helpers/plugin_hooks.php | config.php |
| `apply_filters` | helpers/plugin_hooks.php | config.php |
| `getCommunityId`, `getCommunityContext`, `getCommunityFilter` | helpers/community_helper.php | config.php (apenas config.php; config.docker.php **não** carrega community_helper) |
| `isMasterPanel` | helpers/master_helper.php | Requerido sob demanda por APIs/views (master_helper usa getSystemSetting) |
| `get_user_plan_dashboard_info` | plugins/saas/includes/user_dashboard_info.php | Apenas quando plugin SaaS está ativo e o arquivo existe |

**Conclusão:** Quem não carrega `config/config.php` (ou config.docker.php) antes de usar essas funções quebra em ambiente fora do Docker (ou quebra getCommunity* se usar só config.docker.php).

---

## 3. Pontos que quebram a execução fora do Docker

### 3.1 Entrada sem bootstrap (config não carregado)

| Arquivo | Problema |
|---------|----------|
| **produto_config.php (raiz)** | Não faz `require` de config. Usa `$pdo` e `$_SESSION['id']` já na linha 15. Se a URL for `/produto_config?id=X` (acesso direto ao arquivo), dá erro. O fluxo correto é `/index?pagina=produto_config&id=X` (index carrega config e inclui views/produto_config.php). |

**Recomendação:** No `produto_config.php` da raiz, adicionar no topo `require_once __DIR__ . '/config/config.php';` (ou redirecionar para index com pagina=produto_config) para que acesso direto não quebre.

---

### 3.2 Uso de config.docker.php em não-Docker

| Problema | Detalhe |
|----------|---------|
| **config.docker.php** | Define `DB_HOST='db'` (serviço Docker). Não carrega `env_loader.php` nem `community_helper.php`. Em hospedagem tradicional, se este arquivo for o “config” usado, a conexão falha e `getCommunityId`/`getCommunityFilter` não existem. |

**Recomendação:** Em ambiente não-Docker usar sempre **config/config.php** (com `.env` e env_loader). Não usar config.docker.php como config principal.

---

### 3.3 Dependência da tabela `plugins`

| Arquivo | Problema |
|---------|----------|
| **helpers/plugin_loader.php** | Executa `SELECT pasta, nome FROM plugins WHERE ativo = 1`. Se a tabela `plugins` não existir (migrações não rodadas), gera exceção PDO. |
| **index.php, login.php** | Chamam `plugin_active('saas')` sem `function_exists`. Se config não tiver carregado plugin_loader antes, dá erro. Hoje config carrega plugin_loader; o risco é tabela `plugins` ausente. |

**Recomendação:** Garantir que o schema (ex.: Banco_de_Dados.sql ou migrações) crie a tabela `plugins`. Em instalação nova, rodar migrações antes de usar.

---

### 3.4 Pasta `plugins/` e plugin SaaS

| Arquivo | Problema |
|---------|----------|
| **index.php** | Se `plugin_active('saas')` for true, faz `require_once .../plugins/saas/includes/notifications.php` e `.../plugins/saas/saas.php`. Se a pasta `plugins/saas` não existir, erro fatal. |
| **login.php** | Mesma lógica para plugins/saas. |
| **notification.php** (raiz) | Linha 693: `if (plugin_active('saas')) { require_once .../plugins/saas/includes/notifications.php; }` — se o plugin estiver ativo no DB mas a pasta não existir, quebra. |
| **views/planos.php** | Usa `get_user_plan_dashboard_info` só se `plugin_active('saas')` e arquivo user_dashboard_info existirem; senão pode quebrar se chamar a função em outro contexto. |

**Recomendação:** Antes de qualquer `require_once` em arquivos do plugin, usar `file_exists()`. Ex.: em notification.php, só dar require se `file_exists(__DIR__ . '/plugins/saas/includes/notifications.php')`.

---

### 3.5 Código que depende de WordPress (api.php)

| Arquivo | Problema |
|---------|----------|
| **api.php** | Contém a classe `WpAuthMiddleware` (por volta da linha 7390) que faz `require_once("$wpDirectory/wp-load.php")` e usa `wp_signon`, `wp_logout`, `wp_get_current_user`, `is_user_logged_in`. Em ambiente **sem WordPress**, qualquer rota que use esse middleware gera erro fatal. |

**Recomendação:** Não registrar/usar esse middleware em ambiente não-WordPress; ou envolver o carregamento do WP em `if (file_exists($wpDirectory . '/wp-load.php'))` e tratar o caso “WP não instalado” (ex.: retornar 503 ou redirecionar).

---

### 3.6 Helpers/arquivos opcionais ausentes

| Arquivo que requer | Dependência | Comportamento se ausente |
|--------------------|-------------|---------------------------|
| notification.php (raiz), process_payment.php, vendas_actions.php | utmfy_helper.php, evolution_helper.php, acesso_helper.php | Vários já usam `file_exists` antes de require; os que não usam podem quebrar se o path estiver errado. |
| config/theme_helper.php | config.php | Faz `require_once __DIR__ . '/config.php'`. Se config não estiver no path esperado, quebra. |
| config/load_settings.php | getSystemSetting, config, theme_helper | Só usa getSystemSetting se já existir (if (!function_exists('getSystemSetting')) require config). Correto desde que config seja carregado antes em todas as entradas que incluem load_settings. |
| views/admin/admin_configuracoes.php | master_helper.php | Só require master_helper; é incluída por admin.php, que já carregou config. OK. |
| helpers/license_helper.php | master_helper.php | master_helper usa getSystemSetting; logo config deve estar carregado antes de license_helper. login.php e outros carregam config primeiro. OK. |
| helpers/community_helper.php | $pdo / config | Se `$pdo` não está definido, tenta carregar config. Quando community_helper é carregado por config.php, $pdo já existe. OK. |

Nenhum desses pontos é novo “fora do Docker”; o crítico é **sempre** carregar config (config.php) antes de qualquer view/API que use essas funções.

---

### 3.7 Variáveis de ambiente / .env

| Problema | Detalhe |
|----------|---------|
| **config/config.php** | Depende de env_loader.php, que lê `.env` na raiz (ou ROOT_PATH). Se não houver `.env` ou as chaves (DB_HOST, DB_USER, DB_PASS, DB_NAME, APP_TIMEZONE), usa defaults do env(). Em hospedagem tradicional, sem .env a aplicação pode tentar conectar em localhost com credenciais padrão e falhar. |

**Recomendação:** Fornecer `.env` na raiz com DB_* e APP_TIMEZONE; não depender de variáveis que só existiam no container Docker.

---

## 4. Ordem recomendada de inicialização (bootstrap)

Ordem objetiva do que deve ser carregado primeiro:

1. **config/env_loader.php**  
   - Carrega `.env`, define `env()`.

2. **config/config.php** (não config.docker.php em não-Docker)  
   - Define DB_* a partir de `env()`, conecta PDO, inicia sessão, define `getSystemSetting`, `setSystemSetting`, `getAllSystemSettings`.  
   - Em seguida:  
     - `require_once helpers/plugin_hooks.php` (do_action, apply_filters)  
     - `require_once helpers/plugin_loader.php` (plugin_active, load_active_plugins)  
     - `require_once helpers/community_helper.php` (getCommunityId, getCommunityFilter, etc.)

3. **Demais helpers sob demanda**  
   - Por exemplo: license_helper (e com ele master_helper), security_helper, theme_helper, load_settings, etc., conforme a página ou API.

Arquivos que **devem** ser “entrada” apenas após ter rodado o bootstrap acima (ou eles mesmos carregarem config no topo):

- index.php  
- login.php  
- admin.php  
- checkout.php  
- obrigado.php  
- notification.php (raiz)  
- process_payment.php (raiz)  
- admin_usuarios.php  
- vendas_actions.php (raiz)  
- media.php  
- checkout_editor.php  
- ativacao.php  
- old_register.php  
- jornada_starfy.php  
- install_banners.php  
- member_login.php, member_licenses.php, member_course_view.php, member_area_dashboard.php (wrappers que incluem views)  
- Todas as APIs em api/* (cada uma faz require em config no início).

**Exceção hoje:** produto_config.php na raiz não carrega config; deve passar a carregar ou ser acessado só via index.

---

## 5. Resumo dos erros críticos

1. **produto_config.php (raiz)** não carrega config; acesso direto quebra.  
2. Uso de **config.docker.php** em não-Docker (DB_HOST=db, sem env, sem community_helper).  
3. Tabela **plugins** inexistente quebra **plugin_loader.php** (e qualquer uso de plugin_active/load_active_plugins).  
4. **plugins/saas** ativo no DB mas pasta/arquivos ausentes: **index.php**, **login.php**, **notification.php** podem dar require fatal.  
5. **api.php** – **WpAuthMiddleware** carrega WordPress; em ambiente sem WP, rotas que usam esse middleware quebram.  
6. Ausência de **.env** (ou ROOT_PATH incorreto para env_loader) em hospedagem tradicional pode impedir conexão com o banco ou timezone.

---

## 6. Sugestões de correção

1. **Bootstrap único**  
   - Garantir que em não-Docker só se use **config/config.php** (com env_loader e .env).  
   - Documentar que config.docker.php é só para Docker.

2. **produto_config.php (raiz)**  
   - Adicionar no topo:  
     `require_once __DIR__ . '/config/config.php';`  
   - Ou redirecionar todo acesso a produto_config para `index.php?pagina=produto_config&id=...` e, se quiser, remover ou depreciar o arquivo na raiz.

3. **Tabela plugins**  
   - Incluir criação da tabela `plugins` no script de instalação/migrações (ex.: Banco_de_Dados.sql já a contém; garantir que seja executado).

4. **Requires do plugin SaaS**  
   - Em **index.php**, **login.php** e **notification.php**, antes de cada `require_once` de arquivos em plugins/saas, usar `file_exists()`.  
   - Exemplo em notification.php (por volta da linha 693):  
     `if (plugin_active('saas') && file_exists(__DIR__ . '/plugins/saas/includes/notifications.php')) { require_once ... }`

5. **api.php e WordPress**  
   - Não registrar WpAuthMiddleware em ambiente não-WordPress; ou só carregar wp-load.php se `file_exists($wpDirectory . '/wp-load.php')` e tratar “WP não disponível” sem fatal.

6. **.env na raiz**  
   - Fornecer `.env.example` com DB_HOST, DB_USER, DB_PASS, DB_NAME, APP_TIMEZONE e instruções para copiar para `.env` em hospedagem tradicional.

7. **Ordem de carregamento**  
   - Manter a ordem: env_loader → config.php → plugin_hooks → plugin_loader → community_helper. Nenhum entry point (páginas ou APIs) deve ser executado sem que config (e, quando for o caso, env_loader) já tenha sido carregado.

Com isso, o projeto fica previsível e estável em ambiente fora do Docker, sem depender de WordPress nem de suposições de ambiente Docker.
