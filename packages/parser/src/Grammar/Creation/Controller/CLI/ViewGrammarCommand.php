<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Creation\Controller\CLI;

use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Grammar\Registry\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;
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
            ->addOption('hide-middlewares', null, InputOption::VALUE_NONE, 'Hide middlewares information')
            ->addOption('hide-event-subscribers', null, InputOption::VALUE_NONE, 'Hide event subscribers information')
            ->addOption('show-tags', null, InputOption::VALUE_NONE, 'Show tags with their associated rules and regions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $grammarClass = $input->getArgument('grammar-class');
        $regionFilter = $input->getOption('region');
        $showRules = $input->getOption('show-rules');
        $showMiddlewares = !$input->getOption('hide-middlewares');
        $showEventSubscribers = !$input->getOption('hide-event-subscribers');
        $showTags = $input->getOption('show-tags');

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
            $this->displayRegion($io, $allRegions[$regionFilter], $showRules, $showMiddlewares, $showEventSubscribers, $showTags, 0);
        } else {
            $this->displayAllRegions($io, $grammar, $showRules, $showMiddlewares, $showEventSubscribers, $showTags);
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

    private function displayAllRegions(SymfonyStyle $io, Grammar $grammar, bool $showRules, bool $showMiddlewares, bool $showEventSubscribers, bool $showTags): void
    {
        $io->section('Regions Structure');
        $this->displayRegion($io, $grammar->global, $showRules, $showMiddlewares, $showEventSubscribers, $showTags, 0);
    }

    private function displayRegion(SymfonyStyle $io, Region $region, bool $showRules, bool $showMiddlewares, bool $showEventSubscribers, bool $showTags, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $io->writeln($indent . "📦 <info>{$region->name}</info> <fg=gray>(NodeType: {$region->config->nodeType->name})</>");
        
        $this->displayRegionConfig($io, $region, $depth + 1);

        if ($showRules && count($region->rules) > 0) {
            $io->writeln($indent . "  Rules (" . count($region->rules) . "):");
            foreach ($region->rules as $rule) {
                $this->displayRule($io, $rule, $depth + 2);
            }
        } elseif (count($region->rules) > 0) {
            $io->writeln($indent . "  Rules: " . count($region->rules) . " (" . implode(', ', array_keys($region->rules)) . ")");
        }

        if ($showMiddlewares && count($region->middlewares) > 0) {
            $totalMiddlewares = array_sum(array_map('count', $region->middlewares));
            $io->writeln($indent . "  Middlewares (" . $totalMiddlewares . "):");
            $this->displayMiddlewares($io, $region->middlewares, $depth + 2);
        }

        if ($showEventSubscribers && count($region->eventSubscribers) > 0) {
            $io->writeln($indent . "  Event Subscribers (" . count($region->eventSubscribers) . "):");
            $this->displayEventSubscribers($io, $region->eventSubscribers, $depth + 2);
        }

        if ($showTags) {
            $this->displayRegionTags($io, $region, $depth + 1);
        }

        if (count($region->regions) > 0) {
            $io->writeln($indent . "  Nested Regions (" . count($region->regions) . "):");
            foreach ($region->regions as $nestedRegion) {
                $this->displayRegion($io, $nestedRegion, $showRules, $showMiddlewares, $showEventSubscribers, $showTags, $depth + 2);
            }
        }
    }

    private function displayRule(SymfonyStyle $io, Rule $rule, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $type = $rule->type->name;
        $nodeType = $rule->nodeType ? $rule->nodeType->name : 'null';
        $tags = count($rule->getAllTags()) > 0 ? ' [' . implode(', ', $rule->getAllTags()) . ']' : '';
        $priority = $rule->priority !== 0 ? " (priority: {$rule->priority})" : '';
        
        $io->writeln($indent . "- <comment>{$rule->name}</comment> ({$type}, NodeType: {$nodeType}){$tags}{$priority}");
        
        if ($rule->definition instanceof \PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule) {
            $io->writeln($indent . "  Sequence: " . $rule->definition->toString());
        }
        
        if (count($rule->eventSubscribers) > 0) {
            $io->writeln($indent . "  Event Subscribers: " . count($rule->eventSubscribers));
        }
    }

    /**
     * @param array<string,array<string,GrammarMiddleware>> $middlewares
     */
    private function displayMiddlewares(SymfonyStyle $io, array $middlewares, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        
        foreach ($middlewares as $method => $middlewareList) {
            if (count($middlewareList) === 0) {
                continue;
            }
            
            $io->writeln($indent . "<fg=cyan>{$method}</>:");
            foreach ($middlewareList as $middleware) {
                $className = get_class($middleware);
                $shortName = substr($className, strrpos($className, '\\') + 1);
                $priority = $middleware->priority() !== 0 ? " (priority: {$middleware->priority()})" : '';
                $io->writeln($indent . "  - {$shortName}{$priority}");
            }
        }
    }

    /**
     * @param array<string,EventSubscriber> $eventSubscribers
     */
    private function displayEventSubscribers(SymfonyStyle $io, array $eventSubscribers, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        
        foreach ($eventSubscribers as $subscriber) {
            $listenerClass = is_object($subscriber->listener) ? get_class($subscriber->listener) : 'Closure';
            $lastBackslash = strrpos($listenerClass, '\\');
            $shortListenerName = $lastBackslash !== false ? substr($listenerClass, $lastBackslash + 1) : $listenerClass;
            $eventShortName = $this->getShortEventName($subscriber->eventClassName);
            
            $priority = $subscriber->priority !== 0 ? " <fg=gray>(priority: {$subscriber->priority})</>" : '';
            $onlyFor = $subscriber->onlyForRuleName ? " <fg=gray>[rule: {$subscriber->onlyForRuleName}]</>" : '';
            
            $io->writeln($indent . "- <fg=cyan>{$eventShortName}</> → {$shortListenerName}{$priority}{$onlyFor}");
        }
    }

    private function displayRegionConfig(SymfonyStyle $io, Region $region, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $config = $region->config;

        if ($config->rootSequence !== null) {
            $io->writeln($indent . "<fg=cyan>Root Sequence:</> " . $config->rootSequence->toString());
        }

        if ($config->opener !== null) {
            $openerListener = $config->opener->listener;
            $openerRule = 'unknown';
            if (is_object($openerListener) && property_exists($openerListener, 'rule')) {
                $rule = $openerListener->rule;
                if (is_object($rule) && property_exists($rule, 'name')) {
                    $openerRule = $rule->name;
                }
            }
            $eventName = $this->getShortEventName($config->opener->eventClassName);
            $io->writeln($indent . "<fg=cyan>Opener:</> {$openerRule} <fg=gray>(on {$eventName})</>");
        }

        if ($config->closer !== null) {
            $closerListener = $config->closer->listener;
            $closerRule = 'unknown';
            if (is_object($closerListener) && property_exists($closerListener, 'rule')) {
                $rule = $closerListener->rule;
                if (is_object($rule) && property_exists($rule, 'name')) {
                    $closerRule = $rule->name;
                }
            }
            $eventName = $this->getShortEventName($config->closer->eventClassName);
            $io->writeln($indent . "<fg=cyan>Closer:</> {$closerRule} <fg=gray>(on {$eventName})</>");
        }

        if ($config->innerGrammar !== null) {
            $grammarName = $config->innerGrammar->name;
            if ($config->retokenizeWithInnerGrammar === true) {
                $io->writeln($indent . "<fg=cyan>InnerGrammar:</> {$grammarName} <fg=gray>(retokenize)</>");
            } else {
                $io->writeln($indent . "<fg=cyan>InnerGrammar:</> {$grammarName} <fg=gray>(merge)</>");
            }
        }

        $inheritanceInfo = [];
        if ($config->inheritanceFromGlobal !== Region::NONE) {
            $inheritanceInfo[] = 'Global: ' . $this->formatInheritanceScope($config->inheritanceFromGlobal);
        }
        if ($config->inheritanceFromAncestor !== Region::NONE) {
            $inheritanceInfo[] = 'Ancestor: ' . $this->formatInheritanceScope($config->inheritanceFromAncestor);
        }
        if (!empty($inheritanceInfo)) {
            $io->writeln($indent . "<fg=cyan>Inheritance:</> " . implode(', ', $inheritanceInfo));
        }
    }

    private function formatInheritanceScope(int $scope): string
    {
        $parts = [];
        if (($scope & Region::RULES) === Region::RULES) {
            $parts[] = 'Rules';
        }
        if (($scope & Region::REGIONS) === Region::REGIONS) {
            $parts[] = 'Regions';
        }
        if (($scope & Region::MIDDLEWARES) === Region::MIDDLEWARES) {
            $parts[] = 'Middlewares';
        }
        if (($scope & Region::EVENT_SUBSCRIBERS) === Region::EVENT_SUBSCRIBERS) {
            $parts[] = 'EventSubscribers';
        }
        return implode('+', $parts);
    }

    private function getShortEventName(string $eventClassName): string
    {
        $lastBackslash = strrpos($eventClassName, '\\');
        return $lastBackslash !== false ? substr($eventClassName, $lastBackslash + 1) : $eventClassName;
    }

    private function displayRegionTags(SymfonyStyle $io, Region $region, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        
        // Collect all tags from the region itself, rules and nested regions
        $tagMap = [];
        
        // Tags from the region itself
        foreach ($region->getAllTags() as $tag) {
            if (!isset($tagMap[$tag])) {
                $tagMap[$tag] = ['self' => false, 'rules' => [], 'regions' => []];
            }
            $tagMap[$tag]['self'] = true;
        }
        
        foreach ($region->rules as $rule) {
            foreach ($rule->getAllTags() as $tag) {
                if (!isset($tagMap[$tag])) {
                    $tagMap[$tag] = ['self' => false, 'rules' => [], 'regions' => []];
                }
                $tagMap[$tag]['rules'][] = $rule->name;
            }
        }
        
        foreach ($region->regions as $nestedRegion) {
            foreach ($nestedRegion->getAllTags() as $tag) {
                if (!isset($tagMap[$tag])) {
                    $tagMap[$tag] = ['self' => false, 'rules' => [], 'regions' => []];
                }
                $tagMap[$tag]['regions'][] = $nestedRegion->name;
            }
        }
        
        if (empty($tagMap)) {
            return;
        }
        
        $io->writeln($indent . "<comment>Tags (" . count($tagMap) . "):</comment>");
        foreach ($tagMap as $tag => $usage) {
            $io->writeln($indent . "  - <fg=yellow>{$tag}</>:");
            if ($usage['self']) {
                $io->writeln($indent . "    <fg=magenta>Region: {$region->name}</>");
            }
            if (!empty($usage['rules'])) {
                $io->writeln($indent . "    Rules: " . implode(', ', $usage['rules']));
            }
            if (!empty($usage['regions'])) {
                $io->writeln($indent . "    Regions: " . implode(', ', $usage['regions']));
            }
        }
    }
}
