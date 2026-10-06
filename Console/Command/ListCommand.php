<?php
declare(strict_types=1);

namespace Softaware\CronManager\Console\Command;

use Softaware\CronManager\Model\JobConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{
    public function __construct(private readonly JobConfig $jobConfig)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('softaware:cron:list')
            ->setDescription('List all cron jobs with schedule, state, last run and average duration')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Only jobs of this cron group')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Only jobs whose code, class or module contains this text')
            ->addOption('disabled', null, InputOption::VALUE_NONE, 'Only disabled jobs')
            ->addOption('overridden', null, InputOption::VALUE_NONE, 'Only jobs with a changed schedule')
            ->addOption('failed', null, InputOption::VALUE_NONE, 'Only jobs whose last run failed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $group = $input->getOption('group');
        $search = mb_strtolower((string) $input->getOption('search'));
        $table = new Table($output);
        $table->setHeaders(['Job code', 'Group', 'Schedule', 'State', 'Last run (UTC)', 'Last status', 'Avg s', 'Module']);
        $count = 0;
        foreach ($this->jobConfig->getJobs() as $job) {
            if ($group && $job['group'] !== $group) {
                continue;
            }
            if ($search !== '' && !str_contains(mb_strtolower($job['job_code'] . ' ' . $job['callback'] . ' ' . $job['module']), $search)) {
                continue;
            }
            if ($input->getOption('disabled') && $job['is_enabled']) {
                continue;
            }
            if ($input->getOption('overridden') && !$job['is_overridden']) {
                continue;
            }
            if ($input->getOption('failed') && $job['last_status'] !== 'error') {
                continue;
            }
            $state = !$job['is_enabled'] ? 'disabled' : ($job['schedule'] === '' ? 'no schedule' : 'enabled');
            $schedule = $job['schedule'] !== '' ? $job['schedule'] : '-';
            if ($job['is_overridden']) {
                $schedule .= ' (default ' . ($job['original_schedule'] ?: '-') . ')';
            }
            $table->addRow([
                $job['job_code'],
                $job['group'],
                $schedule,
                $state,
                $job['last_run'] ?: '-',
                $job['last_status'] ?: '-',
                $job['avg_duration'] ?? '-',
                $job['module'],
            ]);
            $count++;
        }
        $table->render();
        $output->writeln(sprintf('%d job(s).', $count));
        return Command::SUCCESS;
    }
}
