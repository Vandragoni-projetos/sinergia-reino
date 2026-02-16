<?php
// Este arquivo é incluído a partir do index.php
$usuario_id_logado = $_SESSION['id'] ?? 0;

if ($usuario_id_logado === 0) {
    header("location: /login");
    exit;
}

// URL base da API
$domainName = $_SERVER['HTTP_HOST'];
$apiUrl = "https://" . $domainName . "/api.php";

// Tenta buscar o token secreto se existir no ambiente ou no banco
$token_secreto = getenv('TOKEN_AUTH_SECRET') ?: 'SUA_CHAVE_SECRETA';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Documentação da API</title>
    <style>
        .endpoint-card {
            background: rgba(15, 20, 25, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        .endpoint-card:hover {
            border-color: rgba(50, 231, 104, 0.3);
            background: rgba(15, 20, 25, 0.6);
        }
        .code-block {
            background: #0d1117;
            border-radius: 8px;
            font-family: 'Fira Code', 'Courier New', Courier, monospace;
        }
        .method-get { color: #61afff; }
        .method-post { color: #49cc90; }
        .method-put { color: #fca130; }
        .method-delete { color: #f93e3e; }
    </style>
</head>
<body class="text-gray-200">

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Breadcrumb & Header -->
        <div class="mb-8">
            <nav class="flex text-sm text-gray-500 mb-4" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/index?pagina=integracoes" class="hover:text-[#32e768] transition-colors">Integrações</a></li>
                    <li><i data-lucide="chevron-right" class="w-4 h-4"></i></li>
                    <li class="text-gray-300 font-medium">Documentação API</li>
                </ol>
            </nav>
            <h1 class="text-3xl md:text-4xl font-extrabold text-white mb-2">Documentação da API</h1>
            <p class="text-gray-400">Utilize nossos endpoints para automatizar integrações e gerenciar seus dados de forma programática.</p>
        </div>

        <!-- Authentication Card -->
        <div class="endpoint-card rounded-2xl p-6 mb-10 border-l-4 border-l-[#32e768]">
            <div class="flex items-start gap-4">
                <div class="bg-[#32e768]/10 p-3 rounded-xl">
                    <i data-lucide="shield-check" class="w-6 h-6 text-[#32e768]"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-xl font-bold text-white mb-2">Autenticação (JWT)</h3>
                    <p class="text-gray-400 text-sm mb-4">
                        Todas as requisições devem incluir o cabeçalho <code>Authorization: Bearer &lt;token&gt;</code>. 
                        O token deve ser assinado usando o algoritmo <strong>HS256</strong> com sua chave secreta.
                    </p>
                    <div class="code-block p-4 relative group">
                        <code class="text-xs text-green-400 break-all">
                            Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
                        </code>
                        <button onclick="copyToClipboard('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9')" class="absolute top-2 right-2 p-2 bg-dark-elevated rounded-md opacity-0 group-hover:opacity-100 transition-opacity">
                            <i data-lucide="copy" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Endpoints Grid -->
        <div class="space-y-6">
            <h2 class="text-2xl font-bold text-white mb-6 flex items-center gap-2">
                <i data-lucide="database" class="w-6 h-6 text-[#32e768]"></i>
                Tabelas e Recursos
            </h2>

            <?php
            $resources = [
                ['name' => 'configuracoes', 'desc' => 'Configurações gerais do painel e gateways.', 'icon' => 'settings'],
                ['name' => 'configuracoes_sistema', 'desc' => 'Parâmetros globais do sistema e temas.', 'icon' => 'sliders'],
                ['name' => 'produtos', 'desc' => 'Catálogo de produtos, preços e descrições.', 'icon' => 'package'],
                ['name' => 'produto_ofertas', 'desc' => 'Ofertas específicas e variações de preços.', 'icon' => 'tag'],
                ['name' => 'usuarios', 'desc' => 'Gerenciamento de usuários e permissões.', 'icon' => 'users'],
                ['name' => 'vendas', 'desc' => 'Registros de transações, status e faturas.', 'icon' => 'shopping-bag'],
            ];

            foreach ($resources as $res):
            ?>
            <div class="endpoint-card rounded-xl overflow-hidden border border-dark-border">
                <button onclick="toggleResource('<?php echo $res['name']; ?>')" class="w-full px-6 py-5 flex items-center justify-between hover:bg-white/5 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="p-2 bg-dark-elevated rounded-lg">
                            <i data-lucide="<?php echo $res['icon']; ?>" class="w-5 h-5 text-[#32e768]"></i>
                        </div>
                        <div class="text-left">
                            <span class="text-lg font-bold text-white"><?php echo ucfirst(str_replace('_', ' ', $res['name'])); ?></span>
                            <p class="text-xs text-gray-500 uppercase tracking-widest mt-0.5">Resource: /records/<?php echo $res['name']; ?></p>
                        </div>
                    </div>
                    <i data-lucide="chevron-down" id="icon-<?php echo $res['name']; ?>" class="w-5 h-5 text-gray-500 transition-transform"></i>
                </button>

                <div id="content-<?php echo $res['name']; ?>" class="hidden px-6 pb-6 pt-2 border-t border-white/5 animate-fade-in">
                    <p class="text-gray-400 text-sm mb-6"><?php echo $res['desc']; ?></p>
                    
                    <div class="space-y-4">
                        <!-- List -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#61afff]/10 text-[#61afff] border border-[#61afff]/20">GET</span>
                                    <span class="text-sm font-mono text-gray-300">/records/<?php echo $res['name']; ?></span>
                                </div>
                                <span class="text-xs text-gray-500">Listar registros</span>
                            </div>
                            <div class="code-block p-3 relative group">
                                <pre class="text-[11px] text-gray-400 overflow-x-auto">curl -X GET "<?php echo $apiUrl; ?>/records/<?php echo $res['name']; ?>" \
  -H "Authorization: Bearer YOUR_TOKEN"</pre>
                                <button onclick="copyRaw(this)" class="absolute top-2 right-2 p-1.5 bg-dark-elevated rounded-md opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Read Single -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#61afff]/10 text-[#61afff] border border-[#61afff]/20">GET</span>
                                    <span class="text-sm font-mono text-gray-300">/records/<?php echo $res['name']; ?>/{id}</span>
                                </div>
                                <span class="text-xs text-gray-500">Obter por ID</span>
                            </div>
                        </div>

                        <!-- Create -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#49cc90]/10 text-[#49cc90] border border-[#49cc90]/20">POST</span>
                                    <span class="text-sm font-mono text-gray-300">/records/<?php echo $res['name']; ?></span>
                                </div>
                                <span class="text-xs text-gray-500">Criar novo</span>
                            </div>
                            <div class="code-block p-3 relative group">
                                <pre class="text-[11px] text-gray-400 overflow-x-auto">curl -X POST "<?php echo $apiUrl; ?>/records/<?php echo $res['name']; ?>" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"campo": "valor"}'</pre>
                                <button onclick="copyRaw(this)" class="absolute top-2 right-2 p-1.5 bg-dark-elevated rounded-md opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Update -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#fca130]/10 text-[#fca130] border border-[#fca130]/20">PUT</span>
                                    <span class="text-sm font-mono text-gray-300">/records/<?php echo $res['name']; ?>/{id}</span>
                                </div>
                                <span class="text-xs text-gray-500">Atualizar registro</span>
                            </div>
                            <div class="code-block p-3 relative group">
                                <pre class="text-[11px] text-gray-400 overflow-x-auto">curl -X PUT "<?php echo $apiUrl; ?>/records/<?php echo $res['name']; ?>/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"campo": "novo_valor"}'</pre>
                                <button onclick="copyRaw(this)" class="absolute top-2 right-2 p-1.5 bg-dark-elevated rounded-md opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Delete -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#f93e3e]/10 text-[#f93e3e] border border-[#f93e3e]/20">DELETE</span>
                                    <span class="text-sm font-mono text-gray-300">/records/<?php echo $res['name']; ?>/{id}</span>
                                </div>
                                <span class="text-xs text-gray-500">Excluir registro</span>
                            </div>
                            <div class="code-block p-3 relative group">
                                <pre class="text-[11px] text-gray-400 overflow-x-auto">curl -X DELETE "<?php echo $apiUrl; ?>/records/<?php echo $res['name']; ?>/1" \
  -H "Authorization: Bearer YOUR_TOKEN"</pre>
                                <button onclick="copyRaw(this)" class="absolute top-2 right-2 p-1.5 bg-dark-elevated rounded-md opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function toggleResource(name) {
            const content = document.getElementById('content-' + name);
            const icon = document.getElementById('icon-' + name);
            content.classList.toggle('hidden');
            icon.style.transform = content.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
            lucide.createIcons();
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            // Simular feedback visual pode ser feito aqui
        }

        function copyRaw(btn) {
            const pre = btn.parentElement.querySelector('pre');
            navigator.clipboard.writeText(pre.innerText);
            
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5 text-[#32e768]"></i>';
            lucide.createIcons();
            
            setTimeout(() => {
                btn.innerHTML = originalIcon;
                lucide.createIcons();
            }, 2000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>
</body>
</html>
