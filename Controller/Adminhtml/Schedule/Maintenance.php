<?php
declare(strict_types=1);

namespace Softaware\CronManager\Controller\Adminhtml\Schedule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Softaware\CronManager\Model\Config;
use Softaware\CronManager\Model\Health;
use Softaware\CronManager\Model\JobConfig;
use Softaware\CronManager\Model\ScheduleManager;

/**
 * Schedule clean-up: remove pending/missed entries, mark stuck jobs as failed, clean history.
 */
class Maintenance extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_CronManager::schedule_manage';

    public function __construct(
        Context $context,
        private readonly ScheduleManager $scheduleManager,
        private readonly JobConfig $jobConfig,
        private readonly Config $config,
        private readonly Health $health
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $request = $this->getRequest();
        $code = trim((string) $request->getParam('job_code'));
        if ($code !== '' && $this->jobConfig->getDefinition($code) === null) {
            // Allow entries of jobs that no longer exist in the configuration too.
            $code = preg_replace('/[^\w.-]/', '', $code) ?: '';
        }
        $jobCode = $code !== '' ? $code : null;
        $statuses = array_map('strval', (array) $request->getParam('statuses', []));
        switch ((string) $request->getParam('operation')) {
            case 'remove_queued':
                $count = $this->scheduleManager->removeQueued($statuses, $jobCode);
                $this->messageManager->addSuccessMessage(__('%1 queued entries removed. Cron queues upcoming runs of enabled jobs again on its next run.', $count));
                break;
            case 'mark_stuck':
                $minutes = trim((string) $request->getParam('minutes'));
                $minutes = $minutes === '' ? $this->config->getMaxRuntime() : $minutes;
                if (!ctype_digit((string) $minutes) || (int) $minutes < 1) {
                    $this->messageManager->addErrorMessage(__('Enter a whole number of minutes of 1 or more.'));
                    break;
                }
                $minutes = (int) $minutes;
                $count = $this->scheduleManager->markStuck($minutes, $jobCode);
                $this->messageManager->addSuccessMessage(__('%1 entries running for more than %2 minutes were marked as failed.', $count, $minutes));
                break;
            case 'clean_history':
                $hours = trim((string) $request->getParam('hours'));
                if (!ctype_digit($hours)) {
                    $this->messageManager->addErrorMessage(__('Enter a whole number of hours of 0 or more.'));
                    break;
                }
                $hours = (int) $hours;
                $count = $this->scheduleManager->cleanHistory($statuses, $hours, $jobCode);
                $this->messageManager->addSuccessMessage(__('%1 history entries deleted.', $count));
                break;
            default:
                $this->messageManager->addErrorMessage(__('Unknown action.'));
        }
        $this->health->clearCache();
        return $this->resultRedirectFactory->create()->setPath('*/*/index', $jobCode ? ['job_code' => $jobCode] : []);
    }
}
