<?php
include 'includes/config.php';
verificarAuth();

// ✅ DADOS REAIS PARA O DONO
$sql_vendas_hoje = "SELECT COUNT(*) as total, COALESCE(SUM(total_venda), 0) as valor 
                    FROM vendas 
                    WHERE DATE(data_venda) = CURDATE()";
$result = $conn->query($sql_vendas_hoje);
$vendas_hoje = $result->fetch_assoc();

$sql_vendas_mes = "SELECT COUNT(*) as total, COALESCE(SUM(total_venda), 0) as valor 
                   FROM vendas 
                   WHERE MONTH(data_venda) = MONTH(CURDATE()) 
                   AND YEAR(data_venda) = YEAR(CURDATE())";
$result = $conn->query($sql_vendas_mes);
$vendas_mes = $result->fetch_assoc();

$sql_estoque_baixo = "SELECT COUNT(*) as total FROM produtos WHERE estoque <= estoque_minimo AND ativo = 1";
$result = $conn->query($sql_estoque_baixo);
$estoque_baixo = $result->fetch_assoc();

$sql_total_clientes = "SELECT COUNT(*) as total FROM clientes";
$result = $conn->query($sql_total_clientes);
$total_clientes = $result->fetch_assoc();

// Últimas vendas
$sql_ultimas_vendas = "SELECT v.*, c.nome as cliente_nome 
                       FROM vendas v 
                       LEFT JOIN clientes c ON v.cliente_id = c.id 
                       ORDER BY v.data_venda DESC 
                       LIMIT 5";
$ultimas_vendas = $conn->query($sql_ultimas_vendas);

// Produtos mais vendidos
$sql_produtos_vendidos = "SELECT p.nome, SUM(vi.quantidade) as total_vendido 
                          FROM venda_itens vi 
                          JOIN produtos p ON vi.produto_id = p.id 
                          GROUP BY p.id, p.nome 
                          ORDER BY total_vendido DESC 
                          LIMIT 5";
$produtos_vendidos = $conn->query($sql_produtos_vendidos);

$produtos_nomes = [];
$produtos_quantidades = [];
while($produto = $produtos_vendidos->fetch_assoc()) {
    $produtos_nomes[] = $produto['nome'];
    $produtos_quantidades[] = $produto['total_vendido'];
}

$page_title = "Dashboard - GameStore Manager";
$load_charts = true;
include 'includes/header.php';
?>

<div class="dashboard-header">
    <h1>🏪 Dashboard da Loja</h1>
    <p>Visão geral do seu negócio</p>
    
    <!-- ✅ BOTÃO ADMINISTRATIVO FUNCIONAL - PARA TODOS OS USUÁRIOS LOGADOS -->
    <div style="margin-top: 15px;">
        <a href="admin_usuarios.php" class="btn-admin-dashboard">
            <span>🔧</span>
            Gerenciar Usuários
        </a>
    </div>
</div>

<!-- ✅ ESTATÍSTICAS PRÁTICAS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <h3>Caixa Hoje</h3>
            <p class="stat-number">R$ <?php echo number_format($vendas_hoje['valor'], 2, ',', '.'); ?></p>
            <p class="stat-value"><?php echo $vendas_hoje['total']; ?> vendas</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-info">
            <h3>Faturamento Mês</h3>
            <p class="stat-number">R$ <?php echo number_format($vendas_mes['valor'], 2, ',', '.'); ?></p>
            <p class="stat-value"><?php echo $vendas_mes['total']; ?> vendas</p>
        </div>
    </div>
    
    <div class="stat-card <?php echo $estoque_baixo['total'] > 0 ? 'alert-warning' : ''; ?>">
        <div class="stat-icon">⚠️</div>
        <div class="stat-info">
            <h3>Reposição</h3>
            <p class="stat-number"><?php echo $estoque_baixo['total']; ?></p>
            <p class="stat-value">Produtos em falta</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-info">
            <h3>Clientes</h3>
            <p class="stat-number"><?php echo $total_clientes['total']; ?></p>
            <p class="stat-value">Cadastrados</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-column">
        <div class="card">
            <h2>📊 Produtos Mais Vendidos</h2>
            <div class="chart-container">
                <canvas id="produtosChart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="dashboard-column">
        <div class="card">
            <h2>🕒 Últimas Vendas</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($venda = $ultimas_vendas->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $venda['id']; ?></td>
                            <td><?php echo $venda['cliente_nome'] ?: 'Cliente não cadastrado'; ?></td>
                            <td>R$ <?php echo number_format($venda['total_venda'], 2, ',', '.'); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($venda['data_venda'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* ✅ ESTILO DO BOTÃO ADMIN NO DASHBOARD */
.btn-admin-dashboard {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #ff7eb3, #ff758c);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(255, 117, 140, 0.3);
}

.btn-admin-dashboard:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 117, 140, 0.4);
    background: linear-gradient(135deg, #ff758c, #ff6b6b);
}

/* ✅ ESTILOS EXISTENTES DO DASHBOARD */
.dashboard-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
}

.dashboard-header h1 {
    margin: 0;
    font-size: 2.5rem;
}

.dashboard-header p {
    margin: 10px 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--border-color);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card.alert-warning {
    background: #fff3cd;
    border-color: #ffeaa7;
}

.stat-icon {
    font-size: 2.5rem;
}

.stat-info h3 {
    margin: 0 0 5px 0;
    color: var(--text-color);
}

.stat-number {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 0;
    color: #2c3e50;
}

.stat-value {
    color: var(--text-muted);
    margin: 0;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.dashboard-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.card {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
}

.card h2 {
    margin: 0 0 1rem 0;
    color: var(--text-color);
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0.5rem;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background: var(--bg-color);
    font-weight: 600;
    color: var(--text-color);
}

@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-header h1 {
        font-size: 2rem;
    }
}
</style>

<script>
// ✅ GRÁFICO DE PRODUTOS MAIS VENDIDOS
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('produtosChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($produtos_nomes); ?>,
            datasets: [{
                label: 'Quantidade Vendida',
                data: <?php echo json_encode($produtos_quantidades); ?>,
                backgroundColor: [
                    '#3498db',
                    '#2ecc71', 
                    '#e74c3c',
                    '#f39c12',
                    '#9b59b6'
                ],
                borderColor: [
                    '#2980b9',
                    '#27ae60',
                    '#c0392b',
                    '#d35400',
                    '#8e44ad'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php
include 'includes/footer.php';
?>