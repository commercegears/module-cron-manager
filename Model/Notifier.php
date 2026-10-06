<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Area;
use Magento\Framework\Escaper;
use Magento\Framework\FlagManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * E-mails failed cron jobs, at most once per configured interval. Failures that happen in
 * between are collected into the next e-mail.
 */
class Notifier
{
    private const FLAG_NOTIFIED = 'softaware_cron_manager_notified_ids';
    private const FLAG_LAST_SENT = 'softaware_cron_manager_last_email_at';
    private const FLAG_LAST_CHECK = 'softaware_cron_manager_last_check_at';
    private const LOOKBACK = 172800;
    private const GAP_WINDOW = 3300;

    public function __construct(
        private readonly Config $config,
        private readonly ScheduleManager $scheduleManager,
        private readonly FlagManager $flagManager,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly BackendUrl $backendUrl,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return string what happened (for logs and the CLI)
     */
    public function notify(bool $dryRun = false): string
    {
        $now = time();
        $lastCheck = (int) $this->flagManager->getFlagData(self::FLAG_LAST_CHECK);
        if (!$dryRun) {
            $this->recordCheck($now);
        }
        if (!$this->config->isNotifyEnabled() && !$dryRun) {
            return 'E-mails are switched off.';
        }
        $recipients = $this->config->getRecipients();
        if (!$recipients && !$dryRun) {
            return 'No valid recipient configured.';
        }
        $lastSent = (int) $this->flagManager->getFlagData(self::FLAG_LAST_SENT);
        if (!$dryRun && $lastSent > $now - $this->config->getNotifyInterval() * 60) {
            return 'Waiting: the last e-mail was sent less than the configured interval ago.';
        }

        $notified = (array) ($this->flagManager->getFlagData(self::FLAG_NOTIFIED) ?: []);
        $errors = $this->scheduleManager->getErrorsSince(gmdate('Y-m-d H:i:s', $now - self::LOOKBACK), $notified);
        $ignoredIds = [];
        $errors = array_values(array_filter($errors, function (array $row) use (&$ignoredIds) {
            if ($this->config->isIgnored((string) $row['job_code'])) {
                $ignoredIds[] = (int) $row['schedule_id'];
                return false;
            }
            return true;
        }));
        // A long gap without any cron activity since the previous check means cron itself was not running.
        // Only the recent history is looked at (Magento deletes successful entries after about an hour), and
        // gaps are measured in cron_schedule, so running this check less often does not look like an outage.
        $gapMinutes = $lastCheck
            ? $this->scheduleManager->getLongestGapMinutes(max($lastCheck, $now - self::GAP_WINDOW), $now)
            : 0;
        $threshold = $this->config->getNotRunningMinutes();
        $gap = $threshold > 0 && $gapMinutes >= max($threshold, 10) ? $gapMinutes : 0;
        if (!$errors && !$gap) {
            if ($ignoredIds && !$dryRun) {
                $this->saveNotified(array_merge($notified, $ignoredIds));
            }
            return 'Nothing to report.';
        }

        $storeName = 'Magento';
        try {
            $view = $this->storeManager->getDefaultStoreView();
            if ($view) {
                $storeName = (string) ($view->getFrontendName() ?: $view->getName());
            }
        } catch (\Exception) {
            // Keep the generic name.
        }
        $vars = [
            'store_name' => $storeName,
            'summary' => $this->getSummary(count($errors), $gap),
            'rows_html' => $this->renderRows($errors),
            // No secret key: it would belong to this (cron) process and be rejected when the link is opened.
            'url' => $this->backendUrl->getUrl('softaware_cron/schedule/index', ['_nosecret' => true]),
        ];
        if ($dryRun) {
            return 'Would send to ' . (implode(', ', $recipients) ?: '(no recipient)') . ': ' . $vars['summary'];
        }
        try {
            $builder = $this->transportBuilder
                ->setTemplateIdentifier($this->config->getTemplate())
                ->setTemplateOptions(['area' => Area::AREA_ADMINHTML, 'store' => Store::DEFAULT_STORE_ID])
                ->setTemplateVars($vars)
                ->setFromByScope($this->config->getSenderIdentity());
            foreach ($recipients as $recipient) {
                $builder->addTo($recipient);
            }
            $builder->getTransport()->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error('Cron Manager: failure e-mail could not be sent: ' . $e->getMessage());
            return 'Sending failed: ' . $e->getMessage();
        }
        $this->saveNotified(array_merge(
            $notified,
            $ignoredIds,
            array_map(static fn ($row) => (int) $row['schedule_id'], $errors)
        ));
        $this->flagManager->saveFlag(self::FLAG_LAST_SENT, $now);
        return 'Sent to ' . implode(', ', $recipients) . ': ' . $vars['summary'];
    }

    /**
     * Records that the health check ran (also when the module is switched off).
     */
    public function recordCheck(?int $time = null): void
    {
        $this->flagManager->saveFlag(self::FLAG_LAST_CHECK, $time ?? time());
    }

    /**
     * @param int[] $ids
     */
    private function saveNotified(array $ids): void
    {
        $this->flagManager->saveFlag(self::FLAG_NOTIFIED, array_slice(array_values(array_unique($ids)), -1000));
    }

    private function getSummary(int $errors, int $gap): string
    {
        $parts = [];
        if ($errors) {
            $parts[] = (string) __('%1 cron job run(s) failed.', $errors);
        }
        if ($gap) {
            $parts[] = (string) __('Cron did not run for about %1 minutes before the last check.', $gap);
        }
        return implode(' ', $parts);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private function renderRows(array $errors): string
    {
        if (!$errors) {
            return '';
        }
        $html = '<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;border-color:#ddd;font-size:13px">'
            . '<tr style="background:#f5f5f5"><th align="left">' . $this->escaper->escapeHtml(__('Job'))
            . '</th><th align="left">' . $this->escaper->escapeHtml(__('Scheduled (UTC)'))
            . '</th><th align="left">' . $this->escaper->escapeHtml(__('Message')) . '</th></tr>';
        foreach (array_slice($errors, 0, 30) as $row) {
            $html .= '<tr><td>' . $this->escaper->escapeHtml((string) $row['job_code']) . '</td><td>'
                . $this->escaper->escapeHtml((string) $row['scheduled_at']) . '</td><td>'
                . $this->escaper->escapeHtml(mb_strimwidth((string) $row['messages'], 0, 300, '…')) . '</td></tr>';
        }
        $html .= '</table>';
        if (count($errors) > 30) {
            $html .= '<p>' . $this->escaper->escapeHtml(__('…and %1 more.', count($errors) - 30)) . '</p>';
        }
        return $html;
    }
}
