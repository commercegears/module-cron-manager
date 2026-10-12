# Changelog

## 2.0.1 (2026-10-12)
- Improvement: user guide screenshots and links updated for the CommerceGears name

## 2.0.0 (2026-10-11)
- Improvement: renamed to CommerceGears (new Composer package, admin menu and settings paths)

## 1.3.2 (2026-10-11)
- Fix: the State and Last Status filters list their options; jobs without a recent run show "No recent runs".
- Improvement: the row menu offers Disable or Enable to match the job, both with a confirmation.
- Improvement: disabled jobs show "Not queued (job disabled)" in the Schedule column.
- Improvement: timeline bars can be reached with the arrow keys, with their details shown below the chart.
- Improvement: settings and clean-up values are checked when saved (whole numbers within sensible ranges).

## 1.3.1 (2026-10-08)
- New: product listing, FAQ and user guide.

## 1.3.0 (2026-10-07)
- New: keyword search on the Schedule grid; confirmations before Run Now and each clean-up action.
- Improvement: tidier Cron Jobs grid with Edit first, a "Changed" mark on rescheduled jobs, narrower columns.
- Improvement: job page with action buttons, a details table and a form with inline validation.
- Improvement: status labels in Magento's grid colours, a plain-language status panel and clearer filters.
- Improvement: settings state their defaults, can be restored and check required values and email recipients.

## 1.2.0 (2026-10-06)
- Improvement: admin menu and permissions moved under "CommerceGears".

## 1.1.0 (2026-10-06)
- New: "Ignore These Jobs" setting and a command to enable, disable, reschedule or reset jobs.
- Fix: failure email links work with secret URL keys; no more false "Cron did not run" emails.
- Improvement: "PHP Binary for Run Now" only accepts a PHP executable.
- Improvement: changes locked in configuration files are refused with a reason; faster mass actions.

## 1.0.0 (2026-10-06)
- New: first release with a cron job grid, Run Now, enable, disable, reschedule and schedule clean-up.
- New: timeline, health status panel, admin messages, failure emails and command line tools.
