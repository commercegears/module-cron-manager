/**
 * Job edit page: presets fill the expression, "use default" disables the input,
 * Run Now asks for confirmation before it posts.
 */
define(['Magento_Ui/js/modal/confirm'], function (confirm) {
    'use strict';

    return function (config, form) {
        var input = form.querySelector('#commercegears-cron-schedule'),
            useDefault = form.querySelector('#commercegears-cron-default'),
            runButton = document.getElementById('commercegears-cron-run'),
            runForm = document.getElementById('commercegears-cron-run-form');

        function sync() {
            input.disabled = useDefault.checked;
            form.querySelectorAll('[data-expr]').forEach(function (button) {
                button.disabled = useDefault.checked;
            });
        }

        form.querySelectorAll('[data-expr]').forEach(function (button) {
            button.addEventListener('click', function () {
                input.value = button.dataset.expr;
                input.focus();
            });
        });
        useDefault.addEventListener('change', sync);
        sync();

        if (runButton && runForm) {
            runButton.addEventListener('click', function (event) {
                event.preventDefault();
                confirm({
                    title: config.runTitle,
                    content: config.runMessage,
                    actions: {
                        confirm: function () {
                            runForm.submit();
                        }
                    }
                });
            });
        }
    };
});
