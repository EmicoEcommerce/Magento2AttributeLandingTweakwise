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
use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\FacetResponse;
use Tweakwise\AttributeLandingTweakwise\Model\Config;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class Facets extends AbstractFacetController
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
     * phpcs:disable Magento2.Performance.ForeachArrayMerge.ForeachArrayMerge
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $facets = [];
        /** @var Store $store */
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->isBackendApiEnabled($store)) {
                $facets = array_merge($this->executeBackendApiRequest($store), $facets);
                continue;
            }

            $facets = array_merge($this->executeDefaultRequest((int)$store->getId()), $facets);
        }

        $facets[] = ['value' => self::OTHER_ATTRIBUTE_VALUE, 'label' => 'Other (text field)'];

        $facets = array_values(array_unique($facets, SORT_REGULAR));

        return $result->setData($facets);
    }

    /**
     * @param int $storeId
     * @return array
     * @throws Exception
     */
    private function executeDefaultRequest(int $storeId): array
    {
        $facetRequest = $this->requestFactory->create();

        $filterTemplate = $this->request->getParam('filter_template');
        if ($filterTemplate) {
            $facetRequest->setParameter('tn_ft', $filterTemplate);
        }

        $categoryId = $this->helper->getTweakwiseId(
            $storeId,
            (int) $this->request->getParam('category_id')
        );
        if ($categoryId) {
            $facetRequest->setParameter('tn_cid', $categoryId);
        }

        /** @var FacetResponse $response */
        $response = $this->client->request($facetRequest);

        $facets = [];
        foreach ($response->getFacets() as $facet) {
            $facets[] = [
                'value' => $facet->getFacetSettings()->getUrlKey(),
                'label' => $facet->getFacetSettings()->getTitle()
            ];
        }

        return $facets;
    }

    /**
     * @param Store $store
     * @return array
     */
    private function executeBackendApiRequest(Store $store): array
    {
        return $this->backendApiClient->getAttributes($store);
    }
}
