<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\ApiClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\Store;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Tweakwise\AttributeLandingTweakwise\Model\Config;

class BackendApiClient
{
    private const TWEAKWISE_BACKEND_API_BASE_URL = 'https://navigator-api.tweakwise.com';

    /**
     * @var ClientInterface|null
     */
    private ?ClientInterface $httpClient = null;

    /**
     * @param Config $config
     * @param LoggerInterface $logger
     * @param Json $jsonSerializer
     */
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly Json $jsonSerializer,
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
                    'label' => $record['Name']
                ];
            }

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
}
