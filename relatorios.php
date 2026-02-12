<?php
// ======================================================================
// CONFIGURAÇÕES INICIAIS E INCLUSÃO DE ARQUIVOS
// ======================================================================

// ATENÇÃO: Verifique se 'config.php' e 'verificarAuth()' existem e funcionam
// Assegure-se de que não há NENHUM espaço em branco ou linha antes desta tag <?php
include 'includes/config.php'; 
// A função verificarAuth() deve redirecionar o usuário se não estiver logado
// Se verificarAuth() fizer qualquer output antes de ser chamada, o Excel/PDF falhará.
// verificarAuth(); 

// Variáveis Globais para os filtros de data
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');


// ======================================================================
// 1. GERAÇÃO DE RELATÓRIO EXCEL (.xls) - COM DETALHES POR DIA
// ======================================================================
if (isset($_GET['exportar_excel'])) {
    
    // CORREÇÃO CRÍTICA: LIMPAR QUALQUER SAÍDA ANTERIOR
    if (ob_get_contents()) {
        ob_end_clean();
    }
    
    $data_inicio_excel = $_GET['data_inicio'];
    $data_fim_excel = $_GET['data_fim'];
    
    // HEADERS CRÍTICOS PARA FORÇAR O DOWNLOAD
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_vendas_detalhado_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0'); 
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // BOM para UTF-8
    
    ob_start();
    
    echo "<html><head><meta charset='UTF-8'><style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
        th { background-color: #f2f2f2; font-weight: bold; padding: 10px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .total { font-weight: bold; background-color: #e8f4fd; }
        .header { background: #2c3e50; color: white; padding: 15px; }
        .dia-header { background: #34495e; color: white; font-weight: bold; }
        .venda-header { background: #ecf0f1; }
    </style></head><body>";
    
    // CABEÇALHO
    echo "<div class='header'><h2>RELATÓRIO DE VENDAS DETALHADO - GAMESTORE MANAGER</h2>";
    echo "<p><strong>Gerado em:</strong> " . date('d/m/Y H:i:s') . "</p>";
    echo "<p><strong>Período:</strong> " . date('d/m/Y', strtotime($data_inicio_excel)) . " à " . date('d/m/Y', strtotime($data_fim_excel)) . "</p></div><br>";
    
    // CONSULTA PRINCIPAL ORDENADA POR DATA
    $sql_excel = "SELECT v.id, DATE(v.data_venda) as data_dia, v.data_venda, v.total_venda, v.forma_pagamento, v.parcelas, v.observacoes,
                          c.nome as cliente_nome, c.telefone,
                          p.nome as produto_nome, p.plataforma, 
                          vi.quantidade, vi.preco_unitario, vi.subtotal
                   FROM vendas v
                   LEFT JOIN clientes c ON v.cliente_id = c.id
                   JOIN venda_itens vi ON v.id = vi.venda_id
                   JOIN produtos p ON vi.produto_id = p.id
                   WHERE DATE(v.data_venda) BETWEEN ? AND ?
                   ORDER BY v.data_venda DESC, v.id";
    
    $stmt_excel = $conn->prepare($sql_excel);
    $stmt_excel->bind_param("ss", $data_inicio_excel, $data_fim_excel);
    $stmt_excel->execute();
    $result_excel = $stmt_excel->get_result();
    
    $total_geral = 0;
    $total_vendas_count = 0;
    $dia_atual = null;
    $total_dia = 0;
    $vendas_por_dia = [];
    
    if ($result_excel->num_rows > 0) {
        
        while($linha = $result_excel->fetch_assoc()) {
            
            // NOVO: Agrupar por dia
            if ($dia_atual != $linha['data_dia']) {
                
                // Se não é o primeiro dia, mostra o total do dia anterior
                if ($dia_atual !== null) {
                    echo "<tr class='total'><td colspan='11' style='text-align: right;'>Total do Dia " . date('d/m/Y', strtotime($dia_atual)) . ":</td><td><strong>R$ " . number_format($total_dia, 2, ',', '.') . "</strong></td></tr>";
                    echo "<tr><td colspan='12' style='height: 20px; background: #f8f9fa;'></td></tr>"; // Espaço entre dias
                }
                
                // Inicia novo dia
                $dia_atual = $linha['data_dia'];
                $total_dia = 0;
                
                // CABEÇALHO DO DIA
                echo "<tr class='dia-header'><td colspan='12' style='text-align: center; font-size: 14px;'>";
                echo "📅 VENDAS DO DIA: " . date('d/m/Y (l)', strtotime($dia_atual));
                echo "</td></tr>";
                
                // CABEÇALHO DA TABELA
                echo "<tr><th>ID Venda</th><th>Hora</th><th>Cliente</th><th>Telefone</th>
                      <th>Produto</th><th>Plataforma</th><th>Qtd</th><th>Valor Unit.</th>
                      <th>Subtotal</th><th>Pagamento</th><th>Parcelas</th><th>Observações</th></tr>";
            }
            
            $venda_atual = $linha['id'];
            
            echo "<tr>";
            
            // DETALHES DA VENDA (sempre na primeira linha de cada venda)
            echo "<td><strong>#" . $linha['id'] . "</strong></td>";
            echo "<td>" . date('H:i', strtotime($linha['data_venda'])) . "</td>";
            echo "<td><strong>" . ($linha['cliente_nome'] ? htmlspecialchars($linha['cliente_nome']) : 'Venda Avulsa') . "</strong></td>";
            echo "<td>" . ($linha['telefone'] ?: '-') . "</td>";
            
            // DETALHES DO ITEM
            echo "<td>" . htmlspecialchars($linha['produto_nome']) . "</td>";
            echo "<td>" . htmlspecialchars($linha['plataforma']) . "</td>";
            echo "<td>" . $linha['quantidade'] . "</td>";
            echo "<td>" . number_format($linha['preco_unitario'], 2, ',', '.') . "</td>";
            echo "<td>" . number_format($linha['subtotal'], 2, ',', '.') . "</td>";
            
            // DETALHES DE PAGAMENTO (apenas primeira linha da venda)
            $forma_pagamento = ucfirst(str_replace('_', ' ', $linha['forma_pagamento']));
            $parcelas_info = '';
            if ($linha['forma_pagamento'] === 'cartao_credito' && $linha['parcelas'] > 1) {
                $valor_parcela = $linha['total_venda'] / $linha['parcelas'];
                $parcelas_info = " (" . $linha['parcelas'] . "x de R$ " . number_format($valor_parcela, 2, ',', '.') . ")";
            }
            
            echo "<td>" . $forma_pagamento . $parcelas_info . "</td>";
            echo "<td>" . ($linha['parcelas'] > 1 ? $linha['parcelas'] . 'x' : 'À vista') . "</td>";
            echo "<td>" . ($linha['observacoes'] ? htmlspecialchars($linha['observacoes']) : '-') . "</td>";
            
            echo "</tr>";
            
            $total_geral += $linha['subtotal'];
            $total_dia += $linha['subtotal'];
            $total_vendas_count++;
            
            // Armazena totais por dia para o resumo
            if (!isset($vendas_por_dia[$dia_atual])) {
                $vendas_por_dia[$dia_atual] = 0;
            }
            $vendas_por_dia[$dia_atual] += $linha['subtotal'];
        }
        
        // Total do último dia
        if ($dia_atual !== null) {
            echo "<tr class='total'><td colspan='11' style='text-align: right;'>Total do Dia " . date('d/m/Y', strtotime($dia_atual)) . ":</td><td><strong>R$ " . number_format($total_dia, 2, ',', '.') . "</strong></td></tr>";
        }
        
    } else {
        echo "<p>Nenhuma venda encontrada no período</p>";
    }
    
    $stmt_excel->close();
    
    // RESUMO GERAL COM DETALHES POR DIA
    echo "<br><br><h3>📊 RESUMO GERAL DETALHADO</h3><table>";
    echo "<tr class='total'><td>Total de Vendas:</td><td><strong>" . $total_vendas_count . "</strong></td></tr>";
    
    // Total de Itens
    $sql_total_itens = "SELECT SUM(vi.quantidade) as total_itens FROM venda_itens vi 
                        JOIN vendas v ON vi.venda_id = v.id WHERE DATE(v.data_venda) BETWEEN ? AND ?";
    $stmt_itens = $conn->prepare($sql_total_itens);
    $stmt_itens->bind_param("ss", $data_inicio_excel, $data_fim_excel);
    $stmt_itens->execute();
    $result_itens = $stmt_itens->get_result();
    $total_itens = $result_itens->fetch_assoc();
    $stmt_itens->close();
    
    echo "<tr class='total'><td>Total de Itens Vendidos:</td><td><strong>" . ($total_itens['total_itens'] ?: '0') . "</strong></td></tr>";
    echo "<tr class='total'><td>Faturamento Total:</td><td><strong>R$ " . number_format($total_geral, 2, ',', '.') . "</strong></td></tr>";
    
    $ticket_medio = $total_vendas_count > 0 ? $total_geral / $total_vendas_count : 0;
    echo "<tr class='total'><td>Ticket Médio:</td><td><strong>R$ " . number_format($ticket_medio, 2, ',', '.') . "</strong></td></tr>";
    
    // RESUMO POR DIA (NOVO)
    echo "<tr class='total'><td colspan='2' style='background: #d4edda; text-align: center;'><strong>📅 FATURAMENTO POR DIA</strong></td></tr>";
    foreach ($vendas_por_dia as $dia => $valor) {
        echo "<tr><td>" . date('d/m/Y', strtotime($dia)) . ":</td><td>R$ " . number_format($valor, 2, ',', '.') . "</td></tr>";
    }
    
    echo "</table></body></html>";
    
    ob_end_flush();
    exit;
}


// ======================================================================
// 2. GERAÇÃO DE RELATÓRIO PDF - COM DETALHES POR DIA
// ======================================================================
if (isset($_GET['gerar_pdf'])) {
    
    require('fpdf/fpdf.php');
    
    $data_inicio_pdf = $_GET['data_inicio'];
    $data_fim_pdf = $_GET['data_fim'];
    
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, utf8_decode('RELATÓRIO DE VENDAS DETALHADO - GAMESTORE MANAGER'), 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            global $data_inicio_pdf, $data_fim_pdf; 
            $this->Cell(0, 5, utf8_decode('Período: ') . date('d/m/Y', strtotime($data_inicio_pdf)) . utf8_decode(' a ') . date('d/m/Y', strtotime($data_fim_pdf)), 0, 1, 'C');
            $this->Cell(0, 5, utf8_decode('Gerado em: ') . date('d/m/Y H:i:s'), 0, 1, 'C');
            $this->Ln(5);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
        
        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(200, 220, 255);
            $this->Cell(0, 6, utf8_decode($title), 0, 1, 'L', true);
            $this->Ln(2);
        }
        
        function ItemLine($item, $value) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(80, 6, utf8_decode($item), 0, 0);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 6, utf8_decode($value), 0, 1);
        }
        
        // NOVA FUNÇÃO: Cabeçalho de dia
        function DiaHeader($data) {
            $this->SetFont('Arial', 'B', 11);
            $this->SetFillColor(144, 238, 144); // Verde claro
            $this->Cell(0, 8, utf8_decode('📅 Vendas do Dia: ') . date('d/m/Y', strtotime($data)), 0, 1, 'L', true);
            $this->Ln(2);
        }
    }
    
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // DADOS DAS VENDAS POR DIA (NOVO)
    $pdf->ChapterTitle('VENDAS DETALHADAS POR DIA');
    
    global $conn;
    
    // Consulta para obter vendas agrupadas por dia
    $sql_vendas_dia_pdf = "SELECT DATE(v.data_venda) as data_dia, 
                                  COUNT(DISTINCT v.id) as total_vendas_dia,
                                  SUM(v.total_venda) as total_dia,
                                  GROUP_CONCAT(DISTINCT v.id) as ids_vendas
                           FROM vendas v
                           WHERE DATE(v.data_venda) BETWEEN ? AND ?
                           GROUP BY DATE(v.data_venda)
                           ORDER BY data_dia DESC";
    
    $stmt_dias = $conn->prepare($sql_vendas_dia_pdf);
    $stmt_dias->bind_param("ss", $data_inicio_pdf, $data_fim_pdf);
    $stmt_dias->execute();
    $result_dias = $stmt_dias->get_result();
    
    if ($result_dias->num_rows > 0) {
        while($dia = $result_dias->fetch_assoc()) {
            
            $pdf->DiaHeader($dia['data_dia']);
            
            // Detalhes das vendas do dia
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, utf8_decode('Total do Dia: R$ ') . number_format($dia['total_dia'], 2, ',', '.'), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Vendas Realizadas: ') . $dia['total_vendas_dia'], 0, 1);
            $pdf->Ln(2);
            
            // Consulta detalhada das vendas do dia específico
            $sql_vendas_detalhes = "SELECT v.id, v.data_venda, v.total_venda, v.forma_pagamento, v.parcelas,
                                           c.nome as cliente_nome,
                                           COUNT(vi.id) as total_itens
                                    FROM vendas v
                                    LEFT JOIN clientes c ON v.cliente_id = c.id
                                    JOIN venda_itens vi ON v.id = vi.venda_id
                                    WHERE DATE(v.data_venda) = ?
                                    GROUP BY v.id, v.data_venda, v.total_venda, v.forma_pagamento, v.parcelas, c.nome
                                    ORDER BY v.data_venda DESC";
            
            $stmt_detalhes = $conn->prepare($sql_vendas_detalhes);
            $stmt_detalhes->bind_param("s", $dia['data_dia']);
            $stmt_detalhes->execute();
            $result_detalhes = $stmt_detalhes->get_result();
            
            if ($result_detalhes->num_rows > 0) {
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(20, 6, 'Hora', 1);
                $pdf->Cell(25, 6, 'Venda #', 1);
                $pdf->Cell(50, 6, 'Cliente', 1);
                $pdf->Cell(40, 6, 'Pagamento', 1);
                $pdf->Cell(20, 6, 'Itens', 1);
                $pdf->Cell(35, 6, 'Total', 1, 1);
                
                $pdf->SetFont('Arial', '', 8);
                while($venda = $result_detalhes->fetch_assoc()) {
                    $forma_pagamento = ucfirst(str_replace('_', ' ', $venda['forma_pagamento']));
                    if ($venda['parcelas'] > 1) {
                        $forma_pagamento .= " (" . $venda['parcelas'] . "x)";
                    }
                    
                    $pdf->Cell(20, 6, date('H:i', strtotime($venda['data_venda'])), 1);
                    $pdf->Cell(25, 6, '#' . $venda['id'], 1);
                    $pdf->Cell(50, 6, utf8_decode(substr($venda['cliente_nome'] ?: 'Venda Avulsa', 0, 20)), 1);
                    $pdf->Cell(40, 6, utf8_decode($forma_pagamento), 1);
                    $pdf->Cell(20, 6, $venda['total_itens'], 1);
                    $pdf->Cell(35, 6, 'R$ ' . number_format($venda['total_venda'], 2, ',', '.'), 1, 1);
                }
            }
            $stmt_detalhes->close();
            
            $pdf->Ln(8);
            
            // Verifica se precisa de nova página
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
            }
        }
    } else {
        $pdf->Cell(0, 10, utf8_decode('Nenhuma venda encontrada no período'), 0, 1);
    }
    $stmt_dias->close();
    
    $pdf->AddPage();
    
    // RESUMO ESTATÍSTICAS
    $pdf->ChapterTitle('RESUMO ESTATÍSTICO');
    
    $sql_total_vendas_pdf = "SELECT COUNT(*) as total_vendas, COALESCE(SUM(total_venda), 0) as total_valor,
                                     AVG(total_venda) as ticket_medio
                              FROM vendas WHERE DATE(data_venda) BETWEEN ? AND ?";
    $stmt_pdf = $conn->prepare($sql_total_vendas_pdf);
    $stmt_pdf->bind_param("ss", $data_inicio_pdf, $data_fim_pdf);
    $stmt_pdf->execute();
    $totais_pdf = $stmt_pdf->get_result()->fetch_assoc();
    $stmt_pdf->close();
    
    $pdf->ItemLine('Total de Vendas:', $totais_pdf['total_vendas']);
    $pdf->ItemLine('Faturamento Total:', 'R$ ' . number_format($totais_pdf['total_valor'], 2, ',', '.'));
    $pdf->ItemLine('Ticket Médio:', 'R$ ' . number_format($totais_pdf['ticket_medio'], 2, ',', '.'));
    
    $sql_total_itens_pdf = "SELECT SUM(quantidade) as total FROM venda_itens vi 
                            JOIN vendas v ON vi.venda_id = v.id WHERE DATE(v.data_venda) BETWEEN ? AND ?";
    $stmt_itens_pdf = $conn->prepare($sql_total_itens_pdf);
    $stmt_itens_pdf->bind_param("ss", $data_inicio_pdf, $data_fim_pdf);
    $stmt_itens_pdf->execute();
    $total_itens_pdf = $stmt_itens_pdf->get_result()->fetch_assoc();
    $stmt_itens_pdf->close();
    $pdf->ItemLine('Itens Vendidos:', $total_itens_pdf['total'] ?: '0');
    
    $pdf->Ln(10);
    
    // FORMAS DE PAGAMENTO
    $pdf->ChapterTitle('FORMAS DE PAGAMENTO');
    
    $sql_pagamentos_pdf = "SELECT forma_pagamento, parcelas, COUNT(*) as total_vendas, SUM(total_venda) as total_valor
                            FROM vendas 
                            WHERE DATE(data_venda) BETWEEN ? AND ?
                            GROUP BY forma_pagamento, parcelas ORDER BY forma_pagamento, parcelas";
    $stmt_pagamentos_pdf = $conn->prepare($sql_pagamentos_pdf);
    $stmt_pagamentos_pdf->bind_param("ss", $data_inicio_pdf, $data_fim_pdf);
    $stmt_pagamentos_pdf->execute();
    $result_pagamentos_pdf = $stmt_pagamentos_pdf->get_result();
    
    if ($result_pagamentos_pdf->num_rows > 0) {
        while($pagamento = $result_pagamentos_pdf->fetch_assoc()) {
            $forma = ucfirst(str_replace('_', ' ', $pagamento['forma_pagamento']));
            $parcelas_info = $pagamento['parcelas'] > 1 ? " ({$pagamento['parcelas']}x)" : " (A vista)";
            $valor = $pagamento['total_vendas'] . utf8_decode(' vendas - R$ ') . number_format($pagamento['total_valor'], 2, ',', '.');
            $pdf->ItemLine('- ' . $forma . $parcelas_info, $valor);
        }
    } else {
        $pdf->ItemLine('-', utf8_decode('Nenhum dado de pagamento encontrado'));
    }
    $stmt_pagamentos_pdf->close();
    
    $pdf->Ln(10);
    
    // MELHORES CLIENTES
    $pdf->ChapterTitle('MELHORES CLIENTES');
    
    $sql_melhores_pdf = "SELECT c.nome, COUNT(v.id) as total_compras, SUM(v.total_venda) as total_gasto
                            FROM clientes c JOIN vendas v ON c.id = v.cliente_id 
                            WHERE DATE(v.data_venda) BETWEEN ? AND ?
                            GROUP BY c.id, c.nome ORDER BY total_gasto DESC LIMIT 5";
    $stmt_melhores_pdf = $conn->prepare($sql_melhores_pdf);
    $stmt_melhores_pdf->bind_param("ss", $data_inicio_pdf, $data_fim_pdf);
    $stmt_melhores_pdf->execute();
    $result_melhores_pdf = $stmt_melhores_pdf->get_result();
    
    if ($result_melhores_pdf->num_rows > 0) {
        $count = 1;
        while($cliente = $result_melhores_pdf->fetch_assoc()) {
            $valor = $cliente['total_compras'] . utf8_decode(' compras - R$ ') . number_format($cliente['total_gasto'], 2, ',', '.');
            $pdf->ItemLine($count . '. ' . utf8_decode($cliente['nome']), $valor);
            $count++;
        }
    } else {
        $pdf->ItemLine('-', utf8_decode('Nenhum cliente encontrado'));
    }
    $stmt_melhores_pdf->close();
    
    $pdf->Ln(10);
    
    // PRODUTOS MAIS VENDIDOS
    $pdf->ChapterTitle('PRODUTOS MAIS VENDIDOS');
    
    $sql_produtos_pdf = "SELECT p.nome, SUM(vi.quantidade) as quantidade, SUM(vi.subtotal) as valor
                            FROM venda_itens vi JOIN produtos p ON vi.produto_id = p.id 
                            JOIN vendas v ON vi.venda_id = v.id 
                            WHERE DATE(v.data_venda) BETWEEN ? AND ?
                            GROUP BY p.id, p.nome ORDER BY quantidade DESC LIMIT 5";
    $stmt_produtos_pdf = $conn->prepare($sql_produtos_pdf);
    $stmt_produtos_pdf->bind_param("ss", $data_inicio_pdf, $data_fim_pdf);
    $stmt_produtos_pdf->execute();
    $result_produtos_pdf = $stmt_produtos_pdf->get_result();
    
    if ($result_produtos_pdf->num_rows > 0) {
        $count = 1;
        while($produto = $result_produtos_pdf->fetch_assoc()) {
            $valor = $produto['quantidade'] . utf8_decode(' unidades - R$ ') . number_format($produto['valor'], 2, ',', '.');
            $pdf->ItemLine($count . '. ' . utf8_decode($produto['nome']), $valor);
            $count++;
        }
    } else {
        $pdf->ItemLine('-', utf8_decode('Nenhum produto vendido no periodo'));
    }
    $stmt_produtos_pdf->close();
    
    // Saída do PDF
    $pdf->Output('I', 'relatorio_vendas_detalhado_' . $data_inicio_pdf . '_a_' . $data_fim_pdf . '.pdf');
    exit;
}


