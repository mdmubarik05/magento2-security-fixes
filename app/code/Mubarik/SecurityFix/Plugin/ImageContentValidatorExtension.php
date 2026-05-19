<?php

declare(strict_types=1);

namespace Mubarik\SecurityFix\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContentValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Phrase;

/**
 * After core validation, reject filenames whose extension is not a safe image type (APSB25-94 / PolyShell).
 */
class ImageContentValidatorExtension
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];
    
    private $ioFile;
    public function __construct(
        IoFile $ioFile
    ) {
        $this->ioFile = $ioFile;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsValid(
        ImageContentValidator $subject,
        bool $result,
        ImageContentInterface $imageContent
    ): bool {
        $fileName = $imageContent->getName();
        $pathInfo = $this->ioFile->getPathInfo($fileName);
        $extension = strtolower($pathInfo['extension'] ?? '');

        if ($extension && !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InputException(
                new Phrase('The image file extension "%1" is not allowed.', [$extension])
            );
        }

        return $result;
    }
}
