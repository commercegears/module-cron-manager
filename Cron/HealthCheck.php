<?php
declare(strict_types=1);

namespace Softaware\CronManager\Cron;

use Psr\Log\LoggerInterface;
use Softaware\CronManager\Model\Config;
use Softaware\CronManager\Model\Health;
use Softaware\CronManager\Model\Notifier;
use Softaware\CronManager\Model\ScheduleManager;

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
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        if ($this->config->isAutoMarkStuck()) {
            $count = $this->scheduleManager->markStuck($this->config->getMaxRuntime());
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
