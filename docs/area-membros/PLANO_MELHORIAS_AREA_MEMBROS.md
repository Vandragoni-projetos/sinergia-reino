# Plano de Mudanças – Melhorias na Área de Membros

## Varredura do Projeto (Análise Inicial)

### 1. Model/Controller do cadastro de aulas
- **Arquivo:** `views/gerenciar_curso.php`
- **Responsabilidades:**
  - Formulário de adicionar aula (modal `#modal-add-aula`, linhas ~730-770)
  - Formulário de editar aula (modal `#modal-edit-aula`, linhas ~780-840)
  - `$_POST['adicionar_aula']` → INSERT em `aulas` (linhas 300-337)
  - `$_POST['editar_aula_form']` → UPDATE em `aulas` (linhas 354-440)
  - `tipo_conteudo` (video | files | mixed) já existe e orienta validações

### 2. Upload handler (arquivos da aula)
- **Arquivo:** `views/gerenciar_curso.php` (upload inline no mesmo form POST)
- **Diretório:** `uploads/aula_files/` (definido em `$aula_files_dir`)
- **Tabela:** `aula_arquivos` (id, aula_id, nome_original, nome_salvo, caminho_arquivo, tipo_mime, tamanho_bytes, ordem)
- **Fluxo:** `$_FILES['aula_files']` → validação → `move_uploaded_file` → INSERT em `aula_arquivos`
- **Nota:** `nome_original` já existe e é preenchido com `$_FILES['aula_files']['name'][$key]`. O nome no disco é `uniqid('aula_file_', true) . '.' . $ext` (slug + hash).

### 3. Render do player na member_course_view
- **Arquivo:** `views/member/member_course_view.php`
- **Função JS:** `loadLesson(lesson)` (linhas ~980-1095)
- **Lógica atual:**
  - Se `tipo_conteudo === 'video'|'mixed'` e há `url_video` YouTube → Player YMin
  - Senão → Placeholder: "Esta aula não contém vídeo. Verifique os materiais de apoio abaixo."
- **Container:** `#player-host` (ou `playerHost`) dentro da área do player
- **Consulta PHP:** aulas incluem `tipo_conteudo`; arquivos vêm de `aula_arquivos` via JOIN/query separada

### 4. Render dos botões de download de materiais
- **Arquivo:** `views/member/member_course_view.php` (linhas ~1058-1070)
- **HTML gerado:** `<a href="${aulaFilesDirPublic}${file.nome_salvo}" ...><span>${file.nome_original}</span></a>`
- **Atualmente:** usa `file.nome_original` no label. O link aponta para `uploads/aula_files/{nome_salvo}`.
- **media.php:** usado para download protegido (`/media?file_id=X&produto_id=Y`); já envia `Content-Disposition: attachment; filename="nome_original"` quando disponível.

### 5. Campos de descrição do módulo e da aula
- **Aula:** `aulas.descricao` (TEXT) – textarea `#add_descricao_aula` e `#edit_descricao_aula` em `gerenciar_curso.php` (linhas 758, 827)
- **Módulo:** tabela `modulos` não possui campo `descricao`; apenas `titulo`, `imagem_capa_url`, `ordem`, `release_days`
- **Foco:** substituir textarea de `descricao_aula` por editor WYSIWYG

---

## Plano de Implementação

### Melhoria 1: Aula "Somente Arquivos" – Banner/thumbnail opcional

| Etapa | Descrição | Arquivos |
|-------|-----------|----------|
| 1.1 | Migration: adicionar colunas em `aulas` | `migrations/011_aula_lesson_cover.sql` |
| 1.2 | Admin: bloco condicional no modal Add/Edit Aula (quando tipo=files) | `views/gerenciar_curso.php` |
| 1.3 | Admin: lógica de upload/URL e persistência (lesson_cover_type, lesson_cover_url, lesson_cover_path) | `views/gerenciar_curso.php` |
| 1.4 | PHP: incluir lesson_cover_* nas queries de aulas | `views/member/member_course_view.php` (PHP), `views/curso_preview.php` (se aplicável) |
| 1.5 | Cliente: em `loadLesson()`, se tipo=files e houver cover, exibir banner no lugar do placeholder | `views/member/member_course_view.php` (JS) |
| 1.6 | CSS: banner responsivo 720x1280/720x1080, object-contain, fundo escuro, bordas arredondadas | `views/member/member_course_view.php` (style) ou CSS global |

**Migration 011:**
```sql
ALTER TABLE aulas
  ADD COLUMN lesson_cover_type ENUM('upload','url') DEFAULT NULL AFTER tipo_conteudo,
  ADD COLUMN lesson_cover_url VARCHAR(512) DEFAULT NULL,
  ADD COLUMN lesson_cover_path VARCHAR(512) DEFAULT NULL;
```

