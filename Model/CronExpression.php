<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Validates cron expressions with Magento's own parser and previews the next run times.
 */
class CronExpression
{
    public function __construct(private readonly ScheduleFactory $scheduleFactory)
    {
    }

    /**
     * @throws LocalizedException
     */
    public function validate(string $expression): string
    {
        $expression = trim(preg_replace('/\s+/', ' ', $expression) ?? '');
        if ($expression === '') {
            throw new LocalizedException(__('Enter a cron expression.'));
        }
        try {
            $this->scheduleFactory->create()->setCronExpr($expression);
        } catch (\Exception $e) {
            throw new LocalizedException(__('"%1" is not a valid cron expression: %2', $expression, $e->getMessage()));
        }
        return $expression;
    }

    /**
     * Next run times (Unix timestamps) within the coming week.
     *
     * @return int[]
     */
    public function getNextRuns(string $expression, int $count = 5, ?int $from = null): array
    {
        if (trim($expression) === '') {
            return [];
        }
        try {
            $schedule = $this->scheduleFactory->create()->setCronExpr($expression);
        } catch (\Exception) {
            return [];
        }
        $time = (int) (floor(($from ?? time()) / 60) * 60) + 60;
        $end = $time + 7 * 86400;
        $runs = [];
        for (; $time < $end && count($runs) < $count; $time += 60) {
            // Magento matches cron expressions in the store (admin) timezone.
            if ($schedule->setScheduledAt($time)->trySchedule()) {
                $runs[] = $time;
            }
        }
        return $runs;
    }
}
