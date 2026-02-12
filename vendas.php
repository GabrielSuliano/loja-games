<?php
include 'includes/config.php';
verificarAuth();

// ✅ PROCESSAR VENDA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_venda'])) {
    $cliente_id = $_POST['cliente_id'] ?: NULL;
    $forma_pagamento = $conn->real_escape_string($_POST['forma_pagamento']);
    $parcelas = isset($_POST['parcelas']) ? intval($_POST['parcelas']) : 1;
    $observacoes = $conn->real_escape_string($_POST['observacoes']);
    $total_venda = 0;
    
    // ✅ VALIDAR ESTOQUE (ESTOQUE DISPONÍVEL = ESTOQUE - MÍNIMO)
    $erros_estoque = [];
    foreach ($_POST['produto_id'] as $index => $produto_id) {
        $quantidade = intval($_POST['quantidade'][$index]);
        
        // ✅ BUSCAR ESTOQUE ATUALIZADO
        $sql_estoque = "SELECT nome, estoque, estoque_minimo FROM produtos WHERE id = ?";
        $stmt = $conn->prepare($sql_estoque);
        $stmt->bind_param("i", $produto_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $produto = $result->fetch_assoc();
        
        // ✅ ESTOQUE DISPONÍVEL = ESTOQUE TOTAL - ESTOQUE MÍNIMO
        $estoque_disponivel = $produto['estoque'] - $produto['estoque_minimo'];
        
        if ($estoque_disponivel < $quantidade) {
            $erros_estoque[] = "❌ ESTOQUE INSUFICIENTE: {$produto['nome']} 
                               (Estoque: {$produto['estoque']}, 
                                Mínimo: {$produto['estoque_minimo']}, 
                                Disponível: $estoque_disponivel, 
                                Solicitado: $quantidade)";
        }
    }
    
    if (!empty($erros_estoque)) {
        $_SESSION['erro'] = "ERROS DE ESTOQUE:<br>" . implode("<br>", $erros_estoque);
        header('Location: vendas.php');
        exit;
    }
    
    // ✅ CALCULAR TOTAL
    foreach ($_POST['produto_id'] as $index => $produto_id) {
        $quantidade = $_POST['quantidade'][$index];
        $preco_unitario = $_POST['preco_unitario'][$index];
        $total_venda += $quantidade * $preco_unitario;
    }
    
    // ✅ ADICIONAR PARCELAS ÀS OBSERVAÇÕES SE FOR CRÉDITO
    if ($forma_pagamento === 'cartao_credito' && $parcelas > 1) {
        $valor_parcela = $total_venda / $parcelas;
        $observacoes .= " | Parcelado em {$parcelas}x de R$ " . number_format($valor_parcela, 2, ',', '.');
    }
    
    // ✅ REGISTRAR VENDA
    $sql = "INSERT INTO vendas (cliente_id, funcionario_id, total_venda, forma_pagamento, parcelas, observacoes) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iidsss", $cliente_id, $_SESSION['funcionario_id'], $total_venda, $forma_pagamento, $parcelas, $observacoes);
    
    if ($stmt->execute()) {
        $venda_id = $stmt->insert_id;
        
        // ✅ REGISTRAR ITENS E BAIXAR ESTOQUE
        foreach ($_POST['produto_id'] as $index => $produto_id) {
            $quantidade = $_POST['quantidade'][$index];
            $preco_unitario = $_POST['preco_unitario'][$index];
            $subtotal = $quantidade * $preco_unitario;
            
            $sql_item = "INSERT INTO venda_itens (venda_id, produto_id, quantidade, preco_unitario, subtotal) 
                         VALUES (?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            $stmt_item->bind_param("iiidd", $venda_id, $produto_id, $quantidade, $preco_unitario, $subtotal);
            $stmt_item->execute();
            $stmt_item->close();
            
            $sql_estoque = "UPDATE produtos SET estoque = estoque - ? WHERE id = ?";
            $stmt_estoque = $conn->prepare($sql_estoque);
            $stmt_estoque->bind_param("ii", $quantidade, $produto_id);
            $stmt_estoque->execute();
            $stmt_estoque->close();
        }
        
        $_SESSION['sucesso'] = "✅ Venda #$venda_id registrada! Total: R$ " . number_format($total_venda, 2, ',', '.');
        header('Location: vendas.php');
        exit;
    } else {
        $_SESSION['erro'] = "❌ Erro ao registrar venda";
    }
}

