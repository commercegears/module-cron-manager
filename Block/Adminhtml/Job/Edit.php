<?php
declare(strict_types=1);

namespace Softaware\CronManager\Block\Adminhtml\Job;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\AuthorizationInterface;
use Softaware\CronManager\Model\CronExpression;
use Softaware\CronManager\Model\JobConfig;

class Edit extends Template
{
    protected $_template = 'Softaware_CronManager::job/edit.phtml';

    public function __construct(
        Context $context,
        private readonly JobConfig $jobConfig,
        private readonly CronExpression $cronExpression,
        private readonly AuthorizationInterface $authorization,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(): ?array
    {
        return $this->jobConfig->getJob((string) $this->getRequest()->getParam('code'));
    }

    public function getScheduleInput(): string
    {
        $job = $this->getJob();
        $posted = $this->_backendSession->getData('softaware_cron_schedule_input', true);
        if (is_string($posted) && $posted !== '') {
            return $posted;
        }
        return (string) ($job['is_overridden'] ? $job['schedule'] : $job['original_schedule']);
    }

    /**
     * @return string[] formatted next run times
     */
    public function getNextRuns(string $expression): array
    {
        $runs = [];
        foreach ($this->cronExpression->getNextRuns($expression, 5) as $timestamp) {
            $runs[] = $this->formatDate(gmdate('Y-m-d H:i:s', $timestamp), \IntlDateFormatter::MEDIUM, true);
        }
        return $runs;
    }

    /**
     * @return array<string, string>
     */
    public function getPresets(): array
    {
        return [
            '* * * * *' => (string) __('Every minute'),
            '*/5 * * * *' => (string) __('Every 5 minutes'),
            '*/15 * * * *' => (string) __('Every 15 minutes'),
            '0 * * * *' => (string) __('Hourly'),
            '0 */6 * * *' => (string) __('Every 6 hours'),
            '0 3 * * *' => (string) __('Daily at 03:00'),
            '0 3 * * 0' => (string) __('Weekly, Sunday 03:00'),
        ];
    }

    public function getTimezone(): string
    {
        return $this->_localeDate->getConfigTimezone();
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save', ['code' => $this->getJob()['job_code']]);
    }

    public function getRunUrl(): string
    {
        return $this->getUrl('*/*/run', ['code' => $this->getJob()['job_code']]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    public function getHistoryUrl(): string
    {
        return $this->getUrl('softaware_cron/schedule/index', ['job_code' => $this->getJob()['job_code']]);
    }

    public function canRun(): bool
    {
        return $this->authorization->isAllowed('Softaware_CronManager::jobs_run');
    }

    public function formatUtc(?string $utc): string
    {
        return $utc ? $this->formatDate($utc, \IntlDateFormatter::MEDIUM, true) : '—';
    }
}
