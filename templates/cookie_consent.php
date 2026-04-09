<?php
/**
 * Cookie consent banner (RGPD).
 * Included by shared_header.php.
 * Only shows if the user has not yet consented.
 */
$cc = app('client_cache');
$cookieConsent = $cc->get('cookie_consent');
if ($cookieConsent === null):
?>
<div id="cookie-consent-banner" class="cookie-consent" role="dialog" aria-label="<?= __('common.cookie_consent') ?>">
    <div class="cookie-consent__inner">
        <p class="cookie-consent__text">
            <?= __('common.cookie_consent_text', ['default' => 'Ce site utilise des cookies essentiels au fonctionnement. En continuant, vous acceptez leur utilisation.']) ?>
        </p>
        <div class="cookie-consent__actions">
            <button type="button" class="btn btn-sm btn-primary" id="cookie-accept-all">
                <?= __('common.accept_all', ['default' => 'Accepter tout']) ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline" id="cookie-essential-only">
                <?= __('common.essential_only', ['default' => 'Essentiels uniquement']) ?>
            </button>
        </div>
    </div>
</div>
<script nonce="<?= $_hdr_nonce ?? '' ?>">
(function() {
    function setCookieConsent(level) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?= defined("BASE_URL") ? BASE_URL : "" ?>/API/endpoints/cookie_consent.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content || '');
        xhr.send('level=' + encodeURIComponent(level));
        var banner = document.getElementById('cookie-consent-banner');
        if (banner) banner.style.display = 'none';
    }
    var acceptBtn = document.getElementById('cookie-accept-all');
    var essentialBtn = document.getElementById('cookie-essential-only');
    if (acceptBtn) acceptBtn.addEventListener('click', function() { setCookieConsent('all'); });
    if (essentialBtn) essentialBtn.addEventListener('click', function() { setCookieConsent('essential'); });
})();
</script>
<?php endif; ?>
