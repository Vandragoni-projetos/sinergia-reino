# Relatório Técnico: Análise de Impacto — Novas Fontes de Vídeo

**Data:** 04/02/2025  
**Escopo:** Somente análise e mapeamento. Nenhuma alteração de código.  
**Contexto:** Sistema em produção; zero quebra permitida.

---

## 1. Resumo Executivo

O sistema hoje é **totalmente centrado em YouTube**: o player do cliente é o **YMin**, que usa exclusivamente a **YouTube IFrame API** e espera um `video_id`. As funcionalidades de velocidade, qualidade, tela cheia e voltar/avançar dependem dessa API. Incluir outras fontes (Vimeo, Wistia, self-hosted, embed, etc.) exige **adapter por tipo de origem** e, para iframe/embed genérico, implica **perda inevitável** de controles customizados (velocidade, qualidade) a menos que cada provedor ofereça API equivalente.

**Recomendação:** Implementar em fases. Fase 1: **YouTube (inalterado) + Vimeo + Self-Hosted (MP4)**. Adiar Wistia, GrooveVideo, link externo e código incorporado até definir estratégia de controles e proteção de URL.

---

## 2. Mapeamento de Arquivos e Banco

### 2.1 Arquivos PHP impactados

| Arquivo | Impacto | Motivo |
|--------|---------|--------|
| `api/video_embed.php` | **Alto** | Hoje retorna só `origem: 'youtube'` e `video_id`; rejeita qualquer outra URL. Qualquer nova origem exige novo branch de resposta (ex.: `origem: 'vimeo'`, `video_id` ou `embed_url`) e validação por tipo. |
| `views/gerenciar_curso.php` | **Alto** | Formulários de **Adicionar** e **Editar** aula: campo único "URL do Vídeo (YouTube)"; INSERT/UPDATE fixam `origem_video = 'youtube'`. O modal de **Editar** já tem select "Origem do Vídeo" (youtube, vimeo, url_externa, codigo_incorporado) e textarea para embed, mas o backend **ignora** `origem_video` do POST e não inclui `origem_video` no SELECT das aulas. Será preciso: incluir `origem_video` no SELECT, tratar no POST, validação por origem e, no Add, campo/origem equivalente. |
| `views/member/member_course_view.php` | **Alto** | Renderização do player: `loadLesson()` usa regex **apenas YouTube** em `lesson.url_video`; se não achar `video_id`, mostra "Esta aula não contém vídeo". Todo o bloco YMin (`createYMin`, controles, CSS) é YouTube-only. Para múltiplas fontes: branch por `origem_video` (ou equivalente) e um "adapter" por tipo (YouTube → YMin; Vimeo → iframe/API Vimeo; MP4 → `<video>`; embed → div com innerHTML do embed). |
| `views/curso_preview.php` | **Médio** | Mesma lógica que a view do cliente: extrai `videoId` com regex YouTube; usa YMin. Deve espelhar a mesma lógica de múltiplas fontes que `member_course_view.php` para o preview refletir o que o aluno verá. |
| `views/member/member_course_view_exemple.php` | **Médio** | Cópia/referência da view do cliente; mesma dependência YMin/YouTube. Se for mantido como espelho, deve seguir as mesmas regras de adapter. |

### 2.2 Arquivos JavaScript e CSS

- **JS:** Toda a lógica do player (YMin) está **inline** em `member_course_view.php` e `curso_preview.php` (e no exemple). Não há arquivo .js separado para o player. Qualquer adapter (ex.: "se origem === 'vimeo', montar iframe Vimeo em vez de createYMin") será nesse mesmo bloco ou em um JS incluído.
- **CSS:** Estilos do YMin (`.ymin`, iframe, controles, barra de progresso, etc.) estão **inline** nas mesmas views. Um player alternativo (ex.: iframe Vimeo ou `<video>`) pode precisar de classes/estilos adicionais para manter layout e responsividade.

### 2.3 Banco de dados

| Tabela | Coluna | Impacto |
|--------|--------|---------|
| `aulas` | `url_video` | Já é TEXT; suporta URL longa ou código embed. **Não exige alteração de tipo.** |
| `aulas` | `origem_video` | Já existe: `varchar(32) NOT NULL DEFAULT 'youtube'` (migration `add_origem_video_aulas.sql`). Comentário prevê: youtube, vimeo, url_externa, codigo_incorporado. **Não exige nova migration** para valores como `vimeo`, `wistia`, `self_hosted`, `url_externa`, `codigo_incorporado`; apenas garantir que o backend passe a **ler e gravar** esse campo (hoje está fixo em `'youtube'` no INSERT/UPDATE). |

**Conclusão BD:** Nenhuma migration necessária para as novas opções listadas. O risco está na **lógica** (validação, sanitização e uso de `origem_video` e `url_video`), não no schema.

---

## 3. Onde o player é renderizado

