<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model\Client\Response;

use Tweakwise\Magento2Tweakwise\Model\Client\Request;
use Tweakwise\Magento2Tweakwise\Model\Client\Response;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetTypeFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class FacetResponse extends Response
{
    /**
     * @param Helper $helper
     * @param Request $request
     * @param FacetTypeFactory $facetTypeFactory
     * @param array|null $data
     */
    public function __construct(
        Helper $helper,
        Request $request,
        private readonly FacetTypeFactory $facetTypeFactory,
        ?array $data = null
    ) {
        parent::__construct($helper, $request, $data);
    }

    /**
     * @return array
     */
    public function getFacets(): array
    {
        return $this->data['facets'] ?? [];
    }

    /**
     * @param array $facetsData
     * @return $this
     */
    public function setFacets(array $facetsData): FacetResponse
    {
        $facetsData = $this->normalizeArray($facetsData, 'facet');

        $facets = [];
        foreach ($facetsData as $facetData) {
            $facet = $this->facetTypeFactory->create()->setData($facetData);

            // Remove tree, link and slider facets
            if (
                $facet->getFacetSettings()->getSelectionType() !== 'checkbox' &&
                $facet->getFacetSettings()->getSelectionType() !== 'color'
            ) {
                continue;
            }

            $facets[] = $facet;
        }

        $this->data['facets'] = $facets;
        return $this;
    }
}
