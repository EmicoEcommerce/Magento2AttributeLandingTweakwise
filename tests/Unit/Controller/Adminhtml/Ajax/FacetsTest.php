<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Controller\Adminhtml\Ajax;

use Emico\CodeCept\Test\Unit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use Tweakwise\AttributeLandingTweakwise\ApiClient\BackendApiClient;
use Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax\Facets;
use Tweakwise\AttributeLandingTweakwise\Model\Config;
use Tweakwise\Magento2Tweakwise\Model\Client;
use Tweakwise\Magento2Tweakwise\Model\Client\RequestFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class FacetsTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    public function testStoreViewPageOnlyOffersFacetsOfThatStoreView(): void
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('store_id')->andReturn('2');
        $request->shouldReceive('getParam')->with('filter_template')->andReturn('17');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getId')->andReturn(2);
        $stores = Mockery::mock(StoreManagerInterface::class);
        $stores->shouldReceive('getStore')->with(2)->andReturn($store);
        $stores->shouldNotReceive('getStores');
        $config = Mockery::mock(Config::class);
        $config->shouldReceive('getBackendApiToken')->with($store)->andReturn('token');
        $backend = Mockery::mock(BackendApiClient::class);
        $backend->shouldReceive('getAttributesFromFilterTemplate')->with($store, 17)->once()
            ->andReturn([['value' => 'size', 'label' => 'Size']]);
        $json = Mockery::mock(Json::class);
        $json->shouldReceive('setData')->with([
            ['value' => 'size', 'label' => 'Size'],
            ['value' => Facets::OTHER_ATTRIBUTE_VALUE, 'label' => 'Other (text field)'],
        ])->once()->andReturnSelf();
        $jsonFactory = Mockery::mock(JsonFactory::class);
        $jsonFactory->shouldReceive('create')->andReturn($json);

        $controller = new Facets(
            Mockery::mock(Context::class)->shouldIgnoreMissing(),
            $config,
            $stores,
            $backend,
            $request,
            $jsonFactory,
            Mockery::mock(Client::class),
            Mockery::mock(RequestFactory::class),
            Mockery::mock(Helper::class)
        );
        $this->assertSame($json, $controller->execute());
    }
}
