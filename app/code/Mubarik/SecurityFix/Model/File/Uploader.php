<?php
/**
* Copyright © Magento, Inc. All rights reserved.
* See COPYING.txt for license details.
*/
declare(strict_types=1);
 
namespace Mubarik\SecurityFix\Model\File;
 
use Magento\MediaStorage\Model\File\Uploader as MagentoUploader;
 
class Uploader extends MagentoUploader
{
    /**
     * Process file attributes for manual initialization.
     *
     * @param array $fileAttributes
     * @return void
     */
    /**
     * Core file storage
     *
     * @var \Magento\MediaStorage\Helper\File\Storage
     */
    protected $_coreFileStorage = null;

    /**
     * Core file storage database
     *
     * @var \Magento\MediaStorage\Helper\File\Storage\Database
     */
    protected $_coreFileStorageDb = null;

    /**
     * @var \Magento\MediaStorage\Model\File\Validator\NotProtectedExtension
     */
    protected $_validator;

    /**
     * @param string $fileId
     * @param \Magento\MediaStorage\Helper\File\Storage\Database $coreFileStorageDb
     * @param \Magento\MediaStorage\Helper\File\Storage $coreFileStorage
     * @param \Magento\MediaStorage\Model\File\Validator\NotProtectedExtension $validator
     */
    public function __construct(
        $fileId,
        \Magento\MediaStorage\Helper\File\Storage\Database $coreFileStorageDb,
        \Magento\MediaStorage\Helper\File\Storage $coreFileStorage,
        \Magento\MediaStorage\Model\File\Validator\NotProtectedExtension $validator
    )
    {
        if (empty($_FILES)) {
            $this->_fileExists = false;
            return;
        }
 
        parent::__construct($fileId, $coreFileStorageDb, $coreFileStorage, $validator);
    }
 
    public function processFileAttributes(array $fileAttributes): void
    {
        $this->_file = $fileAttributes;
        $this->_fileExists = true;
    }
}