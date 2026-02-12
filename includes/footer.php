    </main>

    <footer class="site-footer">
        <div class="footer-container">
            <!-- Informações da Empresa -->
            <div class="footer-section">
                <div class="footer-logo">
                    <h3>🎮 GameStore Manager</h3>
                </div>
                <p class="footer-description">
                    Sistema profissional de gestão para lojas de games.
                    Controle de estoque, vendas, clientes e muito mais.
                </p>
                <div class="footer-contact">
                    <p>📧 gabrielsuliano240@gmail.com</p>
                    <p>📞 (21) 99843-6606</p>
                    <p>📍 Rio de Janeiro, RJ</p>
                </div>
            </div>

            <!-- redes sociais -->
            <div class="footer-section">
                <h4>Redes Sociais</h4>
                <ul class="footer-links">
                    <li><a href="https://www.instagram.com/gabriel_suliano.dev/" target="_blank">Instagram</a></li>
                    <li><a href="https://wa.me/5521998436606?text=Olá!" target="_blank">WhatsApp</a></li>
                </ul>
            </div> 

            <!-- quem somos -->
            <div class="footer-section">
                <h4>Quem somos</h4>
                <ul class="footer-links">
                    <li><a href="quem_somos.php">Visite a página Sobre Nós</a>

            

                <script src="js/script.js"></script>
                <?php if (isset($load_charts) && $load_charts): ?>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="js/graficos.js"></script>
                <?php endif; ?>

                <?php if (isset($load_pdf) && $load_pdf): ?>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
                    <script src="js/pdf-export.js"></script>
                <?php endif; ?>

                <style>
                    /* ✅ FOOTER ESTILIZADO */
                    .site-footer {
                        background: linear-gradient(135deg, #2c3e50, #34495e);
                        color: white;
                        margin-top: auto;
                    }

                    .footer-container {
                        max-width: 1200px;
                        margin: 0 auto;
                        padding: 3rem 2rem;
                        display: grid;
                        grid-template-columns: 2fr 1fr 1fr 1.5fr;
                        gap: 2rem;
                    }

                    .footer-section h4 {
                        color: #3498db;
                        margin-bottom: 1rem;
                        font-size: 1.1rem;
                        border-bottom: 2px solid #3498db;
                        padding-bottom: 0.5rem;
                    }

                    .footer-logo h3 {
                        color: #3498db;
                        margin-bottom: 1rem;
                        font-size: 1.5rem;
                    }

                    .footer-description {
                        line-height: 1.6;
                        margin-bottom: 1.5rem;
                        color: #bdc3c7;
                    }

                    .footer-contact p {
                        margin: 0.5rem 0;
                        color: #ecf0f1;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }

                    .footer-links {
                        list-style: none;
                        padding: 0;
                        margin: 0;
                    }

                    .footer-links li {
                        margin-bottom: 0.5rem;
                    }

                    .footer-links a {
                        color: #bdc3c7;
                        text-decoration: none;
                        transition: color 0.3s ease;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }

                    .footer-links a:hover {
                        color: #3498db;
                    }

                    .social-links {
                        display: flex;
                        flex-direction: column;
                        gap: 0.5rem;
                        margin-bottom: 1.5rem;
                    }

                    .social-link {
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        color: #bdc3c7;
                        text-decoration: none;
                        transition: all 0.3s ease;
                        padding: 0.5rem;
                        border-radius: 5px;
                    }

                    .social-link:hover {
                        color: #3498db;
                        background: rgba(52, 152, 219, 0.1);
                    }

                    .social-icon {
                        font-size: 1.2rem;
                    }

                    .newsletter {
                        background: rgba(255, 255, 255, 0.05);
                        padding: 1rem;
                        border-radius: 8px;
                        margin-top: 1rem;
                    }

                    .newsletter h5 {
                        margin: 0 0 0.5rem 0;
                        color: #3498db;
                    }

                    .newsletter p {
                        font-size: 0.9rem;
                        color: #bdc3c7;
                        margin-bottom: 1rem;
                    }

                    .newsletter-form {
                        display: flex;
                        gap: 0.5rem;
                    }

                    .newsletter-input {
                        flex: 1;
                        padding: 0.5rem;
                        border: 1px solid #34495e;
                        border-radius: 5px;
                        background: #2c3e50;
                        color: white;
                    }

                    .newsletter-input::placeholder {
                        color: #95a5a6;
                    }

                    .newsletter-btn {
                        padding: 0.5rem 1rem;
                        background: #3498db;
                        color: white;
                        border: none;
                        border-radius: 5px;
                        cursor: pointer;
                        transition: background 0.3s ease;
                    }

                    .newsletter-btn:hover {
                        background: #2980b9;
                    }

                    .footer-bottom {
                        border-top: 1px solid #34495e;
                        padding: 1.5rem 2rem;
                        background: rgba(0, 0, 0, 0.2);
                    }

                    .footer-bottom-content {
                        max-width: 1200px;
                        margin: 0 auto;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }

                    .copyright p {
                        margin: 0.25rem 0;
                        color: #bdc3c7;
                        font-size: 0.9rem;
                    }

                    .footer-links-bottom {
                        display: flex;
                        gap: 1.5rem;
                    }

                    .footer-links-bottom a {
                        color: #bdc3c7;
                        text-decoration: none;
                        font-size: 0.9rem;
                        transition: color 0.3s ease;
                    }

                    .footer-links-bottom a:hover {
                        color: #3498db;
                    }

                    /* ✅ RESPONSIVIDADE */
                    @media (max-width: 768px) {
                        .footer-container {
                            grid-template-columns: 1fr;
                            gap: 2rem;
                            padding: 2rem 1rem;
                        }

                        .footer-bottom-content {
                            flex-direction: column;
                            gap: 1rem;
                            text-align: center;
                        }

                        .footer-links-bottom {
                            justify-content: center;
                        }

                        .newsletter-form {
                            flex-direction: column;
                        }
                    }

                    @media (max-width: 480px) {
                        .footer-links-bottom {
                            flex-direction: column;
                            gap: 0.5rem;
                        }

                        .social-links {
                            flex-direction: row;
                            flex-wrap: wrap;
                        }
                    }
                </style>
                </body>

                </html>