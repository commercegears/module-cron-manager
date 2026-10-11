<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Cron\Model\Config\Data as CronConfigData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;

/**
 * Every cron job from the configuration (crontab.xml and config-based schedules), with the
 * Cron Manager overrides and run statistics merged in.
 */
class JobConfig
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $jobs = null;

    public function __construct(
        private readonly CronConfigData $cronConfigData,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ModuleListInterface $moduleList,
        private readonly OverrideStorage $overrides,
        private readonly ScheduleStats $stats,
        private readonly Config $config
    ) {
    }

    /**
     * @return array<string, array<string, mixed>> job code => row
     */
    public function getJobs(bool $withStats = true): array
    {
        if ($this->jobs !== null && $withStats) {
            return $this->jobs;
        }
        $overrides = $this->overrides->getAll();
        $applyOverrides = $this->config->isEnabled();
        $stats = $withStats ? $this->stats->getStats() : [];
        $rows = [];
        foreach ((array) $this->cronConfigData->getJobs() as $group => $jobs) {
            foreach ((array) $jobs as $code => $job) {
                $code = (string) $code;
                $original = $this->getOriginalSchedule($job);
                $override = $overrides[$code] ?? [];
                $disabled = $applyOverrides && !empty($override['disabled']);
                $scheduleOverride = $applyOverrides && !empty($override['schedule']) ? (string) $override['schedule'] : null;
                $effective = $disabled ? '' : ($scheduleOverride ?? $original);
                $stat = $stats[$code] ?? [];
                $rows[$code] = [
                    'job_code' => $code,
                    'group' => (string) $group,
                    'instance' => (string) ($job['instance'] ?? ''),
                    'method' => (string) ($job['method'] ?? ''),
                    'callback' => ($job['instance'] ?? '?') . '::' . ($job['method'] ?? '?'),
                    'module' => $this->getModuleName((string) ($job['instance'] ?? '')),
                    'config_path' => (string) ($job['config_path'] ?? ''),
                    'original_schedule' => $original,
                    'schedule' => $effective,
                    'is_overridden' => $scheduleOverride !== null ? 1 : 0,
                    'is_enabled' => $disabled ? 0 : 1,
                    'state' => $disabled ? 'disabled' : 'enabled',
                    'has_schedule' => $effective !== '' ? 1 : 0,
                    'override_by' => (string) ($override['updated_by'] ?? ''),
                    'override_at' => (string) ($override['updated_at'] ?? ''),
                    'last_run' => $stat['last_run'] ?? null,
                    'last_status' => $stat['last_status'] ?? '',
                    'last_message' => $stat['last_message'] ?? '',
                    'avg_duration' => isset($stat['avg_duration']) ? round((float) $stat['avg_duration'], 1) : null,
                    'max_duration' => $stat['max_duration'] ?? null,
                    'success_count' => (int) ($stat['success'] ?? 0),
                    'error_count' => (int) ($stat['error'] ?? 0),
                    'missed_count' => (int) ($stat['missed'] ?? 0),
                    'pending_count' => (int) ($stat['pending'] ?? 0),
                    'running_count' => (int) ($stat['running'] ?? 0),
                ];
            }
        }
        ksort($rows);
        if ($withStats) {
            $this->jobs = $rows;
        }
        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(string $code): ?array
    {
        return $this->getJobs()[$code] ?? null;
    }

    /**
     * Raw definition (instance, method, group) from the configuration, ignoring overrides.
     *
     * @return array{group: string, instance: string, method: string}|null
     */
    public function getDefinition(string $code): ?array
    {
        foreach ((array) $this->cronConfigData->getJobs() as $group => $jobs) {
            if (isset($jobs[$code])) {
                return [
                    'group' => (string) $group,
                    'instance' => (string) ($jobs[$code]['instance'] ?? ''),
                    'method' => (string) ($jobs[$code]['method'] ?? ''),
                ];
            }
        }
        return null;
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        $groups = array_keys((array) $this->cronConfigData->getJobs());
        sort($groups);
        return array_map('strval', $groups);
    }

    /**
     * @param array<string, mixed> $job
     */
    private function getOriginalSchedule(array $job): string
    {
        if (!empty($job['config_path'])) {
            $value = (string) $this->scopeConfig->getValue((string) $job['config_path']);
            if ($value !== '') {
                return $value;
            }
        }
        return (string) ($job['schedule'] ?? '');
    }

    private function getModuleName(string $class): string
    {
        $parts = explode('\\', ltrim($class, '\\'));
        if (count($parts) < 2) {
            return '';
        }
        $name = $parts[0] . '_' . $parts[1];
        return $this->moduleList->has($name) ? $name : $name . ' (?)';
    }
}
