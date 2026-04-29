<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

/**
 * @author Bram Gerritsen <bgerritsen@emico.nl>
 * @copyright (c) Emico B.V. 2017
 */

namespace Tweakwise\AttributeLandingTweakwise\Model;

use Emico\AttributeLanding\Api\Data\FilterInterface;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Model\Filter;
use Emico\AttributeLanding\Model\FilterHider\FilterHiderInterface;
use Emico\AttributeLanding\Model\LandingPageContext;
use Emico\AttributeLanding\Model\UrlFinder;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Filter\Item;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\FilterSlugManager;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetType\SettingsType;
use Tweakwise\Magento2Tweakwise\Model\Config as TweakwiseConfig;
use Tweakwise\Magento2Tweakwise\Model\Config\Source\UrlStrategy as UrlStrategySource;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;

class FilterManager
{
    /**
     * @var array
     */
    protected $activeFilters;

    /**
     * @var array
     */
    protected $activeFiltersExcludingLandingPageFilters;

    /**
     * @var Resolver
     */
    protected $layerResolver;

    /**
     * @var FilterHiderInterface
     */
    protected $filterHider;

    /**
     * @var LandingPageContext
     */
    protected $landingPageContext;

    /**
     * @var UrlFinder
     */
    protected $urlFinder;

    /**
     * Maximum amount of active filters considered for partial landing-page subset lookup.
     * Limits 2^n combinations to a sane bound.
     */
    private const MAX_PARTIAL_MATCH_FILTERS = 8;

    /**
     * FilterManager constructor.
     * @param Resolver $layerResolver
     * @param FilterHiderInterface $filterHider
     * @param LandingPageContext $landingPageContext
     * @param UrlFinder $urlFinder
     * @param TweakwiseConfig $tweakwiseConfig
     * @param FilterSlugManager $filterSlugManager
     */
    public function __construct(
        Resolver $layerResolver,
        FilterHiderInterface $filterHider,
        LandingPageContext $landingPageContext,
        UrlFinder $urlFinder,
        private readonly TweakwiseConfig $tweakwiseConfig,
        private readonly FilterSlugManager $filterSlugManager
    ) {
        $this->layerResolver = $layerResolver;
        $this->filterHider = $filterHider;
        $this->landingPageContext = $landingPageContext;
        $this->urlFinder = $urlFinder;
    }

    /**
     * @param Item $filterItem
     * @return string|null
     */
    public function findLandingPageUrlForFilterItem(Item $filterItem)
    {
        $layer = $this->getLayer();
        $categoryId = (int)$layer->getCurrentCategory()->getEntityId();

        $candidateItems = array_merge($this->getAllActiveFilters(), [$filterItem]);

        $candidateFilters = array_map(
            static function (Item $item) {
                return new Filter(
                    $item->getFilter()->getUrlKey(),
                    $item->getAttribute()->getTitle()
                );
            },
            $candidateItems
        );

        // 1) Try exact match (existing behavior, including current landing page filters).
        $attributeLandingFilters = $this->getLandingsPageFilters();
        $exactFilters = array_unique(
            array_merge($candidateFilters, $attributeLandingFilters),
            SORT_REGULAR
        );

        $url = $this->urlFinder->findUrlByFilters($exactFilters, $categoryId);
        if ($url) {
            return $url;
        }

        //    Skip this when already on a landing page to avoid cross-linking from one
        //    landing page to another via a partial match.
        if ($this->landingPageContext->getLandingPage() !== null) {
            return null;
        }

        // 2) Fallback: find a landing page that matches a subset of the candidate filters
        //    and append the remaining filters according to the configured URL strategy.
        $partialMatch = $this->findBestPartialLandingPageMatch($candidateItems, $categoryId);
        if ($partialMatch === null) {
            return null;
        }

        [$matchedUrl, $remainingItems] = $partialMatch;
        if (empty($remainingItems)) {
            return $matchedUrl;
        }

        return $this->appendFiltersToUrl($matchedUrl, $remainingItems);
    }

