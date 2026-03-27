<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Tweakwise\Magento2Tweakwise\Model\Config as TweakwiseConfig;

class Config extends TweakwiseConfig
{
    private const BACKEND_API_TOKEN_PATH = 'tweakwise/attribute_landing/backend_api_token';

    /**
     * @param Store|null $store
     * @return string|null
     * @throws LocalizedException
     */
    public function getBackendApiToken(?Store $store = null): ?string
    {
        return $this->getStoreConfig(self::BACKEND_API_TOKEN_PATH, $store);
    }
}
