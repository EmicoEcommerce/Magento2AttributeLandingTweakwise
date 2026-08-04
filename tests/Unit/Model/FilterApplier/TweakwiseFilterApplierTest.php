<?php

declare(strict_types=1);

namespace Tweakwise\Test\Unit\Model\FilterApplier;

use Emico\AttributeLanding\Model\LandingPage;
use Emico\CodeCept\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Tweakwise\AttributeLandingTweakwise\Model\FilterApplier\TweakwiseFilterApplier;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\NavigationContext;
use Tweakwise\Magento2Tweakwise\Model\Client\Request\ProductNavigationRequest;
use Tweakwise\Test\Support\UnitTester;

class TweakwiseFilterApplierTest extends Unit
{
    protected UnitTester $tester;

    private ProductNavigationRequest $request;

    private NavigationContext|MockObject $navigationContext;

    private TweakwiseFilterApplier $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->tester->getObjectManager()->create(ProductNavigationRequest::class);

        $this->navigationContext = $this->createMock(NavigationContext::class);
        $this->navigationContext->method('getRequest')->willReturn($this->request);

        $this->subject = new TweakwiseFilterApplier($this->navigationContext);
    }

    public function testAppliesValuesPickedFromTheList(): void
    {
        $this->subject->applyFilters($this->createLandingPage([
            [
                'attribute' => 'color',
                'attribute_other' => '',
                'value' => ['green', 'blue'],
                'attribute_value_other' => '',
            ],
        ]));

        $this->assertSame(['tn_fk_color' => 'green|blue'], $this->getFilterParameters());
    }

    public function testCombinesValuesFromTheListWithTheFreeTextValue(): void
    {
        $this->subject->applyFilters($this->createLandingPage([
            [
                'attribute' => 'color',
                'attribute_other' => '',
                'value' => ['green', 'tw_other'],
                'attribute_value_other' => 'red',
            ],
        ]));

        $this->assertSame(['tn_fk_color' => 'green|red'], $this->getFilterParameters());
    }

    public function testSplitsACommaSeparatedFreeTextValue(): void
    {
        $this->subject->applyFilters($this->createLandingPage([
            [
                'attribute' => 'color',
                'attribute_other' => '',
                'value' => ['tw_other'],
                'attribute_value_other' => 'red, purple',
            ],
        ]));

        $this->assertSame(['tn_fk_color' => 'red|purple'], $this->getFilterParameters());
    }

    public function testSkipsTheFilterWhenNoFreeTextIsEntered(): void
    {
        $this->subject->applyFilters($this->createLandingPage([
            [
                'attribute' => 'size',
                'attribute_other' => '',
                'value' => ['tw_other'],
                'attribute_value_other' => '',
            ],
        ]));

        $this->assertSame([], $this->getFilterParameters());
    }

    public function testAppliesAnAttributeAndValuesTypedByHand(): void
    {
        $this->subject->applyFilters($this->createLandingPage([
            [
                'attribute' => 'tw_other',
                'attribute_other' => 'color',
                'value' => ['tw_other'],
                'attribute_value_other' => 'green, blue',
            ],
        ]));

        $this->assertSame(['tn_fk_color' => 'green|blue'], $this->getFilterParameters());
    }

    public function testReadsAValueStoredBySingleSelect(): void
    {
        $this->subject->applyFilters($this->createLandingPage([
            [
                'attribute' => 'color',
                'attribute_other' => '',
                'value' => 'green',
                'attribute_value_other' => '',
            ],
        ]));

        $this->assertSame(['tn_fk_color' => 'green'], $this->getFilterParameters());
    }

    /**
     * @param array $filters
     *
     * @return LandingPage
     */
    private function createLandingPage(array $filters): LandingPage
    {
        /** @var LandingPage $page */
        $page = $this->tester->getObjectManager()->create(LandingPage::class);
        $page->setFilterAttributes(serialize($filters));

        return $page;
    }

    /**
     * @return array<string, string>
     */
    private function getFilterParameters(): array
    {
        return array_filter(
            $this->request->getParameters(),
            static fn($parameter) => str_starts_with((string) $parameter, 'tn_fk_'),
            ARRAY_FILTER_USE_KEY
        );
    }
}
