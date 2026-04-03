<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Creation\Controller\CLI;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Grammar\Registry\GrammarDefinitionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ViewGrammarCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('parser:grammar:view')
            ->setDescription('Display grammar definition structure')
            ->addArgument('grammar-class', InputArgument::REQUIRED, 'Fully qualified class name of the grammar definition')
            ->addOption('region', 'r', InputOption::VALUE_OPTIONAL, 'Show only specific region')
            ->addOption('show-rules', null, InputOption::VALUE_NONE, 'Show detailed rules information')
            ->addOption('show-event-subscribers', null, InputOption::VALUE_NONE, 'Show event subscribers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $grammarClass = $input->getArgument('grammar-class');
        $regionFilter = $input->getOption('region');
        $showRules = $input->getOption('show-rules');
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

        $this->displayGrammarHeader($io, $grammar);

        if ($regionFilter) {
            $allRegions = $grammar->getAllRegions();
            if (!isset($allRegions[$regionFilter])) {
                $io->error("Region '{$regionFilter}' not found in grammar.");
                return Command::FAILURE;
            }
            $this->displayRegion($io, $allRegions[$regionFilter], $showRules, $showEventSubscribers, 0);
        } else {
            $this->displayAllRegions($io, $grammar, $showRules, $showEventSubscribers);
        }

        return Command::SUCCESS;
    }

    private function displayGrammarHeader(SymfonyStyle $io, Grammar $grammar): void
    {
        $io->title('Grammar Definition: ' . $grammar->name . ($grammar->variant ? " ({$grammar->variant})" : ''));
        
        $io->section('Grammar Information');
        $io->table(
            ['Property', 'Value'],
            [
                ['Name', $grammar->name],
                ['Variant', $grammar->variant ?? 'N/A'],
                ['Root Region', $grammar->rootRegion->name],
                ['Require BOF/EOF', $grammar->requireBofEof ? 'Yes' : 'No'],
                ['Total Regions', count($grammar->getAllRegions())],
            ]
        );
    }

    private function displayAllRegions(SymfonyStyle $io, Grammar $grammar, bool $showRules, bool $showEventSubscribers): void
    {
        $io->section('Regions Structure');
        $this->displayRegion($io, $grammar->global, $showRules, $showEventSubscribers, 0);
    }

    private function displayRegion(SymfonyStyle $io, Region $region, bool $showRules, bool $showEventSubscribers, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $io->writeln($indent . "📦 <info>{$region->name}</info>");

        if ($showRules && count($region->rules) > 0) {
            $io->writeln($indent . "  Rules (" . count($region->rules) . "):");
            foreach ($region->rules as $rule) {
                $this->displayRule($io, $rule, $depth + 2);
            }
        } elseif (count($region->rules) > 0) {
            $io->writeln($indent . "  Rules: " . count($region->rules) . " (" . implode(', ', array_keys($region->rules)) . ")");
        }

        if ($showEventSubscribers && count($region->eventSubscribers) > 0) {
            $io->writeln($indent . "  Event Subscribers (" . count($region->eventSubscribers) . "):");
            foreach ($region->eventSubscribers as $subscriber) {
                $listenerClass = is_object($subscriber->listener) ? get_class($subscriber->listener) : 'Closure';
                $io->writeln($indent . "    - {$subscriber->eventClassName} → {$listenerClass}");
            }
        }

        if (count($region->regions) > 0) {
            $io->writeln($indent . "  Nested Regions (" . count($region->regions) . "):");
            foreach ($region->regions as $nestedRegion) {
                $this->displayRegion($io, $nestedRegion, $showRules, $showEventSubscribers, $depth + 2);
            }
        }
    }

    private function displayRule(SymfonyStyle $io, Rule $rule, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $type = $rule->type->name;
        $tags = count($rule->getAllTags()) > 0 ? ' [' . implode(', ', $rule->getAllTags()) . ']' : '';
        $priority = $rule->priority !== 0 ? " (priority: {$rule->priority})" : '';
        
        $io->writeln($indent . "- <comment>{$rule->name}</comment> ({$type}){$tags}{$priority}");
        
        if (count($rule->eventSubscribers) > 0) {
            $io->writeln($indent . "  Event Subscribers: " . count($rule->eventSubscribers));
        }
    }
}
