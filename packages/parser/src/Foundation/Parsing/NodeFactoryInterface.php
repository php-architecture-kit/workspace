<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing;

use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

interface NodeFactoryInterface
{
    public function fromToken(Token $token, NodeInterface $parent): NodeInterface;
    public function fromTokenRegion(TokenRegion $region, ?NodeInterface $parent): NodeInterface;
    public function fromMatchedRegion(MatchedRegion $region, NodeInterface $parent): NodeInterface;
    public function fromMatchedSequence(MatchedSequence $matchedSequence, NodeInterface $parent): NodeInterface;
}
