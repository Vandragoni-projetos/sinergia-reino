<?php
/**
 * Configurações PWA - Tela de compra (não ativado) ou configuração (ativado)
 * Ativação: arquivo pwa_activated.key na raiz OU chave pwa_activated=1 no banco (recomendado)
 */
$pwa_activated = false;
$paths_to_try = [
    __DIR__ . '/../../pwa_activated.key',
    isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/pwa_activated.key' : '',
    dirname(__DIR__, 2) . '/pwa_activated.key',
];
foreach ($paths_to_try as $p) {
    if ($p !== '' && file_exists($p)) {
        $pwa_activated = true;
        break;
    }
}
if (!$pwa_activated && function_exists('getSystemSetting') && getSystemSetting('pwa_activated', '0') === '1') {
    $pwa_activated = true;
}
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Configurações PWA</h1>
        <p class="text-gray-400 mt-1">Configure o Progressive Web App (PWA) da plataforma.</p>
    </div>
    <a href="/admin?pagina=admin_dashboard" class="bg-dark-elevated text-gray-300 font-bold py-2 px-4 rounded-lg hover:bg-dark-card transition duration-300 flex items-center space-x-2 border border-dark-border">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
        <span>Voltar</span>
    </a>
</div>

<div id="pwa-status-message" class="hidden px-4 py-3 rounded relative mb-4" role="alert"></div>

<?php if (!$pwa_activated): ?>
<!-- ========== MÓDULO NÃO ATIVADO: TELA DE COMPRA / BENEFÍCIOS ========== -->
<div class="bg-dark-card border border-dark-border rounded-xl overflow-hidden mb-6" style="border-left: 4px solid var(--accent-primary);">
    <div class="p-8 md:p-10">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-14 h-14 rounded-xl flex items-center justify-center" style="background: rgba(18, 231, 104, 0.2);">
                <i data-lucide="smartphone" class="w-8 h-8" style="color: var(--accent-primary);"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-white">Transforme sua Plataforma em um App Nativo</h2>
                <p class="text-gray-400 mt-1">Com o módulo PWA, seus usuários podem instalar sua plataforma diretamente no celular, receber notificações push e ter uma experiência mobile de primeira classe.</p>
            </div>
        </div>
        <a href="#" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition" style="background: var(--accent-primary);" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
            <i data-lucide="shopping-cart" class="w-5 h-5"></i>
            Comprar Módulo PWA →
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
    <div class="bg-dark-card p-6 rounded-lg border border-dark-border">
        <i data-lucide="download" class="w-8 h-8 mb-3" style="color: var(--accent-primary);"></i>
        <h3 class="text-white font-semibold mb-2">Instalação como App</h3>
        <p class="text-gray-400 text-sm">Seus usuários podem instalar sua plataforma na tela inicial do celular, sem loja de aplicativos.</p>
    </div>
    <div class="bg-dark-card p-6 rounded-lg border border-dark-border">
        <i data-lucide="bell" class="w-8 h-8 mb-3" style="color: var(--accent-primary);"></i>
        <h3 class="text-white font-semibold mb-2">Notificações Push</h3>
        <p class="text-gray-400 text-sm">Envie notificações push para aumentar o engajamento.</p>
    </div>
    <div class="bg-dark-card p-6 rounded-lg border border-dark-border">
        <i data-lucide="wifi-off" class="w-8 h-8 mb-3" style="color: var(--accent-primary);"></i>
        <h3 class="text-white font-semibold mb-2">Funciona Offline</h3>
        <p class="text-gray-400 text-sm">Conteúdo acessível mesmo sem internet.</p>
    </div>
    <div class="bg-dark-card p-6 rounded-lg border border-dark-border">
        <i data-lucide="zap" class="w-8 h-8 mb-3" style="color: var(--accent-primary);"></i>
        <h3 class="text-white font-semibold mb-2">Melhor Performance</h3>
        <p class="text-gray-400 text-sm">Carregamento mais rápido e navegação fluida.</p>
    </div>
    <div class="bg-dark-card p-6 rounded-lg border border-dark-border">
        <i data-lucide="smartphone" class="w-8 h-8 mb-3" style="color: var(--accent-primary);"></i>
        <h3 class="text-white font-semibold mb-2">Experiência Mobile</h3>
        <p class="text-gray-400 text-sm">Interface otimizada para iOS e Android.</p>
    </div>
    <div class="bg-dark-card p-6 rounded-lg border border-dark-border">
        <i data-lucide="home" class="w-8 h-8 mb-3" style="color: var(--accent-primary);"></i>
        <h3 class="text-white font-semibold mb-2">Acesso Rápido</h3>
        <p class="text-gray-400 text-sm">Acesso direto da tela inicial, como um app nativo.</p>
    </div>
