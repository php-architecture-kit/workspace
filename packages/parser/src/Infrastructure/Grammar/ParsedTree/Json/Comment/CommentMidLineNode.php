<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Comment;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class CommentMidLineNode extends SequenceNode
{
    public GroupAttribute $trivia0 { get => $this->attributes[0]; }

    public StructureAttribute $asterisk { get => $this->attributes[1]; }

    public OptionalRawAttribute $leadingWs { get => $this->attributes[2]; }

    public RawContentAttribute $content { get => $this->attributes[3]; }

    /** @var GroupAttribute<InlineWsNode|TrailingWsNode|LeadingWsNode|EmptyLineNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[4]; }

    public static function create(string $content): self
    {
        return new self(
            name: 'commentMidLine',
            origin: NodeOrigin::Sequence,
            attributes: [
                new GroupAttribute('trivia0', []),
                new StructureAttribute(true, 'asterisk', '*'),
                new RawContentAttribute($content),
                new GroupAttribute('trivia1', []),
            ],
            parent: null,
        );
    }

    public function getRawContent(): string
    {
        return $this->content->content;
    }

    public function setRawContent(string $value): self
    {
        $this->content->content = $value;
        return $this;
    }
}
