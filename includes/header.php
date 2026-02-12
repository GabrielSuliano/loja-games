<?php
if (!isset($skip_auth)) {
    verificarAuth();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'GameStore Manager'; ?></title>
    <link rel="stylesheet" href="css/style.css">
    
    <style>
    /* BOTÃO TEMA LIMPO */
    .theme-btn {
        background: transparent;
        border: none;
        color: var(--text-primary);
        padding: 0.5rem;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1.4rem;
    }

    .theme-btn:hover {
        background: var(--bg-secondary);
        transform: scale(1.1);
    }

    .theme-icon.sun {
        display: none;
    }

    .theme-icon.moon {
        display: block;
    }

    .dark-mode .theme-icon.sun {
        display: block;
    }

    .dark-mode .theme-icon.moon {
        display: none;
    }
    </style>
    
    <script>
    function toggleTheme() {
        const html = document.documentElement;
        const isDark = html.classList.contains('dark-mode');
        
        if (isDark) {
            html.classList.remove('dark-mode');
            localStorage.setItem('theme', 'light');
        } else {
            html.classList.add('dark-mode');
            localStorage.setItem('theme', 'dark');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    });
    </script>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-brand">
                    <h1>🎮 GameStore Manager</h1>
                </div>
                
                <div class="nav-menu">
                    <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                    <a href="vendas.php" class="nav-link">💰 Vendas</a>
                    <a href="produtos.php" class="nav-link">🎮 Produtos</a>
                    <a href="clientes.php" class="nav-link">👥 Clientes</a>
                    <a href="estoque.php" class="nav-link">📦 Estoque</a>
                    <a href="relatorios.php" class="nav-link">📈 Relatórios</a>
                    
                    <div class="nav-actions">
                        <button id="themeBtn" class="theme-btn" onclick="toggleTheme()" title="Alternar tema">
                            <span class="theme-icon sun">☀️</span>
                            <span class="theme-icon moon">🌙</span>
                        </button>
                        <span class="user-info">
                            Olá, <?php echo $_SESSION['funcionario_nome']; ?>
                            <?php if(isAdmin()): ?>
                                <small style="color: #f39c12;">👑</small>
                            <?php endif; ?>
                        </span>
                        <a href="logout.php" class="btn btn-danger btn-sm">Sair</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container">
            <?php if(isset($_SESSION['sucesso'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?></div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['erro'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
            <?php endif; ?>