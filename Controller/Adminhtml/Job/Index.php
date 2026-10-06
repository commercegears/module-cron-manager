<?php
declare(strict_types=1);

namespace Softaware\CronManager\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_CronManager::jobs';

    public function execute(): ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Softaware_CronManager::jobs');
        $page->getConfig()->getTitle()->prepend(__('Cron Jobs'));
        return $page;
    }
}
