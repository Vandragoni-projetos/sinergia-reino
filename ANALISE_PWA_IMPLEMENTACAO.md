# Análise: Implementação do Módulo PWA no SinergiaCore

Com base na pasta **"pwa 2.0.1"**, na resposta do Windsurf e nas imagens da outra área de membros (telas de Configurações PWA), este documento descreve o que existe, o que falta e o plano para implantar o fluxo **compra → upload do arquivo → liberação das configurações** no projeto atual.

---

## 1. O que o pacote "pwa 2.0.1" já traz

### 1.1 Backend (PHP)

| Arquivo | Função |
|--------|--------|
| **pwa/pwa_config.php** | Verifica se o módulo está instalado (`pwa_module_installed()`), lê/grava configurações na tabela `pwa_config` (`pwa_get_config`, `pwa_save_config`, `pwa_get_config_direct` para manifest sem sessão). |
| **pwa/pwa_functions.php** | Gera manifest dinâmico (`pwa_generate_manifest()`), helpers de tema e prompt de instalação. |
| **pwa/manifest.php** | Endpoint que entrega apenas JSON do manifest (sem sessão, para uso pelo navegador). |
| **pwa/sw.js** | Service Worker com cache (network first), offline e **notificações push** (eventos `push` e `notificationclick`). |
| **pwa/generate_vapid_keys.php** | Página para gerar chaves VAPID e salvar em `pwa_config` (com link “Voltar para Configurações PWA”). |
| **pwa/api/web_push_helper.php** | Funções de push: VAPID, salvar/remover subscriptions, enviar notificação global e por vendedor, integração com `minishlink/web-push`. |
| **pwa/test_push_setup.php** | Diagnóstico (PHP, extensões, tabelas, VAPID, etc.). |

### 1.2 Estrutura de dados esperada (inferida do código)

- **Tabela `pwa_config`**  
  Campos usados: `id`, `app_name`, `short_name`, `description`, `icon_path`, `theme_color`, `background_color`, `display_mode`, `start_url`, `scope`, `push_enabled`, `vapid_public_key`, `vapid_private_key`, `updated_at` (e opcionalmente `created_at`).

- **Tabela `pwa_push_subscriptions`**  
  Campos: `id`, `usuario_id`, `endpoint`, `p256dh`, `auth`, `user_agent`, `created_at`, `updated_at`.

- **Tabela `pwa_push_notifications`**  
  Campos: `id`, `title`, `message`, `url`, `icon`, `sent_count`, `failed_count`, `created_by`, e provavelmente `created_at`.

O pacote **não inclui** arquivos `.sql` de migração; o `test_push_setup.php` só cita “SQL Atualizações/pwa_push_migration.sql”.

### 1.3 Frontend / Admin

- O pacote **não inclui** a view **admin_pwa** (a tela de “Configurações PWA” das imagens).
- Apenas referências a `/admin?pagina=admin_pwa` (por exemplo em `generate_vapid_keys.php`).
- Ou seja: a interface que você vê na “outra área de membros” (compra, depois configuração geral + notificações push) terá de ser **criada** neste projeto.

### 1.4 Compatibilidade com o SinergiaCore (ajuste já feito)

- O projeto usa **`configuracoes_sistema`** e **`getSystemSetting()`** (em `config/config.php`).
- No pacote, em `pwa_config.php`, havia uma leitura em **`system_settings`** para `cor_primaria`.  
  Foi alterado para **`configuracoes_sistema`**, mantendo a chave `cor_primaria`, para ficar compatível com o SinergiaCore.

---

## 2. O que o projeto SinergiaCore já tem (resumo Windsurf + verificação)

- **manifest.json** e **sw.js** na raiz: existem, mas o `sw.js` atual está em modo “matador de cache” (limpa cache e se desregistra), ou seja, não é o mesmo do pacote PWA 2.0.1.
- **Rotas admin:** em `admin.php`, `$paginas_permitidas_admin` **não** contém `admin_pwa`.
- **Menu do admin:** não há item “PWA” no sidebar.
- **View:** não existe `views/admin/admin_pwa.php`.
- **API admin:** em `api/admin_api.php` não há actions para PWA (salvar configurações, upload de ícone, status de ativação).
- **Ativação por arquivo:** não há lógica que detecte upload de um arquivo (ex.: `pwa_activated.key` ou similar) para “liberar” as opções de configuração.

