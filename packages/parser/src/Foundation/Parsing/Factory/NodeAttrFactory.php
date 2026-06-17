<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Factory;

use InvalidArgumentException;
use LogicException;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\NodeAttrFactoryInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Resolver\NodeTypeResolver;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\ParsingContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawGroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawSequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

class NodeAttrFactory implements NodeAttrFactoryInterface
{
    public function __construct(
        private ParsingContext $context,
    ) {}

    public function fillTokenBasedNodeWithAttributes(NodeInterface $tokenBasedNode, Token $token): void
    {
        $tokenBasedNode->addAttribute(
            new RawContentAttribute(
                $token->raw,
                RawContentAttribute::DEFAULT_NAME,
            ),
        );
    }

    public function fillRegionBasedNodeWithAttributes(NodeInterface $regionBasedNode, TokenRegion|MatchedRegion $region): void
    {
        $items = $region instanceof MatchedRegion ? $region->items : $region->stream->tokens;
        foreach ($items as $item) {
            $nodeType = NodeTypeResolver::resolveNodeType($item);

            match ($item::class) {
                Token::class => $this->fromToken($item, $nodeType, $regionBasedNode),
                TokenRegion::class => $this->fromTokenRegion($item, $nodeType, $regionBasedNode),
                MatchedSequence::class => $this->fromMatchedSequence($item, $nodeType, $regionBasedNode),
                default => throw new InvalidArgumentException('Unknown item type'),
            };
        }
    }

    public function fillSequenceBasedNodeWithAttributes(NodeInterface $sequenceBasedNode, MatchedSequence $sequence): void
    {
        $sequenceAttr = null;
        $rawParts = null;
        $rawMeta = [];
        $rawTags = [];

        foreach ($sequence->items as $item) {
            if ($item->hasTag(RawContentAttribute::TAG)) {
                $sequenceAttr = null;
                if ($rawParts === null) {
                    $rawParts = [];
                    $rawMeta = $item->meta;
                    $rawTags = $item->tags;
                }
                $rawParts[] = $item->__toString();
                continue;
            }

            if ($rawParts !== null) {
                $this->flushRawGroup($sequenceBasedNode, $rawParts, $rawMeta, $rawTags);
                $rawParts = null;
            }

            if (!$item->hasTag(SequenceAttribute::TAG)) {
                $sequenceAttr = null;
                $this->fromMatchedSequenceNode($item, $sequenceBasedNode);
                continue;
            }

            if ($sequenceAttr === null) {
                $name = $item->hasMeta(SequenceAttribute::ANCHOR_NAME_META_KEY)
                    ? $item->getMeta(SequenceAttribute::ANCHOR_NAME_META_KEY)
                    : SequenceAttribute::DEFAULT_NAME;
                $sequenceAttr = new SequenceAttribute($name, $sequenceBasedNode, [], $item->meta, $item->tags);
                $sequenceBasedNode->addAttribute($sequenceAttr);
            }

            $this->fromMatchedSequenceNode($item, $sequenceAttr);
        }

        if ($rawParts !== null) {
            $this->flushRawGroup($sequenceBasedNode, $rawParts, $rawMeta, $rawTags);
        }
    }

