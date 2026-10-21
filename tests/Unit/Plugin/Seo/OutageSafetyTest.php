<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Plugin\Seo;

use Emico\AttributeLanding\Model\LandingPage;
use Emico\AttributeLanding\Model\LandingPageContext;
use Emico\CodeCept\Test\Unit;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\PageCache\Version;
use Magento\Framework\Config\ScopeInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\BuilderFactory;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Result\Layout as LayoutResult;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Magento\PageCache\Model\Config as PageCacheConfig;
use Tweakwise\Magento2Tweakwise\Model\Config;
use Tweakwise\Test\Support\UnitTester;

class OutageSafetyTest extends Unit
{
    protected UnitTester $tester;

    protected function setUp(): void
    {
        parent::setUp();
        $objectManager = $this->tester->getObjectManager();
        $objectManager->get(ScopeInterface::class)->setCurrentScope('frontend');
        $objectManager->get(AreaList::class)->getArea('frontend')->load(Area::PART_CONFIG);
    }

    /**
     * @dataProvider robotsProvider
     */
    public function testRobotsDuringOutage(
        bool $landingPage,
        bool $outage,
        string $robots,
        string $expected,
        bool $seoEnabled = false
    ): void {
        $this->tester->mockConfig('tweakwise/seo/enabled', $seoEnabled);
        $objectManager = $this->tester->getObjectManager();
        $context = $objectManager->get(LandingPageContext::class);
        if ($landingPage) {
            $context->setLandingPage($objectManager->create(LandingPage::class));
        }
        $config = $objectManager->get(Config::class);
        $this->assertSame($seoEnabled, $config->isSeoEnabled());
        $config->setTweakwiseExceptionThrown($outage);
        $pageConfig = $objectManager->create(PageConfig::class);
        $pageConfig->setRobots($robots);

        $this->assertSame($expected, $pageConfig->getRobots());
    }

    public static function robotsProvider(): array
    {
        return [
            'outage overrides landing page index' => [true, true, 'INDEX,FOLLOW', 'NOINDEX,FOLLOW'],
            'outage overrides index with Tweakwise SEO enabled' => [true, true, 'INDEX,FOLLOW', 'NOINDEX,FOLLOW', true],
            'outage preserves nofollow' => [true, true, 'INDEX,NOFOLLOW', 'NOINDEX,NOFOLLOW'],
            'outage adds noindex when omitted' => [true, true, 'FOLLOW', 'NOINDEX,FOLLOW'],
            'explicit noindex remains unchanged' => [true, true, 'NOINDEX,NOFOLLOW', 'NOINDEX,NOFOLLOW'],
            'healthy landing page remains unchanged' => [true, false, 'INDEX,FOLLOW', 'INDEX,FOLLOW'],
            'ordinary page remains unchanged' => [false, true, 'INDEX,FOLLOW', 'INDEX,FOLLOW'],
        ];
    }

    /**
     * @dataProvider responseProvider
     */
    public function testResponseAfterRendering(bool $landingPage, bool $outage): void
    {
        $objectManager = $this->tester->getObjectManager();
        if ($landingPage) {
            $objectManager->get(LandingPageContext::class)
                ->setLandingPage($objectManager->create(LandingPage::class));
        }
        $config = $objectManager->get(Config::class);
        $config->setTweakwiseExceptionThrown(false);
        $layout = $this->createMock(Layout::class);
        $layout->method('getOutput')->willReturnCallback(static function () use ($config, $outage): string {
            $config->setTweakwiseExceptionThrown($outage);
            return '<html>Landing page rendered with native products</html>';
        });
        $context = $this->createMock(Context::class);
        $context->method('getLayout')->willReturn($layout);
        $context->method('getEventManager')->willReturn($this->createMock(ManagerInterface::class));
        $context->method('getRequest')->willReturn(
            $objectManager->create(HttpRequest::class)
        );
        $result = $objectManager->create(LayoutResult::class, [
            'context' => $context,
            'translateInline' => $this->createMock(InlineInterface::class),
            'layoutBuilderFactory' => $this->createMock(BuilderFactory::class),
        ]);
        $response = $objectManager->create(Http::class);
        $response->setHeader('Cache-Control', 'public, max-age=86400, s-maxage=86400', true);
        $response->setHeader('X-Robots-Tag', 'nofollow', true);

        $this->assertSame($result, $result->renderResult($response));
        $this->assertStringContainsString('native products', $response->getBody());
        if ($landingPage && $outage) {
            $this->assertStringContainsString('no-store', $response->getHeader('Cache-Control')->getFieldValue());
            $this->assertStringContainsString('noindex', $response->getHeaders()->toString());
            $this->assertStringContainsString('nofollow', $response->getHeader('X-Robots-Tag')->getFieldValue());
        } else {
            $this->assertStringContainsString('public', $response->getHeader('Cache-Control')->getFieldValue());
            $this->assertSame('nofollow', $response->getHeader('X-Robots-Tag')->getFieldValue());
        }
    }

    public static function responseProvider(): array
    {
        return [
            'late outage on landing page' => [true, true],
            'healthy landing page' => [true, false],
            'outage outside landing page' => [false, true],
        ];
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testDegradedResponseIsNotSavedByPageCache(int $cacheType): void
    {
        $objectManager = $this->tester->getObjectManager();
        $cacheConfig = $this->createMock(PageCacheConfig::class);
        $cacheConfig->method('isEnabled')->willReturn(true);
        $cacheConfig->method('getType')->willReturn($cacheType);
        $objectManager->setSharedInstance(PageCacheConfig::class, $cacheConfig);
        $objectManager->get(Registry::class)->register('use_page_cache_plugin', true, true);

        $cache = $this->createMock(PageCache::class);
        $cache->expects($this->never())->method('save');
        $request = $objectManager->create(HttpRequest::class);
        $request->setMethod('GET');
        $kernel = $objectManager->create(Kernel::class, ['fullPageCache' => $cache, 'request' => $request]);
        $kernelSpy = $this->createMock(Kernel::class);
        $kernelSpy->expects($cacheType === PageCacheConfig::BUILT_IN ? $this->once() : $this->never())
            ->method('process')->willReturnCallback(
                function (Http $response) use ($kernel): void {
                    $this->assertStringContainsString('no-store', $response->getHeader('Cache-Control')->getFieldValue());
                    $kernel->process($response);
                }
            );
        $objectManager->setSharedInstance(Kernel::class, $kernelSpy);
        $version = $this->createMock(Version::class);
        $version->expects($cacheType === PageCacheConfig::VARNISH ? $this->once() : $this->never())
            ->method('process');
        $objectManager->setSharedInstance(Version::class, $version);

        $this->testResponseAfterRendering(true, true);
    }

    public static function cacheTypeProvider(): array
    {
        return [
            'built-in cache cannot save degraded response' => [PageCacheConfig::BUILT_IN],
            'Varnish receives uncacheable degraded response' => [PageCacheConfig::VARNISH],
        ];
    }
}
