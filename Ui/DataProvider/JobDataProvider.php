<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use CommerceGears\CronManager\Model\JobConfig;
use CommerceGears\CronManager\Model\Source\LastStatus;

/**
 * Data provider for the cron job grid. Jobs come from configuration, not from a table,
 * so filtering, search, sorting and paging are done on the array.
 */
class JobDataProvider extends AbstractDataProvider
{
    /** @var array<int, array{field: string, type: string, value: mixed}> */
    private array $filters = [];
    private string $search = '';
    private ?string $orderField = null;
    private string $orderDir = 'ASC';
    private int $offset = 1;
    private int $size = 20;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly JobConfig $jobConfig,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function addFilter(Filter $filter)
    {
        if ($filter->getField() === 'fulltext') {
            $this->search = mb_strtolower(trim((string) $filter->getValue()));
            return;
        }
        $this->filters[] = [
            'field' => (string) $filter->getField(),
            'type' => (string) ($filter->getConditionType() ?: 'eq'),
            'value' => $filter->getValue(),
        ];
    }

    public function addOrder($field, $direction)
    {
        $this->orderField = (string) $field;
        $this->orderDir = strtoupper((string) $direction) === 'DESC' ? 'DESC' : 'ASC';
    }

    public function setLimit($offset, $size)
    {
        $this->offset = max(1, (int) $offset);
        $this->size = (int) $size;
    }

    public function getData()
    {
        $rows = $this->getFilteredRows();
        if ($this->orderField) {
            $field = $this->orderField;
            usort($rows, function (array $a, array $b) use ($field) {
                $result = is_numeric($a[$field] ?? null) && is_numeric($b[$field] ?? null)
                    ? ($a[$field] <=> $b[$field])
                    : strnatcasecmp((string) ($a[$field] ?? ''), (string) ($b[$field] ?? ''));
                return $this->orderDir === 'DESC' ? -$result : $result;
            });
        }
        $total = count($rows);
        if ($this->size > 0) {
            $rows = array_slice($rows, ($this->offset - 1) * $this->size, $this->size);
        }
        return ['totalRecords' => $total, 'items' => array_values($rows)];
    }

    /**
     * Apply grid filters as posted by a mass action ("filters" and "search" request parameters).
     *
     * @param array<string, mixed> $filters
     */
    public function applyRequestFilters(array $filters, string $search): void
    {
        $this->search = mb_strtolower(trim($search));
        unset($filters['placeholder']);
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                if (isset($value['from']) && $value['from'] !== '') {
                    $this->filters[] = ['field' => (string) $field, 'type' => 'gteq', 'value' => $value['from']];
                }
                if (isset($value['to']) && $value['to'] !== '') {
                    $this->filters[] = ['field' => (string) $field, 'type' => 'lteq', 'value' => $value['to']];
                }
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            $type = in_array($field, ['group', 'is_enabled', 'is_overridden', 'last_status'], true) ? 'eq' : 'like';
            $this->filters[] = ['field' => (string) $field, 'type' => $type, 'value' => $value];
        }
    }

    /**
     * Job codes matching the current filters and search (used by mass actions with "select all").
     *
     * @return string[]
     */
    public function getAllIds(): array
    {
        return array_column($this->getFilteredRows(), 'job_code');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFilteredRows(): array
    {
        $rows = array_values($this->jobConfig->getJobs());
        if ($this->search !== '') {
            $rows = array_filter($rows, fn (array $row) => str_contains(
                mb_strtolower($row['job_code'] . ' ' . $row['callback'] . ' ' . $row['module'] . ' ' . $row['group']),
                $this->search
            ));
        }
        foreach ($this->filters as $filter) {
            $rows = array_filter($rows, fn (array $row) => $this->matches($row, $filter));
        }
        return array_values($rows);
    }

    /**
     * @param array<string, mixed> $row
     * @param array{field: string, type: string, value: mixed} $filter
     */
    private function matches(array $row, array $filter): bool
    {
        $value = $row[$filter['field']] ?? null;
        $expected = $filter['value'];
        if ($filter['field'] === 'last_status' && $expected === LastStatus::NONE) {
            $expected = '';
        }
        switch ($filter['type']) {
            case 'like':
                $needle = mb_strtolower(trim((string) $expected, '%'));
                return str_contains(mb_strtolower((string) $value), $needle);
            case 'gteq':
                if ($value === null || $value === '') {
                    return false;
                }
                return is_numeric($expected) ? (float) $value >= (float) $expected : strtotime((string) $value . ' UTC') >= strtotime((string) $expected . ' UTC');
            case 'lteq':
                if ($value === null || $value === '') {
                    return false;
                }
                return is_numeric($expected) ? (float) $value <= (float) $expected : strtotime((string) $value . ' UTC') <= strtotime((string) $expected . ' UTC');
            case 'in':
                return in_array((string) $value, array_map('strval', (array) $expected), true);
            default:
                return (string) $value === (string) $expected;
        }
    }
}
