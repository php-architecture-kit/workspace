<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawSequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\GroupNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\AttributeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\NodeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\RawChoiceInfo;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\StructuralFactoryInfo;
use InvalidArgumentException;

/**
 * Renders a NodeSchema into a full PHP facade class string.
 */
final class FacadeClassRenderer
{
    private const I = '    '; // 4-space indent

    /**
     * @param array<string, NodeSchema> $allSchemas
     */
    public function render(NodeSchema $schema, string $namespace, array $allSchemas, string $rootNodeName): string
    {
        $imports = [];
        $body    = '';

        $baseClass = $schema->baseClass ?? Node::class;
        $imports[] = $baseClass;

        // A GroupNode (Region|Node, no root sequence) has no fixed slots — per
        // docs/node-type-origin-cardinality.md ("Facade form"), children aren't typed
        // properties at a fixed index. Instead, each distinct child name gets its own
        // add{Name}()/get{Name}s()/remove{Name}() trio (a GroupAttribute-style API, but
        // on the node itself, since the node IS the group).
        if ($baseClass === GroupNode::class) {
            $propertyHooks = '';
            $createMethod  = $this->renderGroupCreate($schema, $imports);
            $methods       = $this->renderGroupNodeMethods($schema, $allSchemas, $namespace, $imports);
        } else {
            $propertyHooks = $this->renderPropertyHooks($schema, $allSchemas, $namespace, $imports);
            $createMethod  = $this->renderCreate($schema, $allSchemas, $namespace, $imports);
            $methods       = $this->renderMethods($schema, $allSchemas, $namespace, $imports, $rootNodeName);
        }

        $hasGroupedWithParent = $this->nodeHasSequenceAttribute($schema);

        sort($imports);
        $imports = array_unique($imports);

        $useLines = '';
        foreach ($imports as $fqcn) {
            $shortNs = $namespace . '\\';
            if (str_starts_with($fqcn, $shortNs) && !str_contains(substr($fqcn, strlen($shortNs)), '\\')) {
                continue;
            }
            $useLines .= 'use ' . $fqcn . ';' . PHP_EOL;
        }

        $body .= $propertyHooks;
        if ($body !== '' && $createMethod !== '') {
            $body .= PHP_EOL;
        }
        $body .= $createMethod;
        if ($methods !== '') {
            if ($body !== '') {
                $body .= PHP_EOL;
            }
            $body .= $methods;
        }

        $classBody = $body !== '' ? PHP_EOL . $body : '';

        $code  = '<?php' . PHP_EOL . PHP_EOL;
        $code .= 'declare(strict_types=1);' . PHP_EOL . PHP_EOL;
        $code .= 'namespace ' . $namespace . ';' . PHP_EOL . PHP_EOL;
        $code .= $useLines;
        $code .= PHP_EOL;
        $code .= 'class ' . $schema->className . ' extends ' . $this->shortName($baseClass) . PHP_EOL;
        $code .= '{' . $classBody . '}' . PHP_EOL;

        return $code;
    }

    /**
     * @param string[] &$imports
     */
    private function renderPropertyHooks(NodeSchema $schema, array $allSchemas, string $namespace, array &$imports): string
    {
        $lines = '';
        foreach ($schema->attributes as $attr) {
            if ($attr->isChoiceRaw()) {
                // A raw-choice slot may hold RawRegionAttribute or RawContentAttribute
                // depending on the matched variant; type it by the shared interface.
                $attrShort = 'RawAttributeInterface';
                $imports[] = RawAttributeInterface::class;
            } else {
                $attrShort = $this->shortName($attr->attrClass);
                $imports[] = $attr->attrClass;
            }

            $docblock = $this->buildDocblock($attr, $allSchemas, $namespace, $imports);
            if ($docblock !== '') {
                $lines .= self::I . $docblock . PHP_EOL;
            }
            $lines .= self::I . 'public ' . $attrShort . ' $' . $attr->propName . ' { get => $this->attributes[' . $attr->index . ']; }' . PHP_EOL;
            $lines .= PHP_EOL;
        }
        return rtrim($lines, PHP_EOL) . PHP_EOL;
    }

    private function buildDocblock(AttributeSchema $attr, array $allSchemas, string $namespace, array &$imports): string
    {
        $unionTypes = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);

        if ($attr->isGroupAttribute() && !empty($unionTypes)) {
            return '/** @var ' . $this->shortName($attr->attrClass) . '<' . implode('|', $unionTypes) . '> */';
        }

        if ($attr->isSequenceAttribute()) {
            $all = [];
            if ($attr->groupedContentIsChoice) {
                $choiceTypes = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);
                if (!empty($choiceTypes)) {
                    $imports[] = NodeAttribute::class;
                    $all[] = 'NodeAttribute<' . implode('|', $choiceTypes) . '>';
                }
            } else {
                if ($attr->groupedContentNodeName !== null) {
                    $cn = $this->resolveClassName($attr->groupedContentNodeName, $allSchemas, $namespace, $imports);
                    $all[] = $cn;
                }
            }

            foreach ($attr->structuralFactories as $sf) {
                $sfShort = $this->shortName($sf->attrClass);
                $imports[] = $sf->attrClass;
                if (!in_array($sfShort, $all, true)) {
                    $all[] = $sfShort;
                }
            }

            // The content node types are already expressed inside NodeAttribute<…>
            // (choice) or via groupedContentNodeName (single) above — re-appending the
            // bare union here would duplicate them in the docblock.