- **Cliente final:** `views/member/member_course_view.php` — container `#player-host`; função `loadLesson(lesson)` monta o YMin quando há `videoId` extraído de `lesson.url_video` (regex YouTube).
- **Preview (infoprodutor):** `views/curso_preview.php` — mesma ideia: regex YouTube em `lessonData.url_video` e `createYMin(playerDiv, videoId)`.
- **API `video_embed.php`:** Retorna JSON com `video_id` (e `origem: 'youtube'`) para uso protegido; **não é usada** atualmente pela `member_course_view.php`, que recebe as aulas já no JSON da página (`allModulesData`). Ou seja: a proteção “não expor URL no HTML” existe na API, mas a view do curso não a consome.

---

## 4. Suporte do player atual a múltiplas fontes

- **Resposta direta:** O player atual **não** suporta múltiplas fontes. Ele só entende YouTube via `createYMin(root, videoId)` e YouTube IFrame API.
- **Adapter por tipo:** Será necessário um adapter por origem, por exemplo:
  - `youtube` → manter fluxo atual (YMin + `video_id`).
  - `vimeo` → montar iframe com URL embed Vimeo (ou usar Froogaloop/Vimeo Player API se quiser controles extras).
  - `wistia` → iframe ou Wistia Embed API.
  - `self_hosted` → `<video src="...">` com controles nativos (velocidade possível via `playbackRate`; qualidade depende de múltiplas renditions ou lógica própria).
  - `url_externa` / `codigo_incorporado` → container com iframe ou `innerHTML` do embed; controles ficam a cargo do player externo.

---

## 5. Iframe / Embed e controles (velocidade, qualidade, tela cheia)

- **Velocidade e qualidade:** Implementadas no YMin via **YouTube IFrame API** (`setPlaybackRate`, `getAvailableQualityLevels`, etc.). Para um iframe genérico (Vimeo, Wistia, veedea, WordPress, etc.), o conteúdo está em outro domínio; não há acesso ao elemento de vídeo interno. Portanto:
  - **Velocidade:** Perdida, a menos que o provedor exponha API (ex.: Vimeo Player API).
  - **Qualidade:** Perdida para embed genérico; Vimeo/Wistia podem ter opções na própria UI deles.
  - **Tela cheia:** Pode ser mantida aplicando fullscreen no **container** do iframe (elemento pai), não dentro do iframe; isso já é viável e não quebra.
- **Voltar / Avançar:** No YMin são feitos com `yminPlayer.seekTo()`. Em iframe/embed externo, não há acesso; o usuário usa os controles do player incorporado.
- **Conclusão:** Para **código incorporado (iframe/embed)** e **link externo** genérico, as funcionalidades de velocidade e qualidade **não podem ser preservadas** com a mesma experiência do YouTube, a menos que se integre a API de cada provedor. Ou se aceita “player alternativo com menos controles” para essas origens, ou se limita a provedores com API (YouTube, Vimeo, Wistia).

---

## 6. Classificação de risco por opção

| Opção | Risco | Justificativa |
|-------|--------|----------------|
| 1) YouTube (existente) | — | Não alterar; manter como está. |
| 2) Vimeo | 🟡 Médio | API e embed bem documentados; adapter claro (iframe ou Vimeo Player API). Controles de velocidade na API do Vimeo são limitados; qualidade/tela cheia podem ser parcialmente replicados. Risco principal: validação de URL e possível uso da API de oEmbed para obter embed URL de forma segura. |
| 3) Wistia | 🟡 Médio | Semelhante ao Vimeo; API de embed existe. Mesmo tipo de adapter (iframe + eventualmente API). Risco de validação e de manter dois “players tipo iframe” (Vimeo + Wistia) sem duplicar muita lógica. |
| 4) GrooveVideo | 🔴 Alto | Menos padrão de mercado; documentação e manutenção podem ser piores. Aumenta superfície de teste e possíveis quebras com mudanças do provedor. |
| 5) Self-Hosted (MP4) | 🟢 Baixo | `<video>` com `url_video` apontando para arquivo servido pelo próprio sistema (ou URL interna). Velocidade via `HTMLMediaElement.playbackRate`; qualidade depende de múltiplos arquivos ou HLS/DASH se implementado. Risco: armazenamento, banda e proteção do arquivo (ex.: URL assinada ou rota protegida). |
| 6) Link externo (WordPress, etc.) | 🔴 Alto | Qualquer URL; difícil validar e sanitizar; risco XSS se for renderizado como iframe `src` ou pior. Controles customizados perdidos. Não recomendado sem whitelist de domínios e política clara. |
| 7) Código incorporado (iframe/embed) | 🔴 Alto | Permite HTML arbitrário (iframe + script, como no exemplo veedea). Risco **XSS** se o conteúdo for inserido sem sanitização forte (allowlist de atributos e domínios para iframe `src`). Controles de velocidade/qualidade perdidos. Exige sanitizer dedicado (e.g. HTML Purifier com regras restritas) e possivelmente CSP. |

---

## 7. Sugestões objetivas

### 7.1 O que faz sentido manter na primeira fase

- **YouTube** — inalterado.
- **Vimeo** — alto uso; adapter viável; risco médio controlável.
- **Self-Hosted (MP4)** — controle total; velocidade nativa; risco baixo desde que a URL seja protegida e validada.

### 7.2 O que deve ser descartado ou adiado por risco técnico

