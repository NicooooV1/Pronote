</div><!-- Fin content-container -->
            
<?php
// Script spécifique au module absences
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
<?php
$extraScriptHtml = ob_get_clean();

include __DIR__ . '/../../templates/shared_footer.php';
?>
