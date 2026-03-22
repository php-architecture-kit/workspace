<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

use PhpArchitecture\Parser\Grammar\Definition\Regex\RegexRule;
use PhpArchitecture\Parser\Grammar\Definition\RuleDefinition;
use PhpArchitecture\Parser\Grammar\Definition\RuleType;

class Rule
{
    public private(set) int $priority = 0;

    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly RuleType $type,
        public RuleDefinition $definition,
        public array $tags = []
    ) {}

    /** @param string[] $tags */
    public static function token(
        string $name,
        string $token,
        array $tags = []
    ): self {
        return new self(
            $name,
            RuleType::Token,
            RegexRule::fromString(preg_quote($token, '~')),
            $tags
        );
    }

    /** @param string[] $tags */
    public static function keyword(
        string $keyword,
        bool $caseSensitive = false,
        ?string $name = null,
        array $tags = [],
    ): self {
        return new self(
            $name ?? $keyword,
            RuleType::Keyword,
            RegexRule::fromString(preg_quote($keyword, '~'), $caseSensitive),
            $tags,
        );
    }

    /** @param string[] $tags */
    public static function expr(
        string $name,
        string $expression,
        bool $caseSensitive = false,
        array $tags = [],
    ): self {
        return new self(
            $name,
            RuleType::Expression,
            RegexRule::fromString($expression, $caseSensitive),
            $tags,
        );
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function addTag(string ...$tags): self
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    public function removeTag(string ...$tags): self
    {
        $this->tags = array_filter($this->tags, static fn(string $t): bool => !in_array($t, $tags));

        return $this;
    }

    /** @param string[] $tags */
    public function replaceTags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function startRegion(string $name): Region
    {
        return new Region($name, new RegionConfig(openRule: $this));
    }
}
