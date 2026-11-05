<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\ApiClient;

use Emico\CodeCept\Test\Unit;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Tweakwise\AttributeLandingTweakwise\ApiClient\BackendApiClient;
use Tweakwise\AttributeLandingTweakwise\Model\Config;
use Tweakwise\Test\Support\UnitTester;

/**
 * @see AB#231242 Magento | Change backend API to App API
 *
 * The legacy Tweakwise backend API (https://navigator-api.tweakwise.com) is being
 * replaced by the App API (https://app-api.tweakwise.com). Authentication headers
 * are unchanged; only the base URL is switched.
 */
class BackendApiClientTest extends Unit
{
    protected UnitTester $tester;

    private Config|MockObject $config;

    private LoggerInterface|MockObject $logger;

    private Json|MockObject $jsonSerializer;

    private CacheInterface|MockObject $cache;

    private ClientInterface|MockObject $httpClient;

    private BackendApiClient $subject;

    private array $requestPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->jsonSerializer = $this->createMock(Json::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->httpClient = $this->createMock(ClientInterface::class);

        $this->subject = new BackendApiClient(
            $this->config,
            $this->logger,
            $this->jsonSerializer,
            $this->cache
        );

        // Replace the lazily-constructed Guzzle client with a mock so we can
        // inspect the base_uri configuration without hitting the network.
        $reflection = new ReflectionClass($this->subject);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->subject, $this->httpClient);
    }

    /**
     * The client must target the new App API base URL, not the legacy
     * navigator-api URL.
     */
    public function testDoRequestTargetsAppApiBaseUrl(): void
    {
        $store = $this->createMock(Store::class);
        $this->config->method('getGeneralAuthenticationKey')->willReturn('instance-key');
        $this->config->method('getBackendApiToken')->willReturn('api-token');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'attribute',
                $this->callback(function (array $options): bool {
                    return ($options['headers']['TWN-InstanceKey'] ?? null) === 'instance-key'
                        && ($options['headers']['TWN-Authentication'] ?? null) === 'api-token';
                })
            )
            ->willReturn(new Response(200, [], '{"Records":[]}'));

        $this->subject->doRequest('attribute', $store);

        $reflection = new ReflectionClass(BackendApiClient::class);
        $baseUrlConstant = $reflection->getConstant('TWEAKWISE_BACKEND_API_BASE_URL');

        $this->assertSame(
            'https://app-api.tweakwise.com',
            $baseUrlConstant,
            'BackendApiClient must call the new App API, not the legacy navigator-api endpoint.'
        );
    }

    public function testGetAttributesReadsAllPages(): void
    {
        $this->preparePaginatedResponses(
            'attribute',
            $this->createAttributeRecords(1, 100),
            $this->createAttributeRecords(101, 1)
        );

        $attributes = $this->subject->getAttributes($this->createMock(Store::class));

        $this->assertCount(101, $attributes);
        $this->assertSame('attribute-101', $attributes[101]['value']);
        $this->assertSame(
            ['attribute?limit=100&offset=0', 'attribute?limit=100&offset=100'],
            $this->requestPaths
        );
    }

    public function testGetAttributeValuesReadsAllPages(): void
    {
        $this->preparePaginatedResponses(
            'attribute/5/values/distinct',
            $this->createValueRecords(1, 100),
            $this->createValueRecords(101, 1)
        );

        $values = $this->subject->getAttributeValues(5, $this->createMock(Store::class));

        $this->assertCount(101, $values);
        $this->assertSame('value-101', $values[100]['value']);
        $this->assertSame(
            [
                'attribute/5/values/distinct?limit=100&offset=0',
                'attribute/5/values/distinct?limit=100&offset=100',
            ],
            $this->requestPaths
        );
    }

    public function testGetAttributesDoesNotCacheWhenAPageHasAnUnexpectedResponse(): void
    {
        $store = $this->createMock(Store::class);
        $this->config->method('getGeneralAuthenticationKey')->willReturn('instance-key');
        $this->config->method('getBackendApiToken')->willReturn('api-token');
        $this->cache->method('load')->willReturn(false);
        $this->cache->expects($this->never())->method('save');
        $this->jsonSerializer->method('unserialize')->willReturnCallback(
            static fn(string $contents): array => json_decode($contents, true, flags: JSON_THROW_ON_ERROR)
        );
        $this->logger->expects($this->once())->method('critical');

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode([
                    'Total' => 101,
                    'Records' => $this->createAttributeRecords(1, 100),
                ], JSON_THROW_ON_ERROR)),
                new Response(200, [], '{"unexpected":"response"}')
            );

        $this->assertSame([], $this->subject->getAttributes($store));
    }

    private function preparePaginatedResponses(string $path, array $firstPage, array $secondPage): void
    {
        $this->config->method('getGeneralAuthenticationKey')->willReturn('instance-key');
        $this->config->method('getBackendApiToken')->willReturn('api-token');
        $this->cache->method('load')->willReturn(false);
        $this->cache->method('save')->willReturn(true);
        $this->jsonSerializer->method('unserialize')->willReturnCallback(
            static fn(string $contents): array => json_decode($contents, true, flags: JSON_THROW_ON_ERROR)
        );
        $this->jsonSerializer->method('serialize')->willReturn('serialized');

        $this->requestPaths = [];
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $requestPath) use ($path, $firstPage, $secondPage): Response {
                $this->assertSame('GET', $method);
                $offset = count($this->requestPaths) * 100;
                $this->assertSame(sprintf('%s?limit=100&offset=%d', $path, $offset), $requestPath);
                $this->requestPaths[] = $requestPath;

                $page = $offset === 0 ? $firstPage : $secondPage;

                return new Response(200, [], json_encode([
                    'Total' => 101,
                    'Records' => $page,
                ], JSON_THROW_ON_ERROR));
            });
    }

    private function createAttributeRecords(int $firstId, int $count): array
    {
        $records = [];
        for ($id = $firstId; $id < $firstId + $count; $id++) {
            $records[] = [
                'Id' => $id,
                'UrlName' => 'attribute-' . $id,
                'Name' => 'Attribute ' . $id,
            ];
        }

        return $records;
    }

    private function createValueRecords(int $firstId, int $count): array
    {
        $records = [];
        for ($id = $firstId; $id < $firstId + $count; $id++) {
            $records[] = ['Value' => 'value-' . $id];
        }

        return $records;
    }
}
