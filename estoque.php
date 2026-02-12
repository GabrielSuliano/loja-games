<?php
include 'includes/config.php';
verificarAuth();

// ✅ BUSCAR PRODUTOS COM ESTOQUE BAIXO
$sql_estoque_baixo = "SELECT * FROM produtos WHERE estoque <= estoque_minimo AND ativo = 1 ORDER BY estoque ASC";
$estoque_baixo = $conn->query($sql_estoque_baixo);

// ✅ BUSCAR TODOS OS PRODUTOS
$sql_produtos = "SELECT * FROM produtos ORDER BY estoque ASC, nome ASC";
$produtos = $conn->query($sql_produtos);

// ✅ ESTATÍSTICAS
$sql_stats = "SELECT 
    COUNT(*) as total_produtos,
    SUM(CASE WHEN estoque = 0 THEN 1 ELSE 0 END) as esgotados,
    SUM(CASE WHEN estoque <= estoque_minimo AND estoque > 0 THEN 1 ELSE 0 END) as estoque_baixo,
    SUM(estoque) as total_estoque,
    SUM(estoque * preco_venda) as valor_estoque
    FROM produtos WHERE ativo = 1";
$stats = $conn->query($sql_stats)->fetch_assoc();

// ✅ EXPORTAR ESTOQUE PARA EXCEL
if (isset($_GET['exportar_excel'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="estoque_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='8'>RELATÓRIO DE ESTOQUE - GAMESTORE</th></tr>";
    echo "<tr><th colspan='8'>Gerado em: " . date('d/m/Y H:i') . "</th></tr>";
    echo "<tr><th>Produto</th><th>Plataforma</th><th>Categoria</th><th>Preço Venda</th><th>Estoque Atual</th><th>Estoque Mínimo</th><th>Status</th><th>Valor em Estoque</th></tr>";
    
    $sql_excel = "SELECT * FROM produtos ORDER BY estoque ASC, nome ASC";
    $result_excel = $conn->query($sql_excel);
    
    while($produto = $result_excel->fetch_assoc()) {
        $status = '';
        $valor_estoque = $produto['estoque'] * $produto['preco_venda'];
        
        if ($produto['estoque'] == 0) {
            $status = 'ESGOTADO';
        } elseif ($produto['estoque'] <= $produto['estoque_minimo']) {
            $status = 'ESTOQUE BAIXO';
        } else {
            $status = 'NORMAL';
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($produto['nome']) . "</td>";
        echo "<td>" . htmlspecialchars($produto['plataforma']) . "</td>";
        echo "<td>" . htmlspecialchars($produto['categoria']) . "</td>";
        echo "<td>R$ " . number_format($produto['preco_venda'], 2, ',', '.') . "</td>";
        echo "<td>" . $produto['estoque'] . "</td>";
        echo "<td>" . $produto['estoque_minimo'] . "</td>";
        echo "<td>" . $status . "</td>";
        echo "<td>R$ " . number_format($valor_estoque, 2, ',', '.') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

$page_title = "Controle de Estoque - GameStore Manager";
include 'includes/header.php';
?>

<div class="page-header">
    <h1>📦 Controle de Estoque</h1>
    <p>Acompanhe e gerencie o estoque da loja</p>
</div>

<!-- ✅ BOTÃO EXPORTAR EXCEL -->
<div class="export-buttons">
    <a href="?exportar_excel=1" class="btn btn-success">📊 Exportar Excel</a>
</div>

<!-- ✅ CARTÕES DE ESTATÍSTICAS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-info">
            <h3>Total Produtos</h3>
            <p class="stat-number"><?php echo $stats['total_produtos']; ?></p>
            <p class="stat-value">Cadastrados</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <h3>Valor em Estoque</h3>
            <p class="stat-number">R$ <?php echo number_format($stats['valor_estoque'], 2, ',', '.'); ?></p>
            <p class="stat-value">Total</p>
        </div>
    </div>
    
    <div class="stat-card <?php echo $stats['estoque_baixo'] > 0 ? 'alert-warning' : ''; ?>">
        <div class="stat-icon">⚠️</div>
        <div class="stat-info">
            <h3>Estoque Baixo</h3>
            <p class="stat-number"><?php echo $stats['estoque_baixo']; ?></p>
            <p class="stat-value">Precisa repor</p>
        </div>
    </div>
    
    <div class="stat-card <?php echo $stats['esgotados'] > 0 ? 'alert-danger' : ''; ?>">
        <div class="stat-icon">❌</div>
        <div class="stat-info">
            <h3>Esgotados</h3>
            <p class="stat-number"><?php echo $stats['esgotados']; ?></p>
            <p class="stat-value">Sem estoque</p>
        </div>
    </div>
</div>

<!-- ✅ ALERTAS DE ESTOQUE BAIXO -->
<?php if($estoque_baixo->num_rows > 0): ?>
<div class="alert alert-warning">
    <h3>⚠️ ALERTA: Produtos com Estoque Baixo</h3>
    <p>Os seguintes produtos estão com estoque próximo do mínimo:</p>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Plataforma</th>
                    <th>Estoque Atual</th>
                    <th>Estoque Mínimo</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php while($produto = $estoque_baixo->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?php echo $produto['nome']; ?></strong>
                        <br><small><?php echo $produto['categoria']; ?></small>
                    </td>
                    <td><?php echo $produto['plataforma']; ?></td>
                    <td class="stock-low-fixed"><?php echo $produto['estoque']; ?></td>
                    <td><?php echo $produto['estoque_minimo']; ?></td>
                    <td>
                        <?php if($produto['estoque'] == 0): ?>
                            <span class="badge badge-danger">❌ ESGOTADO</span>
                        <?php else: ?>
                            <span class="badge badge-warning">⚠️ BAIXO ESTOQUE</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="produtos.php" class="btn btn-warning btn-sm">🔄 Repor Estoque</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ✅ LISTA COMPLETA DE ESTOQUE -->
<div class="card">
    <h2>📋 Estoque Completo</h2>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Plataforma</th>
                    <th>Preço Venda</th>
                    <th>Estoque Atual</th>
                    <th>Estoque Mínimo</th>
                    <th>Status</th>
                    <th>Valor em Estoque</th>
                </tr>
            </thead>
            <tbody>
                <?php while($produto = $produtos->fetch_assoc()): 
                    $valor_estoque = $produto['estoque'] * $produto['preco_venda'];
                ?>
                <tr>
                    <td>
                        <strong><?php echo $produto['nome']; ?></strong>
                        <br><small><?php echo $produto['categoria']; ?></small>
                    </td>
                    <td><?php echo $produto['plataforma']; ?></td>
                    <td>R$ <?php echo number_format($produto['preco_venda'], 2, ',', '.'); ?></td>
                    <td class="<?php echo $produto['estoque'] <= $produto['estoque_minimo'] ? 'stock-low-fixed' : 'stock-ok-fixed'; ?>">
                        <?php echo $produto['estoque']; ?>
                    </td>
                    <td><?php echo $produto['estoque_minimo']; ?></td>
                    <td>
                        <?php if($produto['estoque'] == 0): ?>
                            <span class="badge badge-danger">❌ Esgotado</span>
                        <?php elseif($produto['estoque'] <= $produto['estoque_minimo']): ?>
                            <span class="badge badge-warning">⚠️ Baixo</span>
                        <?php else: ?>
                            <span class="badge badge-success">✅ Normal</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong>R$ <?php echo number_format($valor_estoque, 2, ',', '.'); ?></strong>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.stock-low-fixed {
    color: #e74c3c !important;
    font-weight: bold;
    background: rgba(231, 76, 60, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid #e74c3c;
}

.stock-ok-fixed {
    color: #27ae60 !important;
    font-weight: bold;
    background: rgba(39, 174, 96, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid #27ae60;
}

.alert-warning .stat-card {
    border: 2px solid #f39c12;
}

.alert-danger .stat-card {
    border: 2px solid #e74c3c;
}

.export-buttons {
    margin: 1rem 0;
}

.badge-danger {
    background: #e74c3c;
    color: white !important;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    border: none;
    font-weight: bold;
}

.badge-warning {
    background: #f39c12;
    color: white !important;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    border: none;
    font-weight: bold;
}

.badge-success {
    background: #27ae60;
    color: white !important;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    border: none;
    font-weight: bold;
}

/* ✅ GARANTIR QUE O TEXTO SEJA VISÍVEL */
.data-table td {
    color: inherit !important;
}
</style>

<?php
include 'includes/footer.php';