<?php
// Verificar autenticação do funcionário
function verificarAuth() {
    if (!isset($_SESSION['funcionario_id'])) {
        header('Location: ../index.php');
        exit;
    }
}

// Verificar se é admin
function verificarAdmin() {
    if (!isset($_SESSION['funcionario_id']) || $_SESSION['funcionario_nivel'] !== 'admin') {
        $_SESSION['erro'] = "Acesso negado! Você precisa ser administrador.";
        header('Location: ../dashboard.php');
        exit;
    }
}

// Redirecionar se já estiver logado
function redirecionarSeLogado() {
    if (isset($_SESSION['funcionario_id'])) {
        header('Location: dashboard.php');
        exit;
    }
}

// Gerar hash de senha (usar para criar novos usuários)
function gerarHashSenha($senha) {
    return password_hash($senha, PASSWORD_DEFAULT);
}

// Verificar força da senha
function verificarForcaSenha($senha) {
    $forca = 0;
    
    // Comprimento mínimo
    if (strlen($senha) >= 8) $forca += 1;
    
    // Contém números
    if (preg_match('/[0-9]/', $senha)) $forca += 1;
    
    // Contém letras minúsculas e maiúsculas
    if (preg_match('/[a-z]/', $senha) && preg_match('/[A-Z]/', $senha)) $forca += 1;
    
    // Contém caracteres especiais
    if (preg_match('/[^a-zA-Z0-9]/', $senha)) $forca += 1;
    
    return $forca;
}
?>