</div>

<div class="bg-dark-card border border-dark-border rounded-xl p-8 mb-6" style="border-left: 4px solid var(--accent-primary);">
    <h3 class="text-lg font-semibold text-white mb-4">O que está incluído?</h3>
    <ul class="space-y-2 text-gray-300">
        <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0" style="color: var(--accent-primary);"></i> Instalação PWA completa (manifest e service worker)</li>
        <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0" style="color: var(--accent-primary);"></i> Notificações push para iOS e Android</li>
        <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0" style="color: var(--accent-primary);"></i> Painel de configuração administrativa</li>
        <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0" style="color: var(--accent-primary);"></i> Cache e funcionamento offline</li>
        <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0" style="color: var(--accent-primary);"></i> Atualizações automáticas do app</li>
    </ul>
    <p class="mt-6 text-gray-400 text-sm">Após a compra, você receberá um arquivo para enviar à sua hospedagem. Assim que o arquivo estiver no lugar e você atualizar esta página, as opções de configuração serão liberadas.</p>

    <div class="mt-6 pt-6 border-t border-dark-border">
        <p class="text-white font-medium mb-2">Já comprou? Ative pelo banco de dados</p>
        <p class="text-gray-400 text-sm mb-3">A ativação pelo banco não depende de arquivo e não é perdida em atualizações ou limpezas do servidor.</p>
        <button type="button" id="pwa-activate-via-db-btn" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold text-white" style="background: var(--accent-primary);">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            Ativar módulo PWA agora
        </button>
    </div>
</div>

<?php else: ?>
<!-- ========== MÓDULO ATIVADO: ABAS DE CONFIGURAÇÃO ========== -->
<?php
$pwa_config = [];
if (file_exists(__DIR__ . '/../../pwa/pwa_config.php')) {
    require_once __DIR__ . '/../../pwa/pwa_config.php';
    if (function_exists('pwa_get_config')) {
        $pwa_config = pwa_get_config() ?: [];
    }
}
$tab = isset($_GET['tab']) && $_GET['tab'] === 'push' ? 'push' : 'general';
?>
<div class="flex gap-2 mb-6 border-b border-dark-border pb-2">
    <a href="/admin?pagina=admin_pwa&tab=general" class="px-4 py-2 rounded-lg font-medium transition <?php echo $tab === 'general' ? 'bg-dark-elevated text-white border border-dark-border' : 'text-gray-400 hover:text-white'; ?>" style="<?php echo $tab === 'general' ? 'border-color: var(--accent-primary); color: var(--accent-primary);' : ''; ?>">
        <i data-lucide="settings" class="w-4 h-4 inline-block mr-2 align-middle"></i>
        Configuração Geral
    </a>
    <a href="/admin?pagina=admin_pwa&tab=push" class="px-4 py-2 rounded-lg font-medium transition <?php echo $tab === 'push' ? 'bg-dark-elevated text-white border border-dark-border' : 'text-gray-400 hover:text-white'; ?>" style="<?php echo $tab === 'push' ? 'border-color: var(--accent-primary); color: var(--accent-primary);' : ''; ?>">
        <i data-lucide="bell" class="w-4 h-4 inline-block mr-2 align-middle"></i>
        Notificações Push
    </a>
