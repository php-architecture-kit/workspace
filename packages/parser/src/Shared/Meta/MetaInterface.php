<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Shared\Meta;

interface MetaInterface
{
    public function getMeta(string $key, mixed $default = null): mixed;

    /**
     * @param array<string,mixed> $meta
     */
    public function initMeta(array $meta): static;

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