    /**
     * Look for the largest subset of $items for which a landing page URL is registered.
     * Returns a tuple of [matched URL, remaining items not covered by the landing page]
     * or null when no subset matches.
     *
     * @param Item[] $items
     * @param int $categoryId
     * @return array{0:string,1:Item[]}|null
     */
    protected function findBestPartialLandingPageMatch(array $items, int $categoryId): ?array
    {
        $count = count($items);
        if ($count === 0 || $count > self::MAX_PARTIAL_MATCH_FILTERS) {
            return null;
        }

        $indices = array_keys($items);

        // Try larger subsets first (most specific landing page wins).
        for ($size = $count - 1; $size >= 1; $size--) {
            foreach ($this->generateCombinations($indices, $size) as $subsetIndices) {
                $subset = [];
                foreach ($subsetIndices as $idx) {
                    $subset[$idx] = $items[$idx];
                }

                $filters = array_map(
                    static function (Item $item) {
                        return new Filter(
                            $item->getFilter()->getUrlKey(),
                            $item->getAttribute()->getTitle()
                        );
                    },
                    $subset
                );

                $url = $this->urlFinder->findUrlByFilters($filters, $categoryId);
                if (!$url) {
                    continue;
                }

                $remaining = [];
                foreach ($items as $idx => $item) {
                    if (!isset($subset[$idx])) {
                        $remaining[] = $item;
                    }
                }

                return [$url, $remaining];
            }
        }

        return null;
    }

    /**
     * Generate all combinations of $size elements from $values.
     *
     * @param array $values
     * @param int $size
     * @return iterable<array>
     */
    protected function generateCombinations(array $values, int $size): iterable
    {
        $count = count($values);
        if ($size <= 0 || $size > $count) {
            return;
        }

        if ($size === $count) {
            yield $values;
            return;
        }

        if ($size === 1) {
            foreach ($values as $value) {
                yield [$value];
            }
            return;
        }

        $first = $values[0];
        $rest = array_slice($values, 1);

        foreach ($this->generateCombinations($rest, $size - 1) as $combination) {
            yield array_merge([$first], $combination);
        }

        yield from $this->generateCombinations($rest, $size);
    }

    /**
     * Append the given filter items to the supplied landing-page URL using the
     * configured Tweakwise URL strategy (path slugs or query parameters).
     *
     * @param string $url
     * @param Item[] $items
     * @return string
     */
    protected function appendFiltersToUrl(string $url, array $items): string
    {
        if ($this->tweakwiseConfig->getUrlStrategy() === UrlStrategySource::STRATEGY_PATH_SLUGS) {
            return $this->appendFiltersAsPathSlugs($url, $items);
        }

        return $this->appendFiltersAsQueryParams($url, $items);
    }

    /**
     * Append filters to the URL using SEO path slugs (e.g. /color/red/size/m/).
     *
     * @param string $url
     * @param Item[] $items
     * @return string
     */
    protected function appendFiltersAsPathSlugs(string $url, array $items): string
    {
        $segments = [];
        usort($items, [$this, 'sortItemsForUrl']);
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

            $segments[] = $urlKey . '/' . $slug;
        }

        if (empty($segments)) {
            return $url;
        }

        $parts = parse_url($url);
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        $newPath = $path . '/' . implode('/', $segments) . '/';

