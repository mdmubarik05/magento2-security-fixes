<?php

declare(strict_types=1);

namespace Mubarik\SecurityFix\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/Mubarik_security_fix.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::WARNING;
}
