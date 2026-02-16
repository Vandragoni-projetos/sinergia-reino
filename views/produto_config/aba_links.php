<?php
// Aba Links - Link do checkout e Ofertas do Produto
// Forçar HTTPS para links de checkout (produção sempre usa HTTPS)
$protocol = "https://";
$domainName = $_SERVER['HTTP_HOST'];
$checkout_link = $protocol . $domainName . '/checkout?p=' . $produto['checkout_hash'];

// Buscar ofertas existentes do produto
$ofertas = [];
try {
    $stmt_ofertas = $pdo->prepare("SELECT * FROM produto_ofertas WHERE produto_id = ? ORDER BY data_criacao DESC");
    $stmt_ofertas->execute([$id_produto]);
    $ofertas = $stmt_ofertas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela pode não existir ainda
    $ofertas = [];
}

// Labels para tipos de acesso
$tipos_acesso_labels = [
    'mensal' => 'Mensal (30 dias)',
    'semestral' => 'Semestral (180 dias)',
    'anual' => 'Anual (365 dias)',
    'vitalicio' => 'Vitalício'
];
?>

<div class="space-y-8">
    <!-- Link Principal do Checkout -->
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="link" class="w-5 h-5 text-[#32e768]"></i>
            Link Principal do Checkout
        </h2>
        
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border">
            <?php $main_link_disabled = $checkout_config['disable_main_link'] ?? false; ?>
            <div class="<?php echo $main_link_disabled ? 'opacity-50' : ''; ?>">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-gray-300 text-sm font-semibold">Link Completo do Checkout</label>
                    <?php if ($main_link_disabled): ?>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-red-500/20 text-red-500 rounded border border-red-500/30 uppercase tracking-tight">Desativado</span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" id="checkout-link-input" readonly value="<?php echo htmlspecialchars($checkout_link); ?>" class="form-input flex-1 bg-dark-card" <?php echo $main_link_disabled ? 'disabled' : ''; ?>>
                    <button type="button" onclick="copyToClipboard('checkout-link-input', this)" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-semibold py-2.5 px-6 rounded-lg transition-all duration-300 flex items-center gap-2 whitespace-nowrap <?php echo $main_link_disabled ? 'cursor-not-allowed grayscale' : ''; ?>">
                        <i data-lucide="copy" class="w-4 h-4"></i>
                        <span>Copiar</span>
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-2">Este é o link principal do produto com o preço padrão de <strong class="text-white">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></strong></p>
                
                <div class="mt-6 pt-4 border-t border-dark-border">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <?php $main_link_disabled = $checkout_config['disable_main_link'] ?? false; ?>
                        <div class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="disable_main_link" value="1" class="sr-only peer" <?php echo $main_link_disabled ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#32e768]"></div>
                        </div>
                        <div>
                            <span class="text-sm font-semibold text-gray-200 group-hover:text-white transition-colors">Desativar Link Principal</span>
                            <p class="text-xs text-gray-500">Se ativado, o link principal acima ficará inacessível, forçando o uso apenas de ofertas específicas.</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Ofertas do Produto -->
    <div>
        <h2 class="text-xl font-semibold mb-4 text-white flex items-center gap-2">
            <i data-lucide="tag" class="w-5 h-5 text-[#32e768]"></i>
            Ofertas do Produto
        </h2>
        
        <div class="bg-dark-elevated p-6 rounded-lg border border-dark-border">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <p class="text-gray-400 text-sm">Crie ofertas com preços diferentes para este produto. Cada oferta terá seu próprio link de checkout.</p>
                <button type="button" onclick="openAddOfertaModal()" class="bg-[#32e768] hover:bg-[#28d15e] text-white font-semibold py-2 px-4 rounded-lg transition-all duration-300 flex items-center gap-2 whitespace-nowrap">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Adicionar Oferta
                </button>
            </div>

            <!-- Lista de Ofertas -->
            <div id="ofertas-container">
                <?php if (empty($ofertas)): ?>
                <div id="empty-ofertas" class="text-center py-12 text-gray-400">
                    <i data-lucide="tag" class="mx-auto w-16 h-16 text-gray-500 mb-4"></i>
                    <p class="font-medium">Nenhuma oferta criada ainda.</p>
                    <p class="text-sm">Clique em "Adicionar Oferta" para criar uma nova oferta.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4" id="ofertas-list">
                    <?php foreach ($ofertas as $oferta): 
                        $oferta_link = $protocol . $domainName . '/checkout?p=' . $produto['checkout_hash'] . '&oferta=' . $oferta['hash'];
                        $tipo_acesso = $oferta['tipo_acesso'] ?? 'vitalicio';
                        $tipo_acesso_label = $tipos_acesso_labels[$tipo_acesso] ?? 'Vitalício';
                    ?>
                    <div class="bg-dark-card p-4 rounded-lg border border-dark-border hover:border-[#32e768]/50 transition-colors" data-oferta-id="<?php echo $oferta['id']; ?>">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h4 class="text-white font-semibold"><?php echo htmlspecialchars($oferta['nome']); ?></h4>
                                    <?php if ($oferta['ativo']): ?>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-500/20 text-green-400">Ativa</span>
                                    <?php else: ?>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-500/20 text-gray-400">Inativa</span>
                                    <?php endif; ?>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-500/20 text-blue-400">
                                        <i data-lucide="clock" class="w-3 h-3 inline-block mr-1"></i><?php echo htmlspecialchars($tipo_acesso_label); ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-4 text-sm">
                                    <span class="text-[#32e768] font-bold">R$ <?php echo number_format($oferta['preco'], 2, ',', '.'); ?></span>
                                    <span class="text-gray-500">•</span>
                                    <span class="text-gray-400">Criada em <?php echo date('d/m/Y', strtotime($oferta['data_criacao'])); ?></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="<?php echo htmlspecialchars($oferta_link); ?>" class="form-input bg-dark-elevated text-sm w-64 lg:w-80" id="oferta-link-<?php echo $oferta['id']; ?>">
                                <button type="button" onclick="copyToClipboard('oferta-link-<?php echo $oferta['id']; ?>', this)" class="p-2 text-gray-400 hover:text-[#32e768] hover:bg-[#32e768]/10 rounded-lg transition-colors" title="Copiar link">
                                    <i data-lucide="copy" class="w-5 h-5"></i>
                                </button>
                                <button type="button" onclick="openEditOfertaModal(<?php echo $oferta['id']; ?>, '<?php echo htmlspecialchars(addslashes($oferta['nome'])); ?>', <?php echo $oferta['preco']; ?>, <?php echo $oferta['ativo']; ?>, '<?php echo $tipo_acesso; ?>')" class="p-2 text-gray-400 hover:text-blue-400 hover:bg-blue-400/10 rounded-lg transition-colors" title="Editar oferta">
                                    <i data-lucide="edit" class="w-5 h-5"></i>
                                </button>
                                <button type="button" onclick="deleteOferta(<?php echo $oferta['id']; ?>, '<?php echo htmlspecialchars(addslashes($oferta['nome'])); ?>')" class="p-2 text-gray-400 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-colors" title="Excluir oferta">
                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar Oferta -->
