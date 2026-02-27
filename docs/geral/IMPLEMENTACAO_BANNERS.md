# 🎯 Implementação do Sistema de Banners Publicitários

## 📋 Visão Geral
Sistema completo para inserir banners publicitários no Dashboard do Infoprodutor e do Cliente, com suporte a **Drag & Drop** para reordenação junto com produtos.

---

## 🗄️ 1. BANCO DE DADOS

### Migrations Criadas
Arquivo: `migrations/008_create_banners_system.sql`

**Tabelas:**
1. **`banners`** - Armazena os banners publicitários
2. **`products_feed_items`** - Controla a ordem de exibição (produtos + banners)

**Trigger automático:**
- `after_produto_insert` - Adiciona novos produtos ao feed automaticamente

### Como Executar

```bash
# 1. Conecte ao MySQL
mysql -u seu_usuario -p seu_banco

# 2. Execute a migration
source migrations/008_create_banners_system.sql;

# 3. Verifique se as tabelas foram criadas
SHOW TABLES LIKE '%banner%';
SHOW TABLES LIKE 'products_feed_items';
```

**⚠️ IMPORTANTE:** A migration popula automaticamente `products_feed_items` com os produtos existentes.

---

## 🔌 2. API ENDPOINTS

### Arquivos Criados:
1. **`api/banners_api.php`** - CRUD completo + reordenação
2. **`api/banner_upload.php`** - Upload de imagens

### Endpoints Disponíveis:

#### GET `/api/banners_api?action=list`
Lista todos os banners do infoprodutor.

#### POST `/api/banners_api?action=create`
Cria novo banner.

**Payload:**
```json
{
  "titulo": "Promoção Black Friday",
  "image_path": "uploads/banner_xxx.png",  // OU
  "image_url": "https://exemplo.com/img.jpg",
  "click_url": "https://exemplo.com/oferta",
  "open_new_tab": 1,
  "is_active": 1,
  "show_in_products_grid": 1,
  "show_in_member_dashboard": 1,
  "show_in_offers_section": 0
}
```

#### POST `/api/banners_api?action=update`
Atualiza banner existente (mesmo payload + `id`).

#### POST `/api/banners_api?action=delete`
Exclui banner.

**Payload:**
```json
{
  "id": 123
}
```

#### GET `/api/banners_api?action=get_feed`
Retorna feed ordenado (produtos + banners).

**Resposta:**
```json
{
  "success": true,
  "feed": [
    {
      "item_type": "product",
      "item_id": 1,
      "sort_order": 0,
      "data": { /* dados do produto */ }
    },
    {
      "item_type": "banner",
      "item_id": 5,
      "sort_order": 1,
      "data": { /* dados do banner */ }
    }
  ]
}
```

#### POST `/api/banners_api?action=reorder_feed`
Salva nova ordem após drag & drop.

**Payload:**
```json
{
  "items": [
    { "item_type": "product", "item_id": 1, "sort_order": 0 },
    { "item_type": "banner", "item_id": 5, "sort_order": 1 },
    { "item_type": "product", "item_id": 2, "sort_order": 2 }
  ]
}
```

#### POST `/api/banner_upload.php`
Upload de imagem (FormData com arquivo `banner_image`).

**Validações:**
- Extensões: jpg, jpeg, png, webp
- Tamanho máximo: 2MB
- Proporção recomendada: 720x1080 (2:3)

---

## 🎨 3. UI DO INFOPRODUTOR

### Arquivos para Modificar:

#### A) `views/produtos.php`

**Alterações necessárias:**

1. **Adicionar botão "+ Novo Banner"** (após linha 238):

```php
<div class="flex gap-3">
    <button id="novo-produto-btn" class="...">
        <i data-lucide="plus" class="w-5 h-5"></i>
        <span>Novo Produto</span>
    </button>
    <button id="novo-banner-btn" class="...">
        <i data-lucide="image" class="w-5 h-5"></i>
        <span>Novo Banner</span>
    </button>
</div>
```

2. **Incluir modal de banner** (antes do `</body>`):

```php
<?php include __DIR__ . '/includes/banner_modal.php'; ?>
```

3. **Substituir listagem de produtos** (linhas 402-479):
   - Em vez de buscar só `produtos`, buscar o **feed ordenado** via `products_feed_items`
   - Para cada item no feed:
     - Se `item_type === 'product'`: renderizar card de produto (código existente)
     - Se `item_type === 'banner'`: renderizar card de banner (template abaixo)

