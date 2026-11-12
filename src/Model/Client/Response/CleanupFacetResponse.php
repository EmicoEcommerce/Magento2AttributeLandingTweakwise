<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model\Client\Response;

use Tweakwise\Magento2Tweakwise\Model\Client\Request;
use Tweakwise\Magento2Tweakwise\Model\Client\Response;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetTypeFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class CleanupFacetResponse extends Response
{
    public function __construct(
        Helper $helper,
        Request $request,
        private readonly FacetTypeFactory $facetTypeFactory,
        ?array $data = null,
    ) {
        parent::__construct($helper, $request, $data);
    }

    public function setFacets(array $facetsData): self
    {
        $facets = [];
        foreach ($this->normalizeArray($facetsData, 'facet') as $facetData) {
            $facets[] = $this->facetTypeFactory->create()->setData($facetData);
        }

        $this->data['facets'] = $facets;
        return $this;
    }

    public function getFacets(): array
    {
        return $this->data['facets'] ?? [];
    }
}
