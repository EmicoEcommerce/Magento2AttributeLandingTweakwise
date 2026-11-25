<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Controller\Adminhtml\Ajax;

use Emico\CodeCept\Test\Unit;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tweakwise\AttributeLandingTweakwise\ApiClient\BackendApiClient;
use Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax\CleanupFacets;
use Tweakwise\AttributeLandingTweakwise\Model\Client\Response\CleanupFacetResponse;
use Tweakwise\AttributeLandingTweakwise\Model\Config;
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
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getId')->andReturn(2);
        $oldStore = Mockery::mock(Store::class);
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

        $controller = new CleanupFacets($this->config(), $stores, Mockery::mock(BackendApiClient::class), $request, $jsonFactory, $client, $factory, $helper);
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
        $store = Mockery::mock(Store::class);
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
        $controller = new CleanupFacets($this->config(), $stores, Mockery::mock(BackendApiClient::class), $request, Mockery::mock(JsonFactory::class), $client, $factory, $helper);

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
        $store = Mockery::mock(Store::class);
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
        $controller = new CleanupFacets($this->config(), $stores, Mockery::mock(BackendApiClient::class), $request, Mockery::mock(JsonFactory::class), $client, $factory, $helper);

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
        $first = Mockery::mock(Store::class);
        $first->shouldReceive('getId')->andReturn(1);
        $second = Mockery::mock(Store::class);
        $second->shouldReceive('getId')->andReturn(2);
        $previous = Mockery::mock(Store::class);
        $previous->shouldReceive('getId')->andReturn(0);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getDefaultStoreView')->andReturn($first);
        $stores->shouldReceive('getStores')->once()->andReturn([$first, $second]);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($previous);
        $stores->shouldReceive('setCurrentStore')->with(1)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(2)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(0)->once()->ordered();

        $facetRequest = Mockery::mock(Request::class);
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', '1000142')->once();
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', '1000242')->once();
        $facetRequest->shouldReceive('setParameter')->with('tn_ft', '17')->twice();
        $factory = Mockery::mock(RequestFactory::class);
        $factory->shouldReceive('create')->twice()->andReturn($facetRequest);
        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getTweakwiseId')->with(1, 42)->andReturn('1000142');
        $helper->shouldReceive('getTweakwiseId')->with(2, 42)->andReturn('1000242');
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

        $controller = new CleanupFacets($this->config(), $stores, Mockery::mock(BackendApiClient::class), $request, $jsonFactory, $client, $factory, $helper);
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
        $first = Mockery::mock(Store::class);
        $first->shouldReceive('getId')->andReturn(1);
        $second = Mockery::mock(Store::class);
        $second->shouldReceive('getId')->andReturn(2);
        $previous = Mockery::mock(Store::class);
        $previous->shouldReceive('getId')->andReturn(0);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStores')->andReturn([$first, $second]);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($previous);
        $stores->shouldReceive('setCurrentStore')->with(1)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(2)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(0)->once()->ordered();
        $facetRequest = Mockery::mock(Request::class);
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', '1000142')->once();
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', '1000242')->once();
        $factory = Mockery::mock(RequestFactory::class);
        $factory->shouldReceive('create')->twice()->andReturn($facetRequest);
        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getTweakwiseId')->with(1, 42)->andReturn('1000142');
        $helper->shouldReceive('getTweakwiseId')->with(2, 42)->andReturn('1000242');
        $response = Mockery::mock(CleanupFacetResponse::class);
        $response->shouldReceive('toArray')->andReturn(['facets' => []]);
        $response->shouldReceive('getFacets')->andReturn([]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')->twice()->andReturn($response, null);
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldNotReceive('create');

        $controller = new CleanupFacets($this->config(), $stores, Mockery::mock(BackendApiClient::class), $request, $jsonFactory, $client, $factory, $helper);
        $this->expectException(LocalizedException::class);
        $controller->execute();
    }

    private function config(?string $backendToken = null): Config&MockInterface
    {
        $config = Mockery::mock(Config::class);
        $config->shouldReceive('getBackendApiToken')->andReturn($backendToken);
        $config->shouldReceive('getGeneralAuthenticationKey')->andReturn('instance');
        return $config;
    }

    private function backendRequest(string $storeId, string $template): RequestInterface&MockInterface
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('category_id')->andReturn('42');
        $request->shouldReceive('getParam')->with('store_id')->andReturn($storeId);
        $request->shouldReceive('getParam')->with('filter_template')->andReturn($template);
        $request->shouldReceive('getParam')->with('store')->andReturnNull();
        $request->shouldReceive('getParam')->with('website')->andReturnNull();
        $request->shouldReceive('setParams');
        return $request;
    }

    private function store(int $id): Store&MockInterface
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getId')->andReturn($id);
        return $store;
    }

    public function testBackendApiUsesFilterTemplateAttributesLikeTheAttributeDropdown(): void
    {
        $store = $this->store(2);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStore')->with(2)->andReturn($store);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($this->store(0));
        $stores->shouldReceive('setCurrentStore');
        $backend = Mockery::mock(BackendApiClient::class);
        $backend->shouldReceive('getAttributesFromFilterTemplateOrFail')->with($store, 17)->once()
            ->andReturn([['value' => 'size', 'label' => 'Size', 'id' => 5]]);
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('request');
        $json = Mockery::mock(Json::class);
        $json->shouldReceive('setData')->with([['value' => 'size']])->once()->andReturnSelf();
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldReceive('create')->andReturn($json);

        $controller = new CleanupFacets(
            $this->config('token'),
            $stores,
            $backend,
            $this->backendRequest('2', '17'),
            $jsonFactory,
            $client,
            Mockery::mock(RequestFactory::class),
            Mockery::mock(Helper::class)
        );
        $this->assertSame($json, $controller->execute());
    }

    public function testBackendApiWithoutTemplateUsesAllAttributesOncePerInstance(): void
    {
        $first = $this->store(1);
        $second = $this->store(2);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStores')->andReturn([$first, $second]);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($this->store(0));
        $stores->shouldReceive('setCurrentStore');
        $backend = Mockery::mock(BackendApiClient::class);
        $backend->shouldReceive('getAttributesOrFail')->with($first)->once()
            ->andReturn([5 => ['value' => 'size', 'label' => 'Size', 'id' => 5]]);
        $json = Mockery::mock(Json::class);
        $json->shouldReceive('setData')->with([['value' => 'size']])->once()->andReturnSelf();
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldReceive('create')->andReturn($json);

        $controller = new CleanupFacets(
            $this->config('token'),
            $stores,
            $backend,
            $this->backendRequest('0', ''),
            $jsonFactory,
            Mockery::mock(Client::class),
            Mockery::mock(RequestFactory::class),
            Mockery::mock(Helper::class)
        );
        $this->assertSame($json, $controller->execute());
    }

    public function testBackendApiFailureIsNotReturnedAsAnEmptyList(): void
    {
        $store = $this->store(2);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStore')->with(2)->andReturn($store);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($this->store(0));
        $stores->shouldReceive('setCurrentStore')->with(2)->once()->ordered();
        $stores->shouldReceive('setCurrentStore')->with(0)->once()->ordered();
        $backend = Mockery::mock(BackendApiClient::class);
        $backend->shouldReceive('getAttributesOrFail')->andThrow(new RuntimeException('timeout'));
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldNotReceive('create');

        $controller = new CleanupFacets(
            $this->config('token'),
            $stores,
            $backend,
            $this->backendRequest('2', ''),
            $jsonFactory,
            Mockery::mock(Client::class),
            Mockery::mock(RequestFactory::class),
            Mockery::mock(Helper::class)
        );
        $this->expectException(LocalizedException::class);
        $controller->execute();
    }

    public function testNavigatorRequestsAreDeduplicatedPerInstanceAndCategory(): void
    {
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStores')->andReturn([$this->store(1), $this->store(2)]);
        $stores->shouldReceive('getStore')->withNoArgs()->andReturn($this->store(0));
        $stores->shouldReceive('setCurrentStore');
        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getTweakwiseId')->andReturn('42');
        $facetRequest = Mockery::mock(Request::class);
        $facetRequest->shouldReceive('setParameter')->with('tn_cid', '42')->once();
        $factory = Mockery::mock(RequestFactory::class);
        $factory->shouldReceive('create')->once()->andReturn($facetRequest);
        $response = Mockery::mock(CleanupFacetResponse::class);
        $response->shouldReceive('toArray')->andReturn(['facets' => []]);
        $response->shouldReceive('getFacets')->andReturn([]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')->once()->andReturn($response);
        $json = Mockery::mock(Json::class);
        $json->shouldReceive('setData')->with([])->once()->andReturnSelf();
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldReceive('create')->andReturn($json);

        $controller = new CleanupFacets(
            $this->config(),
            $stores,
            Mockery::mock(BackendApiClient::class),
            $this->backendRequest('0', ''),
            $jsonFactory,
            $client,
            $factory,
            $helper
        );
        $this->assertSame($json, $controller->execute());
    }
}