**Upload:** salvar em `uploads/aula_covers/` (criar pasta), nome seguro (ex: `lesson_cover_{aula_id}_{uniqid}.{ext}`).

---

### Melhoria 2: Upload de arquivos – Manter nome original

| Etapa | Descrição | Arquivos |
|-------|-----------|----------|
| 2.1 | **Verificação:** `aula_arquivos.nome_original` já existe e é usado no front | N/A |
| 2.2 | Sanitizar `nome_original` no INSERT (remover path traversal, caracteres perigosos) | `views/gerenciar_curso.php` |
| 2.3 | Garantir que links de download no member_course_view usem `/media?file_id=X&produto_id=Y` para forçar nome amigável no Content-Disposition | `views/member/member_course_view.php` |
| 2.4 | Fallback: se `nome_original` vazio ou inválido, usar `nome_salvo` com extensão amigável | `views/gerenciar_curso.php`, `media.php` |

**Nota:** O link atual usa `aulaFilesDirPublic + file.nome_salvo`, que serve o arquivo diretamente. Para garantir `Content-Disposition` com nome amigável, é preciso usar `/media?file_id=...&produto_id=...` quando houver proteção. Verificar se o projeto já usa media.php para aula_arquivos ou se usa path direto.

**Descoberta:** Em member_course_view o link é `href="${aulaFilesDirPublic}${file.nome_salvo}"` – acesso direto. O media.php é usado com `file_id` e `produto_id`. Para downloads com nome amigável, precisamos decidir: (a) passar a usar media.php para todos os arquivos de aula (requer produto_id no contexto) ou (b) manter path direto e confiar em nome_original no HTML (o navegador pode ignorar). A especificação pede "exibir nome original no label" – já fazemos. Para o download em si, o navegador usa o nome do arquivo na URL se não houver Content-Disposition. O path direto `uploads/aula_files/arquivo_hash.pdf` resulta em download com nome "arquivo_hash.pdf". Para "Aula 01 - Material.pdf" precisamos servir via script que envia Content-Disposition. Logo: migrar links de materiais de aula para `/media?file_id=af.id&produto_id=...`.

---

### Melhoria 3: Editor de descrição – HTML com links

| Etapa | Descrição | Arquivos |
|-------|-----------|----------|
| 3.1 | Integrar TinyMCE ou Quill (CDN) no modal Add/Edit Aula | `views/gerenciar_curso.php` |
| 3.2 | Substituir textarea por div/textarea com ID para o editor | `views/gerenciar_curso.php` |
| 3.3 | Backend: sanitizar HTML ao salvar (allowed: a, p, br, strong, em, ul, ol, li, h1-h4, blockquote) | `views/gerenciar_curso.php` ou helper |
| 3.4 | Para `<a>`: permitir href, target, rel; forçar rel="noopener noreferrer" se target="_blank" | Helper PHP |
| 3.5 | Cliente: renderizar HTML na descrição (já usa innerHTML em member_course_view; remover escape que quebra links) | `views/member/member_course_view.php` |

**Fluxo atual da descrição no cliente:**
```javascript
let descriptionHtml = (lesson.descricao || '...')
  .replace(/</g, "&lt;").replace(/>/g, "&gt;")  // Escapa TUDO
  .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" ...>');  // Depois transforma URLs em links
```
Isso invalida qualquer HTML colado. Precisamos: se descricao contiver HTML válido/sanitizado, usar direto; senão, aplicar fallback (auto-link de URLs).

---

## Ordem de Execução Recomendada

1. **Migration 011** (aula cover)
2. **Melhoria 1** (banner Somente Arquivos) – admin + cliente
3. **Melhoria 2** (nome original) – sanitização + links para media.php
4. **Melhoria 3** (WYSIWYG + sanitização) – admin + ajuste de render no cliente

---

## Tabelas/Colunas Afetadas

| Tabela | Ação | Colunas |
|--------|------|---------|
| `aulas` | ALTER | lesson_cover_type, lesson_cover_url, lesson_cover_path |

`aula_arquivos` já possui `nome_original`; nenhuma alteração de esquema necessária para melhoria 2.

---

## Arquivos a Serem Alterados

- `migrations/011_aula_lesson_cover.sql` (novo)
- `views/gerenciar_curso.php` (modals, POST, upload cover, sanitização nome_original, WYSIWYG)
- `views/member/member_course_view.php` (PHP query, JS loadLesson, links de download, render descricao)
- `views/curso_preview.php` (se exibir aulas tipo files; incluir cover)
- `media.php` (garantir uso de nome_original em Content-Disposition; já faz)
- `helpers/html_sanitizer.php` (novo, opcional) – sanitização de HTML
- `README_visual.md` (atualizar com resumo das mudanças)