// ======================================================================
// 3. CONSULTAS PARA O PAINEL E GRÁFICOS (SEGURAS COM PREPARED STATEMENTS)
// ======================================================================

// 1. DADOS TOTAIS
$sql_total_vendas = "SELECT COUNT(*) as total_vendas, COALESCE(SUM(total_venda), 0) as total_valor,
                            AVG(total_venda) as ticket_medio FROM vendas 
                            WHERE DATE(data_venda) BETWEEN ? AND ?";
$stmt_totais = $conn->prepare($sql_total_vendas);
$stmt_totais->bind_param("ss", $data_inicio, $data_fim);
$stmt_totais->execute();
$totais = $stmt_totais->get_result()->fetch_assoc();
$stmt_totais->close();

// 2. VENDAS POR DIA (para gráfico de linha)
$sql_vendas_dia = "SELECT DATE(data_venda) as dia, SUM(total_venda) as valor 
                   FROM vendas 
                   WHERE DATE(data_venda) BETWEEN ? AND ?
                   GROUP BY DATE(data_venda) ORDER BY dia";
$stmt_vendas_dia = $conn->prepare($sql_vendas_dia);
$stmt_vendas_dia->bind_param("ss", $data_inicio, $data_fim);
$stmt_vendas_dia->execute();
$vendas_dia = $stmt_vendas_dia->get_result();

