<?php

declare(strict_types=1);

namespace PhpArchitecture\Address\Domain\Repository;

use PhpArchitecture\Address\Domain\Addresses;
use PhpArchitecture\Address\Domain\Identity\AddressOwnerId;

interface AddressesRepositoryInterface
{
    public function find(AddressOwnerId $ownerId): Addresses;
    public function save(Addresses $addresses): void;
}
