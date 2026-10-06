<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model\System\Message;

/**
 * Admin warning when a job failed several times in a row, or a job is stuck "running".
 */
class FailingJobs extends AbstractHealthMessage
{
    public function getIdentity(): string
    {
        $report = $this->health->getReport();
        return 'softaware_cron_manager_failing_' . md5(implode(',', array_keys($report['failing'])) . '|' . count($report['stuck']));
    }

    public function isDisplayed(): bool
    {
        if (!$this->canShow()) {
            return false;
        }
        $report = $this->health->getReport();
        return $report['failing'] || $report['stuck'];
    }

    public function getText(): string
    {
        $report = $this->health->getReport();
        $parts = [];
        if ($report['failing']) {
            $codes = array_keys($report['failing']);
            $list = implode(', ', array_slice($codes, 0, 5)) . (count($codes) > 5 ? ', …' : '');
            $parts[] = $this->escaper->escapeHtml(__(
                'Cron jobs failing %1 times in a row: %2.',
                $this->config->getFailureThreshold(),
                $list
            ));
        }
        if ($report['stuck']) {
            $codes = array_unique(array_column($report['stuck'], 'job_code'));
            $parts[] = $this->escaper->escapeHtml(__(
                'Cron jobs running for more than %1 minutes (probably stuck): %2.',
                $this->config->getMaxRuntime(),
                implode(', ', array_slice($codes, 0, 5))
            ));
        }
        return implode(' ', $parts) . $this->link('softaware_cron/job/index', (string) __('Open the Cron Manager'));
    }

    public function getSeverity(): int
    {
        return self::SEVERITY_MAJOR;
    }
}
