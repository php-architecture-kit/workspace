<?php

declare(strict_types=1);

namespace Benchmarks\PhpArchitecture\Uuid;

use Benchmarks\PhpArchitecture\Uuid\Contract\UuidCreationBench as Contract;
use PhpArchitecture\Uuid\Bridge\Ramsey\RamseyUuidProvider;
use PhpArchitecture\Uuid\Bridge\Symfony\SymfonyUuidProvider;
use PhpArchitecture\Uuid\Exception\InvalidUuidException;
use PhpArchitecture\Uuid\Provider\UuidProviderRegistry;
use PhpArchitecture\Uuid\Uuid;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUpIteration')]
class UuidCreationBench extends Contract
{
    private string $providerMode;
    private static ?RamseyUuidProvider $ramseyProvider = null;
    private static ?SymfonyUuidProvider $symfonyProvider = null;

    public function provideProviders(): \Generator
    {
        yield 'autodetect' => ['mode' => 'autodetect'];
        yield 'ramsey' => ['mode' => 'ramsey'];
        yield 'symfony' => ['mode' => 'symfony'];
    }

    public function provideRamseyOnly(): \Generator
    {
        yield 'ramsey' => ['mode' => 'ramsey'];
    }
    private static string $lastMode = '';

    public function setUpIteration(array $params): void
    {
        $this->providerMode = $params['mode'];

        // Only reset if mode changed
        if (self::$lastMode !== $this->providerMode) {
            self::$lastMode = $this->providerMode;
            UuidProviderRegistry::reset();

            match ($this->providerMode) {
                'ramsey' => UuidProviderRegistry::register('ramsey', self::$ramseyProvider ??= new RamseyUuidProvider()),
                'symfony' => UuidProviderRegistry::register('symfony', self::$symfonyProvider ??= new SymfonyUuidProvider()),
                'autodetect' => null, // lazy registration on first use
            };
        }
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchfromStringWithoutValidation(array $params = []): string
    {
        return Uuid::fromString(self::VALID_UUID, false)->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchfromStringWithValidation(array $params = []): string
    {
        return Uuid::fromString(self::VALID_UUID, true)->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchfromStringWithValidationException(array $params = []): string
    {
        try {
            Uuid::fromString(self::INVALID_UUID, true);
        } catch (InvalidUuidException $e) {
            return $e->getMessage();
        }

        throw new \Exception('Expected exception not thrown');
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchfromBinaryWithoutValidation(array $params = []): string
    {
        return Uuid::fromBinary(self::VALID_BINARY, false)->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchfromBinaryWithValidation(array $params = []): string
    {
        return Uuid::fromBinary(self::VALID_BINARY, true)->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchfromBinaryWithValidationException(array $params = []): string
    {
        try {
            Uuid::fromBinary(self::INVALID_BINARY, true);
        } catch (InvalidUuidException $e) {
            return $e->getMessage();
        }

        throw new \Exception('Expected exception not thrown');
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchUuidV1CreationWithoutParameters(array $params = []): string
    {
        return Uuid::v1()->toString();
    }

    #[Bench\ParamProviders('provideRamseyOnly')]
    public function benchUuidV1CreationWithRFC9562Parameters(array $params = []): string
    {
        return Uuid::v1(clock: null, clockSequence: 0x1234, nodeIdentifier: '010203040506')->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchUuidV3Creation(array $params = []): string
    {
        return Uuid::v3(Uuid::fromString(self::NAMESPACE, false), self::NAME)->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchUuidV4Creation(array $params = []): string
    {
        return Uuid::v4()->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchUuidV5Creation(array $params = []): string
    {
        return Uuid::v5(Uuid::fromString(self::NAMESPACE), self::NAME)->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchUuidV6CreationWithoutParameters(array $params = []): string
    {
        return Uuid::v6()->toString();
    }

    #[Bench\ParamProviders('provideRamseyOnly')]
    public function benchUuidV6CreationWithRFC9562Parameters(array $params = []): string
    {
        return Uuid::v6(clock: null, clockSequence: 0x1234, nodeIdentifier: '010203040506')->toString();
    }

    #[Bench\ParamProviders('provideProviders')]
    public function benchUuidV7Creation(array $params = []): string
    {
        return Uuid::v7()->toString();
    }

    #[Bench\ParamProviders('provideRamseyOnly')]
    public function benchUuidV8Creation(array $params = []): string
    {
        return Uuid::v8("\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10")->toString();
    }
}
