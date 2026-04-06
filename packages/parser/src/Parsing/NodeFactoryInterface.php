<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing;

use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

interface NodeFactoryInterface
{
    public function fromToken(Token $token, NodeInterface $parent): NodeInterface;
    public function fromTokenRegion(TokenRegion $region, ?NodeInterface $parent): NodeInterface;
    public function fromMatchedRegion(MatchedRegion $region, NodeInterface $parent): NodeInterface;
    public function fromMatchedSequence(MatchedSequence $matchedSequence, NodeInterface $parent): NodeInterface;
}
