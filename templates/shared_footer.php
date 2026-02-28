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
                    <a href="#" onclick="document.getElementById('mentions-legales-modal').style.display='flex'; return false;">Mentions Légales</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?= date('Y') ?> FRONOTE - Tous droits réservés
                </div>
            </div>
        </div>
    </div><!-- Fin main-content -->
</div><!-- Fin app-container -->

<!-- Modal Mentions Légales -->
<div id="mentions-legales-modal" class="modal-overlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; max-width:600px; width:90%; max-height:80vh; overflow-y:auto; padding:30px; box-shadow:0 20px 60px rgba(0,0,0,0.25); position:relative;">
        <button onclick="this.closest('.modal-overlay').style.display='none'" style="position:absolute; top:12px; right:16px; background:none; border:none; font-size:22px; cursor:pointer; color:#666; line-height:1;">&times;</button>
        <h2 style="margin:0 0 20px; color:var(--primary-color, #0f4c81); font-size:1.3em;">Mentions Légales</h2>
        <div style="color:#555; line-height:1.7; font-size:14px;">
            <h3 style="font-size:1em; margin:16px 0 8px; color:#333;">Éditeur</h3>
            <p>FRONOTE — Application de gestion scolaire.<br>
               Ce logiciel est un projet à but éducatif.</p>
            
            <h3 style="font-size:1em; margin:16px 0 8px; color:#333;">Hébergement</h3>
            <p>Cette application est hébergée en réseau local par l'établissement.</p>
            
            <h3 style="font-size:1em; margin:16px 0 8px; color:#333;">Données personnelles</h3>
            <p>Les données personnelles recueillies sont exclusivement destinées à la gestion scolaire. Conformément au RGPD, vous disposez d'un droit d'accès, de rectification et de suppression de vos données. Pour toute demande, contactez l'administrateur de l'établissement.</p>
            
            <h3 style="font-size:1em; margin:16px 0 8px; color:#333;">Propriété intellectuelle</h3>
            <p>L'ensemble du contenu de cette application est protégé. Toute reproduction non autorisée est interdite.</p>
        </div>
    </div>
</div>

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