**Template do Card de Banner:**

```php
<div class="banner-card relative bg-dark-card rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 border border-purple-500/50 flex flex-col overflow-hidden group" data-id="<?php echo $banner['id']; ?>" data-type="banner">
    
    <!-- Handle Drag -->
    <div class="drag-handle cursor-grab active:cursor-grabbing absolute top-3 left-3 z-10 p-2 rounded-lg bg-black/40 hover:bg-black/60 text-gray-300 hover:text-white transition-colors">
        <i data-lucide="grip-vertical" class="w-5 h-5"></i>
    </div>

    <!-- Badge Banner -->
    <div class="absolute top-3 left-14 z-10">
        <span class="bg-purple-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg">BANNER</span>
    </div>

    <!-- Imagem -->
    <div class="relative h-56 overflow-hidden bg-dark-elevated" style="aspect-ratio: 2/3;">
        <?php 
        $banner_img = $banner['image_url'] ?: ('/' . $banner['image_path']);
        ?>
        <img src="<?php echo htmlspecialchars($banner_img); ?>" alt="Banner" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
    </div>

    <!-- Botões de Ação -->
    <div class="absolute top-3 right-3 flex flex-col gap-2">
        <button onclick="abrirBannerModal(<?php echo $banner['id']; ?>)" class="bg-white/90 hover:bg-white text-gray-800 p-2 rounded-lg shadow-md">
            <i data-lucide="pencil" class="w-4 h-4"></i>
        </button>
        <button onclick="excluirBanner(<?php echo $banner['id']; ?>)" class="bg-white/90 hover:bg-white text-red-600 p-2 rounded-lg shadow-md">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
    </div>

    <!-- Info -->
    <div class="p-5 flex-grow">
        <h3 class="font-bold text-white text-lg mb-2">
            <?php echo htmlspecialchars($banner['titulo'] ?: 'Banner Publicitário'); ?>
        </h3>
        <?php if ($banner['click_url']): ?>
            <p class="text-xs text-gray-400 truncate">
                <i data-lucide="external-link" class="w-3 h-3 inline"></i>
                <?php echo htmlspecialchars($banner['click_url']); ?>
            </p>
        <?php endif; ?>
        <p class="text-xs text-gray-500 mt-2">
            <?php echo $banner['is_active'] ? '✅ Ativo' : '🔴 Inativo'; ?>
        </p>
    </div>
</div>
```

4. **Atualizar JS do Sortable** (linhas 490-522):

```javascript
// Modificar onEnd para chamar nova API
onEnd: async function(evt) {
    const cards = lista.querySelectorAll('[data-id]'); // produtos E banners
    const payload = Array.from(cards).map((el, idx) => ({
        item_type: el.classList.contains('produto-card') ? 'product' : 'banner',
        item_id: parseInt(el.getAttribute('data-id'), 10),
        sort_order: idx
    }));
    
    try {
        const r = await fetch('/api/banners_api?action=reorder_feed', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: payload })
        });
        const res = await r.json();
        if (!res.success) {
            console.error('Erro ao salvar ordem:', res.error);
            window.location.reload();
        }
    } catch (e) {
        console.error('Erro:', e);
        window.location.reload();
    }
}
```

5. **Adicionar funções JS** (antes do `</script>`):

```javascript
// Abrir modal de novo banner
document.getElementById('novo-banner-btn').onclick = () => {
    abrirBannerModal();
};

// Excluir banner
async function excluirBanner(bannerId) {
    if (!confirm('Tem certeza que deseja excluir este banner?')) return;
    
    try {
        const res = await fetch('/api/banners_api?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: bannerId })
        });
        const data = await res.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro: ' + data.error);
        }
    } catch (e) {
        alert('Erro de conexão: ' + e.message);
    }
}
```

---

## 👤 4. UI DO CLIENTE (Dashboard)

### Arquivo: `views/member_area_dashboard.php`

**Alterações necessárias:**

1. **Buscar banners ativos** (início do PHP):

```php
// Buscar banners ativos para o dashboard do cliente
$banners_dashboard = [];
$banners_ofertas = [];

try {
    // Banners para exibir no grid (misturados com cursos)
    $stmt_banners_grid = $pdo->prepare("
        SELECT * FROM banners 
        WHERE is_active = 1 
        AND show_in_member_dashboard = 1
        ORDER BY created_at DESC
    ");
    $stmt_banners_grid->execute();
    $banners_dashboard = $stmt_banners_grid->fetchAll(PDO::FETCH_ASSOC);
    
    // Banners para seção "Ofertas Exclusivas"
    $stmt_banners_ofertas = $pdo->prepare("
        SELECT * FROM banners 
        WHERE is_active = 1 
        AND show_in_offers_section = 1
        ORDER BY created_at DESC
    ");
    $stmt_banners_ofertas->execute();
    $banners_ofertas = $stmt_banners_ofertas->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar banners: " . $e->getMessage());
}
```

