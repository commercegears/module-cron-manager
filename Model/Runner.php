<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Cron\Observer\ProcessCronQueueObserver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs one cron job now, outside the schedule, the way Magento's cron does: same lock,
 * a cron_schedule entry for the run, and the result (status, message, duration) recorded on it.
 */
class Runner
{
    public function __construct(
        private readonly JobConfig $jobConfig,
        private readonly ScheduleFactory $scheduleFactory,
        private readonly LockManagerInterface $lockManager,
        private readonly ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{status: string, message: string, duration: float, schedule_id: int}
     * @throws LocalizedException when the job does not exist
     */
    public function run(string $jobCode, string $trigger = 'cli', ?string $user = null): array
    {
        $definition = $this->jobConfig->getDefinition($jobCode);
        if ($definition === null) {
            throw new LocalizedException(__('There is no cron job "%1".', $jobCode));
        }
        $now = gmdate('Y-m-d H:i:s');
        /** @var Schedule $schedule */
        $schedule = $this->scheduleFactory->create();
        $schedule->setJobCode($jobCode)
            ->setStatus(Schedule::STATUS_RUNNING)
            ->setCreatedAt($now)
            ->setScheduledAt($now)
            ->setExecutedAt($now)
            ->setMessages((string) __('Started manually (%1%2) by Softaware Cron Manager.', $trigger, $user ? ', ' . $user : ''));
        $schedule->save();

        $lockName = ProcessCronQueueObserver::LOCK_PREFIX . md5($definition['group'] . '_' . $jobCode);
        if (!$this->lockManager->lock($lockName, 0)) {
            $message = (string) __('Not started: the job is already running.');
            $this->finish($schedule, Schedule::STATUS_ERROR, $message);
            return ['status' => Schedule::STATUS_ERROR, 'message' => $message, 'duration' => 0.0, 'schedule_id' => (int) $schedule->getId()];
        }
        $start = microtime(true);
        $this->logger->info(sprintf('Cron Manager: running %s (%s%s)', $jobCode, $trigger, $user ? ', ' . $user : ''));
        try {
            $model = $this->objectManager->create($definition['instance']);
            $callback = [$model, $definition['method']];
            if (!is_callable($callback)) {
                throw new LocalizedException(__('Invalid callback %1::%2.', $definition['instance'], $definition['method']));
            }
            $callback($schedule);
            $duration = microtime(true) - $start;
            $message = trim((string) __('Ran manually (%1%2) in %3 s.', $trigger, $user ? ', ' . $user : '', round($duration, 2)));
            $this->finish($schedule, Schedule::STATUS_SUCCESS, $message);
            $this->logger->info(sprintf('Cron Manager: %s finished in %.2f s', $jobCode, $duration));
            return ['status' => Schedule::STATUS_SUCCESS, 'message' => $message, 'duration' => $duration, 'schedule_id' => (int) $schedule->getId()];
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $message = mb_substr($e->getMessage(), 0, 4000);
            $this->finish($schedule, Schedule::STATUS_ERROR, $message);
            $this->logger->error(sprintf('Cron Manager: %s failed after %.2f s: %s', $jobCode, $duration, $e->getMessage()), ['exception' => $e]);
            return ['status' => Schedule::STATUS_ERROR, 'message' => $message, 'duration' => $duration, 'schedule_id' => (int) $schedule->getId()];
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }

    private function finish(Schedule $schedule, string $status, string $message): void
    {
        $schedule->setStatus($status)
            ->setFinishedAt(gmdate('Y-m-d H:i:s'))
            ->setMessages($message);
        $schedule->save();
    }
}
