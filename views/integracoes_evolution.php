<?php
// Este arquivo é incluído a partir do index.php,
// então a verificação de login e a conexão com o banco ($pdo) já existem.

// Obter o ID do usuário logado
$usuario_id_logado = $_SESSION['id'] ?? 0;

if ($usuario_id_logado === 0) {
    header("location: /login");
    exit;
}

$mensagem = '';
$msg_type = '';

// Salvar configurações da Evolution API
if (isset($_POST['salvar_evolution_config'])) {
    $evo_name = trim($_POST['evolution_name'] ?? '');
    $evo_server_url = rtrim(trim($_POST['evolution_server_url'] ?? ''), '/');
    $evo_api_key = trim($_POST['evolution_api_key'] ?? '');
    $evo_instance = trim($_POST['evolution_instance'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET evolution_name = ?, evolution_server_url = ?, evolution_api_key = ?, evolution_instance = ? WHERE id = ?");
        $stmt->execute([$evo_name, $evo_server_url, $evo_api_key, $evo_instance, $usuario_id_logado]);

        $mensagem = "Configurações da Evolution API salvas com sucesso!";
        $msg_type = 'success';
    } catch (PDOException $e) {
        $mensagem = "Erro ao salvar: " . $e->getMessage();
        $msg_type = 'error';
    }
}

if (isset($_SESSION['flash_message'])) {
    $mensagem = $_SESSION['flash_message'];
    $msg_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Buscar configurações da Evolution API do usuário
$stmt_evo = $pdo->prepare("SELECT evolution_name, evolution_server_url, evolution_api_key, evolution_instance FROM usuarios WHERE id = ?");
$stmt_evo->execute([$usuario_id_logado]);
$evo_config = $stmt_evo->fetch(PDO::FETCH_ASSOC);

$evolution_name = $evo_config['evolution_name'] ?? '';
$evolution_server_url = $evo_config['evolution_server_url'] ?? '';
$evolution_api_key = $evo_config['evolution_api_key'] ?? '';
$evolution_instance = $evo_config['evolution_instance'] ?? '';

$evolution_configured = !empty($evolution_server_url) && !empty($evolution_api_key) && !empty($evolution_instance);

// Buscar produtos do infoprodutor
$stmt_products = $pdo->prepare("SELECT id, nome FROM produtos WHERE usuario_id = ? ORDER BY nome ASC");
$stmt_products->execute([$usuario_id_logado]);
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// Buscar mensagens configuradas
$stmt_messages = $pdo->prepare("SELECT * FROM evolution_messages WHERE usuario_id = ? ORDER BY created_at DESC");
$stmt_messages->execute([$usuario_id_logado]);
$messages = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto">
    <!-- Header -->
    <div class="flex items-center mb-6">
        <a href="/index?pagina=integracoes" class="text-[#32e768] hover:text-[#28d15e] mr-4">
            <i data-lucide="arrow-left-circle" class="w-8 h-8"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-white">Evolution API - WhatsApp</h1>
            <p class="text-gray-400">Configure mensagens automáticas via WhatsApp para seus clientes.</p>
        </div>
    </div>

    <!-- Toast de mensagem -->
    <?php if(!empty($mensagem)): ?>
    <div id='toast-msg' class='fixed top-5 right-5 z-50 animate-fade-in flex items-center w-full max-w-xs p-4 text-gray-300 bg-dark-card rounded-lg shadow-xl border border-dark-border'>
        <div class='inline-flex items-center justify-center flex-shrink-0 w-8 h-8 <?php echo ($msg_type == "success" ? "text-green-400 bg-green-900/30" : ($msg_type == "error" ? "text-red-400 bg-red-900/30" : "text-blue-400 bg-blue-900/30")); ?> rounded-lg'>
            <i data-lucide='<?php echo ($msg_type == "success" ? "check" : ($msg_type == "error" ? "alert-circle" : "info")); ?>' class='w-5 h-5'></i>
        </div>
        <div class='ml-3 text-sm font-medium'><?php echo $mensagem; ?></div>
        <button type='button' class='ml-auto -mx-1.5 -my-1.5 text-gray-400 hover:text-gray-300 rounded-lg p-1.5 hover:bg-dark-elevated inline-flex h-8 w-8' onclick='this.parentElement.remove()'>
            <i data-lucide='x' class='w-4 h-4'></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Seção de Configuração de Credenciais -->
    <div class="bg-dark-card p-8 rounded-2xl shadow-md mb-8 border border-dark-border <?php echo $evolution_configured ? 'border-green-500/30' : 'border-yellow-500/30'; ?>">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-[#25D366]/20 flex items-center justify-center border border-[#25D366]/30">
                    <i data-lucide="settings" class="w-6 h-6 text-[#25D366]"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">Configuração da API</h2>
                    <p class="text-gray-400 text-sm">Credenciais de conexão com sua Evolution API</p>
                </div>
            </div>
            <?php if($evolution_configured): ?>
            <span class="px-3 py-1.5 text-xs font-semibold rounded-full bg-green-900/30 text-green-300 border border-green-500/30 flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i> Configurado
            </span>
            <?php else: ?>
            <span class="px-3 py-1.5 text-xs font-semibold rounded-full bg-yellow-900/30 text-yellow-300 border border-yellow-500/30 flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> Pendente
            </span>
            <?php endif; ?>
        </div>

        <form action="/index?pagina=integracoes_evolution" method="post" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Nome da Integração -->
                <div>
                    <label class="block text-sm font-semibold text-gray-300 mb-2">Nome da Integração</label>
                    <div class="flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated focus-within:border-[#25D366] transition-colors">
                        <i data-lucide="tag" class="text-gray-400 w-5 h-5 mr-3"></i>
                        <input type="text" name="evolution_name" value="<?php echo htmlspecialchars($evolution_name); ?>" 
                            class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-white placeholder-gray-500 text-sm"
                            placeholder="Ex: WhatsApp Vendas">
                    </div>
                </div>

                <!-- Nome da Instância -->
                <div>
                    <label class="block text-sm font-semibold text-gray-300 mb-2">Nome da Instância <span class="text-red-400">*</span></label>
                    <div class="flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated focus-within:border-[#25D366] transition-colors">
                        <i data-lucide="smartphone" class="text-gray-400 w-5 h-5 mr-3"></i>
                        <input type="text" name="evolution_instance" value="<?php echo htmlspecialchars($evolution_instance); ?>" 
                            class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-white placeholder-gray-500 text-sm"
                            placeholder="nome-da-instancia" required>
                    </div>
                </div>

                <!-- Server URL -->
                <div>
                    <label class="block text-sm font-semibold text-gray-300 mb-2">Server URL <span class="text-red-400">*</span></label>
                    <div class="flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated focus-within:border-[#25D366] transition-colors">
                        <i data-lucide="globe" class="text-gray-400 w-5 h-5 mr-3"></i>
                        <input type="url" name="evolution_server_url" value="<?php echo htmlspecialchars($evolution_server_url); ?>" 
                            class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-white placeholder-gray-500 text-sm"
                            placeholder="https://sua-evolution-api.com" required>
                    </div>
                </div>

                <!-- API Key Global -->
                <div>
                    <label class="block text-sm font-semibold text-gray-300 mb-2">API Key Global <span class="text-red-400">*</span></label>
                    <div class="flex items-center border border-dark-border rounded-lg px-4 py-3 bg-dark-elevated focus-within:border-[#25D366] transition-colors">
                        <i data-lucide="key" class="text-gray-400 w-5 h-5 mr-3"></i>
                        <input type="text" name="evolution_api_key" value="<?php echo htmlspecialchars($evolution_api_key); ?>" 
                            class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-white placeholder-gray-500 text-sm"
                            placeholder="Sua API Key da Evolution" required>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-dark-border">
                <button type="button" onclick="testEvolutionConnection()" 
                    class="bg-dark-elevated hover:bg-dark-card text-white font-semibold py-2.5 px-5 rounded-xl border border-dark-border transition-all flex items-center gap-2">
                    <i data-lucide="wifi" class="w-4 h-4"></i>
                    Testar Conexão
                </button>
                <button type="submit" name="salvar_evolution_config" 
                    class="bg-[#25D366] hover:bg-[#1da851] text-white font-semibold py-2.5 px-6 rounded-xl transition-all flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Salvar Credenciais
                </button>
            </div>
        </form>
    </div>

    <!-- Alerta se não configurado -->
    <?php if (!$evolution_configured): ?>
    <div class="bg-yellow-900/20 border border-yellow-500/30 rounded-xl p-4 mb-6 flex items-start gap-4">
        <i data-lucide="alert-triangle" class="w-6 h-6 text-yellow-400 flex-shrink-0 mt-0.5"></i>
        <div>
            <h4 class="text-yellow-300 font-bold">Configure suas credenciais primeiro</h4>
            <p class="text-yellow-200 text-sm mt-1">Preencha os campos acima com as credenciais da sua Evolution API para poder criar mensagens automáticas.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Formulário de Nova Mensagem -->
    <div class="bg-dark-card p-8 rounded-2xl shadow-md mb-8 border border-dark-border <?php echo !$evolution_configured ? 'opacity-50 pointer-events-none' : ''; ?>">
        <h2 id="form-title" class="text-2xl font-semibold text-white mb-6 flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-[#25D366]/20 flex items-center justify-center">
                <i data-lucide="message-circle" class="w-5 h-5 text-[#25D366]"></i>
            </div>
            <span>Nova Mensagem Automática</span>
        </h2>
        
        <form id="evolution-message-form" class="space-y-6">
            <input type="hidden" name="message_id" id="message_id">
            
            <!-- Nome da Mensagem -->
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Nome da Mensagem</label>
                <input type="text" id="message_name" name="message_name" required
                       class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#25D366] text-white"
                       placeholder="Ex: Boas-vindas Compra Aprovada">
            </div>

            <!-- Produto Associado -->
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Produto Associado</label>
                <select id="produto_id" name="produto_id"
                        class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#25D366] text-white">
                    <option value="">Todos os produtos (Global)</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Deixe vazio para disparar para vendas de qualquer produto.</p>
            </div>

            <!-- Evento Disparador -->
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-3">Evento que Dispara a Mensagem</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <label class="flex items-center p-4 bg-dark-elevated border border-dark-border rounded-xl cursor-pointer hover:border-green-500/50 transition-colors has-[:checked]:border-green-500 has-[:checked]:bg-green-900/20">
                        <input type="radio" name="event_type" value="approved" class="form-radio h-5 w-5 text-green-500" required>
                        <div class="ml-3">
                            <span class="text-white font-medium">Compra Aprovada</span>
                            <p class="text-xs text-gray-400">Pagamento confirmado</p>
                        </div>
                    </label>
                    <label class="flex items-center p-4 bg-dark-elevated border border-dark-border rounded-xl cursor-pointer hover:border-yellow-500/50 transition-colors has-[:checked]:border-yellow-500 has-[:checked]:bg-yellow-900/20">
                        <input type="radio" name="event_type" value="pending" class="form-radio h-5 w-5 text-yellow-500">
                        <div class="ml-3">
                            <span class="text-white font-medium">Aguardando Pagamento</span>
                            <p class="text-xs text-gray-400">Pix/Boleto gerado</p>
                        </div>
                    </label>
                    <label class="flex items-center p-4 bg-dark-elevated border border-dark-border rounded-xl cursor-pointer hover:border-red-500/50 transition-colors has-[:checked]:border-red-500 has-[:checked]:bg-red-900/20">
                        <input type="radio" name="event_type" value="rejected" class="form-radio h-5 w-5 text-red-500">
                        <div class="ml-3">
                            <span class="text-white font-medium">Pagamento Recusado</span>
                            <p class="text-xs text-gray-400">Cartão recusado</p>
                        </div>
                    </label>
                    <label class="flex items-center p-4 bg-dark-elevated border border-dark-border rounded-xl cursor-pointer hover:border-purple-500/50 transition-colors has-[:checked]:border-purple-500 has-[:checked]:bg-purple-900/20">
                        <input type="radio" name="event_type" value="refunded" class="form-radio h-5 w-5 text-purple-500">
                        <div class="ml-3">
                            <span class="text-white font-medium">Reembolso</span>
                            <p class="text-xs text-gray-400">Devolução processada</p>
                        </div>
                    </label>
                    <label class="flex items-center p-4 bg-dark-elevated border border-dark-border rounded-xl cursor-pointer hover:border-pink-500/50 transition-colors has-[:checked]:border-pink-500 has-[:checked]:bg-pink-900/20">
                        <input type="radio" name="event_type" value="charged_back" class="form-radio h-5 w-5 text-pink-500">
                        <div class="ml-3">
                            <span class="text-white font-medium">Chargeback</span>
                            <p class="text-xs text-gray-400">Disputa aberta</p>
                        </div>
                    </label>
                    <label class="flex items-center p-4 bg-dark-elevated border border-dark-border rounded-xl cursor-pointer hover:border-amber-500/50 transition-colors has-[:checked]:border-amber-500 has-[:checked]:bg-amber-900/20">
                        <input type="radio" name="event_type" value="info_filled" class="form-radio h-5 w-5 text-amber-500">
                        <div class="ml-3">
                            <span class="text-white font-medium">Carrinho Abandonado</span>
                            <p class="text-xs text-gray-400">Info preenchida no checkout</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Template da Mensagem -->
            <div>
                <label class="block text-gray-300 text-sm font-semibold mb-2">Mensagem</label>
                <textarea id="message_template" name="message_template" rows="6" required
                          class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#25D366] text-white font-mono text-sm"
                          placeholder="Olá {cliente_nome}! 🎉&#10;&#10;Sua compra do produto {produto_nome} foi aprovada!&#10;&#10;Valor: R$ {valor}"></textarea>
                
                <!-- Variáveis Disponíveis -->
                <div class="mt-3 p-4 bg-dark-base rounded-xl border border-dark-border">
                    <p class="text-sm font-semibold text-gray-300 mb-2">Variáveis disponíveis (clique para inserir):</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="insertVariable('{cliente_nome}')" class="px-3 py-1.5 bg-dark-elevated text-[#25D366] text-xs font-mono rounded-lg hover:bg-dark-card border border-dark-border transition-colors">{cliente_nome}</button>
                        <button type="button" onclick="insertVariable('{cliente_email}')" class="px-3 py-1.5 bg-dark-elevated text-[#25D366] text-xs font-mono rounded-lg hover:bg-dark-card border border-dark-border transition-colors">{cliente_email}</button>
                        <button type="button" onclick="insertVariable('{cliente_telefone}')" class="px-3 py-1.5 bg-dark-elevated text-[#25D366] text-xs font-mono rounded-lg hover:bg-dark-card border border-dark-border transition-colors">{cliente_telefone}</button>
                        <button type="button" onclick="insertVariable('{produto_nome}')" class="px-3 py-1.5 bg-dark-elevated text-[#25D366] text-xs font-mono rounded-lg hover:bg-dark-card border border-dark-border transition-colors">{produto_nome}</button>
                        <button type="button" onclick="insertVariable('{valor}')" class="px-3 py-1.5 bg-dark-elevated text-[#25D366] text-xs font-mono rounded-lg hover:bg-dark-card border border-dark-border transition-colors">{valor}</button>
                        <button type="button" onclick="insertVariable('{transacao_id}')" class="px-3 py-1.5 bg-dark-elevated text-[#25D366] text-xs font-mono rounded-lg hover:bg-dark-card border border-dark-border transition-colors">{transacao_id}</button>
                        <button type="button" onclick="insertVariable('{data_compra}')" class="px-3 py-1.5 bg-dark-elevated text-[#25D366] text-xs font-mono rounded-lg hover:bg-dark-card border border-dark-border transition-colors">{data_compra}</button>
                        <button type="button" onclick="insertVariable('{link_checkout}')" class="px-3 py-1.5 bg-dark-elevated text-[#25D366] text-xs font-mono rounded-lg hover:bg-dark-card border border-dark-border transition-colors" title="Link do checkout (recuperação de carrinho)">{link_checkout}</button>
                    </div>
                </div>
            </div>

            <!-- Status Ativo -->
            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" id="is_active" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-dark-border peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#25D366]/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#25D366]"></div>
                </label>
                <span class="text-gray-300 font-medium">Mensagem ativa</span>
            </div>

            <!-- Botões -->
            <div class="flex items-center justify-end space-x-4 pt-4 border-t border-dark-border">
                <button type="button" id="cancel-edit-btn" class="hidden bg-dark-elevated text-gray-300 font-bold py-3 px-6 rounded-xl hover:bg-dark-card transition border border-dark-border">
                    Cancelar
                </button>
                <button type="button" onclick="sendTestMessage()" class="bg-dark-elevated text-white font-bold py-3 px-6 rounded-xl hover:bg-dark-card transition border border-dark-border flex items-center gap-2">
                    <i data-lucide="send" class="w-5 h-5"></i>
                    Enviar Teste
                </button>
                <button type="submit" id="save-btn" class="bg-[#25D366] text-white font-bold py-3 px-6 rounded-xl hover:bg-[#1da851] transition flex items-center gap-2">
                    <i data-lucide="save" class="w-5 h-5"></i>
                    <span>Salvar Mensagem</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Lista de Mensagens Configuradas -->
    <div class="bg-dark-card p-8 rounded-2xl shadow-md border border-dark-border">
        <h2 class="text-2xl font-semibold text-white mb-6 flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-[#25D366]/20 flex items-center justify-center">
                <i data-lucide="list" class="w-5 h-5 text-[#25D366]"></i>
            </div>
            <span>Mensagens Configuradas</span>
        </h2>
        
        <div id="loading-state" class="text-center py-12 text-gray-400" style="display: none;">
            <svg class="animate-spin h-8 w-8 text-[#25D366] mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
            </svg>
            <p class="mt-4 font-medium">Carregando mensagens...</p>
        </div>

        <div id="empty-state" class="text-center py-12 text-gray-400" style="display: none;">
            <i data-lucide="message-circle-off" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
            <p class="text-lg font-medium">Nenhuma mensagem configurada</p>
            <p class="text-sm mt-1">Use o formulário acima para criar sua primeira mensagem automática.</p>
        </div>

        <div id="messages-list" class="space-y-4">
            <!-- Mensagens serão carregadas via JS -->
        </div>
    </div>
</div>

<!-- Modal de Teste -->
<div id="test-modal" class="fixed inset-0 bg-black/70 z-50 hidden items-center justify-center p-4">
    <div class="bg-dark-card rounded-2xl border border-dark-border w-full max-w-md">
        <div class="p-6 border-b border-dark-border">
            <h3 class="text-xl font-bold text-white flex items-center gap-3">
                <i data-lucide="send" class="w-6 h-6 text-[#25D366]"></i>
                Enviar Mensagem de Teste
            </h3>
        </div>
        <div class="p-6">
            <label class="block text-gray-300 text-sm font-semibold mb-2">Número do WhatsApp</label>
            <div class="flex items-center gap-2">
                <span class="text-gray-400 bg-dark-elevated px-4 py-3 rounded-lg border border-dark-border">+55</span>
                <input type="tel" id="test-phone" 
                       class="flex-1 px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#25D366] text-white"
                       placeholder="11999999999" maxlength="11">
            </div>
            <p class="text-xs text-gray-500 mt-2">Digite apenas números (DDD + número)</p>
        </div>
        <div class="p-6 border-t border-dark-border flex justify-end gap-3">
            <button type="button" onclick="closeTestModal()" class="px-6 py-3 bg-dark-elevated text-gray-300 rounded-xl hover:bg-dark-card border border-dark-border transition">
                Cancelar
            </button>
            <button type="button" onclick="confirmSendTest()" class="px-6 py-3 bg-[#25D366] text-white rounded-xl hover:bg-[#1da851] transition flex items-center gap-2">
                <i data-lucide="send" class="w-4 h-4"></i>
                Enviar
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    fetchMessages();

    // Auto-remove toast
    const toast = document.getElementById('toast-msg');
    if(toast) {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            setTimeout(() => toast.remove(), 500);
        }, 4000);
    }
});

