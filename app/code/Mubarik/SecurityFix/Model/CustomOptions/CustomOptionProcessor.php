<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */

namespace Mubarik\SecurityFix\Model\CustomOptions;

use Magento\Catalog\Api\Data\CustomOptionInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Mubarik\SecurityFix\Model\Product\Option\Type\File\ImageContentProcessor;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item\CartItemProcessorInterface;
use Magento\Quote\Api\Data\ProductOptionExtensionFactory;
use Magento\Quote\Model\Quote\ProductOptionFactory;
use Magento\Catalog\Model\CustomOptions\CustomOption;
use Magento\Catalog\Model\CustomOptions\CustomOptionFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CustomOptionProcessor implements CartItemProcessorInterface
{
    /**
     * @var DataObject\Factory
     */
    protected $objectFactory;

    /**
     * @var ProductOptionFactory
     */
    protected $productOptionFactory;

    /**
     * @var ProductOptionExtensionFactory
     */
    protected $extensionFactory;

    /**
     * @var CustomOptionFactory
     */
    protected $customOptionFactory;

    /**
     * @var \Magento\Catalog\Model\Product\Option\UrlBuilder
     */
    private $urlBuilder;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ImageContentProcessor
     */
    private $imageContentProcessor;

    /**
     * @param DataObject\Factory $objectFactory
     * @param ProductOptionFactory $productOptionFactory
     * @param ProductOptionExtensionFactory $extensionFactory
     * @param CustomOptionFactory $customOptionFactory
     * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
     * @param ProductRepositoryInterface|null $productRepository
     * @param ImageContentProcessor|null $imageContentProcessor
     */
    public function __construct(
        DataObject\Factory $objectFactory,
        ProductOptionFactory $productOptionFactory,
        ProductOptionExtensionFactory $extensionFactory,
        CustomOptionFactory $customOptionFactory,
        ?\Magento\Framework\Serialize\Serializer\Json $serializer = null,
        ?ProductRepositoryInterface $productRepository = null,
        ?ImageContentProcessor $imageContentProcessor = null
    ) {
        $this->objectFactory = $objectFactory;
        $this->productOptionFactory = $productOptionFactory;
        $this->extensionFactory = $extensionFactory;
        $this->customOptionFactory = $customOptionFactory;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
        $this->productRepository = $productRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(ProductRepositoryInterface::class);
        $this->imageContentProcessor = $imageContentProcessor
            ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(ImageContentProcessor::class);
    }

    /**
     * @inheritDoc
     */
    public function convertToBuyRequest(CartItemInterface $cartItem)
    {
        $productOption = $cartItem->getProductOption();

        if ($productOption !== null) {
            $extensionAttributes = $productOption->getExtensionAttributes();

            if ($extensionAttributes !== null) {
                $customOptions = $extensionAttributes->getCustomOptions();

                if (!empty($customOptions)) {
                    $requestData = [];
                    $productOptions = $this->getProductCustomOptions($cartItem);

                    foreach ($customOptions as $option) {
                        $optionId = $option->getOptionId();

                        $requestData['options'][$optionId] = $this->getCustomOptionValue(
                            $option,
                            isset($productOptions[$optionId]) ? $productOptions[$optionId] : null
                        );
                    }

                    return $this->objectFactory->create($requestData);
                }
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function processOptions(CartItemInterface $cartItem)
    {
        $options = $this->getOptions($cartItem);
        if (!empty($options) && is_array($options)) {
            $this->updateOptionsValues($options);
            $productOption = $cartItem->getProductOption()
                ? $cartItem->getProductOption()
                : $this->productOptionFactory->create();

            $extensibleAttribute = $productOption->getExtensionAttributes()
                ? $productOption->getExtensionAttributes()
                : $this->extensionFactory->create();

            $extensibleAttribute->setCustomOptions($options);
            $productOption->setExtensionAttributes($extensibleAttribute);
            $cartItem->setProductOption($productOption);
        }
        return $cartItem;
    }

    /**
     * Receive custom option from buy request
     *
     * @param CartItemInterface $cartItem
     * @return array
     */
    protected function getOptions(CartItemInterface $cartItem)
    {
        $buyRequest = !empty($cartItem->getOptionByCode('info_buyRequest'))
            ? $this->serializer->unserialize($cartItem->getOptionByCode('info_buyRequest')->getValue())
            : null;
        return is_array($buyRequest) && isset($buyRequest['options'])
            ? $buyRequest['options']
            : [];
    }

    /**
     * Update options values
     *
     * @param array $options
     * @return null
     */
    protected function updateOptionsValues(array &$options)
    {
        foreach ($options as $optionId => &$optionValue) {
            /** @var CustomOption $option */
            $option = $this->customOptionFactory->create();
            $option->setOptionId($optionId);
            if (is_array($optionValue)) {
                $optionValue = $this->processFileOptionValue($optionValue);
                $optionValue = $this->processDateOptionValue($optionValue);
                $optionValue = implode(',', $optionValue);
            }
            $option->setOptionValue($optionValue);
            $optionValue = $option;
        }
        return null;
    }

    /**
     * Returns option value with file built URL
     *
     * @param array $optionValue
     * @return array
     */
    private function processFileOptionValue(array $optionValue)
    {
        if (array_key_exists('url', $optionValue) &&
            array_key_exists('route', $optionValue['url']) &&
            array_key_exists('params', $optionValue['url'])
        ) {
            $optionValue['url'] = $this->getUrlBuilder()->getUrl(
                $optionValue['url']['route'],
                $optionValue['url']['params']
            );
        }
        return $optionValue;
    }

    /**
     * Returns date option value only with 'date_internal data
     *
     * @param array $optionValue
     * @return array
     */
    private function processDateOptionValue(array $optionValue)
    {
        if (array_key_exists('date_internal', $optionValue)
        ) {
            $closure = function ($key) {
                return $key === 'date_internal';
            };
            $optionValue = array_filter($optionValue, $closure, ARRAY_FILTER_USE_KEY);
        }
        return $optionValue;
    }

    /**
     * Get URL Builder
     *
     * @return \Magento\Catalog\Model\Product\Option\UrlBuilder
     *
     * @deprecated 101.0.0
     * @see MAGETWO-71174
     */
    private function getUrlBuilder()
    {
        if ($this->urlBuilder === null) {
            $this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Catalog\Model\Product\Option\UrlBuilder::class);
        }
        return $this->urlBuilder;
    }

    /**
     * Get Product Options
     *
     * @param CartItemInterface $cartItem
     * @return ProductCustomOptionInterface[]
     */
    private function getProductCustomOptions(CartItemInterface $cartItem): array
    {
        try {
            $product = $this->productRepository->get($cartItem->getSku());
        } catch (NoSuchEntityException $e) {
            $product = null;
        }

        $options = [];

        if ($product && $product->getHasOptions()) {
            $productOptions = $product->getOptions();

            if (is_array($productOptions)) {
                foreach ($productOptions as $option) {
                    $options[$option->getOptionId()] = $option;
                }
            }
        }

        return $options;
    }

    /**
     * Get custom option value depending on the type of custom option
     *
     * @param CustomOptionInterface $customOption
     * @param ProductCustomOptionInterface|null $productCustomOption
     * @return string|array|null
     */
    private function getCustomOptionValue(
    CustomOptionInterface $customOption,
    ?ProductCustomOptionInterface $productCustomOption = null
    ) {
        $extensionAttributes = $customOption->getExtensionAttributes();

        if ($extensionAttributes && $extensionAttributes->getFileInfo()) {
            if (
                $productCustomOption &&
                $productCustomOption->getType() === ProductCustomOptionInterface::OPTION_TYPE_FILE
            ) {
                // FIX START: Verify that $_FILES is not empty before calling the processor
                if (empty($_FILES)) {
                    // Return the existing value from the quote item if we are just viewing the cart
                    return $customOption->getOptionValue();
                }
                // FIX END
                return $this->imageContentProcessor->process(
                    $extensionAttributes->getFileInfo(),
                    $productCustomOption
                );
            } elseif ($customOption instanceof CustomOption) {
                return $customOption->getData(CustomOptionInterface::OPTION_VALUE);
            }
        }

        return $customOption->getOptionValue();
    }
}
