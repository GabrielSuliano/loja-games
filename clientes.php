<?php
include 'includes/config.php';
verificarAuth();

// ✅ FUNÇÃO PARA VALIDAR CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Calcula e confere primeiro dígito verificador
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

// ✅ FUNÇÃO PARA VALIDAR IDADE (MÍNIMO 18 ANOS)
function validarIdade($data_nascimento) {
    if (empty($data_nascimento)) return true; // Opcional
    
    $nascimento = new DateTime($data_nascimento);
    $hoje = new DateTime();
    $idade = $hoje->diff($nascimento)->y;
    
    return $idade >= 18; // ✅ CORRIGIDO: 13 → 18
}

// ✅ FUNÇÃO PARA VERIFICAR SE EMAIL É DE FUNCIONÁRIO/ADMIN
function emailEhFuncionario($email) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as total FROM funcionarios WHERE email = ? AND ativo = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'];
    $stmt->close();
    
    return $total > 0;
}

// ✅ PROCESSAR AÇÕES
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['adicionar_cliente'])) {
        $nome = $conn->real_escape_string($_POST['nome']);
        $email = $conn->real_escape_string($_POST['email']);
        $telefone = $conn->real_escape_string($_POST['telefone']);
        $cpf = $conn->real_escape_string($_POST['cpf']);
        $endereco = $conn->real_escape_string($_POST['endereco']);
        $data_nascimento = $conn->real_escape_string($_POST['data_nascimento']);
        
        // ✅ VALIDAÇÃO: BLOQUEAR EMAIL DE FUNCIONÁRIOS/ADMIN
        if (!empty($email) && emailEhFuncionario($email)) {
            $_SESSION['erro'] = "❌ Este email pertence a um funcionário/administrador e não pode ser usado para cadastro de cliente!";
            header('Location: clientes.php');
            exit;
        }
        
        // ✅ VALIDAÇÃO DE CPF
        if (!empty($cpf)) {
            $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
            
            if (!validarCPF($cpf_limpo)) {
                $_SESSION['erro'] = "❌ CPF inválido!";
                header('Location: clientes.php');
                exit;
            }
            
            // ✅ VERIFICAR CPF DUPLICADO
            $sql_verifica = "SELECT id FROM clientes WHERE cpf = '$cpf_limpo'";
            $result = $conn->query($sql_verifica);
            if ($result->num_rows > 0) {
                $_SESSION['erro'] = "❌ CPF já cadastrado!";
                header('Location: clientes.php');
                exit;
            }
            
            $cpf = $cpf_limpo;
        }
        
        // ✅ VALIDAÇÃO DE DATA DE NASCIMENTO
        if (!empty($data_nascimento)) {
            $data_nascimento_mysql = date('Y-m-d', strtotime(str_replace('/', '-', $data_nascimento)));
            
            if (!validarIdade($data_nascimento_mysql)) {
                $_SESSION['erro'] = "❌ Cliente deve ter pelo menos 18 anos de idade!"; // ✅ CORRIGIDO
                header('Location: clientes.php');
                exit;
            }
        } else {
            $data_nascimento_mysql = null;
        }
        
        // ✅ INSERIR NO BANCO
        $sql = "INSERT INTO clientes (nome, email, telefone, cpf, endereco, data_nascimento) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ssssss", $nome, $email, $telefone, $cpf, $endereco, $data_nascimento_mysql);
            
            if ($stmt->execute()) {
                $_SESSION['sucesso'] = "✅ Cliente cadastrado com sucesso!";
            } else {
                $_SESSION['erro'] = "❌ Erro ao cadastrar cliente";
            }
            $stmt->close();
        }
        
        header('Location: clientes.php');
        exit;
    }
    
    // ✅ EXCLUIR CLIENTE
    if (isset($_POST['excluir_cliente'])) {
        $id = $_POST['id'];
        
        // Verificar se cliente tem vendas
        $sql_vendas = "SELECT COUNT(*) as total_vendas FROM vendas WHERE cliente_id = ?";
        $stmt = $conn->prepare($sql_vendas);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $vendas = $result->fetch_assoc();
        
        if ($vendas['total_vendas'] > 0) {
            $_SESSION['erro'] = "❌ Não é possível excluir cliente com histórico de vendas!";
        } else {
            $sql = "DELETE FROM clientes WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $_SESSION['sucesso'] = "✅ Cliente excluído com sucesso!";
            } else {
                $_SESSION['erro'] = "❌ Erro ao excluir cliente";
            }
        }
        
        header('Location: clientes.php');
        exit;
    }
}

