<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Resolver;

use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

final class NodeTypeResolver
{
    public static function resolveNodeType(Token|TokenRegion|MatchedRegion|MatchedSequence|MatchedSequenceNode $item): NodeType
    {
        return match (true) {
            in_array(NodeType::Skip->value, $item->tags) => NodeType::Skip,
            in_array(NodeType::Node->value, $item->tags) => NodeType::Node,
            in_array(NodeType::Structure->value, $item->tags) => NodeType::Structure,
            in_array(NodeType::Raw->value, $item->tags) => NodeType::Raw,
            default => $item instanceof MatchedSequence ? NodeType::Node : NodeType::Raw,
        };
    }
}
