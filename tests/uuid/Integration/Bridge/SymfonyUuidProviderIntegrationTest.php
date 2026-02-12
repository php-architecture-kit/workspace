<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Uuid\Integration\Bridge;

use PhpArchitecture\Uuid\Bridge\Symfony\SymfonyUuidProvider;
use PhpArchitecture\Uuid\Exception\ArgumentNotSupportedByProviderException;
use PhpArchitecture\Uuid\Exception\NotSupportedUuidVersionByProviderException;
use PhpArchitecture\Uuid\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class SymfonyUuidProviderIntegrationTest extends TestCase
{
    private SymfonyUuidProvider $provider;

    protected function setUp(): void
    {
        if (!SymfonyUuidProvider::canInstantiate()) {
            $this->markTestSkipped('symfony/uid is not installed');
        }

        $this->provider = new SymfonyUuidProvider();
    }

    #[Test]
    public function canInstantiateReturnsTrueWhenLibraryAvailable(): void
    {
        $this->assertTrue(SymfonyUuidProvider::canInstantiate());
    }

    #[Test]
    public function v1GeneratesValidUuid(): void
    {
        $uuid = $this->provider->v1();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
        );
    }

    #[Test]
    public function v1RespectsCustomClock(): void
    {
        $fixedTime = new \DateTimeImmutable('2024-01-15 12:00:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($fixedTime);

        $uuid = $this->provider->v1($clock);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}/', $uuid);
    }

    #[Test]
    public function v1ThrowsExceptionForClockSequence(): void
    {
        $this->expectException(ArgumentNotSupportedByProviderException::class);
        $this->expectExceptionMessage('clock sequence');

        $this->provider->v1(clockSequence: 0x1234);
    }

    #[Test]
    public function v1RespectsCustomNodeIdentifier6Bytes(): void
    {
        $uuid = $this->provider->v1(nodeIdentifier: "\x01\x02\x03\x04\x05\x06");

        $this->assertStringEndsWith('010203040506', $uuid);
    }

    #[Test]
    public function v1RespectsCustomNodeIdentifier12Hex(): void
    {
        $uuid = $this->provider->v1(nodeIdentifier: 'aabbccddeeff');

        $this->assertStringEndsWith('aabbccddeeff', $uuid);
    }

    #[Test]
    public function v3GeneratesDeterministicUuid(): void
    {
        $namespace = Uuid::NAMESPACE_URL;
        $name = 'https://example.com';

        $uuid1 = $this->provider->v3($namespace, $name);
        $uuid2 = $this->provider->v3($namespace, $name);

        $this->assertSame($uuid1, $uuid2);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-3[0-9a-f]{3}/', $uuid1);
    }

    #[Test]
    public function v3GeneratesDifferentUuidForDifferentInput(): void
    {
        $namespace = Uuid::NAMESPACE_URL;

        $uuid1 = $this->provider->v3($namespace, 'https://example1.com');
        $uuid2 = $this->provider->v3($namespace, 'https://example2.com');

        $this->assertNotSame($uuid1, $uuid2);
    }

    #[Test]
    public function v4GeneratesRandomUuid(): void
    {
        $uuid = $this->provider->v4();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
        );
    }

    #[Test]
    public function v4GeneratesUniqueUuids(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = $this->provider->v4();
        }

        $this->assertCount(100, array_unique($uuids));
    }

    #[Test]
    public function v5GeneratesDeterministicUuid(): void
    {
        $namespace = Uuid::NAMESPACE_URL;
        $name = 'https://example.com';

        $uuid1 = $this->provider->v5($namespace, $name);
        $uuid2 = $this->provider->v5($namespace, $name);

        $this->assertSame($uuid1, $uuid2);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}/', $uuid1);
    }

    #[Test]
    public function v6GeneratesValidUuid(): void
    {
        $uuid = $this->provider->v6();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-6[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
        );
    }

    #[Test]
    public function v6ThrowsExceptionForClockSequence(): void
    {
        $this->expectException(ArgumentNotSupportedByProviderException::class);
        $this->expectExceptionMessage('clock sequence');

        $this->provider->v6(clockSequence: 0x1234);
    }

    #[Test]
    public function v6RespectsCustomNodeIdentifier(): void
    {
        $uuid = $this->provider->v6(nodeIdentifier: 'ffeeddccbbaa');

        $this->assertStringEndsWith('ffeeddccbbaa', $uuid);
    }

    #[Test]
    public function v7GeneratesValidUuid(): void
    {
        $uuid = $this->provider->v7();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
        );
    }

    #[Test]
    public function v7RespectsCustomClock(): void
    {
        $fixedTime = new \DateTimeImmutable('2024-06-15 12:00:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($fixedTime);

        $uuid = $this->provider->v7($clock);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}/', $uuid);
    }

    #[Test]
    public function v8ThrowsNotSupportedException(): void
    {
        $this->expectException(NotSupportedUuidVersionByProviderException::class);

        $this->provider->v8("\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10");
    }

    #[Test]
    public function validateReturnsTrueForValidUuid(): void
    {
        $this->assertTrue($this->provider->validate('df516cba-fb13-4f45-8335-00252f1b87e2'));
    }

    #[Test]
    public function validateReturnsFalseForInvalidUuid(): void
    {
        $this->assertFalse($this->provider->validate('not-a-valid-uuid'));
        $this->assertFalse($this->provider->validate('df516cba-fb13-4f45-8335-00252f1b87g2'));
    }

    #[Test]
    public function supportMatrixReturnsZeroForV8(): void
    {
        $matrix = SymfonyUuidProvider::supportMatrix();

        $this->assertSame(0.0, $matrix['v8']);
    }

    #[Test]
    public function supportMatrixReturnsPositiveValuesForSupportedMethods(): void
    {
        $matrix = SymfonyUuidProvider::supportMatrix();

        $this->assertGreaterThan(0.0, $matrix['v1']);
        $this->assertGreaterThan(0.0, $matrix['v3']);
        $this->assertGreaterThan(0.0, $matrix['v4']);
        $this->assertGreaterThan(0.0, $matrix['v5']);
        $this->assertGreaterThan(0.0, $matrix['v6']);
        $this->assertGreaterThan(0.0, $matrix['v7']);
        $this->assertGreaterThan(0.0, $matrix['validate']);
    }
}
