# Migrations

A implantação do banco de dados é feita **exclusivamente** com o arquivo **`Base_de_Dados_Instalacao.sql`** na raiz do projeto.

Esse arquivo contém:
- Schema completo (todas as tabelas, índices, FKs, trigger)
- Seed mínimo (communities, banner_badges, configuracoes, configuracoes_sistema, saas_planos, saas_config_admin, plugins, 1 usuário admin)

Não é necessário executar outros scripts SQL para instalação. Os scripts incrementais que existiam nesta pasta foram removidos por redundância.

Ver **INSTALL.md** (raiz) para o passo a passo de instalação.

### Scripts incrementais (opcionais)

- **single_session.sql** — Adiciona coluna `session_token` na tabela `usuarios` para permitir apenas uma sessão ativa por usuário (novo login em outro navegador/dispositivo invalida as demais). Execute manualmente se quiser usar esse recurso.

- **nicho_separation.sql** — Separação por nicho (vitrine segmentada). Cria tabelas `nichos`, `categorias`, `produto_categorias` e adiciona `nicho_id` em `produtos` e `usuarios`. Usuários só veem produtos do seu nicho. Execute manualmente para habilitar esse recurso.

- **checkout_internacional.sql** — Checkout BR + Internacional. Adiciona feature flags (payment_routing_enabled, stripe_enabled, paypal_enabled), moedas, price_usd/eur em produtos e tabela payment_decline_logs. Ver docs/geral/PLANO_CHECKOUT_BR_INTERNACIONAL.md.
