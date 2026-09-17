<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model;

use Emico\AttributeLanding\Model\FilterHider\FilterHiderInterface;
use Emico\AttributeLanding\Model\Filter as LandingPageFilter;
use Emico\AttributeLanding\Model\LandingPageContext;
use Emico\AttributeLanding\Model\UrlFinder;
use Emico\CodeCept\Test\Unit;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Category;
use PHPUnit\Framework\MockObject\MockObject;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Filter as LayerFilter;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Filter\Item;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\AttributeType;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetType;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetType\SettingsType;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\Strategy\FilterSlugManager;
use Tweakwise\Magento2Tweakwise\Model\Config as TweakwiseConfig;
use Tweakwise\Magento2Tweakwise\Model\Config\Source\UrlStrategy as UrlStrategySource;
use Tweakwise\Test\Support\UnitTester;

class FilterManagerTest extends Unit
{
    protected UnitTester $tester;

    private Resolver|MockObject $layerResolver;

    private LandingPageContext|MockObject $landingPageContext;

    private UrlFinder|MockObject $urlFinder;

    private TweakwiseConfig|MockObject $tweakwiseConfig;

    private FilterSlugManager|MockObject $filterSlugManager;

    private FilterManager $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->layerResolver = $this->createMock(Resolver::class);
        $this->landingPageContext = $this->createMock(LandingPageContext::class);
        $this->urlFinder = $this->createMock(UrlFinder::class);
        $this->tweakwiseConfig = $this->createMock(TweakwiseConfig::class);
        $this->filterSlugManager = $this->createMock(FilterSlugManager::class);

        $this->subject = $this->getMockBuilder(FilterManager::class)
            ->setConstructorArgs([
                $this->layerResolver,
                $this->createMock(FilterHiderInterface::class),
                $this->landingPageContext,
                $this->urlFinder,
                $this->tweakwiseConfig,
                $this->filterSlugManager,
            ])
            ->onlyMethods(['getLayer', 'getAllActiveFilters'])
            ->getMock();
    }

    /**
     * @return void
     */
    public function testFindLandingPageUrlForFilterItemPrioritizesLandingPageAndKeepsSupplementalQueryFilters(): void
    {
        $this->tweakwiseConfig->method('getUrlStrategy')->willReturn(UrlStrategySource::STRATEGY_QUERY_PARAM);

        $layer = $this->createMock(Layer::class);
        $category = $this->createMock(Category::class);
        $category->method('getEntityId')->willReturn(42);
        $layer->method('getCurrentCategory')->willReturn($category);
        $this->subject->method('getLayer')->willReturn($layer);

        $activeFilter = $this->createFilterItem('filter_y', 'value-y');
        $selectFilter = $this->createFilterItem('filter_x', 'value-x');

        $this->subject->method('getAllActiveFilters')->willReturn([$activeFilter]);
        $this->landingPageContext->method('getLandingPage')->willReturn(null);

        $this->urlFinder->expects($this->exactly(3))->method('findUrlByFilters')->willReturnCallback(
            static function (array $filters): ?string {
                $firstFilter = reset($filters);

                if (count($filters) === 1 && $firstFilter instanceof LandingPageFilter && $firstFilter->getFacet() === 'filter_x') {
                    return 'https://example.test/landing-page';
                }

                return null;
            }
        );

        $result = $this->subject->findLandingPageUrlForFilterItem($selectFilter);

        $this->assertSame('https://example.test/landing-page?filter_y=value-y', $result);
    }

    /**
     * @return void
     */
    public function testFindLandingPageUrlForFilterItemUsesPathSlugStrategyForSupplementalFilters(): void
    {
        $this->tweakwiseConfig->method('getUrlStrategy')->willReturn(UrlStrategySource::STRATEGY_PATH_SLUGS);

        $layer = $this->createMock(Layer::class);
        $category = $this->createMock(Category::class);
        $category->method('getEntityId')->willReturn(42);
        $layer->method('getCurrentCategory')->willReturn($category);
        $this->subject->method('getLayer')->willReturn($layer);

        $activeFilter = $this->createFilterItem('filter_y', 'value-y');
        $selectFilter = $this->createFilterItem('filter_x', 'value-x');

        $this->subject->method('getAllActiveFilters')->willReturn([$activeFilter]);
        $this->landingPageContext->method('getLandingPage')->willReturn(null);
        $this->urlFinder->expects($this->exactly(3))->method('findUrlByFilters')->willReturnCallback(
            static function (array $filters): ?string {
                $firstFilter = reset($filters);

                if (count($filters) === 1 && $firstFilter instanceof LandingPageFilter && $firstFilter->getFacet() === 'filter_x') {
                    return 'https://example.test/landing-page';
                }

                return null;
            }
        );

        $this->filterSlugManager->method('getSlugForFilterItem')->with($activeFilter)->willReturn('value-y');

        $result = $this->subject->findLandingPageUrlForFilterItem($selectFilter);

        $this->assertSame('https://example.test/landing-page/filter_y/value-y/', $result);
    }

    private function createFilterItem(string $facet, string $value): Item
    {
        $settings = new SettingsType([
            'source' => SettingsType::SOURCE_FEED,
            'selectiontype' => SettingsType::SELECTION_TYPE_CHECKBOX,
            'ismultiselect' => 'false',
            'urlkey' => '',
        ]);

        $facetType = new FacetType();
        $facetType->setFacetSettings($settings);

        $filter = $this->createMock(LayerFilter::class);
        $filter->method('getUrlKey')->willReturn($facet);
        $filter->method('getFacet')->willReturn($facetType);

        $attribute = $this->createMock(AttributeType::class);
        $attribute->method('getTitle')->willReturn($value);

        $item = $this->createMock(Item::class);
        $item->method('getFilter')->willReturn($filter);
        $item->method('getAttribute')->willReturn($attribute);

        return $item;
    }
}
