<?php
include 'includes/config.php';
verificarAuth();
verificarAdmin(); // ✅ Só admin (nível 2) pode acessar

$page_title = "Gerenciar Usuários - Sistema";
include 'includes/header.php';

// ✅ ADICIONAR NOVO USUÁRIO
if (isset($_POST['adicionar'])) {
    $nome = $conn->real_escape_string($_POST['nome']);
    $email = $conn->real_escape_string($_POST['email']);
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $nivel = intval($_POST['nivel']); // ✅ 1=funcionário, 2=admin
    
    $sql = "INSERT INTO funcionarios (nome, email, senha, nivel_acesso, ativo) 
            VALUES ('$nome', '$email', '$senha', $nivel, 1)";
    
    if ($conn->query($sql)) {
        $sucesso = "✅ Usuário adicionado com sucesso!";
    } else {
        $erro = "❌ Erro: " . $conn->error;
    }
}

// ✅ ALTERAR SENHA
if (isset($_POST['alterar_senha'])) {
    $id = intval($_POST['id']);
    $nova_senha = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
    
    $sql = "UPDATE funcionarios SET senha = '$nova_senha' WHERE id = $id";
    
    if ($conn->query($sql)) {
        $sucesso = "✅ Senha alterada com sucesso!";
    } else {
        $erro = "❌ Erro: " . $conn->error;
    }
}

// ✅ ALTERAR EMAIL
if (isset($_POST['alterar_email'])) {
    $id = intval($_POST['id_email']);
    $novo_email = $conn->real_escape_string($_POST['novo_email']);
    
    $sql = "UPDATE funcionarios SET email = '$novo_email' WHERE id = $id";
    
    if ($conn->query($sql)) {
        $sucesso = "✅ Email alterado com sucesso para: " . $novo_email;
    } else {
        $erro = "❌ Erro ao alterar email: " . $conn->error;
    }
}

// ✅ EXCLUIR USUÁRIO (COM PROTEÇÕES)
if (isset($_POST['excluir_usuario'])) {
    $id = intval($_POST['id_excluir']);
    $usuario_atual = $_SESSION['funcionario_id'];
    
    // ✅ PROTEÇÕES DE SEGURANÇA
    if ($id == $usuario_atual) {
        $erro = "❌ Você não pode excluir seu próprio usuário!";
    } elseif ($id == 1) {
        $erro = "❌ Não é possível excluir o usuário administrador principal!";
    } else {
        $sql = "DELETE FROM funcionarios WHERE id = $id";
        
        if ($conn->query($sql)) {
            $sucesso = "✅ Usuário excluído com sucesso!";
        } else {
            $erro = "❌ Erro ao excluir usuário: " . $conn->error;
        }
    }
}

// ✅ BUSCAR TODOS OS USUÁRIOS
$sql_usuarios = "SELECT id, nome, email, nivel_acesso, ativo FROM funcionarios ORDER BY nome";
$usuarios = $conn->query($sql_usuarios);

// ✅ CONTAR USUÁRIOS PARA PROTEÇÃO
$sql_count = "SELECT COUNT(*) as total FROM funcionarios";
$total_usuarios = $conn->query($sql_count)->fetch_assoc()['total'];
?>

<div class="page-header">
    <h1>🔧 Gerenciar Usuários</h1>
    <p>Adicionar administradores e alterar dados</p>
</div>

<?php if(isset($sucesso)): ?>
    <div class="alert alert-success"><?php echo $sucesso; ?></div>
<?php endif; ?>

