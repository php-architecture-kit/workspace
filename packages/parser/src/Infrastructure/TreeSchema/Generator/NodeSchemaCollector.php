<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\AttributeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\NodeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\RawChoiceInfo;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\StructuralFactoryInfo;

/**
 * Walks a parse tree and accumulates NodeSchema objects.
 * Call collect() for each parsed file to merge results; then getSchemas().
 */
final class NodeSchemaCollector
{
    /** @var array<string, NodeSchema> keyed by node name */
    private array $schemas = [];

    /** @var array<int, true> spl_object_id of visited nodes to avoid cycles */
    private array $visited = [];

    public function collect(NodeInterface $root): void
    {
        $this->visited = [];
        $this->walk($root);
    }

    /** @return array<string, NodeSchema> */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    private function walk(NodeInterface $node): void
    {
        $id = spl_object_id($node);
        if (isset($this->visited[$id])) {
            return;
        }
        $this->visited[$id] = true;

        $name = $node->getName();

        if (!$this->isValidNodeName($name)) {
            return;
        }

        $className = $this->toClassName($name);
        if (!isset($this->schemas[$name])) {
            $this->schemas[$name] = new NodeSchema($name, $className);
        }

        $schema = $this->schemas[$name];
        $attributes = $node->getAttributes();

        foreach ($attributes as $index => $attribute) {
            $propName = $this->getPropName($attribute);
            if ($this->isReservedName($propName)) {
                continue;
            }

            if (!isset($schema->attributes[$propName])) {
                $schema->attributes[$propName] = new AttributeSchema($propName, $attribute::class, $index);
            }

            $attrSchema = $schema->attributes[$propName];

            // Keep the highest observed index — optional attributes (e.g. GroupedAttribute)
            // are absent in some nodes, shifting indices of following attributes.
            if ($index > $attrSchema->index) {
                $attrSchema->index = $index;
            }

            // Prefer the richer attribute type (GroupedAttribute > GroupAttribute).
            if ($attrSchema->attrClass !== $attribute::class) {
                $attrSchema->attrClass = $this->preferredClass($attrSchema->attrClass, $attribute::class);
            }

            $this->mergeAttribute($attrSchema, $attribute);
            $this->descend($attribute);
        }

        uasort($schema->attributes, static fn(AttributeSchema $a, AttributeSchema $b) => $a->index - $b->index);
    }

    private function mergeAttribute(AttributeSchema $schema, NodeAttributeInterface $attribute): void
    {
        match (true) {
            $attribute instanceof GroupedAttribute => $this->mergeGrouped($schema, $attribute),
            $attribute instanceof GroupAttribute   => $this->mergeGroup($schema, $attribute),
            $attribute instanceof ChoiceAttribute  => $this->mergeChoice($schema, $attribute),
            $attribute instanceof NodeAttribute    => $this->mergeNodeAttr($schema, $attribute),
            $attribute instanceof OptionalAttribute => $this->mergeOptional($schema, $attribute),
            $attribute instanceof RawRegionAttribute => $this->mergeRawRegion($schema, $attribute),
            $attribute instanceof RawContentAttribute => $this->mergeRawContent($schema, $attribute),
            $attribute instanceof StructureAttribute => $this->mergeStructure($schema, $attribute),
            default => null,
        };
    }

    private function mergeNodeAttr(AttributeSchema $schema, NodeAttribute $attr): void
    {
        $nodeName = $attr->node->getName();
        if ($this->isValidNodeName($nodeName) && !in_array($nodeName, $schema->unionNodeNames, true)) {
            $schema->unionNodeNames[] = $nodeName;
        }
    }

    private function mergeOptional(AttributeSchema $schema, OptionalAttribute $attr): void
    {
        if ($attr->node !== null) {
            $nodeName = $attr->node->getName();
            if ($this->isValidNodeName($nodeName) && !in_array($nodeName, $schema->unionNodeNames, true)) {
                $schema->unionNodeNames[] = $nodeName;
            }
        }
    }

