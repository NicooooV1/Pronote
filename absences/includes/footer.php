</div><!-- Fin du content-wrapper -->
            
            <!-- Footer -->
            <div class="footer">
                <div class="footer-content">
                    <div class="footer-links">
                        <a href="#">Mentions Légales</a>
                        <a href="#">Confidentialité</a>
                        <a href="#">Aide</a>
                        <a href="#">Contact</a>
                    </div>
                    <div class="footer-copyright">
                        &copy; <?= date('Y') ?> PRONOTE - Tous droits réservés
                    </div>
                </div>
            </div>
        </div><!-- Fin du main-content -->
    </div><!-- Fin du app-container -->

<!-- Scripts communs -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animation pour les messages d'alerte
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            // Ajouter une classe pour l'animation d'entrée
            alert.classList.add('alert-visible');
            
            // Si l'alerte a un bouton de fermeture
            const closeBtn = alert.querySelector('.alert-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    alert.classList.add('alert-hiding');
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                });
            }
            
            // Auto-fermeture pour les alertes de succès
            if (alert.classList.contains('alert-success')) {
                setTimeout(() => {
                    alert.classList.add('alert-hiding');
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });
        
        // Gestion des coches de filtres
        const filterCheckboxes = document.querySelectorAll('.filter-checkbox');
        if (filterCheckboxes.length > 0) {
            filterCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const form = this.closest('form');
                    if (form) form.submit();
                });
            });
        }
        
        // Support pour la navigation mobile
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const pageOverlay = document.querySelector('.page-overlay');
        
        if (mobileMenuToggle && sidebar && pageOverlay) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-visible');
                pageOverlay.classList.toggle('visible');
                document.body.classList.toggle('no-scroll');
            });
            
            pageOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-visible');
                pageOverlay.classList.remove('visible');
                document.body.classList.remove('no-scroll');
            });
        }
    });
</script>
</body>
</html>
