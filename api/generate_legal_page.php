<?php
/**
 * API para gerar páginas HTML de Política de Privacidade e Termos de Serviço
 * Gera páginas completas com informações do sistema
 */

require_once __DIR__ . '/../config/config.php';

// Verificar autenticação
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json');

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Validar tipo de página
$page_type = $_POST['type'] ?? '';
if (!in_array($page_type, ['privacy', 'terms'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo de página inválido']);
    exit;
}

try {
    // Buscar configurações do sistema
    $logo_url = getSystemSetting('logo_url', 'https://midias.vitrineacademy.com.br/wp-content/uploads/2026/03/Logomarca-Hub-Sinergia-1000x412-1.png');
    $nome_plataforma = getSystemSetting('nome_plataforma', 'Hub SinergIA');
    $favicon_url = getSystemSetting('favicon_url', '');
    
    // Normalizar URLs
    if (!empty($logo_url) && strpos($logo_url, 'http') !== 0) {
        $logo_url = '/' . ltrim($logo_url, '/');
    }
    if (!empty($favicon_url) && strpos($favicon_url, 'http') !== 0) {
        $favicon_url = '/' . ltrim($favicon_url, '/');
    }
    
    // Diretório de destino: preferir /legal, fallback para /uploads/legal (geralmente gravável no servidor)
    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        $root = dirname(__DIR__);
    }
    $legal_dir = $root . DIRECTORY_SEPARATOR . 'legal';
    $url_prefix = '/legal/';

    if (!file_exists($legal_dir)) {
        if (!@mkdir($legal_dir, 0755, true)) {
            $legal_dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'legal';
            $url_prefix = '/uploads/legal/';
            if (!file_exists($legal_dir) && !@mkdir($legal_dir, 0755, true)) {
                throw new Exception('Não foi possível criar a pasta para as páginas legais. Verifique permissões da pasta legal/ ou uploads/.');
            }
        }
    }
    if (file_exists($legal_dir) && !is_writable($legal_dir)) {
        $legal_dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'legal';
        $url_prefix = '/uploads/legal/';
        if (!file_exists($legal_dir)) {
            @mkdir($legal_dir, 0755, true);
        }
        if (!is_writable($legal_dir)) {
            throw new Exception('A pasta de páginas legais não tem permissão de escrita. Ajuste as permissões da pasta legal/ ou uploads/ no servidor.');
        }
    }

    // Gerar conteúdo HTML baseado no tipo
    if ($page_type === 'privacy') {
        $title = 'Política de Privacidade';
        $filename = 'privacidade.html';
        $content = generate_privacy_content($nome_plataforma);
    } else {
        $title = 'Termos de Serviço';
        $filename = 'termos.html';
        $content = generate_terms_content($nome_plataforma);
    }

    // Gerar HTML completo
    $html = generate_html_template($title, $content, $logo_url, $nome_plataforma, $favicon_url);

    // Salvar arquivo
    $file_path = $legal_dir . DIRECTORY_SEPARATOR . $filename;
    $written = @file_put_contents($file_path, $html);
    if ($written === false) {
        $err = error_get_last();
        $hint = ($err && isset($err['message'])) ? ': ' . $err['message'] : '';
        throw new Exception('Não foi possível gravar o arquivo em ' . basename($legal_dir) . '/' . $filename . $hint);
    }
    @chmod($file_path, 0644);

    // Retornar URL do arquivo
    $file_url = $url_prefix . $filename;
    
    echo json_encode([
        'success' => true,
        'url' => $file_url,
        'message' => 'Página gerada com sucesso!'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Gera template HTML completo
 */
function generate_html_template($title, $content, $logo_url, $nome_plataforma, $favicon_url) {
    $favicon_tag = '';
    if (!empty($favicon_url)) {
        $favicon_ext = strtolower(pathinfo($favicon_url, PATHINFO_EXTENSION));
        $favicon_type = $favicon_ext === 'png' ? 'image/png' : ($favicon_ext === 'svg' ? 'image/svg+xml' : 'image/x-icon');
        $favicon_tag = '<link rel="icon" type="' . htmlspecialchars($favicon_type) . '" href="' . htmlspecialchars($favicon_url) . '">';
    }
    
    $current_date = date('d/m/Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - {$nome_plataforma}</title>
    {$favicon_tag}
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .content-section {
            margin-bottom: 2rem;
        }
        .content-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        .content-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.75rem;
            margin-top: 1.5rem;
        }
        .content-section p {
            margin-bottom: 1rem;
            line-height: 1.75;
            color: #4b5563;
        }
        .content-section ul, .content-section ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .content-section li {
            margin-bottom: 0.5rem;
            color: #4b5563;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 py-6">
                <div class="flex items-center justify-center">
                    <img src="{$logo_url}" alt="{$nome_plataforma}" class="h-12 object-contain">
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="max-w-4xl mx-auto px-4 py-12">
            <div class="bg-white rounded-lg shadow-sm p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{$title}</h1>
                <p class="text-sm text-gray-500 mb-8">Última atualização: {$current_date}</p>
                
                <div class="content-section">
                    {$content}
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-12">
            <div class="max-w-4xl mx-auto px-4 py-6 text-center">
                <p class="text-sm text-gray-500">© {$nome_plataforma} - Todos os direitos reservados</p>
            </div>
        </footer>
    </div>
</body>
</html>
HTML;
}

/**
 * Gera conteúdo da Política de Privacidade
 */
function generate_privacy_content($nome_plataforma) {
    return <<<CONTENT
<h2>1. Introdução</h2>
<p>A {$nome_plataforma} está comprometida em proteger a privacidade e os dados pessoais de seus usuários. Esta Política de Privacidade descreve como coletamos, usamos, armazenamos e protegemos suas informações pessoais.</p>

<h2>2. Informações que Coletamos</h2>
<p>Coletamos as seguintes informações quando você utiliza nossa plataforma:</p>
<ul>
    <li><strong>Informações de Cadastro:</strong> Nome, e-mail, CPF, telefone e endereço</li>
    <li><strong>Informações de Pagamento:</strong> Dados de transação processados por nossos parceiros de pagamento</li>
    <li><strong>Informações de Uso:</strong> Dados sobre como você interage com nossa plataforma</li>
    <li><strong>Informações Técnicas:</strong> Endereço IP, tipo de navegador, sistema operacional</li>
</ul>

<h2>3. Como Usamos suas Informações</h2>
<p>Utilizamos suas informações pessoais para:</p>
<ul>
    <li>Processar suas compras e fornecer acesso aos produtos adquiridos</li>
    <li>Enviar comunicações sobre seus pedidos e atualizações de produtos</li>
    <li>Melhorar nossos serviços e experiência do usuário</li>
    <li>Prevenir fraudes e garantir a segurança da plataforma</li>
    <li>Cumprir obrigações legais e regulatórias</li>
</ul>

<h2>4. Compartilhamento de Informações</h2>
<p>Não vendemos suas informações pessoais. Podemos compartilhar seus dados apenas com:</p>
<ul>
    <li><strong>Processadores de Pagamento:</strong> Para processar transações financeiras</li>
    <li><strong>Provedores de Serviços:</strong> Que nos auxiliam na operação da plataforma</li>
    <li><strong>Autoridades Legais:</strong> Quando exigido por lei ou para proteger nossos direitos</li>
</ul>

<h2>5. Segurança dos Dados</h2>
<p>Implementamos medidas de segurança técnicas e organizacionais para proteger suas informações pessoais contra acesso não autorizado, alteração, divulgação ou destruição. Isso inclui:</p>
<ul>
    <li>Criptografia de dados sensíveis</li>
    <li>Controles de acesso rigorosos</li>
    <li>Monitoramento contínuo de segurança</li>
    <li>Backups regulares de dados</li>
</ul>

<h2>6. Seus Direitos (LGPD)</h2>
<p>De acordo com a Lei Geral de Proteção de Dados (LGPD), você tem os seguintes direitos:</p>
<ul>
    <li>Confirmar a existência de tratamento de dados</li>
    <li>Acessar seus dados pessoais</li>
    <li>Corrigir dados incompletos, inexatos ou desatualizados</li>
    <li>Solicitar a anonimização, bloqueio ou eliminação de dados</li>
    <li>Solicitar a portabilidade de dados</li>
    <li>Revogar o consentimento</li>
</ul>

<h2>7. Cookies e Tecnologias Similares</h2>
<p>Utilizamos cookies e tecnologias similares para melhorar sua experiência, analisar o uso da plataforma e personalizar conteúdo. Você pode gerenciar suas preferências de cookies nas configurações do seu navegador.</p>

<h2>8. Retenção de Dados</h2>
<p>Mantemos suas informações pessoais pelo tempo necessário para cumprir as finalidades descritas nesta política, a menos que um período de retenção mais longo seja exigido ou permitido por lei.</p>

<h2>9. Alterações nesta Política</h2>
<p>Podemos atualizar esta Política de Privacidade periodicamente. Notificaremos você sobre mudanças significativas através de e-mail ou aviso em nossa plataforma.</p>

<h2>10. Contato</h2>
<p>Se você tiver dúvidas sobre esta Política de Privacidade ou quiser exercer seus direitos, entre em contato conosco através dos canais de suporte disponíveis na plataforma.</p>
CONTENT;
}

/**
 * Gera conteúdo dos Termos de Serviço
 */
function generate_terms_content($nome_plataforma) {
    return <<<CONTENT
<h2>1. Aceitação dos Termos</h2>
<p>Ao acessar e usar a plataforma {$nome_plataforma}, você concorda em cumprir e estar vinculado a estes Termos de Serviço. Se você não concordar com qualquer parte destes termos, não deverá usar nossa plataforma.</p>

<h2>2. Descrição do Serviço</h2>
<p>A {$nome_plataforma} é uma plataforma de vendas de produtos digitais e área de membros que permite:</p>
<ul>
    <li>Criação e venda de produtos digitais</li>
    <li>Hospedagem de cursos online e conteúdo educacional</li>
    <li>Processamento de pagamentos através de parceiros integrados</li>
    <li>Gerenciamento de área de membros e acesso a conteúdo</li>
</ul>

<h2>3. Cadastro e Conta de Usuário</h2>
<p>Para utilizar nossos serviços, você deve:</p>
<ul>
    <li>Fornecer informações precisas e completas durante o cadastro</li>
    <li>Manter a confidencialidade de suas credenciais de acesso</li>
    <li>Notificar-nos imediatamente sobre qualquer uso não autorizado de sua conta</li>
    <li>Ser responsável por todas as atividades realizadas em sua conta</li>
</ul>

<h2>4. Uso Aceitável</h2>
<p>Você concorda em não:</p>
<ul>
    <li>Usar a plataforma para fins ilegais ou não autorizados</li>
    <li>Violar direitos de propriedade intelectual de terceiros</li>
    <li>Transmitir vírus, malware ou código malicioso</li>
    <li>Tentar obter acesso não autorizado a sistemas ou dados</li>
    <li>Interferir no funcionamento adequado da plataforma</li>
    <li>Usar a plataforma para spam ou comunicações não solicitadas</li>
</ul>

<h2>5. Produtos e Pagamentos</h2>
<h3>5.1 Compra de Produtos</h3>
<p>Ao comprar um produto através da {$nome_plataforma}, você concorda que:</p>
<ul>
    <li>As informações de pagamento fornecidas são verdadeiras e precisas</li>
    <li>Você está autorizado a usar o método de pagamento selecionado</li>
    <li>Os preços estão sujeitos a alterações sem aviso prévio</li>
</ul>

<h3>5.2 Reembolsos e Cancelamentos</h3>
<p>A política de reembolso é definida individualmente por cada vendedor/infoprodutor. Consulte a página do produto para informações específicas sobre reembolsos.</p>

<h2>6. Propriedade Intelectual</h2>
<p>Todo o conteúdo disponível na plataforma, incluindo textos, gráficos, logos, ícones, imagens, clipes de áudio e software, é propriedade da {$nome_plataforma} ou de seus licenciadores e está protegido por leis de direitos autorais.</p>

<h2>7. Conteúdo do Usuário</h2>
<p>Ao enviar conteúdo para a plataforma, você:</p>
<ul>
    <li>Garante que possui todos os direitos necessários sobre o conteúdo</li>
    <li>Concede à {$nome_plataforma} uma licença para usar, reproduzir e distribuir o conteúdo</li>
    <li>É responsável por todo o conteúdo que publicar</li>
</ul>

<h2>8. Limitação de Responsabilidade</h2>
<p>A {$nome_plataforma} não será responsável por:</p>
<ul>
    <li>Danos indiretos, incidentais ou consequenciais</li>
    <li>Perda de lucros, dados ou oportunidades de negócio</li>
    <li>Interrupções ou erros no serviço</li>
    <li>Conteúdo de terceiros ou produtos vendidos por infoprodutores</li>
</ul>

<h2>9. Garantias</h2>
<p>A plataforma é fornecida "como está" e "conforme disponível". Não garantimos que o serviço será ininterrupto, seguro ou livre de erros.</p>

<h2>10. Modificações do Serviço</h2>
<p>Reservamo-nos o direito de modificar ou descontinuar, temporária ou permanentemente, o serviço (ou qualquer parte dele) com ou sem aviso prévio.</p>

<h2>11. Rescisão</h2>
<p>Podemos suspender ou encerrar sua conta e acesso à plataforma imediatamente, sem aviso prévio, por violação destes Termos de Serviço ou por qualquer outro motivo.</p>

<h2>12. Lei Aplicável</h2>
<p>Estes Termos de Serviço são regidos pelas leis da República Federativa do Brasil. Qualquer disputa será resolvida nos tribunais brasileiros.</p>

<h2>13. Alterações nos Termos</h2>
<p>Podemos revisar estes Termos de Serviço a qualquer momento. Alterações significativas serão notificadas através de e-mail ou aviso na plataforma.</p>

<h2>14. Contato</h2>
<p>Para questões sobre estes Termos de Serviço, entre em contato através dos canais de suporte disponíveis na plataforma.</p>

<h2>15. Disposições Gerais</h2>
<p>Se qualquer disposição destes termos for considerada inválida ou inexequível, as demais disposições permanecerão em pleno vigor e efeito.</p>
CONTENT;
}
