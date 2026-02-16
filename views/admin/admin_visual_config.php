<?php
/**
 * Configurações Visuais (White-label)
 * Admin altera cores, fontes, radius, shadow e imagens na UI.
 * O sistema aplica automaticamente via CSS Variables (theme-vars).
 */
require_once __DIR__ . '/../../config/theme_helper.php';
$current_theme = get_theme_json();
// URLs para preview (logo e banner)
$logo_preview_url = '';
if (!empty($current_theme['logo_url'])) {
    $logo_preview_url = (strpos($current_theme['logo_url'], 'http') === 0) ? $current_theme['logo_url'] : '/' . ltrim($current_theme['logo_url'], '/');
}
$banner_preview_url = '';
if (!empty($current_theme['login_banner_url'])) {
    $banner_preview_url = (strpos($current_theme['login_banner_url'], 'http') === 0) ? $current_theme['login_banner_url'] : '/' . ltrim($current_theme['login_banner_url'], '/');
}
?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Configurações Visuais</h1>
        <p class="text-gray-400 mt-1">Personalize cores, fontes, bordas e imagens. O tema é aplicado em todo o sistema.</p>
    </div>
    <a href="/admin?pagina=admin_dashboard" class="bg-dark-elevated text-gray-300 font-bold py-2 px-4 rounded-lg hover:bg-dark-card transition duration-300 flex items-center space-x-2 border border-dark-border">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
        <span>Voltar</span>
    </a>
</div>

<div id="status-message" class="hidden px-4 py-3 rounded relative mb-4" role="alert"></div>

<!-- Cores -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="palette" class="w-6 h-6 text-[#32e768]"></i>
        Cores
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Primária</label>
            <div class="flex gap-2">
                <input type="color" id="theme-primary" class="w-12 h-10 rounded cursor-pointer border border-dark-border bg-transparent">
                <input type="text" id="theme-primary-hex" class="flex-1 px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white font-mono text-sm" placeholder="#32e768" maxlength="7">
            </div>
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Hover Primária</label>
            <div class="flex gap-2">
                <input type="color" id="theme-primary-hover" class="w-12 h-10 rounded cursor-pointer border border-dark-border bg-transparent">
                <input type="text" id="theme-primary-hover-hex" class="flex-1 px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white font-mono text-sm" placeholder="#28d15e" maxlength="7">
            </div>
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Fundo (bg)</label>
            <div class="flex gap-2 items-center">
                <div class="relative w-12 h-10 flex-shrink-0">
                    <div id="theme-bg-preview" class="absolute inset-0 rounded border-2 border-dark-border cursor-pointer" style="background-color: #07090d;" title="Clique para escolher cor"></div>
                    <input type="color" id="theme-bg-color" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Seletor de cor">
                </div>
                <input type="text" id="theme-bg" class="flex-1 px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white font-mono text-sm" placeholder="#07090d" maxlength="7">
            </div>
            <p class="text-xs text-gray-500 mt-1">Aplica em: body, login, área de membros</p>
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Texto</label>
            <input type="text" id="theme-text" class="w-full px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white font-mono text-sm" placeholder="rgba(255,255,255,0.9)">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Texto Muted</label>
            <input type="text" id="theme-text-muted" class="w-full px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white font-mono text-sm" placeholder="rgba(255,255,255,0.5)">
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Card</label>
            <div class="flex gap-2">
                <input type="color" id="theme-card-color" class="w-12 h-10 rounded cursor-pointer border border-dark-border bg-transparent">
                <input type="text" id="theme-card" class="flex-1 px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white font-mono text-sm" placeholder="#1a1f24" maxlength="7">
            </div>
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Borda</label>
            <input type="text" id="theme-border" class="w-full px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white font-mono text-sm" placeholder="rgba(255,255,255,0.1)">
        </div>
    </div>
</div>

