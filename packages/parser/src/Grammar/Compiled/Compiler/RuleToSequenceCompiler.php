<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use LogicException;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Model\Matching\NestedSequence as CompiledNestedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\SequenceNode as CompiledSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Matching\Sequence as CompiledSequence;

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

        return $this->compileSequence(
            $rule->name,
            $sequenceRule,
            $rule->priority,
            $rule->tags,
        );
    }

    /** @param string[] $tags */
    public function compileSequence(string $name, SequenceRule $definition, int $priority, array $tags): CompiledSequence
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
            $tags,
        );
    }

    public function compileNestedSequence(NestedSequence $definition): CompiledNestedSequence
    {
        return new CompiledNestedSequence(
            array_map(
                /** 
                 * @param array<NestedSequence|SequenceNode> $alternatives 
                 * @return array<CompiledNestedSequence|CompiledSequenceNode>
                 */
                fn(array $alternatives): array => array_map(
                    fn(NestedSequence|SequenceNode $def): CompiledNestedSequence|CompiledSequenceNode => $def instanceof NestedSequence
                        ? $this->compileNestedSequence($def)
                        : $this->compileSequenceNode($def),
                    $alternatives,
                ),
                $definition->alternativeSequences
            ),
            $definition->cardinality->min(),
            $definition->cardinality->max(),
            $definition->isLookahead,
            $definition->isLookbehind,
            $definition->tags
        );
    }

    public function compileSequenceNode(SequenceNode $definition): CompiledSequenceNode
    {
        return new CompiledSequenceNode(
            $definition->alternatives,
            $definition->cardinality->min(),
            $definition->cardinality->max(),
            $definition->isLookahead,
            $definition->isLookbehind,
            $definition->anchorName,
            $definition->tags
        );
    }
}
