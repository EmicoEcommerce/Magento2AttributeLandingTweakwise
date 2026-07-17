<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Plugin;

use Emico\AttributeLanding\Api\Data\FilterInterface;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Model\LandingPageContext;
use Emico\CodeCept\Test\Unit;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\AttributeLandingTweakwise\Plugin\PathSlugStrategyPlugin;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\FilterSlugManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\PathSlugStrategy;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\UrlFactory;

class PathSlugStrategyPluginTest extends Unit
{
    private LandingPageContext|MockObject $landingPageContext;

    private FilterManager|MockObject $filterManager;

    private UrlFactory|MockObject $urlFactory;

    private FilterSlugManager|MockObject $filterSlugManager;

    private StoreManagerInterface|MockObject $storeManager;

    private PathSlugStrategyPlugin $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landingPageContext = $this->createMock(LandingPageContext::class);
        $this->filterManager = $this->createMock(FilterManager::class);
        $this->urlFactory = $this->createMock(UrlFactory::class);
        $this->filterSlugManager = $this->createMock(FilterSlugManager::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->subject = new PathSlugStrategyPlugin(
            $this->landingPageContext,
            $this->filterManager,
            $this->urlFactory,
            $this->filterSlugManager,
            $this->storeManager,
        );
    }

    public function testAfterGetCategoryFilterSelectUrlUsesStoreScopedSlug(): void
    {
        $landingPage = $this->createMock(LandingPageInterface::class);
        $landingPage->method('getHideSelectedFilters')->willReturn(true);
        $this->landingPageContext->method('getLandingPage')->willReturn($landingPage);

        $this->filterManager->method('getLandingsPageFilters')->willReturn([
            $this->createLandingPageFilter('color', 'Black / Zwart'),
        ]);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->filterSlugManager->method('getLookupTable')->willReturn([
            2 => ['black / zwart' => 'black-zwart-store'],
            0 => ['black / zwart' => 'black-zwart-default'],
        ]);

        $result = $this->subject->afterGetCategoryFilterSelectUrl(
            $this->createMock(PathSlugStrategy::class),
            '/women'
        );

        $this->assertSame('/women/color/black-zwart-store', $result);
    }

    public function testAfterGetCategoryFilterSelectUrlFallsBackToGlobalStoreSlug(): void
    {
        $landingPage = $this->createMock(LandingPageInterface::class);
        $landingPage->method('getHideSelectedFilters')->willReturn(true);
        $this->landingPageContext->method('getLandingPage')->willReturn($landingPage);

        $this->filterManager->method('getLandingsPageFilters')->willReturn([
            $this->createLandingPageFilter('color', 'Black / Zwart'),
        ]);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->filterSlugManager->method('getLookupTable')->willReturn([
            0 => ['black / zwart' => 'black-zwart-default'],
        ]);

        $result = $this->subject->afterGetCategoryFilterSelectUrl(
            $this->createMock(PathSlugStrategy::class),
            '/women'
        );

        $this->assertSame('/women/color/black-zwart-default', $result);
    }

    public function testAfterGetCategoryFilterSelectUrlFallsBackToLowercaseFilterValue(): void
    {
        $landingPage = $this->createMock(LandingPageInterface::class);
        $landingPage->method('getHideSelectedFilters')->willReturn(true);
        $this->landingPageContext->method('getLandingPage')->willReturn($landingPage);

        $this->filterManager->method('getLandingsPageFilters')->willReturn([
            $this->createLandingPageFilter('color', 'Black / Zwart'),
        ]);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->filterSlugManager->method('getLookupTable')->willReturn([]);

        $result = $this->subject->afterGetCategoryFilterSelectUrl(
            $this->createMock(PathSlugStrategy::class),
            '/women'
        );

        $this->assertSame('/women/color/black / zwart', $result);
    }

    private function createLandingPageFilter(string $facet, string $value): FilterInterface
    {
        $filter = $this->createMock(FilterInterface::class);
        $filter->method('getFacet')->willReturn($facet);
        $filter->method('getValue')->willReturn($value);

        return $filter;
    }
}
