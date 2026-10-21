<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Plugin\Seo;

use Emico\AttributeLanding\Model\LandingPageContext;
use Laminas\Http\Header\GenericMultiHeader;
use Magento\Framework\App\Response\Http;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Result\Layout;
use Tweakwise\Magento2Tweakwise\Model\Config;

class OutageSafetyPlugin
{
    public function __construct(
        private readonly LandingPageContext $landingPageContext,
        private readonly Config $config
    ) {
    }

    public function afterGetRobots(PageConfig $subject, string $result): string
    {
        if (!$this->isDegradedLandingPage()) {
            return $result;
        }

        if (stripos($result, 'noindex') !== false) {
            return $result;
        }

        if (preg_match('/\bindex\b/i', $result)) {
            return preg_replace('/\bindex\b/i', 'NOINDEX', $result) ?? 'NOINDEX';
        }

        return 'NOINDEX' . ($result !== '' ? ',' . $result : '');
    }

    public function afterRenderResult(Layout $subject, Layout $result, Http $response): Layout
    {
        if (!$this->isDegradedLandingPage()) {
            return $result;
        }

        // Rendering can encounter an outage after the robots metadata has already been emitted.
        $this->addNoindexHeader($response);
        $response->setNoCacheHeaders();

        return $result;
    }

    private function addNoindexHeader(Http $response): void
    {
        $headers = $response->getHeaders();
        $existing = [];
        foreach ($headers as $header) {
            if (strcasecmp($header->getFieldName(), 'X-Robots-Tag') !== 0) {
                continue;
            }
            $existing[] = $header;
        }

        $headers->getPluginClassLoader()->registerPlugin('xrobotstag', GenericMultiHeader::class);
        foreach ($existing as $header) {
            $headers->removeHeader($header);
            $headers->addHeader(new GenericMultiHeader('X-Robots-Tag', $header->getFieldValue()));
        }
        $headers->addHeader(new GenericMultiHeader('X-Robots-Tag', 'noindex'));
    }

    private function isDegradedLandingPage(): bool
    {
        return $this->landingPageContext->isOnLandingPage() && $this->config->getTweakwiseExceptionTrown();
    }
}
