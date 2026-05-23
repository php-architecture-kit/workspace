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
                    : $this->compileSequenceNode($node),
                $definition->nodes,
            ),
            $priority,
            $meta,
            $tags,
        );
    }

    public function compileNestedSequence(NestedSequence $definition, bool $inGroup = false): CompiledNestedSequence
    {
        $childInGroup = $definition->isGroup || $inGroup;

        return new CompiledNestedSequence(
            array_map(
                /**
                 * @param array<NestedSequence|SequenceNode> $alternatives
                 * @return array<CompiledNestedSequence|CompiledSequenceNode>
                 */
                fn(array $alternatives): array => array_map(
                    fn(NestedSequence|SequenceNode $def): CompiledNestedSequence|CompiledSequenceNode => $def instanceof NestedSequence
                        ? $this->compileNestedSequence($def, $def->isGroup ? false : $childInGroup)
                        : $this->compileSequenceNode($def, $childInGroup),
                    $alternatives,
                ),
                $definition->alternativeSequences,
            ),
            $definition->cardinality->min(),
            $definition->cardinality->max(),
            $definition->isLookahead,
            $definition->isLookbehind,
            $definition->isGroup,
            $definition->tags,
        );
    }

    public function compileSequenceNode(SequenceNode $definition, bool $inGroup = false): CompiledSequenceNode
    {
        return new CompiledSequenceNode(
            $definition->alternatives,
            $definition->cardinality->min(),
            $definition->cardinality->max(),
            $definition->isLookahead,
            $definition->isLookbehind,
            $definition->anchorName,
            [],
            $definition->nodeType ? array_merge($definition->tags, [$definition->nodeType->value]) : $definition->tags,
            $definition->isNegation,
            $inGroup,
        );
    }
}
