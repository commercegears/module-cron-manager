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
            $html = $expression === ''
                ? '<span class="softaware-cron-muted">' . $this->escaper->escapeHtml(__('None')) . '</span>'
                : '<code class="softaware-cron-expr-cell">' . $this->escaper->escapeHtml($expression) . '</code>';
            if (!empty($item['is_overridden'])) {
                $html .= ' <span class="softaware-cron-changed" title="'
                    . $this->escaper->escapeHtmlAttr(__('Default: %1', (string) ($item['original_schedule'] ?? '') ?: '—')) . '">'
                    . $this->escaper->escapeHtml(__('Changed')) . '</span>';
            }
            $item[$name] = $html;
        }
        unset($item);
        return $dataSource;
    }
}
