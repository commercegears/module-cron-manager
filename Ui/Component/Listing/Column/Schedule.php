<?php
declare(strict_types=1);

namespace Softaware\CronManager\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Cron expression of a job, marked "Changed" when the Cron Manager overrides the default schedule.
 */
class Schedule extends Column
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
        foreach ($dataSource['data']['items'] as &$item) {
            $expression = trim((string) ($item[$name] ?? ''));
            if (($item['state'] ?? 'enabled') === 'disabled') {
                // Disabled jobs are not queued: say so instead of "None" plus a "Changed" mark.
                $item[$name] = '<span class="softaware-cron-muted softaware-cron-nomargin">'
                    . $this->escaper->escapeHtml(__('Not queued (job disabled)')) . '</span>';
                continue;
            }
            $html = $expression === ''
                ? '<span class="softaware-cron-muted softaware-cron-nomargin">' . $this->escaper->escapeHtml(__('None (manual runs only)')) . '</span>'
                : '<code class="softaware-cron-expr-cell">' . $this->escaper->escapeHtml($expression) . '</code>';
            if (!empty($item['is_overridden'])) {
                $html .= ' <span class="softaware-cron-changed" title="'
                    . $this->escaper->escapeHtmlAttr(__('Default: %1', (string) ($item['original_schedule'] ?? '') ?: (string) __('none'))) . '">'
                    . $this->escaper->escapeHtml(__('Changed')) . '</span>';
            }
            $item[$name] = $html;
        }
        unset($item);
        return $dataSource;
    }
}
