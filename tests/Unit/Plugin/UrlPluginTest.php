<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Plugin;

use Emico\AttributeLanding\Model\Config;
use Emico\AttributeLanding\Model\LandingPage;
use Emico\AttributeLanding\Model\LandingPageContext;
use Emico\CodeCept\Test\Unit;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\AttributeLandingTweakwise\Plugin\UrlPlugin;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Filter\Item;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\NavigationContext\CurrentContext;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url;

class UrlPluginTest extends Unit
{
    private Resolver|MockObject $layerResolver;

    private LandingPageContext|MockObject $landingPageContext;

    private FilterManager|MockObject $filterManager;

    private UrlPlugin $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->layerResolver = $this->createMock(Resolver::class);
        $this->landingPageContext = $this->createMock(LandingPageContext::class);
        $this->filterManager = $this->createMock(FilterManager::class);

        $this->subject = new UrlPlugin(
            $this->layerResolver,
            $this->landingPageContext,
            $this->filterManager,
            $this->createMock(Config::class),
            $this->createMock(CurrentContext::class),
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function removeFilterUrlProvider(): array
    {
        return [
            'plus and parentheses' => [
                't-shirts+(sale)',
                'https://shop.test/t-shirts+(sale)/color/red',
                'https://shop.test/category.html/color/red',
            ],
            'dot' => [
                'shoes.html',
                'https://shop.test/shoes.html/color/red',
                'https://shop.test/category.html/color/red',
            ],
            'dot does not match any character' => [
                'shoes.html',
                'https://shop.test/shoesXhtml/color/red',
                'https://shop.test/shoesXhtml/color/red',
            ],
            'unbalanced parenthesis' => [
                'sale(2024',
                'https://shop.test/sale(2024/color/red',
                'https://shop.test/category.html/color/red',
            ],
            'pipe' => [
                'a|b',
                'https://shop.test/a|b/color/red',
                'https://shop.test/category.html/color/red',
            ],
        ];
    }

    #[DataProvider('removeFilterUrlProvider')]
    public function testAroundGetRemoveFilterTreatsLandingPagePathLiterally(
        string $requestPath,
        string $removeUrl,
        string $expected
    ): void {
        $landingPage = $this->createMock(LandingPage::class);
        $landingPage->method('getHideSelectedFilters')->willReturn(false);
        $landingPage->method('getUrlRewriteRequestPath')->willReturn($requestPath);
        $this->landingPageContext->method('getLandingPage')->willReturn($landingPage);
        $this->filterManager->method('isFilterAvailableOnLandingPage')->willReturn(true);

        $category = $this->createMock(Category::class);
        $category->method('getUrl')->willReturn('https://shop.test/category.html');
        $layer = $this->createMock(Layer::class);
        $layer->method('getCurrentCategory')->willReturn($category);
        $this->layerResolver->method('get')->willReturn($layer);

        $result = $this->subject->aroundGetRemoveFilter(
            $this->createMock(Url::class),
            static fn () => $removeUrl,
            $this->createMock(Item::class),
        );

        $this->assertSame($expected, $result);
    }
}
