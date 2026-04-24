<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\ApiClient\BackendApiClient;
use Tweakwise\AttributeLandingTweakwise\Model\Config;

abstract class AbstractFacetController implements HttpPostActionInterface
{
    public const OTHER_ATTRIBUTE_VALUE = 'tw_other';

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param BackendApiClient $backendApiClient
     */
    public function __construct(
        private readonly Config $config,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly BackendApiClient $backendApiClient,
    ) {
    }

    /**
     * @param Store|null $store
     * @return bool
     * @throws LocalizedException
     */
    protected function isBackendApiEnabled(?Store $store = null): bool
    {
        return (bool)$this->config->getBackendApiToken($store);
    }
}