</div>

<?php if ($tab === 'general'): ?>
<!-- Aba Configuração Geral -->
<form id="pwa-config-form" class="space-y-6">
    <div class="bg-dark-card p-8 rounded-lg border border-dark-border">
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5" style="color: var(--accent-primary);"></i>
            Informações Básicas
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-2">Nome do App</label>
                <input type="text" name="app_name" id="pwa_app_name" value="<?php echo htmlspecialchars($pwa_config['app_name'] ?? 'Plataforma'); ?>" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="Nome completo na tela inicial">
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-2">Nome Curto</label>
                <input type="text" name="short_name" id="pwa_short_name" value="<?php echo htmlspecialchars($pwa_config['short_name'] ?? 'App'); ?>" maxlength="12" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="Abaixo do ícone (máx. 12 caracteres)">
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-gray-300 text-sm font-medium mb-2">Descrição (opcional)</label>
            <textarea name="description" id="pwa_description" rows="3" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="Descrição do aplicativo"><?php echo htmlspecialchars($pwa_config['description'] ?? ''); ?></textarea>
        </div>
    </div>

    <div class="bg-dark-card p-8 rounded-lg border border-dark-border">
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
            <i data-lucide="image" class="w-5 h-5" style="color: var(--accent-primary);"></i>
            Ícone do App
        </h2>
        <p class="text-gray-400 text-sm mb-4">Recomendado: 512x512px, PNG. PNG, JPG, WEBP. Máx. 2MB.</p>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <input type="file" id="pwa_icon_file" accept="image/png,image/jpeg,image/webp" class="hidden">
                <label for="pwa_icon_file" class="cursor-pointer inline-flex items-center gap-2 px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white hover:bg-dark-card transition">
                    <i data-lucide="upload" class="w-5 h-5"></i>
                    Selecionar Arquivo
                </label>
            </div>
            <div class="w-24 h-24 rounded-lg border-2 border-dark-border bg-dark-elevated flex items-center justify-center overflow-hidden" id="pwa-icon-preview">
                <?php if (!empty($pwa_config['icon_path'])): ?>
                    <img src="<?php echo htmlspecialchars($pwa_config['icon_path']); ?>" alt="Ícone" class="max-w-full max-h-full object-contain" id="pwa-icon-img">
                <?php else: ?>
                    <i data-lucide="image" class="w-10 h-10 text-gray-500" id="pwa-icon-placeholder"></i>
                <?php endif; ?>
            </div>
            <button type="button" id="pwa-upload-icon-btn" class="px-4 py-3 rounded-lg font-semibold text-white disabled:opacity-50" style="background: var(--accent-primary);" disabled>Enviar Ícone</button>
        </div>
    </div>

    <div class="bg-dark-card p-8 rounded-lg border border-dark-border">
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
            <i data-lucide="palette" class="w-5 h-5" style="color: var(--accent-primary);"></i>
            Cores e Aparência
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-2">Cor do Tema</label>
                <input type="color" name="theme_color" id="pwa_theme_color" value="<?php echo htmlspecialchars($pwa_config['theme_color'] ?? '#12e768'); ?>" class="h-10 w-20 rounded border border-dark-border cursor-pointer bg-transparent">
                <input type="text" id="pwa_theme_color_hex" value="<?php echo htmlspecialchars($pwa_config['theme_color'] ?? '#12e768'); ?>" class="ml-2 w-24 px-2 py-1 bg-dark-elevated border border-dark-border rounded text-white text-sm" maxlength="7">
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-2">Cor de Fundo (splash)</label>
                <input type="color" name="background_color" id="pwa_background_color" value="<?php echo htmlspecialchars($pwa_config['background_color'] ?? '#ffffff'); ?>" class="h-10 w-20 rounded border border-dark-border cursor-pointer bg-transparent">
                <input type="text" id="pwa_background_color_hex" value="<?php echo htmlspecialchars($pwa_config['background_color'] ?? '#ffffff'); ?>" class="ml-2 w-24 px-2 py-1 bg-dark-elevated border border-dark-border rounded text-white text-sm" maxlength="7">
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-gray-300 text-sm font-medium mb-2">Modo de Exibição</label>
            <select name="display_mode" id="pwa_display_mode" class="w-full md:w-64 px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]">
                <option value="standalone" <?php echo ($pwa_config['display_mode'] ?? '') === 'standalone' ? 'selected' : ''; ?>>Standalone (Recomendado)</option>
                <option value="fullscreen" <?php echo ($pwa_config['display_mode'] ?? '') === 'fullscreen' ? 'selected' : ''; ?>>Fullscreen</option>
                <option value="minimal-ui" <?php echo ($pwa_config['display_mode'] ?? '') === 'minimal-ui' ? 'selected' : ''; ?>>Minimal UI</option>
                <option value="browser" <?php echo ($pwa_config['display_mode'] ?? '') === 'browser' ? 'selected' : ''; ?>>Browser</option>
            </select>
            <p class="text-gray-500 text-xs mt-1">Standalone remove a barra de endereço do navegador.</p>
        </div>
    </div>

    <div class="bg-dark-card p-8 rounded-lg border border-dark-border">
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
            <i data-lucide="link" class="w-5 h-5" style="color: var(--accent-primary);"></i>
            URLs
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-2">URL Inicial</label>
                <input type="text" name="start_url" id="pwa_start_url" value="<?php echo htmlspecialchars($pwa_config['start_url'] ?? '/'); ?>" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="/">
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-2">Escopo</label>
                <input type="text" name="scope" id="pwa_scope" value="<?php echo htmlspecialchars($pwa_config['scope'] ?? '/'); ?>" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="/">
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" id="pwa-save-btn" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition" style="background: var(--accent-primary);" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
            <i data-lucide="save" class="w-5 h-5"></i>
            Salvar Configurações PWA
        </button>
    </div>
