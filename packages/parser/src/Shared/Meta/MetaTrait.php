<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Shared\Meta;

trait MetaTrait
{
    /**
     * @var array<string,mixed>
     */
    private array $meta = [];

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function setMeta(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    public function hasMeta(string $key): bool
    {
        return isset($this->meta[$key]);
    }

    public function removeMeta(string $key): void
    {
        unset($this->meta[$key]);
    }

    /**
     * @param callable(mixed $value, string $key):bool $filter
     * @return string[]
     */
    public function getMetaKeys(?callable $filter = null): array
    {
        return array_filter(array_keys($this->meta), $filter, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param callable(mixed $value, string $key):bool $filter
     * @return array<string,mixed>
     */
    public function getMetaAll(?callable $filter = null): array
    {
        return array_filter($this->meta, $filter, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param callable(mixed $value, string $key):bool $filter `true` - remove, `false` - keep
     */
    public function clearMeta(?callable $filter = null): void
    {
        if ($filter === null) {
            $this->meta = [];
            return;
        }

        foreach ($this->meta as $key => $value) {
            if ($filter($value, $key)) {
                unset($this->meta[$key]);
            }
        }
    }
}
