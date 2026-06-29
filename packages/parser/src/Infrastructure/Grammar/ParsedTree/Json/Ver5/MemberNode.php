<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Ver5;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class MemberNode extends SequenceNode
{
    public RawAttributeInterface $identifier { get => $this->attributes[0]; }

    /** @var GroupAttribute<InlineWsNode|BlockCommentNode|EmptyLineNode|TrailingWsNode|LeadingWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[1]; }

    public StructureAttribute $colon { get => $this->attributes[2]; }

    /** @var GroupAttribute<TrailingWsNode|InlineWsNode|BlockCommentNode|LeadingWsNode|EmptyLineNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[3]; }

    /** @var NodeAttribute<PrimitiveNode|ArrayNode|ObjectNode> */
    public NodeAttribute $value { get => $this->attributes[4]; }

    public static function create(IdentifierType $identifierType, string $identifier, PrimitiveNode|ArrayNode|ObjectNode $value): self
    {
        $node = new self(
            name: 'member',
            origin: NodeOrigin::Sequence,
            attributes: [
                self::makeIdentifier($identifierType, $identifier),
                new GroupAttribute('trivia0', []),
                new StructureAttribute(true, 'colon', ':'),
                new GroupAttribute('trivia1', []),
                NodeAttribute::fromNode($value),
            ],
            parent: null,
        );
        $value->setParent($node);

        return $node;
    }

    private static function makeIdentifier(IdentifierType $identifierType, ?string $identifier = null): RawAttributeInterface
    {
        if ($identifierType === IdentifierType::NonQuotedIdentifier) {
            if ($identifier === null) {
                throw new InvalidArgumentException('Content is required for type: ' . $identifierType->value);
            }
            return new RawContentAttribute($identifier, 'nonQuotedIdentifier', null);
        }

        if ($identifierType === IdentifierType::DoubleQuotedString) {
            if ($identifier === null) {
                throw new InvalidArgumentException('Content is required for type: ' . $identifierType->value);
            }
            return new RawRegionAttribute(opener: '"', content: $identifier, closer: '"', name: 'doubleQuotedString', anchorName: null);
        }

        if ($identifierType === IdentifierType::SingleQuotedString) {
            if ($identifier === null) {
                throw new InvalidArgumentException('Content is required for type: ' . $identifierType->value);
            }
            return new RawRegionAttribute(opener: '\'', content: $identifier, closer: '\'', name: 'singleQuotedString', anchorName: null);
        }

        throw new InvalidArgumentException('Unsupported type: ' . $identifierType->value);
    }

    public function setIdentifier(IdentifierType $identifierType, ?string $identifier = null): self
    {
        $this->attributes[0] = self::makeIdentifier($identifierType, $identifier);
        return $this;
    }

    public function getIdentifierType(): IdentifierType|null
    {
        return IdentifierType::tryFrom($this->identifier->name)
            ?? IdentifierType::tryFrom((string) ($this->identifier->content ?? ''));
    }

    public function getIdentifierContent(): string|null
    {
        return $this->identifier->content;
    }

    public function getNodeValue(): PrimitiveNode|ArrayNode|ObjectNode
    {
        /** @var PrimitiveNode|ArrayNode|ObjectNode $node */
        $node = $this->value->node;
        return $node;
    }

    public function setNodeValue(PrimitiveNode|ArrayNode|ObjectNode $value): self
    {
        $this->attributes[4] = NodeAttribute::fromNode($value->setParent($this));
        return $this;
    }
}
