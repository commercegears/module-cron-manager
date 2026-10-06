<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Softaware\CronManager\Model\PhpBinary as PhpBinaryRules;

/**
 * Validates "PHP Binary for Run Now" when the settings are saved.
 */
class PhpBinary extends Value
{
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());
        $this->setValue($value);
        if ($value !== '') {
            $error = PhpBinaryRules::check($value);
            if ($error !== null) {
                throw new LocalizedException(__('PHP Binary for "Run Now": %1', $error));
            }
        }
        return parent::beforeSave();
    }
}
