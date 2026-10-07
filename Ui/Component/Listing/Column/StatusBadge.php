<?php
declare(strict_types=1);

namespace Softaware\CronManager\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Status label in Magento's grid severity style (success, error, missed, running, pending; enabled/disabled for jobs).
 */
class StatusBadge extends Column
{
    /**
     * Magento grid severity class per status. Pending and running are not a severity: neutral label.
     */
    private const CLASSES = [
        'success' => 'grid-severity-notice',
        'enabled' => 'grid-severity-notice',
        'error' => 'grid-severity-critical',
        'disabled' => 'grid-severity-critical',
        'missed' => 'grid-severity-minor',
        'running' => 'softaware-cron-status-neutral _running',
        'pending' => 'softaware-cron-status-neutral',
    ];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public static function getCssClass(string $status): string
    {
        return self::CLASSES[$status] ?? 'softaware-cron-status-neutral';
    }

    public static function getLabel(string $status): string
    {
        $labels = [
            'pending' => __('Pending'),
            'running' => __('Running'),
            'success' => __('Success'),
            'missed' => __('Missed'),
            'error' => __('Error'),
            'enabled' => __('Enabled'),
            'disabled' => __('Disabled'),
        ];
        return (string) ($labels[$status] ?? $status);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = (string) $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $value = (string) ($item[$name] ?? '');
            if ($name === 'is_enabled') {
                $value = $value === '1' ? 'enabled' : 'disabled';
            }
            $item[$name] = $value === ''
                ? ''
                : '<span class="' . $this->escaper->escapeHtmlAttr(self::getCssClass($value)) . '">'
                    . $this->escaper->escapeHtml(self::getLabel($value)) . '</span>';
        }
        unset($item);
        return $dataSource;
    }
}
