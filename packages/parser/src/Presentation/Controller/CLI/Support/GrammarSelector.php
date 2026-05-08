<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\Controller\CLI\Support;

use PhpArchitecture\Parser\Foundation\Grammar\Contract\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Infrastructure\Grammar\Registry\InMemoryGrammarRegistry;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GrammarSelector
{
    public function __construct(
        private readonly InMemoryGrammarRegistry $registry,
    ) {}

    /**
     * Resolves a grammar definition either from the provided FQCN string
     * or — when null — via an interactive choice list built from the registry.
     *
     * Returns null (and writes an error) when resolution fails.
     */
    public function resolve(?string $grammarClass, SymfonyStyle $io): ?GrammarDefinitionInterface
    {
        if ($grammarClass !== null) {
            return $this->fromClass($grammarClass, $io);
        }

        return $this->interactiveChoice($io);
    }

    private function fromClass(string $grammarClass, SymfonyStyle $io): ?GrammarDefinitionInterface
    {
        if (!class_exists($grammarClass)) {
            $io->error("Grammar class '{$grammarClass}' does not exist.");
            return null;
        }

        if (!is_subclass_of($grammarClass, GrammarDefinitionInterface::class)) {
            $io->error("Class '{$grammarClass}' must implement GrammarDefinitionInterface.");
            return null;
        }

        return new $grammarClass();
    }

    private function interactiveChoice(SymfonyStyle $io): ?GrammarDefinitionInterface
    {
        $entries = $this->registry->list();

        if (empty($entries)) {
            $io->error('No grammars registered and no grammar-class argument provided.');
            return null;
        }

        $choices = array_map(
            static fn(array $e) => $e['variant'] !== null
                ? "{$e['name']} ({$e['variant']})"
                : $e['name'],
            $entries,
        );

        $chosen = $io->choice('Select grammar', $choices);

        $idx = array_search($chosen, $choices, true);
        $entry = $entries[$idx];

        return $this->registry->getDefinition($entry['name'], $entry['variant']);
    }
}
