<?php

declare(strict_types=1);

namespace PhpArchitecture\Address\Domain\Property;

use DateTimeImmutable;

final class AddressValidity
{
    public function __construct(
        public readonly ?DateTimeImmutable $from,
        public readonly ?DateTimeImmutable $to,
    ) {}

    public static function always(): self
    {
        return new self(null, null);
    }
}
