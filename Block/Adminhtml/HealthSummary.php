<?php
declare(strict_types=1);

namespace Softaware\CronManager\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Softaware\CronManager\Model\Config;
use Softaware\CronManager\Model\Health;
use Softaware\CronManager\Model\JobConfig;
use Softaware\CronManager\Model\OverrideStorage;
use Softaware\CronManager\Model\ScheduleManager;

/**
 * Health bar at the top of the Cron Manager pages.
 */
class HealthSummary extends Template
{
    protected $_template = 'Softaware_CronManager::health.phtml';

    public function __construct(
        Context $context,
        private readonly Health $health,
        private readonly ScheduleManager $scheduleManager,
        private readonly OverrideStorage $overrides,
        private readonly Config $config,
        private readonly JobConfig $jobConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        return $this->health->getReport(false);
    }

    /**
     * @return array<string, int>
     */
    public function getCounts24h(): array
    {
        return $this->scheduleManager->countByStatus(gmdate('Y-m-d H:i:s', time() - 86400));
    }

    /**
     * @return array<string, int>
     */
    public function getQueueCounts(): array
    {
        return $this->scheduleManager->countByStatus();
    }

    /**
     * @return array{disabled: int, rescheduled: int}
     */
    public function getOverrideCounts(): array
    {
        $disabled = 0;
        $rescheduled = 0;
        // Only jobs that still exist (overrides of removed modules stay stored but do nothing).
        $known = $this->jobConfig->getJobs(false);
        foreach (array_intersect_key($this->overrides->getAll(), $known) as $override) {
            $disabled += empty($override['disabled']) ? 0 : 1;
            $rescheduled += empty($override['schedule']) ? 0 : 1;
        }
        return ['disabled' => $disabled, 'rescheduled' => $rescheduled];
    }

    public function isReadOnly(): bool
    {
        return $this->overrides->isReadOnly();
    }

    public function isModuleEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function formatUtc(?string $utc): string
    {
        return $utc ? $this->formatDate($utc, \IntlDateFormatter::MEDIUM, true) : '—';
    }
}