// ✅ BUSCAR TODOS OS PRODUTOS ATIVOS (INCLUINDO OS QUE ESTÃO NO ESTOQUE MÍNIMO)
$sql_produtos = "SELECT *, 
                CASE 
                    WHEN foto IS NOT NULL AND foto != '' THEN CONCAT('uploads/produtos/', foto)
                    ELSE NULL 
                END as foto_url,
                (estoque - estoque_minimo) as estoque_disponivel
                FROM produtos 
                WHERE ativo = 1  -- ✅ MOSTRA TODOS OS PRODUTOS ATIVOS, MESMO OS NO MÍNIMO
                ORDER BY nome";
$produtos = $conn->query($sql_produtos);

$sql_clientes = "SELECT * FROM clientes ORDER BY nome";
$clientes = $conn->query($sql_clientes);

$page_title = "PDV - Sistema de Vendas";
include 'includes/header.php';
?>

<div class="page-header">
    <h1>💰 PDV - Ponto de Venda</h1>
    <p>Registre as vendas da loja</p>
</div>

<div class="vendas-container">
    <form method="POST" id="formVenda">
        <!-- ✅ DADOS DO CLIENTE -->
        <div class="form-section">
            <h3>👥 Dados do Cliente</h3>
            <div class="form-group">
                <label for="cliente_id">Cliente:</label>
                <select id="cliente_id" name="cliente_id" class="form-control">
                    <option value="">-- Venda sem cadastro --</option>
                    <?php while($cliente = $clientes->fetch_assoc()): ?>
                        <option value="<?php echo $cliente['id']; ?>">
                            <?php echo $cliente['nome']; ?> - <?php echo $cliente['telefone'] ?: 'Sem telefone'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-muted">Selecione um cliente cadastrado ou deixe em branco para venda avulsa</small>
            </div>
        </div>

        <!-- ✅ PRODUTOS DA VENDA -->
        <div class="form-section">
            <h3>🎮 Produtos da Venda</h3>
            <div id="carrinhoVenda">
                <div class="carrinho-vazio" id="carrinhoVazio">
                    <p>🛒 Nenhum produto adicionado ao carrinho</p>
                </div>
            </div>
            
            <div class="adicionar-produto">
                <div class="form-group">
                    <label for="produto_select">Selecionar Produto:</label>
                    <select id="produto_select" class="form-control">
                        <option value="">-- Selecione um produto --</option>
                        <?php while($produto = $produtos->fetch_assoc()): ?>
                            <?php
                            $estoque_disponivel = $produto['estoque'] - $produto['estoque_minimo'];
                            $disponivel = $estoque_disponivel > 0;
                            $texto_estoque = $disponivel ? 
                                "(Estoque: {$produto['estoque']} | Mínimo: {$produto['estoque_minimo']} | Disponível: {$estoque_disponivel})" : 
                                "(ESGOTADO - Estoque: {$produto['estoque']} | Mínimo: {$produto['estoque_minimo']})";
                            ?>
                            <option value="<?php echo $produto['id']; ?>" 
                                    data-preco="<?php echo $produto['preco_venda']; ?>"
                                    data-estoque="<?php echo $produto['estoque']; ?>"
                                    data-estoque_minimo="<?php echo $produto['estoque_minimo']; ?>"
                                    data-nome="<?php echo htmlspecialchars($produto['nome']); ?>"
                                    data-foto="<?php echo $produto['foto_url'] ?: ''; ?>"
                                    data-disponivel="<?php echo $disponivel ? '1' : '0'; ?>"
                                    <?php echo !$disponivel ? 'disabled style="color: var(--danger-color); background-color: #ffe6e6;"' : ''; ?>>
                                <?php echo $produto['nome']; ?> - R$ <?php echo number_format($produto['preco_venda'], 2, ',', '.'); ?> 
                                <?php echo $texto_estoque; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantidade_produto">Quantidade:</label>
                    <input type="number" id="quantidade_produto" value="1" min="1" class="form-control">
                </div>
                <button type="button" class="btn btn-primary" onclick="adicionarProduto()">
                    ➕ Adicionar ao Carrinho
                </button>
            </div>
        </div>

        <!-- ✅ FINALIZAÇÃO -->
        <div class="form-section">
            <h3>💳 Finalizar Venda</h3>
            <div class="form-group">
                <label for="forma_pagamento">Forma de Pagamento:</label>
                <select id="forma_pagamento" name="forma_pagamento" required class="form-control" onchange="mostrarParcelamento()">
                    <option value="">-- Selecione --</option>
                    <option value="dinheiro">💵 Dinheiro</option>
                    <option value="cartao_debito">💳 Cartão Débito</option>
                    <option value="cartao_credito">💳 Cartão Crédito</option>
                    <option value="pix">📱 PIX</option>
                </select>
            </div>
            
            <!-- ✅ PARCELAMENTO (APARECE SÓ NO CRÉDITO) -->
            <div class="form-group" id="parcelamento_group" style="display: none;">
                <label for="parcelas">Parcelas:</label>
                <select id="parcelas" name="parcelas" class="form-control" onchange="atualizarResumoParcelas()">
                    <option value="1">1x à vista</option>
                    <option value="2">2x sem juros</option>
                    <option value="3">3x sem juros</option>
                    <option value="4">4x sem juros</option>
                    <option value="5">5x sem juros</option>
                    <option value="6">6x sem juros</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="observacoes">Observações:</label>
                <textarea id="observacoes" name="observacoes" class="form-control" rows="2" placeholder="Observações da venda..."></textarea>
            </div>
            
            <div class="resumo-venda">
                <h4>📋 Resumo da Venda</h4>
                <div class="resumo-item">
                    <span>Total de Itens:</span>
                    <span id="totalItens">0</span>
                </div>
                <div class="resumo-item">
                    <span>Valor Total:</span>
                    <span id="valorTotal">R$ 0,00</span>
                </div>
                <div class="resumo-item" id="parcelas_resumo" style="display: none;">
                    <span>Valor da Parcela:</span>
                    <span id="valorParcela">-</span>
                </div>
            </div>
            
            <div class="form-buttons-finalizar">
                <button type="submit" name="finalizar_venda" class="btn btn-success btn-lg">
                    ✅ Finalizar Venda
                </button>
                <button type="button" class="btn btn-danger btn-lg" onclick="cancelarVenda()">
                    ❌ Cancelar Venda
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ✅ MODAL PERSONALIZADO PARA DARK MODE - CORRIGIDO -->
<div id="customModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h3 id="customModalTitle">Atenção</h3>
            <span class="custom-close" onclick="fecharModal()">&times;</span>
        </div>
        <div class="custom-modal-body">
            <p id="customModalMessage"></p>
        </div>
        <div class="custom-modal-footer" id="customModalFooter">
            <button class="btn btn-primary" onclick="fecharModal()">OK</button>
        </div>
    </div>
