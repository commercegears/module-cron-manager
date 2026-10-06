# Changelog

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
