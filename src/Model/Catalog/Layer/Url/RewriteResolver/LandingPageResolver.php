<?php

/**
 * @author : Edwin Jacobs, email: ejacobs@emico.nl.
 * @copyright : Copyright Emico B.V. 2020.
 */

namespace Tweakwise\AttributeLandingTweakwise\Model\Catalog\Layer\Url\RewriteResolver;

use Tweakwise\AttributeLanding\Model\Config;
use Tweakwise\Tweakwise\Model\Catalog\Layer\Url\RewriteResolver\RewriteResolverInterface;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as MagentoHttpRequest;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class LandingPageResolver implements RewriteResolverInterface
{
    /**
     * @var UrlFinderInterface
     */
    protected $urlFinder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Config
     */
    protected $attributeLandingConfig;

    /**
     * CategoryResolver constructor.
     * @param UrlFinderInterface $urlFinder
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $attributeLandingConfig
     */
    public function __construct(
        UrlFinderInterface $urlFinder,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Config $attributeLandingConfig
    ) {
        $this->urlFinder = $urlFinder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->attributeLandingConfig = $attributeLandingConfig;
    }

    /**
     * @inheritDoc
     */
    public function getRewrites(MagentoHttpRequest $request): array
    {
        $path = trim($request->getPathInfo(), '/');
        $pathsToCheck = $this->getPossibleLandingPagePaths($path);

        $rewriteFilterData = [
            UrlRewrite::ENTITY_TYPE => 'landingpage',
            UrlRewrite::REQUEST_PATH => $pathsToCheck
        ];
        try {
            $storeId = $this->storeManager->getStore()->getId();
            $rewriteFilterData[UrlRewrite::STORE_ID] = $storeId;
        } catch (NoSuchEntityException $e) {
            // No implementation
        }

        return $this->urlFinder->findAllByData($rewriteFilterData);
    }

    /**
     * Example: if fullUriPath is /category-path/filter1/value1/filter2/value2
     * then this method should return
     * [
     *      category-path/filter1/value1/filter2/value2,
     *      category-path/filter1/value1/filter2,
     *      category-path/filter1/value1,
     *      category-path/filter1,
     *      category-path
     * ]
     *
     * The reason for this is that it is unclear
     * which part or the url corresponds to a category.
     * Furthermore it should take the configured category url suffix into account
     *
     * @param string $fullUriPath
     * @return array
     */
    protected function getPossibleLandingPagePaths(string $fullUriPath): array
    {
        $pathParts = explode('/', $fullUriPath);
        $lastPathPart = array_shift($pathParts);
        $paths[] = $lastPathPart;
        foreach ($pathParts as $i => $pathPart) {
            $lastPathPart .= '/' . $pathPart;
            $paths[] = $lastPathPart;
        }
        $paths = array_reverse($paths);

        if (!$this->attributeLandingConfig->isAppendCategoryUrlSuffix()) {
            return $paths;
        }

        // Take category url suffix into account.
        $categoryUrlSuffix = $this->scopeConfig->getValue(
            CategoryUrlPathGenerator::XML_PATH_CATEGORY_URL_SUFFIX,
            'store'
        );

        if (!$categoryUrlSuffix) {
            return $paths;
        }

        return array_map(
            static function (string $path) use ($categoryUrlSuffix): string {
                // Check if path ends with category url suffix, if not add it.
                if (substr($path, -strlen($categoryUrlSuffix)) !== $categoryUrlSuffix) {
                    return $path . $categoryUrlSuffix;
                }

                return $path;
            },
            $paths
        );
    }
}
