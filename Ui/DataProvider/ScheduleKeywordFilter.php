<?php
declare(strict_types=1);

namespace Softaware\CronManager\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

/**
 * Keyword search of the schedule grid: job code or message contains the text.
 * (cron_schedule has no full-text index, so Magento's own full-text filter would do nothing.)
 */
class ScheduleKeywordFilter implements FilterApplierInterface
{
    public function apply(Collection $collection, Filter $filter)
    {
        $value = trim((string) $filter->getValue());
        if ($value === '' || !$collection instanceof Collection\AbstractDb) {
            return;
        }
        $like = '%' . addcslashes($value, '\\%_') . '%';
        $collection->addFieldToFilter(
            ['main_table.job_code', 'main_table.messages'],
            [['like' => $like], ['like' => $like]]
        );
    }
}
