(function () {
    'use strict';

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
})();
