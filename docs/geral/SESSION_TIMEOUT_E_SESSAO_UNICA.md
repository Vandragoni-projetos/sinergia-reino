# Timeout e Sessão Única

## Visão geral

- **Sessão única**: Apenas um login ativo por usuário; novo login em outro navegador/dispositivo invalida as demais.
- **Timeout por inatividade**: Sessão expira após X minutos sem atividade.
- **Heartbeat**: O painel (index, admin, área de membros) verifica a sessão a cada 20 segundos; se foi invalidada em outro dispositivo, redireciona automaticamente para o login (sem precisar dar refresh).

## Ordem de validação (em cada request)

1. `enforce_single_session()` — valida token no banco
2. `check_session_timeout()` — valida inatividade e atualiza `last_activity`

A ordem evita renovar `last_activity` em sessão já invalidada por login em outro dispositivo.

## Configuração

### Timeout (configuracoes_sistema)

| Chave                   | Valor padrão | Descrição                              |
|-------------------------|--------------|----------------------------------------|
| session_timeout_minutes | 120          | Minutos de inatividade até expirar     |

**Regras:**
- Mínimo: **60 minutos**
- Valores menores que 60 são ajustados automaticamente para 60.

```sql
-- Exemplo: 60 minutos
INSERT INTO configuracoes_sistema (chave, valor) VALUES ('session_timeout_minutes', '60')
ON DUPLICATE KEY UPDATE valor = '60';
```

## Diagnóstico (quando não funcionar)

Acesse `/api/session_debug?key=SESSION_DEBUG_2024` (logado como admin) ou defina `SESSION_DEBUG_KEY` no `.env`.

O script retorna:
- `column_session_token_exists`: se a coluna existe no banco
- `session_token_in_session`: se o token está na sessão atual
- `valid_token_in_db`: token armazenado no banco para o usuário
- `tokens_match`: se coincidem
- `enforce_would_invalidate`: se a sessão seria invalidada

**Remover** `api/session_debug.php` em produção após testar.

---

## Resposta API (401)

Quando a sessão é invalidada, a API retorna:

```json
{
  "success": false,
  "error": "session_timeout",
  "message": "Sessão expirada por inatividade. Faça login novamente."
}
```

ou

```json
{
  "success": false,
  "error": "session_replaced",
  "message": "Sessão encerrada. Você entrou em outro navegador ou dispositivo."
}
```
