<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\Controller\CLI;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Tokenization\Context\TokenizationContextCompiler;
use PhpArchitecture\Parser\Foundation\Tokenization\Lexer;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Infrastructure\Grammar\Registry\InMemoryGrammarRegistry;
use PhpArchitecture\Parser\Presentation\Controller\CLI\Support\GrammarSelector;
use PhpArchitecture\Parser\Presentation\View\Tokenization\TokenizationRenderer;
use PhpArchitecture\Parser\Presentation\View\Tokenization\TokenizationViewFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class TokenizeCommand extends Command
{
    public function __construct(
        private readonly InMemoryGrammarRegistry $registry = new InMemoryGrammarRegistry(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('parser:tokenize')
            ->setDescription('Tokenize input using specified grammar')
            ->addArgument('input-file', InputArgument::REQUIRED, 'Path to input file to tokenize')
            ->addArgument('grammar-class', InputArgument::OPTIONAL, 'FQCN of the grammar definition (interactive if omitted)')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (default: stdout)')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: simple, detailed, stats', 'detailed')
            ->addOption('no-row-col', null, InputOption::VALUE_NONE, 'Disable row/column tracking')
            ->addOption('colored-regions', null, InputOption::VALUE_NONE, 'Display source with colored background per region instead of token list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $inputFile  = $input->getArgument('input-file');
        $outputFile = $input->getOption('output');
        $format     = $input->getOption('format');

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
        $context         = (new TokenizationContextCompiler())->compile(
            $compiledGrammar,
            !$input->getOption('no-row-col'),
        );

        $tokenRegion = (new Lexer($context))->process(new StringStream(file_get_contents($inputFile)));

        $viewData = (new TokenizationViewFactory())->fromTokenRegion($tokenRegion);
        $renderer = new TokenizationRenderer();

        $coloredRegions = $input->getOption('colored-regions');

        $formattedOutput = match (true) {
            $coloredRegions          => $renderer->renderColoredRegions($viewData),
            $format === 'simple'     => $renderer->renderSimple($viewData),
            $format === 'stats'      => $renderer->renderStats($viewData),
            default                  => $renderer->renderDetailed($viewData),
        };

        if ($outputFile) {
            file_put_contents($outputFile, $formattedOutput);
            if ($viewData->hasUnknownTokens()) {
                $io->error("Tokenization completed with " . count($viewData->unknownTokens) . " unknown token(s). Output saved to: {$outputFile}");
            } else {
                $io->success("Tokenization output saved to: {$outputFile}");
            }
        } else {
            $output->write($formattedOutput);
            if ($viewData->hasUnknownTokens()) {
                $io->error("Tokenization completed with " . count($viewData->unknownTokens) . " unknown token(s).");
            }
        }

        return $viewData->hasUnknownTokens() ? Command::FAILURE : Command::SUCCESS;
    }
}
