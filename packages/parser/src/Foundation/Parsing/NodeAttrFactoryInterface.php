<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing;

use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

interface NodeAttrFactoryInterface
{
    /** @param array<Token|TokenRegion|MatchedSequence> $items */
    public function fillRegionBasedNodeWithAttributes(NodeInterface $regionBasedNode, NodeType $regionNodeType, array $items): void;

    /** @param array<MatchedSequenceNode> $items */
    public function fillSequenceBasedNodeWithAttributes(NodeInterface $sequenceBasedNode, array $items): void;
}