    private function mergeGroup(AttributeSchema $schema, GroupAttribute $attr): void
    {
        foreach ($attr->nodes as $node) {
            $nodeName = $node->getName();
            if ($this->isValidNodeName($nodeName) && !in_array($nodeName, $schema->unionNodeNames, true)) {
                $schema->unionNodeNames[] = $nodeName;
            }
        }
    }

    private function mergeChoice(AttributeSchema $schema, ChoiceAttribute $attr): void
    {
        if (empty($schema->choicesList)) {
            $schema->choicesList = $attr->choices;
        }

        if ($attr->selected === null) {
            return;
        }

        if ($attr->selected instanceof NodeAttribute) {
            $nodeName = $attr->selected->node->getName();
            if ($this->isValidNodeName($nodeName) && !in_array($nodeName, $schema->unionNodeNames, true)) {
                $schema->unionNodeNames[] = $nodeName;
            }
        } elseif ($attr->selected instanceof RawContentAttribute || $attr->selected instanceof RawRegionAttribute) {
            $this->mergeRawChoice($schema, $attr->selected);
        }
    }

    private function mergeRawChoice(AttributeSchema $schema, RawContentAttribute $raw): void
    {
        $tokenName = $raw->name;
        foreach ($schema->rawChoices as $existing) {
            if ($existing->tokenName === $tokenName) {
                return;
            }
        }

        $isKeyword = !($raw instanceof RawRegionAttribute) && ($raw->content === $raw->name);
        $keywordContent = $isKeyword ? $raw->content : null;
        $hasOpener = $raw instanceof RawRegionAttribute && $raw->opener !== null;

        $schema->rawChoices[] = new RawChoiceInfo(
            caseName: $this->toCaseName($tokenName),
            tokenName: $tokenName,
            isKeyword: $isKeyword,
            keywordContent: $keywordContent,
            hasOpener: $hasOpener,
            openerContent: $hasOpener ? ($raw instanceof RawRegionAttribute ? $raw->opener?->content : null) : null,
            closerContent: ($raw instanceof RawRegionAttribute && $raw->closer !== null) ? $raw->closer->content : null,
        );
    }

    private function mergeGrouped(AttributeSchema $schema, GroupedAttribute $attr): void
    {
        $anchorName = $attr->getName();

        foreach ($attr->attributes as $child) {
            $isMeta = ($child instanceof MetaInterface)
                && $child->getMeta(GroupedAttribute::ANCHOR_NAME_META_KEY) === $anchorName;

            if ($isMeta) {
                // content element
                if ($child instanceof NodeAttribute && $schema->groupedContentNodeName === null) {
                    $schema->groupedContentNodeName = $child->node->getName();
                    $schema->groupedContentAttrName = $child->getName();
                    $schema->groupedContentIsChoice = false;
                } elseif ($child instanceof ChoiceAttribute && $schema->groupedContentNodeName === null) {
                    $schema->groupedContentIsChoice = true;
                    $schema->groupedContentAttrName = $child->getName();
                    if (empty($schema->groupedChoicesList)) {
                        $schema->groupedChoicesList = $child->choices;
                    }
                    if ($child->selected instanceof NodeAttribute) {
                        $nodeName = $child->selected->node->getName();
                        if ($this->isValidNodeName($nodeName) && !in_array($nodeName, $schema->unionNodeNames, true)) {
                            $schema->unionNodeNames[] = $nodeName;
                        }
                    }
                }
            } else {
                // structural element
                $structName = $child->getName();
                $alreadyExists = false;
                foreach ($schema->structuralFactories as $f) {
                    if ($f->name === $structName) {
                        $alreadyExists = true;
                        break;
                    }
                }

                if (!$alreadyExists) {
                    if ($child instanceof StructureAttribute) {
                        $schema->structuralFactories[] = new StructuralFactoryInfo(
                            name: $structName,
                            attrClass: StructureAttribute::class,
                            content: $child->content,
                        );
                    } elseif ($child instanceof GroupAttribute) {
                        $schema->structuralFactories[] = new StructuralFactoryInfo(
                            name: $structName,
                            attrClass: GroupAttribute::class,
                            content: null,
                        );
                    }
                }
            }
        }
    }