Ou seja: o Windsurf está correto: a estrutura básica PWA existe, mas **toda** a parte de configuração no admin e a lógica de ativação por arquivo precisam ser implementadas.

---

## 3. Fluxo desejado (com base nas imagens e no seu texto)

1. **Sem “compra”/ativação**  
   - No admin, ao clicar em PWA, mostrar apenas a **tela de venda** (imagem 01): benefícios do PWA, “Comprar Módulo PWA”, etc., **sem** abas de configuração.

2. **Pós-compra**  
   - O usuário recebe um arquivo (ex.: `pwa_activated.key` ou outro nome definido por você).
   - Deve fazer upload desse arquivo na hospedagem (ex.: raiz do site ou pasta específica).

3. **Após upload + atualizar o admin**  
   - Ao acessar de novo “Configurações PWA”, o sistema detecta que o arquivo existe.
   - Mostra as **opções de configuração** (imagens 2 e 3):
     - Aba **Configuração Geral**: nome do app, nome curto, descrição, ícone, cores, modo de exibição, URLs (inicial, escopo), botão “Salvar Configurações PWA”.
     - Aba **Notificações Push**: checkbox “Ativar Notificações Push”, etc.

---

## 4. Viabilidade de implantar no projeto

**Sim, é viável.** O pacote “pwa 2.0.1” já cobre:

- Configuração persistida em `pwa_config`.
- Manifest dinâmico e Service Worker com push.
- Lógica de notificações push (VAPID, subscriptions, envio).

O que falta é **integrar** esse pacote ao SinergiaCore e **criar**:

1. Lógica de **ativação por arquivo** (verificar se o arquivo existe e, se sim, mostrar configurações).
2. **Página admin PWA**:  
   - Estado “não ativado” → conteúdo da imagem 01 (venda).  
   - Estado “ativado” → abas Configuração Geral + Notificações Push (imagens 2 e 3).
3. **Rota e view** `admin_pwa` no admin e item de menu “PWA”.
4. **Endpoints** na `admin_api.php` (ou outro ponto único da API admin) para:  
   - Verificar status de ativação,  
   - Salvar configurações PWA,  
   - Upload de ícone do app.
5. **Migrações SQL** para criar as tabelas `pwa_config`, `pwa_push_subscriptions` e `pwa_push_notifications` (o pacote não traz os `.sql`).
6. **Posicionamento do módulo** na árvore do projeto (caminhos do `config` e do manifest/sw) e, se quiser, uso do manifest dinâmico (`manifest.php`) e do `sw.js` do pacote no lugar dos atuais.

---

## 5. Plano de implementação sugerido

### 5.1 Estrutura de pastas

- Copiar o conteúdo de **`pwa 2.0.1/pwa`** para **`pwa/`** na raiz do projeto (ou manter em `pwa 2.0.1/pwa` e ajustar todos os caminhos para `../../config/config.php`, etc.).
- Manter o **vendor** do PWA (minishlink/web-push):  
  - Ou em `vendor/` na raiz (rodar `composer require minishlink/web-push` na raiz),  
  - Ou incluir o autoload de `pwa 2.0.1/vendor` se a pasta ficar separada.

Recomendação: **`pwa/`** na raiz + dependência no `composer.json` da raiz, para um único `vendor/`.

### 5.2 Banco de dados

Criar um arquivo de migração (ex.: `migrations/pwa_tables.sql`) com:

- `pwa_config` (campos listados no item 1.2).
- `pwa_push_subscriptions`.
- `pwa_push_notifications`.

(Incluo no final deste documento um exemplo de SQL compatível com o que o código do pacote usa.)

### 5.3 Ativação por arquivo

