<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

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
     * @var Url
     */
    protected $layerUrl;

    /**
     * LandingPageProvider constructor.
     * @param Config $twConfig
     * @param LandingPageContext $landingPageContext
     * @param RequestInterface $request
     * @param ToolbarInputProvider $toolbarInputProvider
     * @param HashInputProvider $hashInputProvider
     * @param Url $layerUrl
     */
    public function __construct(
        Config             $twConfig,
        LandingPageContext $landingPageContext,
        protected RequestInterface   $request,
        protected ToolbarInputProvider $toolbarInputProvider,
        protected HashInputProvider $hashInputProvider,
        Url $layerUrl
    ) {
        $this->twConfig = $twConfig;
        $this->landingPageContext = $landingPageContext;
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
        // @phpstan-ignore-next-line
        if (!$page) {
            throw new NotFoundException(__('landingpage not found'));
        }

        $url = $this->getOriginalUrl();
        $url = strtok($url, '?');

        $input = [
            '__tw_ajax_type' => self::TYPE,
            '__tw_object_id' => (string)$page->getPageId(),
            '__tw_original_url' => (string)$url,
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
        // @phpstan-ignore-next-line
        return $this->layerUrl->getUrlStrategy()->getOriginalUrl($this->request);
    }
}
