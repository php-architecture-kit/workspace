<?php

declare(strict_types=1);

namespace PhpArchitecture\Address\Domain;

use PhpArchitecture\Address\Domain\Identity\AddressOwnerId;
use PhpArchitecture\Address\Domain\Policy\AddressDefiningPolicy;
use PhpArchitecture\DomainCore\AggregateRoot;
use PhpArchitecture\DomainCore\Exception\InvalidInputException;
use PhpArchitecture\DomainCore\Exception\InvalidStateCausedException;

class Addresses extends AggregateRoot
{
    /**
     * @param array<string,Address> $addresses
     */
    protected function __construct(
        public readonly AddressOwnerId $ownerId,
        public readonly AddressDefiningPolicy $policy,
        public protected(set) array $addresses,
    ) {}

    /**
     * @param Address[] $addresses
     */
    public static function create(
        AddressOwnerId $ownerId,
        AddressDefiningPolicy $policy,
        array $addresses = []
    ): self {
        $addressesByUuid = [];
        foreach ($addresses as $address) {
            if (!$address instanceof Address) {
                throw new InvalidInputException('Addresses must be an array of Address objects. Got: `' . gettype($address) . '`');
            }

            $addressesByUuid[$address->id->toString()] = $address;
        }

        $instance = new self($ownerId, $policy, $addressesByUuid);

        foreach ($instance->addresses as $address) {
            if (!$instance->policy->isAddressAllowed($instance, $address)) {
                throw new InvalidStateCausedException(
                    'The address policy violated: ' .
                        $instance->policy->getPolicyViolationMessage($instance, $address)
                );
            }
        }

        return $instance;
    }
}