- Definir um nome de arquivo (ex.: `pwa_activated.key`) e, opcionalmente, uma pasta (ex.: raiz do document root ou `uploads/`).
- Em PHP, antes de renderizar a view admin PWA (ou em um endpoint de “status”):
  - Verificar `file_exists(caminho_do_arquivo)`.
  - Se existir: considerar PWA “ativado” e mostrar configurações; caso contrário, mostrar apenas a tela de compra (imagem 01).
- Não é necessário gravar na base “quem comprou”; a condição é só a existência do arquivo no servidor.

### 5.4 Admin: rota, menu e view

- Em **admin.php**:
  - Incluir `admin_pwa` em `$paginas_permitidas_admin`.
  - Incluir um link no sidebar para “PWA” apontando para `?pagina=admin_pwa`.
- Criar **views/admin/admin_pwa.php**:
  - No topo: se o arquivo de ativação **não** existir → incluir um fragmento/HTML da “tela de venda” (imagem 01) e terminar (não mostrar abas).
  - Se existir → mostrar título “Configurações PWA”, abas “Configuração Geral” e “Notificações Push”, formulários e botão “Salvar Configurações PWA” conforme as imagens 2 e 3, usando as mesmas chaves que `pwa_config` (app_name, short_name, theme_color, etc.) e chamando a API admin para salvar e para upload de ícone.

### 5.5 API admin (admin_api.php)

- **action=get_pwa_status**  
  Retornar `{ "activated": true/false }` (baseado em `file_exists` do arquivo de ativação).
- **action=get_pwa_config**  
  Chamar `pwa_get_config()` (incluindo o arquivo do módulo se necessário) e retornar JSON para o front.
- **action=save_pwa_config**  
  Receber POST com os campos da configuração geral (e push_enabled) e chamar `pwa_save_config()`.
- **action=upload_pwa_icon**  
  Receber upload de imagem, validar tipo/tamanho, salvar em `uploads/` (ou pasta definida), gravar o caminho em `pwa_config.icon_path` (por exemplo via `pwa_save_config` com o restante da config).

Garantir que todas as actions exijam sessão admin (como as demais da `admin_api.php`).

### 5.6 Manifest e Service Worker no projeto

- Fazer o front (área de membros / painel) apontar o `<link rel="manifest">` para o **manifest dinâmico** do módulo (ex.: `/pwa/manifest.php`), desde que o módulo esteja ativado (arquivo presente).
- Servir o **sw.js** do pacote (ex.: `/pwa/sw.js`) e registrá-lo nas páginas onde o PWA deve funcionar.
- Se o projeto já tiver um `sw.js` na raiz, decidir entre substituí-lo pelo do pacote ou manter dois (um para desenvolvimento e um para produção PWA); o do pacote já tem cache e push.

### 5.7 Notificações push (front)

- O `sw.js` do pacote já trata `push` e `notificationclick`.
- Falta, no front (área de membros ou admin), o JavaScript que:
  - Pede permissão,
  - Obtém a subscription (endpoint + keys),
  - Envia para o backend (endpoint que chame `pwa_register_subscription` ou equivalente).
- E um endpoint (por exemplo na API existente) que receba essa subscription e chame a função do `web_push_helper.php`. O pacote já tem a lógica de envio; falta só expor o registro da subscription e, se quiser, uma tela no admin para “enviar notificação de teste”.

---

## 6. Resumo

| Item | No pacote "pwa 2.0.1" | No SinergiaCore atual | Ação |
|------|------------------------|------------------------|------|
| Configuração PWA (BD) | Sim (pwa_config, pwa_config.php) | Não (tabela não existe) | Migração SQL + uso do pacote |
| Manifest dinâmico | Sim (manifest.php, pwa_functions) | Não (manifest.json estático) | Apontar para manifest.php do módulo quando ativado |
| Service Worker (cache + push) | Sim (sw.js no pacote) | sw.js “matador de cache” | Usar sw.js do pacote quando PWA ativo |
| Push (VAPID, subscriptions, envio) | Sim (web_push_helper + vendor) | Não | Manter vendor/composer + endpoints de registro/envio |
| Página admin PWA (venda + config) | Não (só referência a admin_pwa) | Não | Criar view admin_pwa + lógica ativado/não ativado |
| Menu e rota admin PWA | Não | Não | Adicionar em admin.php |
| API admin (get/save config, upload ícone, status) | Não | Não | Implementar em admin_api.php |
| Ativação por upload de arquivo | Não | Não | Verificar file_exists() e alternar conteúdo da tela |

