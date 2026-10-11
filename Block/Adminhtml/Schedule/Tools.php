<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Block\Adminhtml\Schedule;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\AuthorizationInterface;
use CommerceGears\CronManager\Model\Config;

/**
 * Clean-up forms above the schedule grid.
 */
class Tools extends Template
{
    protected $_template = 'CommerceGears_CronManager::schedule/tools.phtml';

    public function __construct(
        Context $context,
        private readonly AuthorizationInterface $authorization,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function canManage(): bool
    {
        return $this->authorization->isAllowed('CommerceGears_CronManager::schedule_manage');
    }

    public function getJobCode(): string
    {
        return (string) $this->getRequest()->getParam('job_code');
    }

    public function getActionUrl(): string
    {
        return $this->getUrl('commercegears_cron/schedule/maintenance');
    }

    public function getShowAllUrl(): string
    {
        return $this->getUrl('commercegears_cron/schedule/index');
    }

    public function getMaxRuntime(): int
    {
        return $this->config->getMaxRuntime();
    }
}
