# Sistema de Licenças - GatewayPro

## Visão Geral

O GatewayPro usa um sistema de licenças para ativar instalações (painéis clientes). O **painel master** gera licenças; os **painéis clientes** ativam com a chave.

## Tipos de Licença

| Tipo | Duração |
|------|---------|
| VITALICIO | Sem expiração |
| MENSAL | 30 dias |
| ANUAL | 365 dias |
| SEMESTRAL | 180 dias |

## Escopos

- **SYSTEM**: Ativa o sistema inteiro
- **PRODUCT**: Libera produto específico
- **COMMUNITY**: Área de membros específica
- **USER_LIMIT**: Limite de usuários

## Migração

Execute antes de usar o sistema evoluído:

```bash
mysql -u USUARIO -p BANCO < migrations/add_licencas_evolucao.sql
```

## Arquivos Principais

- `helpers/license_helper.php` - Validação via webhook, configuracoes_sistema
- `helpers/license_service.php` - Gerar, ativar, validar (evolução)
- `api/license_api.php` - API de validação (master)
- `ativacao.php` - Tela de ativação (cliente)
