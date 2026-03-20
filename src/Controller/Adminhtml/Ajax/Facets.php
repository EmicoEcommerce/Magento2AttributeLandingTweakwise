<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2Tweakwise\Model\Client\Response\FacetResponse;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class Facets implements HttpPostActionInterface
{
    public const OTHER_ATTRIBUTE_VALUE = 'tw_other';

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
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $facetRequest = $this->requestFactory->create();

        $filterTemplate = $this->request->getParam('filter_template');
        if ($filterTemplate) {
            $facetRequest->setParameter('tn_ft', $filterTemplate);
        }

        $allStores = $facetRequest->getStores();
        $facets = [];
        foreach ($allStores as $store) {
            $categoryId = $this->helper->getTweakwiseId(
                (int)$store->getId(),
                (int) $this->request->getParam('category_id')
            );
            if ($categoryId) {
                $facetRequest->setParameter('tn_cid', $categoryId);
            }

            /** @var FacetResponse $response */
            $response = $this->client->request($facetRequest);

            foreach ($response->getFacets() as $facet) {
                $facets[] = [
                    'value' => $facet->getFacetSettings()->getUrlKey(),
                    'label' => $facet->getFacetSettings()->getTitle()
                ];
            }
        }

        $facets[] = ['value' => self::OTHER_ATTRIBUTE_VALUE, 'label' => 'Other (text field)'];

        $facets = array_values(array_unique($facets, SORT_REGULAR));

        return $result->setData($facets);
    }
}
