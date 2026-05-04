<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Conditional\Exception;

use RuntimeException;

class NoMatchedCaseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No matched case');
    }
}