</div>

<script>
let carrinho = [];
let produtoPendente = null;

// ✅ MODAL PERSONALIZADO - CORRIGIDO
function mostrarModal(titulo, mensagem) {
    document.getElementById('customModalTitle').textContent = titulo;
    document.getElementById('customModalMessage').textContent = mensagem;
    
    // ✅ RESTAURAR FOOTER PADRÃO
    document.getElementById('customModalFooter').innerHTML = `
        <button class="btn btn-primary" onclick="fecharModal()">OK</button>
    `;
    
    document.getElementById('customModal').style.display = 'flex';
}

function fecharModal() {
    document.getElementById('customModal').style.display = 'none';
    produtoPendente = null;
}

// ✅ FECHAR MODAL CLICANDO FORA
window.onclick = function(event) {
    const modal = document.getElementById('customModal');
    if (event.target === modal) {
        fecharModal();
    }
}

function adicionarProduto() {
    const select = document.getElementById('produto_select');
    const quantidadeInput = document.getElementById('quantidade_produto');
    
    if (!select.value) {
        mostrarModal('Atenção', '❌ Selecione um produto!');
        return;
    }
    
    const produtoId = select.value;
    const quantidade = parseInt(quantidadeInput.value);
    const preco = parseFloat(select.options[select.selectedIndex].dataset.preco);
    const estoque = parseInt(select.options[select.selectedIndex].dataset.estoque);
    const estoqueMinimo = parseInt(select.options[select.selectedIndex].dataset.estoque_minimo) || 0;
    const nome = select.options[select.selectedIndex].dataset.nome;
    const foto = select.options[select.selectedIndex].dataset.foto;
    const disponivel = select.options[select.selectedIndex].dataset.disponivel === '1';
    
    // ✅ VERIFICAR SE PRODUTO ESTÁ DISPONÍVEL
    if (!disponivel) {
        mostrarModal(
            'Produto Indisponível', 
            `❌ ${nome}\n\n` +
            `Este produto está ESGOTADO!\n\n` +
            `Estoque Total: ${estoque}\n` +
            `Estoque Mínimo: ${estoqueMinimo}\n` +
            `Disponível para Venda: 0\n\n` +
            `⚠️ É necessário repor o estoque para vender este produto!`
        );
        return;
    }
    
    // ✅ CALCULAR ESTOQUE DISPONÍVEL (ESTOQUE - MÍNIMO)
    const estoqueDisponivel = calcularEstoqueDisponivel(produtoId, estoque, estoqueMinimo);
    
    // ✅ VERIFICAR SE TEM ESTOQUE DISPONÍVEL
    if (quantidade > estoqueDisponivel) {
        mostrarModal(
            'Estoque Insuficiente', 
            `❌ ${nome}\n\n` +
            `Estoque Total: ${estoque}\n` +
            `Estoque Mínimo: ${estoqueMinimo}\n` +
            `Disponível para Venda: ${estoqueDisponivel}\n` +
            `Solicitado: ${quantidade}\n\n` +
            `⚠️ Não é possível vender abaixo do estoque mínimo!`
        );
        return;
    }
    
    // ✅ VERIFICAR SE VAI FICAR NO MÍNIMO E MOSTRAR ALERTA
    const estoqueAposVenda = estoqueDisponivel - quantidade;
    if (estoqueAposVenda === 0) {
        produtoPendente = {
            produtoId, quantidade, preco, estoque, estoqueMinimo, nome, foto
        };
        
        mostrarModal(
            'Atenção - Estoque no Mínimo', 
            `⚠️ ${nome}\n\n` +
            `Estoque Total: ${estoque}\n` +
            `Estoque Mínimo: ${estoqueMinimo}\n` +
            `Disponível: ${estoqueDisponivel}\n\n` +
            `Após esta venda de ${quantidade} unidades, o estoque ficará EXATAMENTE no mínimo (${estoqueMinimo} unidades).\n\n` +
            `⚠️ ATENÇÃO: Após esta venda, o produto ficará INDISPONÍVEL para novas vendas até que seja reposto o estoque.\n\n` +
            `Deseja continuar?`
        );
        
        document.getElementById('customModalFooter').innerHTML = `
            <button class="btn btn-warning" onclick="confirmarVendaEstoqueMinimo()">Sim, Continuar</button>
            <button class="btn btn-secondary" onclick="fecharModal()">Não, Cancelar</button>
        `;
        return;
    }
    
    if (quantidade <= 0) {
        mostrarModal('Erro', '❌ Quantidade deve ser maior que zero!');
        return;
    }
    
    // ✅ SE CHEGOU ATÉ AQUI, PODE ADICIONAR DIRETO
    adicionarAoCarrinho(produtoId, quantidade, preco, estoque, estoqueMinimo, nome, foto);
}

