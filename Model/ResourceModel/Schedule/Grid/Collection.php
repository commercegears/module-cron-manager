<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model\ResourceModel\Schedule\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * cron_schedule rows for the schedule grid, with the run duration in seconds.
 */
class Collection extends SearchResult
{
    private const DURATION = 'TIMESTAMPDIFF(SECOND, main_table.executed_at, COALESCE(main_table.finished_at, IF(main_table.status = \'running\', UTC_TIMESTAMP(), NULL)))';

    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->columns(['duration' => new \Zend_Db_Expr(self::DURATION)]);
        $this->addFilterToMap('duration', new \Zend_Db_Expr(self::DURATION));
        $this->addFilterToMap('schedule_id', 'main_table.schedule_id');
        return $this;
    }
}
