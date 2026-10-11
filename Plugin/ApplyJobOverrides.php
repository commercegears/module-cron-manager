<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Plugin;

use Magento\Cron\Model\ConfigInterface;
use CommerceGears\CronManager\Model\Config;
use CommerceGears\CronManager\Model\OverrideStorage;

/**
 * Applies the Cron Manager overrides to the job configuration Magento's cron reads:
 * a disabled job keeps its definition but gets no schedule (so nothing new is queued and
 * Magento removes its pending entries); a rescheduled job gets the new expression.
 */
class ApplyJobOverrides
{
    public function __construct(
        private readonly Config $config,
        private readonly OverrideStorage $overrides
    ) {
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $result
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function afterGetJobs(ConfigInterface $subject, $result)
    {
        if (!is_array($result) || !$this->config->isEnabled()) {
            return $result;
        }
        $overrides = $this->overrides->getAll();
        if (!$overrides) {
            return $result;
        }
        foreach ($result as $group => $jobs) {
            foreach ($jobs as $code => $job) {
                if (!isset($overrides[$code])) {
                    continue;
                }
                $override = $overrides[$code];
                if (!empty($override['disabled'])) {
                    unset($result[$group][$code]['config_path']);
                    $result[$group][$code]['schedule'] = '';
                    continue;
                }
                if (!empty($override['schedule'])) {
                    unset($result[$group][$code]['config_path']);
                    $result[$group][$code]['schedule'] = (string) $override['schedule'];
                }
            }
        }
        return $result;
    }
}