const form = document.getElementById('evolution-message-form');
const messageIdInput = document.getElementById('message_id');
const formTitle = document.getElementById('form-title');
const cancelBtn = document.getElementById('cancel-edit-btn');
const saveBtn = document.getElementById('save-btn');
const messagesList = document.getElementById('messages-list');
const loadingState = document.getElementById('loading-state');
const emptyState = document.getElementById('empty-state');

// Inserir variável no textarea
function insertVariable(variable) {
    const textarea = document.getElementById('message_template');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + variable + text.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
}

// Funções de UI
function showLoading() {
    messagesList.style.display = 'none';
    emptyState.style.display = 'none';
    loadingState.style.display = 'block';
}

function showEmptyState() {
    messagesList.style.display = 'none';
    loadingState.style.display = 'none';
    emptyState.style.display = 'block';
}

function showMessagesList() {
    loadingState.style.display = 'none';
    emptyState.style.display = 'none';
    messagesList.style.display = 'block';
}

function resetForm() {
    form.reset();
    messageIdInput.value = '';
    document.getElementById('is_active').checked = true;
    formTitle.querySelector('span').textContent = 'Nova Mensagem Automática';
    saveBtn.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> <span>Salvar Mensagem</span>';
    cancelBtn.classList.add('hidden');
    lucide.createIcons();
}

