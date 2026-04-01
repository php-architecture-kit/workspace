<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Parsing;

use LogicException;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

class NodeFactory
{
    /**
     * @param array<string,class-string<NodeInterface>> $tokenToNodeClassMap
     * @param array<string,class-string<NodeInterface>> $regionToNodeClassMap
     * @param array<string,class-string<NodeInterface>> $sequenceToNodeClassMap
     * @param array<string,class-string<NodeInterface>> $sequenceNodeToNodeClassMap
     */
    public function __construct(
        private readonly array $tokenToNodeClassMap,
        private readonly array $regionToNodeClassMap,
        private readonly array $sequenceToNodeClassMap,
        private readonly array $sequenceNodeToNodeClassMap,
    ) {}

    public function fromToken(Token $token): NodeInterface
    {
        throw new LogicException("Not implemented");
    }

    public function fromTokenRegion(TokenRegion $region): NodeInterface
    {
        throw new LogicException("Not implemented");
    }

    public function fromMatchedSequence(MatchedSequence $matchedSequence): NodeInterface
    {
        throw new LogicException("Not implemented");
    }

    public function fromMatchedSequenceNode(MatchedSequenceNode $matchedSequenceNode): NodeInterface
    {
        throw new LogicException("Not implemented");
    }
}
