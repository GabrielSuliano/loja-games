<?php
session_start();

// Configurações do banco
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'loja_games');

// Conexão com MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexão
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8mb4");

// Configuração do tema
if (!isset($_SESSION['dark_mode'])) {
    $_SESSION['dark_mode'] = false;
}

// Função para verificar autenticação
function verificarAuth() {
    if (!isset($_SESSION['funcionario_id'])) {
        header('Location: index.php');
        exit;
    }
}

// ✅ VERIFICAR SE É ADMIN (NÍVEL 2) - CORRETO
function isAdmin() {
    return isset($_SESSION['funcionario_nivel']) && $_SESSION['funcionario_nivel'] == 2;
}

// ✅ VERIFICAR ADMIN (REDIRECIONA SE NÃO FOR)
function verificarAdmin() {
    if (!isAdmin()) {
        $_SESSION['erro'] = "Acesso negado. Apenas administradores podem acessar esta página.";
        header('Location: dashboard.php');
        exit();
    }
}
?>