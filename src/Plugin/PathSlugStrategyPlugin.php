<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

/**
 * @author Bram Gerritsen <bgerritsen@emico.nl>
 * @copyright (c) Emico B.V. 2017
 */

namespace Tweakwise\AttributeLandingTweakwise\Plugin;

use Closure;
use Emico\AttributeLanding\Model\LandingPageContext;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Filter\Item;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\FilterSlugManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\PathSlugStrategy;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\UrlFactory;
use Magento\Framework\App\Request\Http as MagentoHttpRequest;
use Magento\Store\Model\StoreManagerInterface;

class PathSlugStrategyPlugin
{
    /**
     * @var LandingPageContext
     */
    private $landingPageContext;

    /**
     * @var FilterManager
     */
    private $filterManager;

    /**
     * @var UrlFactory
     */
    private $urlFactory;

    /**
     * @var FilterSlugManager
     */
    private $filterSlugManager;

    /**
     * @param LandingPageContext $landingPageContext
     * @param FilterManager $filterManager
     * @param UrlFactory $urlFactory
     * @param FilterSlugManager $filterSlugManager
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        LandingPageContext $landingPageContext,
        FilterManager $filterManager,
        UrlFactory $urlFactory,
        FilterSlugManager $filterSlugManager,
        private readonly StoreManagerInterface $storeManager
    ) {
        $this->landingPageContext = $landingPageContext;
        $this->filterManager = $filterManager;
        $this->urlFactory = $urlFactory;
        $this->filterSlugManager = $filterSlugManager;
    }

    /**
     * @param PathSlugStrategy $pathSlugStrategy
     * @param Closure $proceed
     * @param MagentoHttpRequest $request
     * @param Item $item
     * @return string
     */
    public function aroundGetAttributeSelectUrl(
        PathSlugStrategy $pathSlugStrategy,
        Closure $proceed,
        MagentoHttpRequest $request,
        Item $item
    ) {
        $landingPage = $this->landingPageContext->getLandingPage();
        // @phpstan-ignore-next-line
        if ($landingPage === null || $landingPage->getHideSelectedFilters()) {
            return $proceed($request, $item);
        }

        $filters = $this->filterManager->getActiveFiltersExcludingLandingPageFilters();
        $filters[] = $item;
        return $pathSlugStrategy->buildFilterUrl($request, $filters);
    }

    /**
     * @param PathSlugStrategy $pathSlugStrategy
     * @param Closure $proceed
     * @param MagentoHttpRequest $request
     * @param Item $item
     * @return string
     */
    public function aroundGetSliderUrl(
        PathSlugStrategy $pathSlugStrategy,
        Closure $proceed,
        MagentoHttpRequest $request,
        Item $item
    ) {
        $landingPage = $this->landingPageContext->getLandingPage();
        // @phpstan-ignore-next-line
        if ($landingPage === null || $landingPage->getHideSelectedFilters()) {
            return $proceed($request, $item);
        }

        $filters = $this->filterManager->getActiveFiltersExcludingLandingPageFilters();
        foreach ($filters as $key => $activeItem) {
            if ($activeItem->getFilter()->getUrlKey() !== $item->getFilter()->getUrlKey()) {
                continue;
            }
            unset($filters[$key]);
        }

        $attribute = clone $item->getAttribute();
        $attribute->setValue('title', '{{from}}-{{to}}');
        $filters[] = new Item($item->getFilter(), $attribute, $this->urlFactory->create());

        return $pathSlugStrategy->buildFilterUrl($request, $filters);
    }

    /**
     * @param PathSlugStrategy $pathSlugStrategy
     * @param Closure $proceed
     * @param MagentoHttpRequest $request
     * @param Item $item
     * @return string
     */
    public function aroundGetAttributeRemoveUrl(
        PathSlugStrategy $pathSlugStrategy,
        Closure $proceed,
        MagentoHttpRequest $request,
        Item $item
    ) {
        $landingPage = $this->landingPageContext->getLandingPage();
        // @phpstan-ignore-next-line
        if ($landingPage === null || $landingPage->getHideSelectedFilters()) {
            return $proceed($request, $item);
        }

        if (!$this->filterManager->isFilterAvailableOnLandingPage($landingPage, $item)) {
            $filters = $this->filterManager->getActiveFiltersExcludingLandingPageFilters();
        } else {
            $filters = $this->filterManager->getAllActiveFilters();
        }

        foreach ($filters as $key => $activeItem) {
            if ($activeItem !== $item) {
                continue;
            }
            unset($filters[$key]);
        }

        return $pathSlugStrategy->buildFilterUrl($request, $filters);
    }

    /**
     * Adds landingpage filters to category select url
     *
     * @param PathSlugStrategy $original
     * @param string $result
     * @return string
     */
    public function afterGetCategoryFilterSelectUrl(
        PathSlugStrategy $original,
        string $result
    ): string {
        $landingPage = $this->landingPageContext->getLandingPage();
        // @phpstan-ignore-next-line
        if ($landingPage === null) {
            return $result;
        }

        // If $landingPage->getHideSelectedFilters() === false then the landingpage filters are already in the url
        if (!$landingPage->getHideSelectedFilters()) {
            return $result;
        }

        $landingsPageFilters = $this->filterManager->getLandingsPageFilters();
        if (empty($landingsPageFilters)) {
            return $result;
        }

        $lookupTable = $this->filterSlugManager->getLookupTable();
        $storeId = (int) $this->storeManager->getStore()->getId();
        $filters = [];
        foreach ($landingsPageFilters as $filter) {
            $filters[] = $filter->getFacet();
            $key = strtolower($filter->getValue());
            if (!empty($lookupTable[$storeId][$key])) {
                $filters[] = $lookupTable[$storeId][$key];
            } elseif (!empty($lookupTable[0][$key])) {
                $filters[] = $lookupTable[0][$key];
            } else {
                $filters[] = $key;
            }
        }

        return sprintf(
            '%s/%s',
            rtrim($result, '/'),
            ltrim(implode('/', $filters), '/')
        );
    }

    /**
     * @param PathSlugStrategy $original
     * @param string $result
     * @param MagentoHttpRequest $request
     * @return string
     */
    public function afterGetOriginalUrl(PathSlugStrategy $original, string $result, MagentoHttpRequest $request): string
    {
        $landingPage = $this->landingPageContext->getLandingPage();
        // @phpstan-ignore-next-line
        if ($landingPage === null) {
            return $result;
        }

        if (strpos($result, $landingPage->getUrlPath()) !== false) {
            return $result;
        }

        return $landingPage->getUrlPath();
    }
}