function getEventLabel(event) {
    const labels = {
        'approved': { text: 'Compra Aprovada', color: 'green' },
        'pending': { text: 'Aguardando Pagamento', color: 'yellow' },
        'rejected': { text: 'Pagamento Recusado', color: 'red' },
        'refunded': { text: 'Reembolso', color: 'purple' },
        'info_filled': { text: 'Carrinho Abandonado', color: 'amber' },
        'charged_back': { text: 'Chargeback', color: 'pink' }
    };
    return labels[event] || { text: event, color: 'gray' };
}

function htmlEscape(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// Fetch mensagens
async function fetchMessages() {
    showLoading();
    try {
        const response = await fetch('/api/api.php?action=get_evolution_messages');
        const result = await response.json();

        if (result.success) {
            messagesList.innerHTML = '';
            if (result.messages.length === 0) {
                showEmptyState();
            } else {
                result.messages.forEach(msg => {
                    const eventInfo = getEventLabel(msg.event_type);
                    const produtoNome = msg.produto_nome || '<span class="text-gray-500">Todos os produtos</span>';
                    
                    const item = document.createElement('div');
                    item.className = 'bg-dark-elevated p-5 rounded-xl border border-dark-border';
                    item.innerHTML = `
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-bold text-white truncate">${htmlEscape(msg.name)}</h3>
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-${eventInfo.color}-900/30 text-${eventInfo.color}-300 border border-${eventInfo.color}-500/30">
                                        ${eventInfo.text}
                                    </span>
                                    ${msg.is_active ? 
                                        '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-900/30 text-green-300 border border-green-500/30">Ativo</span>' : 
                                        '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-900/30 text-gray-400 border border-gray-500/30">Inativo</span>'
                                    }
                                </div>
                                <p class="text-sm text-gray-400 mb-2">Produto: ${produtoNome}</p>
                                <p class="text-sm text-gray-500 line-clamp-2 font-mono bg-dark-base p-2 rounded-lg">${htmlEscape(msg.message_template).substring(0, 100)}${msg.message_template.length > 100 ? '...' : ''}</p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button type="button" onclick="editMessage(${msg.id})" class="p-2.5 bg-yellow-900/30 text-yellow-300 rounded-lg hover:bg-yellow-900/50 transition border border-yellow-500/30" title="Editar">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                </button>
                                <button type="button" onclick="toggleMessage(${msg.id}, ${msg.is_active ? 0 : 1})" class="p-2.5 ${msg.is_active ? 'bg-gray-900/30 text-gray-400 border-gray-500/30' : 'bg-green-900/30 text-green-300 border-green-500/30'} rounded-lg hover:opacity-80 transition border" title="${msg.is_active ? 'Desativar' : 'Ativar'}">
                                    <i data-lucide="${msg.is_active ? 'pause' : 'play'}" class="w-4 h-4"></i>
                                </button>
                                <button type="button" onclick="deleteMessage(${msg.id})" class="p-2.5 bg-red-900/30 text-red-300 rounded-lg hover:bg-red-900/50 transition border border-red-500/30" title="Excluir">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    messagesList.appendChild(item);
                });
                lucide.createIcons();
                showMessagesList();
            }
        } else {
            showToast('Erro ao carregar mensagens: ' + (result.error || 'Erro desconhecido'), 'error');
            showEmptyState();
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro de comunicação com o servidor', 'error');
        showEmptyState();
    }
}

// Salvar mensagem
form.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        name: document.getElementById('message_name').value,
        produto_id: document.getElementById('produto_id').value || null,
        event_type: document.querySelector('input[name="event_type"]:checked')?.value,
        message_template: document.getElementById('message_template').value,
        is_active: document.getElementById('is_active').checked ? 1 : 0
    };

    if (!data.event_type) {
        showToast('Selecione um evento disparador', 'error');
        return;
    }

    const action = messageIdInput.value ? 'update_evolution_message' : 'create_evolution_message';
    if (messageIdInput.value) {
        data.id = parseInt(messageIdInput.value);
    }

    try {
        const response = await fetch(`/api/api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();

        if (result.success) {
            showToast(result.message || 'Mensagem salva com sucesso!', 'success');
            resetForm();
            fetchMessages();
        } else {
            showToast('Erro: ' + (result.error || 'Não foi possível salvar'), 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro de comunicação com o servidor', 'error');
    }
});

cancelBtn.addEventListener('click', resetForm);

// Editar mensagem
async function editMessage(id) {
    try {
        const response = await fetch(`/api/api.php?action=get_evolution_message&id=${id}`);
        const result = await response.json();

        if (result.success && result.message) {
            const msg = result.message;
            messageIdInput.value = msg.id;
            document.getElementById('message_name').value = msg.name;
            document.getElementById('produto_id').value = msg.produto_id || '';
            document.querySelector(`input[name="event_type"][value="${msg.event_type}"]`).checked = true;
            document.getElementById('message_template').value = msg.message_template;
            document.getElementById('is_active').checked = msg.is_active == 1;

            formTitle.querySelector('span').textContent = 'Editar Mensagem';
            saveBtn.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> <span>Atualizar</span>';
            cancelBtn.classList.remove('hidden');
            lucide.createIcons();

            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            showToast('Erro ao carregar mensagem', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro de comunicação', 'error');
    }
}

// Toggle ativo/inativo
async function toggleMessage(id, newStatus) {
    try {
        const response = await fetch('/api/api.php?action=toggle_evolution_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, is_active: newStatus })
        });
        const result = await response.json();

        if (result.success) {
            showToast(newStatus ? 'Mensagem ativada!' : 'Mensagem desativada!', 'success');
            fetchMessages();
        } else {
            showToast('Erro: ' + (result.error || 'Falha ao atualizar'), 'error');
        }
    } catch (error) {
        showToast('Erro de comunicação', 'error');
    }
}

// Excluir mensagem
async function deleteMessage(id) {
    if (!confirm('Tem certeza que deseja excluir esta mensagem?')) return;

    try {
        const response = await fetch('/api/api.php?action=delete_evolution_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await response.json();

        if (result.success) {
            showToast('Mensagem excluída!', 'success');
            fetchMessages();
        } else {
            showToast('Erro: ' + (result.error || 'Falha ao excluir'), 'error');
        }
    } catch (error) {
        showToast('Erro de comunicação', 'error');
    }
}

// Modal de teste
function sendTestMessage() {
    const template = document.getElementById('message_template').value;
    if (!template.trim()) {
        showToast('Digite uma mensagem antes de testar', 'error');
        return;
    }
    document.getElementById('test-modal').classList.remove('hidden');
    document.getElementById('test-modal').classList.add('flex');
}

function closeTestModal() {
    document.getElementById('test-modal').classList.add('hidden');
    document.getElementById('test-modal').classList.remove('flex');
    document.getElementById('test-phone').value = '';
}

async function confirmSendTest() {
    const phone = document.getElementById('test-phone').value.replace(/\D/g, '');
    if (phone.length < 10 || phone.length > 11) {
        showToast('Digite um número válido com DDD', 'error');
        return;
    }

    const template = document.getElementById('message_template').value;
    
    try {
        const response = await fetch('/api/api.php?action=test_evolution_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: '55' + phone, message: template })
        });
        const result = await response.json();

        if (result.success) {
            showToast('Mensagem de teste enviada!', 'success');
            closeTestModal();
        } else {
            showToast('Erro: ' + (result.error || 'Falha ao enviar'), 'error');
        }
    } catch (error) {
        showToast('Erro de comunicação', 'error');
    }
}

// Toast helper
function showToast(message, type = 'info') {
    const existing = document.getElementById('dynamic-toast');
    if (existing) existing.remove();

    const icons = { success: 'check', error: 'alert-circle', warning: 'alert-triangle', info: 'info' };
    const colors = {
        success: 'text-green-400 bg-green-900/30',
        error: 'text-red-400 bg-red-900/30',
        warning: 'text-yellow-400 bg-yellow-900/30',
        info: 'text-blue-400 bg-blue-900/30'
    };

    const toast = document.createElement('div');
    toast.id = 'dynamic-toast';
    toast.className = 'fixed top-5 right-5 z-50 animate-fade-in flex items-center w-full max-w-xs p-4 text-gray-300 bg-dark-card rounded-lg shadow-xl border border-dark-border';
    toast.innerHTML = `
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 ${colors[type]} rounded-lg">
            <i data-lucide="${icons[type]}" class="w-5 h-5"></i>
        </div>
        <div class="ml-3 text-sm font-medium">${message}</div>
        <button type="button" class="ml-auto text-gray-400 hover:text-gray-300 rounded-lg p-1.5 hover:bg-dark-elevated" onclick="this.parentElement.remove()">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;

    document.body.appendChild(toast);
    lucide.createIcons();

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease';
        setTimeout(() => toast.remove(), 500);
    }, 5000);
}

// Testar conexão com Evolution API
function testEvolutionConnection() {
    const serverUrl = document.querySelector('input[name="evolution_server_url"]').value;
    const apiKey = document.querySelector('input[name="evolution_api_key"]').value;
    const instance = document.querySelector('input[name="evolution_instance"]').value;
    
    if (!serverUrl || !apiKey || !instance) {
        showToast('Preencha todos os campos obrigatórios antes de testar.', 'error');
        return;
    }
    
    const testBtn = event.target.closest('button');
    const originalContent = testBtn.innerHTML;
    testBtn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Testando...`;
    testBtn.disabled = true;
    lucide.createIcons();
    
    // Faz requisição para verificar status da instância
    fetch(`${serverUrl}/instance/connectionState/${instance}`, {
        method: 'GET',
        headers: {
            'apikey': apiKey,
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        testBtn.innerHTML = originalContent;
        testBtn.disabled = false;
        lucide.createIcons();
        
        if (data.instance && data.instance.state === 'open') {
            showToast('Conexão estabelecida com sucesso! Instância conectada.', 'success');
        } else if (data.instance) {
            showToast(`Instância encontrada, mas status: ${data.instance.state}. Verifique se está conectada.`, 'warning');
        } else if (data.error) {
            showToast(`Erro: ${data.error}`, 'error');
        } else {
            showToast('Resposta inesperada da API. Verifique as configurações.', 'warning');
        }
    })
    .catch(error => {
        testBtn.innerHTML = originalContent;
        testBtn.disabled = false;
        lucide.createIcons();
        showToast('Erro ao conectar. Verifique a URL e se o servidor está acessível.', 'error');
        console.error('Evolution API Test Error:', error);
    });
}
</script>
