<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\FacetAttributeRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2Tweakwise\Model\Client\Response\FacetAttributesResponse;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class FacetAttributes implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param Client $client
     * @param RequestFactory $requestFactory
     * @param Helper $helper
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Client $client,
        private readonly RequestFactory $requestFactory,
        private readonly Helper $helper,
    ) {
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $facetKey = $this->request->getParam('facet_key');
        $otherAttributeOption = ['value' => Facets::OTHER_ATTRIBUTE_VALUE, 'label' => 'Other (text field)'];

        if ($facetKey === Facets::OTHER_ATTRIBUTE_VALUE) {
            return $result->setData([$otherAttributeOption]);
        }

        $facetAttributeRequest = $this->requestFactory->create();

        $filterTemplate = $this->request->getParam('filter_template');
        if ($filterTemplate) {
            $facetAttributeRequest->setParameter('tn_ft', $filterTemplate);
        }

        if ($facetKey && $facetAttributeRequest instanceof FacetAttributeRequest) {
            $facetAttributeRequest->addFacetKey($facetKey);
        }

        $allStores = $facetAttributeRequest->getStores();
        $attributes = [];
        foreach ($allStores as $store) {
            $categoryId = $this->helper->getTweakwiseId(
                (int)$store->getId(),
                (int) $this->request->getParam('category_id')
            );
            if ($categoryId) {
                $facetAttributeRequest->setParameter('tn_cid', $categoryId);
            }

            /** @var FacetAttributesResponse $response */
            $response = $this->client->request($facetAttributeRequest);

            // @phpstan-ignore-next-line
            if (!$response) {
                return $result->setData([$otherAttributeOption]);
            }

            foreach ($response->getAttributes() as $attribute) {
                $attributes[] = [
                    'value' => $attribute['title'],
                    'label' => $attribute['title']
                ];
            }
        }

        $attributes[] = $otherAttributeOption;

        $attributes = array_values(array_unique($attributes, SORT_REGULAR));

        return $result->setData($attributes);
    }
}
