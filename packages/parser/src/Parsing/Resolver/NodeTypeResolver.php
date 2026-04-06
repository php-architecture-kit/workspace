<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Resolver;

use PhpArchitecture\Parser\Processing\Model\Matching\MatchedRegion;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\MatchedSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;

final class NodeTypeResolver
{
    public static function resolveNodeType(Token|TokenRegion|MatchedRegion|MatchedSequence|MatchedSequenceNode $item): NodeType
    {
        return match (true) {
            in_array(NodeType::Node->value, $item->tags) => NodeType::Node,
            in_array(NodeType::Structure->value, $item->tags) => NodeType::Structure,
            in_array(NodeType::Raw->value, $item->tags) => NodeType::Raw,
            default => $item instanceof MatchedSequence ? NodeType::Node : NodeType::Raw,
        };
    }
}