<div id="oferta-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-dark-card rounded-xl border border-dark-border w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between p-6 border-b border-dark-border">
            <h3 id="oferta-modal-title" class="text-xl font-bold text-white">Adicionar Oferta</h3>
            <button type="button" onclick="closeOfertaModal()" class="text-gray-400 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="oferta-id" value="">
            <div>
                <label for="oferta-nome" class="block text-sm font-medium text-gray-300 mb-2">Nome da Oferta</label>
                <input type="text" id="oferta-nome" placeholder="Ex: Oferta Black Friday" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]" maxlength="255">
                <p class="text-xs text-gray-500 mt-1">Mínimo de 3 caracteres</p>
            </div>
            <div>
                <label for="oferta-preco" class="block text-sm font-medium text-gray-300 mb-2">Preço (R$)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium">R$</span>
                    <input type="number" id="oferta-preco" placeholder="0.00" step="0.01" min="0.01" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg pl-12 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
                </div>
                <p class="text-xs text-gray-500 mt-1">Preço deve ser maior que zero</p>
            </div>
            <div>
                <label for="oferta-tipo-acesso" class="block text-sm font-medium text-gray-300 mb-2">Tipo de Acesso</label>
                <select id="oferta-tipo-acesso" class="w-full bg-dark-elevated border border-dark-border text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#32e768] focus:border-[#32e768]">
                    <option value="vitalicio">Vitalício (acesso permanente)</option>
                    <option value="mensal">Mensal (30 dias)</option>
                    <option value="semestral">Semestral (180 dias)</option>
                    <option value="anual">Anual (365 dias)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Define por quanto tempo o cliente terá acesso após a compra</p>
            </div>
            <div id="oferta-ativo-container" class="hidden">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="oferta-ativo" class="form-checkbox" checked>
                    <span class="text-sm text-gray-300">Oferta ativa</span>
                </label>
            </div>
            <div id="oferta-error" class="hidden bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded-lg text-sm"></div>
        </div>
        <div class="flex gap-3 p-6 pt-0">
            <button type="button" onclick="closeOfertaModal()" class="flex-1 px-4 py-3 bg-dark-elevated text-gray-300 rounded-lg font-semibold hover:bg-dark-border transition-colors">
                Cancelar
            </button>
            <button type="button" onclick="saveOferta()" id="btn-save-oferta" class="flex-1 px-4 py-3 rounded-lg font-semibold text-white transition-colors bg-[#32e768] hover:bg-[#28d15e]">
                Salvar Oferta
            </button>
        </div>
    </div>
