<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Mubarik\SecurityFix\Model\Product\Option\Type\File;

use Magento\Catalog\Model\Product\Option;
use Magento\Framework\Api\Data\ImageContentInterface;
use Mubarik\SecurityFix\Api\ImageContentUploaderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Size;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Image\Factory;
use Mubarik\SecurityFix\Model\Validator\ValidatorChainFactory;
use Magento\MediaStorage\Model\File\Validator\NotProtectedExtension;
use Magento\Catalog\Model\Product\Option\Type\File\Validator;

class ImageContentProcessor extends Validator
{
    private const QUOTE_PATH = 'custom_options/quote';
    private const ORDER_PATH = 'custom_options/order';

    private $filesystem;
    private $extensionValidator;
    private $uploader;
    private $validatorChainFactory;
    private $imageFactory;
    private $ioFile;
    private $quotePath;
    private $orderPath;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Size $fileSize
     * @param Filesystem $filesystem
     * @param NotProtectedExtension $extensionValidator
     * @param ImageContentUploaderInterface $uploader
     * @param ValidatorChainFactory $validatorChainFactory
     * @param Factory $imageFactory
     * @param IoFile $ioFile
     * @param string $quotePath
     * @param string $orderPath
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Size $fileSize,
        Filesystem $filesystem,
        NotProtectedExtension $extensionValidator,
        ImageContentUploaderInterface $uploader,
        ValidatorChainFactory $validatorChainFactory,
        Factory $imageFactory,
        IoFile $ioFile,
        string $quotePath = self::QUOTE_PATH,
        string $orderPath = self::ORDER_PATH
    ) {
        parent::__construct($scopeConfig, $filesystem, $fileSize);

        $this->filesystem = $filesystem;
        $this->extensionValidator = $extensionValidator;
        $this->uploader = $uploader;
        $this->validatorChainFactory = $validatorChainFactory;
        $this->imageFactory = $imageFactory;
        $this->ioFile = $ioFile;
        $this->quotePath = $quotePath;
        $this->orderPath = $orderPath;
    }

    /**
     * Process image content for product option and return file information
     *
     * @param ImageContentInterface $imageContent
     * @param Option $option
     * @return array|null
     * @throws LocalizedException
     */
    public function process(ImageContentInterface $imageContent, Option $option): ?array
    {
        if (!$imageContent->getBase64EncodedData()) {
            return null;
        }
        $this->validateBeforeSaveToTmp($imageContent, $option);

        $tmpFilename = $this->uploader->saveToTmpDir($imageContent);
        $validatorChain = $this->validatorChainFactory->create();
        $validatorChain = $this->buildImageValidator($validatorChain, $option);

        $tmpDirectory = $this->filesystem->getDirectoryRead(DirectoryList::SYS_TMP);
        if ($validatorChain->isValid($tmpDirectory->getAbsolutePath($tmpFilename))) {
            $size = $tmpDirectory->stat($tmpFilename)['size'];
            if (!$size) {
                throw new LocalizedException(__('The file is empty. Select another file and try again.'));
            }
            $imageInstance = $this->imageFactory->create($tmpDirectory->getAbsolutePath($tmpFilename));
            $width = $imageInstance->getOriginalWidth();
            $height = $imageInstance->getOriginalHeight();

            $fileHash = hash('sha256', $tmpDirectory->readFile($tmpFilename));
            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $filePath = $this->uploader->moveFromTmpDir(
                $imageContent,
                $tmpFilename,
                $mediaDirectory,
                $this->quotePath
            );
            $fileFullPath = $mediaDirectory->getAbsolutePath($this->quotePath . $filePath);

            return [
                'type' => $imageContent->getType(),
                'title' => $imageContent->getName(),
                'quote_path' => $this->quotePath . $filePath,
                'order_path' => $this->orderPath . $filePath,
                'fullpath' => $fileFullPath,
                'size' => $size,
                'width' => $width,
                'height' => $height,
                'secret_key' => substr($fileHash, 0, 20),
            ];
        } elseif ($validatorChain->getMessages()) {
            $errors = $this->getValidatorErrors(
                array_keys($validatorChain->getMessages()),
                [
                    'title' => $imageContent->getName(),
                    'name' => $imageContent->getName(),
                    'tmp_name' => $tmpDirectory->getAbsolutePath($tmpFilename),
                    'type' => $imageContent->getType(),
                    'size' => 0,
                ],
                $option
            );

            if (count($errors) > 0) {
                throw new LocalizedException(__(implode("\n", $errors)));
            }
        }

        return null;
    }

    private function getFileExtension(string $filename): ?string
    {
        $pathInfo = $this->ioFile->getPathInfo($filename);
        return $pathInfo['extension'] ?? null;
    }

    private function validateBeforeSaveToTmp(ImageContentInterface $imageContent, Option $option): void
    {
        $extension = $this->getFileExtension($imageContent->getName() ?: '');
        if ($extension !== null && (!$extension || !$this->extensionValidator->isValid($extension))) {
            throw new LocalizedException(__(
                "The file '%1' for '%2' has an invalid extension.",
                $imageContent->getName(),
                $option->getTitle()
            ));
        }
    }
}