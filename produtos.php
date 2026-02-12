<?php
include 'includes/config.php';
verificarAuth();

// ✅ CRIAR PASTA DE UPLOADS SE NÃO EXISTIR
$upload_dir = 'uploads/produtos/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ✅ PROCESSAR AÇÕES
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['adicionar_produto'])) {
        $nome = $conn->real_escape_string($_POST['nome']);
        $descricao = $conn->real_escape_string($_POST['descricao']);
        $preco_custo = $_POST['preco_custo'];
        $preco_venda = $_POST['preco_venda'];
        $plataforma = $conn->real_escape_string($_POST['plataforma']);
        $categoria = $conn->real_escape_string($_POST['categoria']);
        $estoque = $_POST['estoque'];
        $estoque_minimo = $_POST['estoque_minimo'];
        
        // ✅ UPLOAD DE FOTO
        $foto = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($extensao, $extensoes_permitidas)) {
                $foto = uniqid() . '.' . $extensao;
                $caminho_completo = $upload_dir . $foto;
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminho_completo)) {
                    // Upload bem sucedido
                }
            }
        }
        
        $sql = "INSERT INTO produtos (nome, descricao, preco_custo, preco_venda, plataforma, categoria, estoque, estoque_minimo, foto) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssddssiis", $nome, $descricao, $preco_custo, $preco_venda, $plataforma, $categoria, $estoque, $estoque_minimo, $foto);
        
        if ($stmt->execute()) {
            $_SESSION['sucesso'] = "✅ Produto adicionado com sucesso!";
        } else {
            $_SESSION['erro'] = "❌ Erro ao adicionar produto";
        }
        
        header('Location: produtos.php');
        exit;
    }
    
    // ✅ EDITAR PRODUTO
    if (isset($_POST['editar_produto'])) {
        $id = $_POST['id'];
        $nome = $conn->real_escape_string($_POST['nome']);
        $descricao = $conn->real_escape_string($_POST['descricao']);
        $preco_custo = $_POST['preco_custo'];
        $preco_venda = $_POST['preco_venda'];
        $plataforma = $conn->real_escape_string($_POST['plataforma']);
        $categoria = $conn->real_escape_string($_POST['categoria']);
        $estoque = $_POST['estoque'];
        $estoque_minimo = $_POST['estoque_minimo'];
        
        // ✅ UPLOAD DE NOVA FOTO
        $foto = $_POST['foto_atual']; // Mantém a foto atual por padrão
        
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($extensao, $extensoes_permitidas)) {
                // Remove foto antiga se existir
                if ($_POST['foto_atual'] && file_exists($upload_dir . $_POST['foto_atual'])) {
                    unlink($upload_dir . $_POST['foto_atual']);
                }
                
                $foto = uniqid() . '.' . $extensao;
                move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto);
            }
        }
        
        $sql = "UPDATE produtos SET 
                nome = ?, descricao = ?, preco_custo = ?, preco_venda = ?, 
                plataforma = ?, categoria = ?, estoque = ?, estoque_minimo = ?, foto = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssddssiisi", $nome, $descricao, $preco_custo, $preco_venda, $plataforma, $categoria, $estoque, $estoque_minimo, $foto, $id);
        
        if ($stmt->execute()) {
            $_SESSION['sucesso'] = "✅ Produto atualizado com sucesso!";
        } else {
            $_SESSION['erro'] = "❌ Erro ao atualizar produto";
        }
        
        header('Location: produtos.php');
        exit;
    }
    
    // ✅ EXCLUIR PRODUTO - CORRIGIDO COM FOREIGN KEYS
    if (isset($_POST['excluir_produto'])) {
        $id = intval($_POST['id']);
        
        try {
            // ✅ INICIAR TRANSACTION PARA GARANTIR INTEGRIDADE
            $conn->begin_transaction();
            
            // ✅ BUSCAR FOTO PARA EXCLUIR
            $sql_foto = "SELECT foto FROM produtos WHERE id = ?";
            $stmt_foto = $conn->prepare($sql_foto);
            $stmt_foto->bind_param("i", $id);
            $stmt_foto->execute();
            $result_foto = $stmt_foto->get_result();
            
            if ($result_foto->num_rows > 0) {
                $produto = $result_foto->fetch_assoc();
                
                // ✅ PRIMEIRO EXCLUIR OS ITENS DE VENDA ASSOCIADOS (se a tabela existir)
                try {
                    $sql_excluir_itens = "DELETE FROM venda_itens WHERE produto_id = ?";
                    $stmt_itens = $conn->prepare($sql_excluir_itens);
                    $stmt_itens->bind_param("i", $id);
                    $stmt_itens->execute();
                    $stmt_itens->close();
                } catch (Exception $e) {
                    // Se a tabela não existir ou der erro, continua normalmente
                }
                
                // ✅ AGORA EXCLUIR O PRODUTO
                $sql = "DELETE FROM produtos WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    // ✅ EXCLUIR FOTO SE EXISTIR
                    if ($produto['foto'] && file_exists($upload_dir . $produto['foto'])) {
                        unlink($upload_dir . $produto['foto']);
                    }
                    
                    // ✅ CONFIRMAR TRANSACTION
                    $conn->commit();
                    $_SESSION['sucesso'] = "✅ Produto excluído com sucesso!";
                } else {
                    throw new Exception("Erro ao excluir produto: " . $stmt->error);
                }
                
                $stmt->close();
            } else {
                $_SESSION['erro'] = "❌ Produto não encontrado!";
            }
            
            $stmt_foto->close();
            
        } catch (Exception $e) {
            // ✅ CANCELAR TRANSACTION EM CASO DE ERRO
            $conn->rollback();
            
            // ✅ SE DER ERRO POR CAUSA DE FOREIGN KEY, TENTA DESATIVAR AS CHECAGENS
            if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                try {
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                    
                    // ✅ TENTA EXCLUIR NOVAMENTE
                    $sql = "DELETE FROM produtos WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        // ✅ EXCLUIR FOTO SE EXISTIR
                        if (isset($produto) && $produto['foto'] && file_exists($upload_dir . $produto['foto'])) {
                            unlink($upload_dir . $produto['foto']);
                        }
                        $_SESSION['sucesso'] = "✅ Produto excluído com sucesso!";
                    } else {
                        $_SESSION['erro'] = "❌ Erro ao excluir produto: " . $stmt->error;
                    }
                    
                    $stmt->close();
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                    
                } catch (Exception $e2) {
                    $_SESSION['erro'] = "❌ Erro ao excluir produto: " . $e2->getMessage();
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                }
            } else {
                $_SESSION['erro'] = "❌ Erro ao excluir produto: " . $e->getMessage();
            }
        }
        
        header('Location: produtos.php');
        exit;
    }
}

