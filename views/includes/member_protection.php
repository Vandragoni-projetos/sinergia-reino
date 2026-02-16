<?php
/**
 * Proteção da Área de Membros: Watermark, Anti-Print, Anti-DevTools
 * Incluir no final do body das páginas: member_area_dashboard, member_course_view, member_licenses
 * O container principal deve ter a classe: member-protected-content
 */
$helper_path = __DIR__ . '/../../helpers/member_protection_helper.php';
if (!file_exists($helper_path)) return;
require_once $helper_path;
if (!function_exists('isMemberProtectionEnabled') || !isMemberProtectionEnabled()) {
    return;
}

$watermark_user = $_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'Usuário';
$watermark_email = $_SESSION['usuario'] ?? '';
$watermark_id = (string)($_SESSION['id'] ?? $_SESSION['usuario_id'] ?? '');
$watermark_slug = 'club';
if (function_exists('getCommunityContext')) {
    $ctx = getCommunityContext();
    $watermark_slug = $ctx['slug'] ?? 'club';
}
$watermark_text = trim("$watermark_user | ID:$watermark_id | $watermark_slug");
?>
<style id="member-protection-styles">
/* Anti-Print */
@media print {
    body * { visibility: hidden !important; }
    .print-blocker-msg { visibility: visible !important; position: fixed !important; inset: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important; background: #111 !important; color: #fff !important; font-size: 1.5rem !important; z-index: 999999 !important; }
}
/* Watermark overlay */
#member-watermark-overlay {
    position: fixed; inset: 0; pointer-events: none; z-index: 9998; overflow: hidden;
    user-select: none; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none;
}
.member-watermark-item {
    position: absolute; white-space: nowrap; opacity: 0.1; font-size: 12px; color: #999;
    transform: rotate(-35deg); pointer-events: none; font-family: monospace;
    text-shadow: 0 0 1px rgba(0,0,0,0.3);
}
@media (max-width: 639px) {
    .member-watermark-item { font-size: 10px; }
}
/* Blur quando DevTools detectado */
body.devtools-detected .member-protected-content { filter: blur(8px); pointer-events: none; user-select: none; }
/* Modais */
#member-protection-print-modal, #member-protection-devtools-modal {
    display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(0,0,0,0.9);
    align-items: center; justify-content: center; padding: 1rem;
}
#member-protection-print-modal.visible, #member-protection-devtools-modal.visible {
    display: flex !important;
}
.member-protection-modal-box {
    background: #1f2937; border: 2px solid #ef4444; border-radius: 1rem; padding: 2rem; max-width: 400px; text-align: center;
}
.member-protection-modal-box h3 { color: #fca5a5; margin-bottom: 1rem; font-size: 1.25rem; }
.member-protection-modal-box p { color: #d1d5db; font-size: 0.95rem; }
</style>

<div id="member-watermark-overlay" aria-hidden="true"></div>
<div class="print-blocker-msg" style="display:none; visibility:hidden;">Conteúdo protegido. Impressão bloqueada.</div>

<div id="member-protection-print-modal" role="alertdialog" aria-labelledby="print-modal-title">
    <div class="member-protection-modal-box">
        <h3 id="print-modal-title">Conteúdo protegido</h3>
        <p>Impressão bloqueada. Este conteúdo não pode ser impresso.</p>
    </div>
</div>

<div id="member-protection-devtools-modal" role="alertdialog" aria-labelledby="devtools-modal-title">
    <div class="member-protection-modal-box">
        <h3 id="devtools-modal-title">Conteúdo protegido</h3>
        <p>Feche o DevTools (F12) para continuar visualizando o conteúdo.</p>
    </div>
</div>

<script>
(function() {
'use strict';
var CONFIG = {
    watermarkText: <?php echo json_encode($watermark_text); ?>,
    watermarkUpdateSeconds: 30,
    logUrl: '/api/security_log',
    page: window.location.pathname + (window.location.search || '')
};

// 1) WATERMARK
function initWatermark() {
    var overlay = document.getElementById('member-watermark-overlay');
    if (!overlay) return;
    function render() {
        overlay.innerHTML = '';
        var now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        var text = CONFIG.watermarkText + ' | ' + now;
        var spacing = 180;
        for (var i = -2; i <= Math.ceil(window.innerWidth / spacing) + 2; i++) {
            for (var j = -2; j <= Math.ceil(window.innerHeight / spacing) + 2; j++) {
                var el = document.createElement('div');
                el.className = 'member-watermark-item';
                el.textContent = text;
                el.style.left = (i * spacing) + 'px';
                el.style.top = (j * spacing) + 'px';
                overlay.appendChild(el);
            }
        }
    }
    render();
    setInterval(render, CONFIG.watermarkUpdateSeconds * 1000);
    window.addEventListener('resize', function() { setTimeout(render, 100); });
}

// 2) ANTI-PRINT
function initAntiPrint() {
    window.addEventListener('beforeprint', function(e) {
        e.preventDefault();
        document.getElementById('member-protection-print-modal')?.classList.add('visible');
        fetch(CONFIG.logUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'event_type=print_attempt&page='+encodeURIComponent(CONFIG.page), credentials: 'same-origin' }).catch(function(){});
    });
    window.addEventListener('afterprint', function() {
        document.getElementById('member-protection-print-modal')?.classList.remove('visible');
    });
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            document.getElementById('member-protection-print-modal')?.classList.add('visible');
            fetch(CONFIG.logUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'event_type=print_attempt&page='+encodeURIComponent(CONFIG.page), credentials: 'same-origin' }).catch(function(){});
            setTimeout(function() { document.getElementById('member-protection-print-modal')?.classList.remove('visible'); }, 3000);
        }
    });
}

