<?php
declare(strict_types=1);

namespace Softaware\CronManager\Controller\Adminhtml\Schedule;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_CronManager::schedule';

    public function execute(): ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Softaware_CronManager::schedule');
        $code = (string) $this->getRequest()->getParam('job_code');
        $page->getConfig()->getTitle()->prepend($code !== '' ? __('Cron Schedule: %1', $code) : __('Cron Schedule'));
        return $page;
    }
}