A única correção já feita no pacote foi trocar **system_settings** por **configuracoes_sistema** em `pwa_config.php` para compatibilidade com o SinergiaCore.

Se quiser, o próximo passo pode ser: (1) criar o SQL de migração das três tabelas, (2) esboço da `admin_pwa.php` (blocos "venda" vs "config") e (3) os blocos de código para as actions da `admin_api.php` (get_pwa_status, get_pwa_config, save_pwa_config, upload_pwa_icon).

---

## 7. Exemplo de migração SQL (pwa_tables.sql)

```sql
-- Tabela de configuração do PWA (uma linha por ambiente)
CREATE TABLE IF NOT EXISTS `pwa_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app_name` varchar(255) NOT NULL DEFAULT 'Plataforma',
  `short_name` varchar(50) NOT NULL DEFAULT 'App',
  `description` text,
  `icon_path` varchar(500) DEFAULT NULL,
  `theme_color` varchar(20) NOT NULL DEFAULT '#32e768',
  `background_color` varchar(20) NOT NULL DEFAULT '#ffffff',
  `display_mode` varchar(30) NOT NULL DEFAULT 'standalone',
  `start_url` varchar(255) NOT NULL DEFAULT '/',
  `scope` varchar(255) NOT NULL DEFAULT '/',
  `push_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `vapid_public_key` text,
  `vapid_private_key` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inscrições para notificações push
CREATE TABLE IF NOT EXISTS `pwa_push_subscriptions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) unsigned NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint` (endpoint(255)),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de notificações enviadas (opcional)
CREATE TABLE IF NOT EXISTS `pwa_push_notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text,
  `url` varchar(500) DEFAULT NULL,
  `icon` varchar(500) DEFAULT NULL,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `failed_count` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 8. Implementação realizada

Foi implementado o módulo PWA no projeto com os seguintes itens:

- **Migração SQL:** `migrations/pwa_tables.sql` — execute no banco (ex.: `mysql -u usuario -p nome_banco < migrations/pwa_tables.sql`).
- **Pasta `pwa/`** na raiz: `pwa_config.php`, `pwa_functions.php`, `manifest.php`, `sw.js`, `generate_vapid_keys.php`, `api/web_push_helper.php`.
- **Admin:** rota `admin_pwa` em `$paginas_permitidas_admin` e item de menu "PWA" no sidebar em `admin.php`.
- **View:** `views/admin/admin_pwa.php` — sem ativação mostra tela de compra/benefícios; com ativação mostra abas "Configuração Geral" e "Notificações Push".
- **API:** em `api/admin_api.php` as actions `get_pwa_status`, `get_pwa_config`, `save_pwa_config`, `upload_pwa_icon`.
- **Composer:** `composer.json` na raiz com `minishlink/web-push` ^7.0 (PHP 7.4); executado `composer update`, gerando `vendor/` e `vendor/autoload.php`.

**Como ativar o módulo (para liberar as configurações):**  
Crie um arquivo vazio chamado **`pwa_activated.key`** na **raiz do projeto** (mesmo nível que `admin.php`). Assim que o arquivo existir, ao acessar **Admin → PWA** as abas de configuração serão exibidas. Para testes locais: `echo. > pwa_activated.key` (Windows) ou `touch pwa_activated.key` (Linux/Mac).

**Uso do manifest dinâmico (opcional):**  
Quando o PWA estiver ativado, nas páginas em que o app for instalável você pode apontar o `<link rel="manifest">` para `/pwa/manifest.php` e o Service Worker para `/pwa/sw.js`.
