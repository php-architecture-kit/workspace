<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use LogicException;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledRegion;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceValidityCursor;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\AttributeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\NodeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\RawChoiceInfo;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\StructuralFactoryInfo;

/**
 * Extends NodeSchema[] with data from CompiledGrammar and the grammar Definition:
 *   - GrammarOrigin (import vs generate decision)
 *   - Union type extension for GroupAttribute / NodeAttribute (via meta.alternatives)
 *   - RawChoiceInfo shape (keyword vs region, opener/closer) for every declared choice
 *     variant, and fixed literals for StructureAttribute/RawRegionAttribute slots —
 *     all resolved from the grammar's own Rule::token()/Rule::keyword() Defaults via
 *     GrammarLiteralResolver, never guessed from a parsed sample's matched text.
 */
final class GrammarAugmentor
{
    /**
     * @param array<string, NodeSchema> $schemas
     * @return array<string, NodeSchema>
     */
    public function augment(array $schemas, CompiledGrammar $grammar, Grammar $definition): array
    {
        $literals = new GrammarLiteralResolver($definition);

        // Build a generic map: possible-name → owning region, for any region that renames itself at runtime.
        $collector = new NodeSchemaCollector();
        $possibleNameToRegion = [];
        foreach ($grammar->regions as $region) {
            $possibleNames = $region->getMeta(CompiledRegion::META_POSSIBLE_NAMES) ?? [];
            foreach ($possibleNames as $possibleName) {
                $possibleNameToRegion[$possibleName] = $region;
                if (!isset($schemas[$possibleName])) {
                    $schemas[$possibleName] = new NodeSchema($possibleName, $collector->toClassName($possibleName));
                }
            }
        }

        foreach ($schemas as $nodeName => $schema) {
            $region = $grammar->regions[$nodeName] ?? $possibleNameToRegion[$nodeName] ?? null;
            if ($region !== null) {
                $this->applyOrigin($schema, $region);
            }

            // The compiler's node-origin index carries the precise, compile-time-propagated
            // origin for sub-region nodes (sequences/tokens) that the region lookup above
            // cannot see, and flags nodes contributed by an inserted retokenize inner grammar.
            if (isset($grammar->nodeOrigins[$nodeName])) {
                $schema->origin = $grammar->nodeOrigins[$nodeName];
            }
            if (isset($grammar->insertedNodeNames[$nodeName])) {
                $schema->isInnerGrammarCarveOut = true;
            }
        }

        // Build sibling-families: for each region with possible names, group those names together.
        // Used by augmentGroupUnion to expand union types with all siblings from the same family.
        $siblingFamilies = [];
        foreach ($grammar->regions as $region) {
            $possibleNames = $region->getMeta(CompiledRegion::META_POSSIBLE_NAMES) ?? [];
            if (!empty($possibleNames)) {
                $siblingFamilies[] = $possibleNames;
            }
        }

        foreach ($schemas as $nodeName => $schema) {
            foreach ($schema->attributes as $attrSchema) {
                $this->augmentAttribute($attrSchema, $grammar, $schemas, $siblingFamilies, $literals);
                if ($attrSchema->isSequenceAttribute()) {
                    $this->captureValidityDescriptor($attrSchema, $grammar, $nodeName);
                }
            }
        }

        return $schemas;
    }

    /**
     * Bakes the validity FSM (NestedSequence) for a grouped SequenceAttribute so the
     * generated create() can self-validate without compiling the grammar at runtime.
     */
    private function captureValidityDescriptor(AttributeSchema $attr, CompiledGrammar $grammar, string $nodeName): void
    {
        $region = $grammar->regions[$nodeName] ?? null;
        $rootSequence = $region?->sequenceLibrary->rootSequence;
        if ($rootSequence === null) {
            return;
        }

        $nested = SequenceValidityCursor::nestedByAnchor($rootSequence, $attr->propName);
        if ($nested !== null) {
            $attr->validityDescriptor = $nested->toString();
        }
    }