// 3. PRODUTOS MAIS VENDIDOS (para gráfico de barras)
$sql_produtos_vendidos = "SELECT p.nome, SUM(vi.quantidade) as quantidade 
                          FROM venda_itens vi JOIN produtos p ON vi.produto_id = p.id 
                          JOIN vendas v ON vi.venda_id = v.id 
                          WHERE DATE(v.data_venda) BETWEEN ? AND ?
                          GROUP BY p.id, p.nome ORDER BY quantidade DESC LIMIT 10";
$stmt_produtos_vendidos = $conn->prepare($sql_produtos_vendidos);
$stmt_produtos_vendidos->bind_param("ss", $data_inicio, $data_fim);
$stmt_produtos_vendidos->execute();
$produtos_vendidos = $stmt_produtos_vendidos->get_result();

// 4. VENDAS POR PLATAFORMA (para gráfico de rosca)
$sql_vendas_plataforma = "SELECT p.plataforma, SUM(vi.subtotal) as valor 
                          FROM venda_itens vi JOIN produtos p ON vi.produto_id = p.id 
                          JOIN vendas v ON vi.venda_id = v.id 
                          WHERE DATE(v.data_venda) BETWEEN ? AND ?
                          GROUP BY p.plataforma ORDER BY valor DESC";
$stmt_vendas_plataforma = $conn->prepare($sql_vendas_plataforma);
$stmt_vendas_plataforma->bind_param("ss", $data_inicio, $data_fim);
$stmt_vendas_plataforma->execute();
$vendas_plataforma = $stmt_vendas_plataforma->get_result();

