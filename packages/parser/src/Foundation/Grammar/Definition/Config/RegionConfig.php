<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Config;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Definition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class RegionConfig
{
    public function __construct(
        // open
        public ?EventSubscriber $opener = null,

        // close
        public ?EventSubscriber $closer = null,

        // root sequence
        public ?SequenceRule $rootSequence = null,

        // inheritance
        public int $inheritanceFromGlobal = Region::NONE,
        public int $inheritanceFromAncestor = Region::NONE,

        // inner grammar
        public ?Grammar $innerGrammar = null,
        public ?bool $retokenizeWithInnerGrammar = null,
        public ?bool $innerGrammarMergeOverrideSource = null,
        public ?int $innerGrammarMergeScope = null,
        public ?int $innerGrammarMergeMiddlewaresScope = null,

        // parsing
        public NodeType $nodeType = NodeType::Node,
        public ?Definition $definition = null,
    ) {}
}