function confirmarVendaEstoqueMinimo() {
    if (produtoPendente) {
        const { produtoId, quantidade, preco, estoque, estoqueMinimo, nome, foto } = produtoPendente;
        adicionarAoCarrinho(produtoId, quantidade, preco, estoque, estoqueMinimo, nome, foto);
        produtoPendente = null;
        fecharModal();
    }
}

function calcularEstoqueDisponivel(produtoId, estoqueOriginal, estoqueMinimo = 0) {
    const itemNoCarrinho = carrinho.find(item => item.produto_id == produtoId);
    const estoqueReservado = itemNoCarrinho ? itemNoCarrinho.quantidade : 0;
    
    // ✅ ESTOQUE DISPONÍVEL = (ESTOQUE TOTAL - ESTOQUE MÍNIMO) - JÁ NO CARRINHO
    return Math.max(0, (estoqueOriginal - estoqueMinimo) - estoqueReservado);
}

function adicionarAoCarrinho(produtoId, quantidade, preco, estoque, estoqueMinimo, nome, foto) {
    // ✅ VERIFICAR SE PRODUTO JÁ ESTÁ NO CARRINHO
    const index = carrinho.findIndex(item => item.produto_id == produtoId);
    
    if (index !== -1) {
        // ✅ ATUALIZAR PRODUTO EXISTENTE
        const novaQuantidade = carrinho[index].quantidade + quantidade;
        carrinho[index].quantidade = novaQuantidade;
        carrinho[index].subtotal = carrinho[index].preco_unitario * novaQuantidade;
    } else {
        // ✅ ADICIONAR NOVO PRODUTO
        carrinho.push({
            produto_id: produtoId,
            nome: nome,
            preco_unitario: preco,
            quantidade: quantidade,
            subtotal: preco * quantidade,
            estoque_original: estoque,
            estoque_minimo: estoqueMinimo,
            foto: foto
        });
    }
    
    atualizarCarrinho();
    document.getElementById('produto_select').value = '';
    document.getElementById('quantidade_produto').value = 1;
    atualizarOpcoesEstoque();
}

