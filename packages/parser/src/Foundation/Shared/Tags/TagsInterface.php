<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Shared\Tags;

interface TagsInterface
{
    public function addTag(string ...$tags): static;

    public function hasTag(string $tag): bool;

    public function removeTag(string ...$tags): static;

    /** @param string[] $tags */
    public function replaceTags(array $tags): static;

    /**
     * @param callable(string $value):bool $filter
     * @return string[]
     */
    public function getAllTags(?callable $filter = null): array;

    /**
     * @param callable(string $value):bool $filter `true` - remove, `false` - keep
     */
    public function clearTags(?callable $filter = null): static;
}
