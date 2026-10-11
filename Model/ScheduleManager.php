<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Model;

use Magento\Cron\Model\Schedule;
use Magento\Framework\App\ResourceConnection;

/**
 * Maintenance operations on the cron_schedule table.
 */
class ScheduleManager
{
    public const STATUSES = [
        Schedule::STATUS_PENDING,
        Schedule::STATUS_RUNNING,
        Schedule::STATUS_SUCCESS,
        Schedule::STATUS_MISSED,
        Schedule::STATUS_ERROR,
    ];

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Remove pending and/or missed entries. Magento queues pending entries again for enabled jobs.
     *
     * @param string[] $statuses
     */
    public function removeQueued(array $statuses, ?string $jobCode = null): int
    {
        $statuses = array_values(array_intersect($statuses, [Schedule::STATUS_PENDING, Schedule::STATUS_MISSED]));
        if (!$statuses) {
            return 0;
        }
        $where = ['status IN (?)' => $statuses];
        if ($jobCode) {
            $where['job_code = ?'] = $jobCode;
        }
        return $this->connection()->delete($this->table(), $where);
    }

    /**
     * Mark entries that are still "running" after $maxMinutes as failed.
     */
    /**
     * @param string[] $exceptCodes job codes to leave alone
     */
    public function markStuck(int $maxMinutes, ?string $jobCode = null, array $exceptCodes = []): int
    {
        $where = [
            'status = ?' => Schedule::STATUS_RUNNING,
            'executed_at < ?' => gmdate('Y-m-d H:i:s', time() - $maxMinutes * 60),
        ];
        if ($jobCode) {
            $where['job_code = ?'] = $jobCode;
        }
        if ($exceptCodes) {
            $where['job_code NOT IN (?)'] = array_values($exceptCodes);
        }
        return $this->connection()->update(
            $this->table(),
            [
                'status' => Schedule::STATUS_ERROR,
                'messages' => (string) __('Marked as failed by Cron Manager: still running after %1 minutes (the process probably stopped).', $maxMinutes),
            ],
            $where
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStuck(int $maxMinutes): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table(), ['schedule_id', 'job_code', 'executed_at'])
            ->where('status = ?', Schedule::STATUS_RUNNING)
            ->where('executed_at < ?', gmdate('Y-m-d H:i:s', time() - $maxMinutes * 60))
            ->order('executed_at ASC');
        return $connection->fetchAll($select);
    }

    /**
     * Delete finished entries older than the given number of hours (0 = all of them).
     *
     * @param string[] $statuses
     */
    public function cleanHistory(array $statuses, int $olderThanHours, ?string $jobCode = null): int
    {
        $statuses = array_values(array_intersect($statuses, [Schedule::STATUS_SUCCESS, Schedule::STATUS_ERROR, Schedule::STATUS_MISSED]));
        if (!$statuses) {
            return 0;
        }
        $where = ['status IN (?)' => $statuses];
        if ($olderThanHours > 0) {
            $where['scheduled_at < ?'] = gmdate('Y-m-d H:i:s', time() - $olderThanHours * 3600);
        }
        if ($jobCode) {
            $where['job_code = ?'] = $jobCode;
        }
        return $this->connection()->delete($this->table(), $where);
    }

    /**
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        return $this->connection()->delete($this->table(), ['schedule_id IN (?)' => $ids]);
    }

    /**
     * Entries for the timeline between two UTC times.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForTimeline(string $fromUtc, string $toUtc, ?array $jobCodes = null, int $limit = 6000): array
    {
        $connection = $this->connection();
        $start = 'COALESCE(executed_at, scheduled_at)';
        $select = $connection->select()
            ->from($this->table(), ['schedule_id', 'job_code', 'status', 'messages', 'scheduled_at', 'executed_at', 'finished_at'])
            ->where("$start < ?", $toUtc)
            ->where("COALESCE(finished_at, IF(status = 'running', UTC_TIMESTAMP(), $start)) >= ?", $fromUtc)
            ->order('schedule_id DESC')
            ->limit($limit);
        if ($jobCodes !== null) {
            if (!$jobCodes) {
                return [];
            }
            $select->where('job_code IN (?)', $jobCodes);
        }
        return $connection->fetchAll($select);
    }

    /**
     * Most recent sign of cron activity (queueing, starting or finishing a job), UTC.
     */
    public function getLastActivity(): ?string
    {
        $connection = $this->connection();
        $value = $connection->fetchOne(
            $connection->select()->from($this->table(), [
                'GREATEST(COALESCE(MAX(created_at), 0), COALESCE(MAX(executed_at), 0), COALESCE(MAX(finished_at), 0))',
            ])
        );
        return $value && $value !== '0' ? (string) $value : null;
    }

