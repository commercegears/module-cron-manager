<?php
declare(strict_types=1);

namespace CommerceGears\CronManager\Controller\Adminhtml\Timeline;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_CronManager::schedule';

    public function execute(): ResultInterface
    {
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('CommerceGears_CronManager::timeline');
        $page->getConfig()->getTitle()->prepend(__('Cron Timeline'));
        return $page;
    }
}