// ✅ EXPORTAR CLIENTES PARA EXCEL
if (isset($_GET['exportar_excel'])) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.xls"');
    
    echo "<meta charset='UTF-8'>";
    echo "<table border='1'>";
    echo "<tr><th colspan='6'>LISTA DE CLIENTES - GAMESTORE</th></tr>";
    echo "<tr><th colspan='6'>Gerado em: " . date('d/m/Y H:i') . "</th></tr>";
    echo "<tr><th>Nome</th><th>Email</th><th>Telefone</th><th>Data Nascimento</th><th>Total Compras</th><th>Total Gasto</th></tr>";
    
    // CONSULTA MODIFICADA: Não buscar o campo CPF
    $sql_excel = "SELECT 
                    c.id,
                    c.nome,
                    c.email,
                    c.telefone,
                    c.data_nascimento,
                    c.endereco,
                    c.data_cadastro,
                    (SELECT COUNT(*) FROM vendas WHERE cliente_id = c.id) as total_compras,
                    (SELECT COALESCE(SUM(total_venda), 0) FROM vendas WHERE cliente_id = c.id) as total_gasto
                  FROM clientes c 
                  ORDER BY nome";
    
    $result_excel = $conn->query($sql_excel);
    
    while($cliente = $result_excel->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($cliente['nome']) . "</td>";
        echo "<td>" . htmlspecialchars($cliente['email'] ?: '--') . "</td>";
        echo "<td>" . htmlspecialchars($cliente['telefone'] ?: '--') . "</td>";
        echo "<td>" . ($cliente['data_nascimento'] ? date('d/m/Y', strtotime($cliente['data_nascimento'])) : '--') . "</td>";
        echo "<td>" . $cliente['total_compras'] . "</td>";
        echo "<td>R$ " . number_format($cliente['total_gasto'], 2, ',', '.') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

// ✅ BUSCAR CLIENTES (CONSULTA ORIGINAL PARA A PÁGINA)
$sql = "SELECT c.*, 
               (SELECT COUNT(*) FROM vendas WHERE cliente_id = c.id) as total_compras,
               (SELECT COALESCE(SUM(total_venda), 0) FROM vendas WHERE cliente_id = c.id) as total_gasto
        FROM clientes c 
        ORDER BY nome";
$clientes = $conn->query($sql);

$page_title = "Gerenciar Clientes - GameStore Manager";
include 'includes/header.php';
?>

<div class="page-header">
    <h1>👥 Gerenciar Clientes</h1>
    <p>Cadastro e histórico de clientes</p>
</div>

<!-- ✅ BOTÃO EXPORTAR EXCEL -->
<div class="export-buttons">
    <a href="?exportar_excel=1" class="btn btn-success">📊 Exportar Excel</a>
</div>

<!-- ✅ POP-UP DE CONFIRMAÇÃO DE EXCLUSÃO -->
<div id="modal-excluir" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🗑️ Confirmar Exclusão</h3>
            <button type="button" class="modal-close" onclick="fecharModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Tem certeza que deseja excluir este cliente?</p>
            <div class="cliente-info">
                <strong>Nome:</strong> <span id="cliente-nome"></span><br>
                <strong>Email:</strong> <span id="cliente-email"></span><br>
                <strong>CPF:</strong> <span id="cliente-cpf"></span>
            </div>
            <p class="warning-text">⚠️ Esta ação não pode ser desfeita!</p>
        </div>
        <div class="modal-footer">
            <form method="POST" id="form-excluir">
                <input type="hidden" name="id" id="cliente-id">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">❌ Cancelar</button>
                <button type="submit" name="excluir_cliente" class="btn btn-danger">🗑️ Confirmar Exclusão</button>
            </form>
        </div>
    </div>
</div>

<div class="content-grid">
    <!-- ✅ FORMULÁRIO EXPANDIDO - AGORA OCUPA 60% DA TELA -->
    <div class="content-column form-column">
        <div class="card">
            <h2>➕ Adicionar Novo Cliente</h2>
            <form method="POST" id="form-cliente">
                <div class="form-group">
                    <label for="nome">Nome Completo *:</label>
                    <input type="text" id="nome" name="nome" required class="form-control" placeholder="Ex: João Silva">
                    <div class="invalid-feedback" id="nome-error"></div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="exemplo@email.com">
                        <div class="invalid-feedback" id="email-error"></div>
                        <small class="form-text text-muted">⚠️ Emails de funcionários/admin são bloqueados</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="text" id="telefone" name="telefone" class="form-control" placeholder="(11) 99999-9999">
                        <div class="invalid-feedback" id="telefone-error"></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cpf">CPF:</label>
                        <input type="text" id="cpf" name="cpf" class="form-control" placeholder="000.000.000-00" maxlength="14">
                        <div class="invalid-feedback" id="cpf-error"></div>
                        <small class="form-text text-muted">Digite apenas números</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_nascimento">Data de Nascimento:</label>
                        <input type="text" id="data_nascimento" name="data_nascimento" class="form-control" placeholder="dd/mm/aaaa" maxlength="10">
                        <div class="invalid-feedback" id="data_nascimento-error"></div>
                        <small class="form-text text-muted">Mínimo 18 anos</small> <!-- ✅ CORRIGIDO -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="endereco">Endereço:</label>
                    <textarea id="endereco" name="endereco" class="form-control" rows="3" placeholder="Endereço completo..."></textarea>
                </div>
                
                <button type="submit" name="adicionar_cliente" class="btn btn-primary" id="btn-submit">
                    💾 Adicionar Cliente
                </button>
            </form>
        </div>
    </div>
    
    <!-- ✅ LISTA DE CLIENTES - AGORA OCUPA 40% DA TELA -->
    <div class="content-column list-column">
        <div class="card">
            <h2>📋 Lista de Clientes</h2>
            
            <?php if($clientes->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Contato</th>
                            <th>CPF</th>
                            <th>Nascimento</th>
                            <th>Compras</th>
                            <th>Total Gasto</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($cliente = $clientes->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($cliente['nome']); ?></strong>
                                <br>
                                <small>Cadastro: <?php echo date('d/m/Y', strtotime($cliente['data_cadastro'])); ?></small>
                            </td>
                            <td>
                                <?php if($cliente['email']): ?>
                                    <div>📧 <?php echo htmlspecialchars($cliente['email']); ?></div>
                                <?php endif; ?>
                                <?php if($cliente['telefone']): ?>
                                    <div>📞 <?php echo formatarTelefone($cliente['telefone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $cliente['cpf'] ? formatarCPF($cliente['cpf']) : '--'; ?></td>
                            <td>
                                <?php if($cliente['data_nascimento']): ?>
                                    <?php echo date('d/m/Y', strtotime($cliente['data_nascimento'])); ?>
                                    <br>
                                    <small><?php echo calcularIdade($cliente['data_nascimento']); ?> anos</small>
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $cliente['total_compras'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $cliente['total_compras']; ?> compras
                                </span>
                            </td>
                            <td>
                                <strong>R$ <?php echo number_format($cliente['total_gasto'], 2, ',', '.'); ?></strong>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            onclick="abrirModalExclusao(
                                                <?php echo $cliente['id']; ?>, 
                                                '<?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($cliente['email'] ?: '--', ENT_QUOTES); ?>',
                                                '<?php echo $cliente['cpf'] ? formatarCPF($cliente['cpf']) : '--'; ?>'
                                            )">
                                        🗑️ Excluir
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="sem-dados">
                <h3>😔 Nenhum cliente cadastrado</h3>
                <p>Cadastre o primeiro cliente usando o formulário ao lado.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ✅ FUNÇÕES DO POP-UP DE EXCLUSÃO
function abrirModalExclusao(id, nome, email, cpf) {
    document.getElementById('cliente-id').value = id;
    document.getElementById('cliente-nome').textContent = nome;
    document.getElementById('cliente-email').textContent = email;
    document.getElementById('cliente-cpf').textContent = cpf;
    document.getElementById('modal-excluir').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function fecharModal() {
    document.getElementById('modal-excluir').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// ✅ FECHAR MODAL CLICANDO FORA
document.getElementById('modal-excluir').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModal();
    }
});

// ✅ FECHAR MODAL COM ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModal();
    }
});

// ✅ FORMATAÇÃO E VALIDAÇÃO DE CPF
document.getElementById('cpf')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.substring(0, 11);
    
    if (value.length > 9) {
        value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    } else if (value.length > 6) {
        value = value.replace(/^(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
    } else if (value.length > 3) {
        value = value.replace(/^(\d{3})(\d{3})/, '$1.$2');
    }
    
    e.target.value = value;
    validarCPF(e.target);
});

// ✅ FORMATAÇÃO DE TELEFONE
document.getElementById('telefone')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.substring(0, 11);
    
    if (value.length > 10) {
        value = value.replace(/^(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (value.length > 6) {
        value = value.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else if (value.length > 2) {
        value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
    } else if (value.length > 0) {
        value = value.replace(/^(\d{0,2})/, '($1');
    }
    
    e.target.value = value;
});

// ✅ FORMATAÇÃO DE DATA DE NASCIMENTO
document.getElementById('data_nascimento')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 8) value = value.substring(0, 8);
    
    if (value.length > 4) {
        value = value.replace(/^(\d{2})(\d{2})(\d{4})/, '$1/$2/$3');
    } else if (value.length > 2) {
        value = value.replace(/^(\d{2})(\d{2})/, '$1/$2');
    }
    
    e.target.value = value;
    validarDataNascimento(e.target);
});

