<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Cron;

use Psr\Log\LoggerInterface;
use CommerceGears\CronManager\Model\Config;
use CommerceGears\CronManager\Model\Health;
use CommerceGears\CronManager\Model\JobConfig;
use CommerceGears\CronManager\Model\Notifier;
use CommerceGears\CronManager\Model\ScheduleManager;

/**
 * Every 5 minutes: mark stuck jobs as failed (if configured) and send failure e-mails.
 */
class HealthCheck
{
    public function __construct(
        private readonly Config $config,
        private readonly ScheduleManager $scheduleManager,
        private readonly Notifier $notifier,
        private readonly Health $health,
        private readonly LoggerInterface $logger,
        private readonly JobConfig $jobConfig
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            // Keep the check time current, so switching the module on again is not reported as a cron gap.
            $this->notifier->recordCheck();
            return;
        }
        if ($this->config->isAutoMarkStuck()) {
            $except = array_values(array_filter(
                array_keys($this->jobConfig->getJobs(false)),
                fn (string $code) => $this->config->isIgnored($code)
            ));
            $count = $this->scheduleManager->markStuck($this->config->getMaxRuntime(), null, $except);
            if ($count) {
                $this->logger->warning(sprintf('Cron Manager: marked %d stuck job(s) as failed.', $count));
            }
        }
        $result = $this->notifier->notify();
        if (str_starts_with($result, 'Sent') || str_starts_with($result, 'Sending failed')) {
            $this->logger->info('Cron Manager e-mail: ' . $result);
        }
        $this->health->clearCache();
    }
}
