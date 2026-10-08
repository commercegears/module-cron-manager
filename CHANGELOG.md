# Changelog

## 1.3.1 (2026-10-08)

### Added

- Product listing, FAQ and user guide (docs/).

## 1.3.0 (2026-10-07)

### Added

- Schedule grid: keyword search (job code or message).
- Confirmation dialogs before "Run Now" on the job page and before each clean-up action on the Schedule page.

### Changed

- Cron Jobs grid: "Edit" is the first row action (it opens the job page; "Change schedule" is gone); a job whose
  schedule was changed here is marked "Changed" next to its expression (hover shows the default); "Schedule Changed"
  and "Module" are hidden by default and long job codes wrap, so the grid fits narrower screens; "Errors in
  History" is now "Recent Errors".
- Job page ("Cron Job: <code>"): Back, History, Run Now and Save in the page actions bar; job details as a
  two-column table; state and schedule as a standard admin form with inline validation; the default expression is
  shown next to "Use the default schedule"; durations formatted (e.g. "1 min 5 s").
- Status labels use Magento's grid severity styles (Success / Enabled green, Error / Disabled red, Missed yellow).
- Status panel: plain-language values (Last 24 Hours: n successful, n failed, n missed), numbers in the admin
  locale, failing jobs shortened to "a, b and n more" with the full list on hover.
- Schedule clean-up panel: labelled fields with notes instead of placeholders, title-case headings and buttons.
- Timeline: labelled filters, "Period" in hours, an "Apply" button, Earlier / Now / Later navigation, the shown
  period in the admin locale; Cron Jobs and Schedule buttons in the page actions bar.
- Settings: Health Warnings before Stuck Jobs, the General group always open, every field states its default and can
  be restored to it, required values validated, e-mail recipients validated.
- Wording: "Run Now", title case for labels and buttons.

## 1.2.0 (2026-10-06)

### Changed

- Requires `softaware/module-core` instead of `softaware/module-base`. The admin menu and ACL now sit under `Softaware_Core::core` ("Softaware"); roles that had access keep it (migrated by module-core). After updating all SoftAware modules, `softaware/module-base` can be removed.

## 1.1.0 (2026-10-06)

### Added
* "Ignore These Jobs" setting (job codes or patterns with `*`): left out of the health warnings, the status bar and
  the failure e-mails, and not marked as failed automatically when they run long.
* CLI command `softaware:cron:set` to enable, disable, reschedule or reset jobs (e.g. from deployment scripts).

### Security
* "PHP Binary for Run Now" only accepts an absolute path to an executable whose name starts with `php`, in the
  settings and again before a job is started, so the setting cannot be used to run another program.

### Fixed
* The link in failure e-mails no longer contains an admin secret key from the cron process, which made it open the
  dashboard with "Invalid security or form key" when secret keys in URLs are on (the default).
* Failure e-mails no longer report "Cron did not run" when the health job itself runs less often (e.g. after
  rescheduling it to every 15 minutes) or after the module was switched off and on: gaps are measured in the cron
  history.
* When the job changes are set in `app/etc/config.php` or `env.php` (e.g. after `app:config:dump`), changes in the
  admin are refused with an explanation instead of being saved without effect; the status bar shows "read-only".
* Enabling, disabling or resetting many jobs at once saves the configuration once instead of once per job.
* The status bar no longer counts changes of jobs whose module has been removed.

## 1.0.0

* First release: cron job grid with statistics, run now (background CLI), enable/disable and schedule overrides,
  schedule grid with clean-up tools, timeline, health status bar and admin system messages, rate-limited failure
  e-mails, CLI commands `softaware:cron:list`, `softaware:cron:run`, `softaware:cron:status`.
