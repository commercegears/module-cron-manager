/**
 * Timeline keyboard access: the chart is one tab stop; the arrow keys move between bars
 * (left/right in a row, up/down to the nearest bar of the next row with bars) and the
 * details of the focused bar are shown below the chart (the title tooltip needs a mouse).
 */
define([], function () {
    'use strict';

    return function (config, chart) {
        var detail = document.querySelector(config.detail),
            rows = Array.prototype.filter.call(
                chart.querySelectorAll('.softaware-cron-row:not(.softaware-cron-axis)'),
                function (row) {
                    return row.querySelector('.softaware-cron-bar');
                }
            ).map(function (row) {
                return Array.prototype.slice.call(row.querySelectorAll('.softaware-cron-bar'));
            });

        function moveTo(bar) {
            chart.querySelectorAll('.softaware-cron-bar[tabindex="0"]').forEach(function (el) {
                el.setAttribute('tabindex', '-1');
            });
            bar.setAttribute('tabindex', '0');
            bar.focus();
        }

        function position(bar) {
            var r, i;

            for (r = 0; r < rows.length; r++) {
                i = rows[r].indexOf(bar);
                if (i !== -1) {
                    return [r, i];
                }
            }
            return null;
        }

        function nearest(list, left) {
            return list.reduce(function (best, el) {
                return Math.abs(el.offsetLeft - left) < Math.abs(best.offsetLeft - left) ? el : best;
            }, list[0]);
        }

        chart.addEventListener('keydown', function (event) {
            var pos = position(event.target), r, i, target = null;

            if (!pos) {
                return;
            }
            r = pos[0];
            i = pos[1];
            switch (event.key) {
                case 'ArrowRight': target = rows[r][i + 1]; break;
                case 'ArrowLeft': target = rows[r][i - 1]; break;
                case 'Home': target = rows[r][0]; break;
                case 'End': target = rows[r][rows[r].length - 1]; break;
                case 'ArrowDown': target = rows[r + 1] ? nearest(rows[r + 1], event.target.offsetLeft) : null; break;
                case 'ArrowUp': target = rows[r - 1] ? nearest(rows[r - 1], event.target.offsetLeft) : null; break;
                default: return;
            }
            event.preventDefault();
            if (target) {
                moveTo(target);
            }
        });

        if (!detail) {
            return;
        }
        chart.addEventListener('focusin', function (event) {
            if (event.target.classList.contains('softaware-cron-bar')) {
                detail.textContent = event.target.getAttribute('title') || '';
                detail.hidden = false;
            }
        });
        chart.addEventListener('focusout', function () {
            detail.hidden = true;
        });
    };
});
