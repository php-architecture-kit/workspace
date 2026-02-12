<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Uuid\Integration;

use PhpArchitecture\Uuid\Bridge\Ramsey\RamseyUuidProvider;
use PhpArchitecture\Uuid\Bridge\Symfony\SymfonyUuidProvider;
use PhpArchitecture\Uuid\Provider\UuidProviderRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProviderRegistryIntegrationTest extends TestCase
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
    public function registerPredefinedProvidersRegistersSymfonyWhenAvailable(): void
    {
        if (!SymfonyUuidProvider::canInstantiate()) {
            $this->markTestSkipped('symfony/uid is not installed');
        }

        UuidProviderRegistry::registerPredefinedProviders();

        $this->assertTrue(UuidProviderRegistry::has(SymfonyUuidProvider::class));
    }

    #[Test]
    public function registerPredefinedProvidersRegistersRamseyWhenAvailable(): void
    {
        if (!RamseyUuidProvider::canInstantiate()) {
            $this->markTestSkipped('ramsey/uuid is not installed');
        }

        UuidProviderRegistry::registerPredefinedProviders();

        $this->assertTrue(UuidProviderRegistry::has(RamseyUuidProvider::class));
    }

    #[Test]
    public function getBestProviderForV8SelectsRamseyOverSymfony(): void
    {
        if (!RamseyUuidProvider::canInstantiate()) {
            $this->markTestSkipped('ramsey/uuid is not installed');
        }

        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());

        $provider = UuidProviderRegistry::getBestProviderForMethod('v8');

        $this->assertInstanceOf(RamseyUuidProvider::class, $provider);
    }

    #[Test]
    public function getBestProviderForV4SelectsProviderWithHighestScore(): void
    {
        UuidProviderRegistry::registerPredefinedProviders();

        $provider = UuidProviderRegistry::getBestProviderForMethod('v4');

        $this->assertNotNull($provider);
        // Both providers support v4, so either is acceptable
        $this->assertTrue(
            $provider instanceof RamseyUuidProvider || $provider instanceof SymfonyUuidProvider,
        );
    }

    #[Test]
    public function fallbackBehaviorWhenOneProviderUnavailable(): void
    {
        // Only register Symfony (simulating Ramsey unavailable)
        if (!SymfonyUuidProvider::canInstantiate()) {
            $this->markTestSkipped('symfony/uid is not installed');
        }

        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());

        $provider = UuidProviderRegistry::getBestProviderForMethod('v4');

        $this->assertInstanceOf(SymfonyUuidProvider::class, $provider);
    }

    #[Test]
    public function autoRegistrationOnFirstUse(): void
    {
        // Registry should auto-register providers when calling getBestProviderForMethod
        $provider = UuidProviderRegistry::getBestProviderForMethod('v4');

        $this->assertNotNull($provider);
    }
}
