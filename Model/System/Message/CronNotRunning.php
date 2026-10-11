<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Model\System\Message;

/**
 * Admin warning when Magento's cron has not run for the configured number of minutes.
 */
class CronNotRunning extends AbstractHealthMessage
{
    public function getIdentity(): string
    {
        return 'commercegears_cron_manager_not_running';
    }

    public function isDisplayed(): bool
    {
        return $this->canShow() && $this->health->getReport()['not_running'];
    }

    public function getText(): string
    {
        $report = $this->health->getReport();
        $text = $report['minutes_since'] === null
            ? __('Cron has never run on this store. Scheduled tasks such as indexing, e-mails and clean-ups do not happen.')
            : __('Cron has not run for %1 minutes. Scheduled tasks such as indexing, e-mails and clean-ups are not happening.', $report['minutes_since']);
        return $this->escaper->escapeHtml($text) . $this->link('commercegears_cron/schedule/index', (string) __('Open the cron schedule'));
    }

    public function getSeverity(): int
    {
        return self::SEVERITY_CRITICAL;
    }
}
