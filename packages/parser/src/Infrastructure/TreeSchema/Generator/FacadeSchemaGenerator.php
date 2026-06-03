<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
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
     * @return array<string, string>  filename (without dir) => PHP code
     */
    public function generate(
        array $parsedTrees,
        CompiledGrammar $compiledGrammar,
        string $namespace,
    ): array {
        foreach ($parsedTrees as $tree) {
            $this->collector->collect($tree);
        }

        $schemas = $this->collector->getSchemas();

        $targetFormat  = $compiledGrammar->name;
        $schemas = $this->augmentor->augment($schemas, $compiledGrammar, $targetFormat, $namespace);

        $rootNodeName = $compiledGrammar->rootRegionName;

        $this->lastSchemas = $schemas;

        $files = [];
        foreach ($schemas as $schema) {
            if (!$schema->shouldGenerate) {
                continue;
            }

            $files[$schema->className . '.php'] = $this->classRenderer->render(
                $schema,
                $namespace,
                $schemas,
                $rootNodeName,
            );

            foreach ($schema->attributes as $attr) {
                if ($attr->isChoiceRaw() && !empty($attr->rawChoices)) {
                    $enumClass = ucfirst($attr->propName) . 'Type';
                    $files[$enumClass . '.php'] = $this->enumRenderer->render(
                        $enumClass,
                        $namespace,
                        $attr->rawChoices,
                    );
                }
            }
        }

        return $files;
    }
}
