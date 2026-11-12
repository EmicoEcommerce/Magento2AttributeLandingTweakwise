<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model\Client\Request;

use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\CleanupFacetResponse;

class CleanupFacetRequest extends FacetRequest
{
    public function getResponseType()
    {
        return CleanupFacetResponse::class;
    }
}
