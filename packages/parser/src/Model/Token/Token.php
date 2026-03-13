<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Token;

use InvalidArgumentException;
use PhpArchitecture\Parser\Model\MetaTrait;

final class Token implements TokenInterface
{
    use MetaTrait;

    /**
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $raw,
    ) {}

    public static function default(
        string $name,
        string $raw,
    ): self {
        return new self(
            name: $name,
            raw: $raw,
        );
    }

    public static function bof(): self
    {
        return new self(
            name: self::TOKEN_BOF,
            raw: '',
        );
    }

    public static function eof(): self
    {
        return new self(
            name: self::TOKEN_EOF,
            raw: '',
        );
    }

    public static function unknown(string $content): self
    {
        if (strlen($content) !== 1) {
            throw new InvalidArgumentException("The unknown token must a representation of exactly one character. " . strlen($content) . " characters long given.");
        }

        return new self(
            name: self::TOKEN_UNKNOWN,
            raw: $content,
        );
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function __toString(): string
    {
        return $this->raw();
    }
}
