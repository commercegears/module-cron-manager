# Softaware Cron Manager

See and control Magento's cron from the admin: every job with its schedule and run statistics, the schedule
(cron_schedule) as a grid and as a timeline, run jobs now, enable/disable and reschedule jobs, clean up the queue,
and get warned when cron stops or a job keeps failing.

User guide: [docs/user-guide.md](docs/user-guide.md) · FAQ: [docs/faq.md](docs/faq.md)

Admin: **Softaware > Cron Manager**: Cron Jobs, Schedule, Timeline, Settings.

## Features

* **Cron Jobs grid:** all jobs from `crontab.xml` and config-based schedules: code, group, effective and default
  schedule, enabled/disabled, schedule changed, last run, last status, average duration, errors in history, module,
  class::method. Filters, keyword search, sorting, column chooser, saved views. A job whose schedule was changed here
  is marked "Changed" in the Schedule column (hover for the default). Row actions match the job's state; Run Now,
  Enable and Disable are POST requests with a confirmation.
  Row actions: Edit, Run Now, Enable/Disable, History. Mass actions: Run Now (up to 20), Enable, Disable,
  Restore Default Schedule.
* **Edit a job:** state (Enabled/Disabled) and cron expression validated with Magento's parser, presets, preview of the next runs (store
  timezone). Overrides are stored in the configuration (`softaware_cron_manager/overrides/jobs`, JSON), so they can
  be exported with `app:config:dump`. A plugin on `Magento\Cron\Model\ConfigInterface::getJobs()` applies them:
  a disabled job keeps its definition but has no schedule (nothing new is queued; its pending entries are removed);
  a rescheduled job gets the new expression (also replacing a config-path schedule).
* **Run Now:** starts `bin/magento softaware:cron:run <job>` in the background (no admin request waits for a job).
  The run takes the same lock as Magento's cron, is recorded in `cron_schedule` (status, start/finish, message,
  who started it) and logged to `var/log/softaware_cron_manager.log`. Disabled jobs can be run manually.
* **Schedule grid:** cron_schedule with keyword search (job code or message), filters (job, status, dates, message),
  duration column, coloured statuses, mass delete, and a clean-up panel (each action asks for confirmation): remove pending/missed entries, mark stuck "running" jobs as failed (configurable
  max runtime), clean history (by status and age, optionally for one job).
* **Timeline:** Gantt-like chart per job for 1 to 24 hours (earlier/later/now navigation, group and job filters,
  "only problems"), bars coloured by status with details on hover or keyboard focus (arrow keys), a "now" line.
* **Health:** a status panel on the Cron Jobs and Schedule pages (cron activity, last 24 hours, queue, failing jobs,
  job changes), and admin system messages when cron has not run for N minutes,
  a job failed N times in a row, or a job runs longer than the max runtime. Cached for 3 minutes.
* **Failure e-mails** (off by default): a cron job (`softaware_cron_manager_health`, every 5 minutes) e-mails new
  failures, at most once per interval (failures in between are collected), and reports gaps when cron itself did
  not run (measured from the cron history of the last hour, so rescheduling the health job does not cause false
  alarms). It can also mark stuck jobs as failed automatically. Template: "Cron Manager: Failed Cron Jobs".
* **Ignore jobs:** job codes (or patterns such as `amazon_*`) listed in "Ignore These Jobs" are left out of the
  warnings, the status panel and the e-mails, and are not marked as failed when they run long.

## Configuration

Stores > Configuration > Softaware > Cron Manager (`softaware_cron_manager/...`): Enabled, PHP Binary for Run Now
(auto-detected when empty; only an absolute path to an executable whose name starts with `php` is accepted), health
warnings (minutes without cron, failures in a row, jobs to ignore), stuck jobs (max runtime, mark automatically),
failure e-mails (on/off, recipients, sender, template, minimum interval). Every field shows its default and can be
restored to it.

Job changes are stored in `softaware_cron_manager/overrides/jobs`. If that value is set in `app/etc/config.php` or
`env.php` (for example after `app:config:dump`), it wins over the database: the Cron Manager then shows the changes as
read-only and refuses to change them, instead of saving changes that would have no effect.

When the module is switched off, overrides are not applied (all jobs use their default schedule) and health checks
and e-mails stop; the pages remain available.

## CLI

```
bin/magento softaware:cron:list [--group=] [--search=] [--disabled] [--overridden] [--failed]
bin/magento softaware:cron:run <job_code> [--trigger=cli] [--user=]
bin/magento softaware:cron:status
bin/magento softaware:cron:set <job_code> [<job_code> …] [--enable|--disable] [--schedule="*/15 * * * *"]
bin/magento softaware:cron:set <job_code> [<job_code> …] --default
```

`softaware:cron:set` makes the same changes as the Cron Jobs page (useful in deployment scripts); `--default` removes
all changes of the jobs.

## ACL

View Cron Jobs (Run Jobs Now; Enable, Disable and Reschedule Jobs), View Schedule and Timeline (Clean Up Schedule),
Settings.

## Compatibility

Magento Open Source / Adobe Commerce 2.4.x (developed on 2.4.9), PHP 8.2 to 8.5, MySQL 8 / MariaDB 10.4+ (window
functions are used for the "failing in a row" check). Admin only.

## Limitations

* "Run Now" needs `exec`-style process start (Magento's `Shell`) and a PHP CLI binary; set it in the settings if it
  is not found.
* Schedule history only goes back as far as Magento keeps it (Stores > Configuration > Advanced > System > Cron);
  averages and the timeline are based on that history.
* The "cron not running" e-mail can only be sent once cron runs again; the admin warning covers the time in between.
