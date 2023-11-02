<?php

namespace Mage2hb\CustomOptionCore\Block\Options\Type;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\Element\Template\Context as TemplateContext;

class Text extends \Magento\Catalog\Block\Product\View\Options\Type\Text
{
    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;

    /**
     * Text constructor.
     * @param TemplateContext $context
     * @param PricingHelper $pricingHelper
     * @param CatalogHelper $catalogData
     * @param DataObjectFactory $dataObjectFactory
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param array $data
     */
    public function __construct(
        TemplateContext $context,
        PricingHelper $pricingHelper,
        CatalogHelper $catalogData,
        DataObjectFactory $dataObjectFactory,
        \Magento\Framework\Serialize\Serializer\Json $json,
        array $data = []
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->json = $json;
        parent::__construct($context, $pricingHelper, $catalogData, $data);
    }

    /**
     * GetMage2hbCustomOptionBlock
     *
     * @param string $place
     * @return string
     * @throws LocalizedException
     */
    public function getMage2hbCustomOptionBlock($place)
    {
        $childObject = $this->dataObjectFactory->create();

        $this->_eventManager->dispatch(
            'mage2hb_custom_options_render_text_' . $place,
            ['child' => $childObject]
        );
        $blocks = $childObject->getData() ?: [];
        $output = '';

        foreach ($blocks as $childBlock) {
            $block = $this->getLayout()->createBlock($childBlock);
            $block->setProduct($this->getProduct())->setOption($this->getOption());
            $output .= $block->toHtml();
        }
        return $output;
    }

    /**
     * @return \Magento\Framework\Serialize\Serializer\Json
     */
    public function jsonClass()
    {
        return $this->json;
    }
}
