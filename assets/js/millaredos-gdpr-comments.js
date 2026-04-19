'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const consentCheckbox = document.getElementById('mgc_privacy_consent');
    const commentForm = document.getElementById('commentform');
    const submitButton = document.getElementById('submit'); // Default WP submit button ID

    if (!consentCheckbox || !commentForm) {
        return;
    }

    // Initial state
    if (submitButton) {
        submitButton.disabled = !consentCheckbox.checked;
        if (submitButton.disabled) {
            submitButton.classList.add('disabled', 'mgc-disabled');
            submitButton.style.opacity = '0.5';
            submitButton.style.cursor = 'not-allowed';
        }
    }

    // On Change
    consentCheckbox.addEventListener('change', function () {
        if (submitButton) {
            submitButton.disabled = !this.checked;
            if (this.checked) {
                submitButton.classList.remove('disabled', 'mgc-disabled');
                submitButton.style.opacity = '1';
                submitButton.style.cursor = 'pointer';
            } else {
                submitButton.classList.add('disabled', 'mgc-disabled');
                submitButton.style.opacity = '0.5';
                submitButton.style.cursor = 'not-allowed';
            }
        }
    });

    // Form Submit Interception
    commentForm.addEventListener('submit', function (e) {
        if (!consentCheckbox.checked) {
            e.preventDefault();
            // Use localized string or fallback
            const msg = (typeof mgc_vars !== 'undefined' && mgc_vars.alert_msg)
                ? mgc_vars.alert_msg
                : 'Please, accept the Privacy Policy to continue.';
            alert(msg);
            consentCheckbox.focus();
        }
    });
});
