# Guia de Instalação e Implantação

Sistema PHP (GatewayPro/SinergIA) — checkout próprio, área de membros e gestão de infoprodutos. Este guia cobre instalação em servidor (com foco em Hostinger) e checklist pós-instalação. Para **VPS** (Ubuntu/Debian, Nginx, MariaDB), ver **`docs/DEPLOY_VPS.md`**. Para **Docker** (imagem PHP + MariaDB), ver **`docker-compose.yml`** na raiz e **`docker/README.md`**.

---

## 1. Requisitos

### 1.1 Servidor

- **PHP:** 7.2 ou superior (recomendado 7.4 ou 8.x).
- **Banco de dados:** MySQL 5.7+ ou MariaDB 10.3+.
- **Servidor web:** Apache (recomendado) ou Nginx, com suporte a `.htaccess` se usar Apache.

### 1.2 Extensões PHP

- `pdo`
- `pdo_mysql`
- `mbstring`
- `json`
- `openssl`

Verificar com: `php -m` ou criar um `phpinfo()` temporário.

### 1.3 Permissões

- **Pasta `uploads/`:** gravável pelo usuário do servidor (755 ou 775). Usada para:
  - Configurações (logo, favicon, imagem de login, selo, etc.).
  - Banners.
  - Arquivos de aulas (aula_arquivos).
  - Fotos de perfil e imagens de produtos/cursos/módulos.
- **Pasta `config/`:** não precisa ser gravável em produção; deve ser **não acessível diretamente** via URL (uso de `config/.htaccess` no Apache).

### 1.4 Configuração do Servidor

- **Document root:** deve apontar para a raiz do projeto (onde estão `index.php`, `checkout.php`, `member_area_dashboard.php`, `admin.php`, etc.).
- **PHP:** `display_errors` desligado em produção; `log_errors` ativado.
- Se usar Apache: `AllowOverride All` para o diretório do projeto (para `.htaccess` funcionar).

---

## 2. Passo a Passo — Hostinger

### 2.1 Upload dos Arquivos

1. Acesse o **Gerenciador de Arquivos** (ou use FTP/SFTP) no painel Hostinger.
2. Navegue até a pasta pública (ex.: `public_html`).
3. Faça upload de **todos** os arquivos do projeto (mantendo a estrutura de pastas: `api/`, `config/`, `helpers/`, `views/`, `gateways/`, `migrations/`, `PHPMailer/`, `legal/`, `uploads/`, etc.).
4. Se já existir conteúdo em `public_html`, faça backup e depois substitua ou mescle conforme necessário.

### 2.2 Criar o Banco de Dados

1. No painel Hostinger, abra **Bancos de Dados MySQL** (ou **MySQL Databases**).
2. Crie um **novo banco de dados** (ex.: `uXXXXX_nomeprojeto`).
3. Crie um **usuário** de banco e **senha forte**.
4. Associe o usuário ao banco, com **todos os privilégios**.
5. Anote: **nome do banco**, **usuário** e **senha** (e host, normalmente `localhost`).

### 2.3 Importar o SQL

1. Acesse **phpMyAdmin** (link no painel Hostinger para o mesmo banco).
2. Selecione o banco criado.
3. Aba **Importar** → escolha o arquivo **`Base_de_Dados_Instalacao.sql`**.
4. Execute a importação. Confirme que não houve erros.
5. Verifique se as tabelas foram criadas e se existem dados em `communities`, `usuarios` (1 admin), `configuracoes`, `banner_badges`, etc.

### 2.4 Configurar o .env

1. Na **raiz do projeto** (mesmo nível de `index.php`), crie o arquivo **`.env`** (copie de **`.env.example`**).
2. Preencha com os dados do banco e do ambiente:

```env
DB_HOST=localhost
DB_USER=seu_usuario_banco
DB_PASS=sua_senha_banco
DB_NAME=nome_do_banco

APP_TIMEZONE=America/Sao_Paulo

TOKEN_AUTH_SECRET=
GATEWAYPRO_MASTER_SECRET=
```

3. **Nunca** commitar o `.env` no Git; manter permissões restritas (ex.: 640).
4. O `config/config.php` lê essas variáveis via `config/env_loader.php`; se `.env` não existir, são usados os valores padrão do `config.php` (menos seguro em produção).

