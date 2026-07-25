<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class CommentEmptyLineNode extends SequenceNode
{
    /** @var GroupAttribute<LeadingWsNode|EmptyLineNode|TrailingWsNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[0]; }

    public StructureAttribute $asterisk { get => $this->attributes[1]; }

    /** @var GroupAttribute<InlineWsNode|EmptyLineNode|TrailingWsNode|LeadingWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[2]; }

    public static function create(): self
    {
        return new self(
            name: 'commentEmptyLine',
            origin: NodeOrigin::Sequence,
            attributes: [
                new GroupAttribute('trivia0', []),
                new StructureAttribute(true, 'asterisk', '*'),
                new GroupAttribute('trivia1', []),
            ],
            parent: null,
        );
    }
}