- **GrooveVideo** — adiar até haver demanda clara e documentação estável.
- **Link externo genérico** — alto risco de segurança e UX; não oferecer sem whitelist e política definida.
- **Código incorporado** — adiar até ter sanitização robusta (allowlist de tags/atributos e domínios) e aceitação de que controles (velocidade/qualidade) não serão os mesmos do YMin.

### 7.3 Limitação inicial recomendada

Implementar primeiro apenas: **YouTube (atual) + Vimeo + Self-Hosted (MP4)**. Wistia pode entrar em uma segunda fase após validar o padrão de adapter (iframe + opcionalmente API).

---

## 8. O que NÃO deve ser feito

1. **Alterar o fluxo de “Tipo de Conteúdo” (Somente Vídeo / Somente Arquivos / Vídeo e Arquivos)** — as três opções devem continuar funcionando exatamente como hoje; qualquer mudança deve ser apenas “quando for vídeo, qual origem?”.
2. **Remover ou enfraquecer validação de `url_video`** — cada origem deve ter validação específica (regex/URL permitida) no backend; nunca confiar apenas no front.
3. **Inserir código incorporado (embed) sem sanitização** — nunca fazer `innerHTML = url_video` ou equivalente com conteúdo do usuário; risco crítico de XSS.
4. **Trocar o YMin por outro player para YouTube** — para `origem_video = 'youtube'`, manter o comportamento atual para não regredir velocidade/qualidade/tela cheia/voltar-avançar.
5. **Deixar de incluir `origem_video` no SELECT das aulas** — em `gerenciar_curso.php` a listagem deve trazer `origem_video` para o modal de edição e para qualquer uso na view do cliente/preview.
6. **Ignorar a API `video_embed.php`** — se no futuro a URL do vídeo deixar de ser enviada na página (proteção de conteúdo), a API precisará retornar formato adequado por origem (não só `video_id` para YouTube).

---

## 9. Onde uma implementação mal feita quebraria o sistema

- **gerenciar_curso.php:** Se o INSERT/UPDATE passarem a usar `origem_video` do POST sem validação, um valor inválido ou vazio pode quebrar constraints ou fazer a view do cliente não reconhecer a origem. Sempre validar contra uma lista fixa (ex.: `youtube`, `vimeo`, `self_hosted`) e default para `youtube` quando ausente ou inválido.
- **member_course_view.php (loadLesson):** Se o branch “não-YouTube” montar iframe ou HTML sem escapar `url_video`, risco de XSS. Se assumir que `url_video` sempre é YouTube e remover o regex, aulas já salvas com outra origem podem quebrar ou exibir “Esta aula não contém vídeo”.
- **video_embed.php:** Se retornar URL bruta ou embed para o cliente sem validar acesso e release_days, pode vazar conteúdo. Manter a mesma lógica de acesso e release; estender apenas o formato de resposta por origem.
- **Banco:** Manter `origem_video` NOT NULL com default `'youtube'`; qualquer migration que altere isso pode quebrar instalações antigas sem o novo código.

---

## 10. Abstração ou refatoração prévia

- **Não é obrigatório** refatorar todo o player para um “framework” genérico antes de adicionar Vimeo ou MP4. Basta um **branch por origem** em `loadLesson()` (e no preview), por exemplo:
  - `if (origem === 'youtube')` → `createYMin(...)` (atual).
  - `else if (origem === 'vimeo')` → montar iframe com embed Vimeo (e opcionalmente API).
  - `else if (origem === 'self_hosted')` → montar `<video>` com URL protegida.
- Uma **camada de adapter** (função `renderPlayer(origem, url_video, container)`) pode vir depois para reduzir duplicação entre `member_course_view.php` e `curso_preview.php`; não é pré-requisito para a primeira fase.
- **Proteção de URL:** Se no futuro as aulas não forem mais enviadas com `url_video` no JSON da página, será necessário que a view chame `video_embed.php` (ou equivalente) e use a resposta para montar o player; a API já verifica acesso e release_days — isso deve ser mantido e estendido por origem, não refeito do zero.

---

## 11. Conclusão

- **Viável:** Adicionar Vimeo e Self-Hosted (MP4) com adapter por origem, mantendo YouTube e as três opções de “Tipo de Conteúdo” intactas.
- **Controles:** Para YouTube mantêm-se todos (velocidade, qualidade, tela cheia, voltar/avançar). Para Vimeo e MP4 é possível manter tela cheia e, no MP4, velocidade; qualidade em MP4 exige lógica própria. Para iframe/embed genérico, velocidade e qualidade seriam perdidas.
- **Risco:** Código incorporado e link externo genérico têm risco alto (XSS e UX); devem ser adiados até haver sanitização e política claras.
- **Não é necessário** alterar o banco com novas colunas; é necessário passar a **usar** `origem_video` no backend e no front e validar por origem em todos os pontos que gravam ou exibem vídeo.

Se quiser, o próximo passo pode ser um **documento de especificação** apenas para a Fase 1 (YouTube + Vimeo + Self-Hosted), com lista exata de arquivos e trechos a alterar, ainda sem implementar código.
