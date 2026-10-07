/**
 * Job edit page: presets fill the expression, "use default" disables the input,
 * Run Now asks for confirmation before it posts.
 */
define(['Magento_Ui/js/modal/confirm'], function (confirm) {
    'use strict';

    return function (config, form) {
        var input = form.querySelector('#softaware-cron-schedule'),
            useDefault = form.querySelector('#softaware-cron-default'),
            runButton = document.getElementById('softaware-cron-run'),
            runForm = document.getElementById('softaware-cron-run-form');

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
