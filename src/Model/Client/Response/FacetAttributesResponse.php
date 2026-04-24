<?php

declare(strict_types=1);

namespace Tweakwise\AttributeLandingTweakwise\Model\Client\Response;

use Tweakwise\Magento2Tweakwise\Model\Client\Request;
use Tweakwise\Magento2Tweakwise\Model\Client\Response;
use Tweakwise\Magento2Tweakwise\Model\Client\Type\AttributeTypeFactory;
use Tweakwise\Magento2TweakwiseExport\Model\Helper;

class FacetAttributesResponse extends Response
{
    /**
     * @param Helper $helper
     * @param Request $request
     * @param AttributeTypeFactory $attributeTypeFactory
     * @param array|null $data
     */
    public function __construct(
        Helper $helper,
        Request $request,
        private readonly AttributeTypeFactory $attributeTypeFactory,
        ?array $data = null
    ) {
        parent::__construct($helper, $request, $data);
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->data['attributes'] ?? [];
    }

    /**
     * @param array $attributesData
     * @return $this
     */
    public function setAttributes(array $attributesData): FacetAttributesResponse
    {
        $attributesData = $this->normalizeArray($attributesData, 'attributes');

        foreach ($attributesData as $attributeData) {
            $attribute = $this->attributeTypeFactory->create()->setData($attributeData);

            $attributes = $attribute->getValue('attribute');

            if (isset($attributes[0])) {
                $this->data['attributes'] = $attribute->getValue('attribute');
            } else {
                // Only one result
                $this->data['attributes'][] = $attribute->getValue('attribute');
            }

            break;
        }

        return $this;
    }
}
