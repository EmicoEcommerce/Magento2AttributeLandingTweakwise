<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model\Client\Request;

use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\FacetAttributesResponse;
use Tweakwise\Magento2Tweakwise\Model\Client\Request;

class FacetAttributeRequest extends Request
{
    /**
     * @var string
     */
    protected $path = 'facets';

    /**
     * @return string
     */
    public function getResponseType()
    {
        return FacetAttributesResponse::class;
    }

    /**
     * @param string $facetKey
     * @return void
     */
    public function addFacetKey(string $facetKey): void
    {
        $this->setPath(sprintf('%s/%s/attributes', $this->path, $facetKey));
    }
}
