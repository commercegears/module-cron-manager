<?php
declare(strict_types=1);

namespace Softaware\CronManager\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class JobActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $canRun = $this->authorization->isAllowed('Softaware_CronManager::jobs_run');
        $canManage = $this->authorization->isAllowed('Softaware_CronManager::jobs_manage');
        foreach ($dataSource['data']['items'] as &$item) {
            $code = (string) $item['job_code'];
            $actions = [];
            if ($canManage) {
                $actions['edit'] = [
                    'href' => $this->urlBuilder->getUrl('softaware_cron/job/edit', ['code' => $code]),
                    'label' => __('Edit'),
                ];
            }
            if ($canRun) {
                $actions['run'] = [
                    'href' => $this->urlBuilder->getUrl('softaware_cron/job/run', ['code' => $code]),
                    'label' => __('Run Now'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Run %1 now?', $code),
                        'message' => __('The job starts in the background. Its result appears in the schedule.'),
                    ],
                ];
            }
            if ($canManage) {
                $enabled = (int) $item['is_enabled'] === 1;
                $actions['toggle'] = [
                    'href' => $this->urlBuilder->getUrl('softaware_cron/job/toggle', ['code' => $code, 'enable' => $enabled ? 0 : 1]),
                    'label' => $enabled ? __('Disable') : __('Enable'),
                    'post' => true,
                ];
                if ($enabled) {
                    $actions['toggle']['confirm'] = [
                        'title' => __('Disable %1?', $code),
                        'message' => __('The job is no longer queued and its pending entries are removed. You can enable it again at any time.'),
                    ];
                }
            }
            $actions['history'] = [
                'href' => $this->urlBuilder->getUrl('softaware_cron/schedule/index', ['job_code' => $code]),
                'label' => __('History'),
            ];
            $item[$this->getData('name')] = $actions;
        }
        unset($item);
        return $dataSource;
    }
}
