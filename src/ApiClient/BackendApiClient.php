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
                $attributes[$record['Id']] = [
                    'value' => $record['UrlName'],
                    'label' => $record['Name'],
                    'id' => $record['Id']
                ];
            }

            $this->cache->save($this->jsonSerializer->serialize($attributes), $cacheKey, [], $this->getCacheLifetime());

            return $attributes;
        } catch (GuzzleException | Exception $e) {
            $this->logger->critical(
                'Retrieving attributes from Tweakwise Backend API failed',
                [
                    'exception' => $e->getMessage()
                ]
            );
            return [];
        }
    }

    /**
     * @param Store $store
     * @param int $filterTemplate
     * @return array
     */
    public function getAttributesFromFilterTemplate(Store $store, int $filterTemplate): array
    {
        $cacheKey = $this->getCacheKey('attributes', (int)$store->getId(), filterTemplate: $filterTemplate);
        if ($this->cacheExists($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }

        $attributes = [];
        try {
            $response = $this->doRequest(sprintf('filtertemplate/%s/attribute', $filterTemplate), $store);
            $contents = $response->getBody()->getContents();
            $result = is_array($this->jsonSerializer->unserialize($contents)) ?
                $this->jsonSerializer->unserialize($contents) :
                [];

            $allAttributes = $result ? $this->getAttributes($store) : [];

            foreach ($result as $filterTemplateAttribute) {
                if (!isset($allAttributes[$filterTemplateAttribute['AttributeId']])) {
                    continue;
                }

                $attributes[] = [
                    'value' => $allAttributes[$filterTemplateAttribute['AttributeId']]['value'],
                    'label' => $filterTemplateAttribute['Name'],
                    'id' => $filterTemplateAttribute['AttributeId']
                ];
            }

            $this->cache->save($this->jsonSerializer->serialize($attributes), $cacheKey, [], $this->getCacheLifetime());

            return $attributes;
        } catch (GuzzleException | Exception $e) {
            $this->logger->critical(
                'Retrieving filter template attributes from Tweakwise Backend API failed',
                [
                    'exception' => $e->getMessage()
                ]
            );
            return [];
        }
    }

    /**
     * @param string $attributeCode
     * @param Store $store
     * @return int|null
     */
    public function getAttributeIdByCode(string $attributeCode, Store $store): ?int
    {
        foreach ($this->getAttributes($store) as $attributeId => $attribute) {
            if ($attribute['value'] === $attributeCode) {
                return $attributeId;
            }
        }

        return null;
    }

    /**
     * @param int $attributeId
     * @param Store $store
     * @return array
     */
    public function getAttributeValues(int $attributeId, Store $store): array
    {
        $cacheKey = $this->getCacheKey('attribute_values', (int)$store->getId(), $attributeId);
        if ($this->cacheExists($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }

        $attributeValues = [];
        try {
            $response = $this->doRequest(sprintf('attribute/%s/values', $attributeId), $store);
            $contents = $response->getBody()->getContents();
            $result = $this->jsonSerializer->unserialize($contents);

            if (!isset($result['Records'])) {
                return [];
            }

            foreach ($result['Records'] as $record) {
                $attributeValues[] = [
                    'value' => $record['Value'],
                    'label' => $record['Value'],
                ];
            }

            $this->cache->save($this->jsonSerializer->serialize($attributeValues), $cacheKey, [], $this->getCacheLifetime());

            return $attributeValues;
        } catch (GuzzleException | Exception $e) {
            $this->logger->critical(
                'Retrieving attribute values from Tweakwise Backend API failed',
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
     * @param int|null $attributeId
     * @param int|null $filterTemplate
     * @return string
     */
    private function getCacheKey(string $type, int $storeId, ?int $attributeId = null, ?int $filterTemplate = null): string
    {
        if ($attributeId) {
            return sprintf(
                'tweakwise_backend_api_result_type_%s_attribute_%s_store_%s',
                $type,
                $attributeId,
                $storeId
            );
        }
        if ($filterTemplate) {
            return sprintf(
                'tweakwise_backend_api_result_type_%s_ft_%s_store_%s',
                $type,
                $filterTemplate,
                $storeId
            );
        }
        return sprintf('tweakwise_backend_api_result_type_%s_store_%s', $type, $storeId);
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

    /**
     * Protected function added so that cache lifetime is overwritable
     * @return int
     */
    protected function getCacheLifetime(): int
    {
        return self::CACHE_LIFETIME;
    }
}
