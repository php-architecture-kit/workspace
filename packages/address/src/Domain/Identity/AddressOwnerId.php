<?php

declare(strict_types=1);

namespace PhpArchitecture\Address\Domain\Identity;

interface AddressOwnerId
{
    public function toString(): string;
}
