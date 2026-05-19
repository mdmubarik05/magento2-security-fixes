<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Mubarik\SecurityFix\Model\Validator;

use Magento\Framework\Validator\ValidatorChain;
use Magento\Framework\ObjectManagerInterface;

class ValidatorChainFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create ValidatorChain
     *
     * @return ValidatorChain
     */
    public function create()
    {
        return $this->objectManager->create(ValidatorChain::class);
    }
}