// ✅ VALIDAÇÃO DE CPF EM TEMPO REAL
function validarCPF(input) {
    const cpf = input.value.replace(/\D/g, '');
    const errorElement = document.getElementById('cpf-error');
    
    if (cpf === '') {
        input.classList.remove('is-invalid', 'is-valid');
        errorElement.textContent = '';
        return true;
    }
    
    if (cpf.length !== 11) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'CPF deve ter 11 dígitos';
        return false;
    }
    
    // Verifica CPFs inválidos conhecidos
    const cpfsInvalidos = [
        '00000000000', '11111111111', '22222222222',
        '33333333333', '44444444444', '55555555555',
        '66666666666', '77777777777', '88888888888', '99999999999'
    ];
    
    if (cpfsInvalidos.includes(cpf)) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'CPF inválido';
        return false;
    }
    
    // Validação dos dígitos verificadores
    let soma = 0;
    let resto;
    
    for (let i = 1; i <= 9; i++) {
        soma += parseInt(cpf.substring(i-1, i)) * (11 - i);
    }
    
    resto = (soma * 10) % 11;
    if ((resto === 10) || (resto === 11)) resto = 0;
    if (resto !== parseInt(cpf.substring(9, 10))) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'CPF inválido';
        return false;
    }
    
    soma = 0;
    for (let i = 1; i <= 10; i++) {
        soma += parseInt(cpf.substring(i-1, i)) * (12 - i);
    }
    
    resto = (soma * 10) % 11;
    if ((resto === 10) || (resto === 11)) resto = 0;
    if (resto !== parseInt(cpf.substring(10, 11))) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'CPF inválido';
        return false;
    }
    
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    errorElement.textContent = '';
    return true;
}

