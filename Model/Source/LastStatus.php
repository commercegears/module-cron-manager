<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Last finished status of a job (job grid filter), including jobs without a recent run.
 */
class LastStatus implements OptionSourceInterface
{
    public const NONE = 'none';

    public function toOptionArray(): array
    {
        return [
            ['value' => 'success', 'label' => __('Success')],
            ['value' => 'error', 'label' => __('Error')],
            ['value' => 'missed', 'label' => __('Missed')],
            ['value' => 'running', 'label' => __('Running')],
            ['value' => self::NONE, 'label' => __('No recent runs')],
        ];
    }
}
