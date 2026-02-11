<?php

declare(strict_types=1);

namespace Benchmarks\PhpArchitecture\Uuid\Bridge\Symfony;

use Benchmarks\PhpArchitecture\Uuid\Contract\UuidCreationBench;
use PhpBench\Attributes as Bench;
use Symfony\Component\Uid\Uuid;

class SymfonyUuidCreationBench extends UuidCreationBench
{
    #[Bench\Skip]
    public function benchfromStringWithoutValidation(array $params = []): string
    {
        // Symfony Uuid::fromString() does not support Uuid recreation without validation
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
        // Symfony Uuid::fromBinary() does not support Uuid recreation without validation
        return '';
    }

    public function benchfromBinaryWithValidation(array $params = []): string
    {
        return Uuid::fromBinary(self::VALID_BINARY)->toString();
    }

    public function benchfromBinaryWithValidationException(array $params = []): string
    {
        try {
            Uuid::fromBinary(self::INVALID_BINARY);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        throw new \Exception('Expected exception not thrown');
    }

    public function benchUuidV1CreationWithoutParameters(array $params = []): string
    {
        return Uuid::v1()->toString();
    }

    #[Bench\Skip]
    public function benchUuidV1CreationWithRFC9562Parameters(array $params = []): string
    {
        // Symfony UuidV1 does not support clockSequence parameter (RFC9562 requirement)
        return '';
    }

    public function benchUuidV3Creation(array $params = []): string
    {
        return Uuid::v3(Uuid::fromString(self::NAMESPACE), self::NAME)->toString();
    }

    public function benchUuidV4Creation(array $params = []): string
    {
        return Uuid::v4()->toString();
    }

    public function benchUuidV5Creation(array $params = []): string
    {
        return Uuid::v5(Uuid::fromString(self::NAMESPACE), self::NAME)->toString();
    }

    public function benchUuidV6CreationWithoutParameters(array $params = []): string
    {
        return Uuid::v6()->toString();
    }

    #[Bench\Skip]
    public function benchUuidV6CreationWithRFC9562Parameters(array $params = []): string
    {
        // Symfony UuidV6 does not support clockSequence parameter (RFC9562 requirement)
        return '';
    }

    public function benchUuidV7Creation(array $params = []): string
    {
        return Uuid::v7()->toString();
    }

    #[Bench\Skip]
    public function benchUuidV8Creation(array $params = []): string
    {
        // Symfony does not support UuidV8
        return '';
    }
}
