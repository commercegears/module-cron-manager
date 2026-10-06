<?php
declare(strict_types=1);

namespace Softaware\CronManager\Console\Command;

use Softaware\CronManager\Model\BackgroundLauncher;
use Softaware\CronManager\Model\Config;
use Softaware\CronManager\Model\Health;
use Softaware\CronManager\Model\JobConfig;
use Softaware\CronManager\Model\Notifier;
use Softaware\CronManager\Model\ScheduleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Cron health at a glance.
 */
class StatusCommand extends Command
{
    public function __construct(
        private readonly Health $health,
        private readonly ScheduleManager $scheduleManager,
        private readonly JobConfig $jobConfig,
        private readonly Config $config,
        private readonly Notifier $notifier,
        private readonly BackgroundLauncher $launcher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('softaware:cron:status')
            ->setDescription('Show cron health: last activity, failing and stuck jobs, counts, disabled and rescheduled jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->health->getReport(false);
        $output->writeln('<info>Cron health</info>');
        if ($report['last_activity'] === null) {
            $output->writeln('  Last activity:   <error>never</error>');
        } else {
            $line = sprintf('  Last activity:   %s UTC (%d min ago)', $report['last_activity'], $report['minutes_since']);
            $output->writeln($report['not_running'] ? '<error>' . $line . ' - cron is not running</error>' : $line);
        }
        $counts = $this->scheduleManager->countByStatus(gmdate('Y-m-d H:i:s', time() - 86400));
        $output->writeln('  Last 24 hours:   ' . ($counts ? implode(', ', array_map(
            static fn ($status, $count) => $status . ' ' . $count,
            array_keys($counts),
            $counts
        )) : 'no entries'));
        $all = $this->scheduleManager->countByStatus();
        $output->writeln(sprintf('  Queued now:      %d pending, %d running', $all['pending'] ?? 0, $all['running'] ?? 0));

        $output->writeln('');
        $output->writeln(sprintf('<info>Failing %d+ times in a row</info>', $this->config->getFailureThreshold()));
        if (!$report['failing']) {
            $output->writeln('  none');
        }
        foreach ($report['failing'] as $code => $info) {
            $output->writeln(sprintf('  %s (last %s UTC): %s', $code, $info['last_at'], mb_strimwidth($info['last_message'], 0, 120, '…')));
        }
        $output->writeln(sprintf('<info>Running longer than %d minutes</info>', $this->config->getMaxRuntime()));
        if (!$report['stuck']) {
            $output->writeln('  none');
        }
        foreach ($report['stuck'] as $row) {
            $output->writeln(sprintf('  #%d %s since %s UTC', $row['schedule_id'], $row['job_code'], $row['executed_at']));
        }

        $output->writeln('');
        $output->writeln('<info>Changed in the Cron Manager</info>' . ($this->config->isEnabled() ? '' : ' <comment>(module switched off: not applied)</comment>'));
        $changed = 0;
        foreach ($this->jobConfig->getJobs(false) as $job) {
            if (!$job['is_enabled']) {
                $output->writeln(sprintf('  %s: disabled (by %s)', $job['job_code'], $job['override_by'] ?: '?'));
                $changed++;
            } elseif ($job['is_overridden']) {
                $output->writeln(sprintf('  %s: %s instead of %s', $job['job_code'], $job['schedule'], $job['original_schedule'] ?: '-'));
                $changed++;
            }
        }
        if (!$changed) {
            $output->writeln('  nothing');
        }
        $output->writeln('');
        $output->writeln('<info>Settings</info>');
        $output->writeln('  Failure e-mails: ' . ($this->config->isNotifyEnabled() ? 'on, to ' . implode(', ', $this->config->getRecipients()) : 'off'));
        $output->writeln('  ' . $this->notifier->notify(true));
        $output->writeln('  PHP for "Run now": ' . $this->launcher->getPhpBinary());
        return Command::SUCCESS;
    }
}
