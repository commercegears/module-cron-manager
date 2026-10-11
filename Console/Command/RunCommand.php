<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use CommerceGears\CronManager\Model\Runner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run one cron job now, in the cron ("crontab") area, recorded in cron_schedule.
 * Also used by "Run now" in the admin, which starts this command in the background.
 */
class RunCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly ObjectManagerInterface $objectManager,
        private readonly ConfigLoaderInterface $configLoader,
        private readonly AreaList $areaList,
        private readonly Runner $runner
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('commercegears:cron:run')
            ->setDescription('Run a cron job now (also runs disabled jobs); the run is recorded in the cron schedule')
            ->addArgument('job_code', InputArgument::REQUIRED, 'Cron job code, see commercegears:cron:list')
            ->addOption('trigger', null, InputOption::VALUE_REQUIRED, 'Who started the run (shown in the schedule)', 'cli')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Admin user who started the run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
            $this->objectManager->configure($this->configLoader->load(Area::AREA_CRONTAB));
            $this->areaList->getArea(Area::AREA_CRONTAB)->load(Area::PART_TRANSLATE);
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // Area already set.
        }
        $code = (string) $input->getArgument('job_code');
        $trigger = preg_replace('/[^\w-]/', '', (string) $input->getOption('trigger')) ?: 'cli';
        $user = $input->getOption('user') !== null ? preg_replace('/[^\w.@-]/', '', (string) $input->getOption('user')) : null;
        $output->writeln(sprintf('<info>Running %s…</info>', $code));
        try {
            $result = $this->runner->run($code, $trigger, $user);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln(sprintf(
            '%s (schedule #%d, %.2f s): %s',
            $result['status'] === 'success' ? '<info>success</info>' : '<error>' . $result['status'] . '</error>',
            $result['schedule_id'],
            $result['duration'],
            $result['message']
        ));
        return $result['status'] === 'success' ? Command::SUCCESS : Command::FAILURE;
    }
}
