<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Plugin\Seo;

use Emico\AttributeLanding\Model\LandingPageContext;
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
        $response->setHeader('X-Robots-Tag', 'noindex', false);
        $response->setNoCacheHeaders();

        return $result;
    }

    private function isDegradedLandingPage(): bool
    {
        return $this->landingPageContext->isOnLandingPage() && $this->config->getTweakwiseExceptionTrown();
    }
}
