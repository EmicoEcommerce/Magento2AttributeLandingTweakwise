<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model;

use Emico\AttributeLanding\Api\Data\FilterInterface;
use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Api\LandingPageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;

class BestLandingPageFinder
{
    /**
     * @var LandingPageInterface[]|null
     */
    private ?array $candidatePages = null;

    /**
     * @param LandingPageRepositoryInterface $landingPageRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Find the landing page whose filters form the largest subset of the given desired filters.
     * Only considers pages with a default (empty) filter template.
     * Returns null when no partial match exists (exact matches are handled elsewhere).
     *
     * @param FilterInterface[] $desiredFilters
     * @param int|null $categoryId
     * @return array{page: LandingPageInterface, extraFilters: FilterInterface[]}|null
     */
    public function findBestMatch(array $desiredFilters, ?int $categoryId): ?array
    {
        $candidatePages = $this->loadCandidatePages();
        $storeId = (int) $this->storeManager->getStore()->getId();
        $normalizedDesired = $this->normalizeFilters($desiredFilters);
        $desiredCount = count($normalizedDesired);

        $bestPage = null;
        $bestFilterCount = 0;

        foreach ($candidatePages as $page) {
            if (!$this->isPageEligible($page, $storeId, $categoryId)) {
                continue;
            }

            $pageFilters = $page->getFilters();
            $pageFilterCount = count($pageFilters);

            // Must have at least one filter and strictly fewer filters than the desired set
            // (exact matches are already handled by the hash-based UrlFinder)
            if ($pageFilterCount === 0 || $pageFilterCount >= $desiredCount) {
                continue;
            }

            // Only consider if this page has more filters than the current best
            if ($pageFilterCount <= $bestFilterCount) {
                continue;
            }

            // All page filters must be present in the desired filter set
            $normalizedPageFilters = $this->normalizeFilters($pageFilters);
            if (!$this->isSubset($normalizedPageFilters, $normalizedDesired)) {
                continue;
            }

            $bestPage = $page;
            $bestFilterCount = $pageFilterCount;
        }

        if ($bestPage === null) {
            return null;
        }

        $extraFilters = $this->computeExtraFilters($desiredFilters, $bestPage->getFilters());

        return ['page' => $bestPage, 'extraFilters' => $extraFilters];
    }

    /**
     * Check whether a landing page is eligible as a best-match candidate.
     *
     * @param LandingPageInterface $page
     * @param int $storeId
     * @param int|null $categoryId
     * @return bool
     */
    private function isPageEligible(LandingPageInterface $page, int $storeId, ?int $categoryId): bool
    {
        // Store must match (0 = all stores)
        // @phpstan-ignore-next-line
        $pageStoreId = (int) $page->getData('store_id');
        if ($pageStoreId !== 0 && $pageStoreId !== $storeId) {
            return false;
        }

        // Category must match (null on the page means "any category")
        $pageCategoryId = $page->getCategoryId();
        if ($pageCategoryId !== null && (int) $pageCategoryId !== $categoryId) {
            return false;
        }

        // Only pages with the default (empty) filter template
        $filterTemplate = $page->getTweakwiseFilterTemplate();
        if (!empty($filterTemplate)) {
            return false;
        }

        return true;
    }

    /**
     * Normalize an array of filters to lowercase "facet|value" strings for set comparison.
     *
     * @param FilterInterface[] $filters
     * @return string[]
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $filter) {
            $normalized[] = strtolower($filter->getFacet() . '|' . $filter->getValue());
        }

        return $normalized;
    }

    /**
     * Check whether every element of $subset exists in $superset.
     *
     * @param string[] $subset
     * @param string[] $superset
     * @return bool
     */
    private function isSubset(array $subset, array $superset): bool
    {
        foreach ($subset as $item) {
            if (!in_array($item, $superset, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return filters that are present in $allFilters but not in $pageFilters.
     *
     * @param FilterInterface[] $allFilters
     * @param FilterInterface[] $pageFilters
     * @return FilterInterface[]
     */
    private function computeExtraFilters(array $allFilters, array $pageFilters): array
    {
        $normalizedPage = $this->normalizeFilters($pageFilters);

        $extra = [];
        foreach ($allFilters as $filter) {
            $key = strtolower($filter->getFacet() . '|' . $filter->getValue());
            if (!in_array($key, $normalizedPage, true)) {
                $extra[] = $filter;
            }
        }

        return $extra;
    }

    /**
     * Load all active, filter-link-allowed landing pages from the repository (cached per request).
     *
     * @return LandingPageInterface[]
     */
    private function loadCandidatePages(): array
    {
        if ($this->candidatePages !== null) {
            return $this->candidatePages;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('emico_attributelanding_page_store.' . LandingPageInterface::ACTIVE, 1)
            ->addFilter('emico_attributelanding_page_store.' . LandingPageInterface::FILTER_LINK_ALLOWED, 1)
            ->create();

        // @phpstan-ignore-next-line
        $this->candidatePages = $this->landingPageRepository
            ->getList($searchCriteria)
            ->getItems();

        return $this->candidatePages;
    }
}

