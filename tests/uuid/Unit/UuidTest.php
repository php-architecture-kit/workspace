<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Uuid\Unit;

use PhpArchitecture\Uuid\Exception\InvalidUuidException;
use PhpArchitecture\Uuid\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UuidTest extends TestCase
{
    #[Test]
    public function fromStringCreatesValidInstance(): void
    {
        $uuidString = 'df516cba-fb13-4f45-8335-00252f1b87e2';
        $uuid = Uuid::fromString($uuidString);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuidString, $uuid->value());
    }

    #[Test]
    public function fromStringThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(InvalidUuidException::class);
        Uuid::fromString('not-a-valid-uuid');
    }

    #[Test]
    public function fromStringThrowsExceptionForInvalidCharacter(): void
    {
        $this->expectException(InvalidUuidException::class);
        Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87g2'); // 'g' is invalid
    }

    #[Test]
    public function fromStringWithoutValidationSkipsValidation(): void
    {
        $invalidUuid = 'df516cba-fb13-4f45-8335-00252f1b87g2';
        $uuid = Uuid::fromString($invalidUuid, false);

        $this->assertSame($invalidUuid, $uuid->value());
    }

    #[Test]
    public function fromStringPreservesCase(): void
    {
        $upperCase = 'DF516CBA-FB13-4F45-8335-00252F1B87E2';
        $uuid = Uuid::fromString($upperCase);

        $this->assertSame($upperCase, $uuid->value());
    }

    #[Test]
    public function fromBinaryCreatesValidUuid(): void
    {
        $binary = "\xdf\x51\x6c\xba\xfb\x13\x4f\x45\x83\x35\x00\x25\x2f\x1b\x87\xe2";
        $uuid = Uuid::fromBinary($binary);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame('df516cba-fb13-4f45-8335-00252f1b87e2', $uuid->value());
    }

    #[Test]
    public function fromBinaryThrowsExceptionForInvalidLength(): void
    {
        $this->expectException(InvalidUuidException::class);
        Uuid::fromBinary("\x01\x02\x03"); // too short
    }

    #[Test]
    public function fromBinaryThrowsExceptionForTooLongBinary(): void
    {
        $this->expectException(InvalidUuidException::class);
        Uuid::fromBinary(str_repeat("\x00", 17)); // 17 bytes
    }

    #[Test]
    public function nilReturnsZeroUuid(): void
    {
        $nil = Uuid::nil();

        $this->assertSame('00000000-0000-0000-0000-000000000000', $nil->value());
    }

    #[Test]
    public function maxReturnsMaxUuid(): void
    {
        $max = Uuid::max();

        $this->assertSame('FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF', $max->value());
    }

    #[Test]
    public function equalsReturnsTrueForSameInstance(): void
    {
        $uuid = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertTrue($uuid->equals($uuid));
    }

    #[Test]
    public function equalsReturnsTrueForSameValueDifferentInstance(): void
    {
        $uuid1 = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $uuid2 = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertTrue($uuid1->equals($uuid2));
    }

    #[Test]
    public function equalsReturnsTrueForSameValueDifferentCase(): void
    {
        $uuid1 = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $uuid2 = Uuid::fromString('DF516CBA-FB13-4F45-8335-00252F1B87E2');

        $this->assertTrue($uuid1->equals($uuid2));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentUuid(): void
    {
        $uuid1 = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $uuid2 = Uuid::fromString('00000000-0000-0000-0000-000000000000');

        $this->assertFalse($uuid1->equals($uuid2));
    }

    #[Test]
    public function equalsAcceptsStringArgument(): void
    {
        $uuid = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertTrue($uuid->equals('df516cba-fb13-4f45-8335-00252f1b87e2'));
        $this->assertTrue($uuid->equals('DF516CBA-FB13-4F45-8335-00252F1B87E2'));
        $this->assertFalse($uuid->equals('00000000-0000-0000-0000-000000000000'));
    }

    #[Test]
    public function valueReturnsOriginalString(): void
    {
        $uuidString = 'df516cba-fb13-4f45-8335-00252f1b87e2';
        $uuid = Uuid::fromString($uuidString);

        $this->assertSame($uuidString, $uuid->value());
    }

    #[Test]
    public function toStringReturnsUuidString(): void
    {
        $uuidString = 'df516cba-fb13-4f45-8335-00252f1b87e2';
        $uuid = Uuid::fromString($uuidString);

        $this->assertSame($uuidString, $uuid->toString());
    }

    #[Test]
    public function magicToStringWorksInStringContext(): void
    {
        $uuidString = 'df516cba-fb13-4f45-8335-00252f1b87e2';
        $uuid = Uuid::fromString($uuidString);

        $this->assertSame($uuidString, (string) $uuid);
        $this->assertSame("UUID: $uuidString", "UUID: $uuid");
    }

    #[Test]
    public function toBinaryReturns16Bytes(): void
    {
        $uuid = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $binary = $uuid->toBinary();

        $this->assertSame(16, strlen($binary));
    }

    #[Test]
    public function toBinaryAndFromBinaryAreIdempotent(): void
    {
        $original = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $binary = $original->toBinary();
        $restored = Uuid::fromBinary($binary);

        $this->assertTrue($original->equals($restored));
    }

    #[Test]
    #[DataProvider('provideUuidVersions')]
    public function getVersionReturnsCorrectVersion(string $uuid, int $expectedVersion): void
    {
        $uuidObj = Uuid::fromString($uuid, false);

        $this->assertSame($expectedVersion, $uuidObj->getVersion());
    }

    public static function provideUuidVersions(): array
    {
        return [
            'v1' => ['f47ac10b-58cc-1e82-a567-0e02b2c3d479', 1],
            'v3' => ['a3bb189e-8bf9-3888-9912-ace4e6543002', 3],
            'v4' => ['df516cba-fb13-4f45-8335-00252f1b87e2', 4],
            'v5' => ['74738ff5-5367-5958-9aee-98fffdcd1876', 5],
            'v6' => ['1ef47ac1-0b58-6c82-a567-0e02b2c3d479', 6],
            'v7' => ['018f47ac-10b5-7c82-a567-0e02b2c3d479', 7],
            'nil' => ['00000000-0000-0000-0000-000000000000', 0],
            'max' => ['ffffffff-ffff-ffff-ffff-ffffffffffff', 15],
        ];
    }

    #[Test]
    public function validateReturnsTrueForValidUuid(): void
    {
        $uuid = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');

        $this->assertTrue($uuid->validate());
    }

    #[Test]
    public function validateReturnsTrueForNilUuid(): void
    {
        $uuid = Uuid::nil();

        $this->assertTrue($uuid->validate());
    }

    #[Test]
    public function validateReturnsTrueForMaxUuid(): void
    {
        $uuid = Uuid::max();

        $this->assertTrue($uuid->validate());
    }

    #[Test]
    public function namespaceConstantsAreDefined(): void
    {
        $this->assertSame('6ba7b810-9dad-11d1-80b4-00c04fd430c8', Uuid::NAMESPACE_DNS);
        $this->assertSame('6ba7b811-9dad-11d1-80b4-00c04fd430c8', Uuid::NAMESPACE_URL);
        $this->assertSame('6ba7b812-9dad-11d1-80b4-00c04fd430c8', Uuid::NAMESPACE_OID);
        $this->assertSame('6ba7b814-9dad-11d1-80b4-00c04fd430c8', Uuid::NAMESPACE_X500);
    }

    #[Test]
    public function fromUuidCreatesNewInstanceWithSameValue(): void
    {
        $original = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $copy = Uuid::fromUuid($original);

        $this->assertNotSame($original, $copy);
        $this->assertTrue($original->equals($copy));
        $this->assertSame($original->value(), $copy->value());
    }

    #[Test]
    public function fromUuidWorksWithSubclasses(): void
    {
        $baseUuid = Uuid::fromString('df516cba-fb13-4f45-8335-00252f1b87e2');
        $subclassUuid = TestUuidSubclass::fromUuid($baseUuid);

        $this->assertInstanceOf(TestUuidSubclass::class, $subclassUuid);
        $this->assertSame($baseUuid->value(), $subclassUuid->value());
    }
}

class TestUuidSubclass extends Uuid
{
}
