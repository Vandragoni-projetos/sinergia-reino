# Docker — SinergIA Core

Imagem e orquestração para rodar o projeto com **PHP 8.2 + Apache** e **MariaDB 10.11**.

---

## Uso rápido

Na **raiz do projeto** (onde está o `docker-compose.yml`):

```bash
docker compose up -d
```

A aplicação fica em **http://localhost:8080**.  
Login padrão: `admin@example.com` / `password` (troque no primeiro acesso).

---

## O que foi criado

| Arquivo | Descrição |
|--------|-----------|
| **docker/Dockerfile** | Imagem da aplicação: PHP 8.2-apache, extensões (pdo_mysql, mbstring, zip, etc.), DocumentRoot na raiz do projeto. |
| **docker/Dockerfile.db** | Imagem do banco: MariaDB 10.11, init com `Base_de_Dados_Instalacao.sql`. |
| **docker-compose.yml** | Serviços `app` (porta 8080) e `db`, variáveis de ambiente, volumes para `uploads/` e dados do MySQL. |
| **.dockerignore** | Evita copiar `.env`, `.git`, logs, etc. para a imagem. |

---

## Variáveis de ambiente (docker-compose)

No `docker-compose.yml`, serviço `app`:

- **DB_HOST**, **DB_USER**, **DB_PASS**, **DB_NAME** — conexão com o MariaDB (devem bater com `MYSQL_*` do serviço `db`).
- **APP_TIMEZONE** — ex.: `America/Sao_Paulo`.
- **GATEWAYPRO_MASTER_SECRET** — opcional; para bypass de ativação (ver INSTALL.md).

Para produção, use `env_file: .env` ou defina as variáveis no painel (ex.: Easypanel) em vez de valores fixos no compose.

---

## Build manual da imagem da aplicação

```bash
docker build -f docker/Dockerfile -t sinergia-core:latest .
```

Para subir só o banco e usar a imagem já construída:

```bash
docker compose up -d db
docker run -d --name sinergia-app --network sinergia-core_default -p 8080:80 -e DB_HOST=db -e DB_USER=gatewaypro -e DB_PASS=gatewaypro_secret_2024 -e DB_NAME=checkout sinergia-core:latest
```

(Ajuste o nome da rede se o projeto tiver outro nome.)

---

## Volumes

- **uploads_data** — conteúdo de `uploads/` (persistido entre restarts).
- **db_data** — dados do MariaDB (persistidos entre restarts).

---

## Referências

- **Instalação geral:** [../INSTALL.md](../INSTALL.md)
- **Deploy em VPS:** [DEPLOY_VPS.md](DEPLOY_VPS.md)
