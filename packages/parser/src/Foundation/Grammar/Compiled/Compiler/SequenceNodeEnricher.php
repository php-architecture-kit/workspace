<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use LogicException;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\Sequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceNode as CompiledSequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class SequenceNodeEnricher
{
    public function __construct(
        private readonly Grammar $definition,
    ) {}

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
            $enrichedSequence = $this->enrichSequence($sequence, $region);
            if (!in_array(NodeType::Tag->value, $enrichedSequence->tags)) {
                $enriched[] = $enrichedSequence;
            }
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
            [],
            $nested->tags,
            $nested->anchorName,
        );
    }

    /**
     * Enrich SequenceNode with NodeType and spread from Rules/Regions/Tags
     */
    private function enrichNode(CompiledSequenceNode $node, Region $region, string $sequenceName): CompiledSequenceNode
    {
        // Spread tag-typed alternatives inline. Each alternative that resolves to a
        // NodeType::Tag rule (created by TagToChoiceCompiler) is replaced by the
        // alternatives it covers. This handles a tag appearing as one of many
        // alternatives (e.g. primitive = false|null|true|number|string where `string`
        // is a tag covering `doubleQuotedString`), not just the single-alternative case.
        $extraTags = [];
        $hadAmbiguousTagAlternative = false;
        $spread = $this->spreadTagAlternatives($node->alternatives, $region, $extraTags, $hadAmbiguousTagAlternative);
        $hadTagAlternative = $spread !== $node->alternatives;

        // Resolve types from the final (post-spread) alternatives — what will actually be
        // matched at runtime.
        $nodeTypesMap = [];
        foreach ($spread as $alternative) {
            $nodeType = $this->resolveNodeType($alternative, $region);

            if ($nodeType !== null) {
                $nodeTypesMap[$alternative] = $nodeType;
            }
        }

        $existingType = $this->existingNodeType($node);

        if (!$hadTagAlternative && $existingType !== null) {
            return $node;
        }

        if (empty($nodeTypesMap)) {
            if (in_array(RawContentAttribute::TAG, $node->tags, true)) {
                return $node;
            }

            $nodeName = $node->anchorName ?? implode('|', $node->alternatives);
            throw new LogicException("Sequence `{$sequenceName}` in `{$region->name}` region has no node type assigned to `{$nodeName}` node. You can add tag `/n`, `/s`, `/r` to sequence node to define is it a node, a structure element or a raw content.");
        }

        // Priority for the slot's final type:
        // 1. An explicit type already stamped at definition time (`/r`/`/s`/`/n`,
        //    attributeTags, or TriviaSequenceNamingMiddleware) always wins — the grammar
        //    author's call, e.g. `primitive`'s alternatives are uniformly Raw on their own
        //    but explicitly forced Raw via attributeTags regardless.
        // 2. A tag that covers more than one rule (e.g. `Rule::taggedWith('keyword')`
        //    covering `null`/`true`) is, by long-standing convention, wrapped as Node —
        //    the choice itself becomes an addressable child Node carrying
        //    meta['alternatives'], even though every covered rule happens to be Raw.
        //    A tag covering exactly one rule has nothing to disambiguate, so it does NOT
        //    qualify here — it falls through to (3) and inherits that rule's own type.
        // 3. Otherwise, derive from the concrete (non-Tag) post-spread alternatives: a
        //    single shared type is preserved; heterogeneous types (e.g. a Node region
        //    alongside a Raw token) can only be told apart once actually matched, so the
        //    slot must be tagged Node, the generic wrapper capable of holding either. A
        //    Tag surviving past spreading is a self-referential artifact (e.g. a region
        //    tagged with its own name, as `whitespace` is in Whitespace.php) carrying no
        //    type of its own; if that's all there is, it's used as a harmless last resort
        //    — such nodes exist only for tag lookup and are never actually matched.
        $concreteTypes = array_values(array_filter($nodeTypesMap, static fn(NodeType $nt) => $nt !== NodeType::Tag));
        $uniqueConcreteTypes = array_unique(array_map(static fn(NodeType $nt) => $nt->value, $concreteTypes));

        $resolvedType = match (true) {
            $existingType !== null => $existingType,
            $hadAmbiguousTagAlternative => NodeType::Node,
            count($uniqueConcreteTypes) === 1 => $concreteTypes[0],
            count($uniqueConcreteTypes) > 1 => NodeType::Node,
            default => array_values($nodeTypesMap)[0],
        };

        $tags = $node->tags;
        $anchorName = $node->anchorName;

        if ($hadTagAlternative) {
            $tags = array_merge($tags, $extraTags);
            $tags = array_values(array_filter($tags, static fn(string $tag): bool => $tag !== NodeType::Tag->value));

            if ($anchorName === null && count($node->alternatives) === 1) {
                // Preserve legacy single-tag anchor naming.
                $anchorName = $region->rules[$node->alternatives[0]]->name ?? null;
            }
        }

        if (!in_array($resolvedType->value, $tags, true)) {
            $tags[] = $resolvedType->value;
        }

        return new CompiledSequenceNode(
            $spread,
            $node->min,
            $node->max,
            $node->isLookahead,
            $node->isLookbehind,
            $anchorName,
            $node->meta,
            $tags,
            $node->isNegation,
        );
    }

    /**
     * Replace each alternative that resolves to a NodeType::Tag rule with the
     * alternatives that tag covers. Deduplicates while preserving order and expands
     * nested tags, guarding against cycles.
     *
     * @param string[] $alternatives
     * @param string[] $accumulatedTags out: tags collected from expanded tag-rules
     * @param bool $hadAmbiguousTagAlternative out: true if any expanded tag covered more
     *             than one alternative — i.e. there was an actual choice to disambiguate,
     *             as opposed to a tag that is merely an indirection to a single rule
     * @return string[]
     */
    private function spreadTagAlternatives(array $alternatives, Region $region, array &$accumulatedTags, bool &$hadAmbiguousTagAlternative): array
    {
        $result = [];
        $expanded = [];
        $queue = $alternatives;
        while ($queue) {
            $alternative = array_shift($queue);
            $rule = $region->rules[$alternative] ?? null;
            if (
                !isset($expanded[$alternative])
                && $rule !== null
                && $rule->nodeType === NodeType::Tag
                && $rule->definition instanceof SequenceRule
                && isset($rule->definition->nodes[0])
            ) {
                $expanded[$alternative] = true;
                $accumulatedTags = array_merge($accumulatedTags, $rule->tags);
                $covered = $rule->definition->nodes[0]->alternatives;
                if (count($covered) > 1) {
                    $hadAmbiguousTagAlternative = true;
                }
                foreach ($covered as $sub) {
                    $queue[] = $sub; // re-process to handle nested tags
                }
                continue;
            }
            if (!in_array($alternative, $result, true)) {
                $result[] = $alternative;
            }
        }
        return $result;
    }

    /**
     * The concrete NodeType already stamped on the node's tags (e.g. by
     * TriviaSequenceNamingMiddleware, or attributeTags on Rule::choice), ignoring the
     * NodeType::Tag placeholder which carries no type information of its own.
     */
    private function existingNodeType(CompiledSequenceNode $node): ?NodeType
    {
        foreach ($node->tags as $tag) {
            if ($tag === NodeType::Tag->value) {
                continue;
            }

            $nodeType = NodeType::tryFrom($tag);
            if ($nodeType !== null) {
                return $nodeType;
            }
        }

        return null;
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

        // Try to find as nested Region
        if (isset($region->regions[$alternative])) {
            return $region->regions[$alternative]->config->nodeType;
        }

        // Try to find as a possibleNames() entry of any region — a runtime-only
        // rename target (e.g. Whitespace's 'trailingWs'/'leadingWs'/...) is never a
        // Rule or Region name of its own; withPossibleNamesForTag() (see
        // TagToChoiceCompiler) can surface it as a spread alternative, so it must
        // resolve to a type here too, or every such alternative would fail as
        // "no node type assigned" below.
        foreach ($this->definition->getAllRegions() as $candidateRegion) {
            if (in_array($alternative, $candidateRegion->config->possibleNames, true)) {
                return $candidateRegion->config->nodeType;
            }
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
                fn($nt) => $nt !== null,
            );

            if (empty($nodeTypes)) {
                return null;
            }

            // Verify all have the same NodeType
            $uniqueNodeTypes = array_unique(array_map(fn(NodeType $nt) => $nt->value, $nodeTypes));
            if (count($uniqueNodeTypes) > 1) {
                throw new LogicException(
                    "Tag '{$alternative}' is used by rules with different NodeTypes: " .
                        implode(', ', $uniqueNodeTypes),
                );
            }

            return $nodeTypes[0];
        }

        // TODO: this is a hack. The correct way is to check region inheritance rules as use source of regions from it. It should be fixed in the future
        // Try to find as global Region
        if (isset($this->definition->global->regions[$alternative])) {
            return $this->definition->global->regions[$alternative]->config->nodeType;
        }

        // Not found - this will be caught later as missing rule
        return null;
    }
}
