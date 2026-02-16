# Proteção da Área de Membros

Camada de proteção para reduzir vazamento de conteúdo na área logada do Cliente Final.

## Arquivos Criados

| Arquivo | Descrição |
|---------|-----------|
| `migrations/009_member_area_protection.sql` | Tabela `security_events` + configs |
| `helpers/member_protection_helper.php` | `isMemberProtectionEnabled()`, `getProtectedMediaUrl()` |
| `views/includes/member_protection.php` | Watermark, anti-print, anti-devtools (JS+CSS) |
| `api/security_log.php` | Endpoint para registrar eventos de segurança |
| `media.php` | Endpoint de mídia/arquivos protegidos |
| `docs/MEMBER_AREA_PROTECTION.md` | Esta documentação |

## Arquivos Alterados

| Arquivo | Alterações |
|---------|------------|
| `views/member/member_area_dashboard.php` | Include proteção, `member-protected-content`, URLs de mídia protegidas |
| `views/member/member_course_view.php` | Include proteção, `member-protected-content`, banner/módulos/arquivos via `/media` |
| `views/member/member_licenses.php` | Include proteção, `member-protected-content` |

## Funcionalidades

### 1. Watermark (Rastreabilidade)
- Overlay fixo com: nome/email, ID, community slug, timestamp
- Repetido em diagonal, opacidade ~0.1
- Texto não selecionável
- Responsivo

### 2. Anti-Print
- CSS `@media print` oculta conteúdo
- Modal ao tentar Ctrl+P / Cmd+P
- Log: `print_attempt`

### 3. Anti-DevTools
- Bloqueia contextmenu (botão direito)
- Bloqueia F12, Ctrl+Shift+I/J, Ctrl+U, Ctrl+S
- Detecta abertura de DevTools (outerWidth/innerWidth)
- Aplica blur no `.member-protected-content`
- Modal "Feche o DevTools para continuar"
- Log: `devtools_detected`, `blocked_shortcut`

### 4. Proteção de Arquivos/Mídia
- **`/media?path=uploads/xxx&produto_id=42`** – valida acesso ao produto
- **`/media?path=uploads/xxx&produto_id=0`** – qualquer membro logado
- **`/media?file_id=123&produto_id=42`** – arquivo de aula
- Sem expor caminho real; valida sessão e autorização

### 5. Logs (security_events)
- `devtools_detected`
- `print_attempt`
- `blocked_shortcut`
- `unauthorized_download_attempt`

## Configuração

### Habilitar/Desabilitar
- **Global:** `configuracoes_sistema` → `PROTECT_MEMBER_AREA` = `true` ou `false`
- **Por comunidade:** `PROTECT_MEMBER_AREA_BY_COMMUNITY` = `{"1":true,"2":false}`

### Migração
```bash
mysql -u user -p database < migrations/009_member_area_protection.sql
```

## Como Testar

1. **Migração:** Execute o SQL da migração.
2. **Login como cliente:** Acesse `/member_login` e faça login.
3. **Dashboard:** Abra `/member_area_dashboard` – verifique watermark e que imagens carregam via `/media`.
4. **Curso:** Abra um curso – banner, capas de módulos e downloads devem usar `/media`.
5. **Anti-print:** Pressione Ctrl+P – deve aparecer modal.
6. **Anti-devtools:** Abra F12 – conteúdo deve ser borrado e modal exibido.
7. **Desabilitar:** Altere `PROTECT_MEMBER_AREA` para `false` – proteção não deve carregar.

## Escopo
- `member_area_dashboard` ✓
- `member_course_view` ✓
- `member_licenses` ✓

## Observações
- Não existe bloqueio 100% – meta é dissuadir, rastrear e proteger URLs/arquivos.
- Acessibilidade: proteção aplicada apenas em `.member-protected-content`.
