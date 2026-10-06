<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

/**
 * Enables, disables and resets jobs, and keeps the queue consistent with the change.
 */
class JobStateManager
{
    public function __construct(
        private readonly JobConfig $jobConfig,
        private readonly OverrideStorage $overrides,
        private readonly ScheduleManager $scheduleManager,
        private readonly Health $health
    ) {
    }

    /**
     * @param string[] $codes
     * @return int number of jobs changed
     */
    public function setEnabled(array $codes, bool $enabled, ?string $user): int
    {
        $count = 0;
        foreach ($codes as $code) {
            if ($this->jobConfig->getDefinition($code) === null) {
                continue;
            }
            $this->overrides->setDisabled($code, !$enabled, $user);
            if (!$enabled) {
                // Remove what is already queued so it does not run.
                $this->scheduleManager->removeQueued(['pending'], $code);
            }
            $count++;
        }
        $this->health->clearCache();
        return $count;
    }

    /**
     * @param string[] $codes
     */
    public function reset(array $codes): int
    {
        $count = 0;
        foreach ($codes as $code) {
            if (!$this->overrides->get($code)) {
                continue;
            }
            $this->overrides->reset($code);
            $this->scheduleManager->removeQueued(['pending'], $code);
            $count++;
        }
        return $count;
    }
}
