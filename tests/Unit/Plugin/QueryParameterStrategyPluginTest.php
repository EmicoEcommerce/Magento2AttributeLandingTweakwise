<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Plugin;

use Emico\AttributeLanding\Api\Data\FilterInterface;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Model\LandingPageContext;
use Emico\CodeCept\Test\Unit;
use Magento\Framework\Url;
use PHPUnit\Framework\MockObject\MockObject;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\AttributeLandingTweakwise\Plugin\QueryParameterStrategyPlugin;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\QueryParameterStrategy;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\UrlModel;
use Magento\Framework\App\Request\Http;
use Tweakwise\Test\Support\UnitTester;

class QueryParameterStrategyPluginTest extends Unit
{
    protected UnitTester $tester;

    private LandingPageContext|MockObject $landingPageContext;

    private FilterManager|MockObject $filterManager;

    private Url|MockObject $magentoUrl;

    private UrlModel|MockObject $url;

    private QueryParameterStrategyPlugin $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landingPageContext = $this->createMock(LandingPageContext::class);
        $this->filterManager = $this->createMock(FilterManager::class);
        $this->magentoUrl = $this->createMock(Url::class);
        $this->url = $this->createMock(UrlModel::class);

        $this->subject = new QueryParameterStrategyPlugin(
            $this->landingPageContext,
            $this->filterManager,
            $this->magentoUrl,
            $this->url,
        );
    }

    /**
     * @return void
     */
    public function testAfterGetAttributeFiltersDeduplicatesRepeatedValuesForLandingPageFacet(): void
    {
        $landingPage = $this->createMock(LandingPageInterface::class);
        $landingPage->method('getHideSelectedFilters')->willReturn(true);

        $landingPageFilter = $this->createLandingPageFilter('filter_x', 'value-1');
        $this->landingPageContext->method('getLandingPage')->willReturn($landingPage);
        $landingPage->method('getFilters')->willReturn([$landingPageFilter]);
        $this->filterManager->method('getLandingsPageFilters')->willReturn([$landingPageFilter]);

        $result = $this->subject->afterGetAttributeFilters(
            $this->createMock(QueryParameterStrategy::class),
            ['filter_x' => ['value-1', 'value-2', 'value-1'], 'filter_y' => 'keep-me'],
            $this->createMock(Http::class)
        );

        $this->assertSame(['filter_x' => ['value-2'], 'filter_y' => 'keep-me'], $result);
    }

    /**
     * @return void
     */
    public function testAfterGetAttributeFiltersPreservesNonLandingPageValues(): void
    {
        $landingPage = $this->createMock(LandingPageInterface::class);
        $landingPage->method('getHideSelectedFilters')->willReturn(true);

        $landingPageFilter = $this->createLandingPageFilter('filter_x', 'value-1');
        $this->landingPageContext->method('getLandingPage')->willReturn($landingPage);
        $landingPage->method('getFilters')->willReturn([$landingPageFilter]);
        $this->filterManager->method('getLandingsPageFilters')->willReturn([$landingPageFilter]);

        $result = $this->subject->afterGetAttributeFilters(
            $this->createMock(QueryParameterStrategy::class),
            ['filter_y' => ['one', 'two'], 'filter_z' => 'keep-me'],
            $this->createMock(Http::class)
        );

        $this->assertSame(['filter_y' => ['one', 'two'], 'filter_z' => 'keep-me'], $result);
    }

    private function createLandingPageFilter(string $facet, string $value): FilterInterface
    {
        $filter = $this->createMock(FilterInterface::class);
        $filter->method('getFacet')->willReturn($facet);
        $filter->method('getValue')->willReturn($value);

        return $filter;
    }
}