<!-- Radius, Shadow, Font -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="layers" class="w-6 h-6 text-[#32e768]"></i>
        Estilo
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Border Radius</label>
            <select id="theme-radius" class="w-full px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white">
                <option value="0">Sem arredondamento</option>
                <option value="0.25rem">Pequeno (4px)</option>
                <option value="0.5rem" selected>Médio (8px)</option>
                <option value="0.75rem">Grande (12px)</option>
                <option value="1rem">Extra (16px)</option>
                <option value="1.5rem">2xl (24px)</option>
            </select>
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Shadow Preset</label>
            <select id="theme-shadow" class="w-full px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white">
                <option value="none">Nenhum</option>
                <option value="0 1px 3px rgba(0,0,0,0.2)">Sutil</option>
                <option value="0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -1px rgba(0,0,0,0.2)" selected>Padrão</option>
                <option value="0 10px 15px -3px rgba(0,0,0,0.3), 0 4px 6px -2px rgba(0,0,0,0.2)">Elevado</option>
                <option value="0 20px 25px -5px rgba(0,0,0,0.3), 0 10px 10px -5px rgba(0,0,0,0.2)">Forte</option>
            </select>
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Fonte Sans</label>
            <select id="theme-font" class="w-full px-3 py-2 bg-dark-elevated border border-dark-border rounded text-white">
                <option value="'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" selected>Inter</option>
                <option value="'Roboto', sans-serif">Roboto</option>
                <option value="'Open Sans', sans-serif">Open Sans</option>
                <option value="'Poppins', sans-serif">Poppins</option>
                <option value="'Montserrat', sans-serif">Montserrat</option>
                <option value="system-ui, sans-serif">System UI</option>
            </select>
        </div>
    </div>
    <!-- Caixa de demonstração: onde radius, shadow e fonte são aplicados -->
    <div class="mt-6 p-4 bg-dark-elevated border border-dark-border rounded-lg">
        <p class="text-gray-400 text-sm mb-3"><i data-lucide="info" class="w-4 h-4 inline mr-1"></i> Onde o Estilo é aplicado: cards, itens do menu, inputs e botões em todo o sistema.</p>
        <div id="estilo-demo-box" class="p-4 bg-dark-card border border-dark-border flex items-center justify-between gap-4">
            <p class="text-white" style="font-family: var(--theme-font-sans);">Esta caixa usa o radius e shadow selecionados. Clique em "Salvar+PFK5" para aplicar em todo o sistema (login, área de membros, etc.).</p>
            <button type="button" id="theme-save-demo-btn" class="px-4 py-2 font-semibold transition" style="background-color: var(--accent-primary); font-family: var(--theme-font-sans); border-radius: var(--theme-radius, 0.5rem);">Salvar+PFK5</button>
        </div>
    </div>
</div>

<!-- Imagens -->
<div class="bg-dark-card p-8 rounded-lg shadow-md mb-6 border border-dark-border">
    <h2 class="text-2xl font-semibold mb-6 text-white flex items-center gap-2">
        <i data-lucide="image" class="w-6 h-6 text-[#32e768]"></i>
        Imagens
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Logo</label>
            <div class="flex flex-col sm:flex-row items-start gap-4">
                <div class="w-32 h-32 flex-shrink-0 border-2 border-dark-border bg-dark-elevated rounded-lg flex items-center justify-center overflow-hidden" id="theme-logo-preview-wrap">
                    <img id="theme-logo-preview" src="<?php echo htmlspecialchars($logo_preview_url); ?>" alt="Logo" class="max-w-full max-h-full object-contain <?php echo empty($logo_preview_url) ? 'hidden' : ''; ?>">
                    <i data-lucide="image" class="w-12 h-12 text-gray-500 <?php echo !empty($logo_preview_url) ? 'hidden' : ''; ?>" id="theme-logo-placeholder"></i>
                </div>
                <div class="flex-1">
                    <input type="file" id="theme-logo-file" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="hidden">
                    <label for="theme-logo-file" class="cursor-pointer inline-flex items-center px-4 py-2 bg-dark-elevated border border-dark-border rounded text-white hover:bg-dark-card transition mb-2">
                        <i data-lucide="upload" class="w-4 h-4 mr-2"></i>Selecionar
                    </label>
                    <p id="theme-logo-filename" class="text-gray-400 text-sm mt-1"><?php echo !empty($current_theme['logo_url']) ? basename($current_theme['logo_url']) : 'Nenhum arquivo escolhido'; ?></p>
                    <p class="text-xs text-gray-400">JPG, PNG, WEBP, SVG. Máx 2MB</p>
                </div>
            </div>
        </div>
        <div>
            <label class="block text-gray-300 text-sm font-semibold mb-2">Banner Login</label>
            <div class="flex flex-col sm:flex-row items-start gap-4">
                <div class="w-32 h-24 flex-shrink-0 border-2 border-dark-border bg-dark-elevated rounded-lg flex items-center justify-center overflow-hidden" id="theme-banner-preview-wrap">
                    <img id="theme-banner-preview" src="<?php echo htmlspecialchars($banner_preview_url); ?>" alt="Banner Login" class="max-w-full max-h-full object-cover <?php echo empty($banner_preview_url) ? 'hidden' : ''; ?>">
                    <i data-lucide="image" class="w-10 h-10 text-gray-500 <?php echo !empty($banner_preview_url) ? 'hidden' : ''; ?>" id="theme-banner-placeholder"></i>
                </div>
                <div class="flex-1">
                    <input type="file" id="theme-login-banner-file" accept="image/jpeg,image/png,image/webp" class="hidden">
                    <label for="theme-login-banner-file" class="cursor-pointer inline-flex items-center px-4 py-2 bg-dark-elevated border border-dark-border rounded text-white hover:bg-dark-card transition mb-2">
                        <i data-lucide="upload" class="w-4 h-4 mr-2"></i>Selecionar
                    </label>
                    <p id="theme-login-banner-filename" class="text-gray-400 text-sm mt-1"><?php echo !empty($current_theme['login_banner_url']) ? basename($current_theme['login_banner_url']) : 'Nenhum arquivo escolhido'; ?></p>
                    <p class="text-xs text-gray-400">JPG, PNG, WEBP. Máx 5MB</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Botões Ação -->
