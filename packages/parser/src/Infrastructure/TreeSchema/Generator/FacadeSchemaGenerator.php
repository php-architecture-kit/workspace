<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\NodeSchema;

/**
 * Orchestrates collection → augmentation → rendering for facade node class generation.
 *
 * Returns an array of filename => PHP code pairs (including enum files).
 */
final class FacadeSchemaGenerator
{
    private NodeSchemaCollector $collector;
    private GrammarAugmentor   $augmentor;
    private FacadeClassRenderer $classRenderer;
    private EnumFileRenderer    $enumRenderer;

    /** @var array<string, NodeSchema> */
    private array $lastSchemas = [];

    public function __construct()
    {
        $this->collector     = new NodeSchemaCollector();
        $this->augmentor     = new GrammarAugmentor();
        $this->classRenderer = new FacadeClassRenderer();
        $this->enumRenderer  = new EnumFileRenderer();
    }

    /** @return array<string, NodeSchema> */
    public function getLastSchemas(): array
    {
        return $this->lastSchemas;
    }

    /**
     * @param NodeInterface[] $parsedTrees   one per input file, already parsed
     * @param Grammar         $definition    the pre-compile grammar Definition — the only
     *                                        place fixed literals (Rule::token/keyword
     *                                        DefaultsDefinition) can legitimately be read from
     * @param string[]        $emitTargets   carve-out grammars to generate, as
     *                                        "format/variant" (e.g. "technical/whitespace")
     * @return array<string, array<string, string>>  targetNamespace => (filename => PHP code)
     */
    public function generate(
        array $parsedTrees,
        CompiledGrammar $compiledGrammar,
        Grammar $definition,
        string $baseNamespace,
        array $emitTargets = [],
    ): array {
        foreach ($parsedTrees as $tree) {
            $this->collector->collect($tree);
        }

        $schemas = $this->collector->getSchemas();
        $schemas = $this->augmentor->augment($schemas, $compiledGrammar, $definition);

        $this->route(
            $schemas,
            $compiledGrammar->name,
            $compiledGrammar->variant,
            $baseNamespace,
            $emitTargets,
        );

        $rootNodeName = $compiledGrammar->rootRegionName;
        $this->lastSchemas = $schemas;

        $files = [];
        foreach ($schemas as $schema) {
            if (!$schema->shouldGenerate || $schema->targetNamespace === null) {
                continue;
            }

            $ns = $schema->targetNamespace;
            $files[$ns][$schema->className . '.php'] = $this->classRenderer->render(
                $schema,
                $ns,
                $schemas,
                $rootNodeName,
            );

            foreach ($schema->attributes as $attr) {
                if ($attr->isChoiceRaw() && !empty($attr->rawChoices)) {
                    $enumClass = ucfirst($attr->propName) . 'Type';
                    $files[$ns][$enumClass . '.php'] = $this->enumRenderer->render(
                        $enumClass,
                        $ns,
                        $attr->rawChoices,
                    );
                }
            }
        }

        return $files;
    }

    /**
     * Assigns every schema a target namespace and whether it is generated this run.
     *
     * The root grammar claims all of its parse output — including nodes from inherited
     * regions of the *same format* (a variant never splits across other variants). Only
     * cross-format grammars (e.g. technical/whitespace) and origins explicitly listed in
     * $emitTargets are carved out to their own namespace; a carve-out is generated only
     * when emitted, otherwise it is import-only.
     *
     * @param array<string, NodeSchema> $schemas
     * @param string[]                  $emitTargets
     */
    private function route(
        array $schemas,
        string $rootFormat,
        ?string $rootVariant,
        string $baseNamespace,
        array $emitTargets,
    ): void {
        $rootNamespace = GrammarPath::namespaceFor($baseNamespace, $rootFormat, $rootVariant);

        foreach ($schemas as $schema) {
            $origin = $schema->origin;
            $emitted = $origin !== null
                && in_array($origin->format . '/' . ($origin->variant ?? ''), $emitTargets, true);

            // Nodes contributed by an inserted retokenize inner grammar (e.g. JsonComment in
            // JsonC) are always carved out to their own namespace, even when they share the
            // root's format — the explicit insertion, not the shared format, decides. This is
            // unlike inherited same-format regions (e.g. rfc8259 inside json5), which the root
            // claims.
            $isCarveOut = $schema->isInnerGrammarCarveOut;

            // Root-claimed: sub-rules (no origin), or same-format inherited regions not
            // explicitly carved out.
            if ($origin === null || ($origin->format === $rootFormat && !$emitted && !$isCarveOut)) {
                $schema->targetNamespace = $rootNamespace;
                $schema->shouldGenerate = true;
                continue;
            }

            // Carve-out: cross-format, or explicitly emitted. Generated only when emitted.
            $schema->targetNamespace = GrammarPath::namespaceFor($baseNamespace, $origin->format, $origin->variant);
            $schema->shouldGenerate = $emitted;
        }
    }
}
