<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\CleanupFacetResponse;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class CleanupFacets implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Client $client,
        private readonly RequestFactory $requestFactory,
        private readonly Helper $helper,
    ) {
    }

    public function execute(): Json
    {
        $categoryId = (int)$this->request->getParam('category_id');
        if ($categoryId <= 0) {
            throw new LocalizedException(__('A category is required to check landing page filters.'));
        }

        $storeId = (int)$this->request->getParam('store_id');
        $store = $storeId ? $this->storeManager->getStore($storeId) : $this->storeManager->getDefaultStoreView();
        if (!$store) {
            throw new LocalizedException(__('A store view is required to check landing page filters.'));
        }

        $previousStore = $this->storeManager->getStore();
        $previousStoreParam = $this->request->getParam('store');
        $previousWebsiteParam = $this->request->getParam('website');
        $this->storeManager->setCurrentStore($store->getId());
        $this->request->setParams(['store' => $store->getId(), 'website' => 0]);
        try {
            $facetRequest = $this->requestFactory->create();
            $facetRequest->setParameter('tn_cid', $this->helper->getTweakwiseId((int)$store->getId(), $categoryId));
            $template = $this->request->getParam('filter_template');
            if ($template) {
                $facetRequest->setParameter('tn_ft', (string)$template);
            }

            $response = $this->client->request($facetRequest);
            if (!$response instanceof CleanupFacetResponse || !array_key_exists('facets', $response->toArray())) {
                throw new LocalizedException(__('Unable to load the available Tweakwise filters.'));
            }

            $facets = [];
            foreach ($response->getFacets() as $facet) {
                $facets[] = ['value' => $facet->getFacetSettings()->getUrlKey()];
            }
        } finally {
            $this->storeManager->setCurrentStore($previousStore->getId());
            $this->request->setParams(['store' => $previousStoreParam, 'website' => $previousWebsiteParam]);
        }

        return $this->resultJsonFactory->create()->setData($facets);
    }
}
