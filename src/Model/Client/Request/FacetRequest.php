<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model\Client\Request;

use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\FacetResponse;
use Tweakwise\Magento2Tweakwise\Model\Client\Request;

class FacetRequest extends Request
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
        return FacetResponse::class;
    }
}