// ✅ VALIDAÇÃO DE DATA DE NASCIMENTO EM TEMPO REAL
function validarDataNascimento(input) {
    const data = input.value;
    const errorElement = document.getElementById('data_nascimento-error');
    
    if (data === '') {
        input.classList.remove('is-invalid', 'is-valid');
        errorElement.textContent = '';
        return true;
    }
    
    const regexData = /^(\d{2})\/(\d{2})\/(\d{4})$/;
    if (!regexData.test(data)) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'Formato inválido. Use dd/mm/aaaa';
        return false;
    }
    
    const [, dia, mes, ano] = data.match(regexData);
    const dataObj = new Date(ano, mes - 1, dia);
    const hoje = new Date();
    
    // Verifica se a data é válida
    if (dataObj.getDate() != dia || dataObj.getMonth() != mes - 1 || dataObj.getFullYear() != ano) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'Data inválida';
        return false;
    }
    
    // Verifica se não é data futura
    if (dataObj > hoje) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'Data não pode ser futura';
        return false;
    }
    
    // Calcula idade
    const idade = hoje.getFullYear() - dataObj.getFullYear();
    const mesAtual = hoje.getMonth();
    const diaAtual = hoje.getDate();
    
    const idadeCorrigida = (mesAtual < (mes - 1) || (mesAtual === (mes - 1) && diaAtual < dia)) ? idade - 1 : idade;
    
    // ✅ CORRIGIDO: Verifica idade mínima (18 anos)
    if (idadeCorrigida < 18) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'Cliente deve ter pelo menos 18 anos'; // ✅ CORRIGIDO
        return false;
    }
    
    // Verifica idade máxima (120 anos)
    if (idadeCorrigida > 120) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorElement.textContent = 'Idade inválida';
        return false;
    }
    
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    errorElement.textContent = '';
    return true;
}

