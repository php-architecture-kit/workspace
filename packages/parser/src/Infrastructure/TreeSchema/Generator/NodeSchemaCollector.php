<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawSequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\AbstractNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Exception\AmbiguousAttributeNameException;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\AttributeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\NodeSchema;
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
        // The facade extends the same shape the runtime materializes (LeafNode /
        // GroupNode / SequenceNode), and reuses its NodeOrigin in create().
        $schema->baseClass = $node::class;
        if ($node instanceof AbstractNode) {
            $schema->nodeOrigin = $node->origin;
        }
        $attributes = $node->getAttributes();

        // A GroupNode (Region|Node, no root sequence — Table 2) has no fixed slots: the
        // same name can legitimately repeat any number of times (e.g. two `emptyLine`
        // siblings under `global`). Only a SequenceNode's fixed, positionally-addressed
        // slots must be unique — the facade exposes those by name at a fixed index, so two
        // attributes resolving to the same name there (e.g. two bare `?asterisk/s` slots)
        // would silently collapse and drop an index; fail loudly instead.
        $isGroupShape = $node instanceof GroupNode;
        $seenPropNames = [];

        foreach ($attributes as $index => $attribute) {
            $propName = $this->getPropName($attribute);
            if ($this->isReservedName($propName)) {
                continue;
            }

            if (!$isGroupShape) {
                if (isset($seenPropNames[$propName])) {
                    throw AmbiguousAttributeNameException::forNode($name, $propName, $seenPropNames[$propName], $index);
                }
                $seenPropNames[$propName] = $index;
            }

            if (!isset($schema->attributes[$propName])) {
                $schema->attributes[$propName] = new AttributeSchema($propName, $attribute::class, $index);
            }

            $attrSchema = $schema->attributes[$propName];

            // Keep the highest observed index — optional attributes (e.g. SequenceAttribute)
            // are absent in some nodes, shifting indices of following attributes.
            if ($index > $attrSchema->index) {
                $attrSchema->index = $index;
            }

            // Prefer the richer attribute type (SequenceAttribute > GroupAttribute).
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
            $attribute instanceof SequenceAttribute  => $this->mergeSequenced($schema, $attribute),
            $attribute instanceof GroupAttribute     => $this->mergeGroup($schema, $attribute),
            $attribute instanceof NodeAttribute      => $this->mergeNodeAttr($schema, $attribute),
            $attribute instanceof OptionalAttribute  => $this->mergeOptional($schema, $attribute),
            $attribute instanceof RawRegionAttribute => $this->mergeRawRegion($schema, $attribute),
            $attribute instanceof RawContentAttribute => $this->mergeRawContent($schema, $attribute),
            $attribute instanceof RawSequenceAttribute => $this->mergeRawSequence($schema, $attribute),
            // StructureAttribute carries no topology to discover here — its fixed
            // literal content is resolved later by GrammarAugmentor from the grammar's
            // own Defaults, never from a parsed sample (see GrammarLiteralResolver).
            default => null,
        };
    }

    private function mergeNodeAttr(AttributeSchema $schema, NodeAttribute $attr): void
    {
        $nodeName = $attr->node->getName();
        if ($this->isValidNodeName($nodeName) && !in_array($nodeName, $schema->unionNodeNames, true)) {
            $schema->unionNodeNames[] = $nodeName;
        }

        $alternatives = $attr->getMeta('alternatives');
        if (is_array($alternatives) && !empty($alternatives) && empty($schema->choicesList)) {
            $schema->choicesList = $alternatives;
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

        $alternatives = $attr->getMeta('alternatives');
        if (is_array($alternatives) && !empty($alternatives) && empty($schema->choicesList)) {
            $schema->choicesList = $alternatives;
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

        $alternatives = $attr->getMeta('alternatives');
        if (is_array($alternatives) && !empty($alternatives) && empty($schema->choicesList)) {
            $schema->choicesList = $alternatives;
        }
    }

    private function mergeSequenced(AttributeSchema $schema, SequenceAttribute $attr): void
    {
        foreach ($attr->attributes as $child) {
            // Content vs structural is taken from the `/c` marker stamped at compile
            // time (deterministic), not guessed from a representative sample.
            $isContent = method_exists($child, 'hasTag')
                && $child->hasTag(SequenceAttribute::CONTENT_TAG);

            if ($isContent) {
                if ($child instanceof NodeAttribute && $schema->groupedContentNodeName === null) {
                    $alternatives = $child->getMeta('alternatives');
                    if (is_array($alternatives) && !empty($alternatives)) {
                        $schema->groupedContentIsChoice = true;
                        $schema->groupedContentAttrName = $child->getName();
                        if (empty($schema->groupedChoicesList)) {
                            $schema->groupedChoicesList = $alternatives;
                        }
                        $nodeName = $child->node->getName();
                        if ($this->isValidNodeName($nodeName) && !in_array($nodeName, $schema->unionNodeNames, true)) {
                            $schema->unionNodeNames[] = $nodeName;
                        }
                    } else {
                        $schema->groupedContentNodeName = $child->node->getName();
                        $schema->groupedContentAttrName = $child->getName();
                        $schema->groupedContentIsChoice = false;
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

    private function mergeRawRegion(AttributeSchema $schema, RawRegionAttribute $attr): void
    {
        // opener/closer content is resolved later by GrammarAugmentor from the
        // grammar's own Defaults, not from this sample's matched text.
        $schema->rawTokenName = $attr->name;

        $alternatives = $attr->getMeta('alternatives');
        if (is_array($alternatives) && !empty($alternatives) && empty($schema->choicesList)) {
            $schema->choicesList = $alternatives;
        }
    }

    private function mergeRawSequence(AttributeSchema $schema, RawSequenceAttribute $attr): void
    {
        // A Raw-typed region/choice collapses to one RawSequenceAttribute (parts joined).
        // Exposed in the facade as a single string, like RawContentAttribute.
        $schema->rawTokenName = $attr->name;
    }

    private function mergeRawContent(AttributeSchema $schema, RawContentAttribute $attr): void
    {
        $schema->rawTokenName = $attr->name;

        $alternatives = $attr->getMeta('alternatives');
        if (is_array($alternatives) && !empty($alternatives) && empty($schema->choicesList)) {
            $schema->choicesList = $alternatives;
        }
    }

    private function descend(NodeAttributeInterface $attribute): void
    {
        match (true) {
            $attribute instanceof NodeAttribute     => $this->walk($attribute->node),
            $attribute instanceof OptionalAttribute => $attribute->node !== null ? $this->walk($attribute->node) : null,
            $attribute instanceof GroupAttribute    => $this->walkGroup($attribute),
            $attribute instanceof SequenceAttribute => $this->walkSequenced($attribute),
            default => null,
        };
    }

    private function walkGroup(GroupAttribute $attr): void
    {
        foreach ($attr->nodes as $node) {
            $this->walk($node);
        }
    }

    private function walkSequenced(SequenceAttribute $attr): void
    {
        foreach ($attr->attributes as $child) {
            if ($child instanceof NodeAttribute) {
                $this->walk($child->node);
            } elseif ($child instanceof GroupAttribute) {
                $this->walkGroup($child);
            }
        }
    }

    private function preferredClass(string $existing, string $incoming): string
    {
        // SequenceAttribute > GroupAttribute (richer structural information wins)
        if ($incoming === SequenceAttribute::class) {
            return SequenceAttribute::class;
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
}
