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
                    <a href="#" id="openLegalModal">Mentions Légales</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?= date('Y') ?> FRONOTE - Tous droits réservés
                </div>
            </div>
        </div>
    </div><!-- Fin main-content -->
</div><!-- Fin app-container -->

<!-- Modal Mentions Légales -->
<div id="mentions-legales-modal" class="legal-modal-overlay">
    <div class="legal-modal">
        <button class="legal-modal-close" id="closeLegalModal" type="button">&times;</button>
        <h2>Mentions Légales</h2>
        <div class="legal-modal-body">
            <h3>Éditeur</h3>
            <p>FRONOTE — Application de gestion scolaire.<br>
               Ce logiciel est un projet à but éducatif.</p>

            <h3>Hébergement</h3>
            <p>Cette application est hébergée en réseau local par l'établissement.</p>

            <h3>Données personnelles</h3>
            <p>Les données personnelles recueillies sont exclusivement destinées à la gestion scolaire. Conformément au RGPD, vous disposez d'un droit d'accès, de rectification et de suppression de vos données. Pour toute demande, contactez l'administrateur de l'établissement.</p>

            <h3>Propriété intellectuelle</h3>
            <p>L'ensemble du contenu de cette application est protégé. Toute reproduction non autorisée est interdite.</p>
        </div>
    </div>
</div>

<?php foreach ($extraJs as $js): ?>
<script src="<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ─── Legal Modal ────────────────────────────────────────────
    var legalModal = document.getElementById('mentions-legales-modal');
    var openBtn = document.getElementById('openLegalModal');
    var closeBtn = document.getElementById('closeLegalModal');

    if (openBtn && legalModal) {
        openBtn.addEventListener('click', function(e) {
            e.preventDefault();
            legalModal.classList.add('is-visible');
        });
    }
    if (closeBtn && legalModal) {
        closeBtn.addEventListener('click', function() {
            legalModal.classList.remove('is-visible');
        });
    }
    if (legalModal) {
        legalModal.addEventListener('click', function(e) {
            if (e.target === legalModal) legalModal.classList.remove('is-visible');
        });
    }

    // ─── Auto-fermeture des alertes ─────────────────────────────
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
