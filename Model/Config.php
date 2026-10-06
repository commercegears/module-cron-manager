<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('softaware_cron_manager/general/enabled');
    }

    public function getPhpBinary(): string
    {
        return trim((string) $this->scopeConfig->getValue('softaware_cron_manager/general/php_binary'));
    }

    public function getMaxRuntime(): int
    {
        return max(1, (int) $this->scopeConfig->getValue('softaware_cron_manager/stuck/max_runtime') ?: 120);
    }

    public function isAutoMarkStuck(): bool
    {
        return $this->scopeConfig->isSetFlag('softaware_cron_manager/stuck/auto_mark');
    }

    public function getNotRunningMinutes(): int
    {
        return max(0, (int) $this->scopeConfig->getValue('softaware_cron_manager/health/not_running_minutes'));
    }

    public function getFailureThreshold(): int
    {
        return max(0, (int) $this->scopeConfig->getValue('softaware_cron_manager/health/failure_threshold'));
    }

    /**
     * Job codes (or patterns with *) that are left out of the health warnings, the failure e-mails and the
     * automatic marking of stuck jobs.
     *
     * @return string[]
     */
    public function getIgnoredJobs(): array
    {
        $value = (string) $this->scopeConfig->getValue('softaware_cron_manager/health/ignore_jobs');
        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[\s,]+/', $value) ?: []),
            static fn ($code) => $code !== ''
        )));
    }

    public function isIgnored(string $jobCode): bool
    {
        foreach ($this->getIgnoredJobs() as $pattern) {
            if ($pattern === $jobCode || (str_contains($pattern, '*') && fnmatch($pattern, $jobCode))) {
                return true;
            }
        }
        return false;
    }

    public function isNotifyEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('softaware_cron_manager/notify/enabled');
    }

    /**
     * @return string[]
     */
    public function getRecipients(): array
    {
        $value = (string) $this->scopeConfig->getValue('softaware_cron_manager/notify/recipients');
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn ($email) => (bool) filter_var($email, FILTER_VALIDATE_EMAIL)
        ));
    }

    public function getSenderIdentity(): string
    {
        return (string) ($this->scopeConfig->getValue('softaware_cron_manager/notify/identity') ?: 'general');
    }

    public function getTemplate(): string
    {
        return (string) ($this->scopeConfig->getValue('softaware_cron_manager/notify/template') ?: 'softaware_cron_manager_notify_template');
    }

    public function getNotifyInterval(): int
    {
        return max(1, (int) $this->scopeConfig->getValue('softaware_cron_manager/notify/interval') ?: 60);
    }
}
