# Implantação em VPS

Guia sugerido para rodar o SinergIA Core em um **VPS** (Ubuntu/Debian), com Nginx ou Apache, PHP-FPM e MariaDB.

---

## 1. Pré-requisitos da VPS

- **SO:** Ubuntu 22.04 LTS ou Debian 12 (recomendado).
- **Recursos mínimos:** 1 vCPU, 1 GB RAM (para poucos acessos); 2 vCPU e 2 GB RAM para produção tranquila.
- **Acesso:** SSH com usuário com `sudo`.
- **Domínio:** apontando o DNS (A ou CNAME) para o IP da VPS (opcional para SSL com Let's Encrypt).

---

## 2. Stack sugerida

| Componente   | Sugestão        | Alternativa   |
|--------------|-----------------|---------------|
| Servidor web | Nginx           | Apache        |
| PHP          | PHP 8.1 ou 8.2 (PHP-FPM) | — |
| Banco        | MariaDB 10.6+   | MySQL 8.x     |

---

## 3. Passo a passo (Ubuntu 22.04)

### 3.1 Atualizar e instalar pacotes

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mariadb-server php-fpm php-mysql php-mbstring php-json php-xml php-curl php-zip unzip git
```

Verificar extensões PHP: `php -m` (deve ter `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`).

### 3.2 MariaDB: criar banco e usuário

```bash
sudo mysql -e "
CREATE DATABASE sinergia_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sinergia_user'@'localhost' IDENTIFIED BY 'SENHA_FORTE_AQUI';
GRANT ALL PRIVILEGES ON sinergia_db.* TO 'sinergia_user'@'localhost';
FLUSH PRIVILEGES;
"
```

### 3.3 Enviar o projeto para a VPS

**Opção A — Git (se o código estiver em repositório):**

```bash
sudo mkdir -p /var/www
sudo chown $USER:$USER /var/www
cd /var/www
git clone https://seu-repo/sinergia-core.git
cd sinergia-core
```

**Opção B — Upload (SFTP/rsync):**

- Envie todos os arquivos do projeto para um diretório, por exemplo `/var/www/sinergia-core`.
- Mantenha a estrutura (raiz com `index.php`, `config/`, `helpers/`, `views/`, `uploads/`, etc.).

### 3.4 Importar o banco

```bash
mysql -u sinergia_user -p sinergia_db < /var/www/sinergia-core/Base_de_Dados_Instalacao.sql
```

### 3.5 Configurar o .env

```bash
cd /var/www/sinergia-core
cp .env.example .env
nano .env
```

Preencha algo como:

```env
DB_HOST=localhost
DB_USER=sinergia_user
DB_PASS=SENHA_FORTE_AQUI
DB_NAME=sinergia_db
APP_TIMEZONE=America/Sao_Paulo
TOKEN_AUTH_SECRET=
GATEWAYPRO_MASTER_SECRET=
```

Salve e restrinja permissões: `chmod 640 .env`.

### 3.6 Permissões

```bash
cd /var/www/sinergia-core
sudo chown -R www-data:www-data .
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;
sudo chmod -R 775 uploads
sudo chmod 640 .env
```

O servidor web (Nginx/Apache) costuma rodar como `www-data`; ajuste se o usuário for outro.

### 3.7 Nginx: virtual host

Crie um site, por exemplo:

```bash
sudo nano /etc/nginx/sites-available/sinergia
```

Conteúdo mínimo (substitua `seudominio.com` e o caminho se necessário):

```nginx
server {
    listen 80;
    server_name seudominio.com www.seudominio.com;
    root /var/www/sinergia-core;
    index index.php;

    access_log /var/log/nginx/sinergia_access.log;
    error_log  /var/log/nginx/sinergia_error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(env|htaccess|git) {
        deny all;
    }
}
```

Ative e teste:

```bash
sudo ln -s /etc/nginx/sites-available/sinergia /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Se a versão do PHP for 8.2, use `php8.2-fpm.sock` no `fastcgi_pass`.

### 3.8 Apache (alternativa)

Se preferir Apache:

```bash
sudo apt install -y apache2 libapache2-mod-php
sudo a2enmod rewrite
```

Virtual host em `/etc/apache2/sites-available/sinergia.conf`:

```apache
<VirtualHost *:80>
    ServerName seudominio.com
    DocumentRoot /var/www/sinergia-core
    <Directory /var/www/sinergia-core>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Ative: `sudo a2ensite sinergia && sudo systemctl reload apache2`.

### 3.9 PHP em produção

Em `/etc/php/8.1/fpm/php.ini` (ou o path da sua versão):

```ini
display_errors = Off
log_errors = On
```

Reinicie o PHP-FPM: `sudo systemctl restart php8.1-fpm`.

### 3.10 SSL com Let's Encrypt (HTTPS)

Com domínio apontando para a VPS:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d seudominio.com -d www.seudominio.com
```

Siga o assistente; o Certbot configura o Nginx para HTTPS e renovação automática.

---

## 4. Checklist pós-deploy

- [ ] Acessar `https://seudominio.com` e testar a página inicial.
- [ ] Fazer login admin (`admin@example.com` / `password`) e trocar a senha.
- [ ] Se aparecer tela de ativação: obter chave ou aplicar bypass conforme **INSTALL.md** (seção Ativação e bypass).
- [ ] Configurar SMTP e gateways de pagamento no admin.
- [ ] Testar upload em **Configurações** (logo) e em aula (arquivo) para validar permissões em `uploads/`.
- [ ] Garantir que `config/` não seja acessível pela URL (retorno 403 ou 404).

---

## 5. Segurança adicional (recomendado)

- **Firewall:** `sudo ufw allow 22 && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw enable`
- **SSH:** use chave em vez de senha; desative `PermitRootLogin` se não precisar.
- **Backups:** agende backup do banco e da pasta `uploads/` (cron + script ou ferramenta do provedor).
- **Atualizações:** mantenha o SO e o PHP atualizados (`apt update && apt upgrade`).

---

## 6. Easypanel + VPS + Cloudflare

Se você usa **Easypanel** na VPS com **Cloudflare** na frente, o projeto roda sem alteração de código.

- **VPS:** Instale o Easypanel no servidor (Docker). A aplicação e o banco rodam como serviços gerenciados pelo painel.
- **Easypanel:** Crie um serviço para a aplicação (PHP; use uma imagem com PHP-FPM + Nginx ou Apache). Document root = raiz do projeto (onde estão `index.php`, `admin.php`, etc.). Crie um serviço de banco (MySQL ou MariaDB), importe o **Base_de_Dados_Instalacao.sql** e configure as variáveis de ambiente (equivalente ao `.env`: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `APP_TIMEZONE`, etc.) no próprio Easypanel. Garanta volume persistente para **uploads/** e para os dados do banco.
- **Cloudflare:** No DNS, aponte o domínio (A ou CNAME) para o IP da VPS ou para o proxy do Easypanel. Use SSL/TLS **Full** ou **Full (strict)** se o Easypanel terminar HTTPS atrás do proxy. Opcional: proxy laranja (CDN), WAF e proteção DDoS.

Nenhuma alteração no código é necessária; apenas configure ambiente (DB_* e demais vars) e permissões/volumes no Easypanel.

---

## 7. Referências

- **Instalação geral e .env:** [../INSTALL.md](../INSTALL.md)
- **Estrutura do projeto:** [DOC_INSTALACAO_E_ESTRUTURA.md](DOC_INSTALACAO_E_ESTRUTURA.md)
