<?php
/**
 * Template partagé : Footer + fermeture HTML
 * 
 * Variable optionnelle :
 *   $extraJs — array : fichiers JS supplémentaires à charger
 *   $extraScriptHtml — string : bloc <script> supplémentaire inline
 */

$extraJs = $extraJs ?? [];
$extraScriptHtml = $extraScriptHtml ?? '';
?>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="#">Mentions Légales</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?= date('Y') ?> PRONOTE - Tous droits réservés
                </div>
            </div>
        </div>
    </div><!-- Fin main-content -->
</div><!-- Fin app-container -->

<?php foreach ($extraJs as $js): ?>
<script src="<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-fermeture des alertes
    document.querySelectorAll('.alert-close').forEach(function(button) {
        button.addEventListener('click', function() {
            var alert = this.closest('.alert-banner, .alert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(function() { alert.style.display = 'none'; }, 300);
            }
        });
    });

    document.querySelectorAll('.alert-success').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() { alert.style.display = 'none'; }, 300);
        }, 5000);
    });
});
</script>

<?= $extraScriptHtml ?>

</body>
</html>
