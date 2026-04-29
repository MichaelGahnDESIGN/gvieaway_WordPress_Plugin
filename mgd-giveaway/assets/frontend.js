(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('.mgd-giveaway-form');
        if (!form || (typeof form.checkValidity === 'function' && !form.checkValidity())) {
            return;
        }

        event.preventDefault();
        submitGiveawayForm(form);
    });

    document.addEventListener('click', function (event) {
        var openButton = event.target.closest('[data-mgd-modal]');
        if (openButton) {
            var modal = document.getElementById(openButton.getAttribute('data-mgd-modal'));
            if (modal) {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.documentElement.classList.add('mgd-modal-open');
            }
            event.preventDefault();
            return;
        }

        var submitButton = event.target.closest('.mgd-giveaway-submit');
        if (submitButton) {
            var form = submitButton.closest('.mgd-giveaway-form');
            if (form) {
                event.preventDefault();
                if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                    return;
                }
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                submitGiveawayForm(form);
            }
            return;
        }

        if (event.target.closest('[data-mgd-modal-close]')) {
            closeModal(event.target.closest('.mgd-privacy-modal'));
            event.preventDefault();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal(document.querySelector('.mgd-privacy-modal.is-open'));
        }
    });

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.mgd-privacy-modal.is-open')) {
            document.documentElement.classList.remove('mgd-modal-open');
        }
    }

    function submitGiveawayForm(form) {
        if (form.dataset.mgdSubmitting === '1') {
            return;
        }

        var submitButton = form.querySelector('button[type="submit"]');
        setFormError(form, '');
        form.dataset.mgdSubmitting = '1';

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.mgdOriginalText = submitButton.textContent;
            submitButton.textContent = 'Wird gesendet...';
        }

        if (!window.fetch || !window.FormData) {
            HTMLFormElement.prototype.submit.call(form);
            return;
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            redirect: 'follow'
        }).then(function (response) {
            if (response.url && response.url !== window.location.href) {
                window.location.href = response.url;
                return;
            }

            if (response.ok) {
                window.location.reload();
                return;
            }

            throw new Error('submit_failed');
        }).catch(function () {
            form.dataset.mgdSubmitting = '0';
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = submitButton.dataset.mgdOriginalText || submitButton.textContent;
            }
            setFormError(form, 'Die Anmeldung konnte nicht gesendet werden. Bitte lade die Seite neu und versuche es erneut.');
        });
    }

    function setFormError(form, message) {
        var error = form.querySelector('.mgd-giveaway-error');
        if (!message) {
            if (error) {
                error.remove();
            }
            return;
        }

        if (!error) {
            error = document.createElement('p');
            error.className = 'mgd-giveaway-error';
            form.appendChild(error);
        }
        error.textContent = message;
    }
})();
