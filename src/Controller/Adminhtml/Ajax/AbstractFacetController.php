<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\Model\Config;

abstract class AbstractFacetController implements HttpPostActionInterface
{
    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $config,
        protected readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @param Store|null $store
     * @return string|null
     * @throws LocalizedException
     */
    protected function getBackendApiToken(?Store $store = null): ?string
    {
        return $this->config->getBackendApiToken($store);
    }

    /**
     * @param Store|null $store
     * @return bool
     * @throws LocalizedException
     */
    protected function isBackendApiEnabled(Store $store = null): bool
    {
        return (bool)$this->getBackendApiToken($store);
    }
}
