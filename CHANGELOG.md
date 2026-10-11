# Changelog

## 1.3.2 (2026-10-11)
- Row menu offers Disable for enabled jobs and Enable for disabled ones, both with a confirmation.
- State and Last Status filters list their options; jobs without a recent run show "No recent runs".
- Disabled jobs show "Not queued (job disabled)" in the Schedule column.
- Timeline bars can be reached with the keyboard (arrow keys), with their details shown below the chart.
- Settings and clean-up values are checked on the server (whole numbers within sensible ranges).

## 1.3.1 (2026-10-08)
- Product listing, FAQ and user guide.

## 1.3.0 (2026-10-07)
- Schedule grid keyword search and confirmations before Run Now and each clean-up action.
- Tidier Cron Jobs grid: Edit first, "Changed" mark on rescheduled jobs, narrower default columns.
- Job page with page action buttons, a details table and an admin form with inline validation.
- Status labels in Magento's grid colours; plain-language status panel; labelled timeline filters.
- Settings state their defaults, can be restored and validate required values and e-mail recipients.

## 1.2.0 (2026-10-06)
- Requires softaware/module-core instead of module-base; menu and permissions move under "Softaware".

## 1.1.0 (2026-10-06)
- "Ignore These Jobs" setting and a CLI command to enable, disable, reschedule or reset jobs.
- "PHP Binary for Run Now" only accepts a PHP executable.
- Failure e-mail links work with secret URL keys; no false "Cron did not run" e-mails.
- Changes locked in app/etc files are refused with an explanation; faster mass actions.

## 1.0.0 (2026-10-06)
- First release: cron job grid, Run Now, enable/disable and reschedule, schedule grid with clean-up and timeline.
- Health status panel, admin messages, failure e-mails and CLI commands (list, run, status).
