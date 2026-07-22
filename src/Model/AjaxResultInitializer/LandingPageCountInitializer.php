<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Tweakwise\AttributeLandingTweakwise\Model\AjaxResultInitializer;

use Emico\AttributeLanding\Api\LandingPageRepositoryInterface;
use Emico\AttributeLanding\Api\Data\FilterInterface;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use InvalidArgumentException;
use Magento\Framework\App\Request\Http as MagentoHttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\NavigationContext;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\ProductNavigationRequest;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\PropertiesType;

class LandingPageCountInitializer
{
    private const IGNORED_PARAMS = [
        '__tw_ajax_type',
        '__tw_object_id',
        '__tw_original_url',
        '__tw_hash',
        'p',
        'product_list_order',
        'product_list_limit',
        'product_list_mode',
        'q',
        '_',
        'categorie',
    ];

    /**
     * @param LandingPageRepositoryInterface $landingPageRepository
     * @param NavigationContext $navigationContext
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly NavigationContext $navigationContext,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Initialize navigation request for landing-page product count.
     */
    public function initializeForCount(RequestInterface $request): int
    {
        $landingPage = $this->getActiveLandingPage($request);

        $navigationRequest = $this->navigationContext->getRequest();
        $this->applyCategoryFilter($navigationRequest, $landingPage);
        $this->applyLandingPageFilters($navigationRequest, $landingPage);
        $this->applyTemplateIds($navigationRequest, $landingPage);

        $this->applyFilterParams($request, $this->navigationContext);

        /** @var PropertiesType $properties */
        $properties = $this->navigationContext->getResponse()->getValue('properties');

        return $properties->getNumberOfItems();
    }

    private function getActiveLandingPage(RequestInterface $request): LandingPageInterface
    {
        $pageId = (int) $request->getParam('__tw_object_id');
        if ($pageId === 0) {
            throw new InvalidArgumentException('No landing page provided for product count request.');
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $landingPage = $this->landingPageRepository->getByIdWithStore($pageId, $storeId);
        } catch (LocalizedException) {
            throw new InvalidArgumentException('Landing page not found.');
        }

        if (!$landingPage->isActive()) {
            throw new InvalidArgumentException('Landing page is not active.');
        }

        return $landingPage;
    }

    private function applyCategoryFilter(ProductNavigationRequest $navigationRequest, LandingPageInterface $landingPage): void
    {
        $categoryId = (int) $landingPage->getCategoryId();
        if ($categoryId === 0) {
            throw new InvalidArgumentException('Landing page has no valid category for product count request.');
        }

        $navigationRequest->addCategoryFilter($categoryId);
    }

    private function applyLandingPageFilters(ProductNavigationRequest $navigationRequest, LandingPageInterface $landingPage): void
    {
        foreach ($landingPage->getFilters() as $filter) {
            foreach ($this->getFilterValues($filter) as $value) {
                if ($value === '') {
                    continue;
                }

                $navigationRequest->addAttributeFilter($filter->getFacet(), $value);
            }
        }
    }

    private function getFilterValues(FilterInterface $filter): array
    {
        /** @phpstan-ignore-next-line method.notFound */
        $values = $filter->getValues();
        if ($values !== []) {
            return $values;
        }

        return [$filter->getValue()];
    }

    private function applyTemplateIds(ProductNavigationRequest $navigationRequest, LandingPageInterface $landingPage): void
    {
        $filterTemplateId = $landingPage->getTweakwiseFilterTemplate();
        if ($filterTemplateId) {
            $navigationRequest->setTemplateId($filterTemplateId);
        }

        $sortTemplateId = $landingPage->getTweakwiseSortTemplate();
        if ($sortTemplateId) {
            $navigationRequest->setSortTemplateId($sortTemplateId);
        }

        $builderTemplateId = $landingPage->getTweakwiseBuilderTemplate();
        if (!$builderTemplateId) {
            return;
        }

        $navigationRequest->setBuilderTemplateId((int) $builderTemplateId);
    }

    private function applyFilterParams(RequestInterface $request, NavigationContext $navigationContext): void
    {
        if (!$request instanceof MagentoHttpRequest) {
            return;
        }

        $navigationRequest = $navigationContext->getRequest();

        foreach ($request->getQuery() as $attribute => $value) {
            if (in_array(strtolower((string) $attribute), self::IGNORED_PARAMS, true)) {
                continue;
            }

            $values = is_array($value) ? $value : [$value];
            foreach ($values as $singleValue) {
                if ($singleValue === '' || $singleValue === null) {
                    continue;
                }

                $navigationRequest->addAttributeFilter((string) $attribute, $singleValue);
            }
        }
    }
}
