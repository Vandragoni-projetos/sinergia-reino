<?php
// É crucial que não haja NENHUM espaço ou linha antes desta tag de abertura do PHP.

// 1. Inicia o mecanismo de sessão do PHP.
session_start();
 
// 2. Apaga todas as variáveis da sessão (limpa os dados como 'loggedin', 'id', etc.).
$_SESSION = array();
 
// 3. Destrói a sessão no servidor.
session_destroy();
 
// 4. Redireciona o usuário de volta para a tela de login.
header("location: /login");
exit;
?>