<?php if(isset($erro)): ?>
    <div class="alert alert-error"><?php echo $erro; ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- ✅ FORMULÁRIO ADICIONAR USUÁRIO -->
    <div class="card">
        <h3>➕ Adicionar Novo Usuário</h3>
        <form method="POST">
            <div class="form-group">
                <label>Nome:</label>
                <input type="text" name="nome" required class="form-control" placeholder="Nome completo">
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required class="form-control" placeholder="email@dominio.com">
            </div>
            
            <div class="form-group">
                <label>Senha:</label>
                <input type="password" name="senha" required class="form-control" placeholder="Nova senha">
            </div>
            
            <div class="form-group">
                <label>Nível de Acesso:</label>
                <select name="nivel" class="form-control" required>
                    <option value="1">👨‍💼 Funcionário</option>
                    <option value="2">👑 Administrador</option>
                </select>
            </div>
            
            <button type="submit" name="adicionar" class="btn btn-primary">
                 Adicionar Usuário
            </button>
        </form>
    </div>

    <!-- ✅ LISTA DE USUÁRIOS -->
    <div class="card">
        <h3>👥 Usuários do Sistema (<?php echo $total_usuarios; ?>)</h3>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = $usuarios->fetch_assoc()): 
                        $pode_excluir = ($user['id'] != $_SESSION['funcionario_id'] && $user['id'] != 1);
                        $eh_admin = $user['nivel_acesso'] == 2;
                    ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($user['nome']); ?>
                            <?php if($user['id'] == 1): ?>
                                <br><small style="color: #e74c3c;">👑 Principal</small>
                            <?php elseif($user['id'] == $_SESSION['funcionario_id']): ?>
                                <br><small style="color: #3498db;">👋 Você</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $eh_admin ? 'badge-warning' : 'badge-info'; ?>">
                                <?php echo $eh_admin ? '👑 Admin' : '👨‍💼 Func'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $user['ativo'] ? 'badge-success' : 'badge-error'; ?>">
                                <?php echo $user['ativo'] ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-warning" 
                                        onclick="abrirModalSenha(<?php echo $user['id']; ?>, '<?php echo $user['nome']; ?>')">
                                    🔑 Senha
                                </button>
                                <button type="button" class="btn btn-sm btn-info" 
                                        onclick="abrirModalEmail(<?php echo $user['id']; ?>, '<?php echo $user['nome']; ?>', '<?php echo $user['email']; ?>')">
                                    📧 Email
                                </button>
                                <?php if($pode_excluir): ?>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="abrirModalExcluir(<?php echo $user['id']; ?>, '<?php echo $user['nome']; ?>')">
                                    🗑️ Excluir
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-secondary" disabled title="Não pode excluir">
                                    🚫 Excluir
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ✅ MODAL ALTERAR SENHA -->
<div id="modalSenha" class="modal">
    <div class="modal-content">
        <span class="close" onclick="fecharModal('modalSenha')">&times;</span>
        <h3>🔑 Alterar Senha</h3>
        
        <form method="POST" id="formSenha">
            <input type="hidden" name="id" id="user_id_senha">
            
            <div class="form-group">
                <label>Usuário:</label>
                <input type="text" id="user_nome_senha" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label>Nova Senha:</label>
                <input type="password" name="nova_senha" required class="form-control" placeholder="Digite a nova senha">
            </div>
            
            <button type="submit" name="alterar_senha" class="btn btn-warning">
                🔄 Alterar Senha
            </button>
        </form>
    </div>
</div>

<!-- ✅ MODAL ALTERAR EMAIL -->
<div id="modalEmail" class="modal">
    <div class="modal-content">
        <span class="close" onclick="fecharModal('modalEmail')">&times;</span>
        <h3>📧 Alterar Email</h3>
        
        <form method="POST" id="formEmail">
            <input type="hidden" name="id_email" id="user_id_email">
            
            <div class="form-group">
                <label>Usuário:</label>
                <input type="text" id="user_nome_email" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label>Email Atual:</label>
                <input type="text" id="user_email_atual" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label>Novo Email:</label>
                <input type="email" name="novo_email" required class="form-control" placeholder="novo@email.com">
            </div>
            
            <button type="submit" name="alterar_email" class="btn btn-info">
                ✏️ Alterar Email
            </button>
        </form>
    </div>
</div>

