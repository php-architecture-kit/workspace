<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Uuid\Integration;

use PhpArchitecture\Uuid\Bridge\Ramsey\RamseyUuidProvider;
use PhpArchitecture\Uuid\Bridge\Symfony\SymfonyUuidProvider;
use PhpArchitecture\Uuid\Provider\UuidProviderRegistry;
use PhpArchitecture\Uuid\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UuidProviderIntegrationTest extends TestCase
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
    public function uuidV4UsesRegisteredProvider(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());

        $uuid = Uuid::v4();

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame(4, $uuid->getVersion());
    }

    #[Test]
    public function uuidNewDelegatesToV7(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());

        $uuid = Uuid::new();

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame(7, $uuid->getVersion());
    }

    #[Test]
    public function providerSwitchingAffectsGeneration(): void
    {
        // Use Ramsey
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());
        $ramseyUuid = Uuid::v4();

        // Reset and use Symfony
        UuidProviderRegistry::reset();
        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());
        $symfonyUuid = Uuid::v4();

        // Both should be valid v4 UUIDs
        $this->assertSame(4, $ramseyUuid->getVersion());
        $this->assertSame(4, $symfonyUuid->getVersion());

        // But they should be different
        $this->assertFalse($ramseyUuid->equals($symfonyUuid));
    }

    #[Test]
    public function uuidV1WithCustomParametersUsesRamsey(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());

        $uuid = Uuid::v1(clockSequence: 0x1234, nodeIdentifier: '010203040506');

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame(1, $uuid->getVersion());
        $this->assertStringEndsWith('010203040506', $uuid->value());
    }

    #[Test]
    public function uuidV3IsDeterministic(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());

        $namespace = Uuid::fromString(Uuid::NAMESPACE_URL);
        $name = 'https://example.com';

        $uuid1 = Uuid::v3($namespace, $name);
        $uuid2 = Uuid::v3($namespace, $name);

        $this->assertTrue($uuid1->equals($uuid2));
    }

    #[Test]
    public function uuidV5IsDeterministic(): void
    {
        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());

        $namespace = Uuid::fromString(Uuid::NAMESPACE_DNS);
        $name = 'example.com';

        $uuid1 = Uuid::v5($namespace, $name);
        $uuid2 = Uuid::v5($namespace, $name);

        $this->assertTrue($uuid1->equals($uuid2));
    }

    #[Test]
    public function uuidV6GeneratesValidUuid(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());

        $uuid = Uuid::v6();

        $this->assertSame(6, $uuid->getVersion());
    }

    #[Test]
    public function uuidV7GeneratesValidUuid(): void
    {
        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());

        $uuid = Uuid::v7();

        $this->assertSame(7, $uuid->getVersion());
    }

    #[Test]
    public function uuidV8UsesRamseyProvider(): void
    {
        UuidProviderRegistry::register('ramsey', new RamseyUuidProvider());
        UuidProviderRegistry::register('symfony', new SymfonyUuidProvider());

        $customData = "\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10";
        $uuid = Uuid::v8($customData);

        $this->assertSame(8, $uuid->getVersion());
    }
}
