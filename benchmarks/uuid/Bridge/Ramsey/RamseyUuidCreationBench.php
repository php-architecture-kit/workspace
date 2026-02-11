<?php

declare(strict_types=1);

namespace Benchmarks\PhpArchitecture\Uuid\Bridge\Ramsey;

use Benchmarks\PhpArchitecture\Uuid\Contract\UuidCreationBench;
use PhpBench\Attributes as Bench;
use Ramsey\Uuid\Uuid;

class RamseyUuidCreationBench extends UuidCreationBench
{
    #[Bench\Skip]
    public function benchfromStringWithoutValidation(array $params = []): string
    {
        // Ramsey Uuid::fromString() does not support Uuid recreation without validation
        return '';
    }

    public function benchfromStringWithValidation(array $params = []): string
    {
        return Uuid::fromString(self::VALID_UUID)->toString();
    }

    public function benchfromStringWithValidationException(array $params = []): string
    {
        try {
            Uuid::fromString(self::INVALID_UUID);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        throw new \Exception('Expected exception not thrown');
    }

    #[Bench\Skip]
    public function benchfromBinaryWithoutValidation(array $params = []): string
    {
        // Ramsey Uuid::fromBytes() does not support Uuid recreation without validation
        return '';
    }

    public function benchfromBinaryWithValidation(array $params = []): string
    {
        return Uuid::fromBytes(self::VALID_BINARY)->toString();
    }

    public function benchfromBinaryWithValidationException(array $params = []): string
    {
        try {
            Uuid::fromBytes(self::INVALID_BINARY);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        throw new \Exception('Expected exception not thrown');
    }

    public function benchUuidV1CreationWithoutParameters(array $params = []): string
    {
        return Uuid::uuid1()->toString();
    }

    public function benchUuidV1CreationWithRFC9562Parameters(array $params = []): string
    {
        return Uuid::uuid1('010203040506', 0x1234)->toString();
    }

    public function benchUuidV3Creation(array $params = []): string
    {
        return Uuid::uuid3(Uuid::fromString(self::NAMESPACE), self::NAME)->toString();
    }

    public function benchUuidV4Creation(array $params = []): string
    {
        return Uuid::uuid4()->toString();
    }

    public function benchUuidV5Creation(array $params = []): string
    {
        return Uuid::uuid5(Uuid::fromString(self::NAMESPACE), self::NAME)->toString();
    }

    public function benchUuidV6CreationWithoutParameters(array $params = []): string
    {
        return Uuid::uuid6()->toString();
    }

    public function benchUuidV6CreationWithRFC9562Parameters(array $params = []): string
    {
        return Uuid::uuid6(new \Ramsey\Uuid\Type\Hexadecimal('010203040506'), 0x1234)->toString();
    }

    public function benchUuidV7Creation(array $params = []): string
    {
        return Uuid::uuid7()->toString();
    }

    public function benchUuidV8Creation(array $params = []): string
    {
        return Uuid::uuid8("\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10")->toString();
    }
}