<div class="flex flex-wrap gap-4">
    <button type="button" id="theme-preview-btn" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition">
        <i data-lucide="eye" class="w-5 h-5 inline mr-2"></i>Preview (ao vivo)
    </button>
    <button type="button" id="theme-save-btn" class="px-6 py-3 text-white font-bold rounded-lg transition" style="background-color: var(--accent-primary);" onmouseover="this.style.backgroundColor='var(--accent-primary-hover)'" onmouseout="this.style.backgroundColor='var(--accent-primary)'">
        <i data-lucide="save" class="w-5 h-5 inline mr-2"></i>Salvar
    </button>
    <button type="button" id="theme-reset-btn" class="px-6 py-3 bg-dark-elevated hover:bg-dark-card text-gray-300 font-bold rounded-lg transition border border-dark-border">
        <i data-lucide="rotate-ccw" class="w-5 h-5 inline mr-2"></i>Restaurar padrão
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    
    const theme = <?php echo json_encode($current_theme); ?>;
    const statusEl = document.getElementById('status-message');
    
    function showStatus(msg, isError = false) {
        statusEl.textContent = msg;
        statusEl.className = 'px-4 py-3 rounded relative mb-4 ' + (isError ? 'bg-red-900/30 border border-red-500 text-red-300' : 'bg-green-900/30 border border-green-500 text-green-300');
        statusEl.classList.remove('hidden');
        setTimeout(() => statusEl.classList.add('hidden'), 4000);
    }
    
    // Preencher formulário
    document.getElementById('theme-primary').value = theme.primary || '#32e768';
    document.getElementById('theme-primary-hex').value = theme.primary || '#32e768';
    document.getElementById('theme-primary-hover').value = theme.primaryHover || '#28d15e';
    document.getElementById('theme-primary-hover-hex').value = theme.primaryHover || '#28d15e';
    const bgVal = (theme.bg || '#07090d').toString().trim();
    const bgHex = /^#[0-9A-Fa-f]{6}$/.test(bgVal) ? bgVal : '#07090d';
    document.getElementById('theme-bg').value = bgHex;
    document.getElementById('theme-bg-color').value = bgHex;
    const bgPreviewEl = document.getElementById('theme-bg-preview');
    if (bgPreviewEl) bgPreviewEl.style.backgroundColor = bgHex;
    document.getElementById('theme-text').value = theme.text || 'rgba(255, 255, 255, 0.9)';
    document.getElementById('theme-text-muted').value = theme.textMuted || 'rgba(255, 255, 255, 0.5)';
    const cardVal = (theme.card || '#1a1f24').toString().trim();
    const cardHex = /^#[0-9A-Fa-f]{6}$/.test(cardVal) ? cardVal : '#1a1f24';
    document.getElementById('theme-card').value = cardHex;
    document.getElementById('theme-card-color').value = cardHex;
    document.getElementById('theme-border').value = theme.border || 'rgba(255, 255, 255, 0.1)';
    document.getElementById('theme-radius').value = theme.radius || '0.5rem';
    document.getElementById('theme-shadow').value = theme.shadow || '0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -1px rgba(0,0,0,0.2)';
    document.getElementById('theme-font').value = theme.fontSans || "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
    
    // Sync color inputs
    document.getElementById('theme-primary').addEventListener('input', e => document.getElementById('theme-primary-hex').value = e.target.value);
    document.getElementById('theme-primary-hex').addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) document.getElementById('theme-primary').value = e.target.value; });
    document.getElementById('theme-primary-hover').addEventListener('input', e => document.getElementById('theme-primary-hover-hex').value = e.target.value);
    document.getElementById('theme-primary-hover-hex').addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) document.getElementById('theme-primary-hover').value = e.target.value; });
    document.getElementById('theme-bg-color').addEventListener('input', function(e) {
        const v = e.target.value;
        document.getElementById('theme-bg').value = v;
        if (bgPreviewEl) bgPreviewEl.style.backgroundColor = v;
    });
    document.getElementById('theme-bg').addEventListener('input', function(e) {
        if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
            document.getElementById('theme-bg-color').value = e.target.value;
            if (bgPreviewEl) bgPreviewEl.style.backgroundColor = e.target.value;
        }
    });
    document.getElementById('theme-card-color').addEventListener('input', e => document.getElementById('theme-card').value = e.target.value);
    document.getElementById('theme-card').addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) document.getElementById('theme-card-color').value = e.target.value; });
    
    // Preview: aplica ao vivo no #theme-vars (substitui apenas :root, preserva .bg-primary etc)
    document.getElementById('theme-preview-btn').addEventListener('click', function() {
        const vars = {
            '--brand-primary': document.getElementById('theme-primary-hex').value,
            '--brand-primary-hover': document.getElementById('theme-primary-hover-hex').value,
            '--accent-primary': document.getElementById('theme-primary-hex').value,
            '--accent-primary-hover': document.getElementById('theme-primary-hover-hex').value,
            '--theme-bg': document.getElementById('theme-bg').value,
            '--theme-text': document.getElementById('theme-text').value,
            '--theme-text-muted': document.getElementById('theme-text-muted').value,
            '--theme-card': document.getElementById('theme-card').value,
            '--theme-card-elevated': document.getElementById('theme-card').value,
            '--theme-border': document.getElementById('theme-border').value,
            '--theme-radius': document.getElementById('theme-radius').value,
            '--theme-shadow': document.getElementById('theme-shadow').value,
            '--theme-font-sans': document.getElementById('theme-font').value
        };
        const styleEl = document.getElementById('theme-vars');
        if (styleEl) {
            const rootCss = ':root {\n' + Object.entries(vars).map(([k,v]) => '    ' + k + ': ' + v + ';').join('\n') + '\n}';
            // Substitui apenas o bloco :root, preserva .bg-primary, .sidebar-item-active etc
            const current = styleEl.textContent;
            const newContent = current.replace(/:root\s*\{[^}]*\}/s, rootCss.trim());
            styleEl.textContent = newContent;
        }
        // Atualiza caixa de demonstração do Estilo com os valores atuais
        const demoBox = document.getElementById('estilo-demo-box');
        if (demoBox) {
            demoBox.style.borderRadius = vars['--theme-radius'];
            demoBox.style.boxShadow = vars['--theme-shadow'];
            demoBox.style.fontFamily = vars['--theme-font-sans'];
        }
        showStatus('Preview aplicado. Veja a caixa "Onde o Estilo é aplicado" e o menu lateral.');
    });
    
    async function doSaveTheme() {
        const data = {
            primary: document.getElementById('theme-primary-hex').value,
            primaryHover: document.getElementById('theme-primary-hover-hex').value,
            bg: document.getElementById('theme-bg').value,
            text: document.getElementById('theme-text').value,
            textMuted: document.getElementById('theme-text-muted').value,
            card: document.getElementById('theme-card').value,
            cardElevated: theme.cardElevated || '#0f1419',
            border: document.getElementById('theme-border').value,
            radius: document.getElementById('theme-radius').value,
            shadow: document.getElementById('theme-shadow').value,
            fontSans: document.getElementById('theme-font').value
        };
        try {
            const r = await fetch('/api/admin_api?action=save_theme', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const res = await r.json();
            if (res.success) {
                // Aplica Preview na página atual para ver alterações sem recarregar
                const vars = {
                    '--brand-primary': data.primary,
                    '--brand-primary-hover': data.primaryHover,
                    '--accent-primary': data.primary,
                    '--accent-primary-hover': data.primaryHover,
                    '--theme-bg': data.bg,
                    '--theme-text': data.text,
                    '--theme-text-muted': data.textMuted,
                    '--theme-card': data.card,
                    '--theme-card-elevated': data.cardElevated || '#0f1419',
                    '--theme-border': data.border,
                    '--theme-radius': data.radius,
                    '--theme-shadow': data.shadow,
                    '--theme-font-sans': data.fontSans
                };
                const styleEl = document.getElementById('theme-vars');
                if (styleEl) {
                    const rootCss = ':root {\n' + Object.entries(vars).map(([k,v]) => '    ' + k + ': ' + v + ';').join('\n') + '\n}';
                    styleEl.textContent = styleEl.textContent.replace(/:root\s*\{[^}]*\}/s, rootCss.trim());
                }
                const demoBox = document.getElementById('estilo-demo-box');
                if (demoBox) { demoBox.style.borderRadius = data.radius; demoBox.style.boxShadow = data.shadow; }
                showStatus('Tema salvo! Já aplicado nesta página. Login e área de membros usarão o novo tema.');
            } else {
                showStatus(res.error || 'Erro ao salvar.', true);
            }
        } catch (e) {
            showStatus('Erro de conexão.', true);
        }
    }
    
    document.getElementById('theme-save-btn').addEventListener('click', doSaveTheme);
    document.getElementById('theme-save-demo-btn').addEventListener('click', doSaveTheme);
    
    // Reset
    document.getElementById('theme-reset-btn').addEventListener('click', function() {
        if (confirm('Restaurar tema padrão?')) {
            document.getElementById('theme-primary-hex').value = '#32e768';
            document.getElementById('theme-primary').value = '#32e768';
            document.getElementById('theme-primary-hover-hex').value = '#28d15e';
            document.getElementById('theme-primary-hover').value = '#28d15e';
            document.getElementById('theme-bg').value = '#07090d';
            document.getElementById('theme-bg-color').value = '#07090d';
            if (document.getElementById('theme-bg-preview')) document.getElementById('theme-bg-preview').style.backgroundColor = '#07090d';
            document.getElementById('theme-text').value = 'rgba(255, 255, 255, 0.9)';
            document.getElementById('theme-text-muted').value = 'rgba(255, 255, 255, 0.5)';
            document.getElementById('theme-card').value = '#1a1f24';
            document.getElementById('theme-card-color').value = '#1a1f24';
            document.getElementById('theme-border').value = 'rgba(255, 255, 255, 0.1)';
            document.getElementById('theme-radius').value = '0.5rem';
            document.getElementById('theme-shadow').value = '0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -1px rgba(0,0,0,0.2)';
            document.getElementById('theme-font').value = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
        }
    });
    
    // Upload logo
    document.getElementById('theme-logo-file').addEventListener('change', async function(e) {
        const f = e.target.files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('logo', f);
        try {
            const r = await fetch('/api/admin_api?action=upload_logo', { method: 'POST', body: fd });
            const res = await r.json();
            if (res.success) {
                const img = document.getElementById('theme-logo-preview');
                const ph = document.getElementById('theme-logo-placeholder');
                img.src = (res.url || '') + (res.url && res.url.indexOf('?') < 0 ? '?t=' + Date.now() : '');
                img.classList.remove('hidden');
                if (ph) ph.classList.add('hidden');
                document.getElementById('theme-logo-filename').textContent = f.name;
                showStatus('Logo enviada com sucesso.');
            } else {
                showStatus(res.error || 'Erro no upload.', true);
            }
        } catch (e) { showStatus('Erro de conexão.', true); }
    });
    
    // Upload banner login
    document.getElementById('theme-login-banner-file').addEventListener('change', async function(e) {
        const f = e.target.files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('login_image', f);
        try {
            const r = await fetch('/api/admin_api?action=upload_login_image', { method: 'POST', body: fd });
            const res = await r.json();
            if (res.success) {
                const img = document.getElementById('theme-banner-preview');
                const ph = document.getElementById('theme-banner-placeholder');
                img.src = (res.url || '') + (res.url && res.url.indexOf('?') < 0 ? '?t=' + Date.now() : '');
                img.classList.remove('hidden');
                if (ph) ph.classList.add('hidden');
                document.getElementById('theme-login-banner-filename').textContent = f.name;
                showStatus('Banner enviado com sucesso.');
            } else {
                showStatus(res.error || 'Erro no upload.', true);
            }
        } catch (e) { showStatus('Erro de conexão.', true); }
    });
    
    // Aplica estilo inicial na caixa de demonstração
    const demoBox = document.getElementById('estilo-demo-box');
    if (demoBox) {
        demoBox.style.borderRadius = theme.radius || '0.5rem';
        demoBox.style.boxShadow = theme.shadow || '0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -1px rgba(0,0,0,0.2)';
    }
    
    lucide.createIcons();
});
</script>