// ✅ VALIDAÇÃO DE EMAIL EM TEMPO REAL
document.getElementById('email')?.addEventListener('blur', function(e) {
    const email = e.target.value;
    const errorElement = document.getElementById('email-error');
    
    if (email === '') {
        e.target.classList.remove('is-invalid', 'is-valid');
        errorElement.textContent = '';
        return true;
    }
    
    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regexEmail.test(email)) {
        e.target.classList.add('is-invalid');
        e.target.classList.remove('is-valid');
        errorElement.textContent = 'Email inválido';
        return false;
    }
    
    e.target.classList.remove('is-invalid');
    e.target.classList.add('is-valid');
    errorElement.textContent = '';
    return true;
});

// ✅ VALIDAÇÃO DO FORMULÁRIO ANTES DO ENVIO
document.getElementById('form-cliente')?.addEventListener('submit', function(e) {
    let formValido = true;
    
    // Validar CPF
    const cpfInput = document.getElementById('cpf');
    if (cpfInput.value && !validarCPF(cpfInput)) {
        formValido = false;
    }
    
    // Validar data de nascimento
    const dataInput = document.getElementById('data_nascimento');
    if (dataInput.value && !validarDataNascimento(dataInput)) {
        formValido = false;
    }
    
    // Validar email
    const emailInput = document.getElementById('email');
    if (emailInput.value && !emailInput.classList.contains('is-valid')) {
        emailInput.classList.add('is-invalid');
        formValido = false;
    }
    
    if (!formValido) {
        e.preventDefault();
        alert('Por favor, corrija os erros no formulário antes de enviar.');
    }
});
</script>


<style>
.export-buttons {
    margin: 1rem 0;
}

.sem-dados {
    text-align: center;
    padding: 3rem;
    color: #6c757d;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
}

.badge-success {
    background: #27ae60;
    color: white;
}

.badge-secondary {
    background: #95a5a6;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* ✅ ESTILOS PARA VALIDAÇÃO */
.is-valid {
    border-color: #28a745 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.is-invalid {
    border-color: #dc3545 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='l5.8 3.6.4.4.4-.4'/%3e%3cpath d='M6 7v2.5'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.form-text {
    font-size: 0.875em;
    margin-top: 0.25rem;
    color: #6c757d;
}

/* ✅ NOVOS ESTILOS PARA EXPANDIR O FORMULÁRIO */
.content-grid {
    display: grid;
    grid-template-columns: 60% 40%;
    gap: 2rem;
    align-items: start;
}

/* ✅ MELHORIAS PARA FORMULÁRIO MAIS ESPAÇOSO */
.form-group {
    margin-bottom: 1.5rem;
}

.form-control {
    padding: 0.75rem 1rem;
    font-size: 1rem;
    border-radius: 8px;
    border: 1px solid #ddd;
    width: 100%;
    transition: all 0.3s ease;
    background: white;
    color: #333;
}

.form-control:focus {
    border-color: #4a90e2;
    box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    outline: none;
}

.btn {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    margin-top: 1rem;
}

.btn-primary {
    background: #4a90e2;
    color: white;
}

.btn-primary:hover {
    background: #357abd;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* ✅ MELHORIAS PARA A TABELA */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    background: white;
}

.data-table th,
.data-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #eee;
    color: #333;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

/* ✅ CORREÇÃO DO HOVER - MAIS SUAVE */
.data-table tr:hover {
    background: rgba(0, 0, 0, 0.03) !important;
    transition: background-color 0.2s ease;
}

/* ✅ ESTILOS DO MODAL DE EXCLUSÃO - CORES CORRIGIDAS */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 500px;
    border: 1px solid #ddd;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: 1px solid #eee;
}

