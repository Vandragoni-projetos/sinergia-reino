# Fontes de Vídeo — Fase 1

Documentação do suporte a múltiplas fontes de vídeo na área de membros (Fase 1).

## Campos usados

- **`aulas.url_video`** (TEXT): URL do vídeo ou caminho do arquivo.
  - **YouTube:** URL completa (ex.: `https://www.youtube.com/watch?v=...` ou `https://youtu.be/...`).
  - **Vimeo:** URL do vídeo (ex.: `https://vimeo.com/123456789` ou `https://player.vimeo.com/video/123456789`).
  - **Self-hosted:** Caminho interno terminando em `.mp4` (ex.: `uploads/course_videos/meu-video.mp4` ou `/uploads/.../video.mp4`).

- **`aulas.origem_video`** (varchar(32), default `'youtube'`): Origem do vídeo.
  - Valores permitidos na Fase 1: `youtube`, `vimeo`, `self_hosted`.
  - Se vier vazio ou inválido, o sistema trata como `youtube` (compatibilidade).

Nenhuma migration nova foi criada; usa-se a coluna já existente.

## Validações por origem (backend)

Todas as validações são feitas no backend (`views/gerenciar_curso.php`), na função `validate_video_by_origin()`:

| Origem        | Regra |
|---------------|--------|
| **youtube**   | URL deve bater com o padrão do YouTube (watch, shorts, embed, youtu.be) e extrair `video_id` de 11 caracteres. |
| **vimeo**     | URL deve conter `vimeo.com` ou `player.vimeo.com/video/` e um ID numérico. |
| **self_hosted** | Apenas caminho interno: deve começar por `uploads/` ou `/uploads/`, terminar em `.mp4`, sem `..` no caminho. Não são aceitas URLs externas. |

Se a origem for inválida ou não estiver em `['youtube','vimeo','self_hosted']`, o backend usa `youtube` como padrão.

## Comportamento do player por origem

| Origem        | Onde renderiza | Controles |
|---------------|----------------|-----------|
| **youtube**  | Player YMin (YouTube IFrame API) no container do aluno e no preview. | Velocidade, qualidade, voltar/avançar 5s, tela cheia, barra de progresso (todos preservados). |
| **vimeo**    | iframe com `https://player.vimeo.com/video/{id}`. | Controles nativos do Vimeo (play, volume, tela cheia, etc.). Velocidade/qualidade dependem do player do Vimeo. |
| **self_hosted** | Tag `<video controls>` com `src` = URL interna do MP4. | Controles nativos do HTML5 (play, volume, tempo, tela cheia). Velocidade via `playbackRate` no elemento (não exposto na UI nesta fase). |

O **preview do curso** (infoprodutor) usa a mesma lógica do aluno: YouTube → YMin, Vimeo → iframe, Self-hosted → `<video>`.

## Arquivos alterados (resumo)

- **views/gerenciar_curso.php:** Validação por origem, INSERT/UPDATE com `origem_video`, SELECT com `origem_video`, formulários Adicionar/Editar com select de origem (youtube, vimeo, self_hosted).
- **views/member/member_course_view.php:** SELECT com `origem_video`, `loadLesson()` com branch por origem (youtube → YMin, vimeo → iframe, self_hosted → `<video>`).
- **views/curso_preview.php:** SELECT com `origem_video`, mesma lógica de player por origem.
- **api/video_embed.php:** SELECT com `origem_video`, resposta por origem (youtube, vimeo, self_hosted); mantidas checagens de acesso e release_days.

## Pontos que assumiam apenas YouTube (alterados na Fase 1)

Para revisão/PR, estes eram os únicos pontos que assumiam YouTube (regex/videoId) e foram ajustados para branch por `origem_video`:

| Arquivo | O que era | O que ficou |
|---------|-----------|-------------|
| `views/gerenciar_curso.php` | INSERT/UPDATE fixavam `origem_video = 'youtube'` | Grava `origem_video` do POST (validado; default `youtube`). SELECT inclui `origem_video`. |
| `views/member/member_course_view.php` | `loadLesson()`: só regex YouTube → createYMin; senão "esta aula não contém vídeo" | Branch por `origem_video`: youtube → YMin; vimeo → iframe; self_hosted → `<video>`. |
| `views/curso_preview.php` | Mesmo: regex YouTube → createYMin; senão "apenas YouTube suportado" | Mesma lógica do aluno (youtube, vimeo, self_hosted). |
| `api/video_embed.php` | Só retornava youtube/video_id; rejeitava o resto | Retorna também vimeo (embed_url/video_id) e self_hosted (video_url). Fallback YouTube para compatibilidade. |

Nenhum outro arquivo foi alterado (checkout, produto_config, etc. continuam apenas com YouTube onde já era esperado).

## O que NÃO foi implementado (Fase 1)

- Wistia, GrooveVideo, link externo genérico, código incorporado (iframe + script).
- Novas colunas no banco ou migrations.
