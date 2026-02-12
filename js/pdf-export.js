// ===== SISTEMA DE EXPORTAÇÃO PARA PDF =====

class ExportadorPDF {
    constructor() {
        this.doc = null;
        this.margem = 20;
        this.larguraUtil = 0;
        this.yAtual = 0;
    }

    // ===== INICIALIZAR DOCUMENTO =====
    inicializar(titulo = 'Relatório') {
        const { jsPDF } = window.jspdf;
        this.doc = new jsPDF();
        this.larguraUtil = this.doc.internal.pageSize.width - (this.margem * 2);
        this.yAtual = this.margem;
        
        // Cabeçalho
        this.adicionarCabecalho(titulo);
        
        return this.doc;
    }

    // ===== CABEÇALHO =====
    adicionarCabecalho(titulo) {
        this.doc.setFontSize(20);
        this.doc.setFont('helvetica', 'bold');
        this.doc.text(titulo, this.margem, this.yAtual);
        this.yAtual += 10;

        // Data de geração
        this.doc.setFontSize(10);
        this.doc.setFont('helvetica', 'normal');
        this.doc.text(`Gerado em: ${new Date().toLocaleDateString('pt-BR')} ${new Date().toLocaleTimeString('pt-BR')}`, this.margem, this.yAtual);
        this.yAtual += 15;

        // Linha separadora
        this.doc.setDrawColor(200, 200, 200);
        this.doc.line(this.margem, this.yAtual, this.margem + this.larguraUtil, this.yAtual);
        this.yAtual += 10;
    }

    // ===== ADICIONAR TÍTULO DE SEÇÃO =====
    adicionarTituloSecao(texto, nivel = 2) {
        this.verificarQuebraPagina(15);
        
        const tamanhos = { 1: 16, 2: 14, 3: 12 };
        this.doc.setFontSize(tamanhos[nivel] || 14);
        this.doc.setFont('helvetica', 'bold');
        this.doc.text(texto, this.margem, this.yAtual);
        this.yAtual += 8;

        // Linha abaixo do título
        this.doc.setDrawColor(100, 100, 100);
        this.doc.line(this.margem, this.yAtual, this.margem + (this.larguraUtil * 0.3), this.yAtual);
        this.yAtual += 10;
    }

    // ===== ADICIONAR PARÁGRAFO =====
    adicionarParagrafo(texto) {
        this.verificarQuebraPagina(10);
        
        this.doc.setFontSize(10);
        this.doc.setFont('helvetica', 'normal');
        
        const linhas = this.doc.splitTextToSize(texto, this.larguraUtil);
        this.doc.text(linhas, this.margem, this.yAtual);
        this.yAtual += (linhas.length * 5) + 5;
    }

    // ===== ADICIONAR TABELA =====
    adicionarTabela(dados, colunas) {
        this.verificarQuebraPagina(50);
        
        const config = {
            startY: this.yAtual,
            head: [colunas],
            body: dados,
            margin: { left: this.margem, right: this.margem },
            styles: {
                fontSize: 8,
                cellPadding: 3,
                valign: 'middle'
            },
            headStyles: {
                fillColor: [52, 152, 219],
                textColor: 255,
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            },
            didDrawPage: (data) => {
                this.yAtual = data.cursor.y + 10;
            }
        };

        this.doc.autoTable(config);
        this.yAtual = this.doc.lastAutoTable.finalY + 10;
    }

    // ===== ADICIONAR GRÁFICO COMO IMAGEM =====
    async adicionarGrafico(canvasId, titulo = '') {
        this.verificarQuebraPagina(100);
        
        if (titulo) {
            this.adicionarTituloSecao(titulo, 3);
        }

        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error(`Canvas ${canvasId} não encontrado`);
            return;
        }

        try {
            const imagemData = await this.capturarCanvas(canvas);
            const larguraImagem = this.larguraUtil;
            const alturaImagem = (canvas.height * larguraImagem) / canvas.width;
            
            this.doc.addImage(imagemData, 'PNG', this.margem, this.yAtual, larguraImagem, alturaImagem);
            this.yAtual += alturaImagem + 10;
        } catch (error) {
            console.error('Erro ao capturar gráfico:', error);
            this.adicionarParagrafo(`[Gráfico ${canvasId} não pôde ser renderizado]`);
        }
    }

    // ===== CAPTURAR CANVAS =====
    capturarCanvas(canvas) {
        return new Promise((resolve, reject) => {
            html2canvas(canvas, {
                scale: 2,
                useCORS: true,
                logging: false
            }).then(canvasCapturado => {
                resolve(canvasCapturado.toDataURL('image/png'));
            }).catch(reject);
        });
    }

    // ===== ADICIONAR ESTATÍSTICAS =====
    adicionarEstatisticas(estatisticas) {
        this.verificarQuebraPagina(30);
        
        this.doc.setFontSize(12);
        this.doc.setFont('helvetica', 'bold');
        this.doc.text('Estatísticas:', this.margem, this.yAtual);
        this.yAtual += 8;

        this.doc.setFontSize(10);
        this.doc.setFont('helvetica', 'normal');
        
        estatisticas.forEach((estatistica, index) => {
            const y = this.yAtual + (index * 6);
            this.doc.text(`${estatistica.label}:`, this.margem, y);
            this.doc.text(estatistica.valor, this.margem + 60, y);
        });
        
        this.yAtual += (estatisticas.length * 6) + 10;
    }

    // ===== VERIFICAR QUEBRA DE PÁGINA =====
    verificarQuebraPagina(alturaNecessaria) {
        const alturaPagina = this.doc.internal.pageSize.height;
        const margemInferior = 20;
        
        if (this.yAtual + alturaNecessaria > alturaPagina - margemInferior) {
            this.doc.addPage();
            this.yAtual = this.margem;
            
            // Adicionar cabeçalho de continuação
            this.doc.setFontSize(8);
            this.doc.setFont('helvetica', 'italic');
            this.doc.text('Continuação...', this.margem, this.yAtual);
            this.yAtual += 10;
        }
    }

    // ===== SALVAR DOCUMENTO =====
    salvar(nomeArquivo = 'relatorio.pdf') {
        if (this.doc) {
            // Rodapé na última página
            this.adicionarRodape();
            this.doc.save(nomeArquivo);
        }
    }

    // ===== ADICIONAR RODAPÉ =====
    adicionarRodape() {
        const totalPaginas = this.doc.internal.getNumberOfPages();
        
        for (let i = 1; i <= totalPaginas; i++) {
            this.doc.setPage(i);
            
            this.doc.setFontSize(8);
            this.doc.setFont('helvetica', 'italic');
            this.doc.text(
                `Página ${i} de ${totalPaginas} - GameStore Manager`,
                this.margem,
                this.doc.internal.pageSize.height - 10
            );
        }
    }
}

