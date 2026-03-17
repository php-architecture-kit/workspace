<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Token;

use Stringable;

interface TokenInterface extends Stringable
{
    public const TOKEN_BOF = 'bof';
    public const TOKEN_EOF = 'eof';
    public const TOKEN_UNKNOWN = 'unknown';

    public const RESERVED_TOKEN_NAMES = [
        self::TOKEN_BOF,
        self::TOKEN_EOF,
        self::TOKEN_UNKNOWN,
    ];

    public function name(): string;
    public function raw(): string;

    public function getMeta(string $key): mixed;
    public function setMeta(string $key, mixed $value): void;
    public function hasMeta(string $key): bool;
    public function removeMeta(string $key): void;

    /**
     * @param callable(mixed $value, string $key):bool $filter
     * @return string[]
     */
    public function getMetaKeys(?callable $filter = null): array;

    /**
     * @param callable(mixed $value, string $key):bool $filter
     * @return array<string,mixed>
     */
    public function getMetaAll(?callable $filter = null): array;

    /**
     * @param callable(mixed $value, string $key):bool $filter `true` - remove, `false` - keep
     */
    public function clearMeta(?callable $filter = null): void;
}