function removerProduto(index) {
    if (index >= 0 && index < carrinho.length) {
        carrinho.splice(index, 1);
        atualizarCarrinho();
        atualizarOpcoesEstoque();
    }
}

function atualizarCarrinho() {
    const container = document.getElementById('carrinhoVenda');
    const carrinhoVazio = document.getElementById('carrinhoVazio');
    const totalItens = document.getElementById('totalItens');
    const valorTotal = document.getElementById('valorTotal');
    
    if (carrinho.length === 0) {
        container.innerHTML = '<div class="carrinho-vazio" id="carrinhoVazio"><p>🛒 Nenhum produto adicionado ao carrinho</p></div>';
        totalItens.textContent = '0';
        valorTotal.textContent = 'R$ 0,00';
        document.getElementById('parcelas_resumo').style.display = 'none';
        return;
    }
    
    // ✅ MONTAR HTML DO CARRINHO
    let html = '<div class="carrinho-itens">';
    let totalGeral = 0;
    let totalItensCount = 0;
    
    carrinho.forEach((item, index) => {
        totalGeral += item.subtotal;
        totalItensCount += item.quantidade;
        
        const estoqueDisponivel = calcularEstoqueDisponivel(item.produto_id, item.estoque_original, item.estoque_minimo);
        
        // ✅ VERIFICAR SE A FOTO EXISTE
        const temFoto = item.foto && item.foto !== '';
        const fotoSrc = temFoto ? item.foto : '';
        
        html += `
            <div class="carrinho-item">
                <div class="item-foto">
                    ${temFoto ? 
                        `<img src="${fotoSrc}" alt="${item.nome}" class="product-thumb" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : 
                        ''
                    }
                    <div class="product-thumb placeholder" style="${temFoto ? 'display: none;' : ''}">🎮</div>
                </div>
                <div class="item-info">
                    <strong>${item.nome}</strong>
                    <div class="item-detalhes">
                        R$ ${item.preco_unitario.toFixed(2)} x ${item.quantidade} = R$ ${item.subtotal.toFixed(2)}
                    </div>
                    <small class="estoque-info" style="color: ${estoqueDisponivel < 5 ? 'var(--danger-color)' : 'var(--success-color)'};">
                        Disponível: ${estoqueDisponivel} | Mínimo: ${item.estoque_minimo}
                    </small>
                </div>
                <div class="item-actions">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removerProduto(${index})" title="Remover produto">
                        🗑️ Remover
                    </button>
                </div>
                <input type="hidden" name="produto_id[]" value="${item.produto_id}">
                <input type="hidden" name="quantidade[]" value="${item.quantidade}">
                <input type="hidden" name="preco_unitario[]" value="${item.preco_unitario}">
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
    totalItens.textContent = totalItensCount;
    atualizarResumoParcelas(totalGeral);
}

function atualizarOpcoesEstoque() {
    const select = document.getElementById('produto_select');
    if (!select) return;
    
    const options = select.options;
    
    for (let i = 0; i < options.length; i++) {
        if (options[i].value) {
            const produtoId = options[i].value;
            const estoqueOriginal = parseInt(options[i].dataset.estoque);
            const estoqueMinimo = parseInt(options[i].dataset.estoque_minimo) || 0;
            const nomeOriginal = options[i].dataset.nome;
            const precoOriginal = options[i].dataset.preco;
            
            const estoqueDisponivel = calcularEstoqueDisponivel(produtoId, estoqueOriginal, estoqueMinimo);
            const disponivel = estoqueDisponivel > 0;
            
            const textoBase = `${nomeOriginal} - R$ ${parseFloat(precoOriginal).toFixed(2)}`;
            
            if (disponivel) {
                options[i].text = `${textoBase} (Estoque: ${estoqueOriginal} | Mínimo: ${estoqueMinimo} | Disponível: ${estoqueDisponivel})`;
                options[i].disabled = false;
                options[i].style.color = '';
                options[i].style.backgroundColor = '';
                options[i].dataset.disponivel = '1';
            } else {
                options[i].text = `${textoBase} (ESGOTADO - Estoque: ${estoqueOriginal} | Mínimo: ${estoqueMinimo})`;
                options[i].disabled = true;
                options[i].style.color = 'var(--danger-color)';
                options[i].style.backgroundColor = '#ffe6e6';
                options[i].dataset.disponivel = '0';
            }
        }
    }
}

// ✅ PARCELAMENTO
function mostrarParcelamento() {
    const formaPagamento = document.getElementById('forma_pagamento').value;
    const parcelamentoGroup = document.getElementById('parcelamento_group');
    
    if (formaPagamento === 'cartao_credito') {
        parcelamentoGroup.style.display = 'block';
    } else {
        parcelamentoGroup.style.display = 'none';
        document.getElementById('parcelas_resumo').style.display = 'none';
    }
    atualizarResumoParcelas();
}

function atualizarResumoParcelas(totalGeral = null) {
    if (totalGeral === null) {
        totalGeral = carrinho.reduce((total, item) => total + item.subtotal, 0);
    }
    
    const formaPagamento = document.getElementById('forma_pagamento').value;
    const parcelas = document.getElementById('parcelas').value;
    const valorTotal = document.getElementById('valorTotal');
    const parcelasResumo = document.getElementById('parcelas_resumo');
    const valorParcela = document.getElementById('valorParcela');
    
    if (formaPagamento === 'cartao_credito' && parcelas > 1 && totalGeral > 0) {
        const valorParcelaCalculado = totalGeral / parcelas;
        valorTotal.textContent = 'R$ ' + totalGeral.toFixed(2).replace('.', ',') + ` (${parcelas}x)`;
        valorParcela.textContent = 'R$ ' + valorParcelaCalculado.toFixed(2).replace('.', ',');
        parcelasResumo.style.display = 'flex';
    } else {
        valorTotal.textContent = 'R$ ' + totalGeral.toFixed(2).replace('.', ',');
        parcelasResumo.style.display = 'none';
    }
}

// ✅ CANCELAR VENDA
function cancelarVenda() {
    if (carrinho.length === 0) {
        window.location.href = 'vendas.php';
        return;
    }
    
    mostrarModal(
        'Cancelar Venda',
        '❌ Tem certeza que deseja cancelar esta venda?\n\nTodos os produtos serão removidos do carrinho.'
    );
    
    document.getElementById('customModalFooter').innerHTML = `
        <button class="btn btn-danger" onclick="confirmarCancelamento()">Sim, Cancelar</button>
        <button class="btn btn-secondary" onclick="fecharModal()">Não, Continuar</button>
    `;
}

function confirmarCancelamento() {
    carrinho = [];
    atualizarCarrinho();
    document.getElementById('forma_pagamento').value = '';
    document.getElementById('parcelamento_group').style.display = 'none';
    document.getElementById('observacoes').value = '';
    fecharModal();
    
    setTimeout(() => {
        window.location.href = 'vendas.php';
    }, 500);
}

// ✅ VALIDAÇÃO SIMPLIFICADA - APENAS VERIFICAÇÕES BÁSICAS
function validarVendaAntesDeEnviar() {
    let problemas = [];
    
    if (carrinho.length === 0) {
        problemas.push("❌ Adicione pelo menos um produto ao carrinho");
    }
    
    const formaPagamento = document.getElementById('forma_pagamento').value;
    if (!formaPagamento) {
        problemas.push("❌ Selecione a forma de pagamento");
    }
    
    if (problemas.length > 0) {
        mostrarModal("Erros na Venda", "CORRIJA OS SEGUINTES PROBLEMAS:\n\n" + problemas.join("\n"));
        return false;
    }
    
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const formVenda = document.getElementById('formVenda');
    if (formVenda) {
        formVenda.addEventListener('submit', function(e) {
            if (!validarVendaAntesDeEnviar()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    atualizarCarrinho();
});
</script>

<style>
:root {
    --modal-bg: #ffffff;
    --modal-text: #333333;
    --modal-border: #dddddd;
    --modal-shadow: rgba(0, 0, 0, 0.3);
}

/* ✅ DARK MODE */
@media (prefers-color-scheme: dark) {
    :root {
        --modal-bg: #2d3748;
        --modal-text: #e2e8f0;
        --modal-border: #4a5568;
        --modal-shadow: rgba(0, 0, 0, 0.5);
    }
}

/* ✅ MODAL PERSONALIZADO - CORRIGIDO */
.custom-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    justify-content: center;
    align-items: center;
}

.custom-modal-content {
    background-color: var(--modal-bg);
    color: var(--modal-text);
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    box-shadow: 0 4px 20px var(--modal-shadow);
    border: 1px solid var(--modal-border);
    animation: modalFadeIn 0.3s;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-50px); }
    to { opacity: 1; transform: translateY(0); }
}

.custom-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--modal-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.custom-modal-header h3 {
    margin: 0;
    color: var(--modal-text);
    font-size: 1.3rem;
}

.custom-close {
    color: var(--modal-text);
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s ease;
    line-height: 1;
}

.custom-close:hover {
    color: #e74c3c;
}

.custom-modal-body {
    padding: 1.5rem;
    white-space: pre-line;
    line-height: 1.5;
    flex: 1;
    overflow-y: auto;
}

.custom-modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--modal-border);
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    flex-shrink: 0;
}

