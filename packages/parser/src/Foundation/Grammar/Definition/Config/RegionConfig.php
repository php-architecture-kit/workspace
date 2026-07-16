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

        // meta
        /** @var string[] */
        public array $possibleNames = [],

        /**
         * Per-tag override of which of $possibleNames a given tag on this region
         * actually covers. Without an entry here, a tag resolves (via
         * TagToChoiceCompiler) to this region's own name — meaning every tag on the
         * region is an indistinguishable synonym for "this region, in any of its
         * possible runtime-renamed forms". Declare an entry when a tag is meant to
         * pick out only a subset of $possibleNames (e.g. Whitespace's '-t'/'-l',
         * which should resolve to the runtime rename targets that keep that tag
         * after TokenRegionEndedEvent's per-instance rename/removeTag, not to every
         * possible whitespace sub-kind).
         *
         * @var array<string,string[]>
         */
        public array $possibleNamesByTag = [],

        // pratt
        public ?string $prattGroupedRegionName = null,
        public bool $isPrattAtom = false,
    ) {}
}