// 3) ANTI-DEVTOOLS
function initAntiDevTools() {
    document.addEventListener('contextmenu', function(e) { e.preventDefault(); }, false);
    var blocked = ['F12', 'I', 'J', 'U', 'S'];
    document.addEventListener('keydown', function(e) {
        var k = e.key.toUpperCase();
        if (k === 'F12') { e.preventDefault(); logShortcut('F12'); return; }
        if ((e.ctrlKey || e.metaKey) && (e.shiftKey && (k === 'I' || k === 'J'))) { e.preventDefault(); logShortcut('Ctrl+Shift+' + k); return; }
        if ((e.ctrlKey || e.metaKey) && (k === 'U' || k === 'S')) { e.preventDefault(); logShortcut('Ctrl+' + k); return; }
    });
    function logShortcut(name) {
        fetch(CONFIG.logUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'event_type=blocked_shortcut&page='+encodeURIComponent(CONFIG.page), credentials: 'same-origin' }).catch(function(){});
    }
    var devtoolsOpen = false;
    function checkDevTools() {
        var threshold = 160;
        var w = window.outerWidth - window.innerWidth;
        var h = window.outerHeight - window.innerHeight;
        var open = w > threshold || h > threshold;
        if (open && !devtoolsOpen) {
            devtoolsOpen = true;
            document.body.classList.add('devtools-detected');
            document.getElementById('member-protection-devtools-modal')?.classList.add('visible');
            fetch(CONFIG.logUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'event_type=devtools_detected&page='+encodeURIComponent(CONFIG.page), credentials: 'same-origin' }).catch(function(){});
        } else if (!open && devtoolsOpen) {
            devtoolsOpen = false;
            document.body.classList.remove('devtools-detected');
            document.getElementById('member-protection-devtools-modal')?.classList.remove('visible');
        }
    }
    setInterval(checkDevTools, 1000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initWatermark();
        initAntiPrint();
        initAntiDevTools();
    });
} else {
    initWatermark();
    initAntiPrint();
    initAntiDevTools();
}
})();
</script>