    /**
     * Stamps the schema with its defining grammar's origin (format/variant). The
     * output-namespace decision (root-claim vs carve-out) is made later by the router
     * in {@see FacadeSchemaGenerator}, not here.
     */
    private function applyOrigin(NodeSchema $schema, CompiledRegion $region): void
    {
        /** @var ?GrammarOrigin $origin */
        $origin = $region->getMeta(Region::META_ORIGIN);
        if ($origin === null) {
            return;
        }

        $schema->origin = $origin;
    }

    /** @param string[][] $siblingFamilies */
    private function augmentAttribute(AttributeSchema $attr, CompiledGrammar $grammar, array $schemas, array $siblingFamilies, GrammarLiteralResolver $literals): void
    {
        if ($attr->isChoiceNodes()) {
            $this->augmentChoiceNodeUnion($attr, $grammar, $schemas);
        }

        if ($attr->isGroupAttribute()) {
            $this->augmentGroupUnion($attr, $siblingFamilies);
        }

        if ($attr->isChoiceRaw()) {
            $this->augmentRawChoices($attr, $literals);
        } elseif ($attr->isRawRegionAttribute()) {
            $this->augmentRawRegionLiteral($attr, $literals);
        }

        if ($attr->isStructureAttribute()) {
            $this->augmentStructureLiteral($attr, $literals);
        }

        if ($attr->isSequenceAttribute()) {
            $this->augmentGroupedContentUnion($attr, $grammar, $schemas);
            $this->augmentStructuralFactoryLiterals($attr, $literals);
        }
    }

    private function augmentChoiceNodeUnion(AttributeSchema $attr, CompiledGrammar $grammar, array $schemas): void
    {
        foreach ($attr->choicesList as $choiceName) {
            if (in_array($choiceName, $attr->unionNodeNames, true)) {
                continue;
            }
            // An alternative is a real node either when it owns a grammar region or when
            // it already has a schema collected from a parsed example (e.g. `primitive`,
            // which is a Rule::choice, not a region).
            $isKnownNode = isset($grammar->regions[$choiceName]) || isset($schemas[$choiceName]);
            if ($isKnownNode) {
                $attr->unionNodeNames[] = $choiceName;
                if (!isset($schemas[$choiceName])) {
                    $collector = new NodeSchemaCollector();
                    $schemas[$choiceName] = $this->stubSchema($choiceName, $collector->toClassName($choiceName));
                }
            }
        }
    }

    /** @param string[][] $siblingFamilies */
    private function augmentGroupUnion(AttributeSchema $attr, array $siblingFamilies): void
    {
        foreach ($siblingFamilies as $family) {
            foreach ($attr->unionNodeNames as $name) {
                if (in_array($name, $family, true)) {
                    foreach ($family as $sibling) {
                        if (!in_array($sibling, $attr->unionNodeNames, true)) {
                            $attr->unionNodeNames[] = $sibling;
                        }
                    }
                    break;
                }
            }
        }
    }

    /**
     * Builds RawChoiceInfo for every declared variant straight from the grammar —
     * never from whichever variants happened to be observed in a parsed sample.
     * A choice variant that was never exercised by sample input used to fall back to
     * a fabricated empty shape (isKeyword: false, no opener); that guess could be
     * flatly wrong (e.g. silently dropping quotes), so each variant is now resolved
     * from its actual region/rule definition.
     */
    private function augmentRawChoices(AttributeSchema $attr, GrammarLiteralResolver $literals): void
    {
        $attr->rawChoices = array_map(
            fn(string $choiceName) => $this->buildRawChoiceInfo($choiceName, $literals),
            $attr->choicesList,
        );
    }

