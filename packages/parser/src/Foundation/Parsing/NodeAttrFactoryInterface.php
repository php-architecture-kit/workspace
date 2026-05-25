<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing;

use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

interface NodeAttrFactoryInterface
{
    public function fromToken(Token $token, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void;
    public function fromTokenRegion(TokenRegion $region, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void;
    public function fromMatchedRegion(MatchedRegion $region, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void;
    public function fromMatchedSequence(MatchedSequence $matchedSequence, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void;
    public function fromMatchedSequenceNode(MatchedSequenceNode $sequenceNode, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void;
}
