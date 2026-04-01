<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Parsing;

use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

interface NodeFactoryInterface
{
    public function fromToken(Token $token): NodeInterface;
    public function fromTokenRegion(TokenRegion $region): NodeInterface;
    public function fromMatchedRegion(MatchedRegion $region): NodeInterface;
    public function fromMatchedSequence(MatchedSequence $matchedSequence): NodeInterface;
    public function fromMatchedSequenceNode(MatchedSequenceNode $matchedSequenceNode): NodeInterface;
}
