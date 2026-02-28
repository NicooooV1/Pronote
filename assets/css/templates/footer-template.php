<?php
/**
 * Footer Template for the new Fronote design system
 */
?>
                <!-- End of page content -->
            </div>
        </div>
    </div>

    <!-- Common scripts -->
    <script src="<?= defined('BASE_URL') ? BASE_URL : '' ?>/assets/js/pronote-theme.js"></script>
    
    <!-- Module-specific script if needed -->
    <?php if (!empty($moduleClass) && file_exists(__DIR__ . "/../../js/modules/{$moduleClass}.js")): ?>
    <script src="<?= defined('BASE_URL') ? BASE_URL : '' ?>/assets/js/modules/<?= $moduleClass ?>.js"></script>
    <?php endif; ?>
    
    <!-- Additional scripts -->
    <?php if (isset($additionalScripts)): ?>
        <?= $additionalScripts ?>
    <?php endif; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle alert closures
        document.querySelectorAll('.alert-close').forEach(button => {
            button.addEventListener('click', function() {
                const alert = this.parentElement;
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            });
        });
        
        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert-banner:not(.alert-persistent)').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
    });
    </script>
</body>
</html>