.modal-header h3 {
    margin: 0;
    color: #333;
    font-size: 1.25rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: #f8f9fa;
    color: #333;
}

.modal-body {
    padding: 1.5rem;
    color: #333;
}

.modal-body p {
    margin: 0 0 1rem 0;
    line-height: 1.5;
    color: #333;
}

.cliente-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    border-left: 4px solid #e74c3c;
    color: #333;
}

.cliente-info strong {
    color: #333;
}

.warning-text {
    color: #e74c3c;
    font-weight: 600;
    background: rgba(231, 76, 60, 0.1);
    padding: 0.75rem;
    border-radius: 6px;
    border-left: 4px solid #e74c3c;
}

.modal-footer {
    padding: 1rem 1.5rem 1.5rem;
    border-top: 1px solid #eee;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.modal-footer .btn {
    width: auto;
    margin: 0;
    min-width: 140px;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: 1px solid #6c757d;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
}

/* ✅ RESPONSIVIDADE */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 55% 45%;
    }
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-control {
        font-size: 16px;
    }
    
    .modal-content {
        margin: 1rem;
        max-width: none;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
}

/* ✅ DARK MODE MANUAL - CORES DEFINIDAS */
.dark-mode .modal-content {
    background: #2d3748;
    border-color: #4a5568;
    color: #e2e8f0;
}

.dark-mode .modal-header {
    border-bottom-color: #4a5568;
}

.dark-mode .modal-header h3 {
    color: #e2e8f0;
}

.dark-mode .modal-body {
    color: #e2e8f0;
}

.dark-mode .modal-body p {
    color: #e2e8f0;
}

.dark-mode .cliente-info {
    background: #4a5568;
    color: #e2e8f0;
}

.dark-mode .cliente-info strong {
    color: #e2e8f0;
}

.dark-mode .modal-close {
    color: #a0aec0;
}

.dark-mode .modal-close:hover {
    background: #4a5568;
    color: #e2e8f0;
}

.dark-mode .modal-footer {
    border-top-color: #4a5568;
}

.dark-mode .data-table {
    background: #2d3748;
    color: #e2e8f0;
}

.dark-mode .data-table th,
.dark-mode .data-table td {
    color: #e2e8f0;
    border-bottom-color: #4a5568;
}

.dark-mode .data-table th {
    background: #4a5568;
    color: #e2e8f0;
}

.dark-mode .data-table tr:hover {
    background: rgba(255, 255, 255, 0.05) !important;
}

.dark-mode .form-control {
    background: #4a5568;
    border-color: #718096;
    color: #e2e8f0;
}

.dark-mode .form-control:focus {
    border-color: #4a90e2;
    background: #4a5568;
    color: #e2e8f0;
}

.dark-mode .form-text {
    color: #a0aec0;
}

.dark-mode .sem-dados {
    color: #a0aec0;
}

/* ✅ SEU SISTEMA JÁ TEM DARK MODE, ADICIONE APENAS ESTAS CORES */
body.dark-mode {
    --card-bg: #2d3748;
    --text-color: #e2e8f0;
    --border-color: #4a5568;
    --hover-bg: #4a5568;
    --text-muted: #a0aec0;
    --table-header-bg: #4a5568;
    --btn-secondary-bg: #6c757d;
    --btn-secondary-color: white;
    --btn-secondary-hover: #5a6268;
}

</style>

<?php
// ✅ FUNÇÃO PARA CALCULAR IDADE
function calcularIdade($data_nascimento) {
    if (empty($data_nascimento)) return '--';
    
    $nascimento = new DateTime($data_nascimento);
    $hoje = new DateTime();
    $idade = $hoje->diff($nascimento);
    return $idade->y;
}

// ✅ FUNÇÃO PARA FORMATAR CPF
function formatarCPF($cpf) {
    if (empty($cpf)) return '--';
    
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) return $cpf;
    
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

// ✅ FUNÇÃO PARA FORMATAR TELEFONE
function formatarTelefone($telefone) {
    if (empty($telefone)) return '--';
    
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    $tamanho = strlen($telefone);
    
    if ($tamanho === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    } elseif ($tamanho === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    
    return $telefone;
}

include 'includes/footer.php';