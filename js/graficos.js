// ===== BIBLIOTECA DE GRÁFICOS PARA O SISTEMA =====

class GerenciadorGraficos {
    constructor() {
        this.graficos = new Map();
        this.coresPadrao = [
            '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6',
            '#1abc9c', '#34495e', '#d35400', '#c0392b', '#7f8c8d'
        ];
    }

    // ===== GRÁFICO DE BARRAS =====
    criarGraficoBarras(canvasId, dados, opcoes = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const config = {
            type: 'bar',
            data: {
                labels: dados.labels || [],
                datasets: [{
                    label: dados.label || 'Dados',
                    data: dados.valores || [],
                    backgroundColor: dados.cores || this.coresPadrao,
                    borderColor: dados.borderColor || this.coresPadrao.map(cor => this.escurecerCor(cor)),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: opcoes.mostrarLegenda !== false,
                        position: opcoes.posicaoLegenda || 'top'
                    },
                    title: {
                        display: opcoes.titulo ? true : false,
                        text: opcoes.titulo || ''
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (opcoes.formatoTooltip === 'moeda') {
                                    label += new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL'
                                    }).format(context.parsed.y);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (opcoes.formatoEixoY === 'moeda') {
                                    return 'R$ ' + value.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                                return value;
                            }
                        }
                    }
                }
            }
        };

        const grafico = new Chart(ctx, config);
        this.graficos.set(canvasId, grafico);
        return grafico;
    }

    // ===== GRÁFICO DE LINHAS =====
    criarGraficoLinhas(canvasId, dados, opcoes = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const config = {
            type: 'line',
            data: {
                labels: dados.labels || [],
                datasets: dados.datasets || [{
                    label: dados.label || 'Dados',
                    data: dados.valores || [],
                    borderColor: dados.cor || '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    fill: opcoes.preencher !== false,
                    tension: opcoes.tensao || 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: opcoes.mostrarLegenda !== false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: opcoes.iniciarZero !== false
                    }
                }
            }
        };

        const grafico = new Chart(ctx, config);
        this.graficos.set(canvasId, grafico);
        return grafico;
    }

    // ===== GRÁFICO DE PIZZA =====
    criarGraficoPizza(canvasId, dados, opcoes = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const config = {
            type: 'pie',
            data: {
                labels: dados.labels || [],
                datasets: [{
                    data: dados.valores || [],
                    backgroundColor: dados.cores || this.coresPadrao,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: opcoes.posicaoLegenda || 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        };

        const grafico = new Chart(ctx, config);
        this.graficos.set(canvasId, grafico);
        return grafico;
    }

    // ===== GRÁFICO DE ROSCA =====
    criarGraficoRosca(canvasId, dados, opcoes = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const config = {
            type: 'doughnut',
            data: {
                labels: dados.labels || [],
                datasets: [{
                    data: dados.valores || [],
                    backgroundColor: dados.cores || this.coresPadrao,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                cutout: opcoes.corte || '50%',
                plugins: {
                    legend: {
                        position: opcoes.posicaoLegenda || 'right'
                    }
                }
            }
        };

        const grafico = new Chart(ctx, config);
        this.graficos.set(canvasId, grafico);
        return grafico;
    }

    // ===== ATUALIZAR GRÁFICO =====
    atualizarGrafico(canvasId, novosDados) {
        const grafico = this.graficos.get(canvasId);
        if (grafico) {
            grafico.data.labels = novosDados.labels || grafico.data.labels;
            
            if (novosDados.datasets) {
                grafico.data.datasets = novosDados.datasets;
            } else if (novosDados.valores) {
                grafico.data.datasets[0].data = novosDados.valores;
            }
            
            grafico.update();
        }
    }

    // ===== DESTRUIR GRÁFICO =====
    destruirGrafico(canvasId) {
        const grafico = this.graficos.get(canvasId);
        if (grafico) {
            grafico.destroy();
            this.graficos.delete(canvasId);
        }
    }

    // ===== UTILITÁRIOS =====
    escurecerCor(cor, quantidade = 20) {
        // Converte cor HEX para RGB
        let r = parseInt(cor.slice(1, 3), 16);
        let g = parseInt(cor.slice(3, 5), 16);
        let b = parseInt(cor.slice(5, 7), 16);

        // Escurece a cor
        r = Math.max(0, r - quantidade);
        g = Math.max(0, g - quantidade);
        b = Math.max(0, b - quantidade);

        // Converte de volta para HEX
        return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
    }

    gerarCoresGradiente(quantidade, corBase = '#3498db') {
        const cores = [];
        for (let i = 0; i < quantidade; i++) {
            const intensidade = 100 + (i * (155 / quantidade));
            cores.push(this.ajustarBrilho(corBase, intensidade));
        }
        return cores;
    }

    ajustarBrilho(cor, intensidade) {
        // Implementação simplificada para ajuste de brilho
        return cor; // Em produção, implementar lógica real
    }
}

// ===== INICIALIZAÇÃO AUTOMÁTICA =====
document.addEventListener('DOMContentLoaded', function() {
    window.gerenciadorGraficos = new GerenciadorGraficos();
    
    // Exemplo de uso automático para gráficos com data attributes
    const elementosGrafico = document.querySelectorAll('[data-grafico]');
    
    elementosGrafico.forEach(elemento => {
        const tipo = elemento.getAttribute('data-grafico');
        const dadosJson = elemento.getAttribute('data-dados');
        
        if (dadosJson) {
            try {
                const dados = JSON.parse(dadosJson);
                
                switch(tipo) {
                    case 'barra':
                        gerenciadorGraficos.criarGraficoBarras(elemento.id, dados);
                        break;
                    case 'linha':
                        gerenciadorGraficos.criarGraficoLinhas(elemento.id, dados);
                        break;
                    case 'pizza':
                        gerenciadorGraficos.criarGraficoPizza(elemento.id, dados);
                        break;
                    case 'rosca':
                        gerenciadorGraficos.criarGraficoRosca(elemento.id, dados);
                        break;
                }
            } catch (e) {
                console.error('Erro ao criar gráfico:', e);
            }
        }
    });
});

// ===== FUNÇÕES GLOBAIS PARA USO RÁPIDO =====
function criarGraficoBarras(canvasId, dados, opcoes = {}) {
    return window.gerenciadorGraficos.criarGraficoBarras(canvasId, dados, opcoes);
}

function criarGraficoLinhas(canvasId, dados, opcoes = {}) {
    return window.gerenciadorGraficos.criarGraficoLinhas(canvasId, dados, opcoes);
}

function criarGraficoPizza(canvasId, dados, opcoes = {}) {
    return window.gerenciadorGraficos.criarGraficoPizza(canvasId, dados, opcoes);
}

function atualizarGrafico(canvasId, novosDados) {
    return window.gerenciadorGraficos.atualizarGrafico(canvasId, novosDados);
}