### 2.5 Testar o Acesso

1. Acesse a URL do site (ex.: `https://seudominio.com`).
2. **Login admin:** use as credenciais padrão da instalação:
   - E-mail: `admin@example.com`
   - Senha: `password`
3. **Primeiro acesso:** por padrão o sistema **solicita a Chave de Ativação** (tela `/ativacao`). Isso é intencional para que o usuário conheça a funcionalidade. Na **aula explicativa** é explicado o **bypass opcional** (acesso sem chave); após a aula, fica a critério do usuário aplicar ou não (ver seção **Ativação e bypass** mais abaixo).
4. **Troque a senha** no primeiro acesso (perfil ou configurações).
5. Verifique:
   - Painel administrativo (`/admin`).
   - Login infoprodutor (criar um usuário infoprodutor no admin e testar `/index`).
   - Página de checkout (criar um produto e acessar `/checkout?p=<hash_do_produto>`).
   - Área de membros (`/member_login` e `/member_area_dashboard` após login como cliente).

---

## 3. Variáveis do .env e Exemplos

Use o arquivo **`.env.example`** como modelo. Abaixo, descrição e exemplos **sem dados reais**.

| Variável | Obrigatório | Descrição | Exemplo |
|----------|-------------|-----------|---------|
| `DB_HOST` | Sim | Host do MySQL/MariaDB | `localhost` |
| `DB_USER` | Sim | Usuário do banco | `seu_usuario` |
| `DB_PASS` | Sim | Senha do banco | `sua_senha_segura` |
| `DB_NAME` | Sim | Nome do banco | `seu_banco` |
| `APP_TIMEZONE` | Não | Fuso horário PHP | `America/Sao_Paulo` |
| `TOKEN_AUTH_SECRET` | Não | Chave para API/autenticação | (string segura) |
| `GATEWAYPRO_MASTER_SECRET` | Não | Ver seção **Ativação e licenças** abaixo | (string segura) |

**Ativação e licenças**

- **Comportamento padrão:** Na primeira instalação o sistema **solicita a Chave de Ativação** ao tentar logar (admin ou infoprodutor). O usuário é redirecionado para `/ativacao`. Isso permite conhecer a funcionalidade; na **aula explicativa** é explicado o bypass opcional.
- **Bypass opcional (primeiro acesso sem chave):** Se esta for a sua única plataforma e você não quiser depender de chave, pode habilitar o **tratamento bypass**: (1) gere uma string segura e defina no `.env` como `GATEWAYPRO_MASTER_SECRET=sua_string_segura`; (2) substitua o conteúdo da função `isMasterPanel()` no arquivo **`helpers/master_helper.php`** pelo **tratamento bypass** descrito na seção **Ativação e bypass** abaixo. Após isso, o primeiro acesso não exige chave. A aplicação do bypass é **por conta do usuário**.
- **Instalação que usa chave:** A chave inserida em `/ativacao` é validada por um serviço externo (webhook). Quem gera a chave pode ser o Painel Master (Admin → Configurações → Gerar Nova Licença) ou o provedor do serviço de licenças. Se não aplicar o bypass, obtenha uma chave válida e insira na tela de ativação.

**Ativação e bypass (código opcional)**

O arquivo **`helpers/master_helper.php`** é enviado **sem bypass**: o primeiro acesso solicita a licença. Opcionalmente, após a aula explicativa, o usuário pode trocar a função `isMasterPanel()` pelo tratamento com bypass abaixo (substituir desde `function isMasterPanel()` até o `}` correspondente, mantendo o restante do arquivo).

```php
/**
 * Retorna true se esta instalação é o Painel Master (não exige chave de ativação).
 * - Bypass em instalação nova: se GATEWAYPRO_MASTER_SECRET estiver no .env e license_key
 *   estiver vazio, considera como master para permitir primeiro acesso e habilitar o painel no admin.
 */
function isMasterPanel() {
    $envSecret = getenv('GATEWAYPRO_MASTER_SECRET');
    if (empty($envSecret)) {
        return false;
    }
    $licenseKey = getSystemSetting('license_key', '');
    // Instalação nova: secret no .env e ainda sem chave → tratar como master para primeiro acesso
    if ($licenseKey === '') {
        return true;
    }
    $isMasterFlag = getSystemSetting('is_master_panel', '0') === '1';
    if (!$isMasterFlag) {
        return false;
    }
    $masterSecretKey = getSystemSetting('master_secret_key', '');
    if (empty($masterSecretKey) || !hash_equals($envSecret, $masterSecretKey)) {
        return false;
    }
    return true;
}
```

