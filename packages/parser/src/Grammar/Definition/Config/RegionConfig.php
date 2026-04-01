<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Config;

use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Grammar\Definition\Region;

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
    ) {}
}
