<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\ApiClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\Store;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Tweakwise\AttributeLandingTweakwise\Model\Config;

class BackendApiClient
{
    private const TWEAKWISE_BACKEND_API_BASE_URL = 'https://navigator-api.tweakwise.com';
    private const CACHE_LIFETIME = 600;

    /**
     * @var ClientInterface|null
     */
    private ?ClientInterface $httpClient = null;

    /**
     * @param Config $config
     * @param LoggerInterface $logger
     * @param Json $jsonSerializer
     * @param CacheInterface $cache
     */
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly Json $jsonSerializer,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return ClientInterface
     */
    private function getHttpClient(): ClientInterface
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client(
                [
                    'base_uri' => self::TWEAKWISE_BACKEND_API_BASE_URL
                ]
            );
        }

        return $this->httpClient;
    }

    /**
     * @param string $path
     * @param Store $store
     * @return ResponseInterface
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function doRequest(
        string $path,
        Store $store
    ): ResponseInterface {
        try {
            return $this->getHttpClient()
                ->request(
                    'GET',
                    $path,
                    [
                        'headers' => [
                            'TWN-InstanceKey' => $this->config->getGeneralAuthenticationKey($store),
                            'TWN-Authentication' => $this->config->getBackendApiToken($store)
                        ]
                    ]
                );
        } catch (Exception $e) {
            $this->logger->critical(
                'Tweakwise Backend API request failed',
                [
                    'path' => $path,
                    'exception' => $e->getMessage()
                ]
            );
            throw $e;
        }
    }

    /**
     * @param Store $store
     * @return array
     */
    public function getAttributes(Store $store): array
    {
        $cacheKey = $this->getCacheKey('attributes', (int)$store->getId());
        if ($this->cacheExists($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }

        $attributes = [];
        try {
            $response = $this->doRequest('attribute', $store);
            $contents = $response->getBody()->getContents();
            $result = $this->jsonSerializer->unserialize($contents);

            if (!isset($result['Records'])) {
                return [];
            }

            foreach ($result['Records'] as $record) {
                $attributes[] = [
                    'value' => $record['UrlName'],
                    'label' => $record['Name'],
                    'id' => $record['Id']
                ];
            }

            $this->cache->save($this->jsonSerializer->serialize($attributes), $cacheKey, [], self::CACHE_LIFETIME);

            return $attributes;
        } catch (GuzzleException | Exception $e) {
            $this->logger->critical(
                'Retrieving attributes from Tweakiwse Backend API failed',
                [
                    'exception' => $e->getMessage()
                ]
            );
            return [];
        }
    }

    /**
     * @param string $type
     * @param int $storeId
     * @return string
     */
    private function getCacheKey(string $type, int $storeId): string
    {
        return sprintf('tweakwise_backend_api_result_%s_%s', $type, $storeId);
    }

    /**
     * @param string $cacheKey
     * @return bool
     */
    private function cacheExists(string $cacheKey): bool
    {
        /** @phpstan-ignore-next-line */
        return $this->cache->load($cacheKey) !== false;
    }

    /**
     * @param string $cacheKey
     * @return array
     */
    private function getFromCache(string $cacheKey): array
    {
        return (array)$this->jsonSerializer->unserialize($this->cache->load($cacheKey));
    }
}
