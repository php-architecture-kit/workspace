<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Uuid\Integration\Bridge;

use PhpArchitecture\Uuid\Bridge\Ramsey\RamseyUuidProvider;
use PhpArchitecture\Uuid\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class RamseyUuidProviderIntegrationTest extends TestCase
{
    private RamseyUuidProvider $provider;

    protected function setUp(): void
    {
        if (!RamseyUuidProvider::canInstantiate()) {
            $this->markTestSkipped('ramsey/uuid is not installed');
        }

        $this->provider = new RamseyUuidProvider();
    }

    #[Test]
    public function canInstantiateReturnsTrueWhenLibraryAvailable(): void
    {
        $this->assertTrue(RamseyUuidProvider::canInstantiate());
    }

    #[Test]
    public function v1GeneratesValidUuid(): void
    {
        $uuid = $this->provider->v1();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    #[Test]
    public function v1RespectsCustomClock(): void
    {
        $fixedTime = new \DateTimeImmutable('2024-01-15 12:00:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($fixedTime);

        $uuid1 = $this->provider->v1($clock);
        $uuid2 = $this->provider->v1($clock);

        $this->assertNotSame($uuid1, $uuid2);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}/', $uuid1);
    }

    #[Test]
    public function v1RespectsCustomClockSequence(): void
    {
        $uuid = $this->provider->v1(clockSequence: 0x1234);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}/', $uuid);
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
            $uuid
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
            $uuid
        );
    }

    #[Test]
    public function v6GeneratesChronologicallySortableUuids(): void
    {
        $uuid1 = $this->provider->v6();
        usleep(1000); // 1ms delay
        $uuid2 = $this->provider->v6();

        $this->assertLessThan($uuid2, $uuid1);
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
            $uuid
        );
    }

    #[Test]
    public function v7ContainsValidTimestamp(): void
    {
        $before = (int) (microtime(true) * 1000);
        $uuid = $this->provider->v7();
        $after = (int) (microtime(true) * 1000);

        // Extract timestamp from UUID v7 (first 48 bits = 12 hex chars)
        $hex = str_replace('-', '', $uuid);
        $timestampHex = substr($hex, 0, 12);
        $timestamp = hexdec($timestampHex);

        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    #[Test]
    public function v8GeneratesValidUuid(): void
    {
        $customData = "\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10";
        $uuid = $this->provider->v8($customData);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-8[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
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
    public function supportMatrixReturnsHighValuesForAllMethods(): void
    {
        $matrix = RamseyUuidProvider::supportMatrix();

        $this->assertGreaterThan(0.0, $matrix['v1']);
        $this->assertGreaterThan(0.0, $matrix['v3']);
        $this->assertGreaterThan(0.0, $matrix['v4']);
        $this->assertGreaterThan(0.0, $matrix['v5']);
        $this->assertGreaterThan(0.0, $matrix['v6']);
        $this->assertGreaterThan(0.0, $matrix['v7']);
        $this->assertGreaterThan(0.0, $matrix['v8']);
        $this->assertGreaterThan(0.0, $matrix['validate']);
    }
}
