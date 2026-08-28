<?php
// Aba Order Bumps - Gerenciamento de ofertas adicionais
?>

<div class="space-y-6">
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="gift" class="w-5 h-5 text-[#32e768]"></i>
            Order Bumps
        </h2>
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border space-y-4">
            
            <?php if($current_gateway == 'pushinpay'): ?>
                <div class="bg-blue-900/20 border-l-4 border-blue-500 p-4 mb-4 rounded">
                    <p class="text-sm text-blue-300">Listando apenas produtos configurados com <strong class="text-blue-200">PushinPay</strong>.</p>
                </div>
            <?php else: ?>
                <div class="bg-blue-900/20 border-l-4 border-blue-500 p-4 mb-4 rounded">
                    <p class="text-sm text-blue-300">Listando apenas produtos configurados com <strong class="text-blue-200">Mercado Pago</strong>.</p>
                </div>
            <?php endif; ?>

            <div id="order-bumps-container" class="space-y-4">
                <?php foreach ($order_bumps as $index => $bump): ?>
                    <div class="order-bump-item p-4 border border-dark-border rounded-lg bg-dark-card" data-index="<?php echo $index; ?>">
                        <div class="flex justify-between items-center mb-3 cursor-grab">
                            <h3 class="font-bold text-white flex items-center gap-2">
                                <i data-lucide="grip-vertical" class="w-5 h-5 text-gray-400"></i>
                                Oferta #<?php echo $index + 1; ?>
                            </h3>
                            <button type="button" class="remove-order-bump text-red-400 hover:text-red-300 transition-colors">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-gray-300 text-sm font-semibold mb-2">Produto da Oferta</label>
                                <select name="orderbump_product_id[]" class="form-input js-ob-product-select">
                                    <option value="">-- Selecione um produto --</option>
                                    <?php foreach ($lista_produtos_orderbump as $prod_ob): ?>
                                        <option value="<?php echo $prod_ob['id']; ?>" <?php echo ($bump['offer_product_id'] == $prod_ob['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prod_ob['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-300 text-sm font-semibold mb-2">Título da Oferta</label>
                                <input type="text" name="orderbump_headline[]" value="<?php echo htmlspecialchars($bump['headline']); ?>" class="form-input" placeholder="Ex: Sim, eu quero aproveitar essa oferta!">
                            </div>
                            <div>
                                <label class="block text-gray-300 text-sm font-semibold mb-2">Descrição da Oferta</label>
                                <textarea name="orderbump_description[]" rows="3" class="form-input" placeholder="Descreva os benefícios desta oferta..."><?php echo htmlspecialchars($bump['description']); ?></textarea>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="orderbump_is_active[<?php echo $index; ?>]" value="1" class="form-checkbox" <?php echo ($bump['is_active'] ?? true) ? 'checked' : ''; ?>>
                                <label class="ml-2 text-sm text-gray-300">Ativar esta oferta</label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" id="add-order-bump" class="w-full bg-dark-card text-gray-300 font-semibold py-3 px-4 rounded-lg hover:bg-[#32e768] hover:text-white transition duration-300 flex items-center justify-center gap-2 border border-dark-border">
                <i data-lucide="plus-circle" class="w-5 h-5"></i>
                Adicionar Oferta
            </button>
        </div>
    </div>
</div>

<div id="order-bump-template" style="display: none;">
    <div class="order-bump-item p-4 border border-dark-border rounded-lg bg-dark-card" data-index="NEW_INDEX">
        <div class="flex justify-between items-center mb-3 cursor-grab">
            <h3 class="font-bold text-white flex items-center gap-2">
                <i data-lucide="grip-vertical" class="w-5 h-5 text-gray-400"></i>
                Nova Oferta
            </h3>
            <button type="button" class="remove-order-bump text-red-400 hover:text-red-300 transition-colors">
                <i data-lucide="trash-2" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Produto da Oferta</label>
                <select name="orderbump_product_id[]" class="form-input js-ob-product-select">
                    <option value="">-- Selecione um produto --</option>
                    <?php foreach ($lista_produtos_orderbump as $prod_ob): ?>
                        <option value="<?php echo $prod_ob['id']; ?>"><?php echo htmlspecialchars($prod_ob['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Título da Oferta</label>
                <input type="text" name="orderbump_headline[]" value="Sim, eu quero aproveitar essa oferta!" class="form-input">
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Descrição da Oferta</label>
                <textarea name="orderbump_description[]" rows="3" class="form-input"></textarea>
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="orderbump_is_active[NEW_INDEX]" value="1" class="form-checkbox" checked>
                <label class="ml-2 text-sm text-gray-300">Ativar esta oferta</label>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('order-bumps-container');
    const addButton = document.getElementById('add-order-bump');
    const template = document.getElementById('order-bump-template');

    const normalizeObSearch = (text) => String(text || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const closeAllObSearch = (exceptWrap) => {
        container.querySelectorAll('.ob-product-search.is-open').forEach((wrap) => {
            if (wrap !== exceptWrap) wrap.classList.remove('is-open');
        });
    };

    const initObProductSearch = (select) => {
        if (!select || select.dataset.obSearchReady === '1') return;
        select.dataset.obSearchReady = '1';
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        const wrap = document.createElement('div');
        wrap.className = 'ob-product-search';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-input ob-product-search-input';
        input.placeholder = 'Digite para buscar um produto...';
        input.autocomplete = 'off';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-autocomplete', 'list');

        const list = document.createElement('div');
        list.className = 'ob-product-search-list';
        list.setAttribute('role', 'listbox');

        const emptyHint = document.createElement('div');
        emptyHint.className = 'ob-product-search-empty';
        emptyHint.textContent = 'Nenhum produto encontrado';

        wrap.appendChild(input);
        wrap.appendChild(list);
        select.insertAdjacentElement('afterend', wrap);

        const selectedLabel = () => {
            const opt = select.options[select.selectedIndex];
            if (!opt || opt.value === '') return '';
            return opt.textContent.trim();
        };

        const syncInputFromSelect = () => {
            input.value = selectedLabel();
        };

        const openList = () => {
            closeAllObSearch(wrap);
            wrap.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
        };

        const closeList = () => {
            wrap.classList.remove('is-open');
            input.setAttribute('aria-expanded', 'false');
            syncInputFromSelect();
        };

        const renderList = (query) => {
            const q = normalizeObSearch(query);
            list.innerHTML = '';
            let shown = 0;
            Array.from(select.options).forEach((opt) => {
                const label = opt.textContent.trim();
                const isEmpty = opt.value === '';
                const matches = !q || isEmpty || normalizeObSearch(label).indexOf(q) !== -1;
                if (!matches) return;
                if (isEmpty && q) return;
                shown += 1;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ob-product-search-option' + (opt.selected ? ' is-selected' : '');
                btn.setAttribute('role', 'option');
                btn.dataset.value = opt.value;
                btn.textContent = isEmpty ? 'Selecione um produto' : label;
                btn.addEventListener('mousedown', (ev) => ev.preventDefault());
                btn.addEventListener('click', () => {
                    select.value = opt.value;
                    syncInputFromSelect();
                    closeList();
                });
                list.appendChild(btn);
            });
            if (shown === 0) {
                list.appendChild(emptyHint);
            }
        };

        input.addEventListener('focus', () => {
            renderList(input.value === selectedLabel() ? '' : input.value);
            openList();
            if (input.value === selectedLabel()) {
                input.select();
            }
        });
        input.addEventListener('input', () => {
            renderList(input.value);
            openList();
        });
        input.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape') {
                ev.preventDefault();
                closeList();
                input.blur();
            }
            if (ev.key === 'Enter') {
                ev.preventDefault();
                const first = list.querySelector('.ob-product-search-option');
                if (first && wrap.classList.contains('is-open')) first.click();
            }
        });
        input.addEventListener('blur', () => {
            setTimeout(() => {
                if (!wrap.contains(document.activeElement)) closeList();
            }, 120);
        });

        syncInputFromSelect();
    };

    const initObProductSearchIn = (root) => {
        if (!root) return;
        root.querySelectorAll('select.js-ob-product-select').forEach(initObProductSearch);
    };

    const updateBumpIndices = () => {
        container.querySelectorAll('.order-bump-item').forEach((item, index) => {
            item.querySelector('h3').innerHTML = `<i data-lucide="grip-vertical" class="w-5 h-5 text-gray-400"></i> Oferta #${index + 1}`;
            const checkbox = item.querySelector('input[type="checkbox"]');
            if(checkbox) {
                checkbox.name = `orderbump_is_active[${index}]`;
            }
        });
        lucide.createIcons();
    };

    addButton.addEventListener('click', () => {
        const newIndex = container.querySelectorAll('.order-bump-item').length;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = template.innerHTML.replace(/NEW_INDEX/g, newIndex);
        
        const clone = tempDiv.firstElementChild;
        container.appendChild(clone);
        initObProductSearchIn(clone);
        updateBumpIndices();
        lucide.createIcons();
    });

    container.addEventListener('click', (e) => {
        const removeButton = e.target.closest('.remove-order-bump');
        if (removeButton) {
            removeButton.closest('.order-bump-item').remove();
            updateBumpIndices();
        }
    });

    document.addEventListener('mousedown', (e) => {
        if (!e.target.closest('.ob-product-search')) closeAllObSearch();
    });

    new Sortable(container, {
        animation: 150,
        handle: '.cursor-grab',
        ghostClass: 'sortable-ghost',
        onEnd: () => {
            updateBumpIndices();
        }
    });

    initObProductSearchIn(container);
});
</script>

<style>
.sortable-ghost { opacity: 0.4; background: rgba(50, 231, 104, 0.2); }
select.js-ob-product-select {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.ob-product-search {
    position: relative;
}
.ob-product-search-list {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 40;
    max-height: 240px;
    overflow-y: auto;
    background: #1a1f24;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 0.5rem;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45);
}
.ob-product-search.is-open .ob-product-search-list {
    display: block;
}
.ob-product-search-option {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    color: #e5e7eb;
    background: transparent;
    border: 0;
    cursor: pointer;
}
.ob-product-search-option:hover,
.ob-product-search-option:focus {
    background: rgba(50, 231, 104, 0.12);
    color: #fff;
    outline: none;
}
.ob-product-search-option.is-selected {
    color: #32e768;
}
.ob-product-search-empty {
    padding: 0.75rem 1rem;
    font-size: 0.8125rem;
    color: #9ca3af;
}
@media (max-width: 640px) {
    .ob-product-search-input {
        font-size: 16px;
    }
    .ob-product-search-list {
        max-height: 200px;
    }
}
</style>
