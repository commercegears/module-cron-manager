<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Framework\App\CacheInterface;

/**
 * Cron health figures used by the admin warnings, the status command and the e-mails.
 * Results for the admin warnings are cached for a few minutes so admin pages stay fast.
 */
class Health
{
    private const CACHE_KEY = 'softaware_cron_manager_health';
    private const CACHE_LIFETIME = 180;

    /** @var array<string, mixed>|null */
    private ?array $report = null;

    public function __construct(
        private readonly ScheduleManager $scheduleManager,
        private readonly Config $config,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return array{last_activity: ?string, minutes_since: ?int, not_running: bool, failing: array<string, array<string, mixed>>, stuck: array<int, array<string, mixed>>}
     */
    public function getReport(bool $useCache = true): array
    {
        if ($this->report !== null && $useCache) {
            return $this->report;
        }
        if ($useCache) {
            $cached = $this->cache->load(self::CACHE_KEY);
            $decoded = $cached ? json_decode($cached, true) : null;
            if (is_array($decoded)) {
                return $this->report = $decoded;
            }
        }
        $last = $this->scheduleManager->getLastActivity();
        $minutes = $last ? (int) floor((time() - strtotime($last . ' UTC')) / 60) : null;
        $threshold = $this->config->getNotRunningMinutes();
        $report = [
            'last_activity' => $last,
            'minutes_since' => $minutes,
            'not_running' => $threshold > 0 && ($minutes === null || $minutes >= $threshold),
            'failing' => array_filter(
                $this->scheduleManager->getFailingJobs($this->config->getFailureThreshold()),
                fn ($code) => !$this->config->isIgnored((string) $code),
                ARRAY_FILTER_USE_KEY
            ),
            'stuck' => array_values(array_filter(
                $this->scheduleManager->getStuck($this->config->getMaxRuntime()),
                fn (array $row) => !$this->config->isIgnored((string) $row['job_code'])
            )),
        ];
        $this->cache->save((string) json_encode($report), self::CACHE_KEY, ['config'], self::CACHE_LIFETIME);
        return $this->report = $report;
    }

    public function clearCache(): void
    {
        $this->report = null;
        $this->cache->remove(self::CACHE_KEY);
    }
}
