<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Block\Product;

use Emico\AttributeLanding\Model\LandingPageRepository;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Url\Helper\Data;
use Tweakwise\AttributeLandingTweakwise\Model\AjaxResultInitializer\LandingPageInitializer;
use Tweakwise\Magento2Tweakwise\Block\Product\ListProduct as TweakwiseListProduct;
use Tweakwise\AttributeLandingTweakwise\Model\Catalog\Layer\Url\RewriteResolver\LandingPageResolver;
use Tweakwise\Magento2Tweakwise\Helper\Cache;
use Tweakwise\Magento2Tweakwise\Model\Config;

class ListProduct extends TweakwiseListProduct
{
    public function __construct(
        Context $context,
        PostHelper $postDataHelper,
        Resolver $layerResolver,
        CategoryRepositoryInterface $categoryRepository,
        Data $urlHelper,
        private readonly Config $tweakwiseConfig,
        private readonly CookieManagerInterface $cookieManager,
        private readonly Cache $cacheHelper,
        private readonly Registry $registry,
        private readonly RequestInterface $request,
        private LandingPageRepository $landingPageRepository,
        array $data = [],
        ?OutputHelper $outputHelper = null
    ) {
        parent::__construct(
            $context,
            $postDataHelper,
            $layerResolver,
            $categoryRepository,
            $urlHelper,
            $tweakwiseConfig,
            $cookieManager,
            $cacheHelper,
            $registry,
            $request,
            $data,
            $outputHelper
        );
    }

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl($route = '', $params = [], $queryParams = [])
    {
        if ($this->request->getModuleName() === 'emico_attributelanding') {
            $landingPageId = (int)$this->request->getParam('id');
            if (!empty($landingPageId)) {
                $landingPage    = $this->landingPageRepository->getById($landingPageId);
                $filters        = $this->registry->registry('alp_filters' . $landingPageId);
                $filterTemplate = $landingPage->getTweakwiseFilterTemplate();
                $sortTemplate   = $landingPage->getTweakwiseSortTemplate();

                foreach ($filters as $filter) {
                    $queryParams[$filter->getFacet()] = $filter->getValue();
                }

                if (!empty($filterTemplate)) {
                    $queryParams['tn_ft'] = $filterTemplate;
                }

                if (!empty($sortTemplate)) {
                    $queryParams['tn_st'] = $sortTemplate;
                }
            }
        }

        return parent::getUrl($route, $params, $queryParams);
    }
}
