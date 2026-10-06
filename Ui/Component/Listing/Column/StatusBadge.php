<?php
declare(strict_types=1);

namespace Softaware\CronManager\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Coloured status label (success, error, missed, running, pending; enabled/disabled for jobs).
 */
class StatusBadge extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = (string) $this->getData('name');
        $labels = [
            'pending' => __('Pending'),
            'running' => __('Running'),
            'success' => __('Success'),
            'missed' => __('Missed'),
            'error' => __('Error'),
        ];
        foreach ($dataSource['data']['items'] as &$item) {
            $value = (string) ($item[$name] ?? '');
            if ($name === 'is_enabled') {
                $value = $value === '1' ? 'enabled' : 'disabled';
                $label = $value === 'enabled' ? __('Enabled') : __('Disabled');
            } else {
                $label = $labels[$value] ?? $value;
            }
            $item[$name] = $value === ''
                ? ''
                : '<span class="softaware-cron-badge softaware-cron-badge-' . $this->escaper->escapeHtmlAttr($value) . '">'
                    . $this->escaper->escapeHtml($label) . '</span>';
        }
        unset($item);
        return $dataSource;
    }
}