/* ✅ ESTILOS EXISTENTES */
.vendas-container {
    display: grid;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.form-section {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.carrinho-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 0.75rem;
    background: var(--light-bg);
    transition: all 0.3s ease;
    gap: 1rem;
}

.carrinho-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.item-foto {
    flex-shrink: 0;
}

.product-thumb {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    object-fit: cover;
    border: 2px solid var(--border-light);
}

.product-thumb.placeholder {
    background: var(--light-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--text-muted);
}

.item-info {
    flex: 1;
}

.item-actions {
    flex-shrink: 0;
}

.carrinho-vazio {
    text-align: center;
    padding: 3rem;
    color: var(--text-muted);
    font-size: 1.1rem;
}

.adicionar-produto {
    background: var(--light-bg);
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1rem;
    border: 1px solid var(--border-light);
}

.resumo-venda {
    background: var(--light-bg);
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    border: 1px solid var(--border-light);
}

.resumo-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-light);
    font-size: 1.1rem;
}

.form-buttons-finalizar {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-top: 1.5rem;
}

.form-buttons-finalizar .btn {
    padding: 15px;
    font-size: 1.1rem;
    font-weight: bold;
}

/* ✅ RESPONSIVIDADE */
@media (max-width: 768px) {
    .carrinho-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .form-buttons-finalizar {
        grid-template-columns: 1fr;
    }
    
    .custom-modal-content {
        width: 95%;
        margin: 5%;
    }
    
    .custom-modal-footer {
        flex-direction: column;
    }
    
    .custom-modal-footer .btn {
        width: 100%;
    }
}
</style>

<?php
include 'includes/footer.php';