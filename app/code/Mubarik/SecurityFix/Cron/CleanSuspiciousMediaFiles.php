<?php

declare(strict_types=1);

namespace Mubarik\SecurityFix\Cron;

use Mubarik\SecurityFix\Logger\Logger;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;

/**
 * Removes script-like files under known upload-heavy media subdirectories,
 * tmp_* debris, and customer_address attack junk (merged from Aureatec_UploadGuard).
 */
class CleanSuspiciousMediaFiles
{
    private const DANGEROUS_EXT_PATTERN = '/\.(?:php|phtml|php[0-9]+|phar|pht)$/i';
    private const EMBEDDED_SCRIPT_IN_BASENAME_PATTERN = '/\.(?:php[0-9]*|phtml|phar|pht)(?:\.|_)/i';
    private const TMP_NUMERIC_BASENAME_PATTERN = '/^tmp_\d+$/';
    private const TMP_PAIR_SUFFIX_PATTERN = '/^[a-zA-Z0-9_-]{1,2}_\d+$/';
    private const OBFUSCATED_SCRIPT_BASENAME_PATTERN = '/\.(?:php[0-9]*|phtml|phar|pht)(?:\.|$|[_-])/i';
    private const OBFUSCATED_MODULE_INC_PATTERN = '/\.(?:module|inc)(?:\.|$)/i';
    private const SCAN_SUBDIRS = [
        'customer_address',
        'tmp',
        'upload',
        'custom_options',
        'hr',
    ];

    /** @var DirectoryList */
    private $directoryList;

    /** @var FileDriver */
    private $fileDriver;

    /** @var Logger */
    private $logger;

    public function __construct(
        DirectoryList $directoryList,
        FileDriver $fileDriver,
        Logger $logger
    ) {
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $mediaPath = rtrim($this->directoryList->getPath(DirectoryList::MEDIA), DIRECTORY_SEPARATOR);

        foreach (self::SCAN_SUBDIRS as $subdir) {
            $base = $mediaPath . DIRECTORY_SEPARATOR . $subdir;
            if (!$this->fileDriver->isExists($base) || !$this->fileDriver->isDirectory($base)) {
                continue;
            }
            $this->scanDirectory($base);
        }

        $this->scanTmpNumericFiles($mediaPath);
        $this->scanCustomerAddressAttackDebris($mediaPath);
    }

    private function scanCustomerAddressAttackDebris(string $mediaRoot): void
    {
        $customerAddress = $mediaRoot . DIRECTORY_SEPARATOR . 'customer_address';
        if (!$this->fileDriver->isExists($customerAddress) || !$this->fileDriver->isDirectory($customerAddress)) {
            return;
        }

        $this->scanCustomerAddressSingleCharPathFiles($customerAddress);

        $tmpDir = $customerAddress . DIRECTORY_SEPARATOR . 'tmp';
        if ($this->fileDriver->isExists($tmpDir) && $this->fileDriver->isDirectory($tmpDir)) {
            $this->scanCustomerAddressTmpDebris($tmpDir);
        }
    }

    private function scanCustomerAddressSingleCharPathFiles(string $customerAddressRoot): void
    {
        try {
            $level1 = new \FilesystemIterator(
                $customerAddressRoot,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Mubarik SecurityFix cron: cannot read customer_address: ' . $customerAddressRoot . ' — ' . $e->getMessage()
            );
            return;
        }

        foreach ($level1 as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isDir()) {
                continue;
            }
            $dirName = $item->getBasename();
            if (strlen($dirName) !== 1) {
                continue;
            }
            $dirPath = $item->getRealPath();
            if ($dirPath === false) {
                continue;
            }
            try {
                $inner = new \FilesystemIterator(
                    $dirPath,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
                );
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($inner as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                if (strlen($file->getBasename()) !== 1) {
                    continue;
                }
                $path = $file->getRealPath();
                if ($path !== false) {
                    $this->tryRemoveMediaFile($path, 'removed customer_address single-char path junk');
                }
            }
        }
    }

