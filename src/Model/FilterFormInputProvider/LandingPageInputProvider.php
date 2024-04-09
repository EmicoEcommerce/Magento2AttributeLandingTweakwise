<?php

/**
 * @author : Edwin Jacobs, email: ejacobs@emico.nl.
 * @copyright : Copyright Emico B.V. 2020.
 */

namespace Tweakwise\AttributeLandingTweakwise\Model\FilterFormInputProvider;

use Emico\AttributeLanding\Api\Data\LandingPageInterface;
use Emico\AttributeLanding\Model\LandingPageContext;
use Tweakwise\Magento2Tweakwise\Model\Config;
use Tweakwise\Magento2Tweakwise\Model\FilterFormInputProvider\FilterFormInputProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NotFoundException;
use Tweakwise\Magento2Tweakwise\Model\FilterFormInputProvider\HashInputProvider;
use Tweakwise\Magento2Tweakwise\Model\FilterFormInputProvider\ToolbarInputProvider;
use Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url;

class LandingPageInputProvider implements FilterFormInputProviderInterface
{
    public const TYPE = 'landingpage';

    /**
     * @var Config $twConfig
     */
    protected $twConfig;

    /**
     * @var LandingPageContext
     */
    protected $landingPageContext;

    /**
     * @var RequestInterface $request
     */
    protected RequestInterface $request;

    /**
     * @var ToolbarInputProvider $toolbarInputProvider
     */
    protected ToolbarInputProvider $toolbarInputProvider;

    /**
     * @var HashInputProvider $hashInputProvider
     */
    protected HashInputProvider $hashInputProvider;

    /**
     * @var Url
     */
    protected $layerUrl;

    /**
     * LandingPageProvider constructor.
     * @param Config $twConfig
     * @param LandingPageContext $landingPageContext
     */
    public function __construct(
        Config             $twConfig,
        LandingPageContext $landingPageContext,
        RequestInterface   $request,
        ToolbarInputProvider $toolbarInputProvider,
        HashInputProvider $hashInputProvider,
        Url $layerUrl
    ) {
        $this->twConfig = $twConfig;
        $this->landingPageContext = $landingPageContext;
        $this->request = $request;
        $this->toolbarInputProvider = $toolbarInputProvider;
        $this->hashInputProvider = $hashInputProvider;
        $this->layerUrl = $layerUrl;
    }

    /**
     * @inheritDoc
     * @throws NotFoundException
     */
    public function getFilterFormInput(): array
    {
        if (!$this->twConfig->isAjaxFilters()) {
            return [];
        }

        $page = $this->getPage();
        if (!$page) {
            throw new NotFoundException(__('landingpage not found'));
        }

        $url = $this->getOriginalUrl();
        $url = strtok($url, '?');

        $input = [
            '__tw_ajax_type' => self::TYPE,
            '__tw_object_id' => (int)$page->getPageId(),
            '__tw_original_url' => $url,
        ];

        $input['__tw_hash'] = $this->hashInputProvider->getHash($input);

        return array_merge(
            $input,
            $this->toolbarInputProvider->getFilterFormInput()
        );
    }

    /**
     * @return LandingPageInterface
     */
    protected function getPage(): LandingPageInterface
    {
        return $this->landingPageContext->getLandingPage();
    }

    /**
     * @return string
     */
    public function getOriginalUrl(): string
    {
        return $this->layerUrl->getUrlStrategy()->getOriginalUrl($this->request);
    }
}