// 5. PARCELAMENTO/FORMA DE PAGAMENTO (para gráfico de pizza)
$sql_parcelamento = "SELECT 
                     CASE 
                        WHEN forma_pagamento = 'cartao_credito' AND parcelas > 1 THEN CONCAT('Cartão Crédito (', parcelas, 'x)')
                        WHEN forma_pagamento = 'cartao_credito' AND parcelas = 1 THEN 'Cartão Crédito (À vista)'
                        ELSE forma_pagamento 
                     END as forma_pagamento_detalhada,
                     SUM(total_venda) as valor 
                     FROM vendas 
                     WHERE DATE(data_venda) BETWEEN ? AND ?
                     GROUP BY forma_pagamento_detalhada ORDER BY valor DESC";
$stmt_parcelamento = $conn->prepare($sql_parcelamento);
$stmt_parcelamento->bind_param("ss", $data_inicio, $data_fim);
$stmt_parcelamento->execute();
$parcelamento = $stmt_parcelamento->get_result();

// 6. CLIENTES ATIVOS (para cartão de estatística)
$sql_clientes_ativos = "SELECT COUNT(DISTINCT cliente_id) as total 
                        FROM vendas 
                        WHERE DATE(data_venda) BETWEEN ? AND ? AND cliente_id IS NOT NULL";
