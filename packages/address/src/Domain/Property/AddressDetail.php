<?php

declare(strict_types=1);

namespace PhpArchitecture\Address\Domain\Property;

final class AddressDetail
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {}
}