    private function buildRawChoiceInfo(string $choiceName, GrammarLiteralResolver $literals): RawChoiceInfo
    {
        $caseName = implode('', array_map('ucfirst', preg_split('/[-_\s]+/', $choiceName) ?: [$choiceName]));

        $region = $literals->regionByName($choiceName);
        if ($region !== null) {
            // hasOpener tracks a *resolved* literal, not merely the presence of a
            // StartRegionEventListener — a region can open with a non-literal rule
            // (e.g. number's Rule::taggedWith() opener), which has no text to bake.
            $opener = $literals->opener($region);

            return new RawChoiceInfo(
                caseName: $caseName,
                tokenName: $choiceName,
                isKeyword: false,
                hasOpener: $opener !== null,
                openerContent: $opener,
                closerContent: $opener !== null ? $literals->closer($region) : null,
            );
        }

        if ($literals->ruleByName($choiceName) !== null) {
            // A plain (non-region) rule renders as a RawContentAttribute. When it's a
            // Rule::keyword()/Rule::token() its Defaults give a fixed literal — the only
            // legitimate fallback when no content is passed. A Rule::expr() reference
            // (e.g. an unquoted identifier) has none, so its content stays required.
            return new RawChoiceInfo(
                caseName: $caseName,
                tokenName: $choiceName,
                isKeyword: true,
                literalContent: $literals->literalForRule($choiceName),
            );
        }

        throw new LogicException(
            "Choice variant '{$choiceName}' is neither a region nor a rule in the grammar definition — " .
                "cannot determine its shape (keyword vs region, opener/closer).",
        );
    }

    /**
     * A single (non-choice) RawRegionAttribute's opener/closer, when it has any, come
     * from its region's actual opening/closing Rule — never from a parsed sample.
     */
    private function augmentRawRegionLiteral(AttributeSchema $attr, GrammarLiteralResolver $literals): void
    {
        $region = $literals->regionByName($attr->rawTokenName ?? $attr->propName);
        if ($region === null) {
            return;
        }

        $attr->rawRegionOpenerContent = $literals->opener($region);
        $attr->rawRegionCloserContent = $literals->closer($region);
    }

    /**
     * A StructureAttribute's content is always a fixed grammar literal (e.g. ":" for
     * colon) — resolved from the rule's Defaults, never from a parsed sample.
     */
    private function augmentStructureLiteral(AttributeSchema $attr, GrammarLiteralResolver $literals): void
    {
        $literal = $literals->literalForRule($attr->propName);
        if ($literal === null) {
            throw new LogicException(
                "No fixed Defaults found for Structure rule '{$attr->propName}' — " .
                    "Rule::token()/Rule::keyword() must declare it before it can be baked into generated code.",
            );
        }

        $attr->structureContent = $literal;
    }

    /**
     * Structural auto-factory placeholders (e.g. the "comma" StructureAttribute
     * re-inserted between sequence units) carry the same fixed-literal requirement.
     */
    private function augmentStructuralFactoryLiterals(AttributeSchema $attr, GrammarLiteralResolver $literals): void
    {
        foreach ($attr->structuralFactories as $i => $sf) {
            if (!$sf->isStructureAttribute()) {
                continue;
            }

            $literal = $literals->literalForRule($sf->name);
            if ($literal === null) {
                throw new LogicException(
                    "No fixed Defaults found for structural rule '{$sf->name}' inside grouped attribute '{$attr->propName}'.",
                );
            }

            $attr->structuralFactories[$i] = new StructuralFactoryInfo($sf->name, $sf->attrClass, $literal);
        }
    }

    /** @param array<string, NodeSchema> $schemas */
    private function augmentGroupedContentUnion(AttributeSchema $attr, CompiledGrammar $grammar, array $schemas): void
    {
        if (!$attr->groupedContentIsChoice) {
            return;
        }
        foreach ($attr->groupedChoicesList as $choiceName) {
            if (in_array($choiceName, $attr->unionNodeNames, true)) {
                continue;
            }
            // Same rule as augmentChoiceNodeUnion: a choice is a real node when it owns a
            // region or already has a collected schema (e.g. `primitive`).
            if (isset($grammar->regions[$choiceName]) || isset($schemas[$choiceName])) {
                $attr->unionNodeNames[] = $choiceName;
            }
        }
    }

    private function stubSchema(string $nodeName, string $className): NodeSchema
    {
        return new NodeSchema($nodeName, $className);
    }
}
