# Checklist de Testes Manuais — Fontes de Vídeo (Fase 1)

Use este checklist após implementar o suporte a múltiplas fontes de vídeo.

## 1. Curso antigo (YouTube) — zero regressão

- [ ] Abrir um curso que já tem aulas só com YouTube.
- [ ] Assistir uma aula: player YMin carrega, play/pause funciona.
- [ ] Verificar controles: velocidade (1x, 1.25x, etc.), qualidade (Auto, 720p, etc.), voltar/avançar 5s, tela cheia.
- [ ] Barra de progresso e tempo atual/duração corretos.
- [ ] “Somente Vídeo” e “Vídeo e Arquivos” continuam iguais; “Somente Arquivos” mostra placeholder (sem vídeo) e arquivos.

## 2. Vimeo — aluno e preview

- [ ] **Gerenciar Conteúdo:** Adicionar aula “Somente Vídeo”, origem “Vimeo”, URL ex.: `https://vimeo.com/123456789` (ou um ID Vimeo válido).
- [ ] Salvar e abrir a aula como **aluno**: iframe do Vimeo carrega, vídeo toca, controles do Vimeo visíveis, tela cheia funciona.
- [ ] **Preview do curso** (infoprodutor): mesma aula exibe o iframe do Vimeo e toca corretamente.
- [ ] Editar a aula e trocar para outra URL Vimeo; salvar e conferir de novo no aluno e no preview.

## 3. Self-hosted MP4 — toca e layout

- [ ] Colocar um arquivo MP4 em `uploads/` (ex.: `uploads/course_videos/teste.mp4`).
- [ ] **Gerenciar Conteúdo:** Adicionar aula “Somente Vídeo”, origem “Self-hosted (MP4)”, URL/caminho: `uploads/course_videos/teste.mp4` (ou `/uploads/...` conforme validação).
- [ ] Salvar e abrir como **aluno**: tag `<video>` carrega, vídeo toca, controles nativos (play, volume, fullscreen) funcionam.
- [ ] Layout: mesmo container (aspecto 16:9), sem quebrar responsividade.
- [ ] **Preview:** mesma aula com MP4 exibida e tocando corretamente.

## 4. “Somente Arquivos” — sem quebra

- [ ] Aula tipo “Somente Arquivos” (sem URL de vídeo): continua mostrando placeholder “Esta aula não contém vídeo” (ou banner, se configurado).
- [ ] Lista de aulas e progresso inalterados; nenhum erro no console.

## 5. Validação no backend

- [ ] Adicionar aula “Somente Vídeo” com origem “Vimeo” e URL inválida (ex.: `https://google.com`): deve exibir erro e não salvar.
- [ ] Self-hosted com caminho inválido (ex.: `http://externo.com/video.mp4` ou sem `.mp4`): deve exibir erro e não salvar.
- [ ] Origem “YouTube” com URL do Vimeo: deve dar erro de validação (URL do YouTube inválida).

## 6. Mobile / responsivo

- [ ] Abrir a área do aluno no celular ou redimensionar o navegador: player (YouTube, Vimeo, MP4) mantém proporção e não estoura o layout.
- [ ] Tela cheia e controles usáveis em viewport pequeno.

## 7. Compatibilidade (origem vazia ou antiga)

- [ ] Aula existente no banco sem `origem_video` (ou NULL): continua sendo exibida como YouTube (regex na URL) no aluno e no preview.
- [ ] Editar essa aula: select “Origem do vídeo” deve mostrar valor coerente (ex.: YouTube) e ao salvar não quebrar.

---

**Resumo:** Foco em não regredir YouTube (YMin e todos os controles), validar Vimeo e Self-hosted no backend, e garantir que o preview reflita o mesmo comportamento do aluno.