    private function scanCustomerAddressTmpDebris(string $tmpDir): void
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $tmpDir,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
                )
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Mubarik SecurityFix cron: cannot read customer_address/tmp: ' . $tmpDir . ' — ' . $e->getMessage()
            );
            return;
        }

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $basename = $file->getBasename();
            $size = $file->getSize();
            if (!$this->isCustomerAddressTmpDebrisFile($basename, $size)) {
                continue;
            }
            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }
            $this->tryRemoveMediaFile($path, 'removed customer_address/tmp debris file');
        }
    }

    private function isCustomerAddressTmpDebrisFile(string $basename, $size): bool
    {
        if ($this->basenameLooksLikeObfuscatedScript($basename)) {
            return true;
        }
        if (strlen($basename) <= 2) {
            return true;
        }
        if (preg_match(self::TMP_PAIR_SUFFIX_PATTERN, $basename) === 1) {
            return true;
        }
        $sz = $size === false ? 0 : $size;
        if (!strpos($basename, '.') && $sz <= 512 && strlen($basename) <= 12) {
            return true;
        }

        return false;
    }

    private function basenameLooksLikeObfuscatedScript(string $basename): bool
    {
        if (preg_match(self::OBFUSCATED_SCRIPT_BASENAME_PATTERN, $basename) === 1) {
            return true;
        }
        if (preg_match(self::OBFUSCATED_MODULE_INC_PATTERN, $basename) === 1) {
            return true;
        }
        if (stripos($basename, 'pgif') !== false) {
            return true;
        }

        return false;
    }

    private function scanTmpNumericFiles(string $mediaRoot): void
    {
        if (!$this->fileDriver->isExists($mediaRoot) || !$this->fileDriver->isDirectory($mediaRoot)) {
            return;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $mediaRoot,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
                )
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Mubarik SecurityFix cron: cannot read media root for tmp_* cleanup: ' . $mediaRoot . ' — ' . $e->getMessage()
            );
            return;
        }

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $basename = $file->getBasename();
            if (preg_match(self::TMP_NUMERIC_BASENAME_PATTERN, $basename) !== 1) {
                continue;
            }
            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }
            $this->tryRemoveMediaFile($path, 'removed tmp_numeric debris file');
        }
    }

    private function scanDirectory(string $directory): void
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $directory,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
                )
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Mubarik SecurityFix cron: cannot read directory: ' . $directory . ' — ' . $e->getMessage());
            return;
        }

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }
            $basename = $file->getBasename();
            $terminal = preg_match(self::DANGEROUS_EXT_PATTERN, $path) === 1;
            $embedded = preg_match(self::EMBEDDED_SCRIPT_IN_BASENAME_PATTERN, $basename) === 1;
            if (!$terminal && !$embedded) {
                continue;
            }
            $this->tryRemoveMediaFile($path, 'removed suspicious file');
        }
    }

    private function tryRemoveMediaFile(string $path, string $successLogVerb): void
    {
        if (!$this->fileDriver->isExists($path) || !$this->fileDriver->isFile($path)) {
            return;
        }

        @chmod($path, 0666);

        try {
            $this->fileDriver->deleteFile($path);
            $this->logger->warning('Mubarik SecurityFix cron: ' . $successLogVerb . ': ' . $path);
        } catch (FileSystemException $e) {
            $msg = $e->getMessage();
            $this->logger->warning(
                'Mubarik SecurityFix cron: failed to remove: ' . $path . ' — ' . $msg
                . $this->buildPermissionDiagnostics($path, $msg)
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Mubarik SecurityFix cron: failed to remove: ' . $path . ' — ' . $e->getMessage()
                . $this->buildPermissionDiagnostics($path, $e->getMessage())
            );
        }
    }

    private function buildPermissionDiagnostics(string $path, string $errorMessage): string
    {
        $lower = strtolower($errorMessage);
        $looksLikePermission = strpos($lower, 'permission') !== false
            || strpos($lower, "can't be deleted") !== false
            || strpos($lower, 'cannot be deleted') !== false
            || strpos($lower, 'unlink') !== false;

        if (!$looksLikePermission) {
            return '';
        }

        $parts = [
            ' Fix: run Magento cron as the same OS user as PHP-FPM, e.g. `sudo -u www-data /usr/bin/php bin/magento cron:run`',
            '(adjust user/path). Or `chown -R <php-fpm-user>:<php-fpm-user> pub/media` so cron can delete files created by the web server.',
        ];

        if (function_exists('posix_geteuid') && function_exists('fileowner')) {
            $ownerUid = @fileowner($path);
            $euid = posix_geteuid();
            if ($ownerUid !== false && (int) $ownerUid !== (int) $euid) {
                array_unshift(
                    $parts,
                    sprintf(' Diagnostics: file owner UID=%s, cron effective UID=%s.', $ownerUid, $euid)
                );
            }
        }

        return ' |' . implode(' ', $parts);
    }
}