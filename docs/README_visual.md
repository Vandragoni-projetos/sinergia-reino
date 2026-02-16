# Configurações Visuais (White-label)

Sistema de personalização visual controlado pelo painel admin. O admin altera cores, fontes, radius, shadow e imagens; o sistema aplica automaticamente em todo o frontend via CSS Variables.

## Onde fica o theme_json

O tema é armazenado no banco de dados na tabela **`configuracoes_sistema`**, na chave **`theme_json`**.

- **Tabela:** `configuracoes_sistema`
- **Coluna chave:** `theme_json`
- **Coluna valor:** JSON com todas as configurações visuais

Exemplo de estrutura:

```json
{
  "primary": "#32e768",
  "primaryHover": "#28d15e",
  "bg": "#07090d",
  "text": "rgba(255, 255, 255, 0.9)",
  "textMuted": "rgba(255, 255, 255, 0.5)",
  "card": "#1a1f24",
  "cardElevated": "#0f1419",
  "border": "rgba(255, 255, 255, 0.1)",
  "radius": "0.5rem",
  "shadow": "0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -1px rgba(0,0,0,0.2)",
  "fontSans": "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
  "logo_url": "uploads/config/logo_xxx.png",
  "login_banner_url": "uploads/config/login_bg_xxx.jpg"
}
```

## Fluxo de carregamento

1. **`config/load_settings.php`** – Incluído no `<head>` de todas as páginas principais.
2. Carrega **`config/theme_helper.php`**.
3. **`get_theme_json()`** – Lê `theme_json` do banco (ou usa defaults).
4. **`get_theme_css_vars()`** – Gera o bloco `:root { --var: value; }`.
5. O bloco é injetado em `<style id="theme-vars">` no HTML.

## Variáveis CSS disponíveis

| Variável              | Descrição           | Exemplo                 |
|-----------------------|---------------------|-------------------------|
| `--brand-primary`     | Cor primária        | `#32e768`               |
| `--brand-primary-hover` | Cor primária hover | `#28d15e`             |
| `--accent-primary`    | Alias de brand-primary | (retrocompat)       |
| `--accent-primary-hover` | Alias (retrocompat) |                   |
| `--theme-bg`          | Cor de fundo        | `#07090d`               |
| `--theme-text`        | Cor do texto        | `rgba(255,255,255,0.9)` |
| `--theme-text-muted`  | Texto secundário    | `rgba(255,255,255,0.5)` |
| `--theme-card`        | Fundo de cards      | `#1a1f24`               |
| `--theme-card-elevated` | Fundo elevado     | `#0f1419`               |
| `--theme-border`      | Cor de bordas       | `rgba(255,255,255,0.1)` |
| `--theme-radius`      | Border radius       | `0.5rem`                |
| `--theme-shadow`      | Box shadow          | `0 4px 6px...`          |
| `--theme-font-sans`   | Fonte sans-serif    | `'Inter', sans-serif`   |

## Como adicionar novos tokens

### 1. Definir o token em `config/theme_helper.php`

Em **`get_default_theme()`**, adicione a nova chave com valor padrão:

```php
function get_default_theme() {
    return [
        // ... existentes ...
        'meuToken' => 'valor-padrao',
    ];
}
```

Em **`get_theme_css_vars()`**, inclua a variável CSS:

```php
$vars['--theme-meu-token'] = $theme['meuToken'] ?? 'valor-padrao';
```

### 2. Salvar no banco

Em **`api/admin_api.php`** (action `save_theme`), adicione a chave ao array `$theme`:

```php
$theme = [
    // ... existentes ...
    'meuToken' => trim($data['meuToken'] ?? 'valor-padrao'),
];
```

### 3. Usar no CSS

Em **`style.css`** ou em componentes:

```css
.meu-elemento {
    propriedade: var(--theme-meu-token);
}
```

### 4. Adicionar na UI Admin (opcional)

Em **`views/admin/admin_visual_config.php`**, inclua o campo de input e o valor no Preview/Salvar.

## Arquivos envolvidos

| Arquivo | Função |
|---------|--------|
| `config/theme_helper.php` | Carrega theme_json, gera CSS vars, defaults |
| `config/load_settings.php` | Inclui theme_helper e injeta `<style id="theme-vars">` |
| `style.css` | Usa `var(--theme-*)` nos elementos principais |
| `views/admin/admin_visual_config.php` | UI admin de Configurações Visuais |
| `api/admin_api.php` | Endpoints `save_theme`, `upload_logo`, `upload_login_image` |

## Segurança

- **Acesso:** Apenas usuários com `$_SESSION['tipo'] === 'admin'` podem alterar.
- **Upload:** Extensões permitidas (JPG, PNG, WEBP, SVG para logo; JPG, PNG, WEBP para banner).
- **Tamanho:** Logo máx 2MB; Banner máx 5MB.
- **Path:** Arquivos salvos em `uploads/config/` (fora de diretórios sensíveis).
