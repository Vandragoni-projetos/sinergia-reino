<?php
// Este arquivo é incluído a partir do index.php,
// então a verificação de login e a conexão com o banco ($pdo) já existem.

// Obter o ID do usuário logado
$usuario_id_logado = $_SESSION['id'] ?? 0;

// Se por algum motivo o ID do usuário não estiver definido, redireciona para o login
if ($usuario_id_logado === 0) {
    header("location: /login");
    exit;
}

// Busca TODOS os produtos do usuário (link, pdf, area_membros)
// Sem filtro de community_id para incluir todos os produtos (igual ao merge "resto" em Meus Produtos)
// Ofertas só aparecem para produtos tipo area_membros
$cursos = [];
$order_sql = 'ORDER BY id DESC';
try {
    $chk_ordem = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'ordem'");
    if ($chk_ordem && $chk_ordem->rowCount() > 0) {
        $order_sql = 'ORDER BY ordem ASC, id DESC';
    }
} catch (PDOException $e) {
    // Tabela pode não existir ou coluna ordem ausente; usa ordem por id
}
try {
    $stmt = $pdo->prepare("
        SELECT id, nome, foto, tipo_entrega 
        FROM produtos 
        WHERE usuario_id = ? 
        " . $order_sql . "
    ");
    $stmt->execute([$usuario_id_logado]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('area_membros: ' . $e->getMessage());
    echo "<div class='bg-red-900/20 border border-red-500 text-red-300 px-4 py-3 rounded relative mb-4' role='alert'>Erro ao buscar produtos: " . htmlspecialchars($e->getMessage()) . "</div>";
}

$upload_dir = 'uploads/'; // Pasta onde as imagens estão salvas
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Área de Membros</h1>
    </div>

    <!-- Listagem de Produtos (Cursos + Link + PDF) -->
    <div class="bg-dark-card p-8 rounded-lg shadow-md border border-[#32e768]">
        <h2 class="text-2xl font-semibold mb-6 text-white">Meus Produtos</h2>
        
        <?php if (empty($cursos)): ?>
            <div class="text-center py-12 text-gray-400">
                <i data-lucide="package" class="mx-auto w-16 h-16 text-gray-500"></i>
                <p class="mt-4">Você ainda não tem produtos cadastrados.</p>
                <p>Vá para a <a href="/index?pagina=produtos" class="text-[#32e768] hover:underline font-semibold">página de produtos</a> para criar um.</p>
            </div>
        <?php else: ?>
            <p class="text-gray-400 text-sm mb-4">A ordem é definida em <a href="/index?pagina=produtos" class="text-[#32e768] hover:underline">Meus Produtos</a>. Ofertas disponíveis apenas para cursos (Área de Membros).</p>
            <div id="lista-cursos-area-membros" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
                <?php foreach ($cursos as $curso): ?>
                    <div class="group bg-dark-elevated rounded-lg overflow-hidden border border-dark-border hover:shadow-xl transition-shadow duration-300 flex flex-col" data-id="<?php echo (int)$curso['id']; ?>">
                        <div class="relative h-64 bg-dark-card">
                             <?php if ($curso['foto']): ?>
                                <img src="<?php echo $upload_dir . htmlspecialchars($curso['foto']); ?>" alt="<?php echo htmlspecialchars($curso['nome']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i data-lucide="image-off" class="text-gray-500 w-16 h-16"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4 flex-grow flex flex-col justify-between">
                            <div>
                                <h3 class="font-bold text-lg text-white mb-2 truncate" title="<?php echo htmlspecialchars($curso['nome']); ?>">
                                    <?php echo htmlspecialchars($curso['nome']); ?>
                                </h3>
                                <?php 
                                $tipo_entrega = !empty($curso['tipo_entrega']) ? $curso['tipo_entrega'] : 'link'; 
                                $tipo_badges = ['link' => 'Link', 'email_pdf' => 'PDF', 'area_membros' => 'Curso']; 
                                ?>
                                <span class="inline-block text-xs px-2 py-0.5 rounded bg-dark-card text-gray-400 border border-dark-border"><?php echo htmlspecialchars($tipo_badges[$tipo_entrega] ?? $tipo_entrega); ?></span>
                            </div>
                            <div class="mt-2 flex flex-col gap-2">
                                <?php if ($tipo_entrega === 'area_membros'): ?>
                                <a href="/curso_preview?produto_id=<?php echo $curso['id']; ?>" target="_blank" class="flex-1 text-center bg-dark-card text-gray-300 font-bold py-2 px-3 rounded-lg hover:bg-dark-elevated hover:text-white transition duration-300 flex items-center justify-center space-x-2 text-sm border border-dark-border">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                    <span>Pré-visualizar</span>
                                </a>
                                <a href="/index?pagina=gerenciar_curso&produto_id=<?php echo $curso['id']; ?>" class="flex-1 text-center bg-[#32e768] text-white font-bold py-2 px-3 rounded-lg hover:bg-[#28d15e] transition duration-300 flex items-center justify-center space-x-2 text-sm">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                    <span>Gerenciar</span>
                                </a>
                                <a href="/index?pagina=infoprodutor_member_offers&source_product_id=<?php echo $curso['id']; ?>" class="flex-1 text-center bg-purple-500 text-white font-bold py-2 px-3 rounded-lg hover:bg-purple-600 transition duration-300 flex items-center justify-center space-x-2 text-sm">
                                    <i data-lucide="tag" class="w-4 h-4"></i>
                                    <span>Ofertas</span>
                                </a>
                                <?php else: ?>
                                <a href="/index?pagina=produto_config&id=<?php echo $curso['id']; ?>" class="flex-1 text-center bg-dark-card text-gray-300 font-bold py-2 px-3 rounded-lg hover:bg-dark-elevated hover:text-white transition duration-300 flex items-center justify-center space-x-2 text-sm border border-dark-border">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                    <span>Configurar</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($cursos)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>
<?php endif; ?>
