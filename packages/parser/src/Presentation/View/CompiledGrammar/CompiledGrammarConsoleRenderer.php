<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\CompiledGrammar;

use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\CompiledEventSubscriberViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\CompiledGrammarViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\CompiledRegionViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\PatternViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\SequenceNodeViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\SequenceViewData;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CompiledGrammarConsoleRenderer
{
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly OutputInterface $output,
        private readonly bool $showPatterns = false,
        private readonly bool $showSequences = false,
        private readonly bool $showEventSubscribers = false,
        private readonly bool $showTags = false,
    ) {}

    public function render(CompiledGrammarViewData $grammar): void
    {
        $this->renderHeader($grammar);
        $this->io->section('Compiled Regions');

        foreach ($grammar->regions as $region) {
            $this->renderRegion($region);
            $this->output->writeln('');
        }
    }

    public function renderSingle(CompiledRegionViewData $region): void
    {
        $this->renderRegion($region);
    }

    private function renderHeader(CompiledGrammarViewData $grammar): void
    {
        $title = $grammar->name . ($grammar->variant ? " <fg=gray>({$grammar->variant})</>" : '');
        $this->io->title("Compiled Grammar: {$title}");

        $this->io->table(
            ['Property', 'Value'],
            [
                ['Name',            "<info>{$grammar->name}</info>"],
                ['Variant',         $grammar->variant ?? '<fg=gray>—</>'],
                ['Root Region',     "<fg=cyan>{$grammar->rootRegionName}</>"],
                ['Require BOF/EOF', $grammar->requireBofEof ? '<fg=green>Yes</>' : '<fg=red>No</>'],
                ['Total Regions',   (string) count($grammar->regions)],
            ],
        );
    }

    private function renderRegion(CompiledRegionViewData $region): void
    {
        $tags = !empty($region->tags)
            ? ' <fg=gray>[' . implode(', ', $region->tags) . ']</>'
            : '';

        $pCount = count($region->patterns);
        $sCount = count($region->sequences);
        $eCount = count($region->eventSubscribers);

        $this->output->writeln(
            "<fg=yellow>📦</> <info>{$region->name}</info>{$tags}"
            . "  <fg=gray>patterns:{$pCount}  sequences:{$sCount}  subscribers:{$eCount}</>",
        );

        if ($this->showPatterns && $pCount > 0) {
            $this->output->writeln('  <fg=gray>Patterns:</>');
            foreach ($region->patterns as $pattern) {
                $this->renderPattern($pattern);
            }
        }

        if ($this->showSequences && $sCount > 0) {
            $this->output->writeln('  <fg=gray>Sequences:</>');
            foreach ($region->sequences as $seq) {
                $this->renderSequence($seq);
            }
        }

        if ($this->showEventSubscribers && $eCount > 0) {
            $this->output->writeln('  <fg=gray>Event Subscribers:</>');
            foreach ($region->eventSubscribers as $sub) {
                $this->renderEventSubscriber($sub);
            }
        }

        if ($this->showTags && !empty($region->tags)) {
            $tagList = implode(', ', array_map(
                static fn(string $t) => "<fg=magenta>{$t}</>",
                $region->tags,
            ));
            $this->output->writeln("  <fg=yellow>Tags:</> {$tagList}");
        }
    }

    private function renderPattern(PatternViewData $p): void
    {
        $tags = !empty($p->tags)
            ? ' <fg=gray>[' . implode(', ', $p->tags) . ']</>'
            : '';
        $pri = $p->priority !== 0 ? " <fg=gray>(p:{$p->priority})</>" : '';

        $this->output->writeln(
            "    <comment>{$p->name}</comment>{$pri}"
            . "  <fg=white>{$p->pattern}</>{$tags}",
        );
    }

    private function renderSequence(SequenceViewData $seq): void
    {
        $root     = $seq->isRoot ? ' <fg=green>(ROOT)</>' : '';
        $nodeType = $seq->nodeType !== null ? " <fg=gray>NodeType:{$seq->nodeType}</>" : '';
        $tags     = !empty($seq->tags)
            ? ' <fg=yellow>[' . implode(', ', $seq->tags) . ']</>'
            : '';
        $pri = $seq->priority !== 0 ? " <fg=gray>(p:{$seq->priority})</>" : '';

        $this->output->writeln(
            "    <comment>{$seq->name}</comment>{$root}{$nodeType}{$tags}{$pri}",
        );

        foreach ($seq->nodes as $idx => $node) {
            $this->renderSequenceNode($node, $idx, '      ');
        }
    }

    private function renderSequenceNode(SequenceNodeViewData $node, int $idx, string $indent): void
    {
        $card      = $this->cardinalityLabel($node->min, $node->max);
        $lookahead = $node->isLookahead ? ' <fg=cyan>[LA]</>' : '';
        $lookbehind = $node->isLookbehind ? ' <fg=cyan>[LB]</>' : '';

        if ($node->type === SequenceNodeViewData::TYPE_SIMPLE) {
            $opts     = implode(' <fg=gray>|</> ', $node->alternatives);
            $nodeType = $node->nodeType !== null ? " <fg=gray>NodeType:{$node->nodeType}</>" : '';
            $tags     = !empty($node->tags)
                ? ' <fg=yellow>[' . implode(', ', $node->tags) . ']</>'
                : '';

            $this->output->writeln(
                "{$indent}<fg=gray>[{$idx}]</> {$opts}{$card}{$nodeType}{$tags}{$lookahead}{$lookbehind}",
            );
        } else {
            $altCount = count($node->nestedAlternatives);
            $this->output->writeln(
                "{$indent}<fg=gray>[{$idx}]</> <fg=cyan>(NESTED {$altCount} alt)</>{$card}{$lookahead}{$lookbehind}",
            );

            foreach ($node->nestedAlternatives as $altIdx => $altNodes) {
                if ($altCount > 1) {
                    $this->output->writeln("{$indent}  <fg=magenta>alt {$altIdx}:</>");
                }
                $childIndent = $altCount > 1 ? $indent . '    ' : $indent . '  ';
                foreach ($altNodes as $nIdx => $nestedNode) {
                    $this->renderSequenceNode($nestedNode, $nIdx, $childIndent);
                }
            }
        }
    }

    private function renderEventSubscriber(CompiledEventSubscriberViewData $sub): void
    {
        $pri  = $sub->priority !== 0 ? " <fg=gray>(p:{$sub->priority})</>" : '';
        $only = $sub->onlyForRule !== null ? " <fg=gray>[rule:{$sub->onlyForRule}]</>" : '';

        $this->output->writeln(
            "    <fg=cyan>{$sub->eventShortName}</> → <comment>{$sub->listenerShortName}</comment>"
            . " <fg=gray>({$sub->details})</>{$pri}{$only}",
        );
    }

    private function cardinalityLabel(int $min, int $max): string
    {
        $maxStr = $max === PHP_INT_MAX ? '∞' : (string) $max;
        return match (true) {
            $min === 0 && $max === 1         => ' <fg=gray>?</>',
            $min === 0 && $max === PHP_INT_MAX => ' <fg=gray>*</>',
            $min === 1 && $max === PHP_INT_MAX => ' <fg=gray>+</>',
            $min === 1 && $max === 1          => '',
            default                           => " <fg=gray>{$min}..{$maxStr}</>",
        };
    }
}