$stmt_clientes_ativos = $conn->prepare($sql_clientes_ativos);
$stmt_clientes_ativos->bind_param("ss", $data_inicio, $data_fim);
$stmt_clientes_ativos->execute();
$clientes_ativos = $stmt_clientes_ativos->get_result()->fetch_assoc();
$stmt_clientes_ativos->close();

// 7. TOTAL DE ITENS VENDIDOS (para cartão de estatística)
$sql_total_itens_display = "SELECT SUM(quantidade) as total FROM venda_itens vi 
                        JOIN vendas v ON vi.venda_id = v.id 
                        WHERE DATE(v.data_venda) BETWEEN ? AND ?";
$stmt_itens_display = $conn->prepare($sql_total_itens_display);
$stmt_itens_display->bind_param("ss", $data_inicio, $data_fim);
$stmt_itens_display->execute();
$total_itens_display = $stmt_itens_display->get_result()->fetch_assoc();
$stmt_itens_display->close();


// ======================================================================
// 4. PREPARAR DADOS PARA JAVASCRIPT E HTML
// ======================================================================

// Dados Vendas por Dia
$vendas_dia_labels = []; $vendas_dia_valores = [];
while($venda = $vendas_dia->fetch_assoc()) {
    $vendas_dia_labels[] = date('d/m', strtotime($venda['dia']));
    $vendas_dia_valores[] = floatval($venda['valor']);
}
$stmt_vendas_dia->close();

