# O que achei do projeto – análise e melhorias sugeridas

Visão geral com base na varredura e no código que tivemos contato (PWA, API, config, rotas, segurança).

---

## Pontos positivos

- **Segurança na superfície:** `.htaccess` bem cuidado: bloqueia config, migrations, helpers, gateways, .env, composer, execução de PHP em uploads, acesso direto a views.
- **Separação de papéis:** Admin, infoprodutor e cliente (área de membros) com redirecionamento por tipo de usuário.
- **APIs:** `display_errors` desligado em api.php e admin_api.php; erros vão para log, evitando vazar stack no JSON.
- **Banco:** Uso de PDO com prepared statements nos trechos vistos; timezone e charset configurados.
- **Configuração:** Uso de `.env` (env_loader) para credenciais; funções centralizadas para `configuracoes_sistema`.
- **CSRF:** Existe `config/csrf_helper.php` (gera/valida token, expiração 1h) e funções em `security_helper.php`.
- **Módulo PWA:** Bem integrado (manifest, SW, push, normalização de chaves, endpoints separados para admin e usuário).

---

## Possíveis falhas / riscos

### 1. Mensagem de erro do banco no config (config.php)

```php
} catch (PDOException $e) {
    die("ERRO: Não foi possível conectar ao banco de dados. " . $e->getMessage());
}
```

- **Problema:** Em produção, `$e->getMessage()` pode expor host, nome do banco ou detalhes do servidor.
- **Sugestão:** Em produção, logar o erro (`error_log($e->getMessage())`) e dar `die()` com mensagem genérica (ex.: "Erro de conexão. Tente mais tarde.").

### 2. Senha padrão do banco no código (config.php)

```php
define('DB_PASS', env('DB_PASS', 'gatewaypro_secret_2024'));
```

- **Problema:** Se o `.env` não existir ou não tiver `DB_PASS`, fica uma senha padrão no código (e em repositório, se esse arquivo for versionado).
- **Sugestão:** Em produção, não usar default de senha; se `env('DB_PASS')` for vazio, exibir mensagem genérica e não conectar (ou exigir .env válido na instalação).

### 3. Uso de CSRF no login e em formulários críticos

- Existe `csrf_helper.php` e `validate_csrf_request()`, mas o login e vários formulários podem não estar enviando/validando token CSRF.
- **Risco:** Ataques de Cross-Site Request Forgery em login, alteração de senha ou ações sensíveis do admin.
- **Sugestão:** Garantir que login, “esqueci minha senha”, formulários do admin (incl. PWA) e qualquer POST que altere dados recebam e validem o token CSRF (ou equivalente com mesmo nível de proteção).

### 4. API muito grande (api/api.php)

- O arquivo tem milhares de linhas e muitas `if ($action === '...')`.
- **Problema:** Dificulta manutenção, testes e revisão de segurança por ação.
- **Sugestão:** A médio prazo, ir extraindo ações para controllers ou módulos (ex.: um por domínio: vendas, perfil, notificações, PWA, etc.) e manter o api.php como roteador fino.

### 5. Logs de debug em produção (api/api.php)

- Ex.: `error_log("API: Ação recebida: " . $action);` em toda requisição.
- **Problema:** Gera muito ruído no log e pode expor padrões de uso.
- **Sugestão:** Usar um nível de log (ex.: só em desenvolvimento ou quando `APP_DEBUG=true`) ou remover logs muito verbosos em produção.

### 6. Tailwind via CDN em produção

- O console já avisa: `cdn.tailwindcss.com` não deve ser usado em produção.
- **Sugestão:** Em produção, usar Tailwind via build (PostCSS/CLI) e servir CSS estático; reduz tamanho e melhora desempenho.

---

## Melhorias gerais (não necessariamente falhas)

- **Testes:** Não apareceram testes automatizados (PHPUnit, etc.). Mesmo um conjunto pequeno de testes para login, APIs críticas e helpers ajudaria em refatorações e deploys.
- **Documentação de APIs:** Ter uma lista (ou doc) das actions da `api.php` e da `admin_api.php` com método (GET/POST), parâmetros e quem pode acessar facilita manutenção e auditoria.
- **Rate limiting:** Em login, “esqueci senha” e endpoints públicos, limitar tentativas por IP ou por usuário reduz força bruta e abuso.
- **Headers de segurança:** Considerar adicionar (no servidor ou em um bootstrap) headers como `X-Content-Type-Options: nosniff`, `X-Frame-Options`, `Content-Security-Policy` (ajustando conforme necessário para o PWA e recursos externos).
- **Migrations:** Manter as migrações (ex.: `pwa_tables.sql`) versionadas e um pequeno README ou script que indique a ordem de execução e dependências entre elas.

---

## Resumo

O projeto está organizado, com boa separação admin/infoprodutor/cliente, proteção na camada web (htaccess) e uso de prepared statements. Os pontos mais sensíveis são: **não expor detalhes do banco em produção**, **não depender de senha padrão no código**, **garantir CSRF onde há POST crítico** e **reduzir ruído de log e uso de CDN em produção**. As demais sugestões são evoluções (estrutura da API, testes, docs, rate limit, headers e migrations) para deixar o projeto mais fácil de manter e mais seguro a longo prazo.
