<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model\AjaxResultInitializer;

use Emico\AttributeLanding\Api\LandingPageRepositoryInterface;
use Emico\AttributeLanding\Api\Data\FilterInterface;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use InvalidArgumentException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\Magento2Tweakwise\Model\AjaxResultInitializer\AbstractCountInitializer;
use Tweakwise\Magento2Tweakwise\Model\AjaxResultInitializer\CountInitializerInterface;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\NavigationContext;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\ProductNavigationRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\PropertiesType;

class LandingPageCountInitializer extends AbstractCountInitializer implements CountInitializerInterface
{
    /**
     * @param LandingPageRepositoryInterface $landingPageRepository
     * @param NavigationContext $navigationContext
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly NavigationContext $navigationContext,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Initialize navigation request for landing-page product count.
     */
    public function initializeForCount(RequestInterface $request): int
    {
        $landingPage = $this->getActiveLandingPage($request);

        $navigationRequest = $this->navigationContext->getRequest();
        $this->applyCategoryFilter($navigationRequest, $landingPage);
        $this->applyLandingPageFilters($navigationRequest, $landingPage);
        $this->applyTemplateIds($navigationRequest, $landingPage);

        $this->applyFilterParams($request, $this->navigationContext);

        /** @var PropertiesType $properties */
        $properties = $this->navigationContext->getResponse()->getValue('properties');

        return $properties->getNumberOfItems();
    }

    private function getActiveLandingPage(RequestInterface $request): LandingPageInterface
    {
        $pageId = (int) $request->getParam('__tw_object_id');
        if ($pageId === 0) {
            throw new InvalidArgumentException('No landing page provided for product count request.');
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $landingPage = $this->landingPageRepository->getByIdWithStore($pageId, $storeId);
        } catch (LocalizedException) {
            throw new InvalidArgumentException('Landing page not found.');
        }

        if (!$landingPage->isActive()) {
            throw new InvalidArgumentException('Landing page is not active.');
        }

        return $landingPage;
    }

    private function applyCategoryFilter(ProductNavigationRequest $navigationRequest, LandingPageInterface $landingPage): void
    {
        $categoryId = (int) $landingPage->getCategoryId();
        if ($categoryId === 0) {
            throw new InvalidArgumentException('Landing page has no valid category for product count request.');
        }

        $navigationRequest->addCategoryFilter($categoryId);
    }

    private function applyLandingPageFilters(ProductNavigationRequest $navigationRequest, LandingPageInterface $landingPage): void
    {
        foreach ($landingPage->getFilters() as $filter) {
            foreach ($this->getFilterValues($filter) as $value) {
                if ($value === '') {
                    continue;
                }

                $navigationRequest->addAttributeFilter($filter->getFacet(), $value);
            }
        }
    }

    /**
     * @return string[]
     */
    private function getFilterValues(FilterInterface $filter): array
    {
        $values = $filter->getValues();
        if ($values !== []) {
            return $values;
        }

        return [$filter->getValue()];
    }

    private function applyTemplateIds(ProductNavigationRequest $navigationRequest, LandingPageInterface $landingPage): void
    {
        $filterTemplateId = $landingPage->getTweakwiseFilterTemplate();
        if ($filterTemplateId) {
            $navigationRequest->setTemplateId($filterTemplateId);
        }

        $sortTemplateId = $landingPage->getTweakwiseSortTemplate();
        if ($sortTemplateId) {
            $navigationRequest->setSortTemplateId($sortTemplateId);
        }

        $builderTemplateId = $landingPage->getTweakwiseBuilderTemplate();
        if (!$builderTemplateId) {
            return;
        }

        $navigationRequest->setBuilderTemplateId((int) $builderTemplateId);
    }

}
