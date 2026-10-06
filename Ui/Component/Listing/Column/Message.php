<?php
declare(strict_types=1);

namespace Softaware\CronManager\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Shortened cron message (full text in the schedule grid).
 */
class Message extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = (string) $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $text = trim((string) ($item[$name] ?? ''));
            $item[$name] = mb_strimwidth(preg_replace('/\s+/', ' ', $text) ?? '', 0, 300, '…');
        }
        unset($item);
        return $dataSource;
    }
}
