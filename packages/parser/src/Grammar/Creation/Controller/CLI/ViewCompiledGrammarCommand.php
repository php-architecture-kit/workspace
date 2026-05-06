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
use Closure;

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
            ->addOption('show-event-subscribers', null, InputOption::VALUE_NONE, 'Show event subscribers')
            ->addOption('show-tags', null, InputOption::VALUE_NONE, 'Show tags with their associated patterns and sequences');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $grammarClass = $input->getArgument('grammar-class');
        $regionFilter = $input->getOption('region');
        $showPatterns = $input->getOption('show-patterns');
        $showSequences = $input->getOption('show-sequences');
        $showEventSubscribers = $input->getOption('show-event-subscribers');
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
        
        $compiler = new GrammarCompiler();
        $compiledGrammar = $compiler->compile($grammar);

        $this->displayCompiledGrammarHeader($io, $compiledGrammar);

        if ($regionFilter) {
            if (!isset($compiledGrammar->regions[$regionFilter])) {
                $io->error("Region '{$regionFilter}' not found in compiled grammar.");
                return Command::FAILURE;
            }
            $this->displayCompiledRegion($io, $regionFilter, $compiledGrammar->regions[$regionFilter], $showPatterns, $showSequences, $showEventSubscribers, $showTags, $compiledGrammar->regions);
        } else {
            $this->displayAllCompiledRegions($io, $compiledGrammar, $showPatterns, $showSequences, $showEventSubscribers, $showTags);
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
            ],
        );
    }

    private function displayAllCompiledRegions(SymfonyStyle $io, CompiledGrammar $grammar, bool $showPatterns, bool $showSequences, bool $showEventSubscribers, bool $showTags): void
    {
        $io->section('Compiled Regions');
        
        foreach ($grammar->regions as $regionName => $region) {
            $this->displayCompiledRegion($io, $regionName, $region, $showPatterns, $showSequences, $showEventSubscribers, $showTags, $grammar->regions);
            $io->newLine();
        }
    }

    private function displayCompiledRegion(SymfonyStyle $io, string $name, CompiledRegion $region, bool $showPatterns, bool $showSequences, bool $showEventSubscribers, bool $showTags, array $allRegions = []): void
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
                $escapedPattern = addcslashes($pattern->pattern, "\0..\37");
                $io->writeln("    - {$tokenName}: {$escapedPattern} (priority: {$pattern->priority}){$tags}");
            }
        }

        if ($showSequences && ($sequencesCount > 0 || $region->sequenceLibrary->rootSequence !== null)) {
            $io->writeln("\n  <comment>Sequence Library:</comment>");
            
            if ($region->sequenceLibrary->rootSequence !== null) {
                $rootSeq = $region->sequenceLibrary->rootSequence;
                $sequenceTags = count($rootSeq->tags) > 0 ? ' <fg=yellow>[' . implode(', ', $rootSeq->tags) . ']</>' : '';
                $nodeType = $rootSeq->hasMeta('nodeType') ? $rootSeq->getMeta('nodeType') : null;
                $nodeTypeStr = $nodeType ? " <fg=gray>(NodeType: {$nodeType->name})</>" : '';
                $io->writeln("    - <fg=green>{$rootSeq->name}</> (ROOT){$nodeTypeStr}{$sequenceTags}:");
                foreach ($rootSeq->nodes as $idx => $node) {
                    $this->displaySequenceNode($io, $node, $idx, '      ');
                }
            }
            
            foreach ($region->sequenceLibrary->sequences as $sequenceName => $sequence) {
                $sequenceTags = count($sequence->tags) > 0 ? ' <fg=yellow>[' . implode(', ', $sequence->tags) . ']</>' : '';
                $nodeType = $sequence->hasMeta('nodeType') ? $sequence->getMeta('nodeType') : null;
                $nodeTypeStr = $nodeType ? " <fg=gray>(NodeType: {$nodeType->name})</>" : '';
                $io->writeln("    - {$sequenceName}{$nodeTypeStr}{$sequenceTags}:");
                foreach ($sequence->nodes as $idx => $node) {
                    $this->displaySequenceNode($io, $node, $idx, '      ');
                }
            }
        }

        if ($showEventSubscribers && $subscribersCount > 0) {
            $io->writeln("\n  <comment>Event Subscribers:</comment>");
            foreach ($region->eventSubscribers as $subscriber) {
                $listenerClass = is_object($subscriber->listener) ? get_class($subscriber->listener) : 'Closure';
                $shortListenerName = $this->getShortClassName($listenerClass);
                $shortEventName = $this->getShortClassName($subscriber->eventClassName);
                
                $details = $this->formatListenerDetails($subscriber->listener, $subscriber->onlyForRuleName);
                
                $io->writeln("    - <fg=cyan>{$shortEventName}</> → {$shortListenerName} <fg=gray>{$details}</>");
            }
        }

        if ($showTags) {
            $this->displayCompiledRegionTags($io, $region, $allRegions);
        }
    }

    private function displaySequenceNode(SymfonyStyle $io, object $node, int $idx, string $indent): void
    {
        if ($node instanceof \PhpArchitecture\Parser\Processing\Model\Matching\SequenceNode) {
            $options = implode(' | ', $node->alternatives);
            $cardinality = "min:{$node->min}, max:{$node->max}";
            
            $allTags = $node->tags ?? [];
            
            // Extract NodeType from tags (stored as "NodeType.Node", "NodeType.Raw", etc.)
            $nodeTypeTag = null;
            $otherTags = [];
            foreach ($allTags as $tag) {
                if (str_starts_with($tag, 'NodeType.')) {
                    $nodeTypeTag = substr($tag, 9); // Remove "NodeType." prefix
                } else {
                    $otherTags[] = $tag;
                }
            }
            
            $nodeTypeStr = $nodeTypeTag ? " <fg=gray>(NodeType: {$nodeTypeTag})</>" : '';
            $nodeTags = count($otherTags) > 0 ? ' <fg=yellow>[' . implode(', ', $otherTags) . ']</>' : '';
            $io->writeln("{$indent}[{$idx}] ({$options}) - {$cardinality}{$nodeTypeStr}{$nodeTags}");
        } elseif ($node instanceof \PhpArchitecture\Parser\Processing\Model\Matching\NestedSequence) {
            $cardinality = "min:{$node->min}, max:{$node->max}";
            $alternativesCount = count($node->alternativeSequences);
            $io->writeln("{$indent}[{$idx}] <fg=cyan>(NESTED {$alternativesCount} alternatives)</> - {$cardinality}");
            
            // Display each alternative sequence
            foreach ($node->alternativeSequences as $altIdx => $alternativeNodes) {
                if ($alternativesCount > 1) {
                    $io->writeln("{$indent}  <fg=magenta>Alternative {$altIdx}:</>");
                }
                foreach ($alternativeNodes as $nestedIdx => $nestedNode) {
                    $nestedIndent = $alternativesCount > 1 ? $indent . '    ' : $indent . '  ';
                    $this->displaySequenceNode($io, $nestedNode, $nestedIdx, $nestedIndent);
                }
            }
        }
    }

    private function getShortClassName(string $className): string
    {
        $lastBackslash = strrpos($className, '\\');
        return $lastBackslash !== false ? substr($className, $lastBackslash + 1) : $className;
    }

    private function formatListenerDetails(object $listener, ?string $onlyForRuleName): string
    {
        if ($listener instanceof Closure) {
            return "(rule: " . ($onlyForRuleName ?? 'all') . ")";
        }

        $className = get_class($listener);
        
        // StartRegionEventListener: region, rule
        if (str_ends_with($className, 'StartRegionEventListener')) {
            if (property_exists($listener, 'region') && property_exists($listener, 'rule')) {
                $regionName = $listener->region->name ?? 'unknown';
                $ruleName = $listener->rule->name ?? 'unknown';
                return "(region: {$regionName}, rule: {$ruleName})";
            }
        }
        
        // EndRegionEventListener: rule, negated, allowedForTokenWhichStartedRegion, callLastTokenRemoval
        if (str_ends_with($className, 'EndRegionEventListener')) {
            if (property_exists($listener, 'rule')) {
                $ruleName = $listener->rule->name ?? 'unknown';
                $parts = ["rule: {$ruleName}"];
                
                if (property_exists($listener, 'negated') && $listener->negated) {
                    $parts[] = "negated: true";
                }
                if (property_exists($listener, 'allowedForTokenWhichStartedRegion') && $listener->allowedForTokenWhichStartedRegion) {
                    $parts[] = "allowStartToken: true";
                }
                if (property_exists($listener, 'callLastTokenRemoval') && !$listener->callLastTokenRemoval) {
                    $parts[] = "removeToken: false";
                }
                
                return "(" . implode(', ', $parts) . ")";
            }
        }
        
        // Default fallback
        return "(rule: " . ($onlyForRuleName ?? 'all') . ")";
    }

    private function displayCompiledRegionTags(SymfonyStyle $io, CompiledRegion $region, array $allRegions): void
    {
        // Collect all tags from the region itself, patterns and sequences
        $tagMap = [];
        
        // Tags from the region itself
        foreach ($region->tags as $tag) {
            if (!isset($tagMap[$tag])) {
                $tagMap[$tag] = ['self' => false, 'patterns' => [], 'sequences' => [], 'otherRegions' => []];
            }
            $tagMap[$tag]['self'] = true;
        }
        
        foreach ($region->patternLibrary->patterns as $patternName => $pattern) {
            foreach ($pattern->tags as $tag) {
                if (!isset($tagMap[$tag])) {
                    $tagMap[$tag] = ['self' => false, 'patterns' => [], 'sequences' => [], 'otherRegions' => []];
                }
                $tagMap[$tag]['patterns'][] = $patternName;
            }
        }
        
        foreach ($region->sequenceLibrary->sequences as $sequenceName => $sequence) {
            foreach ($sequence->tags as $tag) {
                if (!isset($tagMap[$tag])) {
                    $tagMap[$tag] = ['self' => false, 'patterns' => [], 'sequences' => [], 'otherRegions' => []];
                }
                $tagMap[$tag]['sequences'][] = $sequenceName;
            }
        }
        
        // Find other regions that use the same tags
        foreach ($allRegions as $otherRegionName => $otherRegion) {
            if ($otherRegionName === $region->name) {
                continue;
            }
            
            foreach ($otherRegion->tags as $tag) {
                if (isset($tagMap[$tag])) {
                    $tagMap[$tag]['otherRegions'][] = $otherRegionName;
                }
            }
        }
        
        if (empty($tagMap)) {
            return;
        }
        
        $io->writeln("\n  <comment>Tags (" . count($tagMap) . "):</comment>");
        foreach ($tagMap as $tag => $usage) {
            $io->writeln("    - <fg=yellow>{$tag}</>:");
            if ($usage['self']) {
                $io->writeln("      <fg=magenta>Region: {$region->name}</>");
            }
            if (!empty($usage['patterns'])) {
                $io->writeln("      Patterns: " . implode(', ', $usage['patterns']));
            }
            if (!empty($usage['sequences'])) {
                $io->writeln("      Sequences: " . implode(', ', $usage['sequences']));
            }
            if (!empty($usage['otherRegions'])) {
                $io->writeln("      <fg=magenta>Other Regions: " . implode(', ', $usage['otherRegions']) . "</>");
            }
        }
    }
}