</div>

<script>
const produtoId = <?php echo $id_produto; ?>;
const baseUrl = '<?php echo $protocol . $domainName; ?>';
const checkoutHash = '<?php echo $produto['checkout_hash']; ?>';

function copyToClipboard(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    // Usar API moderna de clipboard se disponível
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(() => {
            showCopyFeedback(btn);
        }).catch(() => {
            // Fallback para método antigo
            input.select();
            document.execCommand('copy');
            showCopyFeedback(btn);
        });
    } else {
        // Fallback para navegadores antigos
        input.select();
        document.execCommand('copy');
        showCopyFeedback(btn);
    }
}

function showCopyFeedback(btn) {
    const span = btn.querySelector('span');
    const isIconOnly = !span; // Botão só com ícone (ofertas)
    
    // Salvar estado original
    const originalText = span ? span.textContent : '';
    const originalHTML = btn.innerHTML;
    
    if (isIconOnly) {
        // Botão só com ícone - trocar ícone e cor
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        btn.classList.add('text-[#32e768]', 'bg-[#32e768]/20');
        btn.classList.remove('text-gray-400');
    } else {
        // Botão com texto - mudar texto e cor do fundo
        span.textContent = 'Copiado!';
        const icon = btn.querySelector('i');
        if (icon) icon.setAttribute('data-lucide', 'check');
        btn.classList.add('bg-green-600');
        btn.classList.remove('bg-[#32e768]', 'hover:bg-[#28d15e]');
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    }
    
    // Voltar ao estado original após 2 segundos
    setTimeout(() => {
        if (isIconOnly) {
            btn.innerHTML = originalHTML;
            btn.classList.remove('text-[#32e768]', 'bg-[#32e768]/20');
            btn.classList.add('text-gray-400');
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        } else {
            span.textContent = originalText || 'Copiar';
            const icon = btn.querySelector('i');
            if (icon) icon.setAttribute('data-lucide', 'copy');
            btn.classList.remove('bg-green-600');
            btn.classList.add('bg-[#32e768]', 'hover:bg-[#28d15e]');
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        }
    }, 2000);
}