    /**
     * @param string[] $parts
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    private function flushRawGroup(NodeInterface $parent, array $parts, array $meta, array $tags): void
    {
        $anchorName = $meta[SequenceAttribute::ANCHOR_NAME_META_KEY] ?? null;

        $parent->addAttribute(new RawContentAttribute(
            implode('', $parts),
            RawContentAttribute::DEFAULT_NAME,
            $anchorName,
            $meta,
            $tags,
        ));
    }

    public function fromToken(Token $token, NodeType $nodeType, NodeInterface|SequenceAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        $nodeParent = $parent instanceof SequenceAttribute ? $parent->parent : $parent;

        $attribute = match ($nodeType) {
            NodeType::Node => new NodeAttribute($token->name, $this->context->nodeFactory()->fromToken($token, $nodeParent), $token->meta, $token->tags),
            NodeType::Structure => new StructureAttribute(true, $token->name, $token->raw === '' ? null : $token->raw, $token->meta, $token->tags),
            NodeType::Raw => new RawContentAttribute($token->raw, $token->name, null, $token->meta, $token->tags),
        };

        $parent->addAttribute($attribute);
    }

    public function fromTokenRegion(TokenRegion $region, NodeType $nodeType, NodeInterface|SequenceAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        $nodeParent = $parent instanceof SequenceAttribute ? $parent->parent : $parent;

        $attribute = match ($nodeType) {
            NodeType::Node => new NodeAttribute($region->name, $this->context->nodeFactory()->fromTokenRegion($region, $nodeParent), $region->meta, $region->tags),
            NodeType::Structure => new StructureAttribute(true, $region->name, ($content = $region->__toString()) === '' ? null : $content, $region->meta, $region->tags),
            NodeType::Raw => $this->createRawRegionAttribute($region, null),
        };

        $parent->addAttribute($attribute);
    }

    public function fromMatchedRegion(MatchedRegion $region, NodeType $nodeType, NodeInterface|SequenceAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        $nodeParent = $parent instanceof SequenceAttribute ? $parent->parent : $parent;

        $attribute = match ($nodeType) {
            NodeType::Node => new NodeAttribute($region->name, $this->context->nodeFactory()->fromMatchedRegion($region, $nodeParent), $region->meta, $region->tags),
            NodeType::Structure => new StructureAttribute(true, $region->name, ($content = $region->__toString()) === '' ? null : $content, $region->meta, $region->tags),
            NodeType::Raw => $this->createRawRegionAttribute($region, null),
        };

        $parent->addAttribute($attribute);
    }

    public function fromMatchedSequence(MatchedSequence $matchedSequence, NodeType $nodeType, NodeInterface|SequenceAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        $nodeParent = $parent instanceof SequenceAttribute ? $parent->parent : $parent;

        $attribute = match ($nodeType) {
            NodeType::Node => new NodeAttribute($matchedSequence->name, $this->context->nodeFactory()->fromMatchedSequence($matchedSequence, $nodeParent), $matchedSequence->meta, $matchedSequence->tags),
            NodeType::Structure => new StructureAttribute(true, $matchedSequence->name, ($content = $matchedSequence->__toString()) === '' ? null : $content, $matchedSequence->meta, $matchedSequence->tags),
            NodeType::Raw => $this->createRawSequenceAttribute($matchedSequence, null),
        };

        $parent->addAttribute($attribute);
    }

    public function fromMatchedSequenceNode(MatchedSequenceNode $sequenceNode, NodeInterface|SequenceAttribute $parent): void
    {
        $nodeType = NodeTypeResolver::resolveNodeType($sequenceNode);

        if ($nodeType === NodeType::Skip) {
            return;
        }

        if ($nodeType === NodeType::Structure) {
            $content = $sequenceNode->__toString();

            $parent->addAttribute(
                new StructureAttribute(
                    !empty($sequenceNode->items),
                    $sequenceNode->getName(),
                    $content === '' ? null : $content,
                    $sequenceNode->getComprehensiveMeta(),
                    $sequenceNode->tags,
                )
            );

            return;
        }

        if ($nodeType === NodeType::Raw) {
            if ($sequenceNode->max > 1) {
                $raws = [];
                foreach ($sequenceNode->items as $item) {
                    $raws[] = match ($item::class) {
                        Token::class => new RawContentAttribute($item->raw, $item->name, $sequenceNode->anchorName, $item->meta, $item->tags),
                        TokenRegion::class => $this->createRawRegionAttribute($item, $sequenceNode->anchorName),
                        MatchedSequence::class => $this->createRawSequenceAttribute($item, $sequenceNode->anchorName),
                        default => throw new InvalidArgumentException('Unsupported item type'),
                    };
                }

                $parent->addAttribute(
                    new RawGroupAttribute(
                        $raws,
                        $sequenceNode->getName(),
                        $sequenceNode->anchorName,
                        $sequenceNode->getComprehensiveMeta(),
                        $sequenceNode->tags,
                    )
                );

                return;
            }

            $raw = empty($sequenceNode->items)
                ? null
                : match ($sequenceNode->items[0]::class) {
                    Token::class => new RawContentAttribute($sequenceNode->items[0]->raw, $sequenceNode->getName(), $sequenceNode->anchorName, $sequenceNode->getComprehensiveMeta(), $sequenceNode->items[0]->tags),
                    TokenRegion::class => $this->createRawRegionAttribute($sequenceNode->items[0], $sequenceNode->anchorName, $sequenceNode->getComprehensiveMeta()),
                    MatchedSequence::class => $this->createRawSequenceAttribute($sequenceNode->items[0], $sequenceNode->anchorName, $sequenceNode->getComprehensiveMeta()),
                    default => throw new InvalidArgumentException('Unsupported item type'),
                };

            if ($sequenceNode->min === 0) {
                $parent->addAttribute(
                    new OptionalRawAttribute(
                        $raw,
                        $sequenceNode->getName(),
                        $sequenceNode->anchorName,
                        $sequenceNode->getComprehensiveMeta(),
                        $sequenceNode->tags,
                    )
                );

                return;
            }

            assert($raw instanceof RawAttributeInterface);
            $parent->addAttribute($raw);

            return;
        }

        $nodeParent = $parent instanceof SequenceAttribute ? $parent->parent : $parent;

        if ($sequenceNode->max > 1) {
            $nodes = [];
            foreach ($sequenceNode->items as $item) {
                $nodes[] = match ($item::class) {
                    Token::class => $this->context->nodeFactory()->fromToken($item, $nodeParent),
                    TokenRegion::class => $this->context->nodeFactory()->fromTokenRegion($item, $nodeParent),
                    MatchedSequence::class => $this->context->nodeFactory()->fromMatchedSequence($item, $nodeParent),
                    default => throw new InvalidArgumentException('Unsupported item type'),
                };
            }

            $parent->addAttribute(
                new GroupAttribute(
                    $sequenceNode->getName(),
                    $nodes,
                    $sequenceNode->getComprehensiveMeta(),
                    $sequenceNode->tags,
                )
            );

            return;
        }

        $node = empty($sequenceNode->items) ? null : match ($sequenceNode->items[0]::class) {
            Token::class => $this->context->nodeFactory()->fromToken($sequenceNode->items[0], $nodeParent),
            TokenRegion::class => $this->context->nodeFactory()->fromTokenRegion($sequenceNode->items[0], $nodeParent),
            MatchedSequence::class => $this->context->nodeFactory()->fromMatchedSequence($sequenceNode->items[0], $nodeParent),
            default => throw new InvalidArgumentException(
                'Unknown item type: `' . $sequenceNode->items[0]::class
                    . '`. Expected Token, TokenRegion or MatchedSequence.',
            ),
        };

        if ($sequenceNode->min === 0) {
            $parent->addAttribute(new OptionalAttribute(
                $sequenceNode->getName(),
                $node,
                $sequenceNode->getComprehensiveMeta(),
                $sequenceNode->tags,
            ));

            return;
        }

        assert($node instanceof NodeInterface);

        $parent->addAttribute(new NodeAttribute(
            $sequenceNode->getName(),
            $node,
            $sequenceNode->getComprehensiveMeta(),
            $sequenceNode->tags,
        ));
    }

    private function createRawRegionAttribute(MatchedRegion|TokenRegion $region, ?string $anchorName, ?array $overrideMeta = null): RawRegionAttribute
    {
        $items = $region instanceof MatchedRegion ? $region->items : $region->stream->tokens;
        $meta = $overrideMeta ?? $region->meta;
        $opener = null;
        $closer = null;
        if (empty($items)) {
            return new RawRegionAttribute($opener, null, $closer, $region->name, $anchorName, $meta, $region->tags);
        }

        // opener
        if (
            $region->getMeta(StartRegionEventListener::KEY_CAUSED_BY_EVENT) instanceof TokenMatchedEvent &&
            $items[0]->hasTag(NodeType::Structure->value)
        ) {
            $firstItem = array_shift($items);
            $firstItemContent = $firstItem->__toString();
            $opener = $firstItemContent === '' ? null : $firstItemContent;
        }

        if (empty($items)) {
            return new RawRegionAttribute($opener, null, $closer, $region->name, $anchorName, $meta, $region->tags);
        }

        // closer
        $lastItemIndex = array_key_last($items);
        if (
            $region->getMeta(EndRegionEventListener::KEY_CAUSED_BY_EVENT) instanceof TokenAddedEvent &&
            $items[$lastItemIndex]->hasTag(NodeType::Structure->value)
        ) {
            $lastItem = array_pop($items);
            $lastItemContent = $lastItem->__toString();
            $closer = $lastItemContent === '' ? null : $lastItemContent;
        }

        $content = implode('', array_map(static fn(Token|TokenRegion|MatchedSequence $item) => $item->__toString(), $items));

        return new RawRegionAttribute(
            $opener,
            $content ?: null,
            $closer,
            $region->name,
            $anchorName,
            $meta,
            $region->tags,
        );
    }

    private function createRawSequenceAttribute(MatchedSequence $sequence, ?string $anchorName, ?array $overrideMeta = null): RawSequenceAttribute
    {
        $meta = $overrideMeta ?? $sequence->meta;

        $parts = [];
        foreach ($sequence->items as $item) {
            $name = $item->getName();
            if (empty($name)) {
                throw new LogicException("Sequence node name can't be empty.");
            }

            $origName = $name;
            $i = 0;
            while (isset($parts[$name])) {
                $name = $origName . ++$i;
            }

            $parts[$name] = $item->__toString();
        }

        return new RawSequenceAttribute(
            $parts,
            $sequence->name,
            $anchorName,
            $meta,
            $sequence->tags,
        );
    }
}
