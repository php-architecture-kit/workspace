<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use LogicException;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence as CompiledNestedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceNode as CompiledSequenceNode;
use PhpArchitecture\Parser\Foundation\Matching\Model\Sequence as CompiledSequence;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;

class RuleToSequenceCompiler implements RuleCompilerInterface
{
    public function supports(object $object): bool
    {
        return $object instanceof Rule && $object->type->isParsingRuleType();
    }

    public function compileRule(Rule $rule): CompiledSequence
    {
        if (!$this->supports($rule)) {
            throw new LogicException("Unsupported rule type. Rule must be a parsing rule.");
        }

        $sequenceRule = $rule->definition;
        if (!$sequenceRule instanceof SequenceRule) {
            throw new LogicException("Unsupported definition type. Compiler require SequenceRule definition.");
        }

        $meta = [];
        if ($rule->nodeType !== null) {
            $meta['nodeType'] = $rule->nodeType;
        }

        return $this->compileSequence(
            $rule->name,
            $sequenceRule,
            $rule->priority,
            $rule->tags,
            $meta,
        );
    }

    /** 
     * @param string[] $tags 
     * @param array<string,mixed> $meta
     */
    public function compileSequence(string $name, SequenceRule $definition, int $priority, array $tags, array $meta = []): CompiledSequence
    {
        return new CompiledSequence(
            $name,
            array_map(
                fn(NestedSequence|SequenceNode $node): CompiledNestedSequence|CompiledSequenceNode => $node instanceof NestedSequence
                    ? $this->compileNestedSequence($node)
                    : $this->compileSequenceNode($node, false),
                $definition->nodes,
            ),
            $priority,
            $meta,
            $tags,
        );
    }

    public function compileNestedSequence(NestedSequence $definition, bool $inGroup = false, bool $inRawGroup = false): CompiledNestedSequence
    {
        $isRaw = in_array('r', $definition->tags, true);
        $childInRawGroup = $isRaw || $inRawGroup;
        $childInGroup = !$isRaw && (in_array('g', $definition->tags, true) || $inGroup);

        return new CompiledNestedSequence(
            array_map(
                /**
                 * @param array<NestedSequence|SequenceNode> $alternatives
                 * @return array<CompiledNestedSequence|CompiledSequenceNode>
                 */
                fn(array $alternatives): array => array_map(
                    fn(NestedSequence|SequenceNode $def): CompiledNestedSequence|CompiledSequenceNode => $def instanceof NestedSequence
                        ? $this->compileNestedSequence($def, $childInGroup, $childInRawGroup)
                        : $this->compileSequenceNode($def, $childInGroup, $childInRawGroup),
                    $alternatives,
                ),
                $definition->alternativeSequences,
            ),
            $definition->cardinality->min(),
            $definition->cardinality->max(),
            $definition->isLookahead,
            $definition->isLookbehind,
            $definition->tags,
            [],
            $definition->anchorName,
        );
    }

    public function compileSequenceNode(SequenceNode $definition, bool $inGroup = false, bool $inRawGroup = false): CompiledSequenceNode
    {
        $tags = $definition->nodeType
            ? array_merge($definition->tags, [$definition->nodeType->value])
            : $definition->tags;
        if ($inRawGroup) {
            $tags[] = RawContentAttribute::TAG;
        } elseif ($inGroup) {
            $tags[] = SequenceAttribute::TAG;
        }

        return new CompiledSequenceNode(
            $definition->alternatives,
            $definition->cardinality->min(),
            $definition->cardinality->max(),
            $definition->isLookahead,
            $definition->isLookbehind,
            $definition->anchorName,
            [],
            $tags,
            $definition->isNegation,
        );
    }
}
