<?php
declare(strict_types=1);

namespace Softaware\CronManager\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Softaware\CronManager\Model\CronExpression;
use Softaware\CronManager\Model\Health;
use Softaware\CronManager\Model\JobConfig;
use Softaware\CronManager\Model\OverrideStorage;
use Softaware\CronManager\Model\ScheduleManager;

/**
 * Save a schedule override (or remove it) and the enabled state of one job.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_CronManager::jobs_manage';

    public function __construct(
        Context $context,
        private readonly JobConfig $jobConfig,
        private readonly OverrideStorage $overrides,
        private readonly CronExpression $cronExpression,
        private readonly ScheduleManager $scheduleManager,
        private readonly Health $health
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $code = (string) $this->getRequest()->getParam('code');
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/edit', ['code' => $code]);
        $job = $this->jobConfig->getJob($code);
        if ($job === null) {
            $this->messageManager->addErrorMessage(__('There is no cron job "%1".', $code));
            return $redirect->setPath('*/*/index');
        }
        $user = $this->_auth->getUser() ? (string) $this->_auth->getUser()->getUserName() : null;
        try {
            $useDefault = (bool) $this->getRequest()->getParam('use_default');
            $expression = null;
            if (!$useDefault) {
                $expression = $this->cronExpression->validate((string) $this->getRequest()->getParam('schedule'));
                if ($expression === $job['original_schedule']) {
                    $expression = null;
                }
            }
            $enabled = (bool) $this->getRequest()->getParam('is_enabled', 1);
            $this->overrides->setSchedule($code, $expression, $user);
            $this->overrides->setDisabled($code, !$enabled, $user);
            // Entries queued under the old schedule would still run; Magento queues new ones.
            $removed = $this->scheduleManager->removeQueued(['pending'], $code);
            $this->health->clearCache();
            if (!$enabled) {
                $this->messageManager->addSuccessMessage(__('%1 is disabled.', $code));
            } elseif ($expression === null) {
                $this->messageManager->addSuccessMessage(__('%1 is enabled and runs on its default schedule.', $code));
            } else {
                $this->messageManager->addSuccessMessage(__('%1 now runs on "%2".', $code, $expression));
            }
            if ($removed) {
                $this->messageManager->addNoticeMessage(__('%1 pending entries were removed; cron queues the new times on its next run.', $removed));
            }
            return $redirect->setPath('*/*/index');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_getSession()->setData('softaware_cron_schedule_input', (string) $this->getRequest()->getParam('schedule'));
        }
        return $redirect;
    }
}
