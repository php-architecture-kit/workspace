<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\LazyOperators\Application\Exception;

use InvalidArgumentException;

final class MissingInputException extends InvalidArgumentException
{
    public function __construct(public readonly string $input)
    {
        parent::__construct(sprintf('Input "%s" has no value.', $input));
    }
}
