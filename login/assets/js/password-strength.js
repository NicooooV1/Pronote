/**
 * password-strength.js — Vérification de complexité du mot de passe
 * Extrait de change_password.php pour externaliser le JS.
 */
document.addEventListener('DOMContentLoaded', function () {
    const pwInput      = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const indicator    = document.getElementById('strength-indicator');
    const strengthText = document.getElementById('strength-text');

    if (!pwInput || !indicator) return;

    const checks = {
        'length-check':    (pw) => pw.length >= 8,
        'uppercase-check': (pw) => /[A-Z]/.test(pw),
        'lowercase-check': (pw) => /[a-z]/.test(pw),
        'number-check':    (pw) => /[0-9]/.test(pw),
        'special-check':   (pw) => /[^A-Za-z0-9]/.test(pw),
    };

    function updateCheck(id, passed) {
        const el = document.getElementById(id);
        if (!el) return;
        const icon = el.querySelector('i');
        if (icon) {
            icon.className = passed ? 'fas fa-check valid' : 'fas fa-times invalid';
        }
    }

    pwInput.addEventListener('input', function () {
        const pw = this.value;
        let score = 0;
        const total = Object.keys(checks).length;

        for (const [id, fn] of Object.entries(checks)) {
            const passed = fn(pw);
            updateCheck(id, passed);
            if (passed) score++;
        }

        const pct = Math.round((score / total) * 100);
        indicator.style.width = pct + '%';

        if (pct < 40) {
            indicator.style.backgroundColor = '#ff3b30';
            if (strengthText) { strengthText.textContent = 'Faible'; strengthText.style.color = '#ff3b30'; }
        } else if (pct < 80) {
            indicator.style.backgroundColor = '#ff9500';
            if (strengthText) { strengthText.textContent = 'Moyen'; strengthText.style.color = '#ff9500'; }
        } else {
            indicator.style.backgroundColor = '#34c759';
            if (strengthText) { strengthText.textContent = 'Fort'; strengthText.style.color = '#34c759'; }
        }

        // Also re-validate confirm if filled
        if (confirmInput && confirmInput.value) {
            validateConfirm();
        }
    });

    function validateConfirm() {
        if (!confirmInput) return;
        const match = confirmInput.value === pwInput.value;
        confirmInput.classList.toggle('is-valid', match && confirmInput.value.length > 0);
        confirmInput.classList.toggle('is-invalid', !match && confirmInput.value.length > 0);
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', validateConfirm);
    }

    // Toggle password visibility
    document.querySelectorAll('.visibility-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = this.closest('.input-group').querySelector('input');
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        });
    });
});
