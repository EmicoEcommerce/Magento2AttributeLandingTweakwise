<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Tweakwise\AttributeLandingTweakwise\Model\AjaxResultInitializer;

use Emico\AttributeLanding\Api\LandingPageRepositoryInterface;
use InvalidArgumentException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Tweakwise\Magento2Tweakwise\Model\AjaxResultInitializer\AbstractCountInitializer;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\NavigationContext;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\PropertiesType;

class LandingPageCountInitializer extends AbstractCountInitializer
{
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
     * @param RequestInterface $request
     * @return int
     * @throws NoSuchEntityException
     */
    public function initializeForCount(RequestInterface $request): int
    {
        $pageId = (int)$request->getParam('__tw_object_id');
        if ($pageId === 0) {
            throw new InvalidArgumentException('No landing page provided for product count request.');
        }

        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $landingPage = $this->landingPageRepository->getByIdWithStore($pageId, $storeId);
        } catch (LocalizedException) {
            throw new InvalidArgumentException('Landing page not found.');
        }

        if (!$landingPage->isActive()) {
            throw new InvalidArgumentException('Landing page is not active.');
        }

        $navigationRequest = $this->navigationContext->getRequest();
        $categoryId = (int)$landingPage->getCategoryId();
        if ($categoryId === 0) {
            throw new InvalidArgumentException('Landing page has no valid category for product count request.');
        }

        $navigationRequest->addCategoryFilter($categoryId);

        foreach ($landingPage->getFilters() as $filter) {
            $values = [$filter->getValue()];
            if (method_exists($filter, 'getValues')) {
                // @phpstan-ignore-next-line
                $values = $filter->{'getValues'}();
            }

            foreach ($values as $value) {
                if ($value === '') {
                    continue;
                }

                $navigationRequest->addAttributeFilter($filter->getFacet(), $value);
            }
        }

        $filterTemplateId = $landingPage->getTweakwiseFilterTemplate();
        if ($filterTemplateId) {
            $navigationRequest->setTemplateId($filterTemplateId);
        }

        $sortTemplateId = $landingPage->getTweakwiseSortTemplate();
        if ($sortTemplateId) {
            $navigationRequest->setSortTemplateId($sortTemplateId);
        }

        $builderTemplateId = $landingPage->getTweakwiseBuilderTemplate();
        if ($builderTemplateId) {
            $navigationRequest->setBuilderTemplateId((int)$builderTemplateId);
        }

        $this->applyFilterParams($request, $this->navigationContext);

        /** @var PropertiesType $properties */
        $properties = $this->navigationContext->getResponse()->getValue('properties');

        return $properties->getNumberOfItems();
    }
}
