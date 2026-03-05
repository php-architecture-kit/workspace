<?php

declare(strict_types=1);

namespace PhpArchitecture\Address\Domain\Policy;

use PhpArchitecture\Address\Domain\Address;
use PhpArchitecture\Address\Domain\Addresses;

interface AddressDefiningPolicy
{
    public function isAddressAllowed(Addresses $addresses, Address $address): bool;

    public function getPolicyViolationMessage(Addresses $addresses, Address $address): ?string;
}
