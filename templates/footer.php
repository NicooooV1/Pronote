<?php
/**
 * Template de pied de page pour PRONOTE
 */
?>
            <!-- Footer -->
            <div class="footer">
                <div class="footer-content">
                    <div class="footer-links">
                        <a href="#">Mentions Légales</a>
                        <a href="#">Aide</a>
                        <a href="#">Contact</a>
                    </div>
                    <div class="footer-copyright">
                        &copy; <?= date('Y') ?> PRONOTE - Tous droits réservés
                    </div>
                </div>
            </div>
        </div> <!-- Fin de main-content -->
    </div> <!-- Fin de app-container -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navigation mobile
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const sidebar = document.getElementById('sidebar');
            const pageOverlay = document.getElementById('page-overlay');
            
            if (mobileMenuToggle && sidebar && pageOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-visible');
                    pageOverlay.classList.toggle('visible');
                });
                
                pageOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-visible');
                    pageOverlay.classList.remove('visible');
                });
            }
            
            // Auto-masquer les messages d'alerte après 5 secondes
            const alertBanners = document.querySelectorAll('.alert-banner');
            if (alertBanners.length > 0) {
                alertBanners.forEach(alert => {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        setTimeout(() => {
                            alert.style.display = 'none';
                        }, 300);
                    }, 5000);
                });
            }
            
            <?php if (isset($customScripts)): echo $customScripts; endif; ?>
        });
    </script>
    
    <?php if (isset($additionalScripts)): echo $additionalScripts; endif; ?>
</body>
</html>
