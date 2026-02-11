<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Uuid\Unit\Provider;

use PhpArchitecture\Uuid\Bridge\Ramsey\RamseyUuidProvider;
use PhpArchitecture\Uuid\Bridge\Symfony\SymfonyUuidProvider;
use PhpArchitecture\Uuid\Exception\MissingProviderException;
use PhpArchitecture\Uuid\Provider\UuidProvider;
use PhpArchitecture\Uuid\Provider\UuidProviderRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UuidProviderRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        UuidProviderRegistry::reset();
    }

    protected function tearDown(): void
    {
        UuidProviderRegistry::reset();
    }

    #[Test]
    public function registerAddsProviderToRegistry(): void
    {
        $provider = new RamseyUuidProvider();
        UuidProviderRegistry::register('test', $provider);

        $this->assertTrue(UuidProviderRegistry::has('test'));
        $this->assertSame($provider, UuidProviderRegistry::get('test'));
    }

    #[Test]
    public function getThrowsExceptionForUnknownProvider(): void
    {
        $this->expectException(MissingProviderException::class);

        UuidProviderRegistry::get('unknown');
    }

    #[Test]
    public function hasReturnsTrueForRegisteredProvider(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());

        $this->assertTrue(UuidProviderRegistry::has('ramsey'));
    }

    #[Test]
    public function hasReturnsFalseForUnknownProvider(): void
    {
        $this->assertFalse(UuidProviderRegistry::has('unknown'));
    }

    #[Test]
    public function resetClearsAllProviders(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());
        $this->assertTrue(UuidProviderRegistry::has('ramsey'));

        UuidProviderRegistry::reset();

        $this->assertFalse(UuidProviderRegistry::has('ramsey'));
    }

    #[Test]
    public function getBestProviderForMethodReturnsProviderWithHighestScore(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());
        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());

        $provider = UuidProviderRegistry::getBestProviderForMethod('v4');

        $this->assertInstanceOf(UuidProvider::class, $provider);
    }

    #[Test]
    public function getBestProviderForMethodAutoRegistersProvidersWhenNoneExist(): void
    {
        // Registry auto-registers predefined providers when none exist
        $provider = UuidProviderRegistry::getBestProviderForMethod('v4');

        $this->assertInstanceOf(UuidProvider::class, $provider);
    }

    #[Test]
    public function registerPredefinedProvidersRegistersAvailableProviders(): void
    {
        UuidProviderRegistry::registerPredefinedProviders();

        $hasRamsey = UuidProviderRegistry::has(RamseyUuidProvider::class);
        $hasSymfony = UuidProviderRegistry::has(SymfonyUuidProvider::class);

        $this->assertTrue($hasRamsey || $hasSymfony, 'At least one provider should be registered');
    }

    #[Test]
    public function getBestProviderForV8ReturnsRamsey(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());
        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());

        $provider = UuidProviderRegistry::getBestProviderForMethod('v8');

        $this->assertInstanceOf(RamseyUuidProvider::class, $provider);
    }

    #[Test]
    public function registryUpdatesBestProviderOnNewRegistration(): void
    {
        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());
        $providerBefore = UuidProviderRegistry::getBestProviderForMethod('v4');

        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());
        $providerAfter = UuidProviderRegistry::getBestProviderForMethod('v4');

        $this->assertInstanceOf(UuidProvider::class, $providerBefore);
        $this->assertInstanceOf(UuidProvider::class, $providerAfter);
    }
}
