<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Plugin\Block\Product;

use Emico\AttributeLanding\Api\LandingPageRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tweakwise\Magento2Tweakwise\Block\Product\ListProduct as Subject;
use Tweakwise\Magento2Tweakwise\Helper\Cache;

class ListProduct
{
    /**
     * @param RequestInterface $request
     * @param Cache $cacheHelper
     * @param LandingPageRepositoryInterface $landingPageRepository
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Cache $cacheHelper,
        private readonly LandingPageRepositoryInterface $landingPageRepository
    ) {
    }

    /**
     * @param Subject $subject
     * @param string $route
     * @param array $params
     * @return array
     */
    public function beforeGetUrl(Subject $subject, $route = '', $params = [])
    {
        $landingPageId = (int)$this->request->getParam('id');
        if (
            !$this->isAttributeLandingRequest() ||
            !$landingPageId ||
            !$this->cacheHelper->personalMerchandisingCanBeApplied() ||
            $route !== 'page_cache/block/esi'
        ) {
            return [$route, $params];
        }

        try {
            // @phpstan-ignore-next-line
            $landingPage = $this->landingPageRepository->getById($landingPageId);
        } catch (NoSuchEntityException | LocalizedException $e) {
            return [$route, $params];
        }

        $filters = $landingPage->getFilters();
        $filterTemplate = $landingPage->getTweakwiseFilterTemplate();
        $sortTemplate = $landingPage->getTweakwiseSortTemplate();

        foreach ($filters as $filter) {
            $params['_query'][$filter->getFacet()] = $filter->getValue();
        }

        if (!empty($filterTemplate)) {
            $params['_query']['tn_ft'] = $filterTemplate;
        }

        if (!empty($sortTemplate)) {
            $params['_query']['tn_st'] = $sortTemplate;
        }

        return [$route, $params];
    }

    /**
     * @return bool
     */
    private function isAttributeLandingRequest(): bool
    {
        return $this->request->getModuleName() === 'emico_attributelanding';
    }
}
