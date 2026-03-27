<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\ApiClient\BackendApiClient;
use Tweakwise\AttributeLandingTweakwise\Model\Config;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\FacetAttributeRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2Tweakwise\Model\Client\Response\FacetAttributesResponse;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class FacetAttributes extends AbstractFacetController
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
     * @return ResponseInterface|Json|ResultInterface
     * @throws LocalizedException
     * phpcs:disable Magento2.Performance.ForeachArrayMerge.ForeachArrayMerge
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $facetKey = $this->request->getParam('facet_key');
        $otherAttributeOption = ['value' => self::OTHER_ATTRIBUTE_VALUE, 'label' => 'Other (text field)'];

        if ($facetKey === self::OTHER_ATTRIBUTE_VALUE) {
            return $result->setData([$otherAttributeOption]);
        }

        $attributeValues = [];
        /** @var Store $store */
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->isBackendApiEnabled($store)) {
                $attributeValues = array_merge($this->executeBackendApiRequest($store, $facetKey), $attributeValues);
                continue;
            }

            $attributeValues = array_merge($this->executeDefaultRequest((int)$store->getId(), $facetKey), $attributeValues);
        }

        $attributeValues[] = $otherAttributeOption;

        $attributeValues = array_values(array_unique($attributeValues, SORT_REGULAR));

        return $result->setData($attributeValues);
    }

    /**
     * @param int $storeId
     * @param string|null $facetKey
     * @return array
     * @throws Exception
     */
    private function executeDefaultRequest(int $storeId, ?string $facetKey): array
    {
        $facetAttributeRequest = $this->requestFactory->create();

        $filterTemplate = $this->request->getParam('filter_template');
        if ($filterTemplate) {
            $facetAttributeRequest->setParameter('tn_ft', $filterTemplate);
        }

        if ($facetKey && $facetAttributeRequest instanceof FacetAttributeRequest) {
            $facetAttributeRequest->addFacetKey($facetKey);
        }

        $categoryId = $this->helper->getTweakwiseId(
            $storeId,
            (int) $this->request->getParam('category_id')
        );
        if ($categoryId) {
            $facetAttributeRequest->setParameter('tn_cid', $categoryId);
        }

        /** @var FacetAttributesResponse $response */
        $response = $this->client->request($facetAttributeRequest);

        if (!$response->getAttributes()) {
            return [];
        }

        $attributes = [];
        foreach ($response->getAttributes() as $attribute) {
            $attributes[] = [
                'value' => $attribute['title'],
                'label' => $attribute['title']
            ];
        }

        return $attributes;
    }

    /**
     * @param Store $store
     * @param string|null $facetKey
     * @return array
     */
    private function executeBackendApiRequest(Store $store, ?string $facetKey): array
    {
        if (!$facetKey) {
            return [];
        }

        $attributeId = $this->backendApiClient->getAttributeIdByCode($facetKey, $store);
        if (!$attributeId) {
            return [];
        }

        return $this->backendApiClient->getAttributeValues($attributeId, $store);
    }
}
