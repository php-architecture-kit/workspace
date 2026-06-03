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
 *   - Union type extension for GroupAttribute / ChoiceAttribute
 *   - Missing RawChoiceInfo cases for ChoiceAttribute(raws) from grammar's choice list
 */
final class GrammarAugmentor
{
    private const PARSED_TREE_NS_BASE = 'PhpArchitecture\\Parser\\Infrastructure\\Grammar\\ParsedTree\\';

    /**
     * @param array<string, NodeSchema> $schemas
     * @return array<string, NodeSchema>
     */
    public function augment(array $schemas, CompiledGrammar $grammar, string $targetFormat, string $targetNamespace): array
    {
        // Build set of dynamically-renamed whitespace node names (leadingWs, trailingWs, etc.)
        // These nodes are renamed at parse time from whitespace_region; they share its origin.
        $wsRegion = $grammar->regions['whitespace_region'] ?? null;
        $wsNodeNames = $wsRegion !== null
            ? ($wsRegion->getMeta(CompiledRegion::META_POSSIBLE_NAMES) ?? [])
            : [];

        // Ensure all whitespace node names have schemas (even if not seen in parse output).
        $collector = new NodeSchemaCollector();
        foreach ($wsNodeNames as $wsName) {
            if (!isset($schemas[$wsName]) && $wsRegion !== null) {
                $schemas[$wsName] = new NodeSchema($wsName, $collector->toClassName($wsName));
            }
        }

        foreach ($schemas as $nodeName => $schema) {
            $region = $grammar->regions[$nodeName] ?? null;
            if ($region === null && in_array($nodeName, $wsNodeNames, true)) {
                $region = $wsRegion;
            }
            if ($region !== null) {
                $this->applyOrigin($schema, $region, $targetFormat, $targetNamespace);
            }
        }

        foreach ($schemas as $nodeName => $schema) {
            if (!$schema->shouldGenerate) {
                continue;
            }
            foreach ($schema->attributes as $attrSchema) {
                $this->augmentAttribute($attrSchema, $grammar, $schemas, $targetFormat, $targetNamespace);
            }
        }

        return $schemas;
    }

    private function applyOrigin(NodeSchema $schema, CompiledRegion $region, string $targetFormat, string $targetNamespace): void
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

    private function augmentAttribute(AttributeSchema $attr, CompiledGrammar $grammar, array $schemas, string $targetFormat, string $targetNamespace): void
    {
        if ($attr->isChoiceAttribute() && !$attr->isChoiceRaw()) {
            $this->augmentChoiceNodeUnion($attr, $grammar, $schemas);
        }

        if ($attr->isGroupAttribute()) {
            $this->augmentGroupUnion($attr, $grammar);
        }

        if ($attr->isChoiceRaw()) {
            $this->augmentRawChoices($attr);
        }

        if ($attr->isGroupedAttribute()) {
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

    private function augmentGroupUnion(AttributeSchema $attr, CompiledGrammar $grammar): void
    {
        // Group union is extended by checking whitespace_region possible names
        $whitespaceRegion = $grammar->regions['whitespace_region'] ?? null;
        if ($whitespaceRegion === null) {
            return;
        }
        /** @var string[]|null $possibleNames */
        $possibleNames = $whitespaceRegion->getMeta(CompiledRegion::META_POSSIBLE_NAMES);
        if ($possibleNames === null) {
            return;
        }
        foreach ($attr->unionNodeNames as $name) {
            // if any existing union member is a whitespace node, also include siblings
            if (in_array($name, $possibleNames, true)) {
                foreach ($possibleNames as $sibling) {
                    if (!in_array($sibling, $attr->unionNodeNames, true)) {
                        $attr->unionNodeNames[] = $sibling;
                    }
                }
                break;
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

    /** @param array<string,NodeSchema> $schemas */
    private function stubSchema(string $nodeName, string $className): NodeSchema
    {
        return new NodeSchema($nodeName, $className);
    }
}
