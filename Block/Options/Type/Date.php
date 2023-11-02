<?php

namespace Mage2hb\CustomOptionCore\Block\Options\Type;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product\Option\Type\Date as OptionDateModel;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\Element\Template\Context;

class Date extends \Magento\Catalog\Block\Product\View\Options\Type\Date
{
    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * Date constructor.
     * @param Context $context
     * @param PricingHelper $pricingHelper
     * @param CatalogHelper $catalogData
     * @param OptionDateModel $catalogProductOptionTypeDate
     * @param DataObjectFactory $dataObjectFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        PricingHelper $pricingHelper,
        CatalogHelper $catalogData,
        OptionDateModel $catalogProductOptionTypeDate,
        DataObjectFactory $dataObjectFactory,
        array $data = []
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        parent::__construct($context, $pricingHelper, $catalogData, $catalogProductOptionTypeDate, $data);
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
            'mage2hb_custom_options_render_file_' . $place,
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
}
