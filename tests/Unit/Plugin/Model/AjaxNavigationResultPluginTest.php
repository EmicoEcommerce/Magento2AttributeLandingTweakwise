<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Plugin\Model;

use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Model\Config as AlpConfig;
use Emico\AttributeLanding\Model\LandingPageContext;
use Emico\CodeCept\Test\Unit;
use Mockery;
use Mockery\MockInterface;
use Magento\Framework\App\Request\Http as MagentoHttpRequest;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\AttributeLandingTweakwise\Plugin\Model\AjaxNavigationResultPlugin;
use Tweakwise\Magento2Tweakwise\Model\AjaxNavigationResult;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\UrlModel;

class AjaxNavigationResultPluginTest extends Unit
{
    private MagentoHttpRequest&MockInterface $request;

    private LandingPageContext&MockInterface $landingPageContext;

    private FilterManager&MockInterface $filterManager;

    private Url&MockInterface $url;

    private UrlModel&MockInterface $urlModel;

    private AlpConfig&MockInterface $alpConfig;

    private StoreManagerInterface&MockInterface $storeManager;

    private AjaxNavigationResultPlugin $subject;

    public function _before(): void
    {
        $this->request = Mockery::mock(MagentoHttpRequest::class);
        $this->landingPageContext = Mockery::mock(LandingPageContext::class);
        $this->filterManager = Mockery::mock(FilterManager::class);
        $this->url = Mockery::mock(Url::class);
        $this->urlModel = Mockery::mock(UrlModel::class);
        $this->alpConfig = Mockery::mock(AlpConfig::class);
        $this->storeManager = Mockery::mock(StoreManagerInterface::class);

        $this->subject = new AjaxNavigationResultPlugin(
            $this->request,
            $this->landingPageContext,
            $this->filterManager,
            $this->url,
            $this->urlModel,
            $this->alpConfig,
            $this->storeManager,
        );
    }

    public function _after(): void
    {
        Mockery::close();
    }

    public function testAroundGetCanonicalUrlFallsBackToProceedForNonLandingPageRequest(): void
    {
        $this->request->shouldReceive('getParam')->with('__tw_ajax_type')->andReturn('category');
        $this->landingPageContext->shouldReceive('getLandingPage')->andReturn(null);

        $proceedCalled = false;
        $proceed = static function (string $responseUrl) use (&$proceedCalled): string {
            $proceedCalled = true;
            return 'proceed:' . $responseUrl;
        };

        $result = $this->subject->aroundGetCanonicalUrl(
            Mockery::mock(AjaxNavigationResult::class),
            $proceed,
            'https://example.com/filter-url'
        );

        $this->assertTrue($proceedCalled);
        $this->assertSame('proceed:https://example.com/filter-url', $result);
    }

    public function testAroundGetCanonicalUrlReturnsLandingPageCanonicalOverride(): void
    {
        $landingPage = Mockery::mock(LandingPageInterface::class);

        $this->request->shouldReceive('getParam')->with('__tw_ajax_type')->andReturn('landingpage');
        $this->request->shouldReceive('getParam')->with('p')->andReturn('4');
        $this->landingPageContext->shouldReceive('getLandingPage')->andReturn($landingPage);
        $landingPage->shouldReceive('getCanonicalUrl')->andReturn('https://canonical.test/fixed-url');

        $result = $this->subject->aroundGetCanonicalUrl(
            Mockery::mock(AjaxNavigationResult::class),
            static fn (string $responseUrl): string => $responseUrl,
            'https://example.com/filter-url?p=4'
        );

        $this->assertSame('https://canonical.test/fixed-url', $result);
    }

    public function testAroundGetCanonicalUrlUsesSelfReferencingResponseUrlAndAppendsPageParam(): void
    {
        $landingPage = Mockery::mock(LandingPageInterface::class);

        $this->request->shouldReceive('getParam')->with('__tw_ajax_type')->andReturn('landingpage');
        $this->request->shouldReceive('getParam')->with('p')->andReturn('3');
        $this->landingPageContext->shouldReceive('getLandingPage')->andReturn($landingPage);
        $landingPage->shouldReceive('getCanonicalUrl')->andReturn('');
        $this->alpConfig->shouldReceive('isCanonicalSelfReferencingEnabled')->andReturn(true);

        $result = $this->subject->aroundGetCanonicalUrl(
            Mockery::mock(AjaxNavigationResult::class),
            static fn (string $responseUrl): string => $responseUrl,
            'https://example.com/landing?color=red'
        );

        $this->assertSame('https://example.com/landing?color=red&p=3', $result);
    }

    public function testAroundGetCanonicalUrlUsesBareLandingPageUrlWhenSelfReferencingDisabled(): void
    {
        $landingPage = Mockery::mock(LandingPageInterface::class);
        $store = Mockery::mock(StoreInterface::class);

        $this->request->shouldReceive('getParam')->with('__tw_ajax_type')->andReturn('landingpage');
        $this->request->shouldReceive('getParam')->with('p')->andReturn('2');
        $this->landingPageContext->shouldReceive('getLandingPage')->andReturn($landingPage);
        $landingPage->shouldReceive('getCanonicalUrl')->andReturn(null);
        $this->alpConfig->shouldReceive('isCanonicalSelfReferencingEnabled')->andReturn(false);
        $landingPage->shouldReceive('getUrlPath')->andReturn('red-pants.html');
        $this->storeManager->shouldReceive('getStore')->andReturn($store);
        $store->shouldReceive('getUrl')->with('', ['_direct' => 'red-pants.html'])->andReturn('https://shop.test/red-pants.html');

        $result = $this->subject->aroundGetCanonicalUrl(
            Mockery::mock(AjaxNavigationResult::class),
            static fn (string $responseUrl): string => $responseUrl,
            'https://example.com/ignored'
        );

        $this->assertSame('https://shop.test/red-pants.html?p=2', $result);
    }

    public function testAroundGetCanonicalUrlHandlesUnparseableUrlWhenAppendingPageParam(): void
    {
        $landingPage = Mockery::mock(LandingPageInterface::class);

        $this->request->shouldReceive('getParam')->with('__tw_ajax_type')->andReturn('landingpage');
        $this->request->shouldReceive('getParam')->with('p')->andReturn('2');
        $this->landingPageContext->shouldReceive('getLandingPage')->andReturn($landingPage);
        $landingPage->shouldReceive('getCanonicalUrl')->andReturn('');
        $this->alpConfig->shouldReceive('isCanonicalSelfReferencingEnabled')->andReturn(true);

        $result = $this->subject->aroundGetCanonicalUrl(
            Mockery::mock(AjaxNavigationResult::class),
            static fn (string $responseUrl): string => $responseUrl,
            'https://example.com:abc/path'
        );

        $this->assertSame('https://example.com:abc/path?p=2', $result);
    }
}
