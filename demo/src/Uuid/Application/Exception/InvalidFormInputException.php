<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Uuid\Application\Exception;

use InvalidArgumentException;

/**
 * Form input that cannot be converted to the argument type a Uuid method expects
 * (e.g. a non-numeric clock sequence). Everything else is left to the uuid package to validate.
 */
final class InvalidFormInputException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
