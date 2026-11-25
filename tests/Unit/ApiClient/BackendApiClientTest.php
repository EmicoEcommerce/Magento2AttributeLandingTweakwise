<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\ApiClient;

use Emico\CodeCept\Test\Unit;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\Store;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Tweakwise\AttributeLandingTweakwise\ApiClient\BackendApiClient;
use Tweakwise\AttributeLandingTweakwise\Model\Config;

class BackendApiClientTest extends Unit
{
    public function _after(): void
    {
        Mockery::close();
    }

    private function client(): BackendApiClient&MockInterface
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('load')->andReturnFalse();
        $cache->shouldReceive('save');
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('critical');

        return Mockery::mock(
            BackendApiClient::class . '[doRequest]',
            [Mockery::mock(Config::class), $logger, new Json(), $cache]
        );
    }

    private function store(): Store&MockInterface
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getId')->andReturn(1);
        return $store;
    }

    public function testStrictVariantsReturnAttributesLikeLenientVariants(): void
    {
        $client = $this->client();
        $client->shouldReceive('doRequest')->with('attribute', Mockery::any())
            ->andReturnUsing(fn () => new Response(200, [], '{"Records":[{"Id":5,"UrlName":"size","Name":"Size"}]}'));
        $client->shouldReceive('doRequest')->with('filtertemplate/17/attribute', Mockery::any())
            ->andReturn(new Response(200, [], '[{"AttributeId":5,"Name":"Maat"}]'));

        $expected = [['value' => 'size', 'label' => 'Maat', 'id' => 5]];
        $this->assertSame($expected, $client->getAttributesFromFilterTemplateOrFail($this->store(), 17));
        $this->assertSame(
            [5 => ['value' => 'size', 'label' => 'Size', 'id' => 5]],
            $client->getAttributesOrFail($this->store())
        );
    }

    public function testLenientVariantsReturnEmptyListOnFailure(): void
    {
        $client = $this->client();
        $client->shouldReceive('doRequest')->andThrow(new RuntimeException('timeout'));

        $this->assertSame([], $client->getAttributes($this->store()));
        $this->assertSame([], $client->getAttributesFromFilterTemplate($this->store(), 17));
    }

    public function testStrictAttributesThrowOnRequestFailure(): void
    {
        $client = $this->client();
        $client->shouldReceive('doRequest')->andThrow(new RuntimeException('timeout'));

        $this->expectException(RuntimeException::class);
        $client->getAttributesOrFail($this->store());
    }

    public function testStrictAttributesThrowOnInvalidResponse(): void
    {
        $client = $this->client();
        $client->shouldReceive('doRequest')->andReturn(new Response(200, [], '{}'));

        $this->expectException(LocalizedException::class);
        $client->getAttributesOrFail($this->store());
    }

    public function testStrictFilterTemplateAttributesThrowWhenAttributeLookupFails(): void
    {
        $client = $this->client();
        $client->shouldReceive('doRequest')->with('filtertemplate/17/attribute', Mockery::any())
            ->andReturn(new Response(200, [], '[{"AttributeId":5,"Name":"Maat"}]'));
        $client->shouldReceive('doRequest')->with('attribute', Mockery::any())
            ->andThrow(new RuntimeException('timeout'));

        $this->expectException(RuntimeException::class);
        $client->getAttributesFromFilterTemplateOrFail($this->store(), 17);
    }

    public function testStrictLookupDoesNotReuseEmptyResultCachedAfterLenientFailure(): void
    {
        $cached = new stdClass();
        $cached->entries = [];
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('load')->andReturnUsing(fn ($key) => $cached->entries[$key] ?? false);
        $cache->shouldReceive('save')->andReturnUsing(function ($value, $key) use ($cached) {
            $cached->entries[$key] = $value;
            return true;
        });
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('critical');
        $client = Mockery::mock(
            BackendApiClient::class . '[doRequest]',
            [Mockery::mock(Config::class), $logger, new Json(), $cache]
        );
        $attributeCalls = new stdClass();
        $attributeCalls->count = 0;
        $client->shouldReceive('doRequest')->with('filtertemplate/17/attribute', Mockery::any())
            ->andReturnUsing(fn () => new Response(200, [], '[{"AttributeId":5,"Name":"Maat"}]'));
        $client->shouldReceive('doRequest')->with('attribute', Mockery::any())
            ->andReturnUsing(function () use ($attributeCalls) {
                if ($attributeCalls->count++ === 0) {
                    throw new RuntimeException('timeout');
                }
                return new Response(200, [], '{"Records":[{"Id":5,"UrlName":"size","Name":"Size"}]}');
            });

        $this->assertSame([], $client->getAttributesFromFilterTemplate($this->store(), 17));
        $this->assertSame(
            [['value' => 'size', 'label' => 'Maat', 'id' => 5]],
            $client->getAttributesFromFilterTemplateOrFail($this->store(), 17)
        );
    }
}