        $rebuilt = '';
        if (isset($parts['scheme'], $parts['host'])) {
            $rebuilt = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $rebuilt .= ':' . $parts['port'];
            }
        }
        $rebuilt .= $newPath;
        if (!empty($parts['query'])) {
            $rebuilt .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        // Collapse accidental double slashes (but not in scheme).
        return preg_replace('/(?<!:)\/\//', '/', $rebuilt);
    }

    /**
     * Append filters to the URL using query parameters (e.g. ?color=Red&size=M).
     *
     * @param string $url
     * @param Item[] $items
     * @return string
     */
    protected function appendFiltersAsQueryParams(string $url, array $items): string
    {
        $queryParams = [];
        foreach ($items as $item) {
            $settings = $item->getFilter()->getFacet()->getFacetSettings();
            if ($settings->getSource() === SettingsType::SOURCE_CATEGORY) {
                continue;
            }

            $urlKey = $settings->getUrlKey() ?: $item->getFilter()->getUrlKey();
            $value = $item->getAttribute()->getTitle();

            if ($settings->getIsMultipleSelect()) {
                $queryParams[$urlKey][] = $value;
            } else {
                $queryParams[$urlKey] = $value;
            }
        }

        if (empty($queryParams)) {
            return $url;
        }

        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . http_build_query($queryParams);
    }

    /**
     * Sort filter items by url key then attribute title for deterministic URL output.
     *
     * @param Item $a
     * @param Item $b
     * @return int
     */
    protected function sortItemsForUrl(Item $a, Item $b): int
    {
        $keyA = $a->getFilter()->getUrlKey();
        $keyB = $b->getFilter()->getUrlKey();
        if ($keyA === $keyB) {
            return $a->getAttribute()->getTitle() <=> $b->getAttribute()->getTitle();
        }

        return $keyA <=> $keyB;
    }

    /**
     * @return FilterInterface[]
     */
    public function getLandingsPageFilters()
    {
        $landingsPage = $this->landingPageContext->getLandingPage();
        // @phpstan-ignore-next-line
        if (!$landingsPage) {
            return [];
        }

        return $landingsPage->getFilters();
    }

    /**
     * @return Item[]
     */
    public function getActiveFiltersExcludingLandingPageFilters(): array
    {
        if ($this->activeFiltersExcludingLandingPageFilters === null) {
            $filters = $this->getAllActiveFilters();
            $landingPage = $this->landingPageContext->getLandingPage();
            // @phpstan-ignore-next-line
            if ($landingPage === null) {
                return $filters;
            }

            /** @var string|int $index  */
            foreach ($filters as $index => $filterItem) {
                if (
                    !$this->filterHider->shouldHideFilter(
                        $landingPage,
                        $filterItem->getFilter(),
                        $filterItem
                    )
                ) {
                    continue;
                }

                unset($filters[$index]);
            }

            $this->activeFiltersExcludingLandingPageFilters = $filters;
        }

        return $this->activeFiltersExcludingLandingPageFilters;
    }

    /**
     * @return array|Item[]
     */
    public function getAllActiveFilters(): array
    {
        if ($this->activeFilters !== null) {
            return $this->activeFilters;
        }

        $filterItems = $this->getLayer()->getState()->getFilters();
        // @phpstan-ignore-next-line
        if (!is_array($filterItems)) {
            return [];
        }

        // Do not consider category as active
        $filterItems = array_filter(
            $filterItems,
            // @phpstan-ignore-next-line
            function (Item $filter) {
                $source = $filter
                ->getFilter()
                ->getFacet()
                ->getFacetSettings()
                ->getSource();
                return $source !== SettingsType::SOURCE_CATEGORY;
            }
        );
        $this->activeFilters = $filterItems;
        // @phpstan-ignore-next-line
        return $this->activeFilters;
    }

    /**
     * @return Layer
     */
    protected function getLayer(): Layer
    {
        return $this->layerResolver->get();
    }

    /**
     * @param LandingPageInterface $landingPage
     * @param Item $filterItem
     * @return bool
     */
    public function isFilterAvailableOnLandingPage(LandingPageInterface $landingPage, Item $filterItem): bool
    {
        foreach ($landingPage->getFilters() as $landingPageFilter) {
            if (
                $filterItem->getAttribute()->getTitle() === $landingPageFilter->getValue() &&
                $filterItem->getFilter()->getUrlKey() === $landingPageFilter->getFacet()
            ) {
                return true;
            }
        }

        return false;
    }
}
