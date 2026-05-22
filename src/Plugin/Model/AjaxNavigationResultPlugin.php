<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Tweakwise\AttributeLandingTweakwise\Plugin\Model;

use Emico\AttributeLanding\Model\Config as AlpConfig;
use Emico\AttributeLanding\Model\LandingPageContext;
use Magento\Framework\App\Request\Http as MagentoHttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\AttributeLandingTweakwise\Model\FilterManager;
use Tweakwise\Magento2Tweakwise\Model\AjaxNavigationResult;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url\UrlModel;

class AjaxNavigationResultPlugin
{
    /**
     * @var MagentoHttpRequest
     */
    private $request;

    /**
     * @var LandingPageContext
     */
    private $landingPageContext;

    /**
     * @var FilterManager
     */
    private $filterManager;

    /**
     * @var Url
     */
    private $url;

    /**
     * @var UrlModel
     * @phpstan-ignore-next-line
     */
    private $urlModel;

    public function __construct(
        MagentoHttpRequest $request,
        LandingPageContext $landingPageContext,
        FilterManager $filterManager,
        Url $url,
        UrlModel $urlModel,
        private readonly AlpConfig $alpConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->landingPageContext = $landingPageContext;
        $this->filterManager = $filterManager;
        $this->urlModel = $urlModel;
        $this->url = $url;
    }

    // @phpstan-ignore-next-line
    public function aroundGetResponseUrl(AjaxNavigationResult $ajaxNavigationResult, callable $proceed)
    {
        $type = $this->request->getParam('__tw_ajax_type');

        // @phpstan-ignore-next-line
        if ($type === 'landingpage' && $this->landingPageContext->getLandingPage()) {
            $filters = $this->filterManager->getActiveFiltersExcludingLandingPageFilters();
            return $this->url->getFilterUrl($filters);
        }

        return $proceed();
    }

    /**
     * @param AjaxNavigationResult $subject
     * @param callable $proceed
     * @param string $responseUrl
     * @return string
     */
    public function aroundGetCanonicalUrl(AjaxNavigationResult $subject, callable $proceed, string $responseUrl): string
    {
        $type = $this->request->getParam('__tw_ajax_type');
        $landingPage = $this->landingPageContext->getLandingPage();

        if ($type !== 'landingpage' || !$landingPage) {
            return $proceed($responseUrl);
        }

        $page = (int) $this->request->getParam('p');

        // Admin-configured canonical override: never append ?p= to explicit overrides
        if ($landingPage->getCanonicalUrl()) {
            return $landingPage->getCanonicalUrl();
        }

        if ($this->alpConfig->isCanonicalSelfReferencingEnabled()) {
            // Self-referencing: use full URL with current query params (filters), add ?p= when needed
            return $this->appendPageParam($responseUrl, $page);
        }

        // Plain canonical: bare landing page URL + ?p= when needed
        $baseUrl = $this->storeManager->getStore()->getUrl('', ['_direct' => $landingPage->getUrlPath()]);
        return $this->appendPageParam($baseUrl, $page);
    }

    /**
     * @param string $url
     * @param int $page
     * @return string
     */
    private function appendPageParam(string $url, int $page): string
    {
        if ($page < 2) {
            return $url;
        }

        $urlParts = parse_url($url);
        $query = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $query);
        }

        $query['p'] = $page;

        return (isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '')
            . ($urlParts['host'] ?? '')
            . ($urlParts['path'] ?? '')
            . '?' . http_build_query($query);
    }
}
