# Migrations

A implantação do banco de dados é feita **exclusivamente** com o arquivo **`Base_de_Dados_Instalacao.sql`** na raiz do projeto.

Esse arquivo contém:
- Schema completo (todas as tabelas, índices, FKs, trigger)
- Seed mínimo (communities, banner_badges, configuracoes, configuracoes_sistema, saas_planos, saas_config_admin, plugins, 1 usuário admin)

Não é necessário executar outros scripts SQL para instalação. Os scripts incrementais que existiam nesta pasta foram removidos por redundância.

Ver **INSTALL.md** (raiz) para o passo a passo de instalação.
