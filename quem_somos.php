<?php
include 'includes/config.php';
verificarAuth();

$page_title = "Quem Somos - GameStore Manager";
include 'includes/header.php';
?>

<div class="page-header">
    <h1>👨‍💻 Quem Somos</h1>
    <p>Conheça a mente por trás da GameStore Manager</p>
</div>

<div class="about-container">
    <!-- ✅ SEÇÃO PRINCIPAL DO DESENVOLVEDOR -->
    <div class="dev-card">
        <div class="dev-photo-section">
            <div class="photo-container">
                <img src="imagens/gabriel-suliano.jpeg" alt="Gabriel Suliano" class="dev-photo">
            </div>
            <div class="dev-social">
                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=gabrielsuliano240@gmail.com" target="_blank" class="social-link">📧 Gmail</a>
                <a href="https://www.instagram.com/gabriel_suliano.dev/" target="_blank" class="social-link">Instagram</a>
                
            </div>
        </div>
        
        <div class="dev-info">
            <h2>Gabriel Suliano</h2>
            <p class="dev-role">Desenvolvedor Full Stack & Estudante</p>
            
            <div class="dev-bio">
                <h3>🎯 Sobre Mim</h3>
                <p>
                    Desenvolvedor apaixonado por tecnologia e games, criando soluções inovadoras 
                    para o mercado de varejo. Atualmente focado em desenvolvimento web full stack 
                    com PHP, JavaScript e MySQL.
                </p>
                
                <h3>🚀 Competências Técnicas</h3>
                <div class="skills-grid">
                    <span class="skill-tag">PHP</span>
                    <span class="skill-tag">MySQL</span>
                    <span class="skill-tag">JavaScript</span>
                    <span class="skill-tag">HTML/CSS</span>
                    <span class="skill-tag">Chart.js</span>
                    
                    
                   
                </div>
                
                <h3>📈 Projeto GameStore Manager</h3>
                <p>
                    Sistema completo de gestão para lojas de games, desenvolvido como projeto 
                    acadêmico com foco em praticidade e usabilidade. Inclui controle de estoque, 
                    PDV, relatórios e muito mais.
                </p>
            </div>
        </div>
    </div>

    <!-- ✅ MISSÃO E VISÃO -->
    <div class="mission-cards">
        <div class="mission-card">
            <div class="mission-icon">🎯</div>
            <h3>Missão</h3>
            <p>
                Desenvolver soluções tecnológicas que simplifiquem a gestão de negócios, 
                oferecendo ferramentas intuitivas e eficientes para empreendedores.
            </p>
        </div>
        
        <div class="mission-card">
            <div class="mission-icon">🚀</div>
            <h3>Visão</h3>
            <p>
                Ser referência em desenvolvimento de sistemas de gestão, transformando 
                ideias em realidade através da tecnologia.
            </p>
        </div>
        
        <div class="mission-card">
            <div class="mission-icon">💎</div>
            <h3>Valores</h3>
            <p>
                Inovação, Qualidade, Simplicidade e Comprometimento com o sucesso 
                dos nossos clientes.
            </p>
        </div>
    </div>

    <!-- ✅ ESTATÍSTICAS DO SISTEMA -->
    <div class="stats-section">
        <h2>📊 GameStore em Números</h2>
        <div class="system-stats">
            <?php
            // Buscar estatísticas em tempo real
            $sql_system_stats = "SELECT 
                (SELECT COUNT(*) FROM produtos WHERE ativo = 1) as total_produtos,
                (SELECT COUNT(*) FROM clientes) as total_clientes,
                (SELECT COUNT(*) FROM vendas) as total_vendas,
                (SELECT SUM(total_venda) FROM vendas) as faturamento_total";
            
            $stats = $conn->query($sql_system_stats)->fetch_assoc();
            ?>
            
            <div class="system-stat">
                <div class="stat-number"><?php echo $stats['total_produtos']; ?></div>
                <div class="stat-label">Produtos Cadastrados</div>
            </div>
            
            <div class="system-stat">
                <div class="stat-number"><?php echo $stats['total_clientes']; ?></div>
                <div class="stat-label">Clientes Ativos</div>
            </div>
            
            <div class="system-stat">
                <div class="stat-number"><?php echo $stats['total_vendas']; ?></div>
                <div class="stat-label">Vendas Realizadas</div>
            </div>
            
            <div class="system-stat">
                <div class="stat-number">R$ <?php echo number_format($stats['faturamento_total'], 2, ',', '.'); ?></div>
                <div class="stat-label">Faturamento Total</div>
            </div>
        </div>
    </div>

    <!-- ✅ TECNOLOGIAS UTILIZADAS -->
    <div class="tech-section">
        <h2>🛠️ Tecnologias Utilizadas</h2>
        <div class="tech-grid">
            <div class="tech-item">
                <div class="tech-icon">🐘</div>
                <h4>PHP</h4>
                <p>Backend robusto e seguro</p>
            </div>
            
            <div class="tech-item">
                <div class="tech-icon">🗄️</div>
                <h4>MySQL</h4>
                <p>Banco de dados relacional</p>
            </div>
            
            <div class="tech-item">
                <div class="tech-icon">📊</div>
                <h4>Chart.js</h4>
                <p>Gráficos interativos</p>
            </div>
            
            <div class="tech-item">
                <div class="tech-icon">🎨</div>
                <h4>CSS3</h4>
                <p>Design responsivo</p>
            </div>
            
            <div class="tech-item">
                <div class="tech-icon">⚡</div>
                <h4>JavaScript</h4>
                <p>Interatividade dinâmica</p>
            </div>
            
            <div class="tech-item">
                <div class="tech-icon">📱</div>
                <h4>Responsive</h4>
                <p>Mobile First</p>
            </div>
        </div>
    </div>

 
