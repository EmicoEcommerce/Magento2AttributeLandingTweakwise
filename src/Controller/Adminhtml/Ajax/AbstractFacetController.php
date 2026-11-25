<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\ApiClient\BackendApiClient;
use Tweakwise\AttributeLandingTweakwise\Model\Config;

abstract class AbstractFacetController extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Emico_AttributeLanding::page_management';

    public const OTHER_ATTRIBUTE_VALUE = 'tw_other';

    /**
     * @param Context $context
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param BackendApiClient $backendApiClient
     */
    public function __construct(
        Context $context,
        protected readonly Config $config,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly BackendApiClient $backendApiClient,
    ) {
        parent::__construct($context);
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