function openAddOfertaModal() {
    document.getElementById('oferta-modal-title').textContent = 'Adicionar Oferta';
    document.getElementById('oferta-id').value = '';
    document.getElementById('oferta-nome').value = '';
    document.getElementById('oferta-preco').value = '';
    document.getElementById('oferta-tipo-acesso').value = 'vitalicio';
    document.getElementById('oferta-ativo-container').classList.add('hidden');
    document.getElementById('oferta-error').classList.add('hidden');
    document.getElementById('btn-save-oferta').textContent = 'Salvar Oferta';
    document.getElementById('oferta-modal').classList.remove('hidden');
    lucide.createIcons();
}

function openEditOfertaModal(id, nome, preco, ativo, tipoAcesso) {
    document.getElementById('oferta-modal-title').textContent = 'Editar Oferta';
    document.getElementById('oferta-id').value = id;
    document.getElementById('oferta-nome').value = nome;
    document.getElementById('oferta-preco').value = preco;
    document.getElementById('oferta-tipo-acesso').value = tipoAcesso || 'vitalicio';
    document.getElementById('oferta-ativo').checked = ativo == 1;
    document.getElementById('oferta-ativo-container').classList.remove('hidden');
    document.getElementById('oferta-error').classList.add('hidden');
    document.getElementById('btn-save-oferta').textContent = 'Atualizar Oferta';
    document.getElementById('oferta-modal').classList.remove('hidden');
    lucide.createIcons();
}

function closeOfertaModal() {
    document.getElementById('oferta-modal').classList.add('hidden');
}

async function saveOferta() {
    const id = document.getElementById('oferta-id').value;
    const nome = document.getElementById('oferta-nome').value.trim();
    const preco = parseFloat(document.getElementById('oferta-preco').value);
    const tipoAcesso = document.getElementById('oferta-tipo-acesso').value;
    const ativo = document.getElementById('oferta-ativo').checked ? 1 : 0;
    const errorDiv = document.getElementById('oferta-error');
    const btn = document.getElementById('btn-save-oferta');
    
    // Validações
    if (nome.length < 3) {
        errorDiv.textContent = 'O nome da oferta deve ter pelo menos 3 caracteres.';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    if (isNaN(preco) || preco <= 0) {
        errorDiv.textContent = 'O preço deve ser maior que zero.';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    errorDiv.classList.add('hidden');
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    
    try {
        const action = id ? 'update_oferta' : 'create_oferta';
        const response = await fetch('/api/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: action,
                oferta_id: id || null,
                produto_id: produtoId,
                nome: nome,
                preco: preco,
                tipo_acesso: tipoAcesso,
                ativo: ativo
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeOfertaModal();
            // Recarregar a página para mostrar as ofertas atualizadas
            window.location.reload();
        } else {
            errorDiv.textContent = result.error || 'Erro ao salvar oferta.';
            errorDiv.classList.remove('hidden');
        }
    } catch (e) {
        console.error(e);
        errorDiv.textContent = 'Erro de conexão. Tente novamente.';
        errorDiv.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.textContent = id ? 'Atualizar Oferta' : 'Salvar Oferta';
    }
}

async function deleteOferta(id, nome) {
    if (!confirm(`Tem certeza que deseja excluir a oferta "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
        return;
    }
    
    try {
        const response = await fetch('/api/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_oferta',
                oferta_id: id,
                produto_id: produtoId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Remover o elemento da lista
            const element = document.querySelector(`[data-oferta-id="${id}"]`);
            if (element) {
                element.remove();
            }
            
            // Verificar se ainda há ofertas
            const ofertasList = document.getElementById('ofertas-list');
            if (ofertasList && ofertasList.children.length === 0) {
                document.getElementById('ofertas-container').innerHTML = `
                    <div id="empty-ofertas" class="text-center py-12 text-gray-400">
                        <i data-lucide="tag" class="mx-auto w-16 h-16 text-gray-500 mb-4"></i>
                        <p class="font-medium">Nenhuma oferta criada ainda.</p>
                        <p class="text-sm">Clique em "Adicionar Oferta" para criar uma nova oferta.</p>
                    </div>
                `;
                lucide.createIcons();
            }
        } else {
            alert(result.error || 'Erro ao excluir oferta.');
        }
    } catch (e) {
        console.error(e);
        alert('Erro de conexão. Tente novamente.');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
});
</script>
