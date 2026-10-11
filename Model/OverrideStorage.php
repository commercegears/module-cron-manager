<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Model;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\LocalizedException;

/**
 * Job overrides (disabled flag, schedule) stored in the configuration as one JSON value:
 * commercegears_cron_manager/overrides/jobs. Because it is plain config it can also be exported
 * with app:config:dump and deployed with the code.
 */
class OverrideStorage
{
    public const PATH = 'commercegears_cron_manager/overrides/jobs';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly ReinitableConfigInterface $reinitableConfig,
        private readonly TypeListInterface $cacheTypeList,
        private readonly DeploymentConfig $deploymentConfig
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

    /**
     * Whether the overrides are set in app/etc/config.php or env.php (e.g. after app:config:dump). Values there
     * win over the database, so changes made in the admin would have no effect.
     */
    public function isReadOnly(): bool
    {
        return $this->deploymentConfig->get('system/default/' . self::PATH) !== null;
    }

    /**
     * @throws LocalizedException when the overrides are set in a deployment configuration file
     */
    public function setDisabled(string $jobCode, bool $disabled, ?string $user): void
    {
        $this->setDisabledMany([$jobCode], $disabled, $user);
    }

    /**
     * Enables or disables several jobs with a single configuration save.
     *
     * @param string[] $jobCodes
     * @throws LocalizedException when the overrides are set in a deployment configuration file
     */
    public function setDisabledMany(array $jobCodes, bool $disabled, ?string $user): void
    {
        if (!$jobCodes) {
            return;
        }
        $all = $this->getAll();
        foreach ($jobCodes as $jobCode) {
            $entry = $all[$jobCode] ?? [];
            if ($disabled) {
                $entry['disabled'] = true;
            } else {
                unset($entry['disabled']);
            }
            $all[$jobCode] = $this->touch($entry, $user);
            if (!$all[$jobCode]) {
                unset($all[$jobCode]);
            }
        }
        $this->save($all, null, null);
    }

    /**
     * @throws LocalizedException when the overrides are set in a deployment configuration file
     */
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

    /**
     * @throws LocalizedException when the overrides are set in a deployment configuration file
     */
    public function reset(string $jobCode): void
    {
        $this->resetMany([$jobCode]);
    }

    /**
     * @param string[] $jobCodes
     * @throws LocalizedException when the overrides are set in a deployment configuration file
     */
    public function resetMany(array $jobCodes): void
    {
        $all = $this->getAll();
        foreach ($jobCodes as $jobCode) {
            unset($all[$jobCode]);
        }
        $this->save($all, null, null);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed> the entry with who/when updated, or [] when nothing is overridden any more
     */
    private function touch(array $entry, ?string $user): array
    {
        unset($entry['updated_at'], $entry['updated_by']);
        if (!$entry) {
            return [];
        }
        $entry['updated_at'] = gmdate('Y-m-d H:i:s');
        $entry['updated_by'] = (string) $user;
        return $entry;
    }

    /**
     * @param array<string, array<string, mixed>> $all
     */
    private function save(array $all, ?string $touched, ?string $user): void
    {
        if ($this->isReadOnly()) {
            throw new LocalizedException(__(
                'The Cron Manager changes are set in app/etc/config.php or app/etc/env.php (%1), so they cannot be changed here. Change them in that file, or remove them from it to manage them here.',
                self::PATH
            ));
        }
        if ($touched !== null) {
            $entry = $this->touch($all[$touched], $user);
            if (!$entry) {
                unset($all[$touched]);
            } else {
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
