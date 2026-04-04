<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use LogicException;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Processing\Model\Matching\NestedSequence;
use PhpArchitecture\Parser\Processing\Model\Matching\Sequence;
use PhpArchitecture\Parser\Processing\Model\Matching\SequenceNode as CompiledSequenceNode;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;

class SequenceNodeEnricher
{
    /**
     * Enrich all sequences in arrays with NodeType from Rules/Regions/Tags
     * 
     * @param Sequence[] $sequences
     * @param Region $region
     * @return Sequence[]
     */
    public function enrichSequences(array $sequences, Region $region): array
    {
        $enriched = [];
        foreach ($sequences as $sequence) {
            $enriched[] = $this->enrichSequence($sequence, $region);
        }
        return $enriched;
    }

    /**
     * Enrich single sequence with NodeType
     */
    public function enrichSequence(Sequence $sequence, Region $region): Sequence
    {
        $enrichedNodes = [];
        foreach ($sequence->nodes as $node) {
            if ($node instanceof CompiledSequenceNode) {
                $enrichedNodes[] = $this->enrichNode($node, $region, $sequence->name);
            } elseif ($node instanceof NestedSequence) {
                $enrichedNodes[] = $this->enrichNestedSequence($node, $region, $sequence->name);
            } else {
                $enrichedNodes[] = $node;
            }
        }

        $tags = $sequence->tags;
        if (isset($sequence->meta['nodeType']) && $sequence->meta['nodeType'] instanceof NodeType) {
            $tags[] = $sequence->meta['nodeType']->value;
        }

        return new Sequence(
            $sequence->name,
            $enrichedNodes,
            $sequence->priority,
            $sequence->meta,
            $tags,
        );
    }

    /**
     * Enrich nested sequence
     */
    private function enrichNestedSequence(NestedSequence $nested, Region $region, string $sequenceName): NestedSequence
    {
        $enrichedAlternatives = [];
        foreach ($nested->alternativeSequences as $alternatives) {
            $enrichedAlt = [];
            foreach ($alternatives as $node) {
                if ($node instanceof CompiledSequenceNode) {
                    $enrichedAlt[] = $this->enrichNode($node, $region, $sequenceName);
                } elseif ($node instanceof NestedSequence) {
                    $enrichedAlt[] = $this->enrichNestedSequence($node, $region, $sequenceName);
                } else {
                    $enrichedAlt[] = $node;
                }
            }
            $enrichedAlternatives[] = $enrichedAlt;
        }

        return new NestedSequence(
            $enrichedAlternatives,
            $nested->min,
            $nested->max,
            $nested->isLookahead,
            $nested->isLookbehind,
            $nested->tags,
        );
    }

    /**
     * Enrich SequenceNode with NodeType from Rules/Regions/Tags
     */
    private function enrichNode(CompiledSequenceNode $node, Region $region, string $sequenceName): CompiledSequenceNode
    {
        // If node already has NodeType defined, return as is
        if ($this->hasNodeType($node)) {
            return $node;
        }

        $nodeTypesMap = [];
        
        // Iterate through alternatives and collect NodeTypes
        foreach ($node->alternatives as $alternative) {
            $nodeType = $this->resolveNodeType($alternative, $region);
            if ($nodeType !== null) {
                $nodeTypesMap[$alternative] = $nodeType;
            }
        }

        // If no NodeTypes found, return node as is
        if (empty($nodeTypesMap)) {
            return $node;
        }

        // Verify all NodeTypes are the same
        $uniqueNodeTypes = array_unique(array_map(fn(NodeType $nt) => $nt->value, $nodeTypesMap));
        if (count($uniqueNodeTypes) > 1) {
            // Build detailed error message
            $details = [];
            foreach ($nodeTypesMap as $alt => $nodeType) {
                $details[] = "  - '{$alt}' has NodeType: {$nodeType->name}";
            }
            
            throw new LogicException(
                "Conflicting NodeTypes in sequence '{$sequenceName}' in region '{$region->name}'.\n" .
                "SequenceNode with alternatives [" . implode(', ', $node->alternatives) . "] has conflicting NodeTypes:\n" .
                implode("\n", $details) . "\n" .
                "All alternatives must have the same NodeType, or the SequenceNode must define its own NodeType using /n, /s, or /r suffix."
            );
        }

        // Add NodeType to tags
        $nodeType = array_values($nodeTypesMap)[0];
        $tags = $node->tags;
        $tags[] = $nodeType->value;

        return new CompiledSequenceNode(
            $node->alternatives,
            $node->min,
            $node->max,
            $node->isLookahead,
            $node->isLookbehind,
            $node->anchorName,
            $node->meta,
            $tags,
            $node->isSpread,
        );
    }

    /**
     * Check if node already has NodeType in tags
     */
    private function hasNodeType(CompiledSequenceNode $node): bool
    {
        foreach ($node->tags as $tag) {
            if (str_starts_with($tag, 'NodeType.')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolve NodeType from alternative name (Rule/Region/Tag)
     */
    private function resolveNodeType(string $alternative, Region $region): ?NodeType
    {
        // Try to find as Rule
        if (isset($region->rules[$alternative])) {
            return $region->rules[$alternative]->nodeType;
        }

        // Try to find as Region
        if (isset($region->regions[$alternative])) {
            return $region->regions[$alternative]->config->nodeType;
        }

        // Try to find as Tag - get all rules with this tag
        $rulesWithTag = [];
        foreach ($region->rules as $rule) {
            if (in_array($alternative, $rule->getAllTags())) {
                $rulesWithTag[] = $rule;
            }
        }

        if (!empty($rulesWithTag)) {
            // Collect NodeTypes from rules with this tag
            $nodeTypes = array_filter(
                array_map(fn($rule) => $rule->nodeType, $rulesWithTag),
                fn($nt) => $nt !== null
            );

            if (empty($nodeTypes)) {
                return null;
            }

            // Verify all have the same NodeType
            $uniqueNodeTypes = array_unique(array_map(fn(NodeType $nt) => $nt->value, $nodeTypes));
            if (count($uniqueNodeTypes) > 1) {
                throw new LogicException(
                    "Tag '{$alternative}' is used by rules with different NodeTypes: " . 
                    implode(', ', $uniqueNodeTypes)
                );
            }

            return $nodeTypes[0];
        }

        // Not found - this will be caught later as missing rule
        return null;
    }
}
