<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledRegion;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\AttributeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\NodeSchema;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\RawChoiceInfo;

/**
 * Extends NodeSchema[] with data from CompiledGrammar:
 *   - GrammarOrigin (import vs generate decision)
 *   - Union type extension for GroupAttribute / NodeAttribute (via meta.alternatives)
 *   - Missing RawChoiceInfo cases for raw attributes with alternatives from grammar's choice list
 */
final class GrammarAugmentor
{
    private const PARSED_TREE_NS_BASE = 'PhpArchitecture\\Parser\\Infrastructure\\Grammar\\ParsedTree\\';

    /**
     * @param array<string, NodeSchema> $schemas
     * @return array<string, NodeSchema>
     */
    public function augment(array $schemas, CompiledGrammar $grammar, string $targetFormat): array
    {
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
                $this->applyOrigin($schema, $region, $targetFormat);
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
            if (!$schema->shouldGenerate) {
                continue;
            }
            foreach ($schema->attributes as $attrSchema) {
                $this->augmentAttribute($attrSchema, $grammar, $schemas, $siblingFamilies);
            }
        }

        return $schemas;
    }

    private function applyOrigin(NodeSchema $schema, CompiledRegion $region, string $targetFormat): void
    {
        /** @var ?GrammarOrigin $origin */
        $origin = $region->getMeta(Region::META_ORIGIN);
        if ($origin === null) {
            return;
        }

        $schema->origin = $origin;

        if ($origin->format !== $targetFormat) {
            $schema->shouldGenerate = false;
            $schema->importFqcn = $this->computeImportFqcn($origin, $schema->className);
        }
    }

    private function computeImportFqcn(GrammarOrigin $origin, string $className): string
    {
        $formatPart  = $this->toNamespacePart($origin->format);
        $variantPart = $origin->variant !== null ? '\\' . $this->toNamespacePart($origin->variant) : '';
        return self::PARSED_TREE_NS_BASE . $formatPart . $variantPart . '\\' . $className;
    }

    private function toNamespacePart(string $value): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? $value;
        if ($clean === '') {
            return $value;
        }
        $result = ucfirst($clean);
        if (ctype_digit($result[0])) {
            $result = 'Ver' . $result;
        }
        return $result;
    }

    /** @param string[][] $siblingFamilies */
    private function augmentAttribute(AttributeSchema $attr, CompiledGrammar $grammar, array $schemas, array $siblingFamilies): void
    {
        if ($attr->isChoiceNodes()) {
            $this->augmentChoiceNodeUnion($attr, $grammar, $schemas);
        }

        if ($attr->isGroupAttribute()) {
            $this->augmentGroupUnion($attr, $siblingFamilies);
        }

        if ($attr->isChoiceRaw()) {
            $this->augmentRawChoices($attr);
        }

        if ($attr->isSequenceAttribute()) {
            $this->augmentGroupedContentUnion($attr, $grammar);
        }
    }

    private function augmentChoiceNodeUnion(AttributeSchema $attr, CompiledGrammar $grammar, array $schemas): void
    {
        foreach ($attr->choicesList as $choiceName) {
            if (!in_array($choiceName, $attr->unionNodeNames, true)) {
                $region = $grammar->regions[$choiceName] ?? null;
                if ($region !== null && !in_array($choiceName, $attr->unionNodeNames, true)) {
                    $attr->unionNodeNames[] = $choiceName;
                    if (!isset($schemas[$choiceName])) {
                        $collector = new NodeSchemaCollector();
                        $schemas[$choiceName] = $this->stubSchema($choiceName, $collector->toClassName($choiceName));
                    }
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

    private function augmentRawChoices(AttributeSchema $attr): void
    {
        foreach ($attr->choicesList as $choiceName) {
            $alreadyExists = false;
            foreach ($attr->rawChoices as $existing) {
                if ($existing->tokenName === $choiceName) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (!$alreadyExists) {
                $caseName = implode('', array_map('ucfirst', preg_split('/[-_\s]+/', $choiceName) ?: [$choiceName]));
                $attr->rawChoices[] = new RawChoiceInfo(
                    caseName: $caseName,
                    tokenName: $choiceName,
                    isKeyword: false,
                    keywordContent: null,
                    hasOpener: false,
                    openerContent: null,
                    closerContent: null,
                );
            }
        }
    }

    private function augmentGroupedContentUnion(AttributeSchema $attr, CompiledGrammar $grammar): void
    {
        if (!$attr->groupedContentIsChoice) {
            return;
        }
        foreach ($attr->groupedChoicesList as $choiceName) {
            if (!in_array($choiceName, $attr->unionNodeNames, true)) {
                $region = $grammar->regions[$choiceName] ?? null;
                if ($region !== null) {
                    $attr->unionNodeNames[] = $choiceName;
                }
            }
        }
    }

    private function stubSchema(string $nodeName, string $className): NodeSchema
    {
        return new NodeSchema($nodeName, $className);
    }
}
