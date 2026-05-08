<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Model;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;
use Stringable;

final class Token implements Stringable
{
    use MetaTrait;
    use TagsTrait;

    public const TOKEN_BOF = 'bof';
    public const TOKEN_EOF = 'eof';
    public const TOKEN_UNKNOWN = 'unknown';

    public const RESERVED_TOKEN_NAMES = [
        self::TOKEN_BOF,
        self::TOKEN_EOF,
        self::TOKEN_UNKNOWN,
    ];

    public function __construct(
        public readonly string $name,
        public readonly string $raw,
        public readonly int $startPosition,
        public readonly int $endPosition,
    ) {}

    public static function bof(): self
    {
        return new self(
            name: self::TOKEN_BOF,
            raw: '',
            startPosition: 0,
            endPosition: 0,
        );
    }

    public static function default(
        string $name,
        string $raw,
        int $startPosition,
        int $endPosition,
    ): self {
        return new self(
            name: $name,
            raw: $raw,
            startPosition: $startPosition,
            endPosition: $endPosition,
        );
    }

    public static function eof(int $position): self
    {
        return new self(
            name: self::TOKEN_EOF,
            raw: '',
            startPosition: $position,
            endPosition: $position,
        );
    }

    public static function unknown(string $character, int $position): self
    {
        if (strlen($character) !== 1) {
            throw new InvalidArgumentException("The unknown token must a representation of exactly one character. " . strlen($character) . " characters long given.");
        }

        return new self(
            name: self::TOKEN_UNKNOWN,
            raw: $character,
            startPosition: $position,
            endPosition: $position,
        );
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}
