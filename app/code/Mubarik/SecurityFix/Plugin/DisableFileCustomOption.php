<?php

declare(strict_types=1);

namespace Mubarik\SecurityFix\Plugin;

use Magento\Catalog\Model\Product\Option\Type\File;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

/**
 * Disables catalog product file-type custom options on storefront and APIs (PolyShell-related surface).
 * Admin area remains allowed.
 */
class DisableFileCustomOption
{
    private $appState;
    public function __construct(
        State $appState
    ) {
        $this->appState = $appState;
    }

    /**
     * @param array<mixed>|mixed $values
     * @return array{0: mixed}
     * @throws LocalizedException
     */
    public function beforeValidateUserValue(File $subject, $values): array
    {
        try {
            $areaCode = $this->appState->getAreaCode();
        } catch (\Exception $e) {
            return [$values];
        }

        if ($areaCode === 'adminhtml') {
            return [$values];
        }

        throw new LocalizedException(
            __('File upload custom options are disabled on storefront and APIs.')
        );
    }
}
