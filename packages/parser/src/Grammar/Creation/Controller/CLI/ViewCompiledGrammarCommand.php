<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Creation\Controller\CLI;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledRegion;
use PhpArchitecture\Parser\Grammar\Registry\GrammarDefinitionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ViewCompiledGrammarCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('parser:grammar:compiled')
            ->setDescription('Display compiled grammar structure')
            ->addArgument('grammar-class', InputArgument::REQUIRED, 'Fully qualified class name of the grammar definition')
            ->addOption('region', 'r', InputOption::VALUE_OPTIONAL, 'Show only specific region')
            ->addOption('show-patterns', null, InputOption::VALUE_NONE, 'Show pattern library details')
            ->addOption('show-sequences', null, InputOption::VALUE_NONE, 'Show sequence library details')
            ->addOption('show-event-subscribers', null, InputOption::VALUE_NONE, 'Show event subscribers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $grammarClass = $input->getArgument('grammar-class');
        $regionFilter = $input->getOption('region');
        $showPatterns = $input->getOption('show-patterns');
        $showSequences = $input->getOption('show-sequences');
        $showEventSubscribers = $input->getOption('show-event-subscribers');

        if (!class_exists($grammarClass)) {
            $io->error("Grammar class '{$grammarClass}' does not exist.");
            return Command::FAILURE;
        }

        if (!is_subclass_of($grammarClass, GrammarDefinitionInterface::class)) {
            $io->error("Class '{$grammarClass}' must implement GrammarDefinitionInterface.");
            return Command::FAILURE;
        }

        $grammarDefinition = new $grammarClass();
        $grammar = $grammarDefinition->grammar();
        
        $compiler = new GrammarCompiler();
        $compiledGrammar = $compiler->compile($grammar);

        $this->displayCompiledGrammarHeader($io, $compiledGrammar);

        if ($regionFilter) {
            if (!isset($compiledGrammar->regions[$regionFilter])) {
                $io->error("Region '{$regionFilter}' not found in compiled grammar.");
                return Command::FAILURE;
            }
            $this->displayCompiledRegion($io, $regionFilter, $compiledGrammar->regions[$regionFilter], $showPatterns, $showSequences, $showEventSubscribers);
        } else {
            $this->displayAllCompiledRegions($io, $compiledGrammar, $showPatterns, $showSequences, $showEventSubscribers);
        }

        return Command::SUCCESS;
    }

    private function displayCompiledGrammarHeader(SymfonyStyle $io, CompiledGrammar $grammar): void
    {
        $io->title('Compiled Grammar: ' . $grammar->name . ($grammar->variant ? " ({$grammar->variant})" : ''));
        
        $io->section('Compiled Grammar Information');
        $io->table(
            ['Property', 'Value'],
            [
                ['Name', $grammar->name],
                ['Variant', $grammar->variant ?? 'N/A'],
                ['Root Region', $grammar->rootRegionName],
                ['Require BOF/EOF', $grammar->requireBofEof ? 'Yes' : 'No'],
                ['Total Regions', count($grammar->regions)],
            ]
        );
    }

    private function displayAllCompiledRegions(SymfonyStyle $io, CompiledGrammar $grammar, bool $showPatterns, bool $showSequences, bool $showEventSubscribers): void
    {
        $io->section('Compiled Regions');
        
        foreach ($grammar->regions as $regionName => $region) {
            $this->displayCompiledRegion($io, $regionName, $region, $showPatterns, $showSequences, $showEventSubscribers);
            $io->newLine();
        }
    }

    private function displayCompiledRegion(SymfonyStyle $io, string $name, CompiledRegion $region, bool $showPatterns, bool $showSequences, bool $showEventSubscribers): void
    {
        $io->writeln("📦 <info>{$name}</info>");
        
        $patternsCount = count($region->patternLibrary->patterns);
        $sequencesCount = count($region->sequenceLibrary->sequences);
        $subscribersCount = count($region->eventSubscribers);
        
        $io->writeln("  Patterns: {$patternsCount}");
        $io->writeln("  Sequences: {$sequencesCount}");
        $io->writeln("  Event Subscribers: {$subscribersCount}");

        if ($showPatterns && $patternsCount > 0) {
            $io->writeln("\n  <comment>Pattern Library:</comment>");
            foreach ($region->patternLibrary->patterns as $tokenName => $pattern) {
                $tags = count($pattern->tags) > 0 ? ' [' . implode(', ', $pattern->tags) . ']' : '';
                $io->writeln("    - {$tokenName}: {$pattern->pattern} (priority: {$pattern->priority}){$tags}");
            }
        }

        if ($showSequences && $sequencesCount > 0) {
            $io->writeln("\n  <comment>Sequence Library:</comment>");
            foreach ($region->sequenceLibrary->sequences as $sequenceName => $sequence) {
                $io->writeln("    - {$sequenceName}:");
                foreach ($sequence->nodes as $idx => $node) {
                    if ($node instanceof \PhpArchitecture\Parser\Processing\Model\Matching\SequenceNode) {
                        $options = implode(' | ', $node->alternatives);
                        $cardinality = "min:{$node->min}, max:{$node->max}";
                        $io->writeln("      [{$idx}] ({$options}) - {$cardinality}");
                    }
                }
            }
        }

        if ($showEventSubscribers && $subscribersCount > 0) {
            $io->writeln("\n  <comment>Event Subscribers:</comment>");
            foreach ($region->eventSubscribers as $subscriber) {
                $listenerClass = is_object($subscriber->listener) ? get_class($subscriber->listener) : 'Closure';
                $ruleName = $subscriber->onlyForRuleName ?? 'all';
                $io->writeln("    - {$subscriber->eventClassName} → {$listenerClass} (rule: {$ruleName})");
            }
        }
    }
}
