<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Plugin;

use Emico\AttributeLanding\Api\Data\FilterInterface;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Model\LandingPageContext;
use Emico\CodeCept\Test\Unit;
use Mockery;
use Mockery\MockInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\AttributeLandingTweakwise\Plugin\PathSlugStrategyPlugin;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\FilterSlugManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\PathSlugStrategy;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\UrlFactory;

class PathSlugStrategyPluginTest extends Unit
{
    private LandingPageContext&MockInterface $landingPageContext;

    private FilterManager&MockInterface $filterManager;

    private UrlFactory&MockInterface $urlFactory;

    private FilterSlugManager&MockInterface $filterSlugManager;

    private StoreManagerInterface&MockInterface $storeManager;

    private PathSlugStrategyPlugin $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landingPageContext = Mockery::mock(LandingPageContext::class);
        $this->filterManager = Mockery::mock(FilterManager::class);
        $this->urlFactory = Mockery::mock(UrlFactory::class);
        $this->filterSlugManager = Mockery::mock(FilterSlugManager::class);
        $this->storeManager = Mockery::mock(StoreManagerInterface::class);

        $this->subject = new PathSlugStrategyPlugin(
            $this->landingPageContext,
            $this->filterManager,
            $this->urlFactory,
            $this->filterSlugManager,
            $this->storeManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAfterGetCategoryFilterSelectUrlUsesStoreScopedSlug(): void
    {
        $landingPage = Mockery::mock(LandingPageInterface::class);
        $landingPage->shouldReceive('getHideSelectedFilters')->andReturn(true);
        $this->landingPageContext->shouldReceive('getLandingPage')->andReturn($landingPage);

        $this->filterManager->shouldReceive('getLandingsPageFilters')->andReturn([
            $this->createLandingPageFilter('color', 'Black / Zwart'),
        ]);

        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getId')->andReturn(2);
        $this->storeManager->shouldReceive('getStore')->andReturn($store);

        $this->filterSlugManager->shouldReceive('getLookupTable')->andReturn([
            2 => ['black / zwart' => 'black-zwart-store'],
            0 => ['black / zwart' => 'black-zwart-default'],
        ]);

        $result = $this->subject->afterGetCategoryFilterSelectUrl(
            Mockery::mock(PathSlugStrategy::class),
            '/women'
        );

        $this->assertSame('/women/color/black-zwart-store', $result);
    }

    public function testAfterGetCategoryFilterSelectUrlFallsBackToGlobalStoreSlug(): void
    {
        $landingPage = Mockery::mock(LandingPageInterface::class);
        $landingPage->shouldReceive('getHideSelectedFilters')->andReturn(true);
        $this->landingPageContext->shouldReceive('getLandingPage')->andReturn($landingPage);

        $this->filterManager->shouldReceive('getLandingsPageFilters')->andReturn([
            $this->createLandingPageFilter('color', 'Black / Zwart'),
        ]);

        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getId')->andReturn(2);
        $this->storeManager->shouldReceive('getStore')->andReturn($store);

        $this->filterSlugManager->shouldReceive('getLookupTable')->andReturn([
            0 => ['black / zwart' => 'black-zwart-default'],
        ]);

        $result = $this->subject->afterGetCategoryFilterSelectUrl(
            Mockery::mock(PathSlugStrategy::class),
            '/women'
        );

        $this->assertSame('/women/color/black-zwart-default', $result);
    }

    public function testAfterGetCategoryFilterSelectUrlFallsBackToLowercaseFilterValue(): void
    {
        $landingPage = Mockery::mock(LandingPageInterface::class);
        $landingPage->shouldReceive('getHideSelectedFilters')->andReturn(true);
        $this->landingPageContext->shouldReceive('getLandingPage')->andReturn($landingPage);

        $this->filterManager->shouldReceive('getLandingsPageFilters')->andReturn([
            $this->createLandingPageFilter('color', 'Black / Zwart'),
        ]);

        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getId')->andReturn(2);
        $this->storeManager->shouldReceive('getStore')->andReturn($store);

        $this->filterSlugManager->shouldReceive('getLookupTable')->andReturn([]);

        $result = $this->subject->afterGetCategoryFilterSelectUrl(
            Mockery::mock(PathSlugStrategy::class),
            '/women'
        );

        $this->assertSame('/women/color/black / zwart', $result);
    }

    private function createLandingPageFilter(string $facet, string $value): FilterInterface
    {
        $filter = Mockery::mock(FilterInterface::class);
        $filter->shouldReceive('getFacet')->andReturn($facet);
        $filter->shouldReceive('getValue')->andReturn($value);

        return $filter;
    }
}
