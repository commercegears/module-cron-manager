<?php
declare(strict_types=1);

namespace Softaware\CronManager\Model\System\Message;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;
use Softaware\CronManager\Model\Config;
use Softaware\CronManager\Model\Health;

abstract class AbstractHealthMessage implements MessageInterface
{
    public function __construct(
        protected readonly Health $health,
        protected readonly Config $config,
        protected readonly AuthorizationInterface $authorization,
        protected readonly UrlInterface $urlBuilder,
        protected readonly Escaper $escaper
    ) {
    }

    protected function canShow(): bool
    {
        return $this->config->isEnabled() && $this->authorization->isAllowed('Softaware_CronManager::jobs');
    }

    protected function link(string $route, string $label): string
    {
        return ' <a href="' . $this->escaper->escapeUrl($this->urlBuilder->getUrl($route)) . '">'
            . $this->escaper->escapeHtml($label) . '</a>';
    }
}