2. **Inserir banners no grid de cursos** (procure o loop `foreach ($cursos_com_acesso as $curso)`):

Antes do loop, mescle cursos com banners:

```php
// Mesclar cursos com banners para o grid
$items_grid = [];

// Adicionar cursos
foreach ($cursos_com_acesso as $curso) {
    $items_grid[] = ['type' => 'curso', 'data' => $curso];
}

// Adicionar banners
foreach ($banners_dashboard as $banner) {
    $items_grid[] = ['type' => 'banner', 'data' => $banner];
}

// Opcional: embaralhar ou ordenar manualmente
```

Então no HTML:

```php
<?php foreach ($items_grid as $item): ?>
    <?php if ($item['type'] === 'curso'): ?>
        <!-- Renderizar card de curso (código existente) -->
    <?php elseif ($item['type'] === 'banner'): ?>
        <!-- Renderizar card de banner -->
        <?php $banner = $item['data']; ?>
        <?php 
        $banner_img = $banner['image_url'] ?: ('/' . $banner['image_path']);
        $is_clickable = !empty($banner['click_url']);
        $link_target = $banner['open_new_tab'] ? '_blank' : '_self';
        ?>
        
        <?php if ($is_clickable): ?>
            <a href="<?php echo htmlspecialchars($banner['click_url']); ?>" target="<?php echo $link_target; ?>" class="block">
        <?php endif; ?>
        
        <div class="bg-dark-card rounded-2xl shadow-sm border border-purple-500/50 overflow-hidden hover:shadow-xl transition-all">
            <div style="aspect-ratio: 2/3;">
                <img src="<?php echo htmlspecialchars($banner_img); ?>" alt="<?php echo htmlspecialchars($banner['titulo'] ?: 'Banner'); ?>" class="w-full h-full object-cover">
            </div>
            <?php if ($banner['titulo']): ?>
            <div class="p-4">
                <h3 class="text-white font-bold"><?php echo htmlspecialchars($banner['titulo']); ?></h3>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($is_clickable): ?>
            </a>
        <?php endif; ?>
    <?php endif; ?>
<?php endforeach; ?>
```

3. **Seção "Ofertas Exclusivas"** (procure a seção com id `ofertas-exclusivas`):

```php
<section id="ofertas-exclusivas" class="mt-12">
    <h2 class="text-2xl font-bold text-white mb-6">Ofertas Exclusivas para Você</h2>
    
    <?php if (!empty($banners_ofertas)): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($banners_ofertas as $banner): ?>
                <?php 
                $banner_img = $banner['image_url'] ?: ('/' . $banner['image_path']);
                $is_clickable = !empty($banner['click_url']);
                $link_target = $banner['open_new_tab'] ? '_blank' : '_self';
                ?>
                
                <?php if ($is_clickable): ?>
                    <a href="<?php echo htmlspecialchars($banner['click_url']); ?>" target="<?php echo $link_target; ?>">
                <?php endif; ?>
                
                <div class="bg-dark-card rounded-2xl overflow-hidden hover:shadow-2xl transition-all border border-purple-500/50">
                    <img src="<?php echo htmlspecialchars($banner_img); ?>" alt="Oferta" class="w-full" style="aspect-ratio: 2/3; object-fit: cover;">
                    <?php if ($banner['titulo']): ?>
                    <div class="p-4">
                        <h3 class="text-white font-bold text-lg"><?php echo htmlspecialchars($banner['titulo']); ?></h3>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($is_clickable): ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-dark-card rounded-xl p-8 text-center text-gray-400">
            <i data-lucide="package-open" class="w-16 h-16 mx-auto mb-4 text-gray-600"></i>
            <p>Nenhuma oferta exclusiva disponível. Fique atento para futuras oportunidades!</p>
        </div>
    <?php endif; ?>
</section>
```

---

## ✅ 5. TESTES

### Checklist de Testes:

#### 5.1 Banco de Dados
- [ ] Migration executada sem erros
- [ ] Tabelas `banners` e `products_feed_items` criadas
- [ ] Produtos existentes populados em `products_feed_items`
- [ ] Trigger `after_produto_insert` funcionando

