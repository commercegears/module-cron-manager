<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Run statistics per job from the cron_schedule history.
 */
class ScheduleStats
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getStats(): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('cron_schedule');
        $duration = 'TIMESTAMPDIFF(SECOND, executed_at, finished_at)';
        $select = $connection->select()
            ->from($table, [
                'job_code',
                'last_run' => 'MAX(executed_at)',
                'avg_duration' => new \Zend_Db_Expr("AVG(CASE WHEN status = 'success' AND executed_at IS NOT NULL AND finished_at IS NOT NULL THEN $duration END)"),
                'max_duration' => new \Zend_Db_Expr("MAX(CASE WHEN status = 'success' AND executed_at IS NOT NULL AND finished_at IS NOT NULL THEN $duration END)"),
                'success' => new \Zend_Db_Expr("SUM(status = 'success')"),
                'error' => new \Zend_Db_Expr("SUM(status = 'error')"),
                'missed' => new \Zend_Db_Expr("SUM(status = 'missed')"),
                'pending' => new \Zend_Db_Expr("SUM(status = 'pending')"),
                'running' => new \Zend_Db_Expr("SUM(status = 'running')"),
            ])
            ->group('job_code');
        $stats = [];
        foreach ($connection->fetchAll($select) as $row) {
            $stats[(string) $row['job_code']] = $row;
        }

        $latest = $connection->select()
            ->from($table, ['id' => 'MAX(schedule_id)'])
            ->where('status IN (?)', ['success', 'error', 'missed', 'running'])
            ->group('job_code');
        $select = $connection->select()
            ->from(['s' => $table], ['job_code', 'status', 'messages'])
            ->join(['l' => $latest], 'l.id = s.schedule_id', []);
        foreach ($connection->fetchAll($select) as $row) {
            $stats[(string) $row['job_code']]['last_status'] = (string) $row['status'];
            $stats[(string) $row['job_code']]['last_message'] = mb_substr((string) $row['messages'], 0, 500);
        }
        return $stats;
    }
}