</form>
<?php endif; ?>

<?php if ($tab === 'push'): ?>
<?php
$pwa_vapid_preview = '';
$pwa_subscriptions_count = 0;
if (!empty($pwa_config['vapid_public_key'])) {
    $pwa_vapid_preview = substr($pwa_config['vapid_public_key'], 0, 20) . '...';
}
if (file_exists(__DIR__ . '/../../pwa/api/web_push_helper.php')) {
    require_once __DIR__ . '/../../pwa/api/web_push_helper.php';
    if (function_exists('pwa_count_subscriptions')) {
        $pwa_subscriptions_count = pwa_count_subscriptions();
    }
}
?>
<!-- Aba Notificações Push -->
<div class="bg-dark-card p-8 rounded-lg border border-dark-border mb-6" style="border-left: 4px solid var(--accent-primary);">
    <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
        <i data-lucide="bell" class="w-5 h-5" style="color: var(--accent-primary);"></i>
        Notificações Push
    </h2>
    <label class="flex items-center gap-3 cursor-pointer">
        <input type="checkbox" id="pwa_push_enabled" name="push_enabled" value="1" <?php echo !empty($pwa_config['push_enabled']) ? 'checked' : ''; ?> class="w-5 h-5 rounded border-dark-border bg-dark-elevated text-[var(--accent-primary)] focus:ring-[var(--accent-primary)]">
        <span class="text-white font-medium">Ativar Notificações Push</span>
    </label>
    <p class="text-gray-400 text-sm mt-2">Permite que o app envie notificações push para os usuários instalados.</p>
    <div class="mt-4">
        <button type="button" id="pwa-save-push-btn" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold text-white" style="background: var(--accent-primary);">Salvar</button>
    </div>

    <div class="mt-6 pt-6 border-t border-dark-border">
        <label class="block text-gray-300 text-sm font-medium mb-2">Chaves VAPID</label>
        <p class="text-gray-400 text-sm mb-3" id="pwa-vapid-status">
            <?php if ($pwa_vapid_preview): ?>
                Chaves configuradas (<?php echo htmlspecialchars($pwa_vapid_preview); ?>)
            <?php else: ?>
                Chaves não configuradas. Gere as chaves para ativar o envio de notificações.
            <?php endif; ?>
        </p>
        <div class="flex flex-wrap gap-2">
            <a href="/pwa/generate_vapid_keys.php" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold text-white" style="background: var(--accent-primary);">
                <i data-lucide="key" class="w-4 h-4"></i>
                Gerar Chaves
            </a>
            <a href="/pwa/generate_vapid_keys.php" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold bg-blue-600/80 text-white hover:bg-blue-600">
                <i data-lucide="file-code" class="w-4 h-4"></i>
                Script Alternativo
            </a>
        </div>
    </div>

    <div class="mt-6 pt-6 border-t border-dark-border">
        <label class="block text-gray-300 text-sm font-medium mb-2">Usuários Inscritos</label>
        <p class="text-2xl font-bold text-white" id="pwa-subscriptions-count"><?php echo (int) $pwa_subscriptions_count; ?></p>
        <p class="text-gray-500 text-xs mt-1">Usuários que aceitaram receber notificações push.</p>
    </div>
