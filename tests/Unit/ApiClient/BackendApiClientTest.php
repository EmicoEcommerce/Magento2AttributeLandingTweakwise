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
}
