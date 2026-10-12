# Cron Manager for Magento 2: FAQ

**Does Cron Manager replace Magento's cron?**
No. Magento's own cron (`bin/magento cron:run` from the server's crontab) still runs the jobs. Cron Manager shows
what cron does, lets you change which jobs run and when, and warns you when something goes wrong. You still need a
working system crontab.

**Which jobs does it show?**
Every job Magento knows: the jobs from the `crontab.xml` files of Magento and your extensions, and jobs whose schedule
comes from the configuration. The grid shows the group, the schedule in use and the default one, the state, the last
run and status, the average duration, recent errors, the module and the class and method.

**How does "Run Now" work?**
It starts `bin/magento commercegears:cron:run <job>` in the background, so the admin page does not wait for the job. The
run takes the same lock as Magento's cron, so it cannot overlap with a scheduled run of the same job. It is written to
the cron schedule with its status, start and finish time, a message with who started it, and to
`var/log/commercegears_cron_manager.log`. You can run up to 20 jobs at once from the grid, and a disabled job can still be
run manually.

**What happens when I disable a job?**
The job keeps its definition but gets no schedule, so Magento queues nothing new for it, and its pending entries are
removed. Enable it again, or use "Restore Default Schedule", to go back to the original behaviour.

**Where are my changes stored? Are they lost on deployment?**
They are stored in the configuration (`commercegears_cron_manager/overrides/jobs`), so they survive deployments and can
be exported with `bin/magento app:config:dump`. If that value is set in `app/etc/config.php` or `env.php`, it wins over
the database: the Cron Manager then shows the changes as read-only and refuses to change them, instead of saving
changes that would have no effect.

**Can I change a job's schedule without writing code?**
Yes. Open the job, enter a cron expression or pick a preset (every minute, every 5 or 15 minutes, hourly, every 6
hours, daily at 03:00, weekly on Sunday at 03:00), and check the preview of the next runs. The expression is checked
with Magento's own cron parser before it is saved.

**How do I know that cron has stopped?**
An admin system message appears when cron has not run for the number of minutes you set (default 15). Further
messages appear when a job fails a set number of times in a row (default 3) or runs longer than the maximum runtime
(default 120 minutes). The status panel on the Cron Jobs and Schedule pages shows the same information at a glance.

**Can it e-mail me about failures?**
Yes, but it is off by default. Switch on "Send E-mails About Failed Jobs" and enter one or more recipients. A check
runs every 5 minutes and sends at most one e-mail per interval you choose (default 60 minutes); failures in between
are collected into the next e-mail. It also reports when cron itself did not run, once cron runs again.

**A third-party job fails all the time and fills my warnings. What can I do?**
Add its job code to "Ignore These Jobs". Patterns with `*` work too, for example `amazon_*`. Ignored jobs are left out
of the warnings, the status panel and the e-mails, and are not marked as failed when they run long.

**Can I give a colleague read-only access?**
Yes. The permissions are separate: View Cron Jobs, Run Jobs Now, Enable, Disable and Reschedule Jobs, View Schedule
and Timeline, Clean Up Schedule, and Cron Manager Settings. Set them under System > Permissions > User Roles. A role with only View Cron Jobs and View Schedule and Timeline
can look at everything but change nothing.

**Does it change anything on the storefront?**
No. It is an admin-only module and adds nothing to storefront pages.

**What happens when I switch the module off?**
Your job changes are no longer applied, so all jobs use their default schedule, and the health checks and e-mails
stop. The Cron Manager pages stay available, and your changes come back when you switch it on again.

**Which Magento and PHP versions are supported?**
Magento Open Source and Adobe Commerce 2.4.6 to 2.4.9, PHP 8.2 to 8.5 (Magento 2.4.6: PHP 8.2), with MySQL 8 or MariaDB 10.4 or later.
