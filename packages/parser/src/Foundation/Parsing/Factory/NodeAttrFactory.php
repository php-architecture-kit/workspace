<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Factory;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\NodeAttrFactoryInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\ParsingContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\MatchedSequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;

class NodeAttrFactory implements NodeAttrFactoryInterface
{
    public function __construct(
        private ParsingContext $context,
    ) {}

    public function fromToken(Token $token, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        $nodeParent = $parent instanceof GroupedAttribute ? $parent->parent : $parent;

        $attribute = match ($nodeType) {
            NodeType::Node => new NodeAttribute($token->name, $this->context->nodeFactory()->fromToken($token, $nodeParent), $token->meta, $token->tags),
            NodeType::Structure => new StructureAttribute(true, $token->name, $token->raw === '' ? null : $token->raw, $token->meta, $token->tags),
            NodeType::Raw => new RawContentAttribute($token->raw, $token->name, null, $token->meta, $token->tags),
        };

        $parent->addAttribute($attribute);
    }

    public function fromTokenRegion(TokenRegion $region, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        $nodeParent = $parent instanceof GroupedAttribute ? $parent->parent : $parent;

        $attribute = match ($nodeType) {
            NodeType::Node => new NodeAttribute($region->name, $this->context->nodeFactory()->fromTokenRegion($region, $nodeParent), $region->meta, $region->tags),
            NodeType::Structure => new StructureAttribute(true, $region->name, ($content = $region->__toString()) === '' ? null : $content, $region->meta, $region->tags),
            NodeType::Raw => $this->createRawRegionAttribute($region, null),
        };

        $parent->addAttribute($attribute);
    }

    public function fromMatchedRegion(MatchedRegion $region, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        $nodeParent = $parent instanceof GroupedAttribute ? $parent->parent : $parent;

        $attribute = match ($nodeType) {
            NodeType::Node => new NodeAttribute($region->name, $this->context->nodeFactory()->fromMatchedRegion($region, $nodeParent), $region->meta, $region->tags),
            NodeType::Structure => new StructureAttribute(true, $region->name, ($content = $region->__toString()) === '' ? null : $content, $region->meta, $region->tags),
            NodeType::Raw => $this->createRawRegionAttribute($region, null),
        };

        $parent->addAttribute($attribute);
    }

    public function fromMatchedSequence(MatchedSequence $matchedSequence, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        $nodeParent = $parent instanceof GroupedAttribute ? $parent->parent : $parent;

        $attribute = match ($nodeType) {
            NodeType::Node => new NodeAttribute($matchedSequence->name, $this->context->nodeFactory()->fromMatchedSequence($matchedSequence, $nodeParent), $matchedSequence->meta, $matchedSequence->tags),
            NodeType::Structure => new StructureAttribute(true, $matchedSequence->name, ($content = $matchedSequence->__toString()) === '' ? null : $content, $matchedSequence->meta, $matchedSequence->tags),
            NodeType::Raw => new RawContentAttribute($matchedSequence->__toString(), $matchedSequence->name, null, $matchedSequence->meta, $matchedSequence->tags),
        };

        $parent->addAttribute($attribute);
    }

