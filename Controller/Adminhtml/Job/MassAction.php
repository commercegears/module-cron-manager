<?php
declare(strict_types=1);

namespace Softaware\CronManager\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Softaware\CronManager\Model\BackgroundLauncher;
use Softaware\CronManager\Model\JobConfig;
use Softaware\CronManager\Model\JobStateManager;
use Softaware\CronManager\Ui\DataProvider\JobDataProviderFactory;

/**
 * Mass actions of the job grid: run, enable, disable, restore defaults.
 */
class MassAction extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_CronManager::jobs';

    private const MAX_RUN = 20;

    public function __construct(
        Context $context,
        private readonly JobConfig $jobConfig,
        private readonly JobStateManager $stateManager,
        private readonly BackgroundLauncher $launcher,
        private readonly JobDataProviderFactory $dataProviderFactory
    ) {
        parent::__construct($context);
    }

    protected function _isAllowed(): bool
    {
        $operation = (string) $this->getRequest()->getParam('operation');
        $resource = $operation === 'run' ? 'Softaware_CronManager::jobs_run' : 'Softaware_CronManager::jobs_manage';
        return parent::_isAllowed() && $this->_authorization->isAllowed($resource);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');
        $codes = $this->getSelectedCodes();
        if (!$codes) {
            $this->messageManager->addErrorMessage(__('Select at least one job.'));
            return $redirect;
        }
        $user = $this->_auth->getUser() ? (string) $this->_auth->getUser()->getUserName() : null;
        $operation = (string) $this->getRequest()->getParam('operation');
        try {
            switch ($operation) {
                case 'enable':
                case 'disable':
                    $count = $this->stateManager->setEnabled($codes, $operation === 'enable', $user);
                    $this->messageManager->addSuccessMessage($operation === 'enable'
                        ? __('%1 job(s) enabled.', $count)
                        : __('%1 job(s) disabled.', $count));
                    break;
                case 'reset':
                    $count = $this->stateManager->reset($codes);
                    $this->messageManager->addSuccessMessage(__('%1 job(s) use their default schedule again.', $count));
                    break;
                case 'run':
                    if (count($codes) > self::MAX_RUN) {
                        throw new LocalizedException(__('Run at most %1 jobs at a time.', self::MAX_RUN));
                    }
                    foreach ($codes as $code) {
                        $this->launcher->launch($code, $user);
                    }
                    $this->messageManager->addSuccessMessage(__('Started in the background: %1.', implode(', ', $codes)));
                    return $redirect->setPath('softaware_cron/schedule/index');
                default:
                    throw new LocalizedException(__('Unknown action.'));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }

    /**
     * Job codes from the grid selection (explicit selection, or "select all" minus exclusions within the filters).
     *
     * @return string[]
     */
    private function getSelectedCodes(): array
    {
        $request = $this->getRequest();
        $all = array_keys($this->jobConfig->getJobs(false));
        $selected = $request->getParam('selected');
        if (is_array($selected)) {
            return array_values(array_intersect($all, array_map('strval', $selected)));
        }
        $excluded = $request->getParam('excluded');
        if ($excluded === 'false' || is_array($excluded)) {
            $provider = $this->dataProviderFactory->create([
                'name' => 'softaware_cron_job_listing_data_source',
                'primaryFieldName' => 'job_code',
                'requestFieldName' => 'code',
            ]);
            $provider->applyRequestFilters((array) $request->getParam('filters', []), (string) $request->getParam('search', ''));
            $codes = $provider->getAllIds();
            return is_array($excluded) ? array_values(array_diff($codes, array_map('strval', $excluded))) : $codes;
        }
        return [];
    }
}