    private function mergeStructure(AttributeSchema $schema, StructureAttribute $attr): void
    {
        if ($schema->structureContent === null) {
            $schema->structureContent = $attr->content;
        }
    }

    private function mergeRawRegion(AttributeSchema $schema, RawRegionAttribute $attr): void
    {
        $schema->rawTokenName = $attr->name;
        if ($schema->rawRegionOpenerContent === null && $attr->opener !== null) {
            $schema->rawRegionOpenerContent = $attr->opener->content;
            $schema->rawRegionOpenerName    = $attr->opener->name;
            $schema->rawRegionCloserContent = $attr->closer?->content;
        }
    }

    private function mergeRawContent(AttributeSchema $schema, RawContentAttribute $attr): void
    {
        $schema->rawTokenName     = $attr->name;
        $schema->rawDefaultContent ??= $attr->content;
    }

    private function descend(NodeAttributeInterface $attribute): void
    {
        match (true) {
            $attribute instanceof NodeAttribute    => $this->walk($attribute->node),
            $attribute instanceof OptionalAttribute => $attribute->node !== null ? $this->walk($attribute->node) : null,
            $attribute instanceof GroupAttribute   => $this->walkGroup($attribute),
            $attribute instanceof ChoiceAttribute  => $this->walkChoice($attribute),
            $attribute instanceof GroupedAttribute => $this->walkGrouped($attribute),
            default => null,
        };
    }

    private function walkGroup(GroupAttribute $attr): void
    {
        foreach ($attr->nodes as $node) {
            $this->walk($node);
        }
    }

    private function walkChoice(ChoiceAttribute $attr): void
    {
        if ($attr->selected instanceof NodeAttribute) {
            $this->walk($attr->selected->node);
        }
    }

    private function walkGrouped(GroupedAttribute $attr): void
    {
        foreach ($attr->attributes as $child) {
            if ($child instanceof NodeAttribute) {
                $this->walk($child->node);
            } elseif ($child instanceof ChoiceAttribute && $child->selected instanceof NodeAttribute) {
                $this->walk($child->selected->node);
            } elseif ($child instanceof GroupAttribute) {
                $this->walkGroup($child);
            }
        }
    }

    private function preferredClass(string $existing, string $incoming): string
    {
        // GroupedAttribute > GroupAttribute (richer structural information wins)
        if ($incoming === GroupedAttribute::class) {
            return GroupedAttribute::class;
        }
        return $existing;
    }

    private function getPropName(NodeAttributeInterface $attr): string
    {
        $name = ($attr instanceof RawContentAttribute)
            ? ($attr->anchorName ?? $attr->name)
            : $attr->getName();

        return $this->sanitizePropName($name);
    }

    private function sanitizePropName(string $name): string
    {
        // Parser may produce names like "space|tab|asterisk|commentContent" for collapsed alternatives.
        if (str_contains($name, '|')) {
            $name = explode('|', $name)[0];
        }
        // Strip characters that are invalid in PHP identifiers.
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? $name;
        if ($name === '' || ctype_digit($name[0])) {
            $name = 'raw';
        }
        return $name;
    }

    private function isValidNodeName(string $name): bool
    {
        if ($name === '' || $name === '-') {
            return false;
        }
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        return $clean !== '' && $clean !== 'Node';
    }

    private function isReservedName(string $name): bool
    {
        return in_array($name, ['parent', 'attributes', 'meta', 'tags'], true);
    }

    public function toClassName(string $nodeName): string
    {
        $parts = preg_split('/[-_\s]+/', $nodeName) ?: [$nodeName];
        $result = implode('', array_map('ucfirst', $parts));
        $result = preg_replace('/[^a-zA-Z0-9]/', '', $result) ?? $result;
        if ($result !== '' && ctype_digit($result[0])) {
            $result = 'Ver' . $result;
        }
        return $result . 'Node';
    }

    private function toCaseName(string $tokenName): string
    {
        $parts = preg_split('/[-_\s]+/', $tokenName) ?: [$tokenName];
        return implode('', array_map('ucfirst', $parts));
    }
}
