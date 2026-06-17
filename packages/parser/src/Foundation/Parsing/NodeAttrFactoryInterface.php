<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing;

use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

interface NodeAttrFactoryInterface
{
    public function fillTokenBasedNodeWithAttributes(NodeInterface $tokenBasedNode, Token $token): void;

    public function fillRegionBasedNodeWithAttributes(NodeInterface $regionBasedNode, TokenRegion|MatchedRegion $region): void;

    public function fillSequenceBasedNodeWithAttributes(NodeInterface $sequenceBasedNode, MatchedSequence $sequence): void;
}
