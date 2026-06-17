<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\ParseTree;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Cardinality;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\OptionalRawAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawGroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawSequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Presentation\View\ParseTree\DTO\ParseNodeViewData;
use PhpArchitecture\Parser\Presentation\View\ParseTree\DTO\ParseTreeViewData;

final class ParseTreeViewFactory
{
    public function fromNode(NodeInterface $root): ParseTreeViewData
    {
        return new ParseTreeViewData(
            root: $this->convert($root),
            rawContent: $root->__toString(),
        );
    }

    private function convert(mixed $node): ParseNodeViewData
    {
        if ($node instanceof Node) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_NODE,
                name: $node->name,
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: array_map($this->convert(...), $node->attributes),
                childCount: count($node->attributes),
                origin: $node->origin->name,
            );
        }

        if ($node instanceof NodeAttribute) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_NODE_ATTR,
                name: $node->name,
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: [$this->convert($node->node)],
            );
        }

        if ($node instanceof GroupAttribute) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_GROUP_ATTR,
                name: $node->name,
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: array_map($this->convert(...), $node->nodes),
                childCount: count($node->nodes),
            );
        }

        if ($node instanceof SequenceAttribute) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_SEQUENCE_ATTR,
                name: $node->name,
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: array_map($this->convert(...), $node->attributes),
                childCount: count($node->attributes),
            );
        }

        if ($node instanceof OptionalAttribute) {
            $children = $node->node !== null ? [$this->convert($node->node)] : [];
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_OPTIONAL_ATTR,
                name: $node->name,
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: $children,
                present: $node->node !== null,
            );
        }

        if ($node instanceof RawRegionAttribute) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_RAW_REGION_ATTR,
                name: $node->name,
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: [],
                content: $node->__toString(),
            );
        }

        if ($node instanceof RawContentAttribute) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_RAW_CONTENT_ATTR,
                name: $node->getName(),
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: [],
                content: $node->content,
            );
        }

        if ($node instanceof RawGroupAttribute) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_RAW_GROUP_ATTR,
                name: $node->getName(),
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: array_map($this->convert(...), $node->raws),
                childCount: count($node->raws),
            );
        }

        if ($node instanceof RawSequenceAttribute) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_RAW_SEQUENCE_ATTR,
                name: $node->getName(),
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: [],
                content: $node->__toString(),
            );
        }

        if ($node instanceof OptionalRawAttribute) {
            $children = $node->raw !== null ? [$this->convert($node->raw)] : [];
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_OPTIONAL_RAW_ATTR,
                name: $node->getName(),
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: $children,
                present: $node->raw !== null,
            );
        }

        if ($node instanceof StructureAttribute) {
            return new ParseNodeViewData(
                type: ParseNodeViewData::TYPE_STRUCTURE_ATTR,
                name: $node->name,
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: [],
                content: $node->content,
                present: $node->present,
                cardinality: $this->cardinalityLabel($node->getMeta('min'), $node->getMeta('max')),
            );
        }

        return new ParseNodeViewData(
            type: 'Unknown',
            name: $node->name ?? 'unknown',
            tags: [],
            meta: [],
            children: [],
        );
    }

    /**
     * @param string[] $tags
     * @return string[]
     */
    private function domainTags(array $tags): array
    {
        return array_values(array_filter(
            $tags,
            static fn(string $t) => !str_starts_with($t, 'NodeType.')
                && $t !== SequenceAttribute::TAG
                && $t !== RawContentAttribute::TAG,
        ));
    }

    private const INTERNAL_META_KEYS = ['parentRegion', 'renamedFrom', 'streamReplacedFrom'];

    /**
     * Keys carrying cardinality/matching bookkeeping that's already implied by the
     * attribute's class (Group* => repeated, Optional* => 0..1, etc.) and would
     * otherwise be duplicated on every single attribute in the tree.
     */
    private const REDUNDANT_META_KEYS = ['alternatives', 'isNegation', 'min', 'max'];

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function safeMeta(array $meta): array
    {
        $safe = [];
        foreach ($meta as $key => $value) {
            if (in_array($key, self::INTERNAL_META_KEYS, true) || in_array($key, self::REDUNDANT_META_KEYS, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_array($value)) {
                $safe[$key] = $value;
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $safe[$key] = (string) $value;
            } elseif (is_object($value) && property_exists($value, 'name')) {
                $safe[$key] = $value->name;
            }
        }
        return $safe;
    }

    /**
     * StructureAttribute is the only attribute type without a dedicated Optional/Group
     * sibling class to carry cardinality, so it's the one place where we still need to
     * surface it explicitly, using the same labels as the Cardinality enum.
     */
    private function cardinalityLabel(mixed $min, mixed $max): ?string
    {
        if (!is_int($min) || !is_int($max)) {
            return null;
        }

        foreach (Cardinality::cases() as $case) {
            if ($case->min() === $min && $case->max() === $max) {
                return $case->value;
            }
        }

        return "{$min}..{$max}";
    }
}
