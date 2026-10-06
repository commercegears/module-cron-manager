<?php
declare(strict_types=1);

namespace Softaware\CronManager\Controller\Adminhtml\Schedule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Softaware\CronManager\Model\ScheduleManager;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_CronManager::schedule_manage';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ScheduleManager $scheduleManager
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        try {
            $ids = $this->filter->getCollection($this->collectionFactory->create())->getAllIds();
            $count = $this->scheduleManager->deleteByIds($ids);
            $this->messageManager->addSuccessMessage(__('%1 schedule entries deleted.', $count));
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
