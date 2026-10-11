<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Seconds as "0.4 s", "2 min 5 s" or "1 h 3 min".
 */
class Duration extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = (string) $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$name]) || $item[$name] === '' || $item[$name] === null) {
                $item[$name] = '';
                continue;
            }
            $item[$name] = self::format((float) $item[$name]);
        }
        unset($item);
        return $dataSource;
    }

    public static function format(float $seconds): string
    {
        if ($seconds < 60) {
            return rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.') . ' s';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60) . ' min ' . ((int) $seconds % 60) . ' s';
        }
        return floor($seconds / 3600) . ' h ' . floor(((int) $seconds % 3600) / 60) . ' min';
    }
}
