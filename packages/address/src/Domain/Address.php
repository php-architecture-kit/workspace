<?php

declare(strict_types=1);

namespace PhpArchitecture\Address\Domain;

use PhpArchitecture\Address\Domain\Identity\AddressId;
use PhpArchitecture\Address\Domain\Identity\AddressOwnerId;
use PhpArchitecture\Address\Domain\Property\AddressDetail;
use PhpArchitecture\Address\Domain\Property\AddressValidity;

abstract class Address
{
    /**
     * @param AddressDetail[] $details
     * @param string[] $useTypes
     */
    protected function __construct(
        public readonly AddressId $id,
        public readonly AddressOwnerId $ownerId,
        public protected(set) AddressValidity $validity,
        public protected(set) array $details,
        public protected(set) array $useTypes,
    ) {}
}