</div>

<div class="bg-dark-card p-8 rounded-lg border border-dark-border" style="border-left: 4px solid var(--accent-primary);">
    <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
        <i data-lucide="send" class="w-5 h-5" style="color: var(--accent-primary);"></i>
        Enviar Notificação Push
    </h3>
    <form id="pwa-send-push-form" class="space-y-4">
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Título <span class="text-red-400">*</span></label>
            <input type="text" id="pwa_push_title" required maxlength="200" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="Ex: Nova atualização disponível">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Mensagem <span class="text-red-400">*</span></label>
            <textarea id="pwa_push_message" required rows="3" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="Digite a mensagem da notificação"></textarea>
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">URL (opcional)</label>
            <input type="text" id="pwa_push_url" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="/index?pagina=dashboard" value="/index?pagina=dashboard">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-medium mb-2">Ícone (opcional)</label>
            <input type="text" id="pwa_push_icon" class="w-full px-4 py-3 bg-dark-elevated border border-dark-border rounded-lg text-white focus:ring-2 focus:ring-[var(--accent-primary)]" placeholder="/assets/pix.svg" value="/assets/pix.svg">
        </div>
        <button type="submit" id="pwa-send-push-btn" class="inline-flex items-center gap-2 px-5 py-3 rounded-lg font-semibold text-white transition" style="background: var(--accent-primary);" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
            <i data-lucide="send" class="w-5 h-5"></i>
            Enviar Notificação
        </button>
        <p class="text-gray-500 text-xs mt-3">Após enviar, confira <strong>Enviadas</strong> e <strong>Falhas</strong> no resultado. Se houver falhas, as inscrições podem estar expiradas — peça aos usuários que reabram a área de membros e cliquem em "Receber notificações" de novo. Permissões do site (ícone de cadeado) e do sistema devem estar liberadas; em alguns navegadores a notificação só aparece com a aba em segundo plano.</p>
    </form>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
