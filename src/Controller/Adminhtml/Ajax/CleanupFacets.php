<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\ApiClient\BackendApiClient;
use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\CleanupFacetResponse;
use Tweakwise\AttributeLandingTweakwise\Model\Config;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

/**
 * Returns the filter attributes available for a landing page category and filter template. Uses the same source as
 * the attribute dropdown (Facets): the Backend API when a token is configured, otherwise the published facets.
 */
class CleanupFacets extends AbstractFacetController
{
    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param BackendApiClient $backendApiClient
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param Client $client
     * @param RequestFactory $requestFactory
     * @param Helper $helper
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        BackendApiClient $backendApiClient,
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Client $client,
        private readonly RequestFactory $requestFactory,
        private readonly Helper $helper,
    ) {
        parent::__construct($config, $storeManager, $backendApiClient);
    }

    /**
     * @return Json
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        $categoryId = (int)$this->request->getParam('category_id');
        if ($categoryId <= 0) {
            throw new LocalizedException(__('A category is required to check landing page filters.'));
        }

        $storeId = (int)$this->request->getParam('store_id');
        $stores = $storeId ? [$this->storeManager->getStore($storeId)] : $this->storeManager->getStores();
        if (!$stores) {
            throw new LocalizedException(__('A store view is required to check landing page filters.'));
        }

        $template = (string)$this->request->getParam('filter_template');
        $previousStore = $this->storeManager->getStore();
        $previousStoreParam = $this->request->getParam('store');
        $previousWebsiteParam = $this->request->getParam('website');
        try {
            $facets = [];
            $checked = [];
            /** @var Store $store */
            foreach ($stores as $store) {
                $this->storeManager->setCurrentStore($store->getId());
                // RequestInterface::setParams() merges, so only the store scope used by the Tweakwise config changes
                $this->request->setParams(['store' => $store->getId(), 'website' => 0]);

                $tweakwiseCategoryId = null;
                $key = ['backend', $this->config->getBackendApiToken($store)];
                if (!$this->isBackendApiEnabled($store)) {
                    $tweakwiseCategoryId = $this->helper->getTweakwiseId((int)$store->getId(), $categoryId);
                    $key = ['navigator', $tweakwiseCategoryId];
                }
                $key = implode('|', [...$key, $this->config->getGeneralAuthenticationKey($store), $template]);
                if (isset($checked[$key])) {
                    continue;
                }

                $checked[$key] = true;
                $facets += $tweakwiseCategoryId === null
                    ? $this->getBackendApiFacets($store, $template)
                    : $this->getNavigatorFacets($tweakwiseCategoryId, $template);
            }
        } finally {
            $this->storeManager->setCurrentStore($previousStore->getId());
            // Null values remove the parameters again when they were not present before
            $this->request->setParams(['store' => $previousStoreParam, 'website' => $previousWebsiteParam]);
        }

        return $this->resultJsonFactory->create()->setData(array_values($facets));
    }

    /**
     * @param string $tweakwiseCategoryId
     * @param string $template
     * @return array
     * @throws LocalizedException
     */
    private function getNavigatorFacets(string $tweakwiseCategoryId, string $template): array
    {
        $facetRequest = $this->requestFactory->create();
        $facetRequest->setParameter('tn_cid', $tweakwiseCategoryId);
        if ($template) {
            $facetRequest->setParameter('tn_ft', $template);
        }

        $response = $this->client->request($facetRequest);
        if (!$response instanceof CleanupFacetResponse || !array_key_exists('facets', $response->toArray())) {
            throw new LocalizedException(__('Unable to load the available Tweakwise filters.'));
        }

        $facets = [];
        foreach ($response->getFacets() as $facet) {
            $key = $facet->getFacetSettings()->getUrlKey();
            $facets[$key] = ['value' => $key];
        }

        return $facets;
    }

    /**
     * @param Store $store
     * @param string $template
     * @return array
     * @throws LocalizedException
     */
    private function getBackendApiFacets(Store $store, string $template): array
    {
        try {
            $attributes = (int)$template
                ? $this->backendApiClient->getAttributesFromFilterTemplateOrFail($store, (int)$template)
                : $this->backendApiClient->getAttributesOrFail($store);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new LocalizedException(__('Unable to load the available Tweakwise filters.'), $e);
        }

        $facets = [];
        foreach ($attributes as $attribute) {
            $key = (string)$attribute['value'];
            $facets[$key] = ['value' => $key];
        }

        return $facets;
    }
}
