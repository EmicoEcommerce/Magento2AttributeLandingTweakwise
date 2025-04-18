<?php

/**
 * @author : Edwin Jacobs, email: ejacobs@emico.nl.
 * @copyright : Copyright Emico B.V. 2020.
 */

namespace Tweakwise\AttributeLandingTweakwise\Model\AjaxResultInitializer;

use Emico\AttributeLanding\Model\FilterApplier\FilterApplierInterface;
use Emico\AttributeLanding\Model\LandingPageContext;
use Tweakwise\Magento2Tweakwise\Model\AjaxNavigationResult;
use Tweakwise\Magento2Tweakwise\Model\AjaxResultInitializer\InitializerInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Registry;
use Emico\AttributeLanding\Api\LandingPageRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class LandingPageInitializer implements InitializerInterface
{
    public const LAYOUT_HANDLE_LANDINGPAGE = 'tweakwise_ajax_landingpage';

    /**
     * @var Resolver
     */
    protected $layerResolver;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var LandingPageRepositoryInterface
     */
    protected $landingPageRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var FilterApplierInterface
     */
    protected $filterApplier;

    /**
     * @var LandingPageContext
     */
    protected $landingPageContext;

    /**
     * AjaxResultCategoryInitializer constructor.
     * @param Resolver $layerResolver
     * @param Registry $registry
     * @param LandingPageRepositoryInterface $landingPageRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param FilterApplierInterface $filterApplier
     * @param LandingPageContext $landingPageContext
     */
    public function __construct(
        Resolver $layerResolver,
        Registry $registry,
        LandingPageRepositoryInterface $landingPageRepository,
        CategoryRepositoryInterface $categoryRepository,
        FilterApplierInterface $filterApplier,
        LandingPageContext $landingPageContext,
        private readonly StoreManagerInterface $storeManager,
    ) {
        $this->layerResolver = $layerResolver;
        $this->registry = $registry;
        $this->landingPageRepository = $landingPageRepository;
        $this->categoryRepository = $categoryRepository;
        $this->filterApplier = $filterApplier;
        $this->landingPageContext = $landingPageContext;
    }

    /**
     * @inheritDoc
     * @throws NoSuchEntityException
     */
    public function initializeAjaxResult(
        AjaxNavigationResult $ajaxNavigationResult,
        RequestInterface $request
    ) {
        $this->initializeLayer();
        $this->initializeLayout($ajaxNavigationResult);
        $this->initializePage($request);
    }

    /**
     * @param AjaxNavigationResult $ajaxNavigationResult
     */
    protected function initializeLayout(AjaxNavigationResult $ajaxNavigationResult)
    {
        $ajaxNavigationResult->addHandle(self::LAYOUT_HANDLE_LANDINGPAGE);
    }

    /**
     * Create category layer
     */
    protected function initializeLayer()
    {
        $this->layerResolver->create(Resolver::CATALOG_LAYER_CATEGORY);
    }

    /**
     * @param RequestInterface $request
     * @throws NoSuchEntityException
     * @throws NotFoundException
     */
    private function initializePage(RequestInterface $request)
    {
        $pageId = (int)$request->getParam('__tw_object_id');
        if (!$pageId) {
            throw new NotFoundException(__('Page not found'));
        }

        try {
            $storeId = $this->storeManager->getStore()->getId();
            $landingPage = $this->landingPageRepository->getByIdWithStore($pageId, $storeId);
            if (!$landingPage->isActive()) {
                throw new NotFoundException(__('Page not active'));
            }
        } catch (NoSuchEntityException $e) {
            throw new NotFoundException(__('Page not found'));
        } catch (LocalizedException $e) {
            throw new NotFoundException(__('Page not found'));
        }

        // Register the category, its needed while rendering filters and products
        if (!$this->registry->registry('current_category')) {
            $categoryId = $landingPage->getCategoryId();
            $category = $this->categoryRepository->get($categoryId);
            $this->registry->register('current_category', $category);
        }

        $this->landingPageContext->setLandingPage($landingPage);
        $this->filterApplier->applyFilters($landingPage);
    }
}
