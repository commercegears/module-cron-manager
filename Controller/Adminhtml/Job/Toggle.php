<?php
declare(strict_types=1);

namespace Softaware\CronManager\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Softaware\CronManager\Model\JobStateManager;

/**
 * Enable or disable one job.
 */
class Toggle extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_CronManager::jobs_manage';

    public function __construct(Context $context, private readonly JobStateManager $stateManager)
    {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $code = (string) $this->getRequest()->getParam('code');
        $enable = (bool) $this->getRequest()->getParam('enable');
        $user = $this->_auth->getUser() ? (string) $this->_auth->getUser()->getUserName() : null;
        if ($this->stateManager->setEnabled([$code], $enable, $user) === 0) {
            $this->messageManager->addErrorMessage(__('There is no cron job "%1".', $code));
        } else {
            $this->messageManager->addSuccessMessage($enable ? __('%1 is enabled.', $code) : __('%1 is disabled.', $code));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