Requisitos para o bypass: definir `GATEWAYPRO_MASTER_SECRET` no `.env` e aplicar o código acima em `helpers/master_helper.php`. O usuário fica ciente das funcionalidades; a decisão de usar ou não é dele.

**Exemplo mínimo (produção):**

```env
DB_HOST=localhost
DB_USER=meu_usuario_db
DB_PASS=MinHaS3nHaF0rt3!
DB_NAME=meu_banco_plataforma

APP_TIMEZONE=America/Sao_Paulo

TOKEN_AUTH_SECRET=
GATEWAYPRO_MASTER_SECRET=
```

O carregamento do `.env` é feito em **`config/env_loader.php`**, chamado por **`config/config.php`** antes de definir `DB_*` e `date_default_timezone_set`.

---

## 4. Checklist Pós-Instalação

- [ ] **Alterar senha do admin** (primeiro acesso).
- [ ] **Configurações gerais (admin):**
  - [ ] Nome da plataforma.
  - [ ] SMTP (host, porta, usuário, senha, remetente) e testar envio de e-mail.
  - [ ] URL de login da área de membros (`member_area_login_url`) — ex.: `https://seudominio.com/member_login`.
- [ ] **Configurações visuais (admin):**
  - [ ] Logo, favicon, imagem de fundo do login, selo (se usar).
  - [ ] Cor primária e tema (theme_json), se aplicável.
- [ ] **Licença:** Por padrão o primeiro acesso solicita chave em `/ativacao`. Opcionalmente, após a aula explicativa, aplicar o **bypass** (ver seção Ativação e bypass no INSTALL.md) e definir `GATEWAYPRO_MASTER_SECRET` no `.env` para acesso sem chave.
- [ ] **Gateway de pagamento:**
  - [ ] Configurar no admin (Mercado Pago, Efí, PushinPay, etc.) com credenciais de produção ou teste.
  - [ ] Configurar URLs de webhook no painel do gateway (ex.: `https://seudominio.com/api/process_payment` ou o endpoint indicado na documentação).
- [ ] **Testes funcionais:**
  - [ ] Criar um produto (tipo área de membros) e um curso com módulo e aula.
  - [ ] Fazer um teste de compra (ou registro grátis) e conferir e-mail de entrega e acesso à área de membros.
  - [ ] Testar upload em **uploads/** (ex.: logo em Configurações visuais, arquivo em aula).
- [ ] **Segurança:**
  - [ ] Remover ou restringir acesso a `phpinfo()` e arquivos de teste.
  - [ ] Garantir que a pasta `config/` não seja acessível pela URL.
  - [ ] Revisar permissões de arquivos (recomendado: 644 para PHP, 640 para `.env`).

---

## 5. Arquivos SQL de Referência

| Arquivo | Uso |
|--------|-----|
| **Base_de_Dados_Instalacao.sql** | Carga inicial para 1ª instalação (schema + seed mínimo). |
| **Base_de_Dados_Limpa_Tabelas_Operacionais.sql** | Opcional: limpar tabelas operacionais de um banco já existente. |
| **Banco_de_Dados.sql** | Dump completo de referência (com dados de teste); não usar como instalação “clean”. |

---

## 6. Suporte e Documentação Adicional

- **Implantação em VPS (Ubuntu/Debian, Nginx, PHP-FPM, MariaDB):** `docs/DEPLOY_VPS.md`
- **Funcionalidades e fluxos:** `docs/README_FUNCIONALIDADES.md`
- **Estrutura e instalação resumida:** `docs/DOC_INSTALACAO_E_ESTRUTURA.md`
- **Licenças:** `docs/README_LICENCAS.md` e `docs/DOC_LICENCAS_ANALISE.md` (se existirem)

Instalação e alterações devem ser feitas de forma **incremental e segura**; não alterar nomes de colunas/tabelas que impactem integrações de pagamento.
