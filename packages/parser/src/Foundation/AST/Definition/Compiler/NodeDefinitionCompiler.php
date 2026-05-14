<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Definition\Compiler;

use PhpArchitecture\Parser\Foundation\AST\Definition\AttributeDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\ChildDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\ContextDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\FormatDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\MissingDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\NodeDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\ReferenceDefinition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Definition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use InvalidArgumentException;
use LogicException;

/**
 * Compiles Definition (builder/factory) → NodeDefinition (final AST structure).
 *
 * Responsibilities:
 * - Aggregating definitions from multiple sources (Rules, Regions)
 * - Deduplication (same NodeDefinition can be used in multiple places)
 * - Cycle detection and reference creation for recursive definitions
 */
class NodeDefinitionCompiler
{
    /** @var array<string,NodeDefinition> */
    private array $compiled = [];

    /** @var array<string,true> */
    private array $inProgress = [];

    /**
     * @param DefinitionSource[] $sources
     * @return array<string,NodeDefinition>
     */
    public function compile(array $sources): array
    {
        $this->compiled = [];
        $this->inProgress = [];

        foreach ($sources as $source) {
            $this->compileDefinition($source);
        }

        return $this->compiled;
    }

    private function compileDefinition(DefinitionSource $source): NodeDefinition
    {
        $definition = $source->definition;
        $sourceName = $source->sourceName;
        $name = $definition->name;

        // Return existing if already compiled
        if (isset($this->compiled[$name])) {
            return $this->compiled[$name];
        }

        // Cycle detection - if currently compiling, it's recursion
        if (isset($this->inProgress[$name])) {
            // For recursive definitions create placeholder
            // that will be replaced with proper definition
            throw new LogicException(
                "Cyclic definition detected for '{$name}' from '{$sourceName}'. " .
                    "Recursive AST nodes require explicit reference handling.",
            );
        }

        $this->inProgress[$name] = true;

        // Categorize definitions
        $attributes = [];
        $children = [];
        $contexts = [];
        $formats = [];
        $references = [];

        foreach ($definition->definitions as $def) {
            match (true) {
                $def instanceof AttributeDefinition => $attributes[] = $def,
                $def instanceof ChildDefinition => $children[] = $def,
                $def instanceof ContextDefinition => $contexts[] = $def,
                $def instanceof FormatDefinition => $formats[] = $def,
                $def instanceof ReferenceDefinition => $references[] = $def,
                default => throw new InvalidArgumentException(
                    'Unknown definition type: ' . get_class($def),
                ),
            };
        }

        // Detect missing definitions - SequenceNodes not captured by any AST definition
        $missingDefinitions = $this->detectMissingDefinitions(
            $source->rootSequence,
            $sourceName,
            $attributes,
            $children,
            $contexts,
            $references,
        );

        // FormatDefinition - take first or create default
        $format = $formats[0] ?? new FormatDefinition(name: 'default');

        $nodeDefinition = new NodeDefinition(
            name: $name,
            attributes: $attributes,
            children: $children,
            contexts: $contexts,
            formats: $format,
            references: $references,
            missingDefinitions: $missingDefinitions,
        );

        $this->compiled[$name] = $nodeDefinition;
        unset($this->inProgress[$name]);

        return $nodeDefinition;
    }

    /**
     * Detect SequenceNodes that are not captured by any AST definition.
     *
     * @param array<AttributeDefinition|ChildDefinition|ContextDefinition|ReferenceDefinition> $definitions
     * @return MissingDefinition[]
     */
    private function detectMissingDefinitions(
        ?SequenceRule $rootSequence,
        string $sourceName,
        array $attributes,
        array $children,
        array $contexts,
        array $references,
    ): array {
        if ($rootSequence === null) {
            return [];
        }

        // Collect all captured names from AST definitions
        $capturedNames = [];

        foreach ($attributes as $def) {
            $capturedNames[$def->name] = true;
        }
        foreach ($children as $def) {
            $capturedNames[$def->name] = true;
            // ChildDefinition also captures via edge name
            $capturedNames[$def->edge->name] = true;
        }
        foreach ($contexts as $def) {
            $capturedNames[$def->name] = true;
        }
        foreach ($references as $def) {
            $capturedNames[$def->name] = true;
            // ReferenceDefinition also captures via edge name
            $capturedNames[$def->edge->name] = true;
        }

        // Get all SequenceNodes from root sequence
        $allNodes = $rootSequence->getAllSequenceNodes();

        $missing = [];
        foreach ($allNodes as $node) {
            $isCaptured = false;

            // Check by anchorName
            if ($node->anchorName !== null && isset($capturedNames[$node->anchorName])) {
                $isCaptured = true;
            }

            // Check by alternatives (first alternative is typically the node name)
            foreach ($node->alternatives as $alt) {
                if (isset($capturedNames[$alt])) {
                    $isCaptured = true;
                    break;
                }
            }

            if (!$isCaptured) {
                // sequenceNodeName: anchorName has priority, otherwise join alternatives
                $sequenceNodeName = $node->anchorName ?? implode('|', $node->alternatives);

                $missing[] = new MissingDefinition(
                    anchorName: $node->anchorName,
                    alternatives: $node->alternatives,
                    sequenceNodeName: $sequenceNodeName,
                    sourceRuleOrRegion: $sourceName,
                );
            }
        }

        return $missing;
    }

    /**
     * Returns compilation statistics.
     */
    public function getStats(): array
    {
        return [
            'compiled' => count($this->compiled),
            'in_progress' => count($this->inProgress),
        ];
    }
}