// ===== FUNÇÕES DE EXPORTAÇÃO RÁPIDAS =====

// Exportar relatório completo do dashboard
async function exportarRelatorioCompleto() {
    const exportador = new ExportadorPDF();
    exportador.inicializar('Relatório Completo - GameStore Manager');
    
    // Adicionar período
    exportador.adicionarTituloSecao('Período do Relatório', 2);
    exportador.adicionarParagrafo(`Período: ${document.getElementById('data_inicio').value} à ${document.getElementById('data_fim').value}`);
    
    // Adicionar estatísticas
    const estatisticas = [
        { label: 'Total de Vendas', valor: document.querySelector('.stat-card:nth-child(1) .stat-number').textContent },
        { label: 'Faturamento Total', valor: document.querySelector('.stat-card:nth-child(1) .stat-value').textContent },
        { label: 'Ticket Médio', valor: document.querySelector('.stat-card:nth-child(2) .stat-number').textContent },
        { label: 'Clientes Ativos', valor: document.querySelector('.stat-card:nth-child(3) .stat-number').textContent }
    ];
    exportador.adicionarEstatisticas(estatisticas);
    
    // Adicionar gráficos
    exportador.adicionarTituloSecao('Gráficos e Análises', 2);
    
    try {
        await exportador.adicionarGrafico('vendasDiaChart', 'Vendas por Dia');
        await exportador.adicionarGrafico('produtosChart', 'Produtos Mais Vendidos');
        await exportador.adicionarGrafico('plataformasChart', 'Vendas por Plataforma');
        await exportador.adicionarGrafico('pagamentosChart', 'Formas de Pagamento');
    } catch (error) {
        console.error('Erro ao adicionar gráficos:', error);
    }
    
    // Adicionar tabela de vendas recentes
    exportador.adicionarTituloSecao('Vendas Recentes', 2);
    
    const dadosTabela = [];
    document.querySelectorAll('.data-table tbody tr').forEach(linha => {
        const celulas = linha.querySelectorAll('td');
        if (celulas.length >= 4) {
            dadosTabela.push([
                celulas[0].textContent,
                celulas[1].textContent,
                celulas[2].textContent,
                celulas[3].textContent
            ]);
        }
    });
    
    if (dadosTabela.length > 0) {
        exportador.adicionarTabela(dadosTabela, ['ID', 'Data', 'Cliente', 'Total']);
    }
    
    exportador.salvar(`relatorio_completo_${new Date().toISOString().split('T')[0]}.pdf`);
}

// Exportar relatório simples
function exportarRelatorioSimples(titulo, conteudoHtml, nomeArquivo) {
    const exportador = new ExportadorPDF();
    exportador.inicializar(titulo);
    
    // Converter HTML para texto simples (simplificado)
    const texto = conteudoHtml.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    exportador.adicionarParagrafo(texto);
    
    exportador.salvar(nomeArquivo || `${titulo.toLowerCase().replace(/\s+/g, '_')}.pdf`);
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar botões de exportação se não existirem
    if (document.querySelector('.chart-container') && !document.querySelector('.btn-exportar-pdf')) {
        const botoesExportacao = `
            <div style="margin: 10px 0; text-align: center;">
                <button onclick="exportarRelatorioCompleto()" class="btn btn-secondary">
                    📄 Exportar Relatório Completo em PDF
                </button>
            </div>
        `;
        
        const primeiroChart = document.querySelector('.chart-container');
        if (primeiroChart) {
            primeiroChart.insertAdjacentHTML('beforebegin', botoesExportacao);
        }
    }
});

// ===== EXPORTAÇÃO GLOBAL =====
window.ExportadorPDF = ExportadorPDF;
window.exportarRelatorioCompleto = exportarRelatorioCompleto;
window.exportarRelatorioSimples = exportarRelatorioSimples;