<style>
.about-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* ✅ CARTA DO DESENVOLVEDOR */
.dev-card {
    background: var(--card-bg);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    align-items: start;
}

.dev-photo-section {
    text-align: center;
}

.photo-container {
    width: 200px;
    height: 200px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid var(--border-light);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    background: #f8f9fa; /* Fundo caso a imagem não carregue */
}

.dev-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.dev-photo:hover {
    transform: scale(1.05);
}

.dev-social {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.social-link {
    padding: 0.5rem 1rem;
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    text-decoration: none;
    color: var(--text-color);
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.social-link:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

.dev-info h2 {
    margin: 0 0 0.5rem 0;
    color: var(--heading-color);
    font-size: 2rem;
}

.dev-role {
    color: var(--primary-color);
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.dev-bio h3 {
    color: var(--heading-color);
    margin: 1.5rem 0 0.5rem 0;
    font-size: 1.2rem;
}

.dev-bio p {
    line-height: 1.6;
    color: var(--text-color);
}

/* ✅ SKILLS */
.skills-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 1rem 0;
}

.skill-tag {
    background: var(--primary-color);
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 500;
}

/* ✅ MISSÃO, VISÃO, VALORES */
.mission-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.mission-card {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    text-align: center;
    transition: transform 0.3s ease;
}

.mission-card:hover {
    transform: translateY(-5px);
}

.mission-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.mission-card h3 {
    color: var(--heading-color);
    margin-bottom: 1rem;
}

.mission-card p {
    line-height: 1.6;
    color: var(--text-color);
}

/* ✅ ESTATÍSTICAS DO SISTEMA */
.stats-section {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
}

.stats-section h2 {
    text-align: center;
    margin-bottom: 2rem;
    color: var(--heading-color);
}

.system-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.system-stat {
    text-align: center;
    padding: 1.5rem;
    background: var(--light-bg);
    border-radius: 8px;
    border: 1px solid var(--border-light);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* ✅ TECNOLOGIAS */
.tech-section {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
}

.tech-section h2 {
    text-align: center;
    margin-bottom: 2rem;
    color: var(--heading-color);
}

.tech-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
}

.tech-item {
    text-align: center;
    padding: 1.5rem;
    background: var(--light-bg);
    border-radius: 8px;
    border: 1px solid var(--border-light);
    transition: transform 0.3s ease;
}

.tech-item:hover {
    transform: translateY(-3px);
}

.tech-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.tech-item h4 {
    color: var(--heading-color);
    margin-bottom: 0.5rem;
}

.tech-item p {
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* ✅ CONTATO */
.contact-section {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    text-align: center;
}

.contact-section h2 {
    margin-bottom: 1.5rem;
    color: var(--heading-color);
}

.contact-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: center;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.8rem 1.5rem;
    background: var(--light-bg);
    border-radius: 25px;
    border: 1px solid var(--border-light);
}

.contact-icon {
    font-size: 1.2rem;
}

/* ✅ RESPONSIVIDADE */
@media (max-width: 768px) {
    .dev-card {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .photo-container {
        width: 150px;
        height: 150px;
    }
    
    .mission-cards {
        grid-template-columns: 1fr;
    }
    
    .system-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .tech-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .dev-social {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .system-stats {
        grid-template-columns: 1fr;
    }
    
    .tech-grid {
        grid-template-columns: 1fr;
    }
    
    .contact-item {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php
include 'includes/footer.php';