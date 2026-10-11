<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use CommerceGears\CronManager\Model\BackgroundLauncher;

/**
 * Start a job now in the background (bin/magento commercegears:cron:run).
 */
class Run extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_CronManager::jobs_run';

    public function __construct(Context $context, private readonly BackgroundLauncher $launcher)
    {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $code = (string) $this->getRequest()->getParam('code');
        try {
            $user = $this->_auth->getUser() ? (string) $this->_auth->getUser()->getUserName() : null;
            $this->launcher->launch($code, $user);
            $this->messageManager->addSuccessMessage(__(
                '%1 has been started in the background. Its result appears in the schedule within moments (or when the job finishes).',
                $code
            ));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('commercegears_cron/schedule/index', ['job_code' => $code]);
    }
}