(function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    var activated = <?php echo $pwa_activated ? 'true' : 'false'; ?>;

    function showPwaMessage(msg, isError) {
        var el = document.getElementById('pwa-status-message');
        if (!el) return;
        el.textContent = msg;
        el.className = 'px-4 py-3 rounded relative mb-4 ' + (isError ? 'bg-red-900/30 border border-red-500 text-red-300' : 'bg-green-900/30 border border-green-500 text-green-300');
        el.classList.remove('hidden');
        setTimeout(function() { el.classList.add('hidden'); }, 5000);
    }

    var activateViaDbBtn = document.getElementById('pwa-activate-via-db-btn');
    if (activateViaDbBtn) {
        activateViaDbBtn.addEventListener('click', function() {
            activateViaDbBtn.disabled = true;
            fetch('/api/admin_api.php?action=activate_pwa_module', { method: 'POST' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showPwaMessage('Módulo ativado. Atualizando...');
                        window.location.reload();
                    } else {
                        showPwaMessage(data.error || 'Erro ao ativar.', true);
                        activateViaDbBtn.disabled = false;
                    }
                })
                .catch(function() {
                    showPwaMessage('Erro de conexão.', true);
                    activateViaDbBtn.disabled = false;
                });
        });
    }

    if (activated) {
        // Sincronizar color picker com input hex
        var themeColor = document.getElementById('pwa_theme_color');
        var themeHex = document.getElementById('pwa_theme_color_hex');
        if (themeColor && themeHex) {
            themeColor.addEventListener('input', function() { themeHex.value = themeColor.value; });
            themeHex.addEventListener('input', function() { if (/^#[0-9A-Fa-f]{6}$/.test(themeHex.value)) themeColor.value = themeHex.value; });
        }
        var bgColor = document.getElementById('pwa_background_color');
        var bgHex = document.getElementById('pwa_background_color_hex');
        if (bgColor && bgHex) {
            bgColor.addEventListener('input', function() { bgHex.value = bgColor.value; });
            bgHex.addEventListener('input', function() { if (/^#[0-9A-Fa-f]{6}$/.test(bgHex.value)) bgColor.value = bgHex.value; });
        }

        // Form submit - Configuração Geral
        var form = document.getElementById('pwa-config-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = document.getElementById('pwa-save-btn');
                if (btn) btn.disabled = true;
                var themeHex = document.getElementById('pwa_theme_color_hex');
                var bgHex = document.getElementById('pwa_background_color_hex');
                var payload = {
                    app_name: document.getElementById('pwa_app_name').value,
                    short_name: document.getElementById('pwa_short_name').value,
                    description: document.getElementById('pwa_description').value,
                    theme_color: themeHex ? themeHex.value : document.getElementById('pwa_theme_color').value,
                    background_color: bgHex ? bgHex.value : document.getElementById('pwa_background_color').value,
                    display_mode: document.getElementById('pwa_display_mode').value,
                    start_url: document.getElementById('pwa_start_url').value || '/',
                    scope: document.getElementById('pwa_scope').value || '/'
                };
                fetch('/api/admin_api.php?action=save_pwa_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) showPwaMessage('Configurações salvas com sucesso.');
                    else showPwaMessage(data.error || 'Erro ao salvar.', true);
                }).catch(function() { showPwaMessage('Erro de conexão.', true); }).finally(function() { if (btn) btn.disabled = false; });
            });
        }

        // Upload ícone
        var iconFile = document.getElementById('pwa_icon_file');
        var uploadIconBtn = document.getElementById('pwa-upload-icon-btn');
        if (iconFile && uploadIconBtn) {
            iconFile.addEventListener('change', function() { uploadIconBtn.disabled = !iconFile.files.length; });
            uploadIconBtn.addEventListener('click', function() {
                if (!iconFile.files.length) return;
                var fd = new FormData();
                fd.append('pwa_icon', iconFile.files[0]);
                uploadIconBtn.disabled = true;
                fetch('/api/admin_api.php?action=upload_pwa_icon', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            showPwaMessage('Ícone enviado.');
                            var img = document.getElementById('pwa-icon-img');
                            var ph = document.getElementById('pwa-icon-placeholder');
                            if (data.icon_url) {
                                if (!img) { img = document.createElement('img'); img.id = 'pwa-icon-img'; img.alt = 'Ícone'; img.className = 'max-w-full max-h-full object-contain'; document.getElementById('pwa-icon-preview').appendChild(img); }
                                img.src = data.icon_url;
                                if (ph) ph.style.display = 'none';
                            }
                        } else showPwaMessage(data.error || 'Erro ao enviar ícone.', true);
                    })
                    .catch(function() { showPwaMessage('Erro de conexão.', true); })
                    .finally(function() { uploadIconBtn.disabled = false; });
            });
        }

        // Salvar apenas push_enabled
        var savePushBtn = document.getElementById('pwa-save-push-btn');
        var pushCheck = document.getElementById('pwa_push_enabled');
        if (savePushBtn && pushCheck) {
            savePushBtn.addEventListener('click', function() {
                savePushBtn.disabled = true;
                fetch('/api/admin_api.php?action=save_pwa_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ push_enabled: pushCheck.checked ? 1 : 0 })
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) showPwaMessage('Configuração de notificações salva.');
                    else showPwaMessage(data.error || 'Erro.', true);
                }).catch(function() { showPwaMessage('Erro de conexão.', true); }).finally(function() { savePushBtn.disabled = false; });
            });
        }

        // Enviar notificação push — restaurar último rascunho
        var sendPushForm = document.getElementById('pwa-send-push-form');
        var sendPushBtn = document.getElementById('pwa-send-push-btn');
        var STORAGE_KEY = 'pwa_push_draft';
        if (sendPushForm && sendPushBtn) {
            try {
                var draft = localStorage.getItem(STORAGE_KEY);
                if (draft) {
                    var d = JSON.parse(draft);
                    var t = document.getElementById('pwa_push_title');
                    var m = document.getElementById('pwa_push_message');
                    var u = document.getElementById('pwa_push_url');
                    var i = document.getElementById('pwa_push_icon');
                    if (t && d.title) t.value = d.title;
                    if (m && d.message) m.value = d.message;
                    if (u && d.url) u.value = d.url;
                    if (i && d.icon) i.value = d.icon;
                }
            } catch (e) {}
            sendPushForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var title = document.getElementById('pwa_push_title').value.trim();
                var message = document.getElementById('pwa_push_message').value.trim();
                if (!title || !message) {
                    showPwaMessage('Preencha título e mensagem.', true);
                    return;
                }
                var url = document.getElementById('pwa_push_url').value.trim() || null;
                var icon = document.getElementById('pwa_push_icon').value.trim() || null;
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({ title: title, message: message, url: url || '', icon: icon || '' }));
                } catch (e) {}
                sendPushBtn.disabled = true;
                fetch('/api/admin_api.php?action=send_pwa_push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title: title, message: message, url: url, icon: icon })
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) {
                        var sent = data.sent || 0;
                        var failed = data.failed || 0;
                        var total = data.total || 0;
                        var msg = 'Notificação enviada. Enviadas: ' + sent + ', falhas: ' + failed;
                        if (failed > 0 || (sent === 0 && total > 0)) {
                            msg += '. Dica: peça ao usuário para verificar as permissões de notificação do site (ícone de cadeado na barra de endereço), reabrir a página da área de membros e aceitar as notificações novamente se necessário. Em celular, verifique também em Configurações do sistema.';
                            showPwaMessage(msg, true);
                        } else {
                            showPwaMessage(msg);
                        }
                        var countEl = document.getElementById('pwa-subscriptions-count');
                        if (countEl) fetch('/api/admin_api.php?action=get_pwa_push_info').then(function(r) { return r.json(); }).then(function(info) { if (info.success && info.subscriptions_count !== undefined) countEl.textContent = info.subscriptions_count; });
                    } else {
                        showPwaMessage(data.error || 'Erro ao enviar.', true);
                    }
                }).catch(function() { showPwaMessage('Erro de conexão.', true); }).finally(function() { sendPushBtn.disabled = false; });
            });
        }
    }
})();
</script>
