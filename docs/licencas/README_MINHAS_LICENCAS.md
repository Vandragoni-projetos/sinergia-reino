# Minhas Licenças - Como Funciona

## Fluxo do Revendedor

1. **Você comprou** o produto vitalício do vendedor (administrador)
2. **Área de membros** do vendedor → você faz login como cliente (tipo "usuario")
3. **Canto superior direito** → menu com "Editar Perfil", **"Minhas Licenças"**, "Sair"
4. **Minhas Licenças** → você gera licenças para:
   - Você mesmo (atualizar sua instalação quando houver novidades)
   - Seus clientes finais (ativar a plataforma white-label deles)

## Requisitos para "Minhas Licenças" funcionar

A instalação (do vendedor) precisa estar configurada como **Painel Master**:

### 1. Arquivo `.env`
```
GATEWAYPRO_MASTER_SECRET=sua_chave_secreta_qualquer
```

### 2. Banco de dados
Executar no phpMyAdmin (ou Admin > Configurações > Habilitar Painel Master):

```sql
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('is_master_panel', '1')
ON DUPLICATE KEY UPDATE valor = '1';

INSERT INTO configuracoes_sistema (chave, valor) VALUES ('master_secret_key', 'SUA_CHAVE_DO_ENV')
ON DUPLICATE KEY UPDATE valor = 'SUA_CHAVE_DO_ENV';
```
**Substitua `SUA_CHAVE_DO_ENV`** pelo mesmo valor de `GATEWAYPRO_MASTER_SECRET` no .env.

Ou use: `migrations/enable_master_panel.sql` (editando antes o valor do master_secret_key).
