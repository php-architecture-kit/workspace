<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\Controller\CLI;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Parser;
use PhpArchitecture\Parser\Foundation\Parsing\Context\DefaultParsingContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Infrastructure\Grammar\Registry\InMemoryGrammarRegistry;
use PhpArchitecture\Parser\Presentation\Controller\CLI\Support\GrammarSelector;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\CompiledGrammarConsoleRenderer;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\CompiledGrammarViewFactory;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\CompiledRegionViewData;
use PhpArchitecture\Parser\Presentation\View\ParseTree\ParseTreeRenderer;
use PhpArchitecture\Parser\Presentation\View\ParseTree\ParseTreeViewFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ParseCommand extends Command
{
    public function __construct(
        private readonly InMemoryGrammarRegistry $registry = new InMemoryGrammarRegistry(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('parser:parse')
            ->setDescription('Parse input using specified grammar')
            ->addArgument('input-file', InputArgument::REQUIRED, 'Path to input file to parse')
            ->addArgument('grammar-class', InputArgument::OPTIONAL, 'FQCN of the grammar definition (interactive if omitted)')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (default: stdout)')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: tree, json, simple', 'tree')
            ->addOption('max-depth', 'd', InputOption::VALUE_OPTIONAL, 'Maximum depth to display', null)
            ->addOption('colored-regions', null, InputOption::VALUE_NONE, 'Display source with colored background per node name instead of tree');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $inputFile  = $input->getArgument('input-file');
        $outputFile = $input->getOption('output');
        $format     = $input->getOption('format');
        $maxDepth   = $input->getOption('max-depth') !== null ? (int) $input->getOption('max-depth') : null;

        $selector   = new GrammarSelector($this->registry);
        $definition = $selector->resolve($input->getArgument('grammar-class'), $io);
        if ($definition === null) {
            return Command::FAILURE;
        }

        if (!file_exists($inputFile)) {
            $io->error("Input file '{$inputFile}' does not exist.");
            return Command::FAILURE;
        }

        $compiledGrammar = (new GrammarCompiler())->compile($definition->grammar());
        $parseTree       = (new Parser())->parse(
            new StringStream(file_get_contents($inputFile)),
            new DefaultParsingContext($compiledGrammar),
        );

        $viewData = (new ParseTreeViewFactory())->fromNode($parseTree);
        $renderer = new ParseTreeRenderer();

        if ($input->getOption('colored-regions')) {
            $uniqueNames = $renderer->buildUniqueNodeNames($viewData);

            $indexedNames = array_values(array_keys($uniqueNames));

            $rows = [];
            foreach ($indexedNames as $i => $name) {
                $rows[] = [$i + 1, $name, $uniqueNames[$name]];
            }
            $io->table(['#', 'Node name', 'Occurrences'], $rows);

            $selectedNames = [];
            while (empty($selectedNames)) {
                $answer = $io->ask('Enter number(s) to colorize (comma-separated)', '1');

                foreach (explode(',', $answer ?? '1') as $part) {
                    $idx = (int) trim($part) - 1;
                    if (isset($indexedNames[$idx])) {
                        $selectedNames[] = $indexedNames[$idx];
                    }
                }

                if (empty($selectedNames)) {
                    $io->error('No valid numbers entered. Use numbers from the # column.');
                }
            }

            $this->renderCompiledGrammarDetails($compiledGrammar, $selectedNames, $io, $output);

            $formattedOutput = $renderer->renderColoredSelected($viewData, $selectedNames);
        } else {
            $formattedOutput = match ($format) {
                'json'    => $renderer->renderJson($viewData),
                'simple'  => $renderer->renderSimple($viewData),
                default   => $renderer->renderTree($viewData, $maxDepth),
            };
        }

        if ($outputFile) {
            file_put_contents($outputFile, $formattedOutput);
            $io->success("Parse output saved to: {$outputFile}");
        } else {
            $output->write($formattedOutput);
        }

        return Command::SUCCESS;
    }

    /**
     * @param string[] $selectedNames
     */
    private function renderCompiledGrammarDetails(
        CompiledGrammar $compiledGrammar,
        array $selectedNames,
        SymfonyStyle $io,
        OutputInterface $output,
    ): void {
        $factory  = new CompiledGrammarViewFactory();
        $cgRenderer = new CompiledGrammarConsoleRenderer(
            $io,
            $output,
            showPatterns: true,
            showSequences: true,
            showEventSubscribers: true,
            showTags: true,
        );

        foreach ($selectedNames as $name) {
            $matched = [];
            foreach ($compiledGrammar->regions as $regionName => $region) {
                foreach ($region->sequenceLibrary->sequences as $sequence) {
                    if ($sequence->name === $name) {
                        $matched[$regionName] = $region;
                        break;
                    }
                }
                if ($region->sequenceLibrary->rootSequence?->name === $name) {
                    $matched[$regionName] = $region;
                }
            }

            if (empty($matched)) {
                $output->writeln("<fg=gray>No compiled grammar entry found for node [{$name}].</>");
                continue;
            }

            $io->section("Compiled grammar: [{$name}]");
            foreach ($matched as $regionName => $region) {
                $regionView = $factory->fromRegion($region);
                $matchingSequences = array_filter(
                    $regionView->sequences,
                    static fn($s) => $s->name === $name,
                );
                foreach ($matchingSequences as $seq) {
                    $output->writeln("<fg=gray>in region: {$regionName}</>");
                    $cgRenderer->renderSingle(new CompiledRegionViewData(
                        name: $regionView->name,
                        tags: $regionView->tags,
                        patterns: [],
                        sequences: [$seq],
                        eventSubscribers: [],
                    ));
                    $output->writeln('');
                }
            }
        }
    }
}
