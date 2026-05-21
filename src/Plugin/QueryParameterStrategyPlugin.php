<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

/**
 * @author : Edwin Jacobs, email: ejacobs@emico.nl.
 * @copyright : Copyright Emico B.V. 2020.
 */

namespace Tweakwise\AttributeLandingTweakwise\Plugin;

use Emico\AttributeLanding\Model\LandingPageContext;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\QueryParameterStrategy;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\UrlModel;
use Magento\Framework\App\Request\Http as MagentoHttpRequest;
use Magento\Framework\Url;

class QueryParameterStrategyPlugin
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
     * @var Url
     */
    private $magentoUrl;

    /**
     * @var UrlModel
     * @phpstan-ignore-next-line
     */
    private $url;

    public function __construct(
        LandingPageContext $landingPageContext,
        FilterManager $filterManager,
        Url $magentoUrl,
        UrlModel $url
    ) {
        $this->landingPageContext = $landingPageContext;
        $this->filterManager = $filterManager;
        $this->magentoUrl = $magentoUrl;
        $this->url = $url;
    }

    /**
     * Adds landingpage filters to category select url
     *
     * @param QueryParameterStrategy $original
     * @param string $result
     * @return string
     *
     * phpcs:disable Magento2.Functions.DiscouragedFunction.Discouraged
     */
    public function afterGetCategoryFilterSelectUrl(
        QueryParameterStrategy $original,
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

        $urlParts = parse_url($result) ? parse_url($result) : null;
        if (!$urlParts) {
            return $result;
        }

        $query = [];
        $queryPart = isset($urlParts['query']) ? $urlParts['query'] : '';
        // Parse the current query parameters as string
        parse_str($queryPart, $query);

        foreach ($landingsPageFilters as $filter) {
            // @phpstan-ignore-next-line
            $query[$filter->getFacet()][] = strtolower($filter->getValue());
        }

        return $this->magentoUrl->getDirectUrl(
            ltrim($urlParts['path'], '/'),
            ['_query' => $query]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterGetAttributeFilters(
        QueryParameterStrategy $original,
        array $result,
        MagentoHttpRequest $request
    ): array {
        $landingPage = $this->landingPageContext->getLandingPage();
        // @phpstan-ignore-next-line
        if ($landingPage === null) {
            return $result;
        }

        $filters = $landingPage->getFilters();

        //hide landingspage filters in url
        foreach ($filters as $filter) {
            $facet = $filter->getFacet();
            if (!isset($result[$facet])) {
                continue;
            }

            $values = $result[$facet];
            // Single-select filters arrive as a scalar string from the request query;
            // normalize to an array so the comparison/unset logic works in both shapes.
            $isScalar = !is_array($values);
            if ($isScalar) {
                $values = [$values];
            }

            // Pre-dedupe: when hide_selected_filters is off the landing-page
            // filter input and the regular checkbox can both submit the same
            // value, producing duplicate entries in the request.
            $values = array_values(array_unique($values));

            foreach ($values as $key => $value) {
                // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedNotEqualOperator
                if ($value != $filter->getValue()) {
                    continue;
                }
                unset($values[$key]);
            }

            // Reindex so the emitted URL uses contiguous keys (ae-color[0], ae-color[1], ...).
            // Without this a removed first entry leaves a gap (e.g. ae-color[1]=Green),
            // which causes the front-end query-string merge to treat ae-color[0] and
            // ae-color[1] as distinct keys and produce duplicate values in the URL.
            $values = array_values($values);

            if (empty($values)) {
                // Keep the key with an empty array rather than unsetting it entirely.
                // getCurrentQueryUrl merges raw request params back into the query using
                // array_key_exists; if the key is absent it re-adds the LP filter value
                // from the request. An empty array acts as a tombstone that prevents this.
                $result[$facet] = [];
                continue;
            }

            $result[$facet] = $isScalar ? reset($values) : $values;
        }

        return $result;
    }
}