    public function fromMatchedSequenceNode(MatchedSequenceNode $sequenceNode, NodeType $nodeType, NodeInterface|GroupedAttribute $parent): void
    {
        if ($nodeType === NodeType::Skip) {
            return;
        }

        if ($nodeType === NodeType::Structure) {
            $content = $sequenceNode->__toString();

            $parent->addAttribute(new StructureAttribute(
                !empty($sequenceNode->items),
                $sequenceNode->name,
                $content === '' ? null : $content,
                $sequenceNode->meta,
                $sequenceNode->tags,
            ));

            return;
        }

        if ($nodeType === NodeType::Raw) {
            $countItems = count($sequenceNode->items);
            $firstItem = $sequenceNode->items[0] ?? null;
            $parent->addAttribute(
                match (true) {
                    $countItems === 1 && $firstItem instanceof Token => new RawContentAttribute($firstItem->raw, $firstItem->name, $sequenceNode->name, $firstItem->meta, $firstItem->tags),
                    $countItems === 1 && $firstItem instanceof TokenRegion => $this->createRawRegionAttribute($firstItem, $sequenceNode->name),
                    default => new RawContentAttribute(
                        $sequenceNode->__toString(),
                        implode('', array_map(static fn(Token|TokenRegion|MatchedSequence $item) => $item->__toString(), $sequenceNode->items)),
                        $sequenceNode->name,
                        $sequenceNode->meta,
                        $sequenceNode->tags,
                    )
                },
            );

            return;
        }

        $nodeParent = $parent instanceof GroupedAttribute ? $parent->parent : $parent;

        if ($sequenceNode->max > 1) {
            $nodes = [];
            foreach ($sequenceNode->items as $item) {
                $nodes[] = match ($item::class) {
                    Token::class => $this->context->nodeFactory()->fromToken($item, $nodeParent),
                    TokenRegion::class => $this->context->nodeFactory()->fromTokenRegion($item, $nodeParent),
                    MatchedSequence::class => $this->context->nodeFactory()->fromMatchedSequence($item, $nodeParent),
                    default => throw new InvalidArgumentException('Unknown item type'),
                };
            }

            $parent->addAttribute(new GroupAttribute(
                $sequenceNode->name,
                $nodes,
                $sequenceNode->meta,
                $sequenceNode->tags,
            ));

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
                $sequenceNode->name,
                $node,
                $sequenceNode->meta,
                $sequenceNode->tags,
            ));

            return;
        }

        assert($node instanceof NodeInterface);

        $parent->addAttribute(new NodeAttribute(
            $sequenceNode->name,
            $node,
            $sequenceNode->meta,
            $sequenceNode->tags,
        ));
    }

    private function createRawRegionAttribute(MatchedRegion|TokenRegion $region, ?string $anchorName): RawRegionAttribute
    {
        $items = $region instanceof MatchedRegion ? $region->items : $region->stream->tokens;
        $opener = null;
        $closer = null;
        if (empty($items)) {
            return new RawRegionAttribute($opener, $closer, '', $region->name, $anchorName, $region->meta, $region->tags);
        }

        // opener
        if (
            $region->getMeta(StartRegionEventListener::KEY_CAUSED_BY_EVENT) instanceof TokenMatchedEvent &&
            $items[0]->hasTag(NodeType::Structure->value)
        ) {
            $firstItem = array_shift($items);
            $firstItemContent = $firstItem->__toString();
            $opener = new StructureAttribute(
                $firstItemContent !== '',
                $firstItem->name,
                $firstItemContent === '' ? null : $firstItemContent,
                $firstItem->meta,
                $firstItem->tags,
            );
        }

        if (empty($items)) {
            return new RawRegionAttribute($opener, $closer, '', $region->name, $anchorName, $region->meta, $region->tags);
        }

        // closer
        $lastItemIndex = array_key_last($items);
        if (
            $region->getMeta(EndRegionEventListener::KEY_CAUSED_BY_EVENT) instanceof TokenAddedEvent &&
            $items[$lastItemIndex]->hasTag(NodeType::Structure->value)
        ) {
            $lastItem = array_pop($items);
            $lastItemContent = $lastItem->__toString();
            $closer = new StructureAttribute(
                $lastItemContent !== '',
                $lastItem->name,
                $lastItemContent === '' ? null : $lastItemContent,
                $lastItem->meta,
                $lastItem->tags,
            );
        }

        return new RawRegionAttribute(
            $opener,
            $closer,
            implode('', array_map(static fn(Token|TokenRegion|MatchedSequence $item) => $item->__toString(), $items)),
            $region->name,
            $anchorName,
            $region->meta,
            $region->tags,
        );
    }
}
