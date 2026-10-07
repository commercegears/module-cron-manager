/**
 * Asks for confirmation (Magento confirm modal) before a clean-up form is submitted.
 */
define(['Magento_Ui/js/modal/confirm'], function (confirm) {
    'use strict';

    return function (config, form) {
        var confirmed = false;

        form.addEventListener('submit', function (event) {
            if (confirmed) {
                return;
            }
            event.preventDefault();
            confirm({
                title: config.title,
                content: form.dataset.confirm,
                actions: {
                    confirm: function () {
                        confirmed = true;
                        form.submit();
                    }
                }
            });
        });
    };
});
