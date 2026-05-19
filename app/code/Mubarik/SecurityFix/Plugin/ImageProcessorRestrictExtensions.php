<?php

declare(strict_types=1);

namespace Mubarik\SecurityFix\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageProcessor;
use Magento\Framework\Api\Uploader;

/**
 * Lock Magento\Framework\Api\ImageProcessor uploader to image extensions before save (core path).
 */
class ImageProcessorRestrictExtensions
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];
    private $uploader;
    public function __construct(
        Uploader $uploader
    ) {
        $this->uploader = $uploader;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeProcessImageContent(
        ImageProcessor $subject,
        $entityType,
        ImageContentInterface $imageContent
    ): void {
        $this->uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
    }
}
