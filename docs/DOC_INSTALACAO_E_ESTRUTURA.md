# Documentação: Instalação, Estrutura e Funcionalidades

Sistema PHP (GatewayPro/SinergIA) para checkout próprio, área de membros e gestão de infoprodutos. Produção na Hostinger; banco MariaDB/MySQL.

---

## 1. Funcionalidades

### 1.1 Painel Administrativo (admin)
- **Dashboard**: visão geral da plataforma.
- **Usuários**: criar/editar infoprodutores.
- **Configurações**: SMTP, e-mail de entrega, nome da plataforma.
- **Configurações visuais**: cor primária, logo, favicon, imagem de login, tema (theme_json).
- **Plugins**: ativar/desativar (ex.: Modo SaaS).
- **Relatórios e logs**: vendas, segurança, auditoria.
- **Revenda autorizada**: gestão de revendedores.
- **Licenças** (se painel master): gerar chaves de ativação.

### 1.2 Painel do Infoprodutor (index?pagina=...)
- **Dashboard**: vendas, ticket médio, produtos.
- **Produtos**: CRUD; tipos: link, PDF, área de membros.
- **Configuração de produto**: geral, checkout, métodos de pagamento, order bumps, rastreamento (pixels), ofertas exclusivas.
- **Área de membros**: cursos, módulos, aulas (vídeo YouTube/Vimeo, arquivos).
- **Vendas**: listagem e detalhes.
- **Clientes**: lista de compradores/alunos.
- **Integrações**: gateways (Mercado Pago, PushinPay, Efí, HyperCash, etc.), webhooks, UTMfy, Evolution API.
- **Tracking**: produtos rastreados e eventos.
- **Banners**: criação e exibição no feed/dashboard do cliente.
- **Clonar site**: páginas clonadas com editor HTML.
- **Planos** (Modo SaaS): planos Free/Premium e assinaturas.
- **Perfil**: dados e foto.

### 1.3 Área do Cliente (membro)
- **Login/registro**: `member_login`, `member_register`.
- **Dashboard**: produtos adquiridos, ofertas exclusivas, banners.
- **Curso**: visualização de módulos/aulas, progresso, arquivos.
- **Licenças** (se habilitado): minhas licenças.
- **Proteção**: watermark, anti-print, anti-devtools (configurável por comunidade).

### 1.4 Checkout e Pagamento
- **Checkout**: página por produto (hash); Pix, cartão, boleto (conforme gateway).
- **Processamento**: `process_payment.php`, `process_free.php` (registro grátis).
- **Webhooks**: notificações de aprovação/pendência/rejeição etc.
- **E-mail de entrega**: template em `configuracoes` (entrega de acesso/PDF/link).

### 1.5 Multi-tenant (communities)
- **Communities**: slugs (club, mkd, flow, kids) para subdomínios/contexto.
- **Configurações e tema** podem ser por comunidade.

---

## 2. Estrutura do Projeto

```
├── api/                    # Endpoints (vendas_actions, video_embed, admin_api via api.php)
├── config/
│   ├── config.php         # Conexão PDO, getSystemSetting, env()
│   ├── env_loader.php     # Carrega .env (DB_*, APP_TIMEZONE, etc.)
│   ├── load_settings.php   # Tema, cores, logos
│   └── theme_helper.php
├── helpers/               # Acesso, community, license, security, plugin_loader, etc.
├── views/
│   ├── admin/             # Telas do painel administrativo
│   ├── member/            # Login, dashboard e curso do cliente
│   ├── produto_config/    # Abas da configuração do produto
│   └── *.php              # Dashboard, produtos, vendas, integracoes, etc.
├── gateways/              # Efí, HyperCash, Beehive
├── migrations/            # SQL de migração
├── PHPMailer/             # Envio de e-mail
├── legal/                 # Termos, privacidade (gerados pelo Editor de Checkout; se não gravável, usa uploads/legal/)
├── uploads/               # Arquivos enviados (config, banners, aulas)
├── index.php              # Entrada infoprodutor (pagina= dashboard|produtos|...)
├── dashboard.php          # Redirecionamento (admin vai para /admin)
├── checkout.php           # Página de checkout
├── process_payment.php    # Processamento de pagamento
├── process_free.php       # Registro/compra grátis
├── member_area_dashboard.php  # Entrada área de membros (cliente)
├── api.php                # API REST (admin, vendas, etc.)
├── ativacao.php           # Ativação de licença
└── config/.htaccess       # Proteção da pasta config
```

**Entradas principais:**
- **Admin**: `/admin` → carrega layout admin e `?pagina=...` (admin_dashboard, admin_usuarios, etc.).
- **Infoprodutor**: `/login` → após login redireciona para `/admin` (se admin) ou `/member_area_dashboard` (se cliente) ou `/index` (se infoprodutor). O `index.php` usa `?pagina=dashboard|produtos|vendas|...`.
- **Cliente**: `/member_login` → `/member_area_dashboard`; curso em `member_course_view.php`.
- **Checkout**: `/checkout?p=<hash>`.

---

## 3. Pontos de Atenção / Possível Duplicação

- **Configuração de tema/visual**: `configuracoes_sistema` (theme_json, cor_primaria, logos) + `config/load_settings.php` e `theme_helper.php`. Verificar se não há duplicação de lógica entre painel e checkout.
- **URL de login da área de membros**: chave `member_area_login_url` em `configuracoes`; usada em e-mails de entrega. Manter alinhada com a URL real (ex.: `https://seudominio.com/member_login`).
- **Dois arquivos de ações de vendas**: `api/vendas_actions.php` e `vendas_actions.php` na raiz — verificar se ambos são usados ou se um pode ser removido.
- **Redirecionamentos de login**: vários `header("location: /login")` ou `"/member_login"` ou `"/admin"`; garantir que não haja loop e que base URL esteja correta em produção (HTTPS, sem barra final).
- **Plugins**: pasta `plugins/` (ex.: saas) carregada por `plugin_loader.php`; o painel lê a tabela `plugins`. Manter consistência entre registro no BD e arquivos na pasta.

