<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

use PhpArchitecture\Parser\Grammar\Definition\RuleDefinition;
use PhpArchitecture\Parser\Grammar\Definition\RuleType;

class Rule
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly RuleType $type,
        public RuleDefinition $definition,
        public array $tags = []
    ) {}
}
