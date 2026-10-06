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
        $known = array_values(array_filter(
            array_unique(array_map('strval', $codes)),
            fn (string $code) => $this->jobConfig->getDefinition($code) !== null
        ));
        // One configuration save for all jobs (each save cleans and reloads the configuration).
        $this->overrides->setDisabledMany($known, !$enabled, $user);
        if (!$enabled) {
            foreach ($known as $code) {
                // Remove what is already queued so it does not run.
                $this->scheduleManager->removeQueued(['pending'], $code);
            }
        }
        $this->health->clearCache();
        return count($known);
    }

    /**
     * @param string[] $codes
     */
    public function reset(array $codes): int
    {
        $changed = array_values(array_filter(
            array_unique(array_map('strval', $codes)),
            fn (string $code) => (bool) $this->overrides->get($code)
        ));
        if (!$changed) {
            return 0;
        }
        $this->overrides->resetMany($changed);
        foreach ($changed as $code) {
            $this->scheduleManager->removeQueued(['pending'], $code);
        }
        $this->health->clearCache();
        return count($changed);
    }
}
