<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model\Source;

use Magento\Cron\Model\Config\Data as CronConfigData;
use Magento\Framework\Data\OptionSourceInterface;

class JobGroup implements OptionSourceInterface
{
    public function __construct(private readonly CronConfigData $cronConfigData)
    {
    }

    public function toOptionArray(): array
    {
        $groups = array_keys((array) $this->cronConfigData->getJobs());
        sort($groups);
        return array_map(static fn ($group) => ['value' => (string) $group, 'label' => (string) $group], $groups);
    }
}
