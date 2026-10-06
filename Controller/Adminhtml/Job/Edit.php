<?php
declare(strict_types=1);

namespace Softaware\CronManager\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Softaware\CronManager\Model\JobConfig;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_CronManager::jobs_manage';

    public function __construct(Context $context, private readonly JobConfig $jobConfig)
    {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $code = (string) $this->getRequest()->getParam('code');
        if ($this->jobConfig->getJob($code) === null) {
            $this->messageManager->addErrorMessage(__('There is no cron job "%1".', $code));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Softaware_CronManager::jobs');
        $page->getConfig()->getTitle()->prepend(__('Schedule of %1', $code));
        return $page;
    }
}
