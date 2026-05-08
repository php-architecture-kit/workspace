<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\ParseTree;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
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
                name: $node->name,
                tags: $this->domainTags($node->getAllTags()),
                meta: $this->safeMeta($node->meta),
                children: [],
                content: $node->content,
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
            static fn(string $t) => !str_starts_with($t, 'NodeType.'),
        ));
    }

    private const INTERNAL_META_KEYS = ['parentRegion', 'renamedFrom', 'streamReplacedFrom'];

    /**
     * @param array<string,mixed> $meta
     * @return array<string,scalar>
     */
    private function safeMeta(array $meta): array
    {
        $safe = [];
        foreach ($meta as $key => $value) {
            if (in_array($key, self::INTERNAL_META_KEYS, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_array($value)) {
                $safe[$key] = json_encode($value) ?: '';
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $safe[$key] = (string) $value;
            } elseif (is_object($value) && property_exists($value, 'name')) {
                $safe[$key] = $value->name;
            }
        }
        return $safe;
    }
}