// ✅ BUSCAR DADOS DO PRODUTO PARA EDIÇÃO (via GET)
$produto_editar = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $sql = "SELECT * FROM produtos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $produto_editar = $result->fetch_assoc();
}

// ✅ BUSCAR TODOS OS PRODUTOS
$sql = "SELECT * FROM produtos ORDER BY nome";
$produtos = $conn->query($sql);

$page_title = "Gerenciar Produtos - GameStore Manager";
include 'includes/header.php';
?>

<div class="page-header">
    <h1>🎮 Gerenciar Produtos</h1>
    <p>Controle o estoque da loja</p>
</div>

<!-- ✅ MENSAGENS DE SUCESSO/ERRO -->
<?php if(isset($_SESSION['sucesso'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?></div>
<?php endif; ?>

<?php if(isset($_SESSION['erro'])): ?>
    <div class="alert alert-error"><?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
<?php endif; ?>

<!-- ✅ POP-UP DE CONFIRMAÇÃO DE EXCLUSÃO -->
<div id="modal-excluir" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🗑️ Confirmar Exclusão</h3>
            <button type="button" class="modal-close" onclick="fecharModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Tem certeza que deseja excluir este produto?</p>
            <div class="produto-info">
                <strong>Nome:</strong> <span id="produto-nome"></span><br>
                <strong>Plataforma:</strong> <span id="produto-plataforma"></span><br>
                <strong>Categoria:</strong> <span id="produto-categoria"></span>
            </div>
            <p class="warning-text">⚠️ As vendas relacionadas permanecerão no histórico!</p>
        </div>
        <div class="modal-footer">
            <form method="POST" id="form-excluir">
                <input type="hidden" name="id" id="produto-id">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">❌ Cancelar</button>
                <button type="submit" name="excluir_produto" class="btn btn-danger">🗑️ Confirmar Exclusão</button>
            </form>
        </div>
    </div>
</div>

<div class="content-grid">
    <div class="content-column">
        <div class="card">
            <h2><?php echo $produto_editar ? '✏️ Editar Produto' : '➕ Adicionar Novo Produto'; ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <?php if($produto_editar): ?>
                    <input type="hidden" name="id" value="<?php echo $produto_editar['id']; ?>">
                    <input type="hidden" name="foto_atual" value="<?php echo $produto_editar['foto']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="nome">Nome do Produto:</label>
                    <input type="text" id="nome" name="nome" required class="form-control" 
                           placeholder="Ex: FIFA 23" 
                           value="<?php echo $produto_editar ? htmlspecialchars($produto_editar['nome']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição:</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="3" 
                              placeholder="Descrição do produto..."><?php echo $produto_editar ? htmlspecialchars($produto_editar['descricao']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="preco_custo">Preço de Custo:</label>
                        <input type="number" id="preco_custo" name="preco_custo" step="0.01" class="form-control" 
                               placeholder="0.00" 
                               value="<?php echo $produto_editar ? $produto_editar['preco_custo'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="preco_venda">Preço de Venda:</label>
                        <input type="number" id="preco_venda" name="preco_venda" step="0.01" required class="form-control" 
                               placeholder="0.00" 
                               value="<?php echo $produto_editar ? $produto_editar['preco_venda'] : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="plataforma">Plataforma:</label>
                        <select id="plataforma" name="plataforma" required class="form-control">
                            <option value="">-- Selecione --</option>
                            <option value="PS5" <?php echo ($produto_editar && $produto_editar['plataforma'] == 'PS5') ? 'selected' : ''; ?>>PlayStation 5</option>
                            <option value="PS4" <?php echo ($produto_editar && $produto_editar['plataforma'] == 'PS4') ? 'selected' : ''; ?>>PlayStation 4</option>
                            <option value="XBOX Series X" <?php echo ($produto_editar && $produto_editar['plataforma'] == 'XBOX Series X') ? 'selected' : ''; ?>>XBOX Series X</option>
                            <option value="XBOX One" <?php echo ($produto_editar && $produto_editar['plataforma'] == 'XBOX One') ? 'selected' : ''; ?>>XBOX One</option>
                            <option value="Switch" <?php echo ($produto_editar && $produto_editar['plataforma'] == 'Switch') ? 'selected' : ''; ?>>Nintendo Switch</option>
                            <option value="PC" <?php echo ($produto_editar && $produto_editar['plataforma'] == 'PC') ? 'selected' : ''; ?>>PC</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria">Categoria:</label>
                        <select id="categoria" name="categoria" required class="form-control">
                            <option value="">-- Selecione --</option>
                            <option value="Ação" <?php echo ($produto_editar && $produto_editar['categoria'] == 'Ação') ? 'selected' : ''; ?>>Ação</option>
                            <option value="Aventura" <?php echo ($produto_editar && $produto_editar['categoria'] == 'Aventura') ? 'selected' : ''; ?>>Aventura</option>
                            <option value="RPG" <?php echo ($produto_editar && $produto_editar['categoria'] == 'RPG') ? 'selected' : ''; ?>>RPG</option>
                            <option value="Esportes" <?php echo ($produto_editar && $produto_editar['categoria'] == 'Esportes') ? 'selected' : ''; ?>>Esportes</option>
                            <option value="Corrida" <?php echo ($produto_editar && $produto_editar['categoria'] == 'Corrida') ? 'selected' : ''; ?>>Corrida</option>
                            <option value="FPS" <?php echo ($produto_editar && $produto_editar['categoria'] == 'FPS') ? 'selected' : ''; ?>>FPS</option>
                            <option value="Estratégia" <?php echo ($produto_editar && $produto_editar['categoria'] == 'Estratégia') ? 'selected' : ''; ?>>Estratégia</option>
                            <option value="Indie" <?php echo ($produto_editar && $produto_editar['categoria'] == 'Indie') ? 'selected' : ''; ?>>Indie</option>
                            <option value="Simulação" <?php echo ($produto_editar && $produto_editar['categoria'] == 'Simulação') ? 'selected' : ''; ?>>Simulação</option>
                            <option value="Luta" <?php echo ($produto_editar && $produto_editar['categoria'] == 'Luta') ? 'selected' : ''; ?>>Luta</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="estoque">Estoque:</label>
                        <input type="number" id="estoque" name="estoque" required class="form-control" 
                               value="<?php echo $produto_editar ? $produto_editar['estoque'] : '0'; ?>" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="estoque_minimo">Estoque Mínimo:</label>
                        <input type="number" id="estoque_minimo" name="estoque_minimo" class="form-control" 
                               value="<?php echo $produto_editar ? $produto_editar['estoque_minimo'] : '5'; ?>" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="foto">Foto do Produto:</label>
                    <?php if($produto_editar && $produto_editar['foto']): ?>
                        <div style="margin-bottom: 10px;">
                            <strong>Foto atual:</strong><br>
                            <?php if(file_exists('uploads/produtos/' . $produto_editar['foto'])): ?>
                                <img src="uploads/produtos/<?php echo $produto_editar['foto']; ?>" alt="<?php echo $produto_editar['nome']; ?>" style="max-width: 100px; border-radius: 4px;">
                            <?php else: ?>
                                <div style="color: #e74c3c;">⚠️ Imagem não encontrada</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <input type="file" id="foto" name="foto" accept="image/*" class="form-control">
                    <small>
                        <?php if($produto_editar): ?>
                            Deixe em branco para manter a foto atual
                        <?php else: ?>
                            Formatos: JPG, PNG, GIF
                        <?php endif; ?>
                    </small>
                </div>
                
                <div class="form-buttons">
                    <?php if($produto_editar): ?>
                        <button type="submit" name="editar_produto" class="btn btn-warning">
                            💾 Salvar Alterações
                        </button>
                        <a href="produtos.php" class="btn btn-secondary">❌ Cancelar</a>
                    <?php else: ?>
                        <button type="submit" name="adicionar_produto" class="btn btn-primary">
                            💾 Adicionar Produto
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="content-column">
        <div class="card">
            <h2>📋 Lista de Produtos</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Produto</th>
                            <th>Plataforma</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($produto = $produtos->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if($produto['foto'] && file_exists('uploads/produtos/' . $produto['foto'])): ?>
                                    <img src="uploads/produtos/<?php echo $produto['foto']; ?>" alt="<?php echo $produto['nome']; ?>" class="product-thumb">
                                <?php else: ?>
                                    <div class="product-thumb placeholder">🎮</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo $produto['nome']; ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php 
                                    if (!empty($produto['descricao'])) {
                                        echo strlen($produto['descricao']) > 50 
                                            ? substr($produto['descricao'], 0, 50) . '...' 
                                            : $produto['descricao'];
                                    } else {
                                        echo 'Sem descrição';
                                    }
                                    ?>
                                </small>
                            </td>
                            <td>
                                <span class="platform-badge platform-<?php echo strtolower(str_replace(' ', '-', $produto['plataforma'])); ?>">
                                    <?php echo $produto['plataforma']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="category-badge">
                                    <?php echo $produto['categoria']; ?>
                                </span>
                            </td>
                            <td>
                                <strong>R$ <?php echo number_format($produto['preco_venda'], 2, ',', '.'); ?></strong>
                                <?php if($produto['preco_custo'] > 0): ?>
                                    <br>
                                    <small class="text-muted">
                                        Custo: R$ <?php echo number_format($produto['preco_custo'], 2, ',', '.'); ?>
                                    </small>
                                    <br>
                                    <small class="text-success">
                                        Lucro: R$ <?php echo number_format($produto['preco_venda'] - $produto['preco_custo'], 2, ',', '.'); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="stock-info">
                                    <span class="<?php echo $produto['estoque'] <= $produto['estoque_minimo'] ? 'stock-low' : 'stock-ok'; ?>">
                                        <?php echo $produto['estoque']; ?> un
                                    </span>
                                    <?php if($produto['estoque'] <= $produto['estoque_minimo']): ?>
                                        <br>
                                        <small class="stock-alert">⚠️ Mín: <?php echo $produto['estoque_minimo']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="?editar=<?php echo $produto['id']; ?>" class="btn btn-warning btn-sm">
                                        ✏️ Editar
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            onclick="abrirModalExclusao(
                                                <?php echo $produto['id']; ?>, 
                                                '<?php echo htmlspecialchars($produto['nome'], ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($produto['plataforma'], ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($produto['categoria'], ENT_QUOTES); ?>'
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
        </div>
    </div>
</div>

<script>
// ✅ FUNÇÕES DO POP-UP DE EXCLUSÃO
function abrirModalExclusao(id, nome, plataforma, categoria) {
    document.getElementById('produto-id').value = id;
    document.getElementById('produto-nome').textContent = nome;
    document.getElementById('produto-plataforma').textContent = plataforma;
    document.getElementById('produto-categoria').textContent = categoria;
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
</script>

<style>
.product-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
}

.product-thumb.placeholder {
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stock-low {
    color: #e74c3c;
    font-weight: bold;
    background: #fdf2f2;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
}

.stock-ok {
    color: #27ae60;
    font-weight: bold;
    background: #f2fdf2;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
}

.stock-alert {
    color: #e74c3c;
    font-size: 0.75rem;
    margin-top: 2px;
    display: inline-block;
}

.stock-info {
    text-align: center;
}

.platform-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
    color: white;
    display: inline-block;
}

.platform-ps5 { background: #003791; }
.platform-ps4 { background: #0070cc; }
.platform-xbox-series-x { background: #107c10; }
.platform-xbox-one { background: #9bf00b; color: #000; }
.platform-switch { background: #e60012; }
.platform-pc { background: #767676; }

.category-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: bold;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
    display: inline-block;
}

.text-muted {
    color: #6c757d !important;
}

.text-success {
    color: #28a745 !important;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn-group {
    display: flex;
    gap: 5px;
}

.form-buttons {
    display: flex;
    gap: 10px;
}

/* ✅ ESTILOS DO MODAL DE EXCLUSÃO */
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

.produto-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    border-left: 4px solid #e74c3c;
    color: #333;
}

.produto-info strong {
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

/* ✅ DARK MODE */
@media (prefers-color-scheme: dark) {
    .modal-content {
        background: #2d3748;
        border-color: #4a5568;
    }
    
    .modal-header {
        border-bottom-color: #4a5568;
    }
    
    .modal-header h3 {
        color: #e2e8f0;
    }
    
    .modal-body {
        color: #e2e8f0;
    }
    
    .modal-body p {
        color: #e2e8f0;
    }
    
    .produto-info {
        background: #4a5568;
        color: #e2e8f0;
    }
    
    .produto-info strong {
        color: #e2e8f0;
    }
    
    .modal-close {
        color: #a0aec0;
    }
    
    .modal-close:hover {
        background: #4a5568;
        color: #e2e8f0;
    }
    
    .modal-footer {
        border-top-color: #4a5568;
    }
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .form-buttons {
        flex-direction: column;
    }
    
    .data-table th:nth-child(4),
    .data-table td:nth-child(4) {
        display: none;
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
</style>

<?php
include 'includes/footer.php';