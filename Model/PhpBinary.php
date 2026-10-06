<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model;

/**
 * Rules for the configurable PHP binary of "Run now". The binary is executed by the web server user, so the
 * setting must not be able to point at an arbitrary program: only an absolute path to an executable file
 * whose name starts with "php" (php, php8.3, php83, php-cli …) is accepted.
 */
class PhpBinary
{
    /**
     * @return string|null error message, or null when the path is acceptable
     */
    public static function check(string $path): ?string
    {
        if (!preg_match('#^/[A-Za-z0-9_./+-]+$#', $path) || str_contains($path, '/../')) {
            return (string) __('Enter an absolute path without spaces, e.g. /usr/bin/php8.3.');
        }
        if (!preg_match('/^php[\w.+-]*$/i', basename($path))) {
            return (string) __('The file name must start with "php", e.g. /usr/bin/php8.3.');
        }
        if (!is_file($path) || !is_executable($path)) {
            return (string) __('%1 does not exist or is not executable for the web server user.', $path);
        }
        return null;
    }
}