// Função para gerar cores dinâmicas para o gráfico de barras
function gerarCoresDinamicas($quantidade) {
    $cores = [];
    for ($i = 0; $i < $quantidade; $i++) {
        $hue = rand(0, 360);
        $cores[] = "hsl($hue, 70%, 60%)";
    }
    return $cores;
}

// Dados Produtos Mais Vendidos
$produtos_labels = []; $produtos_quantidades = [];
while($produto = $produtos_vendidos->fetch_assoc()) {
    $produtos_labels[] = $produto['nome'];
    $produtos_quantidades[] = intval($produto['quantidade']);
}
$stmt_produtos_vendidos->close();
$cores_dinamicas = gerarCoresDinamicas(count($produtos_labels));

// Dados Vendas por Plataforma
$plataformas_labels = []; $plataformas_valores = [];
while($plataforma = $vendas_plataforma->fetch_assoc()) {
    $plataformas_labels[] = $plataforma['plataforma'];
    $plataformas_valores[] = floatval($plataforma['valor']);
}
$stmt_vendas_plataforma->close();

// Dados Formas de Pagamento
$pagamentos_labels = []; $pagamentos_valores = [];
while($pagamento = $parcelamento->fetch_assoc()) {
    $label = ucwords(str_replace('_', ' ', $pagamento['forma_pagamento_detalhada']));
    $pagamentos_labels[] = $label;
    $pagamentos_valores[] = floatval($pagamento['valor']);
}
$stmt_parcelamento->close();

