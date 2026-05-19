<?php

declare(strict_types=1);

namespace Mubarik\SecurityFix\Plugin;

use Mubarik\SecurityFix\Logger\Logger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Directory\Write;

class MediaWriteSecurityPlugin
{
    private const DANGEROUS_EXT_PATTERN = '/\.(?:php|phtml|php[0-9]+|phar|pht|pl|py|jsp|asp|aspx|sh|cgi|htaccess)$/i';

    /** Basename only: script token then another extension or underscore (e.g. tdhrjh.php.txt, x.php_00.png). */
    private const DANGEROUS_EXT_IN_NAME_PATTERN = '/\.(?:php[0-9]*|phtml|phar|pht|pl|py|jsp|asp|aspx|sh|cgi)(?:\.|_)/i';

    private $logger;
    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Block writes to customer_address and executable-like extensions under media.
     *
     * @param mixed $content
     * @param string|null $mode
     * @return array{0: string, 1: mixed, 2: string|null, 3: bool}
     * @throws LocalizedException
     */
    public function beforeWriteFile(
        Write $subject,
        $path,
        $content,
        $mode = 'w+',
        bool $lock = false
    ): array {
        $relative = strtolower(str_replace('\\', '/', (string) $path));

        if (str_contains($relative, 'customer_address')) {
            $this->logger->warning('Blocked upload to customer_address: ' . $path);
            throw new LocalizedException(
                __('File upload to customer_address is not allowed.')
            );
        }

        $writeRoot = strtolower(str_replace('\\', '/', rtrim($subject->getAbsolutePath(''), '/')));
        $isPubMedia = str_contains($writeRoot, '/pub/media') || str_ends_with($writeRoot, '/media');

        $targetPath = strtolower(str_replace('\\', '/', $subject->getAbsolutePath((string) $path)));
        if ($isPubMedia && $this->isBlockedScriptUploadBasename($targetPath)) {
            $this->logger->warning('Blocked malicious file upload: ' . $path);
            throw new LocalizedException(
                __('Uploading executable or script files is not allowed.')
            );
        }

        return [$path, $content, $mode, $lock];
    }

    private function isBlockedScriptUploadBasename(string $absolutePathLower): bool
    {
        $basename = basename($absolutePathLower);
        if (preg_match(self::DANGEROUS_EXT_PATTERN, $basename) === 1) {
            return true;
        }
        if (preg_match(self::DANGEROUS_EXT_IN_NAME_PATTERN, $basename) === 1) {
            return true;
        }

        return false;
    }
}