#### 5.2 API
- [ ] `GET /api/banners_api?action=list` retorna lista vazia inicialmente
- [ ] `POST /api/banners_api?action=create` cria banner
- [ ] `POST /api/banner_upload.php` faz upload de imagem
- [ ] `GET /api/banners_api?action=get_feed` retorna produtos + banners ordenados
- [ ] `POST /api/banners_api?action=reorder_feed` salva nova ordem
- [ ] `POST /api/banners_api?action=update` atualiza banner
- [ ] `POST /api/banners_api?action=delete` exclui banner

#### 5.3 UI Infoprodutor
- [ ] Botão "+ Novo Banner" abre modal
- [ ] Upload de imagem funciona (preview, validação)
- [ ] URL externa funciona (preview)
- [ ] Salvar banner cria item no grid
- [ ] Banner aparece no grid com estilo correto (borda roxa, badge "BANNER")
- [ ] Drag & Drop funciona (arrastar banner entre produtos)
- [ ] Ordem persiste ao recarregar página
- [ ] Editar banner carrega dados corretamente
- [ ] Excluir banner remove do grid

#### 5.4 UI Cliente
- [ ] Banners com `show_in_member_dashboard = 1` aparecem no grid
- [ ] Banners com `show_in_offers_section = 1` aparecem em "Ofertas Exclusivas"
- [ ] Banner sem `click_url` não é clicável
- [ ] Banner com `click_url` redireciona corretamente
- [ ] `open_new_tab` abre em nova aba quando marcado
- [ ] Banner inativo (`is_active = 0`) não aparece

---

## 🚀 6. INSTRUÇÕES DE INSTALAÇÃO

### Passo a Passo:

```bash
# 1. Fazer backup do banco de dados
mysqldump -u usuario -p banco > backup_antes_banners.sql

# 2. Executar migration
mysql -u usuario -p banco < migrations/008_create_banners_system.sql

# 3. Verificar instalação
mysql -u usuario -p banco -e "SELECT COUNT(*) FROM products_feed_items;"

# 4. Copiar arquivos da API
cp api/banners_api.php /seu_projeto/api/
cp api/banner_upload.php /seu_projeto/api/

# 5. Copiar modal
mkdir -p views/includes
cp views/includes/banner_modal.php /seu_projeto/views/includes/

# 6. Aplicar alterações em views/produtos.php
# (Seguir instruções da seção 3)

# 7. Aplicar alterações em views/member_area_dashboard.php
# (Seguir instruções da seção 4)

# 8. Testar
# - Criar banner via UI
# - Arrastar e soltar
# - Verificar no dashboard do cliente
```

---

## 🐛 7. TROUBLESHOOTING

### Problema: Produtos não aparecem em `products_feed_items`

**Solução:**
```sql
-- Re-popular manualmente
INSERT INTO `products_feed_items` (`community_id`, `usuario_id`, `item_type`, `item_id`, `sort_order`)
SELECT 
    NULL, 
    p.usuario_id,
    'product',
    p.id,
    p.id
FROM `produtos` p
WHERE NOT EXISTS (
    SELECT 1 FROM `products_feed_items` pfi 
    WHERE pfi.item_type = 'product' AND pfi.item_id = p.id
);
```

### Problema: Drag & Drop não funciona

**Verificar:**
1. SortableJS está carregado? (`<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>`)
2. Console do navegador mostra erros?
3. `.drag-handle` existe em TODOS os cards (produtos E banners)?

### Problema: Upload de imagem falha

**Verificar:**
1. Diretório `uploads/` tem permissão de escrita (chmod 755)
2. `php.ini`: `upload_max_filesize` >= 2M
3. `php.ini`: `post_max_size` >= 2M

---

## 📦 8. ARQUIVOS ENTREGUES

```
migrations/
  └── 008_create_banners_system.sql

api/
  ├── banners_api.php
  └── banner_upload.php

views/
  └── includes/
      └── banner_modal.php

IMPLEMENTACAO_BANNERS.md (este arquivo)
```

---

## 📞 9. SUPORTE

Para dúvidas ou problemas:
1. Verifique os logs do PHP (`error_log`)
2. Inspecione console do navegador (F12)
3. Teste endpoints da API individualmente (Postman/Insomnia)

---

**Desenvolvido por:** Dev Sênior Full-Stack  
**Data:** 2026-01-30  
**Versão:** 1.0.0
