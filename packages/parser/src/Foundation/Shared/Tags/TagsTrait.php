<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Shared\Tags;

trait TagsTrait
{
    /**
     * @var string[]
     */
    public private(set) array $tags = [];

    public function addTag(string ...$tags): static
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags);
    }

    public function removeTag(string ...$tags): static
    {
        $this->tags = array_filter($this->tags, static fn(string $t): bool => !in_array($t, $tags));

        return $this;
    }

    /** @param string[] $tags */
    public function replaceTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @param callable(string $value):bool $filter
     * @return string[]
     */
    public function getAllTags(?callable $filter = null): array
    {
        return array_filter($this->tags, $filter);
    }

    /**
     * @param callable(string $value):bool $filter `true` - remove, `false` - keep
     */
    public function clearTags(?callable $filter = null): static
    {
        if ($filter === null) {
            $this->tags = [];

            return $this;
        }

        foreach ($this->tags as $key => $value) {
            if ($filter($value)) {
                unset($this->tags[$key]);
            }
        }

        return $this;
    }
}
