<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;
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

        $imports[] = Node::class;

        $propertyHooks = $this->renderPropertyHooks($schema, $allSchemas, $namespace, $imports);
        $createMethod  = $this->renderCreate($schema, $allSchemas, $namespace, $imports);
        $methods       = $this->renderMethods($schema, $allSchemas, $namespace, $imports, $rootNodeName);

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
        $code .= 'class ' . $schema->className . ' extends Node' . PHP_EOL;
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
            $attrShort = $this->shortName($attr->attrClass);
            $imports[] = $attr->attrClass;

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

            if (!empty($attr->unionNodeNames)) {
                foreach ($this->resolveUnionTypes($attr->unionNodeNames, $allSchemas, $namespace, $imports) as $t) {
                    if (!in_array($t, $all, true)) {
                        $all[] = $t;
                    }
                }
            }

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
     * @param string[] &$imports
     */
    private function renderCreate(NodeSchema $schema, array $allSchemas, string $namespace, array &$imports): string
    {
        $params = [];
        $attrInits = [];
        $postLines = [];
        $hasGrouped = false;

        foreach ($schema->attributes as $attr) {
            if ($attr->isStructureAttribute()) {
                $content = var_export($attr->structureContent ?? '', true);
                $attrInits[] = self::I . self::I . self::I . 'new StructureAttribute(true, ' . var_export($attr->propName, true) . ', ' . $content . '),';
                $imports[] = StructureAttribute::class;
            } elseif ($attr->isGroupAttribute()) {
                $attrInits[] = self::I . self::I . self::I . 'new GroupAttribute(' . var_export($attr->propName, true) . ', []),';
                $imports[] = GroupAttribute::class;
            } elseif ($attr->isSequenceAttribute()) {
                $attrInits[] = self::I . self::I . self::I . 'new SequenceAttribute(' . var_export($attr->propName, true) . ', null, []),';
                $imports[] = SequenceAttribute::class;
                $hasGrouped = true;
                $postLines[] = self::I . self::I . '$node->' . $attr->propName . '->withParent($node);';
            } elseif ($attr->isRawRegionAttribute()) {
                $paramVar = '$' . $attr->propName;
                $params[] = 'string ' . $paramVar;
                if ($attr->rawRegionOpenerContent !== null) {
                    $openerName = $attr->rawRegionOpenerName ?? 'doubleQuote';
                    $opener = 'new StructureAttribute(true, ' . var_export($openerName, true) . ', ' . var_export($attr->rawRegionOpenerContent, true) . ')';
                    $closer = 'new StructureAttribute(true, ' . var_export($openerName, true) . ', ' . var_export($attr->rawRegionCloserContent ?? $attr->rawRegionOpenerContent, true) . ')';
                    $imports[] = StructureAttribute::class;
                } else {
                    $opener = 'null';
                    $closer = 'null';
                }
                $rawTokenName = $attr->rawTokenName ?? $attr->propName;
                $anchorName = ($attr->rawTokenName !== null && $attr->rawTokenName !== $attr->propName)
                    ? ', ' . var_export($attr->propName, true)
                    : ', null';
                $attrInits[] = self::I . self::I . self::I . 'new RawRegionAttribute(';
                $attrInits[] = self::I . self::I . self::I . self::I . 'opener: ' . $opener . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . 'closer: ' . $closer . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . 'content: ' . $paramVar . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . 'name: ' . var_export($rawTokenName, true) . ',';
                $attrInits[] = self::I . self::I . self::I . self::I . 'anchorName: ' . ($anchorName === ', null' ? 'null' : ltrim($anchorName, ', ')) . ',';
                $attrInits[] = self::I . self::I . self::I . '),';
                $imports[] = RawRegionAttribute::class;
            } elseif ($attr->isRawContentAttribute()) {
                $default = var_export($attr->rawDefaultContent ?? '', true);
                $paramVar = '$' . $attr->propName;
                $params[] = 'string ' . $paramVar . ' = ' . $default;
                $attrInits[] = self::I . self::I . self::I . 'new RawContentAttribute(' . $paramVar . '),';
                $imports[] = RawContentAttribute::class;
            } elseif ($attr->isNodeAttribute()) {
                $nodeClass = $attr->unionNodeNames[0] ?? 'Node';
                $cn = $this->resolveClassName($nodeClass, $allSchemas, $namespace, $imports);
                $paramVar = '$' . lcfirst($cn);
                $params[] = $cn . ' ' . $paramVar;
                $attrInits[] = self::I . self::I . self::I . 'NodeAttribute::fromNode(' . $paramVar . '),';
                $imports[] = NodeAttribute::class;
            } elseif ($attr->isOptionalAttribute()) {
                $attrInits[] = self::I . self::I . self::I . 'new OptionalAttribute(' . var_export($attr->propName, true) . ', null),';
                $imports[] = OptionalAttribute::class;
            }
        }

        $paramList = implode(', ', $params);
        $attrsBlock = implode(PHP_EOL, $attrInits);

        $body  = self::I . 'public static function create(' . $paramList . '): self' . PHP_EOL;
        $body .= self::I . '{' . PHP_EOL;

        if ($hasGrouped || !empty($postLines)) {
            $body .= self::I . self::I . '$node = new self(' . PHP_EOL;
            $body .= self::I . self::I . self::I . 'name: ' . var_export($schema->nodeName, true) . ',' . PHP_EOL;
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
        $isRoot = $schema->nodeName === $rootNodeName;

        foreach ($schema->attributes as $attr) {
            $m = '';
            if ($attr->isNodeAttribute()) {
                $m = $this->renderNodeAttributeMethods($attr, $allSchemas, $namespace, $imports);
            } elseif ($attr->isOptionalAttribute()) {
                $m = $this->renderOptionalAttributeMethods($attr, $allSchemas, $namespace, $imports);
            } elseif ($attr->isGroupAttribute()) {
                if ($isRoot || !$this->isTriviaName($attr->propName)) {
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
        $m .= self::I . self::I . '/** @var NodeAttribute $attribute */' . PHP_EOL;
        $m .= self::I . self::I . '$attribute = $this->' . $prop . ';' . PHP_EOL;
        $m .= self::I . self::I . 'return $attribute->node;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function setNode' . $propU . '(' . $union . ' $value): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . ' = NodeAttribute::fromNode($value->setParent($this));' . PHP_EOL;
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

        $imports[] = RawContentAttribute::class;
        $imports[] = RawRegionAttribute::class;
        $imports[] = StructureAttribute::class;
        $imports[] = InvalidArgumentException::class;

        $m  = self::I . 'public function set' . $propU . '(' . $enumClass . ' $type, ?string $content = null): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;

        foreach ($attr->rawChoices as $choice) {
            $m .= self::I . self::I . 'if ($type === ' . $enumClass . '::' . $choice->caseName . ') {' . PHP_EOL;
            if ($choice->isKeyword) {
                $val = var_export($choice->keywordContent ?? $choice->tokenName, true);
                $m .= self::I . self::I . self::I . '$this->' . $prop . ' = new RawContentAttribute(' . $val . ', ' . var_export($choice->tokenName, true) . ', null);' . PHP_EOL;
            } elseif ($choice->hasOpener) {
                $m .= self::I . self::I . self::I . 'if ($content === null) {' . PHP_EOL;
                $m .= self::I . self::I . self::I . self::I . 'throw new InvalidArgumentException(\'Content required for ' . $choice->tokenName . '.\');' . PHP_EOL;
                $m .= self::I . self::I . self::I . '}' . PHP_EOL;
                $openerName = 'doubleQuote';
                $openerVal  = var_export($choice->openerContent ?? '"', true);
                $closerVal  = var_export($choice->closerContent ?? $choice->openerContent ?? '"', true);
                $m .= self::I . self::I . self::I . '$this->' . $prop . ' = new RawRegionAttribute(' . PHP_EOL;
                $m .= self::I . self::I . self::I . self::I . 'new StructureAttribute(true, ' . var_export($openerName, true) . ', ' . $openerVal . '),' . PHP_EOL;
                $m .= self::I . self::I . self::I . self::I . 'new StructureAttribute(true, ' . var_export($openerName, true) . ', ' . $closerVal . '),' . PHP_EOL;
                $m .= self::I . self::I . self::I . self::I . '$content, ' . var_export($choice->tokenName, true) . ', null,' . PHP_EOL;
                $m .= self::I . self::I . self::I . ');' . PHP_EOL;
            } else {
                $m .= self::I . self::I . self::I . 'if ($content === null) {' . PHP_EOL;
                $m .= self::I . self::I . self::I . self::I . 'throw new InvalidArgumentException(\'Content required for ' . $choice->tokenName . '.\');' . PHP_EOL;
                $m .= self::I . self::I . self::I . '}' . PHP_EOL;
                $m .= self::I . self::I . self::I . '$this->' . $prop . ' = new RawRegionAttribute(null, null, $content, ' . var_export($choice->tokenName, true) . ', null);' . PHP_EOL;
            }
            $m .= self::I . self::I . self::I . 'return $this;' . PHP_EOL;
            $m .= self::I . self::I . '}' . PHP_EOL;
            $m .= PHP_EOL;
        }

        $m .= self::I . self::I . 'throw new InvalidArgumentException(\'Unsupported type: \' . $type->value);' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function get' . $propU . 'Type(): ' . $enumClass . '|null' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return ' . $enumClass . '::from($this->' . $prop . '->name);' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;
        $m .= self::I . 'public function get' . $propU . 'Content(): string|null' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . 'return $this->' . $prop . '->content;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;

        return $m;
    }

    private function renderSequenceAttributeMethods(AttributeSchema $attr, array $allSchemas, string $namespace, array &$imports): string
    {
        $prop  = $attr->propName;
        $propU = ucfirst($prop);

        $imports[] = NestedSequence::class;
        $imports[] = SequenceValidityCursor::class;
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

        // withValidation (always keeps prop name)
        $m  = self::I . 'public function with' . $propU . 'Validation(NestedSequence|SequenceValidityCursor $sequence): self' . PHP_EOL;
        $m .= self::I . '{' . PHP_EOL;
        $m .= self::I . self::I . '$this->' . $prop . '->withValidSequence($sequence, [' . PHP_EOL;
        foreach ($attr->structuralFactories as $sf) {
            if ($sf->isGroupAttribute()) {
                $imports[] = GroupAttribute::class;
                $m .= self::I . self::I . self::I . var_export($sf->name, true) . ' => static fn() => new GroupAttribute(' . var_export($sf->name, true) . ', []),' . PHP_EOL;
            } elseif ($sf->isStructureAttribute()) {
                $imports[] = StructureAttribute::class;
                $m .= self::I . self::I . self::I . var_export($sf->name, true) . ' => static fn() => new StructureAttribute(true, ' . var_export($sf->name, true) . ', ' . var_export($sf->content, true) . '),' . PHP_EOL;
            }
        }
        $m .= self::I . self::I . ']);' . PHP_EOL;
        $m .= self::I . self::I . 'return $this;' . PHP_EOL;
        $m .= self::I . '}' . PHP_EOL;
        $m .= PHP_EOL;

        // add
        $imports[] = NodeAttribute::class;
        if ($attr->groupedContentIsChoice) {
            $m .= self::I . 'public function add' . $contentU . $toSuffix . '(' . $addMethodType . ' $node): self' . PHP_EOL;
            $m .= self::I . '{' . PHP_EOL;
            $m .= self::I . self::I . '$this->' . $prop . '->addUnit(NodeAttribute::fromNode($node->setParent($this)));' . PHP_EOL;
        } else {
            $m .= self::I . 'public function add' . $contentU . $toSuffix . '(' . $addMethodType . ' $' . lcfirst($addMethodType) . '): self' . PHP_EOL;
            $m .= self::I . '{' . PHP_EOL;
            $m .= self::I . self::I . '$this->' . $prop . '->addUnit(NodeAttribute::fromNode($' . lcfirst($addMethodType) . '->setParent($this)));' . PHP_EOL;
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

        return $m;
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

        if ($nodeSchema !== null && !$nodeSchema->shouldGenerate && $nodeSchema->importFqcn !== null) {
            $imports[] = $nodeSchema->importFqcn;
            return $this->shortName($nodeSchema->importFqcn);
        }

        if ($nodeSchema !== null) {
            return $nodeSchema->className;
        }

        $collector = new NodeSchemaCollector();
        return $collector->toClassName($nodeName);
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
