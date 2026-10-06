/**
 * Schedule form: presets fill the expression; "use default" disables the input.
 */
define([], function () {
    'use strict';

    return function (config, form) {
        var input = form.querySelector('#softaware-cron-schedule'),
            useDefault = form.querySelector('#softaware-cron-default');

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
    };
});
