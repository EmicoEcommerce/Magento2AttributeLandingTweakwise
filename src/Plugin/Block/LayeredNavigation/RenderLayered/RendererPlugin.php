<?php

namespace Tweakwise\AttributeLandingTweakwise\Plugin\Block\LayeredNavigation\RenderLayered;

use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\Tweakwise\Model\Catalog\Layer\Filter\Item;
use Magento\Framework\View\Element\Template;

/**
 * Class DefaultRendererPlugin
 * @package Tweakwise\AttributeLandingTweakwise\Plugin\Block\LayeredNavigation\RenderLayered
 */
class RendererPlugin
{
    /**
     * @var FilterManager
     */
    protected $filterManager;

    /**
     * DefaultRendererPlugin constructor.
     * @param FilterManager $filterManager
     */
    public function __construct(FilterManager $filterManager)
    {
        $this->filterManager = $filterManager;
    }

    /**
     * @param Template $renderer used Template here as this class is subscribed multiple times as a plugin
     * @param string $result
     * @param Item $filterItem
     * @return string
     */
    public function afterRenderAnchorHtmlTagAttributes(
        Template $renderer,
        string $result,
        Item $filterItem
    ) {
        if (!$this->filterManager->findLandingPageUrlForFilterItem($filterItem)) {
            return $result;
        }

        $htmlAttributes = explode(' ', $result);
        $htmlAttributes[] = 'data-no-ajax="1"';

        return implode(' ', $htmlAttributes);
    }
}
