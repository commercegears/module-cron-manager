<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class JobState implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '1', 'label' => __('Enabled')],
            ['value' => '0', 'label' => __('Disabled')],
        ];
    }
}
