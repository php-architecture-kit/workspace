<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use LogicException;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template\ClassStmtTemplate;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template\DocblockTemplate;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template\NamespaceStmtTemplate;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template\PhpClassFileTemplate;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template\PropertyTemplate;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template\TypeRef;

final class TreeSchemaGenerator
{
    private const RESERVED_NAMES = ['parent', 'attributes', 'meta', 'tags'];

    /** @var array<string, PhpClassFileTemplate> keyed by node name */
    private array $classes = [];

    /** @var array<int, true> spl_object_id => visited in current run */
    private array $visited = [];
    private string $namespace = '';

    /**
     * @return array<string, PhpClassFileTemplate>
     */
    public function generate(NodeInterface $nodeTree, string $namespace): array
    {
        $this->namespace = $namespace;
        $this->visited   = [];
        $this->processNode($nodeTree);
        return $this->classes;
    }

    private function processNode(NodeInterface $node): string
    {
        $id   = spl_object_id($node);
        $fqcn = $this->namespace . '\\' . $this->toClassName($node->getName());

        if (isset($this->visited[$id])) {
            return $fqcn;
        }
        $this->visited[$id] = true;

        $nodeName = $node->getName();
        if (!isset($this->classes[$nodeName])) {
            $this->classes[$nodeName] = new PhpClassFileTemplate(
                new NamespaceStmtTemplate($this->namespace),
                new ClassStmtTemplate(
                    $this->toClassName($nodeName),
                    Node::class,
                    new DocblockTemplate([]),
                ),
            );
        }

        $docblock = $this->classes[$nodeName]->classStmt->docblock;
        foreach ($node->getAttributes() as $attribute) {
            $docblock->upsertProperty($this->buildProperty($attribute));
        }

        return $fqcn;
    }

    private function buildProperty(NodeAttributeInterface $attribute): PropertyTemplate
    {
        if (in_array($attribute->getName(), self::RESERVED_NAMES, true)) {
            throw new LogicException(
                "Attribute name '{$attribute->getName()}' is reserved by Node and cannot be used as a property name.",
            );
        }

        return new PropertyTemplate(
            $attribute->getName(),
            $attribute::class,
            $this->buildContainerTypeRefs($attribute),
        );
    }

    /** @return TypeRef[] — type params for <...> of this attribute */
    private function buildContainerTypeRefs(NodeAttributeInterface $attribute): array
    {
        return match (true) {
            $attribute instanceof NodeAttribute     => [new TypeRef($this->processNode($attribute->node))],
            $attribute instanceof OptionalAttribute => $attribute->node !== null
                ? [new TypeRef($this->processNode($attribute->node))]
                : [],
            $attribute instanceof GroupAttribute    => array_values(array_unique(array_map(
                fn(NodeInterface $n) => new TypeRef($this->processNode($n)),
                $attribute->nodes,
            ))),
            $attribute instanceof ChoiceAttribute   => $attribute->selected !== null
                ? $this->buildItemTypeRefs($attribute->selected)
                : [],
            $attribute instanceof GroupedAttribute  => array_values(array_merge(
                ...array_map(
                    fn(NodeAttributeInterface $a) => $this->buildItemTypeRefs($a),
                    $attribute->attributes,
                ) ?: [[]],
            )),
            default => [],
        };
    }

    /** @return TypeRef[] — TypeRef(s) representing this attribute as an item inside a container */
    private function buildItemTypeRefs(NodeAttributeInterface $attribute): array
    {
        return match (true) {
            $attribute instanceof NodeAttribute     => [new TypeRef($this->processNode($attribute->node))],
            $attribute instanceof OptionalAttribute => $attribute->node !== null
                ? [new TypeRef($this->processNode($attribute->node))]
                : [],
            $attribute instanceof GroupAttribute    => array_values(array_unique(array_map(
                fn(NodeInterface $n) => new TypeRef($this->processNode($n)),
                $attribute->nodes,
            ))),
            $attribute instanceof ChoiceAttribute,
            $attribute instanceof GroupedAttribute  => [
                new TypeRef($attribute::class, $this->buildContainerTypeRefs($attribute)),
            ],
            default => [new TypeRef($attribute::class)],
        };
    }

    private function toClassName(string $nodeName): string
    {
        $parts = preg_split('/[-_\s]+/', $nodeName) ?: [$nodeName];
        return implode('', array_map('ucfirst', $parts)) . 'Node';
    }
}