---

## 4. Passo a Passo de Instalação / Implantação

### 4.1 Requisitos
- PHP 7.2+ (recomendado 7.4 ou 8.x)
- MySQL 5.7+ ou MariaDB 10.3+
- Extensões: PDO, pdo_mysql, mbstring, json, openssl

### 4.2 Banco de Dados

1. **Primeira instalação (banco vazio)**  
   - Criar o banco (ex.: pelo painel da Hostinger).  
   - Importar **`Base_de_Dados_Instalacao.sql`** (schema + seed mínimo: communities, banner_badges, configuracoes, configuracoes_sistema, saas_planos, saas_config_admin, plugins, 1 admin).  
   - **Credenciais padrão do admin**:  
     - Login: `admin@example.com`  
     - Senha: `password`  
     - **Trocar a senha no primeiro acesso.**

2. **Limpar banco já existente (opcional)**  
   - Fazer backup completo.  
   - Executar **`Base_de_Dados_Limpa_Tabelas_Operacionais.sql`** para truncar tabelas operacionais (vendas, produtos, acessos, etc.).  
   - Se quiser manter só 1 admin, descomentar no final do script as linhas `DELETE FROM usuarios WHERE id != 1` e `ALTER TABLE usuarios AUTO_INCREMENT = 2`.

### 4.3 Arquivo .env

1. Na **raiz do projeto**, criar o arquivo **`.env`** (copiar de **`.env.example`**).

2. Ajustar as variáveis:

```env
# Banco de dados (dados do painel Hostinger ou do seu servidor)
DB_HOST=localhost
DB_USER=seu_usuario_banco
DB_PASS=sua_senha_banco
DB_NAME=nome_do_banco

# Fuso horário
APP_TIMEZONE=America/Sao_Paulo

# Opcional: API / autenticação
TOKEN_AUTH_SECRET=sua_chave_secreta

# Opcional: painel master (gerar licenças)
GATEWAYPRO_MASTER_SECRET=chave_secreta_master
```

3. **Segurança**:  
   - Nunca commitar `.env` no Git.  
   - Manter permissões restritas (ex.: 640).  
   - Em produção, usar senhas fortes e `DB_HOST` correto (ex.: localhost ou IP interno).

4. O **`config/config.php`** usa `env('DB_HOST', 'localhost')`, `env('DB_USER', ...)`, etc. Se `.env` não existir, os defaults do `config.php` são usados (menos seguro em produção).

### 4.4 Arquivos no Servidor

1. Enviar todos os arquivos do projeto para a pasta pública (ex.: `public_html`).
2. Garantir que a **pasta `config/`** não seja acessível diretamente (`.htaccess` já presente).
3. **Pasta `uploads/`**: permissão de escrita (755 ou 775) para o usuário do servidor (uploads de config, banners, aulas).
4. **Document root**: deve apontar para a raiz onde estão `index.php`, `checkout.php`, `member_area_dashboard.php`, etc.

### 4.5 Configurações Pós-Instalação

1. Acessar o **painel administrativo** com o usuário admin e **alterar a senha**.
2. Em **Configurações** (admin):  
   - SMTP (host, porta, usuário, senha, remetente).  
   - **URL de login da área de membros** (`member_area_login_url`) com a URL real (ex.: `https://seudominio.com/member_login`).
3. Em **Configurações visuais**: logos, favicon, cor primária, tema (se aplicável).
4. **Licença**: se o sistema usar validação de licença, ativar com a chave no painel ou via `ativacao.php`, conforme documentação de licenças.
5. **Gateways**: configurar no admin (Mercado Pago, Efí, etc.) e, se necessário, URLs de webhook no painel do gateway.

### 4.6 Cron (opcional)

- Recuperação de carrinho e e-mail marketing: configurar cron para os scripts indicados na documentação interna (ex.: chamada a um endpoint ou script PHP em intervalo definido).

---

## 5. Resumo dos Arquivos SQL Gerados

| Arquivo | Uso |
|--------|-----|
| **Base_de_Dados_Instalacao.sql** | Carga inicial para 1ª instalação: schema completo + seed mínimo (communities, banner_badges, configuracoes, configuracoes_sistema, saas_planos, saas_config_admin, plugins, 1 admin). Sem dados de teste/operacionais. |
| **Base_de_Dados_Limpa_Tabelas_Operacionais.sql** | Limpar um banco já existente: TRUNCATE das tabelas operacionais (vendas, produtos, acessos, etc.). Opcional: manter só o admin (id=1). |
| **Banco_de_Dados.sql** | Dump completo de referência (com dados de teste); não usar como instalação “clean”. |

---

## 6. Referência Rápida .env

| Variável | Obrigatório | Descrição |
|----------|-------------|-----------|
| DB_HOST | Sim | Host do MySQL (ex.: localhost) |
| DB_USER | Sim | Usuário do banco |
| DB_PASS | Sim | Senha do banco |
| DB_NAME | Sim | Nome do banco |
| APP_TIMEZONE | Não | Fuso (default America/Sao_Paulo) |
| TOKEN_AUTH_SECRET | Não | Chave para API/auth |
| GATEWAYPRO_MASTER_SECRET | Não | Habilitar painel master e gerar licenças |

O carregamento do `.env` é feito em **`config/env_loader.php`**, chamado por **`config/config.php`** antes de definir `DB_*` e `date_default_timezone_set`.