            if (!empty($all)) {
                return '/** @var ' . $this->shortName($attr->attrClass) . '<' . implode('|', $all) . '> */';
            }
            return '';
        }

        if ($attr->isChoiceNodes() && !empty($unionTypes)) {
            return '/** @var ' . $this->shortName($attr->attrClass) . '<' . implode('|', $unionTypes) . '> */';
        }

        if ($attr->isChoiceRaw()) {
            return '';
        }

        return '';
    }

    /**
     * Minimal create() for a GroupNode-shaped node: cardinality is 0..∞ with no fixed
     * named slots, so there is nothing to type per-attribute — the caller populates
     * children via the inherited addAttribute()/getByName() API after construction.
     *
     * @param string[] &$imports
     */
    private function renderGroupCreate(NodeSchema $schema, array &$imports): string
    {
        $imports[] = NodeAttributeInterface::class;
        $imports[] = NodeOrigin::class;

        $body  = self::I . '/** @param NodeAttributeInterface[] $attributes */' . PHP_EOL;
        $body .= self::I . 'public static function create(array $attributes = []): self' . PHP_EOL;
        $body .= self::I . '{' . PHP_EOL;
        $body .= self::I . self::I . 'return new self(' . PHP_EOL;
        $body .= self::I . self::I . self::I . 'name: ' . var_export($schema->nodeName, true) . ',' . PHP_EOL;
        $body .= self::I . self::I . self::I . 'origin: NodeOrigin::' . ($schema->nodeOrigin?->name ?? 'Region') . ',' . PHP_EOL;
        $body .= self::I . self::I . self::I . 'attributes: $attributes,' . PHP_EOL;
        $body .= self::I . self::I . self::I . 'parent: null,' . PHP_EOL;
        $body .= self::I . self::I . ');' . PHP_EOL;
        $body .= self::I . '}' . PHP_EOL;

        return $body;
    }

    /**
     * Per-child-name add{Name}()/get{Name}s()/remove{Name}(...) trio for a GroupNode's
     * dynamic children. Table 2 (Region|Node) allows NodeAttribute, StructureAttribute,
     * RawContentAttribute, RawRegionAttribute, RawSequenceAttribute — Structure is a
     * fixed literal delimiter and gets no accessor here either, same as the
     * single-attribute case below. A raw choice (multiple alternatives sharing one
     * child name) isn't handled yet — no current grammar produces one inside a
     * GroupNode; falls through to no accessor rather than guessing its shape.
     *
     * @param array<string, NodeSchema> $allSchemas
     * @param string[] &$imports
     */
    private function renderGroupNodeMethods(NodeSchema $schema, array $allSchemas, string $namespace, array &$imports): string
    {
        $methods = '';

        foreach ($schema->attributes as $attr) {
            $m = match (true) {
                $attr->isChoiceRaw() => '',
                $attr->isNodeAttribute() => $this->renderGroupNodeAttributeMethods($attr, $allSchemas, $namespace, $imports),
                $attr->isRawContentAttribute() => $this->renderGroupRawContentMethods($attr, $imports),
                $attr->isRawRegionAttribute() => $this->renderGroupRawRegionMethods($attr, $imports),
                $attr->isRawSequenceAttribute() => $this->renderGroupRawSequenceMethods($attr, $imports),
                default => '',
            };

            if ($m !== '') {
                $methods .= $m . PHP_EOL;
            }
        }

        return rtrim($methods, PHP_EOL) . ($methods !== '' ? PHP_EOL : '');
    }

    /**
     * @param array<string, NodeSchema> $allSchemas
     * @param string[] &$imports
     */
    private function renderGroupNodeAttributeMethods(AttributeSchema $attr, array $allSchemas, string $namespace, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $types = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);
        $union = !empty($types) ? implode('|', $types) : $this->resolveClassName($prop, $allSchemas, $namespace, $imports);

        $imports[] = NodeAttribute::class;

        $m  = self::I . 'public function add' . $propU . '(' . $union . ' $' . $prop . '): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->addAttribute(NodeAttribute::fromNode($' . $prop . '->setParent($this)));' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . '/** @return ' . $union . '[] */' . PHP_EOL;
        $m .= self::I . 'public function get' . $propU . 's(): array' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$result = [];' . PHP_EOL;
        $m .= self::I . self::I . 'foreach ($this->getByName(' . var_export($prop, true) . ') as $attr) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . 'if ($attr instanceof NodeAttribute) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . self::I . '/** @var ' . $union . ' $node */' . PHP_EOL;
        $m .= self::I . self::I . self::I . self::I . '$node = $attr->node;' . PHP_EOL;
        $m .= self::I . self::I . self::I . self::I . '$result[] = $node;' . PHP_EOL;
        $m .= self::I . self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . 'return $result;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function remove' . $propU . '(' . $union . ' $' . $prop . '): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'foreach ($this->getByName(' . var_export($prop, true) . ') as $attr) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . 'if ($attr instanceof NodeAttribute && $attr->node === $' . $prop . ') {' . PHP_EOL;
        $m .= self::I . self::I . self::I . self::I . '$this->removeAttribute($attr);' . PHP_EOL;
        $m .= self::I . self::I . self::I . self::I . 'break;' . PHP_EOL;
        $m .= self::I . self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    /** @param string[] &$imports */
    private function renderGroupRawContentMethods(AttributeSchema $attr, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $imports[] = RawContentAttribute::class;
        $rawName = var_export($attr->rawTokenName ?? $prop, true);

        $m  = self::I . 'public function add' . $propU . '(string $content): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->addAttribute(new RawContentAttribute($content, ' . $rawName . '));' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . '/** @return string[] */' . PHP_EOL;
        $m .= self::I . 'public function get' . $propU . 's(): array' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$result = [];' . PHP_EOL;
        $m .= self::I . self::I . 'foreach ($this->getByName(' . var_export($prop, true) . ') as $attr) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . 'if ($attr instanceof RawContentAttribute) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . self::I . '$result[] = $attr->content;' . PHP_EOL;
        $m .= self::I . self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . 'return $result;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= $this->renderGroupRemoveByIndex($propU, $prop);

        return $m;
    }

    /** @param string[] &$imports */
    private function renderGroupRawRegionMethods(AttributeSchema $attr, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $imports[] = RawRegionAttribute::class;

        $opener = $attr->rawRegionOpenerContent !== null ? var_export($attr->rawRegionOpenerContent, true) : 'null';
        $closer = $attr->rawRegionOpenerContent !== null
            ? var_export($attr->rawRegionCloserContent ?? $attr->rawRegionOpenerContent, true)
            : 'null';
        $rawName = var_export($attr->rawTokenName ?? $prop, true);

        $m  = self::I . 'public function add' . $propU . '(string $content): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->addAttribute(new RawRegionAttribute(opener: ' . $opener . ', content: $content, closer: ' . $closer . ', name: ' . $rawName . ', anchorName: null));' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . '/** @return string[] */' . PHP_EOL;
        $m .= self::I . 'public function get' . $propU . 's(): array' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$result = [];' . PHP_EOL;
        $m .= self::I . self::I . 'foreach ($this->getByName(' . var_export($prop, true) . ') as $attr) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . 'if ($attr instanceof RawRegionAttribute) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . self::I . '$result[] = $attr->content;' . PHP_EOL;
        $m .= self::I . self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . 'return $result;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= $this->renderGroupRemoveByIndex($propU, $prop);

        return $m;
    }

    /** @param string[] &$imports */
    private function renderGroupRawSequenceMethods(AttributeSchema $attr, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $imports[] = RawSequenceAttribute::class;

        $m  = self::I . 'public function add' . $propU . '(string $content): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->addAttribute(' . $this->rawSequenceCtor($attr, '$content') . ');' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . '/** @return string[] */' . PHP_EOL;
        $m .= self::I . 'public function get' . $propU . 's(): array' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$result = [];' . PHP_EOL;
        $m .= self::I . self::I . 'foreach ($this->getByName(' . var_export($prop, true) . ') as $attr) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . 'if ($attr instanceof RawSequenceAttribute) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . self::I . '$result[] = (string) $attr;' . PHP_EOL;
        $m .= self::I . self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . 'return $result;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= $this->renderGroupRemoveByIndex($propU, $prop);

        return $m;
    }

    /** Shared by all three raw-kind renderers — removal addresses the Nth match by name, since names repeat. */
    private function renderGroupRemoveByIndex(string $propU, string $prop): string
    {
        $m  = self::I . 'public function remove' . $propU . 'ByIndex(int $index): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$matches = $this->getByName(' . var_export($prop, true) . ');' . PHP_EOL;
        $m .= self::I . self::I . 'if (isset($matches[$index])) {' . PHP_EOL;
        $m .= self::I . self::I . self::I . '$this->removeAttribute($matches[$index]);' . PHP_EOL;
        $m .= self::I . self::I . '}' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    /**
     * @param string[] &$imports
     */
    private function renderCreate(NodeSchema $schema, array $allSchemas, string $namespace, array &$imports): string
    {
        $params = [];
        $attrInits = [];
        $postLines = [];
        $hasGrouped = false;

        // A choice-raw param can only default to its keyword literal (via the factory's
        // own `?? literal` fallback) when no required param follows it — PHP forbids a
        // required param after one with a default. Find the last param-contributing
        // attribute so only a trailing choice-raw slot is offered that convenience.
        $paramContributing = static fn(AttributeSchema $a): bool => $a->isChoiceRaw()
            || $a->isRawRegionAttribute() || $a->isRawContentAttribute()
            || $a->isRawSequenceAttribute() || $a->isNodeAttribute();
        $lastParamAttr = null;
        foreach ($schema->attributes as $attr) {
            if ($paramContributing($attr)) {
                $lastParamAttr = $attr;
            }
        }

        foreach ($schema->attributes as $attr) {
            if ($attr->isChoiceRaw()) {
                // Raw-choice slot: create() picks the variant via the backed enum and
                // builds the right attribute through the shared factory. A keyword
                // variant's content is fixed by the grammar's own Defaults, so when this
                // is the trailing param it may be omitted; any other variant (or a
                // non-trailing position, where PHP forbids a default) still requires it.
                $enumClass = ucfirst($attr->propName) . 'Type';
                $typeVar = '$' . $attr->propName . 'Type';
                $contentVar = '$' . $attr->propName;
                $contentParam = $attr === $lastParamAttr ? '?string ' . $contentVar . ' = null' : 'string ' . $contentVar;
                $params[] = $enumClass . ' ' . $typeVar . ', ' . $contentParam;
                $attrInits[] = self::I . self::I . self::I . self::I . 'self::' . $this->choiceRawFactoryName($attr) . '(' . $typeVar . ', ' . $contentVar . '),';
            } elseif ($attr->isStructureAttribute()) {
                $content = var_export($attr->structureContent ?? '', true);
                $attrInits[] = self::I . self::I . self::I . self::I . 'new StructureAttribute(true, ' . var_export($attr->propName, true) . ', ' . $content . '),';
                $imports[] = StructureAttribute::class;
            } elseif ($attr->isGroupAttribute()) {
                $attrInits[] = self::I . self::I . self::I . self::I . 'new GroupAttribute(' . var_export($attr->propName, true) . ', []),';
                $imports[] = GroupAttribute::class;
            } elseif ($attr->isSequenceAttribute()) {
                $attrInits[] = self::I . self::I . self::I . self::I . 'new SequenceAttribute(' . var_export($attr->propName, true) . ', null, []),';
                $imports[] = SequenceAttribute::class;
                $hasGrouped = true;
                $postLines[] = self::I . self::I . '$node->' . $attr->propName . '->withParent($node);';
                if ($attr->validityDescriptor !== null) {
                    // create() bakes in self-validation via the self-sufficient validation
                    // method so the sequence is consistent on build.
                    $postLines[] = self::I . self::I . '$node->with' . ucfirst($attr->propName) . 'Validation();';
                }
            } elseif ($attr->isRawRegionAttribute()) {
                $paramVar = '$' . $attr->propName;
                $params[] = 'string ' . $paramVar;
                if ($attr->rawRegionOpenerContent !== null) {
                    // RawRegionAttribute opener/closer are plain strings (?string).
                    $opener = var_export($attr->rawRegionOpenerContent, true);
                    $closer = var_export($attr->rawRegionCloserContent ?? $attr->rawRegionOpenerContent, true);
                } else {
                    $opener = 'null';
                    $closer = 'null';
                }
                $rawTokenName = $attr->rawTokenName ?? $attr->propName;
                $anchorName = ($attr->rawTokenName !== null && $attr->rawTokenName !== $attr->propName)
                    ? ', ' . var_export($attr->propName, true)
                    : ', null';
                $attrInits[] = self::I . self::I . self::I . self::I . 'new RawRegionAttribute(';
                $attrInits[] = self::I . self::I . self::I . self::I . self::I . 'opener: ' . $opener . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . self::I . 'closer: ' . $closer . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . self::I . 'content: ' . $paramVar . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . self::I . 'name: ' . var_export($rawTokenName, true) . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . self::I . 'anchorName: ' . ($anchorName === ', null' ? 'null' : ltrim($anchorName, ', ')) . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . '),';
                $imports[] = RawRegionAttribute::class;
            } elseif ($attr->isRawContentAttribute()) {
                // No default: nothing here tells us whether this token's content is
                // ever fixed (a real keyword) or always variable — defaulting to
                // whatever the first parsed sample happened to contain would bake an
                // arbitrary, misleading literal into the public API.
                $paramVar = '$' . $attr->propName;
                $params[] = 'string ' . $paramVar;
                $attrInits[] = self::I . self::I . self::I . self::I . 'new RawContentAttribute(' . $paramVar . '),';
                $imports[] = RawContentAttribute::class;
            } elseif ($attr->isRawSequenceAttribute()) {
                $paramVar = '$' . $attr->propName;
                $params[] = 'string ' . $paramVar;
                $attrInits[] = self::I . self::I . self::I . self::I . $this->rawSequenceCtor($attr, $paramVar) . ',';
                $imports[] = RawSequenceAttribute::class;
            } elseif ($attr->isNodeAttribute()) {
                $types = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);
                $union = !empty($types)
                    ? implode('|', $types)
                    : $this->resolveClassName('Node', $allSchemas, $namespace, $imports);
                $paramVar = '$' . $attr->propName;
                $params[] = $union . ' ' . $paramVar;
                $attrInits[] = self::I . self::I . self::I . self::I . 'NodeAttribute::fromNode(' . $paramVar . '),';
                // The child node's parent must point at the node we are building; do it
                // after construction (the attribute holds the same object reference).
                $postLines[] = self::I . self::I . $paramVar . '->setParent($node);';
                $imports[] = NodeAttribute::class;
            } elseif ($attr->isOptionalAttribute()) {
                $attrInits[] = self::I . self::I . self::I . self::I . 'new OptionalAttribute(' . var_export($attr->propName, true) . ', null),';
                $imports[] = OptionalAttribute::class;
            }
        }

        $paramList = implode(', ', $params);
        $attrsBlock = implode(PHP_EOL, $attrInits);

        // AbstractNode's constructor requires the NodeOrigin; reuse the one captured
        // from the live parse node (Token/Region/Sequence).
        $imports[] = NodeOrigin::class;
        $originLine = self::I . self::I . self::I . 'origin: NodeOrigin::' . ($schema->nodeOrigin?->name ?? 'Sequence') . ',' . PHP_EOL;

        $body  = self::I . 'public static function create(' . $paramList . '): self' . PHP_EOL;
        $body .= self::I . '{' . PHP_EOL;

        if ($hasGrouped || !empty($postLines)) {
            $body .= self::I . self::I . '$node = new self(' . PHP_EOL;
            $body .= self::I . self::I . self::I . 'name: ' . var_export($schema->nodeName, true) . ',' . PHP_EOL;
            $body .= $originLine;
            $body .= self::I . self::I . self::I . 'attributes: [' . PHP_EOL;
            $body .= $attrsBlock . PHP_EOL;
            $body .= self::I . self::I . self::I . '],' . PHP_EOL;
            $body .= self::I . self::I . self::I . 'parent: null,' . PHP_EOL;
            $body .= self::I . self::I . ');' . PHP_EOL;
            foreach ($postLines as $line) {
                $body .= $line . PHP_EOL;
            }
            $body .= PHP_EOL;
            $body .= self::I . self::I . 'return $node;' . PHP_EOL;
        } else {
            $body .= self::I . self::I . 'return new self(' . PHP_EOL;
            $body .= self::I . self::I . self::I . 'name: ' . var_export($schema->nodeName, true) . ',' . PHP_EOL;
            $body .= $originLine;
            $body .= self::I . self::I . self::I . 'attributes: [' . PHP_EOL;
            $body .= $attrsBlock . PHP_EOL;
            $body .= self::I . self::I . self::I . '],' . PHP_EOL;
            $body .= self::I . self::I . self::I . 'parent: null,' . PHP_EOL;
            $body .= self::I . self::I . ');' . PHP_EOL;
        }

        $body .= self::I . '}' . PHP_EOL;

        return $body;
    }

    /**
     * @param string[] &$imports
     */
    private function renderMethods(NodeSchema $schema, array $allSchemas, string $namespace, array &$imports, string $rootNodeName): string
    {
        $methods = '';

        foreach ($schema->attributes as $attr) {
            $m = '';
            if ($attr->isNodeAttribute()) {
                $m = $this->renderNodeAttributeMethods($attr, $allSchemas, $namespace, $imports);
            } elseif ($attr->isOptionalAttribute()) {
                $m = $this->renderOptionalAttributeMethods($attr, $allSchemas, $namespace, $imports);
            } elseif ($attr->isGroupAttribute()) {
                // Trivia (whitespace) slots get no public accessors on any node.
                if (!$this->isTriviaName($attr->propName)) {
                    $m = $this->renderGroupAttributeMethods($attr, $allSchemas, $namespace, $imports);
                }
            } elseif ($attr->isChoiceRaw()) {
                $m = $this->renderChoiceRawMethods($attr, $allSchemas, $namespace, $imports, $schema->className);
            } elseif ($attr->isSequenceAttribute()) {
                $m = $this->renderSequenceAttributeMethods($attr, $allSchemas, $namespace, $imports);
            } elseif ($attr->isRawContentAttribute()) {
                $m = $this->renderRawContentMethods($attr, $imports);
            } elseif ($attr->isRawRegionAttribute()) {
                $m = $this->renderRawRegionMethods($attr, $imports);
            } elseif ($attr->isRawSequenceAttribute()) {
                $m = $this->renderRawSequenceMethods($attr, $imports);
            }

            if ($m !== '') {
                $methods .= $m . PHP_EOL;
            }
        }

        return rtrim($methods, PHP_EOL) . ($methods !== '' ? PHP_EOL : '');
    }

    private function renderNodeAttributeMethods(AttributeSchema $attr, array $allSchemas, string $namespace, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $types = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);
        $union = implode('|', $types);

        $m  = self::I . 'public function getNode' . $propU . '(): ' . $union . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '/** @var ' . $union . ' $node */' . PHP_EOL;
        $m .= self::I . self::I . '$node = $this->' . $prop . '->node;' . PHP_EOL;
        $m .= self::I . self::I . 'return $node;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function setNode' . $propU . '(' . $union . ' $value): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->attributes[' . $attr->index . '] = NodeAttribute::fromNode($value->setParent($this));' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        $imports[] = NodeAttribute::class;
        return $m;
    }

    private function renderOptionalAttributeMethods(AttributeSchema $attr, array $allSchemas, string $namespace, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $types = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);
        $union = implode('|', $types);
        $nullableUnion = $union . '|null';

        $m  = self::I . 'public function getNode' . $propU . '(): ' . $nullableUnion . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return $this->' . $prop . '->node;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function setNode' . $propU . '(' . $union . ' $node): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->node = $node->setParent($this);' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function removeNode' . $propU . '(): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->node = null;' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    private function renderGroupAttributeMethods(AttributeSchema $attr, array $allSchemas, string $namespace, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $types = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);
        $union = implode('|', $types);

        $imports[] = Placement::class;
        $imports[] = NodeInterface::class;

        $m  = self::I . 'public function addNodeTo' . $propU . '(' . $union . ' $node, Placement $placement = Placement::After, int $offset = -1): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->addNode($node->setParent($this), $placement, $offset);' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . '/** @return array<' . $union . '> */' . PHP_EOL;
        $m .= self::I . 'public function getNodesFrom' . $propU . '(?callable $filter = null): array' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return $this->' . $prop . '->getNodes($filter);' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function removeNodeFrom' . $propU . 'ByOffset(int $offset): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->removeNodeByOffset($offset);' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . '/** @param callable(' . (count($types) === 1 ? $types[0] : 'NodeInterface') . '):bool $filter true - stay, false - remove */' . PHP_EOL;
        $m .= self::I . 'public function removeNodeFrom' . $propU . 'ByFilter(callable $filter): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->removeNodeByFilter($filter);' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    private function renderChoiceRawMethods(AttributeSchema $attr, array $allSchemas, string $namespace, array &$imports, string $className): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $enumClass = ucfirst($prop) . 'Type';
        $typeVar = '$' . $prop . 'Type';
        $contentVar = '$' . $prop;

        $imports[] = InvalidArgumentException::class;

        // Per-variant attribute builder, shared by set{Prop}() and create().
        $m  = $this->renderChoiceRawFactory($attr, $imports);
        $m .= PHP_EOL;

        $m .= self::I . 'public function set' . $propU . '(' . $enumClass . ' ' . $typeVar . ', ?string ' . $contentVar . ' = null): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->attributes[' . $attr->index . '] = self::' . $this->choiceRawFactoryName($attr) . '(' . $typeVar . ', ' . $contentVar . ');' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function get' . $propU . 'Type(): ' . $enumClass . '|null' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        // Region variants carry the variant in `name` (e.g. "number"); keyword variants
        // carry the choice anchor in `name` and the literal in `content` — try both.
        $m .= self::I . self::I . 'return ' . $enumClass . '::tryFrom($this->' . $prop . '->name)' . PHP_EOL;
        $m .= self::I . self::I . self::I . '?? ' . $enumClass . '::tryFrom((string) ($this->' . $prop . '->content ?? \'\'));' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function get' . $propU . 'Content(): string|null' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return $this->' . $prop . '->content;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    private function choiceRawFactoryName(AttributeSchema $attr): string
    {
        return 'make' . ucfirst($attr->propName);
    }

    /**
     * Per-variant attribute builder. A keyword variant's content never actually
     * varies (Rule::keyword()/Rule::token() fix it via Defaults), so when none is
     * passed it falls back to that literal. A variable-content variant (e.g. number,
     * doubleQuotedString) has no such Defaults and must reject a missing content —
     * the generator must never invent a value for it.
     *
     * @param string[] &$imports
     */
    private function renderChoiceRawFactory(AttributeSchema $attr, array &$imports): string
    {
        $enumClass = ucfirst($attr->propName) . 'Type';
        $fn = $this->choiceRawFactoryName($attr);
        $typeVar = '$' . $attr->propName . 'Type';
        $contentVar = '$' . $attr->propName;

        $imports[] = RawContentAttribute::class;
        $imports[] = RawRegionAttribute::class;
        $imports[] = RawAttributeInterface::class;
        $imports[] = InvalidArgumentException::class;

        $m  = self::I . 'private static function ' . $fn . '(' . $enumClass . ' ' . $typeVar . ', ?string ' . $contentVar . ' = null): RawAttributeInterface' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;

        foreach ($attr->rawChoices as $choice) {
            $m .= self::I . self::I . 'if (' . $typeVar . ' === ' . $enumClass . '::' . $choice->caseName . ') {' . PHP_EOL;
            if ($choice->isKeyword && $choice->literalContent !== null) {
                $fallback = $contentVar . ' ?? ' . var_export($choice->literalContent, true);
                $m .= self::I . self::I . self::I . 'return new RawContentAttribute(' . $fallback . ', ' . var_export($choice->tokenName, true) . ', null);' . PHP_EOL;
            } elseif ($choice->isKeyword) {
                $m .= self::I . self::I . self::I . 'if (' . $contentVar . ' === null) {' . PHP_EOL;
                $m .= self::I . self::I . self::I . self::I . 'throw new InvalidArgumentException(\'Content is required for type: \' . ' . $typeVar . '->value);' . PHP_EOL;
                $m .= self::I . self::I . self::I . '}' . PHP_EOL;
                $m .= self::I . self::I . self::I . 'return new RawContentAttribute(' . $contentVar . ', ' . var_export($choice->tokenName, true) . ', null);' . PHP_EOL;
            } else {
                $m .= self::I . self::I . self::I . 'if (' . $contentVar . ' === null) {' . PHP_EOL;
                $m .= self::I . self::I . self::I . self::I . 'throw new InvalidArgumentException(\'Content is required for type: \' . ' . $typeVar . '->value);' . PHP_EOL;
                $m .= self::I . self::I . self::I . '}' . PHP_EOL;
                if ($choice->hasOpener) {
                    $openerVal = var_export($choice->openerContent, true);
                    $closerVal = var_export($choice->closerContent, true);
                    $m .= self::I . self::I . self::I . 'return new RawRegionAttribute(opener: ' . $openerVal . ', content: ' . $contentVar . ', closer: ' . $closerVal . ', name: ' . var_export($choice->tokenName, true) . ', anchorName: null);' . PHP_EOL;
                } else {
                    $m .= self::I . self::I . self::I . 'return new RawRegionAttribute(opener: null, content: ' . $contentVar . ', closer: null, name: ' . var_export($choice->tokenName, true) . ', anchorName: null);' . PHP_EOL;
                }
            }
            $m .= self::I . self::I . '}' . PHP_EOL;
            $m .= PHP_EOL;
        }

        $m .= self::I . self::I . 'throw new InvalidArgumentException(\'Unsupported type: \' . ' . $typeVar . '->value);' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    private function renderSequenceAttributeMethods(AttributeSchema $attr, array $allSchemas, string $namespace, array &$imports): string
    {
        $prop  = $attr->propName;
        $propU = ucfirst($prop);

        $imports[] = NestedSequence::class;
        $imports[] = NodeAttributeInterface::class;

        $contentClassName = '';
        $contentClassShort = '';

        if ($attr->groupedContentIsChoice) {
            $choiceTypes = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);
            $union = implode('|', $choiceTypes);
            $contentClassShort = implode('|', $choiceTypes);
            $contentClassNamePlural = implode('', $choiceTypes);
        } else {
            $contentNodeName = $attr->groupedContentNodeName ?? 'Node';
            $contentClassName = $this->resolveClassName($contentNodeName, $allSchemas, $namespace, $imports);
            $contentClassShort = $contentClassName;
            $contentClassNamePlural = $contentClassName . 's';
        }

        $contentAttrName = $attr->groupedContentAttrName ?? $attr->propName;
        $contentU = ucfirst($contentAttrName);
        $addMethodType = $contentClassShort;

        // When content name is a substring of the prop name, drop "To{Prop}"/"From{Prop}" suffixes.
        $dropPropSuffix = str_contains(strtolower($prop), strtolower($contentAttrName));
        $toSuffix   = $dropPropSuffix ? '' : 'To' . $propU;
        $fromSuffix = $dropPropSuffix ? '' : 'From' . $propU;

        $m = '';

        // with{Prop}Validation — self-sufficient: builds the cursor from the baked
        // validity descriptor and the structural auto-factories. create() calls it too.
        // Only emitted when a descriptor was captured (it references {prop}Validity()).
        if ($attr->validityDescriptor !== null) {
            $factories = $this->renderAutoFactoriesLiteral($attr, self::I . self::I . self::I, $imports);
            $m .= self::I . 'public function with' . $propU . 'Validation(): self' . PHP_EOL;
            $m .= self::I . '{' . PHP_EOL;
            // Pass the NestedSequence (not a pre-built cursor) so the carrier keeps it as
            // the replay source — addUnit/removeUnit can rebuild the cursor after mutations.
            $m .= self::I . self::I . '$this->' . $prop . '->withValidSequence(' . PHP_EOL;
            $m .= self::I . self::I . self::I . 'self::' . $prop . 'Validity(),' . PHP_EOL;
            $m .= self::I . self::I . self::I . $factories . ',' . PHP_EOL;
            $m .= self::I . self::I . ');' . PHP_EOL;
            $m .= self::I . self::I . 'return $this;' . PHP_EOL;
            $m .= self::I . '}' . PHP_EOL;
            $m .= PHP_EOL;
        }

        // add — the content NodeAttribute must carry the slot's content anchor name
        // (e.g. 'item'), which the validity cursor and get{Plural}() filter match on —
        // not the node's own name (they coincide only when anchor == node name).
        $imports[] = NodeAttribute::class;
        $contentName = var_export($contentAttrName, true);
        if ($attr->groupedContentIsChoice) {
            $m .= self::I . 'public function add' . $contentU . $toSuffix . '(' . $addMethodType . ' $node): self' . PHP_EOL;
            $m .= self::I . '{' . PHP_EOL;
            $m .= self::I . self::I . '$this->' . $prop . '->addUnit(new NodeAttribute(' . $contentName . ', $node->setParent($this)));' . PHP_EOL;
        } else {
            $m .= self::I . 'public function add' . $contentU . $toSuffix . '(' . $addMethodType . ' $' . lcfirst($addMethodType) . '): self' . PHP_EOL;
            $m .= self::I . '{' . PHP_EOL;
            $m .= self::I . self::I . '$this->' . $prop . '->addUnit(new NodeAttribute(' . $contentName . ', $' . lcfirst($addMethodType) . '->setParent($this)));' . PHP_EOL;
        }
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;

        // remove by index
        $m .= self::I . 'public function remove' . $contentU . $fromSuffix . 'ByIndex(int $index): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->removeUnit($index);' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;

        // getUnit
        $m .= self::I . '/** @return NodeAttributeInterface[] */' . PHP_EOL;
        $m .= self::I . 'public function get' . $contentU . 'Unit' . $fromSuffix . '(int $index): array' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return $this->' . $prop . '->getUnit($index);' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;

        // getAll
        if ($attr->groupedContentIsChoice) {
            $returnTypes = $this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports);
            $returnUnion = implode('|', $returnTypes);
            $plural = ucfirst($contentAttrName) . 's';
            $m .= self::I . '/** @return array<' . $returnUnion . '> */' . PHP_EOL;
            $m .= self::I . 'public function get' . $plural . $fromSuffix . '(): array' . PHP_EOL;
            $m .= self::I . '{' . PHP_EOL;
            $m .= self::I . self::I . '$result = [];' . PHP_EOL;
            $m .= self::I . self::I . 'foreach ($this->' . $prop . '->attributes as $attr) {' . PHP_EOL;
            $m .= self::I . self::I . self::I . 'if ($attr instanceof NodeAttribute && $attr->getName() === ' . var_export($contentAttrName, true) . ') {' . PHP_EOL;
            $m .= self::I . self::I . self::I . self::I . '$result[] = $attr->node;' . PHP_EOL;
            $m .= self::I . self::I . self::I . '}' . PHP_EOL;
            $m .= self::I . self::I . '}' . PHP_EOL;
            $m .= self::I . self::I . 'return $result;' . PHP_EOL;
            $m .= self::I . '}' . PHP_EOL;
            $imports[] = NodeAttribute::class;
        } else {
            $plural = ucfirst($contentAttrName) . 's';
            $m .= self::I . '/** @return ' . $contentClassShort . '[] */' . PHP_EOL;
            $m .= self::I . 'public function get' . $plural . $fromSuffix . '(): array' . PHP_EOL;
            $m .= self::I . '{' . PHP_EOL;
            $m .= self::I . self::I . '$result = [];' . PHP_EOL;
            $m .= self::I . self::I . 'foreach ($this->' . $prop . '->attributes as $attr) {' . PHP_EOL;
            $m .= self::I . self::I . self::I . 'if ($attr instanceof NodeAttribute && $attr->getName() === ' . var_export($contentAttrName, true) . ') {' . PHP_EOL;
            $m .= self::I . self::I . self::I . self::I . '/** @var ' . $contentClassShort . ' $node */' . PHP_EOL;
            $m .= self::I . self::I . self::I . self::I . '$node = $attr->node;' . PHP_EOL;
            $m .= self::I . self::I . self::I . self::I . '$result[] = $node;' . PHP_EOL;
            $m .= self::I . self::I . self::I . '}' . PHP_EOL;
            $m .= self::I . self::I . '}' . PHP_EOL;
            $m .= self::I . self::I . 'return $result;' . PHP_EOL;
            $m .= self::I . '}' . PHP_EOL;
            $imports[] = NodeAttribute::class;
        }

        if ($attr->validityDescriptor !== null) {
            $m .= PHP_EOL . $this->renderValidityMethod($attr, $imports);
        }

        return $m;
    }

    /**
     * Emits the baked validity FSM as a private static factory the create() auto-validation
     * consumes — self-sufficient, no grammar compiled at runtime.
     *
     * @param string[] &$imports
     */
    private function renderValidityMethod(AttributeSchema $attr, array &$imports): string
    {
        $imports[] = NestedSequence::class;
        $literal = var_export($attr->validityDescriptor, true);

        $m  = self::I . 'private static function ' . $attr->propName . 'Validity(): NestedSequence' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return NestedSequence::fromString(' . $literal . ');' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    /**
     * Renders the structural auto-factory map literal (`[name => fn() => new …, …]`) used
     * by withValidSequence, indented from $indent. Shared by with{Prop}Validation and create().
     *
     * @param string[] &$imports
     */
    private function renderAutoFactoriesLiteral(AttributeSchema $attr, string $indent, array &$imports): string
    {
        $lines = ['['];
        foreach ($attr->structuralFactories as $sf) {
            if ($sf->isGroupAttribute()) {
                $imports[] = GroupAttribute::class;
                $lines[] = $indent . self::I . var_export($sf->name, true) . ' => static fn() => new GroupAttribute(' . var_export($sf->name, true) . ', []),';
            } elseif ($sf->isStructureAttribute()) {
                $imports[] = StructureAttribute::class;
                $lines[] = $indent . self::I . var_export($sf->name, true) . ' => static fn() => new StructureAttribute(true, ' . var_export($sf->name, true) . ', ' . var_export($sf->content, true) . '),';
            }
        }
        $lines[] = $indent . ']';

        return implode(PHP_EOL, $lines);
    }

    private function renderRawContentMethods(AttributeSchema $attr, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);

        $m  = self::I . 'public function getRaw' . $propU . '(): string' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return $this->' . $prop . '->content;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function setRaw' . $propU . '(string $value): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->content = $value;' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    private function renderRawRegionMethods(AttributeSchema $attr, array &$imports): string
    {
        $prop = $attr->propName;
        $propU = ucfirst($prop);

        $m  = self::I . 'public function getRaw' . $propU . '(): string' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return $this->' . $prop . '->content;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function setRaw' . $propU . '(string $' . lcfirst($propU) . '): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->content = $' . lcfirst($propU) . ';' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    /**
     * A Raw-typed region/choice collapses to one RawSequenceAttribute (parts joined).
     * Exposed as a single string: read via __toString, write by replacing the attribute.
     *
     * @param string[] &$imports
     */
    private function renderRawSequenceMethods(AttributeSchema $attr, array &$imports): string
    {
        $imports[] = RawSequenceAttribute::class;
        $prop = $attr->propName;
        $propU = ucfirst($prop);
        $var = '$' . lcfirst($propU);

        $m  = self::I . 'public function getRaw' . $propU . '(): string' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return (string) $this->' . $prop . ';' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function setRaw' . $propU . '(string ' . $var . '): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->attributes[' . $attr->index . '] = ' . $this->rawSequenceCtor($attr, $var) . ';' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    /** Builds a `new RawSequenceAttribute([<content>], name, anchorName)` literal. */
    private function rawSequenceCtor(AttributeSchema $attr, string $contentExpr): string
    {
        $name   = var_export($attr->rawTokenName ?? $attr->propName, true);
        $anchor = ($attr->rawTokenName !== null && $attr->rawTokenName !== $attr->propName)
            ? var_export($attr->propName, true)
            : 'null';

        return 'new RawSequenceAttribute([' . $contentExpr . '], ' . $name . ', ' . $anchor . ')';
    }

    // --- helpers ---

    /**
     * @param string[] $nodeNames
     * @param string[] &$imports
     * @return string[]
     */
    private function resolveUnionTypes(array $nodeNames, array $allSchemas, string $namespace, array &$imports): array
    {
        $types = [];
        foreach ($nodeNames as $nodeName) {
            $types[] = $this->resolveClassName($nodeName, $allSchemas, $namespace, $imports);
        }
        return $types;
    }

    /** @param string[] &$imports */
    private function resolveClassName(string $nodeName, array $allSchemas, string $namespace, array &$imports): string
    {
        $nodeSchema = $allSchemas[$nodeName] ?? null;

        if ($nodeSchema === null) {
            $collector = new NodeSchemaCollector();
            return $collector->toClassName($nodeName);
        }

        // A reference whose target namespace differs from the class being rendered is
        // imported by FQCN; same-namespace references stay local.
        if ($nodeSchema->targetNamespace !== null && $nodeSchema->targetNamespace !== $namespace) {
            $imports[] = $nodeSchema->targetNamespace . '\\' . $nodeSchema->className;
        }

        return $nodeSchema->className;
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }

    private function isTriviaName(string $name): bool
    {
        return (bool) preg_match('/^trivia\d+$/', $name);
    }

    private function nodeHasSequenceAttribute(NodeSchema $schema): bool
    {
        foreach ($schema->attributes as $attr) {
            if ($attr->isSequenceAttribute()) {
                return true;
            }
        }
        return false;
    }
}
