<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ShellInterface;
use Psr\Log\LoggerInterface;

/**
 * Starts "bin/magento softaware:cron:run <job>" as a background process, so a long job never
 * blocks the admin request. The run is recorded in cron_schedule by the command itself.
 */
class BackgroundLauncher
{
    public function __construct(
        private readonly ShellInterface $shell,
        private readonly Config $config,
        private readonly JobConfig $jobConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function launch(string $jobCode, ?string $user): void
    {
        if ($this->jobConfig->getDefinition($jobCode) === null) {
            throw new LocalizedException(__('There is no cron job "%1".', $jobCode));
        }
        $php = $this->getPhpBinary();
        $arguments = [$php, BP . '/bin/magento', 'softaware:cron:run', $jobCode, '--trigger=admin'];
        $command = '%s %s %s %s %s';
        if ($user !== null && $user !== '') {
            $arguments[] = '--user=' . preg_replace('/[^\w.@-]/', '', $user);
            $command .= ' %s';
        }
        try {
            $this->shell->execute($command, $arguments);
        } catch (\Exception $e) {
            $this->logger->error('Cron Manager: could not start ' . $jobCode . ': ' . $e->getMessage());
            throw new LocalizedException(__('The job could not be started: %1', $e->getMessage()));
        }
    }

    /**
     * The configured CLI binary, or the CLI binary matching the running PHP version.
     */
    public function getPhpBinary(): string
    {
        $configured = $this->config->getPhpBinary();
        if ($configured !== '') {
            return $configured;
        }
        // Under PHP-FPM PHP_BINARY is the FPM binary, so look for the matching CLI binary.
        $versioned = PHP_BINDIR . '/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        if (is_executable($versioned)) {
            return $versioned;
        }
        if (is_executable(PHP_BINDIR . '/php')) {
            return PHP_BINDIR . '/php';
        }
        return PHP_SAPI === 'cli' && PHP_BINARY ? PHP_BINARY : 'php';
    }
}
