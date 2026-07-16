<?php
// admin.php já inclui config.php e lida com a sessão e verificação de admin.
// Este arquivo agora se torna uma interface cliente-lado para a admin_api.php

// Não há mais lógica de POST ou fetch direto do DB neste arquivo PHP.
// Toda interação com o backend será via AJAX.
// Apenas preparamos o HTML.
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Configurações de E-mail</h1>
        <p class="text-gray-400 mt-1">Configure o serviço de e-mail e os modelos de entrega.</p>
    </div>
    <a href="/admin?pagina=admin_dashboard" class="bg-dark-elevated text-gray-300 font-bold py-2 px-4 rounded-lg hover:bg-dark-card transition duration-300 flex items-center space-x-2 border border-dark-border">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
        <span>Voltar ao Dashboard</span>
    </a>
</div>

<div id="status-message" class="hidden bg-blue-900/20 border border-blue-500/30 text-blue-300 px-4 py-3 rounded relative mb-4" role="alert"></div>

<div class="bg-dark-card rounded-lg shadow-md p-6 border border-[#32e768]"> <!-- Alterado p-8 para p-6 e removido container mx-auto -->
    <h2 class="text-2xl font-semibold mb-6 text-white">Detalhes do Servidor SMTP</h2>
    
    <form id="email-settings-form">
        <!-- SMTP Settings -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="smtp_host" class="block text-gray-300 text-sm font-semibold mb-2">Host SMTP</label>
                <input type="text" id="smtp_host" name="smtp_host" required
                       class="form-input-style" placeholder="Ex: smtp.seudominio.com">
            </div>
            <div>
                <label for="smtp_port" class="block text-gray-300 text-sm font-semibold mb-2">Porta SMTP</label>
                <input type="number" id="smtp_port" name="smtp_port" required
                       class="form-input-style" placeholder="Ex: 587 ou 465">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="smtp_username" class="block text-gray-300 text-sm font-semibold mb-2">Usuário SMTP (E-mail)</label>
                <input type="email" id="smtp_username" name="smtp_username" required
                       class="form-input-style" placeholder="Ex: seuemail@seudominio.com">
            </div>
            <div>
                <label for="smtp_password" class="block text-gray-300 text-sm font-semibold mb-2">Senha SMTP / Senha de App</label>
                <input type="password" id="smtp_password" name="smtp_password"
                       class="form-input-style" placeholder="Digite apenas para alterar"
                       autocomplete="new-password" value="">
                <p id="smtp-password-status" class="text-xs mt-1 text-gray-400">
                    Deixe em branco para manter a senha atual salva no banco.
                </p>
                <p class="text-xs text-amber-300/80 mt-1">
                    Gmail exige <strong>Senha de App</strong> (não a senha da conta). Se o campo aparecer preenchido sozinho, limpe-o — é preenchimento automático do navegador.
                </p>
            </div>
        </div>

        <div class="mb-6">
            <label for="smtp_encryption" class="block text-gray-300 text-sm font-semibold mb-2">Criptografia</label>
            <select id="smtp_encryption" name="smtp_encryption" class="form-input-style" required>
                <option value="tls">TLS (Recomendado)</option>
                <option value="ssl">SSL</option>
                <option value="none">Nenhuma</option>
            </select>
        </div>

        <h2 class="text-2xl font-semibold mb-6 text-white border-t border-dark-border pt-6">Detalhes do Remetente</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="smtp_from_email" class="block text-gray-300 text-sm font-semibold mb-2">E-mail do Remetente</label>
                <input type="email" id="smtp_from_email" name="smtp_from_email" required
                       class="form-input-style" placeholder="Ex: noreply@seudominio.com">
                <p class="text-xs text-gray-400 mt-1 text-blue-300">
                    <i data-lucide="info" class="w-3 h-3 inline-block mr-1"></i>
                    Para evitar erros, este e-mail deve ser o mesmo que o "Usuário SMTP" na maioria dos provedores.
                </p>
            </div>
            <div>
                <label for="smtp_from_name" class="block text-gray-300 text-sm font-semibold mb-2">Nome do Remetente</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" required
                       class="form-input-style" placeholder="Ex: GatewayPro Notificações">
            </div>
        </div>

        <!-- NOVO: Configurações de E-mail de Entrega -->
        <h2 class="text-2xl font-semibold mb-6 text-white border-t border-dark-border pt-6">E-mail de Entrega do Produto</h2>
        <p class="text-sm text-gray-400 mb-4">Personalize o e-mail que seus clientes recebem após a compra com os acessos aos produtos.</p>

        <div class="mb-6">
            <label for="email_template_delivery_subject" class="block text-gray-300 text-sm font-semibold mb-2">Assunto do E-mail</label>
            <input type="text" id="email_template_delivery_subject" name="email_template_delivery_subject" required
                   class="form-input-style" placeholder="Ex: Acesso ao seu Produto!">
        </div>

        <div class="mb-6">
            <label class="block text-gray-300 text-sm font-semibold mb-2">Conteúdo HTML do E-mail</label>
            <div class="border border-dark-border rounded-lg overflow-hidden">
                <div class="p-4 bg-dark-elevated flex justify-between items-center border-b border-dark-border">
                    <span class="font-medium text-white">Pré-visualização</span>
                    <button type="button" id="edit-html-template-btn" class="bg-blue-900/30 text-blue-300 font-semibold py-1.5 px-3 rounded-md hover:bg-blue-900/50 transition text-sm border border-blue-500/30">
                        <i data-lucide="edit" class="w-4 h-4 inline-block mr-1"></i> Editar HTML
                    </button>
                </div>
                <div id="email-template-preview" class="p-4 bg-dark-card min-h-[200px] border-b border-dark-border text-white">
                    <!-- Conteúdo do preview carregado via JS -->
                    <div class="text-center text-gray-400 py-8">
                        <i data-lucide="mail-check" class="w-12 h-12 mx-auto mb-2"></i>
                        <p>Carregando pré-visualização...</p>
                    </div>
                </div>
                <textarea id="email_template_delivery_html" name="email_template_delivery_html" rows="15" 
                          class="w-full p-4 bg-gray-900 text-green-300 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#32e768] hidden"
                          wrap="off"></textarea> <!-- Removido style="min-height: 200px;" -->
                <div id="html-editor-controls" class="p-4 bg-dark-elevated border-t border-dark-border flex justify-end space-x-2 hidden">
                    <button type="button" id="cancel-edit-html-btn" class="bg-dark-card text-gray-300 font-bold py-2 px-4 rounded-lg hover:bg-dark-elevated transition border border-dark-border">Cancelar</button>
                    <button type="button" id="save-html-content-btn" class="bg-[#32e768] text-white font-bold py-2 px-4 rounded-lg hover:bg-[#28d15e] transition">Salvar HTML</button>
                </div>
            </div>
        </div>

        <!-- NOVO: URL de Login da Área de Membros -->
        <div class="mb-6">
            <label for="member_area_login_url" class="block text-gray-300 text-sm font-semibold mb-2">URL de Login da Área de Membros</label>
            <input type="url" id="member_area_login_url" name="member_area_login_url" required
                   class="form-input-style" placeholder="Ex: https://seusite.com/member_login.php">
            <p class="text-xs text-gray-400 mt-1">Esta URL será incluída nos e-mails de acesso para produtos da área de membros.</p>
        </div>

        <div class="mt-8 pt-6 border-t border-dark-border flex flex-col sm:flex-row justify-end space-y-4 sm:space-y-0 sm:space-x-4">
            <button type="button" id="test-connection-btn" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition duration-300 flex items-center justify-center space-x-2">
                <i data-lucide="plug-zap" class="w-5 h-5"></i>
                <span>Testar Conexão</span>
            </button>
            <button type="button" id="send-test-email-btn" class="bg-purple-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-purple-700 transition duration-300 flex items-center justify-center space-x-2">
                <i data-lucide="send" class="w-5 h-5"></i>
                <span>Enviar E-mail de Teste</span>
            </button>
            <button type="submit" id="save-settings-btn" class="bg-[#32e768] text-white font-bold py-3 px-6 rounded-lg hover:bg-[#28d15e] transition duration-300 flex items-center justify-center space-x-2">
                <i data-lucide="save" class="w-5 h-5"></i>
                <span>Salvar Configurações</span>
            </button>
        </div>
    </form>

    <div id="response-message" class="mt-8 text-center py-4 rounded-lg hidden"></div>
