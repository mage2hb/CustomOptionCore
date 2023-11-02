<?php

namespace Mage2hb\CustomOptionCore\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Config\Source\Product\Options\Price as ProductOptionsPrice;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Model\ProductOptions\ConfigInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\CustomOptions;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Container;
use Magento\Ui\Component\DynamicRows;

class Mage2hbCustomOptions extends CustomOptions
{
    const FIELD_IS_USE_DEFAULT_BSS_DESCRIPTION_OPTION_TYPE = 'is_use_default_mage2hb_description_option_type';
    const FIELD_IS_USE_DEFAULT_BSS_DESCRIPTION_OPTION = 'is_use_default_mage2hb_description_option';
    const FIELD_BSS_DESCRIPTION_OPTION_TYPE = 'mage2hb_description_option_type';
    const FIELD_BSS_DESCRIPTION_OPTION = 'mage2hb_description_option';

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * Mage2hbCustomOptions constructor.
     *
     * @param LocatorInterface $locator
     * @param StoreManagerInterface $storeManager
     * @param ConfigInterface $productOptionsConfig
     * @param ProductOptionsPrice $productOptionsPrice
     * @param UrlInterface $urlBuilder
     * @param ArrayManager $arrayManager
     * @param DataObjectFactory $dataObjectFactory
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        LocatorInterface $locator,
        StoreManagerInterface $storeManager,
        ConfigInterface $productOptionsConfig,
        ProductOptionsPrice $productOptionsPrice,
        UrlInterface $urlBuilder,
        ArrayManager $arrayManager,
        DataObjectFactory $dataObjectFactory,
        ManagerInterface $eventManager
    ) {
        parent::__construct(
            $locator,
            $storeManager,
            $productOptionsConfig,
            $productOptionsPrice,
            $urlBuilder,
            $arrayManager
        );
        $this->dataObjectFactory = $dataObjectFactory;
        $this->eventManager = $eventManager;
    }

    /**
     * Modify Data before load data to Admin, add check checkbox use default value field description.
     *
     * @inheritdoc
     * @since 101.0.0
     */
    public function modifyData(array $data)
    {
        $options = [];
        $productOptions = $this->locator->getProduct()->getOptions() ?: [];

        /** @var \Magento\Catalog\Model\Product\Option $option */
        foreach ($productOptions as $index => $option) {
            $optionData = $option->getData();
            $optionData[static::FIELD_IS_USE_DEFAULT] = !$option->getData(static::FIELD_STORE_TITLE_NAME);

            // Add check use default value two field description
            $optionData[static::FIELD_IS_USE_DEFAULT_BSS_DESCRIPTION_OPTION_TYPE] = !$option->getData('store_' . static::FIELD_BSS_DESCRIPTION_OPTION_TYPE . '_option_id');
            $optionData[static::FIELD_IS_USE_DEFAULT_BSS_DESCRIPTION_OPTION] = !$option->getData('store_' . static::FIELD_BSS_DESCRIPTION_OPTION . '_option_id');
            // End check use default value two field description

            $options[$index] = $this->formatPriceByPath(static::FIELD_PRICE_NAME, $optionData);
            $values = $option->getValues() ?: [];
            foreach ($values as $value) {
                $value->setData(static::FIELD_IS_USE_DEFAULT, !$value->getData(static::FIELD_STORE_TITLE_NAME));
            }
            /** @var \Magento\Catalog\Model\Product\Option $value */
            foreach ($values as $value) {
                $options[$index][static::GRID_TYPE_SELECT_NAME][] = $this->formatPriceByPath(
                    static::FIELD_PRICE_NAME,
                    $value->getData()
                );
            }
        }

        return array_replace_recursive(
            $data,
            [
                $this->locator->getProduct()->getId() => [
                    static::DATA_SOURCE_DEFAULT => [
                        static::FIELD_ENABLE => 1,
                        static::GRID_OPTIONS_NAME => $options
                    ]
                ]
            ]
        );
    }

    /**
     * @param int $sortOrder
     * @return array
     */
    protected function getHeaderContainerConfig($sortOrder)
    {
        $result = parent::getHeaderContainerConfig($sortOrder);
        $childObject = $this->dataObjectFactory->create()->addData($result['children']);
        $this->eventManager->dispatch(
            'mage2hb_custom_options_get_header_container',
            ['child' => $childObject, 'product' => $this->locator->getProduct()]
        );
        $childData = $childObject->getData();
        if (isset($childData['tenplates_excluded'])) {
            $excludeTemplate['tenplates_excluded'] = $childData['tenplates_excluded'];
            $result['children'] = $excludeTemplate + $result['children'];
        }
        return $result;
    }

    /**
     * GetCommonContainerConfig
     *
     * @param int $sortOrder
     * @return array
     */
    public function getCommonContainerConfig($sortOrder)
    {
        $commonContainer = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => Container::NAME,
                        'formElement' => Container::NAME,
                        'component' => 'Magento_Ui/js/form/components/group',
                        'breakLine' => false,
                        'showLabel' => false,
                        'additionalClasses' => 'admin__field-group-columns admin__control-group-equal',
                        'sortOrder' => $sortOrder,
                    ],
                ],
            ],
            'children' => $this->getCoapCommonChildren()
        ];

        if ($this->locator->getProduct()->getStoreId()) {
            $useDefaultConfig = [
                'service' => [
                    'template' => 'Magento_Catalog/form/element/helper/custom-option-service',
                ]
            ];
            $titlePath = $this->arrayManager->findPath(static::FIELD_TITLE_NAME, $commonContainer, null)
                . static::META_CONFIG_PATH;
            $commonContainer = $this->arrayManager->merge($titlePath, $commonContainer, $useDefaultConfig);
        }

        return $commonContainer;
    }

    /**
     * GetCoapCommonChildren
     *
     * @return array
     */
    public function getCoapCommonChildren()
    {
        $customTitle = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Option Title'),
                        'component' => 'Magento_Catalog/component/static-type-input',
                        'valueUpdate' => 'input',
                        'imports' => [
                            'optionId' => '${ $.provider }:${ $.parentScope }.option_id',
                            'isUseDefault' => '${ $.provider }:${ $.parentScope }.is_use_default',
                            '__disableTmpl' => ['optionId' => false, 'isUseDefault' => false],
                        ]
                    ],
                ],
            ],
        ];
        $childs = [
            10 => ['index' => static::FIELD_OPTION_ID, 'field' => $this->getOptionIdFieldConfig(10)],
            20 => ['index' => static::FIELD_TITLE_NAME, 'field' => $this->getTitleFieldConfig(20, $customTitle)],
            30 => ['index' => static::FIELD_TYPE_NAME, 'field' => $this->getTypeFieldConfig(30)],
            40 => ['index' => static::FIELD_IS_REQUIRE_NAME, 'field' => $this->getIsRequireFieldConfig(40)]
        ];

        $childObject = $this->dataObjectFactory->create()->addData($childs);

        $this->eventManager->dispatch(
            'mage2hb_custom_options_common_container_add_child_before',
            ['child' => $childObject]
        );
        $sortedChild = $childObject->getData();
        ksort($sortedChild);
        $result = [];
        foreach ($sortedChild as $child) {
            $result[$child['index']] = $child['field'];
        }
        return $result;
    }

    /**
     * GetSelectTypeGridConfig
     *
     * @param int $sortOrder
     * @return array
     */
    public function getSelectTypeGridConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'addButtonLabel' => __('Add Value'),
                        'componentType' => DynamicRows::NAME,
                        'component' => 'Magento_Ui/js/dynamic-rows/dynamic-rows',
                        'additionalClasses' => 'admin__field-wide',
                        'deleteProperty' => static::FIELD_IS_DELETE,
                        'deleteValue' => '1',
                        'renderDefaultRecord' => false,
                        'sortOrder' => $sortOrder,
                    ],
                ],
            ],
            'children' => [
                'record' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'componentType' => Container::NAME,
                                'component' => 'Magento_Ui/js/dynamic-rows/record',
                                'positionProvider' => static::FIELD_SORT_ORDER_NAME,
                                'isTemplate' => true,
                                'is_collection' => true,
                            ],
                        ],
                    ],
                    'children' => $this->getSelectTypeGridChildConfig()
                ]
            ]
        ];
    }

    /**
     * GetSelectTypeGridChildConfig
     *
     * @return array
     */
    public function getSelectTypeGridChildConfig()
    {
        $options = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'imports' => [
                            'optionId' => '${ $.provider }:${ $.parentScope }.option_id',
                            'optionTypeId' => '${ $.provider }:${ $.parentScope }.option_type_id',
                            'isUseDefault' => '${ $.provider }:${ $.parentScope }.is_use_default',
                            '__disableTmpl' => ['optionId' => false, 'optionTypeId' => false, 'isUseDefault' => false],
                        ],
                        'service' => [
                            'template' => 'Magento_Catalog/form/element/helper/custom-option-type-service',
                        ],
                    ],
                ],
            ],
        ];
        $childs = [
            50 => ['index' => static::FIELD_TITLE_NAME, 'field' => $this->getTitleFieldConfig(
                50,
                $this->locator->getProduct()->getStoreId() ? $options : []
            )],
            100 => ['index' => static::FIELD_PRICE_NAME, 'field' => $this->getPriceFieldConfig(100)],
            150 => [
                'index' => static::FIELD_PRICE_TYPE_NAME,
                'field' => $this->getPriceTypeFieldConfig(150, ['fit' => true])
            ],
            200 => ['index' => static::FIELD_SKU_NAME, 'field' => $this->getSkuFieldConfig(200)],
            250 => ['index' => static::FIELD_SORT_ORDER_NAME, 'field' => $this->getPositionFieldConfig(250)],
            300 => ['index' => static::FIELD_IS_DELETE, 'field' => $this->getIsDeleteFieldConfig(300)]
        ];

        $childObject = $this->dataObjectFactory->create()->addData($childs);

        $this->eventManager->dispatch(
            'mage2hb_custom_options_select_type_add_child_before',
            ['child' => $childObject]
        );
        $this->eventManager->dispatch(
            'mage2hb_abs_custom_options_select_type_add_child_before',
            ['child' => $childObject]
        );
        $this->eventManager->dispatch(
            'mage2hb_custom_options_template_select_type_add_child_before',
            ['child' => $childObject]
        );
        $sortedChild = $childObject->getData();
        ksort($sortedChild);
        $result = [];
        foreach ($sortedChild as $child) {
            $result[$child['index']] = $child['field'];
        }
        return $result;
    }

    /**
     * Get config for container with fields for all types except "select"
     *
     * @param int $sortOrder
     * @return array
     * @since 101.0.0
     */
    protected function getStaticTypeContainerConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => Container::NAME,
                        'formElement' => Container::NAME,
                        'component' => 'Magento_Ui/js/form/components/group',
                        'breakLine' => false,
                        'showLabel' => false,
                        'additionalClasses' => 'admin__field-group-columns admin__control-group-equal',
                        'sortOrder' => $sortOrder,
                        'fieldTemplate' => 'Magento_Catalog/form/field',
                        'visible' => false,
                    ],
                ],
            ],
            'children' => $this->addTierPriceButton()
        ];
    }

    /**
     * @return array
     */
    public function addTierPriceButton()
    {
        $childs = [
            10 => ['index' => static::FIELD_PRICE_NAME, 'field' => $this->getPriceFieldConfig(10)],
            20 => ['index' => static::FIELD_PRICE_TYPE_NAME, 'field' => $this->getPriceTypeFieldConfig(20)],
            30 => ['index' => static::FIELD_SKU_NAME, 'field' => $this->getSkuFieldConfig(30)],
            40 => ['index' => static::FIELD_MAX_CHARACTERS_NAME, 'field' => $this->getMaxCharactersFieldConfig(40)],
            50 => ['index' => static::FIELD_FILE_EXTENSION_NAME, 'field' => $this->getFileExtensionFieldConfig(50)],
            60 => ['index' => static::FIELD_IMAGE_SIZE_X_NAME, 'field' => $this->getImageSizeXFieldConfig(60)],
            70 => ['index' => static::FIELD_IMAGE_SIZE_Y_NAME, 'field' => $this->getImageSizeYFieldConfig(70)]
        ];

        $childObject = $this->dataObjectFactory->create()->addData($childs);

        $this->eventManager->dispatch(
            'mage2hb_abs_custom_options_select_type_add_child_before',
            ['child' => $childObject]
        );
        $sortedChild = $childObject->getData();
        ksort($sortedChild);
        $result = [];
        foreach ($sortedChild as $child) {
            $result[$child['index']] = $child['field'];
        }
        return $result;
    }
}
