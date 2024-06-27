<?php

namespace Tweakwise\AttributeLandingTweakwise\Plugin\Block\LayeredNavigation\RenderLayered;

use Emico\AttributeLanding\Model\LandingPageContext;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Filter\Item;
use Magento\Framework\View\Element\Template;

/**
 * Class DefaultRendererPlugin
 */
class RendererPlugin
{
    /**
     * @var FilterManager
     */
    protected $filterManager;

    /**
     * @var LandingPageContext
     */
    protected $landingPageContext;

    /**
     * DefaultRendererPlugin constructor.
     * @param FilterManager $filterManager
     */
    public function __construct(FilterManager $filterManager, LandingPageContext $landingPageContext)
    {
        $this->filterManager = $filterManager;
        $this->landingPageContext = $landingPageContext;
    }

    /**
     * @param Template $renderer
     * @param string $result
     * @param Item $filterItem
     * @return string
     */
    public function afterRenderAnchorHtmlTagAttributes(
        Template $renderer,
        string $result,
        Item $filterItem
    ) {

        $returnToDefaultPage = false;

        $landingPage = $this->landingPageContext->getLandingPage();
        if ($landingPage) {
            $landingPageUrl = $landingPage->getUrlPath();
            if (stripos($result, $landingPageUrl) === false) {
                $returnToDefaultPage = true;
            }
        }

        if (!$this->filterManager->findLandingPageUrlForFilterItem($filterItem) && !$returnToDefaultPage) {
            return $result;
        }

        $htmlAttributes = explode(' ', $result);
        $htmlAttributes[] = 'data-no-ajax="1"';

        return implode(' ', $htmlAttributes);
    }
}
