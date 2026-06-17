<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model;

use Closure;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;

final readonly class CompiledGrammar
{
    /**
     * @param array<string,CompiledRegion> $regions
     * @param array<Closure(NodeInterface $rootNode):void> $contextInitializers
     * @param array<Closure(NodeInterface $node, string $style):void> $formatters
     * @param array<string,class-string> $nodeClassMap
     */
    public function __construct(
        public string $name,
        public ?string $variant,
        public bool $requireBofEof,
        public string $rootRegionName,
        public array $regions,
        public array $contextInitializers,
        public array $formatters,
        public array $nodeClassMap = [],
        public string $globalRegionName = 'global',
    ) {}
}
