<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\AjaxResultInitializer;

use Emico\AttributeLanding\Api\Data\FilterInterface;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Api\LandingPageRepositoryInterface;
use Emico\CodeCept\Test\Unit;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Magento\Framework\App\Request\Http as MagentoHttpRequest;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\Model\AjaxResultInitializer\LandingPageCountInitializer;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\NavigationContext;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\ProductNavigationRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\Response\ProductNavigationResponse;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\PropertiesType;
use Tweakwise\Test\Support\UnitTester;

class LandingPageCountInitializerTest extends Unit
{
    protected UnitTester $tester;

    private LandingPageRepositoryInterface&MockInterface $landingPageRepository;

    private NavigationContext&MockInterface $navigationContext;

    private StoreManagerInterface&MockInterface $storeManager;

    private MagentoHttpRequest&MockInterface $request;

    private ProductNavigationRequest&MockInterface $navigationRequest;

    private ProductNavigationResponse&MockInterface $navigationResponse;

    private PropertiesType&MockInterface $properties;

    private LandingPageCountInitializer $subject;

    public function _before(): void
    {
        $this->landingPageRepository = Mockery::mock(LandingPageRepositoryInterface::class);
        $this->navigationContext = Mockery::mock(NavigationContext::class);
        $this->storeManager = Mockery::mock(StoreManagerInterface::class);
        $this->request = Mockery::mock(MagentoHttpRequest::class);
        $this->navigationRequest = Mockery::mock(ProductNavigationRequest::class);
        $this->navigationResponse = Mockery::mock(ProductNavigationResponse::class);
        $this->properties = Mockery::mock(PropertiesType::class);

        $this->subject = new LandingPageCountInitializer(
            $this->landingPageRepository,
            $this->navigationContext,
            $this->storeManager,
        );
    }

    public function _after(): void
    {
        Mockery::close();
    }

    public function testInitializeForCountReturnsProductCountForLandingPageWithSelectedFilters(): void
    {
        $landingPage = Mockery::mock(LandingPageInterface::class);
        $store = Mockery::mock(StoreInterface::class);
        $filter = Mockery::mock(FilterInterface::class);

        $this->request->shouldReceive('getParam')
            ->with('__tw_object_id')
            ->andReturn('1');
        $this->storeManager->shouldReceive('getStore')->andReturn($store);
        $store->shouldReceive('getId')->andReturn(2);
        $this->landingPageRepository->shouldReceive('getByIdWithStore')
            ->with(1, 2)
            ->andReturn($landingPage);

        $landingPage->shouldReceive('isActive')->andReturn(true);
        $landingPage->shouldReceive('getCategoryId')->andReturn(9);
        $landingPage->shouldReceive('getFilters')->andReturn([$filter]);
        $landingPage->shouldReceive('getTweakwiseFilterTemplate')->andReturn(15);
        $landingPage->shouldReceive('getTweakwiseSortTemplate')->andReturn(25);
        $landingPage->shouldReceive('getTweakwiseBuilderTemplate')->andReturn(35);

        $filter->shouldReceive('getValue')->andReturn('Sports');
        $filter->shouldReceive('getFacet')->andReturn('activity');

        $this->request->shouldReceive('getQuery')->andReturn([
            '__tw_ajax_type' => 'landingpage',
            '__tw_object_id' => '1',
            '__tw_original_url' => 'test-alp/',
            '__tw_hash' => 'hash',
            'activity' => ['Recreation', 'Sports'],
        ]);

        $this->navigationContext->shouldReceive('getRequest')->andReturn($this->navigationRequest);
        $this->navigationRequest->shouldReceive('addCategoryFilter')->with(9);
        $this->navigationRequest->shouldReceive('addAttributeFilter')->with('activity', 'Sports');
        $this->navigationRequest->shouldReceive('setTemplateId')->with(15);
        $this->navigationRequest->shouldReceive('setSortTemplateId')->with(25);
        $this->navigationRequest->shouldReceive('setBuilderTemplateId')->with(35);
        $this->navigationRequest->shouldReceive('addAttributeFilter')->with('activity', 'Recreation');
        $this->navigationRequest->shouldReceive('addAttributeFilter')->with('activity', 'Sports');

        $this->navigationContext->shouldReceive('getResponse')->andReturn($this->navigationResponse);
        $this->navigationResponse->shouldReceive('getValue')->with('properties')->andReturn($this->properties);
        $this->properties->shouldReceive('getNumberOfItems')->andReturn(42);

        $this->assertSame(42, $this->subject->initializeForCount($this->request));
    }

    public function testInitializeForCountThrowsExceptionWhenNoLandingPageIdProvided(): void
    {
        $this->request->shouldReceive('getParam')
            ->with('__tw_object_id')
            ->andReturn('0');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No landing page provided for product count request.');

        $this->subject->initializeForCount($this->request);
    }
}
