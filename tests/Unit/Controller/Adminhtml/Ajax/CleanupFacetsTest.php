<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Controller\Adminhtml\Ajax;

use Emico\CodeCept\Test\Unit;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax\CleanupFacets;
use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\CleanupFacetResponse;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\Client\Request;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetType;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\FacetType\SettingsType;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;
use Tweakwise\Test\Support\UnitTester;

class CleanupFacetsTest extends Unit
{
    protected UnitTester $tester;

    public function _after(): void
    {
        Mockery::close();
    }

    public function testUsesSelectedStoreAndCategoryAndRestoresStore(): void
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('category_id')->andReturn('42');
        $request->shouldReceive('getParam')->with('store_id')->andReturn('2');
        $request->shouldReceive('getParam')->with('filter_template')->andReturn('17');
        $request->shouldReceive('getParam')->with('store')->andReturnNull();
        $request->shouldReceive('getParam')->with('website')->andReturnNull();
        $request->shouldReceive('setParams')->with(['store' => 2, 'website' => 0])->once();
        $request->shouldReceive('setParams')->with(['store' => null, 'website' => null])->once();
        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getId')->andReturn(2);
        $oldStore = Mockery::mock(StoreInterface::class);
        $oldStore->shouldReceive('getId')->andReturn(0);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStore')->with(2)->once()->andReturn($store);
        $stores->shouldReceive('getStore')->withNoArgs()->once()->andReturn($oldStore);
        $stores->shouldReceive('setCurrentStore')->with(2)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(0)->once()->ordered();

        $facetRequest = Mockery::mock(Request::class);
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', '1000242')->once();
        $facetRequest->shouldReceive('setParameter')->with('tn_ft', '17')->once();
        $factory = Mockery::mock(RequestFactory::class);
        $factory->shouldReceive('create')->once()->andReturn($facetRequest);
        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getTweakwiseId')->with(2, 42)->once()->andReturn('1000242');

        $settings = Mockery::mock(SettingsType::class);
        $settings->shouldReceive('getUrlKey')->once()->andReturn('size');
        $facet = Mockery::mock(FacetType::class);
        $facet->shouldReceive('getFacetSettings')->once()->andReturn($settings);
        $response = Mockery::mock(CleanupFacetResponse::class);
        $response->shouldReceive('toArray')->once()->andReturn(['facets' => [$facet]]);
        $response->shouldReceive('getFacets')->once()->andReturn([$facet]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')->with($facetRequest)->once()->andReturn($response);
        $json = Mockery::mock(Json::class);
        $json->shouldReceive('setData')->with([['value' => 'size']])->once()->andReturnSelf();
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldReceive('create')->once()->andReturn($json);

        $controller = new CleanupFacets($request, $jsonFactory, $stores, $client, $factory, $helper);
        $this->assertSame($json, $controller->execute());
    }

