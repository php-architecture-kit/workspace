<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Model\Technical;

use PhpArchitecture\Parser\Grammar\Definition\Model\RuleDefinition;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;

final class TaggedRule implements RuleDefinition
{
    public private(set) Region $sourceRegionRef;

    public function __construct(
        public readonly string $tag,
    ) {}

    public function setTaggedRulesSourceRegion(Region $region): void
    {
        $this->sourceRegionRef = $region;
    }

    /** @return array<string,Rule> */
    public function getTaggedRules(): array
    {
        return array_filter(
            $this->sourceRegionRef->rules,
            fn(Rule $rule) => in_array($this->tag, $rule->tags),
        );
    }
}
