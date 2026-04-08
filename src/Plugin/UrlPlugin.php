<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

/**
 * @author Bram Gerritsen <bgerritsen@emico.nl>
 * @copyright (c) Emico B.V. 2017
 */

namespace Tweakwise\AttributeLandingTweakwise\Plugin;

use Closure;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Model\Config;
use Emico\AttributeLanding\Model\LandingPageContext;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Filter\Item;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\NavigationContext\CurrentContext;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\FilterSlugManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\PathSlugStrategy;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\ProductSearchRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetType\SettingsType;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Store\Model\StoreManagerInterface;

class UrlPlugin
{
    /**
     * @var Resolver
     */
    private $layerResolver;

    /**
     * @var LandingPageContext
     */
    private $landingPageContext;

    /**
     * @var FilterManager
     */
    private $filterManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CurrentContext
     */
    private $context;

    /**
     * @var FilterSlugManager
     */
    private $filterSlugManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * UrlPlugin constructor.
     * @param Resolver $layerResolver
     * @param LandingPageContext $landingPageContext
     * @param FilterManager $filterManager
     * @param Config $config
     * @param CurrentContext $context
     * @param FilterSlugManager $filterSlugManager
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Resolver $layerResolver,
        LandingPageContext $landingPageContext,
        FilterManager $filterManager,
        Config $config,
        CurrentContext $context,
        FilterSlugManager $filterSlugManager,
        StoreManagerInterface $storeManager
    ) {
        $this->layerResolver = $layerResolver;
        $this->landingPageContext = $landingPageContext;
        $this->filterManager = $filterManager;
        $this->config = $config;
        $this->context = $context;
        $this->filterSlugManager = $filterSlugManager;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Url $subject
     * @param Closure $proceed
     * @param Item $filterItem
     * @return mixed|string
     */
    public function aroundGetSelectFilter(Url $subject, Closure $proceed, Item $filterItem)
    {
        if (!$this->config->isCrossLinkEnabled() || $this->context->getRequest() instanceof ProductSearchRequest) {
            return $proceed($filterItem);
        }

        // Try exact match first (existing behaviour)
        $url = $this->filterManager->findLandingPageUrlForFilterItem($filterItem);
        if ($url) {
            return $url;
        }

        // Try best (subset) match – only when NOT already on a landing page.
        // When the user is on a landing page we keep them there and let $proceed handle the URL.
        if ($this->landingPageContext->getLandingPage() === null) {
            $bestMatch = $this->filterManager->findBestLandingPageForFilterItem($filterItem);
            if ($bestMatch !== null) {
                return $this->buildLandingPageUrlWithExtraFilters(
                    $subject,
                    $bestMatch['page'],
                    $bestMatch['extraItems'],
                    $proceed,
                    $filterItem
                );
            }
        }

        return $proceed($filterItem);
    }

    /**
     * @param Url $subject
     * @param Closure $proceed
     * @param Item $filterItem
     * @return mixed|string
     *
     * When "hide_selected_filters" is disabled we need to apply some extra logic for the removal of filters.
     * If the user removes a filter which is part of the landingPage predefined filters we need to go to the category page instead of the landingpage
     */
    public function aroundGetRemoveFilter(Url $subject, Closure $proceed, Item $filterItem)
    {
        $landingPage = $this->landingPageContext->getLandingPage();

        $removeUrl = $proceed($filterItem);

        // We are not on a landing page, no need to do anything special
        // @phpstan-ignore-next-line
        if ($landingPage === null || $landingPage->getHideSelectedFilters()) {
            return $removeUrl;
        }

        // The filter you want to remove is not set as predefined filter on the landingpage, we can safely stay on the landingpage
        if (!$this->filterManager->isFilterAvailableOnLandingPage($landingPage, $filterItem)) {
            return $removeUrl;
        }

        // Capture the filter part of the URL and rebuild the URL to from {landingPage}/{filters} to {category}/{filters}
        // @phpstan-ignore-next-line
        if (preg_match('|' . $landingPage->getUrlRewriteRequestPath() . '(.*)|', $removeUrl, $matches)) {
            $category = $this->getLayer()->getCurrentCategory();
            $categoryUrl = $category->getUrl();

            //ensure there is one slash between category and the rest of the url
            $removeUrl = rtrim($categoryUrl, '/') . '/' . ltrim($matches[1], '/');
        }

        return $removeUrl;
    }

    /**
     * @param Url $subject
     * @param Closure $proceed
     * @param array $activeFilterItems
     * @return mixed|string
     */
    public function aroundGetClearUrl(Url $subject, Closure $proceed, array $activeFilterItems)
    {
        $landingPage = $this->landingPageContext->getLandingPage();

        // We are not on a landing page, no need to do anything special
        // @phpstan-ignore-next-line
        if ($landingPage === null || $landingPage->getHideSelectedFilters()) {
            return $proceed($activeFilterItems);
        }

        $category = $this->getLayer()->getCurrentCategory();
        return $category->getUrl();
    }

    /**
     * Build a full URL for the best-matching landing page with extra filter Items appended.
     *
     * @param Url $urlModel
     * @param LandingPageInterface $landingPage
     * @param Item[] $extraItems
     * @param Closure $proceed
     * @param Item $filterItem
     * @return string
     */
    private function buildLandingPageUrlWithExtraFilters(
        Url $urlModel,
        LandingPageInterface $landingPage,
        array $extraItems,
        Closure $proceed,
        Item $filterItem
    ): string {
        // @phpstan-ignore-next-line
        $storeBaseUrl = $this->storeManager->getStore()->getBaseUrl();
        // @phpstan-ignore-next-line
        $landingPagePath = $landingPage->getUrlRewriteRequestPath();
        $landingPageUrl = $storeBaseUrl . $landingPagePath;

        if (empty($extraItems)) {
            return $landingPageUrl;
        }

        $strategy = $urlModel->getUrlStrategy();

        if ($strategy instanceof PathSlugStrategy) {
            $extraPath = $this->buildExtraFilterSlugPath($extraItems);
            return rtrim($landingPageUrl, '/') . '/' . ltrim($extraPath, '/');
        }

        // For QueryParameterStrategy (or other strategies): use $proceed to get the
        // properly formatted URL through the normal URL pipeline (preserving encoding,
        // hash parameters, etc.), then swap the base path to point to the landing page.
        return $this->rebuildProceedUrlForLandingPage($proceed, $filterItem, $landingPageUrl);
    }

    /**
     * Use the normal URL pipeline ($proceed) and swap the base path to the landing page URL.
     * This preserves the encoding and query string format that the active URL strategy generates.
     *
     * @param Closure $proceed
     * @param Item $filterItem
     * @param string $landingPageUrl
     * @return string
     */
    private function rebuildProceedUrlForLandingPage(
        Closure $proceed,
        Item $filterItem,
        string $landingPageUrl
    ): string {
        $proceedUrl = $proceed($filterItem);

        $parsedProceed = parse_url($proceedUrl);
        $parsedLanding = parse_url($landingPageUrl);

        if (!isset($parsedProceed['path']) || !isset($parsedLanding['path'])) {
            return $proceedUrl;
        }

        // Replace the base path with the landing page path
        $result = (isset($parsedProceed['scheme']) ? $parsedProceed['scheme'] . '://' : '')
            . ($parsedProceed['host'] ?? '')
            . $parsedLanding['path'];

        if (isset($parsedProceed['query'])) {
            $result .= '?' . $parsedProceed['query'];
        }

        if (isset($parsedProceed['fragment'])) {
            $result .= '#' . $parsedProceed['fragment'];
        }

        return $result;
    }

    /**
     * Build a filter slug path for the given Items (PathSlugStrategy format: /urlKey/slug/).
     *
     * @param Item[] $items
     * @return string
     */
    private function buildExtraFilterSlugPath(array $items): string
    {
        usort($items, static function (Item $a, Item $b) {
            $facetA = $a->getFilter()->getUrlKey();
            $facetB = $b->getFilter()->getUrlKey();

            if ($facetA === $facetB) {
                return $a->getAttribute()->getTitle() > $b->getAttribute()->getTitle() ? 1 : -1;
            }

            return $facetA > $facetB ? 1 : -1;
        });

        $path = '';
        foreach ($items as $item) {
            $facetSettings = $item->getFilter()->getFacet()->getFacetSettings();

            if ($facetSettings->getSource() === SettingsType::SOURCE_CATEGORY) {
                continue;
            }

            $urlKey = $item->getFilter()->getUrlKey();

            if ($facetSettings->getSelectionType() === SettingsType::SELECTION_TYPE_SLIDER) {
                $slug = $item->getAttribute()->getTitle();
            } else {
                $slug = $this->filterSlugManager->getSlugForFilterItem($item);
            }

            $path .= $urlKey . '/' . $slug . '/';
        }

        return $path;
    }


    /**
     * @return Layer
     */
    protected function getLayer(): Layer
    {
        return $this->layerResolver->get();
    }
}
