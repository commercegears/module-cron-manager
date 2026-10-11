<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ScheduleStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'pending', 'label' => __('Pending')],
            ['value' => 'running', 'label' => __('Running')],
            ['value' => 'success', 'label' => __('Success')],
            ['value' => 'missed', 'label' => __('Missed')],
            ['value' => 'error', 'label' => __('Error')],
        ];
    }
}
