<?php
declare(strict_types=1);

namespace Softaware\CronManager\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Softaware\CronManager\Model\CronExpression;
use Softaware\CronManager\Model\Health;
use Softaware\CronManager\Model\JobConfig;
use Softaware\CronManager\Model\JobStateManager;
use Softaware\CronManager\Model\OverrideStorage;
use Softaware\CronManager\Model\ScheduleManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Enable, disable or reschedule cron jobs from the command line (e.g. in deployment scripts),
 * with the same effect as the Cron Jobs page.
 */
class SetCommand extends Command
{
    public function __construct(
        private readonly JobConfig $jobConfig,
        private readonly JobStateManager $stateManager,
        private readonly OverrideStorage $overrides,
        private readonly CronExpression $cronExpression,
        private readonly ScheduleManager $scheduleManager,
        private readonly Health $health
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('softaware:cron:set')
            ->setDescription('Enable, disable or reschedule cron jobs (same as the Cron Jobs page)')
            ->addArgument('job_codes', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Cron job codes, see softaware:cron:list')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Disable the jobs')
            ->addOption('enable', null, InputOption::VALUE_NONE, 'Enable the jobs')
            ->addOption('schedule', null, InputOption::VALUE_REQUIRED, 'Run the jobs on this cron expression, e.g. "*/15 * * * *"')
            ->addOption('default', null, InputOption::VALUE_NONE, 'Remove all changes: enabled, default schedule');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $codes = array_values(array_unique(array_map('strval', (array) $input->getArgument('job_codes'))));
        $unknown = array_filter($codes, fn (string $code) => $this->jobConfig->getDefinition($code) === null);
        if ($unknown) {
            $output->writeln('<error>Unknown job code(s): ' . implode(', ', $unknown) . '</error>');
            return Command::FAILURE;
        }
        $enable = (bool) $input->getOption('enable');
        $disable = (bool) $input->getOption('disable');
        $reset = (bool) $input->getOption('default');
        $schedule = $input->getOption('schedule');
        if (($enable && $disable) || ($reset && ($enable || $disable || $schedule !== null))
            || (!$enable && !$disable && !$reset && $schedule === null)
        ) {
            $output->writeln('<error>Use --enable or --disable and/or --schedule, or --default on its own.</error>');
            return Command::FAILURE;
        }
        $user = 'cli';
        try {
            if ($reset) {
                $count = $this->stateManager->reset($codes);
                $output->writeln(sprintf('<info>%d job(s) use their default schedule again.</info>', $count));
                return Command::SUCCESS;
            }
            if ($schedule !== null) {
                $expression = $this->cronExpression->validate((string) $schedule);
                foreach ($codes as $code) {
                    $job = $this->jobConfig->getJob($code);
                    $this->overrides->setSchedule($code, $expression === ($job['original_schedule'] ?? null) ? null : $expression, $user);
                    $this->scheduleManager->removeQueued(['pending'], $code);
                }
                $output->writeln(sprintf('<info>%s now run on "%s".</info>', implode(', ', $codes), $expression));
            }
            if ($enable || $disable) {
                $this->stateManager->setEnabled($codes, $enable, $user);
                $output->writeln(sprintf('<info>%s %s.</info>', implode(', ', $codes), $enable ? 'enabled' : 'disabled'));
            }
            $this->health->clearCache();
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
