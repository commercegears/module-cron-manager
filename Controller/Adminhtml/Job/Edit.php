<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use CommerceGears\CronManager\Model\JobConfig;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_CronManager::jobs_manage';

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
        $page->setActiveMenu('CommerceGears_CronManager::jobs');
        $page->getConfig()->getTitle()->prepend(__('Cron Job: %1', $code));
        return $page;
    }
}
