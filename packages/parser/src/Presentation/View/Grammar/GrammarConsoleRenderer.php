<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Grammar;

use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\EventSubscriberViewData;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\GrammarViewData;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\MiddlewareViewData;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\RegionViewData;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\RuleViewData;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GrammarConsoleRenderer
{
    private const TREE_PIPE     = '│   ';
    private const TREE_BRANCH   = '├── ';
    private const TREE_LAST     = '└── ';
    private const TREE_EMPTY    = '    ';

    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly OutputInterface $output,
        private readonly bool $showRules = false,
        private readonly bool $showMiddlewares = true,
        private readonly bool $showEventSubscribers = true,
        private readonly bool $showTags = false,
        private readonly int $maxDepth = PHP_INT_MAX,
    ) {}

    public function render(GrammarViewData $grammar): void
    {
        $this->renderHeader($grammar);
        $this->io->section('Regions Tree');
        $this->renderRegion($grammar->globalRegion, '', '', 0);
    }

    public function renderSingle(RegionViewData $region): void
    {
        $this->renderRegion($region, '', '', 0);
    }

    private function renderHeader(GrammarViewData $grammar): void
    {
        $title = $grammar->name . ($grammar->variant ? " <fg=gray>({$grammar->variant})</>" : '');
        $this->io->title("Grammar Definition: {$title}");

        $this->io->table(
            ['Property', 'Value'],
            [
                ['Name',            "<info>{$grammar->name}</info>"],
                ['Variant',         $grammar->variant ?? '<fg=gray>—</>'],
                ['Root Region',     "<fg=cyan>{$grammar->rootRegionName}</>"],
                ['Require BOF/EOF', $grammar->requireBofEof ? '<fg=green>Yes</>' : '<fg=red>No</>'],
                ['Total Regions',   (string) $grammar->totalRegions],
            ],
        );
    }

    /**
     * @param string $indent   — continuation characters from all ancestor levels (e.g. "│   │   ")
     * @param string $connector — the branch character for THIS node (e.g. "├── " or "└── ")
     */
    private function renderRegion(RegionViewData $region, string $indent, string $connector, int $depth): void
    {
        $nodeTypeColor = $this->nodeTypeColor($region->nodeType);
        $tags = !empty($region->tags) ? ' <fg=gray>[' . implode(', ', $region->tags) . ']</>' : '';

        $nodeLine = "<fg=yellow>📦</> <info>{$region->name}</info>"
            . " <{$nodeTypeColor}>{$region->nodeType}</>"
            . $tags;

        $this->output->writeln($indent . $connector . $nodeLine);

        // Continuation for everything below this node
        $childIndent = $connector === ''
            ? $indent
            : $indent . (($connector === self::TREE_BRANCH) ? self::TREE_PIPE : self::TREE_EMPTY);

        if ($depth >= $this->maxDepth) {
            $this->output->writeln($childIndent . self::TREE_LAST . '<fg=gray>… (max depth reached)</>');
            return;
        }

        $hasNested   = !empty($region->nestedRegions);
        $detailLines = $this->collectRegionDetails($region, $depth);

        foreach ($detailLines as $line) {
            $this->output->writeln($childIndent . '  ' . $line);
        }

        if ($hasNested) {
            $nestedCount = count($region->nestedRegions);
            $this->output->writeln($childIndent . '  ' . "<fg=gray>Nested Regions ({$nestedCount}):</>");

            $nestedIndent = $childIndent . '  ';
            foreach ($region->nestedRegions as $idx => $nested) {
                $isLastNested    = ($idx === $nestedCount - 1);
                $nestedConnector = $isLastNested ? self::TREE_LAST : self::TREE_BRANCH;
                $this->renderRegion($nested, $nestedIndent, $nestedConnector, $depth + 1);
            }
        }
    }

    /**
     * @return string[]
     */
    private function collectRegionDetails(RegionViewData $region, int $depth): array
    {
        $lines = [];

        if ($region->rootSequence !== null) {
            $lines[] = "<fg=cyan>Root Sequence:</> <fg=white>{$region->rootSequence}</>";
        }

        if ($region->opener !== null) {
            $lines[] = "<fg=cyan>Opener:</> <comment>{$region->opener}</comment>"
                . " <fg=gray>on {$region->openerEvent}</>";
        }

        if ($region->closer !== null) {
            $lines[] = "<fg=cyan>Closer:</> <comment>{$region->closer}</comment>"
                . " <fg=gray>on {$region->closerEvent}</>";
        }

        if ($region->innerGrammar !== null) {
            $mode = $region->innerGrammarRetokenize ? 'retokenize' : 'merge';
            $lines[] = "<fg=cyan>InnerGrammar:</> <comment>{$region->innerGrammar}</comment>"
                . " <fg=gray>({$mode})</>";
        }

        if ($region->inheritanceInfo !== '') {
            $lines[] = "<fg=cyan>Inheritance:</> {$region->inheritanceInfo}";
        }

        if ($this->showTags && !empty($region->tags)) {
            $lines[] = '<fg=yellow>Tags:</> ' . implode(', ', array_map(
                static fn(string $t) => "<fg=magenta>{$t}</>",
                $region->tags,
            ));
        }

        if (!empty($region->rules)) {
            $ruleNames = array_map(static fn(RuleViewData $r) => $r->name, $region->rules);

            if ($this->showRules) {
                $lines[] = "<fg=gray>Rules (" . count($region->rules) . "):</>";
                foreach ($region->rules as $rule) {
                    $lines[] = $this->formatRuleDetail($rule);
                    if ($this->showTags && !empty($rule->tags)) {
                        $lines[] = '     <fg=gray>tags:</> ' . implode(', ', array_map(
                            static fn(string $t) => "<fg=magenta>{$t}</>",
                            $rule->tags,
                        ));
                    }
                }
            } else {
                $lines[] = '<fg=gray>Rules (' . count($region->rules) . '):</> '
                    . implode(', ', $ruleNames);
            }
        }

        if ($this->showMiddlewares && !empty($region->middlewares)) {
            $grouped = [];
            foreach ($region->middlewares as $mw) {
                $grouped[$mw->hookName][] = $mw;
            }
            $lines[] = '<fg=gray>Middlewares (' . count($region->middlewares) . '):</>';
            foreach ($grouped as $hook => $list) {
                $lines[] = "  <fg=cyan>{$hook}</>:";
                foreach ($list as $mw) {
                    $pri = $mw->priority !== 0 ? " <fg=gray>(priority: {$mw->priority})</>" : '';
                    $lines[] = "    <fg=gray>-</> {$mw->shortClassName}{$pri}";
                }
            }
        }

        if ($this->showEventSubscribers && !empty($region->eventSubscribers)) {
            $lines[] = '<fg=gray>Event Subscribers (' . count($region->eventSubscribers) . '):</>';
            foreach ($region->eventSubscribers as $sub) {
                $lines[] = $this->formatEventSubscriber($sub);
            }
        }

        return $lines;
    }

    private function formatRuleDetail(RuleViewData $rule): string
    {
        $nodeType = $rule->nodeType !== null ? " <fg=gray>NodeType:{$rule->nodeType}</>" : '';
        $priority = $rule->priority !== 0 ? " <fg=gray>(p:{$rule->priority})</>" : '';
        $seq      = $rule->sequenceDefinition !== null
            ? " <fg=white>→ {$rule->sequenceDefinition}</>"
            : '';

        return "  <comment>{$rule->name}</comment> <fg=gray>[{$rule->type}]</>{$nodeType}{$priority}{$seq}";
    }

    private function formatEventSubscriber(EventSubscriberViewData $sub): string
    {
        $priority = $sub->priority !== 0 ? " <fg=gray>(p:{$sub->priority})</>" : '';
        $only     = $sub->onlyForRule !== null ? " <fg=gray>[rule:{$sub->onlyForRule}]</>" : '';

        return "  <fg=cyan>{$sub->eventShortName}</> → <comment>{$sub->listenerShortName}</comment>{$priority}{$only}";
    }

    private function writePrefixed(array $prefixes, string $line): void
    {
        $this->output->writeln(implode('', $prefixes) . $line);
    }

    private function nodeTypeColor(string $nodeType): string
    {
        return match ($nodeType) {
            'Node'      => 'fg=green',
            'Raw'       => 'fg=blue',
            'Structure' => 'fg=magenta',
            default     => 'fg=white',
        };
    }
}