    /**
     * Longest time in minutes without any cron activity (a job queued, started or finished) between two Unix
     * times. Used to notice that cron itself was not running, independent of how often the caller runs.
     */
    public function getLongestGapMinutes(int $from, int $to): int
    {
        if ($to <= $from) {
            return 0;
        }
        $connection = $this->connection();
        $fromUtc = gmdate('Y-m-d H:i:s', $from);
        $toUtc = gmdate('Y-m-d H:i:s', $to);
        $minute = static fn (string $column) => new \Zend_Db_Expr("DATE_FORMAT($column, '%Y-%m-%d %H:%i:00')");
        $parts = [];
        foreach (['created_at', 'executed_at', 'finished_at'] as $column) {
            $parts[] = $connection->select()
                ->from($this->table(), ['t' => $minute($column)])
                ->where("$column >= ?", $fromUtc)
                ->where("$column <= ?", $toUtc);
        }
        $times = $connection->fetchCol($connection->select()->union($parts));
        $stamps = array_map(static fn ($t) => (int) strtotime($t . ' UTC'), $times);
        $stamps[] = $from;
        $stamps[] = $to;
        sort($stamps);
        $longest = 0;
        for ($i = 1, $n = count($stamps); $i < $n; $i++) {
            $longest = max($longest, $stamps[$i] - $stamps[$i - 1]);
        }
        return intdiv($longest, 60);
    }

    /**
     * Counts per status, optionally since a UTC time.
     *
     * @return array<string, int>
     */
    public function countByStatus(?string $sinceUtc = null): array
    {
        $connection = $this->connection();
        $select = $connection->select()->from($this->table(), ['status', 'COUNT(*)'])->group('status');
        if ($sinceUtc) {
            $select->where('scheduled_at >= ?', $sinceUtc);
        }
        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Jobs whose last $threshold finished runs all failed.
     *
     * @return array<string, array{count: int, last_message: string, last_at: string}>
     */
    public function getFailingJobs(int $threshold): array
    {
        if ($threshold < 1) {
            return [];
        }
        $connection = $this->connection();
        $ranked = $connection->select()
            ->from($this->table(), [
                'job_code', 'status', 'messages', 'finished_at', 'executed_at',
                'rn' => new \Zend_Db_Expr('ROW_NUMBER() OVER (PARTITION BY job_code ORDER BY schedule_id DESC)'),
            ])
            ->where('status IN (?)', [Schedule::STATUS_SUCCESS, Schedule::STATUS_ERROR]);
        $select = $connection->select()
            ->from(['r' => $ranked], [
                'job_code',
                'errors' => new \Zend_Db_Expr("SUM(r.status = 'error')"),
                'runs' => new \Zend_Db_Expr('COUNT(*)'),
                'last_message' => new \Zend_Db_Expr('MAX(CASE WHEN r.rn = 1 THEN r.messages END)'),
                'last_at' => new \Zend_Db_Expr('MAX(CASE WHEN r.rn = 1 THEN COALESCE(r.finished_at, r.executed_at) END)'),
            ])
            ->where('r.rn <= ?', $threshold)
            ->group('r.job_code')
            ->having('errors = ?', $threshold)
            ->having('runs = ?', $threshold);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(string) $row['job_code']] = [
                'count' => (int) $row['errors'],
                'last_message' => mb_substr((string) $row['last_message'], 0, 300),
                'last_at' => (string) $row['last_at'],
            ];
        }
        return $result;
    }

    /**
     * Failed entries scheduled after a UTC time, excluding the given schedule IDs.
     *
     * @param int[] $excludeIds
     * @return array<int, array<string, mixed>>
     */
    public function getErrorsSince(string $sinceUtc, array $excludeIds = []): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table(), ['schedule_id', 'job_code', 'messages', 'scheduled_at', 'executed_at', 'finished_at'])
            ->where('status = ?', Schedule::STATUS_ERROR)
            ->where('scheduled_at >= ?', $sinceUtc)
            ->order('schedule_id ASC')
            ->limit(500);
        if ($excludeIds) {
            $select->where('schedule_id NOT IN (?)', array_map('intval', $excludeIds));
        }
        return $connection->fetchAll($select);
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(): string
    {
        return $this->resource->getTableName('cron_schedule');
    }
}