<!-- ✅ MODAL EXCLUIR USUÁRIO -->
<div id="modalExcluir" class="modal">
    <div class="modal-content">
        <span class="close" onclick="fecharModal('modalExcluir')">&times;</span>
        <h3>🗑️ Excluir Usuário</h3>
        
        <form method="POST" id="formExcluir">
            <input type="hidden" name="id_excluir" id="user_id_excluir">
            
            <div class="alert alert-warning">
                <strong>⚠️ ATENÇÃO!</strong><br>
                Esta ação <strong>NÃO PODE</strong> ser desfeita!
            </div>
            
            <div class="form-group">
                <label>Usuário a ser excluído:</label>
                <input type="text" id="user_nome_excluir" class="form-control" readonly style="font-weight: bold; color: #e74c3c;">
            </div>
            
            <div class="form-group">
                <label>Confirme o nome do usuário:</label>
                <input type="text" name="confirmacao_nome" required class="form-control" 
                       placeholder="Digite o nome exato para confirmar" id="confirmacao_input">
            </div>
            
            <div class="form-buttons">
                <button type="submit" name="excluir_usuario" class="btn btn-danger" id="btnExcluir" disabled>
                    💀 CONFIRMAR EXCLUSÃO
                </button>
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalExcluir')">
                    ↩️ Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin: 2rem 0;
}

.card {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: bold;
}

.badge-success { background: #d4edda; color: #155724; }
.badge-error { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info { background: #d1ecf1; color: #0c5460; }

.btn-group {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
}

.modal-content {
    background: #ffffff;
    margin: 10% auto;
    padding: 2rem;
    border-radius: 10px;
    width: 90%;
    max-width: 450px;
    position: relative;
    border: 2px solid #e74c3c;
    box-shadow: 0 15px 40px rgba(0,0,0,0.5);
    color: #333333;
}

/* ✅ DARK MODE FORÇADO */
@media (prefers-color-scheme: dark) {
    .modal-content {
        background: #2d3748 !important;
        color: #e2e8f0 !important;
        border: 2px solid #e74c3c !important;
    }
    
    .alert-warning {
        background: rgba(231, 76, 60, 0.2) !important;
        border: 1px solid #e74c3c !important;
        color: #e74c3c !important;
    }
}

.alert-warning {
    background: rgba(231, 76, 60, 0.1);
    border: 1px solid #e74c3c;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    color: #e74c3c;
    font-weight: bold;
}


.close {
    position: absolute;
    right: 1rem;
    top: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted);
}

.close:hover {
    color: var(--text-color);
}

.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    color: #856404;
}

.form-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .grid-2 {
        grid-template-columns: 1fr;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .form-buttons {
        flex-direction: column;
    }
    
    .modal-content {
        margin: 5% auto;
        width: 95%;
    }
}
</style>

<script>
function abrirModalSenha(id, nome) {
    document.getElementById('user_id_senha').value = id;
    document.getElementById('user_nome_senha').value = nome;
    document.getElementById('modalSenha').style.display = 'block';
}

function abrirModalEmail(id, nome, email) {
    document.getElementById('user_id_email').value = id;
    document.getElementById('user_nome_email').value = nome;
    document.getElementById('user_email_atual').value = email;
    document.getElementById('modalEmail').style.display = 'block';
}

function abrirModalExcluir(id, nome) {
    document.getElementById('user_id_excluir').value = id;
    document.getElementById('user_nome_excluir').value = nome;
    document.getElementById('modalExcluir').style.display = 'block';
    document.getElementById('confirmacao_input').value = '';
    document.getElementById('btnExcluir').disabled = true;
}

function fecharModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// ✅ VALIDAÇÃO DE CONFIRMAÇÃO PARA EXCLUSÃO
document.addEventListener('DOMContentLoaded', function() {
    const confirmacaoInput = document.getElementById('confirmacao_input');
    const userNomeExcluir = document.getElementById('user_nome_excluir');
    const btnExcluir = document.getElementById('btnExcluir');
    
    if (confirmacaoInput && userNomeExcluir && btnExcluir) {
        confirmacaoInput.addEventListener('input', function() {
            const nomeConfirmacao = this.value.trim();
            const nomeUsuario = userNomeExcluir.value.trim();
            
            btnExcluir.disabled = nomeConfirmacao !== nomeUsuario;
        });
    }
});

// Fechar modal clicando fora
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php
include 'includes/footer.php';