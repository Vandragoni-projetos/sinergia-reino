# Proposta: Evolução do Sistema de Licenças

## Arquitetura Nova (Híbrida)

### Princípios
- **Retrocompatibilidade**: Licenças existentes continuam funcionando.
- **Extensibilidade**: Novos tipos e escopos podem ser adicionados.
- **Server-side**: Todas as validações no backend.

### Tabela `licencas_geradas` (estendida)

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | int | PK |
| chave_licenca | varchar(64) | Chave única |
| tipo_licenca | varchar(32) | VITALICIO, MENSAL, ANUAL, SEMESTRAL |
| dias_validade | int | null=vitalício, 30, 365, 180 |
| **escopo** | enum | SYSTEM, COMMUNITY, PRODUCT, USER_LIMIT (novo) |
| **escopo_ref_id** | int | ID do produto/comunidade (novo) |
| status | varchar | disponivel, ativa, ativada, expirada, bloqueada, revogada |
| **owner_user_id** | int | Quem gerou (novo) |
| **assigned_user_id** | int | Quem está usando (novo) |
| aluno_email | varchar | Retrocompat |
| aluno_nome | varchar | Retrocompat |
| produto_id | int | Produto que concede direito |
| **observacoes** | text | (novo) |
| data_geracao | timestamp | |
| data_ativacao | timestamp | |
| data_expiracao | date | null=vitalício |
| instalacao_id | varchar | system_id |
| ip_ativacao | varchar | |

### Tipos de Licença
- **VITALICIO**: Sem expiração (dias_validade=null, data_expiracao=null)
- **MENSAL**: 30 dias
- **ANUAL**: 365 dias
- **SEMESTRAL**: 180 dias

### Escopos
- **SYSTEM**: Ativa o sistema inteiro (Gateway Pro white-label)
- **COMMUNITY**: Ativa comunidade/área de membros específica
- **PRODUCT**: Libera produto/curso específico
- **USER_LIMIT**: Define limite de usuários/infoprodutores

### Papéis
- **Admin Master**: Gerar qualquer licença, ver todas, revogar/bloquear
- **Infoprodutor/Aluno**: Gerar licenças conforme produto (gera_licenca), ver só as que gerou
- **Usuário Final**: Ver "Minhas Licenças", ativar chave em ativacao.php

### Fluxo de Validação
1. Cliente cola chave em ativacao.php
2. activateLicense() → validateLicenseKey() → webhook n8n → license_api.php (master)
3. license_api usa license_validate_local e license_activate_local (license_service)
4. Cliente salva em configuracoes_sistema