    public function testFailedRequestIsNotReturnedAsAnEmptyList(): void
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('category_id')->andReturn('42');
        $request->shouldReceive('getParam')->with('store_id')->andReturn('2');
        $request->shouldReceive('getParam')->with('filter_template')->andReturn('');
        $request->shouldReceive('getParam')->with('store')->andReturnNull();
        $request->shouldReceive('getParam')->with('website')->andReturnNull();
        $request->shouldReceive('setParams')->with(['store' => 2, 'website' => 0])->once();
        $request->shouldReceive('setParams')->with(['store' => null, 'website' => null])->once();
        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getId')->andReturn(2);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStore')->with(2)->andReturn($store);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($store);
        $stores->shouldReceive('setCurrentStore')->with(2)->twice();
        $facetRequest = Mockery::mock(Request::class);
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', '1000242');
        $factory = Mockery::mock(RequestFactory::class);
        $factory->shouldReceive('create')->andReturn($facetRequest);
        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getTweakwiseId')->with(2, 42)->andReturn('1000242');
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')->with($facetRequest)->andReturnNull();
        $controller = new CleanupFacets($request, Mockery::mock(JsonFactory::class), $stores, $client, $factory, $helper);

        $this->expectException(LocalizedException::class);
        $controller->execute();
    }

    public function testMissingFacetsBlockIsNotTreatedAsAnEmptyTemplate(): void
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('category_id')->andReturn('42');
        $request->shouldReceive('getParam')->with('store_id')->andReturn('0');
        $request->shouldReceive('getParam')->with('filter_template')->andReturn('');
        $request->shouldReceive('getParam')->with('store')->andReturnNull();
        $request->shouldReceive('getParam')->with('website')->andReturnNull();
        $request->shouldReceive('setParams')->with(['store' => 2, 'website' => 0])->once();
        $request->shouldReceive('setParams')->with(['store' => null, 'website' => null])->once();
        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getId')->andReturn(2);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStores')->once()->andReturn([$store]);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($store);
        $stores->shouldReceive('setCurrentStore')->with(2)->twice();
        $facetRequest = Mockery::mock(Request::class);
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', '1000242');
        $factory = Mockery::mock(RequestFactory::class);
        $factory->shouldReceive('create')->andReturn($facetRequest);
        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getTweakwiseId')->with(2, 42)->andReturn('1000242');
        $response = Mockery::mock(CleanupFacetResponse::class);
        $response->shouldReceive('toArray')->andReturn([]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')->with($facetRequest)->andReturn($response);
        $controller = new CleanupFacets($request, Mockery::mock(JsonFactory::class), $stores, $client, $factory, $helper);

        $this->expectException(LocalizedException::class);
        $controller->execute();
    }

    public function testDefaultScopeKeepsFacetsAvailableInAnyStore(): void
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('category_id')->andReturn('42');
        $request->shouldReceive('getParam')->with('store_id')->andReturn('0');
        $request->shouldReceive('getParam')->with('filter_template')->andReturn('17');
        $request->shouldReceive('getParam')->with('store')->andReturnNull();
        $request->shouldReceive('getParam')->with('website')->andReturnNull();
        $request->shouldReceive('setParams')->with(['store' => 1, 'website' => 0])->once();
        $request->shouldReceive('setParams')->with(['store' => 2, 'website' => 0])->once();
        $request->shouldReceive('setParams')->with(['store' => null, 'website' => null])->once();
        $first = Mockery::mock(StoreInterface::class);
        $first->shouldReceive('getId')->andReturn(1);
        $second = Mockery::mock(StoreInterface::class);
        $second->shouldReceive('getId')->andReturn(2);
        $previous = Mockery::mock(StoreInterface::class);
        $previous->shouldReceive('getId')->andReturn(0);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getDefaultStoreView')->andReturn($first);
        $stores->shouldReceive('getStores')->once()->andReturn([$first, $second]);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($previous);
        $stores->shouldReceive('setCurrentStore')->with(1)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(2)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(0)->once()->ordered();

        $facetRequest = Mockery::mock(Request::class);
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', 'category')->twice();
        $facetRequest->shouldReceive('setParameter')->with('tn_ft', '17')->twice();
        $factory = Mockery::mock(RequestFactory::class);
        $factory->shouldReceive('create')->twice()->andReturn($facetRequest);
        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getTweakwiseId')->with(1, 42)->andReturn('category');
        $helper->shouldReceive('getTweakwiseId')->with(2, 42)->andReturn('category');
        $settings = Mockery::mock(SettingsType::class);
        $settings->shouldReceive('getUrlKey')->andReturn('size');
        $facet = Mockery::mock(FacetType::class);
        $facet->shouldReceive('getFacetSettings')->andReturn($settings);
        $empty = Mockery::mock(CleanupFacetResponse::class);
        $empty->shouldReceive('toArray')->andReturn(['facets' => []]);
        $empty->shouldReceive('getFacets')->andReturn([]);
        $nonEmpty = Mockery::mock(CleanupFacetResponse::class);
        $nonEmpty->shouldReceive('toArray')->andReturn(['facets' => [$facet]]);
        $nonEmpty->shouldReceive('getFacets')->andReturn([$facet]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')->twice()->andReturn($empty, $nonEmpty);
        $json = Mockery::mock(Json::class);
        $json->shouldReceive('setData')->with([['value' => 'size']])->once()->andReturnSelf();
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldReceive('create')->andReturn($json);

        $controller = new CleanupFacets($request, $jsonFactory, $stores, $client, $factory, $helper);
        $this->assertSame($json, $controller->execute());
    }

    public function testDefaultScopeRejectsPartialResultsWhenAStoreFails(): void
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('category_id')->andReturn('42');
        $request->shouldReceive('getParam')->with('store_id')->andReturn('0');
        $request->shouldReceive('getParam')->with('filter_template')->andReturn('');
        $request->shouldReceive('getParam')->with('store')->andReturnNull();
        $request->shouldReceive('getParam')->with('website')->andReturnNull();
        $request->shouldReceive('setParams')->with(['store' => 1, 'website' => 0])->once();
        $request->shouldReceive('setParams')->with(['store' => 2, 'website' => 0])->once();
        $request->shouldReceive('setParams')->with(['store' => null, 'website' => null])->once();
        $first = Mockery::mock(StoreInterface::class);
        $first->shouldReceive('getId')->andReturn(1);
        $second = Mockery::mock(StoreInterface::class);
        $second->shouldReceive('getId')->andReturn(2);
        $previous = Mockery::mock(StoreInterface::class);
        $previous->shouldReceive('getId')->andReturn(0);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStores')->andReturn([$first, $second]);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($previous);
        $stores->shouldReceive('setCurrentStore')->with(1)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(2)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(0)->once()->ordered();
        $facetRequest = Mockery::mock(Request::class);
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', 'category')->twice();
        $factory = Mockery::mock(RequestFactory::class);
        $factory->shouldReceive('create')->twice()->andReturn($facetRequest);
        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getTweakwiseId')->with(1, 42)->andReturn('category');
        $helper->shouldReceive('getTweakwiseId')->with(2, 42)->andReturn('category');
        $response = Mockery::mock(CleanupFacetResponse::class);
        $response->shouldReceive('toArray')->andReturn(['facets' => []]);
        $response->shouldReceive('getFacets')->andReturn([]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')->twice()->andReturn($response, null);
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldNotReceive('create');

        $controller = new CleanupFacets($request, $jsonFactory, $stores, $client, $factory, $helper);
        $this->expectException(LocalizedException::class);
        $controller->execute();
    }
}