</div>

<style>
    .form-input-style { 
        width: 100%;
        padding: 0.75rem 1rem;
        background-color: #0f1419;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.5rem;
        color: white;
    }
    .form-input-style:focus {
        outline: none;
        ring: 2px;
        ring-color: #32e768;
        border-color: #32e768;
    }
    .form-input-style::placeholder {
        color: #6b7280;
    }
    .form-input-style option {
        background-color: #0f1419;
        color: white;
    }
    input[type="date"].form-input-style,
    input[type="email"].form-input-style,
    input[type="text"].form-input-style,
    input[type="number"].form-input-style,
    input[type="password"].form-input-style,
    input[type="url"].form-input-style,
    select.form-input-style {
        color-scheme: dark;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    const form = document.getElementById('email-settings-form');
    const statusMessageDiv = document.getElementById('status-message');
    const testConnectionBtn = document.getElementById('test-connection-btn');
    const sendTestEmailBtn = document.getElementById('send-test-email-btn');
    const saveSettingsBtn = document.getElementById('save-settings-btn');

    // New elements for HTML template editor
    const emailTemplatePreview = document.getElementById('email-template-preview');
    const emailTemplateHtmlInput = document.getElementById('email_template_delivery_html');
    const editHtmlTemplateBtn = document.getElementById('edit-html-template-btn');
    const htmlEditorControls = document.getElementById('html-editor-controls');
    const saveHtmlContentBtn = document.getElementById('save-html-content-btn');
    const cancelEditHtmlBtn = document.getElementById('cancel-edit-html-btn');
    
    // Store original fetched HTML for cancel action
    let originalEmailHtmlTemplate = '';

    function showStatusMessage(message, type = 'info') {
        statusMessageDiv.classList.remove('hidden', 'bg-green-900/20', 'text-green-300', 'bg-red-900/20', 'text-red-300', 'bg-blue-900/20', 'text-blue-300', 'bg-yellow-900/20', 'text-yellow-300', 'border-green-500/30', 'border-red-500/30', 'border-blue-500/30', 'border-yellow-500/30');
        statusMessageDiv.innerHTML = message;
        if (type === 'success') {
            statusMessageDiv.classList.add('bg-green-900/20', 'text-green-300', 'border-green-500/30');
        } else if (type === 'error') {
            statusMessageDiv.classList.add('bg-red-900/20', 'text-red-300', 'border-red-500/30');
        } else if (type === 'warning') {
            statusMessageDiv.classList.add('bg-yellow-900/20', 'text-yellow-300', 'border-yellow-500/30');
        } else {
            statusMessageDiv.classList.add('bg-blue-900/20', 'text-blue-300', 'border-blue-500/30');
        }
        statusMessageDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        lucide.createIcons(); // Ensure icons within messages are rendered
    }

    async function fetchEmailSettings() {
        showStatusMessage('<i data-lucide="loader" class="animate-spin w-5 h-5 inline-block mr-2"></i> Carregando configurações...', 'info');
        try {
            const response = await fetch('/api/admin_api?action=get_email_settings');
            const result = await response.json();

            if (response.ok && result.success) {
                const data = result.data;
                document.getElementById('smtp_host').value = data.smtp_host || '';
                document.getElementById('smtp_port').value = data.smtp_port || '587';
                document.getElementById('smtp_username').value = data.smtp_username || '';
                document.getElementById('smtp_encryption').value = data.smtp_encryption || 'tls';
                document.getElementById('smtp_from_email').value = data.smtp_from_email || '';
                document.getElementById('smtp_from_name').value = data.smtp_from_name || 'GatewayPro';

                // Senha NUNCA volta da API. Limpa autofill do navegador.
                const passwordInput = document.getElementById('smtp_password');
                passwordInput.value = '';
                setTimeout(() => { passwordInput.value = ''; }, 100);

                const passwordStatus = document.getElementById('smtp-password-status');
                if (data.smtp_password_configured) {
                    passwordStatus.className = 'text-xs mt-1 text-green-400';
                    passwordStatus.innerHTML = '<i data-lucide="check-circle" class="w-3 h-3 inline-block mr-1"></i> Senha já salva no banco. Deixe em branco para mantê-la, ou digite uma nova senha de app para substituir.';
                } else {
                    passwordStatus.className = 'text-xs mt-1 text-amber-300';
                    passwordStatus.innerHTML = '<i data-lucide="alert-triangle" class="w-3 h-3 inline-block mr-1"></i> Nenhuma senha SMTP salva ainda. Informe a senha de app e clique em Salvar.';
                }
                
                // Populate new fields
                document.getElementById('email_template_delivery_subject').value = data.email_template_delivery_subject || '';
                emailTemplateHtmlInput.value = data.email_template_delivery_html || '';
                originalEmailHtmlTemplate = data.email_template_delivery_html || ''; // Store original for cancel
                emailTemplatePreview.innerHTML = data.email_template_delivery_html || 
                    '<div class="text-center text-gray-400 py-8"><i data-lucide="mail-check" class="w-12 h-12 mx-auto mb-2 text-gray-500"></i><p>Nenhum template carregado. Edite para definir um padrão.</p></div>';
                document.getElementById('member_area_login_url').value = data.member_area_login_url || '';

                showStatusMessage('<i data-lucide="check-circle" class="w-5 h-5 inline-block mr-2"></i> Configurações carregadas com sucesso!', 'success');
            } else {
                showStatusMessage(`<i data-lucide="x-circle" class="w-5 h-5 inline-block mr-2"></i> Erro ao carregar configurações: ${result.error || 'Erro desconhecido.'}`, 'error');
            }
        } catch (error) {
            console.error('Erro na requisição AJAX para carregar configurações:', error);
            showStatusMessage(`<i data-lucide="alert-triangle" class="w-5 h-5 inline-block mr-2"></i> Erro de rede ou servidor ao carregar configurações.`, 'error');
        } finally {
            lucide.createIcons();
        }
    }

    async function sendSmtpRequest(action, extraData = {}, showSuccessMessage = true) {
        showStatusMessage('<i data-lucide="loader" class="animate-spin w-5 h-5 inline-block mr-2"></i> Aguarde...', 'info');
        lucide.createIcons();
        
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            // Se o campo de senha estiver vazio, não o envia na requisição AJAX
            // para que o backend use a senha já salva.
            if (key === 'smtp_password' && value === '') {
                // Não adiciona 'smtp_password' ao objeto de dados
            } else {
                data[key] = value;
            }
        });
        
        // Assegura que o 'action' da função seja o que prevalece
        data.action = action;
        Object.assign(data, extraData);
        
        try {
            const response = await fetch(`/api/admin_api?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            const result = await response.json();

            if (response.ok && result.success) {
                if (showSuccessMessage) {
                    showStatusMessage(`<i data-lucide="check-circle" class="w-5 h-5 inline-block mr-2"></i> ${result.message}`, 'success');
                }
                return { success: true, data: result.data }; // Return data if action returns it
            } else {
                showStatusMessage(`<i data-lucide="x-circle" class="w-5 h-5 inline-block mr-2"></i> ${result.error || 'Erro desconhecido.'}`, 'error');
                return { success: false, error: result.error };
            }
        } catch (error) {
            console.error('Erro na requisição AJAX:', error);
            let errorMessage = "Erro de rede ou servidor.";
            if (error instanceof TypeError && error.message.includes("Failed to fetch")) {
                errorMessage = `Erro de rede: Não foi possível conectar ao servidor. Verifique sua conexão ou a URL do backend.`;
            } else if (error instanceof SyntaxError && error.message.includes("JSON")) {
                errorMessage = `Erro no servidor: Resposta inválida. O servidor pode ter encontrado um erro PHP antes de retornar JSON. Verifique os logs de erro do PHP.`;
            }
            showStatusMessage(`<i data-lucide="alert-triangle" class="w-5 h-5 inline-block mr-2"></i> ${errorMessage}`, 'error');
            return { success: false, error: errorMessage };
        } finally {
            lucide.createIcons();
        }
    }

    testConnectionBtn.addEventListener('click', () => {
        sendSmtpRequest('test_smtp_connection');
    });

    sendTestEmailBtn.addEventListener('click', () => {
        const testEmail = prompt("Para qual e-mail você gostaria de enviar o e-mail de teste?", document.getElementById('smtp_username').value);
        if (testEmail) {
            sendSmtpRequest('send_test_email', { test_email: testEmail });
        }
    });

    saveSettingsBtn.addEventListener('click', async (e) => {
        e.preventDefault(); // Prevent default form submission
        const result = await sendSmtpRequest('save_email_settings');
        if(result.success) {
            document.getElementById('smtp_password').value = '';
            // Re-fetch to update original values in case user cancels HTML editing later
            await fetchEmailSettings(); 
        }
    });

    // Evita que o autofill do navegador “finja” que a senha já está na tela
    window.addEventListener('load', () => {
        setTimeout(() => {
            const passwordInput = document.getElementById('smtp_password');
            if (passwordInput) passwordInput.value = '';
        }, 300);
    });

    // --- HTML Template Editor Logic ---
    editHtmlTemplateBtn.addEventListener('click', (e) => {
        e.preventDefault();
        emailTemplatePreview.classList.add('hidden');
        emailTemplateHtmlInput.classList.remove('hidden');
        htmlEditorControls.classList.remove('hidden');
        editHtmlTemplateBtn.classList.add('hidden');
        emailTemplateHtmlInput.focus();
    });

    saveHtmlContentBtn.addEventListener('click', (e) => {
        e.preventDefault();
        emailTemplatePreview.innerHTML = emailTemplateHtmlInput.value;
        originalEmailHtmlTemplate = emailTemplateHtmlInput.value; // Update original as well
        emailTemplatePreview.classList.remove('hidden');
        emailTemplateHtmlInput.classList.add('hidden');
        htmlEditorControls.classList.add('hidden');
        editHtmlTemplateBtn.classList.remove('hidden');
        lucide.createIcons(); // Render any icons in the HTML preview
    });

    cancelEditHtmlBtn.addEventListener('click', (e) => {
        e.preventDefault();
        emailTemplateHtmlInput.value = originalEmailHtmlTemplate; // Revert to original
        emailTemplatePreview.innerHTML = originalEmailHtmlTemplate; // Revert preview as well
        emailTemplatePreview.classList.remove('hidden');
        emailTemplateHtmlInput.classList.add('hidden');
        htmlEditorControls.classList.add('hidden');
        editHtmlTemplateBtn.classList.remove('hidden');
        lucide.createIcons(); // Render any icons in the HTML preview
    });


    // Initial fetch of settings when page loads
    fetchEmailSettings();
});
</script>