// Incluir o cabeçalho HTML
$load_charts = true;
$page_title = "Relatórios - GameStore Manager";
include 'includes/header.php';
?>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header">
    <h1>📈 Relatórios e Estatísticas</h1>
    <p>Dados reais das vendas da loja</p>
</div>

<div class="card">
    <h3>🔍 Filtros do Relatório</h3>
    <form method="GET" class="form-row">
        <div class="form-group">
            <label for="data_inicio">Data Início:</label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>" class="form-control">
        </div>
        
        <div class="form-group">
            <label for="data_fim">Data Fim:</label>
            <input type="date" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>" class="form-control">
        </div>
        
        <div class="form-group">
            <label>&nbsp;</label>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">🔍 Aplicar Filtros</button>
                <a href="?exportar_excel=1&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>" class="btn btn-success">
                    📊 Abrir no Excel
                </a>
                <a href="?gerar_pdf=1&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>" target="_blank" class="btn btn-secondary">
                    📄 Gerar PDF
                </a>
            </div>
        </div>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <h3>Faturamento Total</h3>
            <p class="stat-number">R$ <?php echo number_format($totais['total_valor'], 2, ',', '.'); ?></p>
            <p class="stat-value"><?php echo $totais['total_vendas']; ?> Vendas</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-info">
            <h3>Ticket Médio</h3>
            <p class="stat-number">R$ <?php echo number_format($totais['ticket_medio'], 2, ',', '.'); ?></p>
            <p class="stat-value">Por venda</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-info">
            <h3>Itens Vendidos</h3>
            <p class="stat-number">
                <?php echo $total_itens_display['total'] ?: '0'; ?>
            </p>
            <p class="stat-value">Unidades</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-info">
            <h3>Clientes Ativos</h3>
            <p class="stat-number">
                <?php echo $clientes_ativos['total']; ?>
            </p>
            <p class="stat-value">Compraram no período</p>
        </div>
    </div>
</div>

<div class="charts-grid">
    <div class="chart-card">
        <h3>📈 Vendas por Dia (Valor)</h3>
        <div class="chart-container">
            <canvas id="vendasDiaChart"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <h3>🎮 Games Mais Vendidos (Qtd.)</h3>
        <div class="chart-container">
            <canvas id="produtosChart"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <h3>🕹️ Vendas por Plataforma (Valor)</h3>
        <div class="chart-container">
            <canvas id="plataformasChart"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <h3>💳 Formas de Pagamento (Valor)</h3>
        <div class="chart-container">
            <canvas id="pagamentosChart"></canvas>
        </div>
    </div>
</div>

<script>
// LÓGICA DE GRÁFICOS COM CHART.JS
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Vendas por Dia (Linha)
    const ctxVendasDia = document.getElementById('vendasDiaChart').getContext('2d');
    new Chart(ctxVendasDia, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($vendas_dia_labels); ?>,
            datasets: [{
                label: 'Valor em R$',
                data: <?php echo json_encode($vendas_dia_valores); ?>,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true, plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: function(value) { return 'R$ ' + value.toFixed(2); } } }
            }
        }
    });

    // Gráfico de Barras - Games Mais Vendidos (VERTICAL)
    const ctxProdutos = document.getElementById('produtosChart').getContext('2d');
    new Chart(ctxProdutos, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($produtos_labels); ?>,
            datasets: [{
                label: 'Quantidade Vendida',
                data: <?php echo json_encode($produtos_quantidades); ?>,
                backgroundColor: <?php echo json_encode($cores_dinamicas); ?>,
                borderColor: <?php echo json_encode($cores_dinamicas); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true, 
            plugins: { 
                legend: { 
                    display: false 
                } 
            }, 
            scales: { 
                y: { 
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade Vendida'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Produtos'
                    }
                }
            }
        }
    });

    // Gráfico de Plataformas (Rosca)
    const ctxPlataformas = document.getElementById('plataformasChart').getContext('2d');
    new Chart(ctxPlataformas, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($plataformas_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($plataformas_valores); ?>,
                backgroundColor: ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6']
            }]
        },
        options: { responsive: true }
    });

    // Gráfico de Pagamentos COM PARCELAS (Pizza)
    const ctxPagamentos = document.getElementById('pagamentosChart').getContext('2d');
    new Chart(ctxPagamentos, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($pagamentos_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($pagamentos_valores); ?>,
                backgroundColor: [
                    '#27ae60', '#3498db', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c', 
                    '#34495e', '#e67e22', '#95a5a6' 
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12 } }
            }
        }
    });
});
</script>

