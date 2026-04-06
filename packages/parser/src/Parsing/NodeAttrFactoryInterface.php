<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing;

use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

interface NodeAttrFactoryInterface
{
    public function fromToken(Token $token, NodeType $nodeType, NodeInterface $parent): void;
    public function fromTokenRegion(TokenRegion $region, NodeType $nodeType, NodeInterface $parent): void;
    public function fromMatchedRegion(MatchedRegion $region, NodeType $nodeType, NodeInterface $parent): void;
    public function fromMatchedSequence(MatchedSequence $matchedSequence, NodeType $nodeType, NodeInterface $parent): void;
    public function fromMatchedSequenceNode(MatchedSequenceNode $sequenceNode, NodeType $nodeType, NodeInterface $parent): void;
}
