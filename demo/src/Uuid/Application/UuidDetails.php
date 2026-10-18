<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Uuid\Application;

use PhpArchitecture\Uuid\Foundation\Uuid;

final readonly class UuidDetails
{
    public function __construct(
        public string $call,
        public string $value,
        public int $version,
        public ?string $time,
        public ?int $clockSequence,
        public ?string $node,
        public string $binaryHex,
        public bool $valid,
        public ?string $provider,
    ) {
    }

    public static function of(Uuid $uuid, string $call, ?string $provider): self
    {
        return new self(
            call: $call,
            value: $uuid->value(),
            version: $uuid->getVersion(),
            time: $uuid->getTime()?->format('Y-m-d\TH:i:s.uP'),
            clockSequence: $uuid->getClockSequence(),
            node: $uuid->getNode(),
            binaryHex: bin2hex($uuid->toBinary()),
            valid: $uuid->validate(),
            provider: $provider,
        );
    }
}