<style>
/* Remove todo o CSS anterior e substitua por este */

/* ===== VARIÁVEIS CSS COMPATÍVEIS ===== */
:root {
    --bg-primary: #ffffff;
    --bg-secondary: #f8f9fa;
    --bg-card: #ffffff;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --text-muted: #adb5bd;
    --border-color: #dee2e6;
    --primary-color: #007bff;
    --danger-color: #dc3545;
    --success-color: #28a745;
    --heading-color: #2c3e50;
}

.dark-mode {
    --bg-primary: #1a1d23;
    --bg-secondary: #252a33;
    --bg-card: #2d3440;
    --text-primary: #ffffff;
    --text-secondary: #adb5bd;
    --text-muted: #6c757d;
    --border-color: #495057;
    --primary-color: #0d6efd;
    --danger-color: #dc3545;
    --success-color: #198754;
    --heading-color: #ffffff;
}

/* ===== ESTILOS ESPECÍFICOS DA PÁGINA RELATÓRIOS ===== */
body { 
    background: var(--bg-primary); 
    color: var(--text-primary); 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
}

.charts-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); 
    gap: 1.5rem; 
    margin: 2rem 0; 
}

.chart-card { 
    background: var(--bg-card); 
    padding: 1.5rem; 
    border-radius: 0.5rem; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.chart-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.chart-container { 
    position: relative; 
    height: 300px; 
    width: 100%; 
}

.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
    gap: 1.5rem; 
    margin: 2rem 0; 
}

.stat-card { 
    background: var(--bg-card); 
    padding: 1.5rem; 
    border-radius: 0.5rem; 
    display: flex; 
    align-items: center; 
    gap: 1rem; 
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-icon { 
    font-size: 2.5rem; 
    color: var(--primary-color); 
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-secondary);
    border-radius: 0.375rem;
}

.stat-info { 
    flex: 1; 
}

.stat-info h3 { 
    margin: 0 0 0.5rem 0; 
    font-size: 0.9rem; 
    color: var(--text-secondary); 
}

.stat-number { 
    font-size: 2rem; 
    font-weight: bold; 
    margin: 0; 
    color: var(--primary-color); 
    line-height: 1;
}

.stat-value { 
    color: var(--text-muted); 
    margin: 0; 
    font-size: 0.875rem; 
}

.form-row { 
    display: flex; 
    gap: 1rem; 
    flex-wrap: wrap; 
    align-items: end; 
}

.form-group { 
    flex: 1; 
    min-width: 150px; 
}

.btn { 
    padding: 0.75rem 1.5rem; 
    border: none; 
    border-radius: 0.375rem; 
    cursor: pointer; 
    text-decoration: none; 
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem; 
    font-weight: 500;
    transition: all 0.3s ease; 
    text-align: center;
    justify-content: center;
}

.btn-primary { 
    background: var(--primary-color); 
    color: white; 
}

.btn-primary:hover { 
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}

.btn-success { 
    background: var(--success-color); 
    color: white; 
}

.btn-success:hover { 
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40,167,69,0.3);
}

.btn-secondary { 
    background: #6c757d; 
    color: white; 
}

.btn-secondary:hover { 
    background: #545b62;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108,117,125,0.3);
}

.card { 
    background: var(--bg-card); 
    padding: 1.5rem; 
    border-radius: 0.5rem; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    border: 1px solid var(--border-color); 
    margin-bottom: 2rem; 
}

.page-header { 
    margin-bottom: 2rem; 
    text-align: center;
}

.page-header h1 { 
    margin: 0; 
    color: var(--heading-color); 
    font-size: 2.5rem;
}

.page-header p { 
    margin: 0.5rem 0 0 0; 
    color: var(--text-muted); 
    font-size: 1.1rem;
}

.form-control { 
    width: 100%; 
    padding: 0.75rem 1rem; 
    border: 1px solid var(--border-color); 
    border-radius: 0.375rem; 
    font-size: 1rem; 
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.form-group label { 
    display: block; 
    margin-bottom: 0.5rem; 
    font-weight: bold; 
    color: var(--text-primary); 
}

/* ===== RESPONSIVIDADE ===== */
@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .form-group {
        min-width: 100%;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .chart-card {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
include 'includes/footer.php';
?>