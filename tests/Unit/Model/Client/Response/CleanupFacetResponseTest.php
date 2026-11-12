<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\Client\Response;

use Emico\CodeCept\Test\Unit;
use Mockery;
use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\CleanupFacetResponse;
use Tweakwise\Magento2Tweakwise\Model\Client\Request;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetType;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetTypeFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Test\Support\UnitTester;

class CleanupFacetResponseTest extends Unit
{
    protected UnitTester $tester;

    public function _after(): void
    {
        Mockery::close();
    }

    public function testIncludesFacetsOfAllSelectionTypes(): void
    {
        $factory = Mockery::mock(FacetTypeFactory::class);
        $factory->shouldReceive('create')->twice()->andReturnUsing(static fn() => new FacetType());
        $response = new CleanupFacetResponse(
            Mockery::mock(Helper::class),
            Mockery::mock(Request::class),
            $factory,
            [
                'facets' => [
                    'facet' => [
                        ['facet_settings' => ['urlkey' => 'color', 'selectiontype' => 'checkbox']],
                        ['facet_settings' => ['urlkey' => 'price', 'selectiontype' => 'slider']],
                    ],
                ],
            ],
        );

        $this->assertSame(
            ['color', 'price'],
            array_map(static fn(FacetType $facet) => $facet->getFacetSettings()->getUrlKey(), $response->getFacets()),
        );
    }
}
