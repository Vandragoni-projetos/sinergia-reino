<?php
// ------ IMPORTANTE ------
// A senha que estamos usando
$senhaPlana = 'Leonardo195423@';
// --------------------

// Gera o hash da senha usando o método padrão e seguro do PHP
$hash = password_hash($senhaPlana, PASSWORD_DEFAULT);

// Exibe o resultado de forma clara na tela
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="pt-BR"><head><title>Gerador de Hash de Senha</title>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<style>body { font-family: sans-serif; padding: 20px; } .container { max-width: 800px; margin: auto; border: 1px solid #ccc; padding: 20px; border-radius: 8px; } textarea { width: 100%; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 1.1em; } h1, p { margin: 0 0 15px 0; }</style>';
echo '</head><body><div class="container">';
echo '<h1>Gerador de Hash de Senha</h1>';
echo "<p><strong>Sua senha:</strong> " . htmlspecialchars($senhaPlana) . "</p>";
echo "<p><strong>Seu hash seguro (copie todo o texto abaixo):</strong></p>";
echo '<textarea rows="4" readonly onclick="this.select()">' . htmlspecialchars($hash) . '</textarea>';
echo '</div></body></html>';
?>

