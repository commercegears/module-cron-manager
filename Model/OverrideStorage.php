<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;

/**
 * Job overrides (disabled flag, schedule) stored in the configuration as one JSON value:
 * softaware_cron_manager/overrides/jobs. Because it is plain config it can also be exported
 * with app:config:dump and deployed with the code.
 */
class OverrideStorage
{
    public const PATH = 'softaware_cron_manager/overrides/jobs';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly ReinitableConfigInterface $reinitableConfig,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * @return array<string, array{disabled?: bool, schedule?: string, updated_at?: string, updated_by?: string}>
     */
    public function getAll(): array
    {
        if ($this->cache === null) {
            $decoded = json_decode((string) $this->scopeConfig->getValue(self::PATH), true);
            $this->cache = is_array($decoded) ? $decoded : [];
        }
        return $this->cache;
    }

    /**
     * @return array{disabled?: bool, schedule?: string, updated_at?: string, updated_by?: string}
     */
    public function get(string $jobCode): array
    {
        return $this->getAll()[$jobCode] ?? [];
    }

    public function setDisabled(string $jobCode, bool $disabled, ?string $user): void
    {
        $all = $this->getAll();
        $entry = $all[$jobCode] ?? [];
        if ($disabled) {
            $entry['disabled'] = true;
        } else {
            unset($entry['disabled']);
        }
        $all[$jobCode] = $entry;
        $this->save($all, $jobCode, $user);
    }

    public function setSchedule(string $jobCode, ?string $expression, ?string $user): void
    {
        $all = $this->getAll();
        $entry = $all[$jobCode] ?? [];
        if ($expression === null || $expression === '') {
            unset($entry['schedule']);
        } else {
            $entry['schedule'] = $expression;
        }
        $all[$jobCode] = $entry;
        $this->save($all, $jobCode, $user);
    }

    public function reset(string $jobCode): void
    {
        $all = $this->getAll();
        unset($all[$jobCode]);
        $this->save($all, null, null);
    }

    /**
     * @param array<string, array<string, mixed>> $all
     */
    private function save(array $all, ?string $touched, ?string $user): void
    {
        if ($touched !== null) {
            $entry = $all[$touched];
            unset($entry['updated_at'], $entry['updated_by']);
            if (!$entry) {
                unset($all[$touched]);
            } else {
                $entry['updated_at'] = gmdate('Y-m-d H:i:s');
                $entry['updated_by'] = (string) $user;
                $all[$touched] = $entry;
            }
        }
        ksort($all);
        if ($all) {
            $this->configWriter->save(self::PATH, (string) json_encode($all, JSON_UNESCAPED_SLASHES));
        } else {
            $this->configWriter->delete(self::PATH);
        }
        $this->cache = $all;
        $this->cacheTypeList->cleanType('config');
        $this->reinitableConfig->reinit();
    }
}
