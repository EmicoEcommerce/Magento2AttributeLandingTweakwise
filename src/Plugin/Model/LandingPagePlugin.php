<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Plugin\Model;

use Emico\AttributeLanding\Model\LandingPage;
use Tweakwise\AttributeLandingTweakwise\Controller\Adminhtml\Ajax\AbstractFacetController;

class LandingPagePlugin
{
    /**
     * Set other attribute and other attribute value as attribute and value
     * @param LandingPage $subject
     * @param array $result
     * @return array
     */
    public function afterGetFrontendFilterAttributes(LandingPage $subject, array $result): array
    {
        foreach ($result as $key => $filterAttribute) {
            if ($filterAttribute['attribute'] === AbstractFacetController::OTHER_ATTRIBUTE_VALUE) {
                $result[$key]['attribute'] = $filterAttribute['attribute_other'];
            }

            $values = (array) ($filterAttribute['value'] ?? []);
            if (!in_array(AbstractFacetController::OTHER_ATTRIBUTE_VALUE, $values, true)) {
                continue;
            }

            $result[$key]['value'] = array_merge(
                array_values(array_diff($values, [AbstractFacetController::OTHER_ATTRIBUTE_VALUE])),
                $this->splitOtherValue($filterAttribute['attribute_value_other'] ?? null),
            );
        }

        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return string[]
     */
    private function splitOtherValue(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn(string $item) => $item !== '',
        ));
    }
}
