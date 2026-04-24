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
            if ($filterAttribute['attribute'] !== AbstractFacetController::OTHER_ATTRIBUTE_VALUE) {
                continue;
            }

            $result[$key]['attribute'] = $filterAttribute['attribute_other'];
            $result[$key]['value'] = $filterAttribute['attribute_value_other'];
        }

        return $result;
    }
}
