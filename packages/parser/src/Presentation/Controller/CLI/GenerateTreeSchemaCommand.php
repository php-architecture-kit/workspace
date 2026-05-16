<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\Controller\CLI;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Parser;
use PhpArchitecture\Parser\Foundation\Parsing\Context\DefaultParsingContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Infrastructure\Grammar\Registry\InMemoryGrammarRegistry;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\TreeSchemaGenerator;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\TreeSchemaRenderer;
use PhpArchitecture\Parser\Presentation\Controller\CLI\Support\GrammarSelector;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GenerateTreeSchemaCommand extends Command
{
    public function __construct(
        private readonly InMemoryGrammarRegistry $registry = new InMemoryGrammarRegistry(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('tree:generate')
            ->setDescription('Generate Tree Schema based on input file and grammar definition')
            ->addArgument('input-file', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Path(s) to input file(s) to parse as source of truth about tree schema')
            ->addOption('grammar', 'g', InputOption::VALUE_OPTIONAL, 'FQCN of the grammar definition (interactive if omitted)')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output dir path (default: stdout)')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'Namespace for generated classes (default: PhpArchitecture\\Parser\\Infrastructure\\TreeSchema\\Model\\{Format})');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $inputFiles = $input->getArgument('input-file');
        $outputDir  = $input->getOption('output');

        $selector   = new GrammarSelector($this->registry);
        $definition = $selector->resolve($input->getOption('grammar'), $io);
        if ($definition === null) {
            return Command::FAILURE;
        }

        foreach ($inputFiles as $inputFile) {
            if (!file_exists($inputFile)) {
                $io->error("Input file '{$inputFile}' does not exist.");
                return Command::FAILURE;
            }
        }

        $compiledGrammar = (new GrammarCompiler())->compile($definition->grammar());

        $format    = implode('', array_map('ucfirst', preg_split('/[-_\s]+/', $compiledGrammar->name) ?: [$compiledGrammar->name]));
        $namespace = $input->getOption('namespace')
            ?? 'PhpArchitecture\\Parser\\Infrastructure\\TreeSchema\\Model\\' . $format;

        $generator = new TreeSchemaGenerator();
        $parser    = new Parser();
        $templates = [];
        foreach ($inputFiles as $inputFile) {
            $parseTree = $parser->parse(
                new StringStream(file_get_contents($inputFile)),
                new DefaultParsingContext($compiledGrammar),
            );
            $templates = $generator->generate($parseTree, $namespace);
        }

        $renderer = new TreeSchemaRenderer();
        foreach ($templates as $template) {
            $className = $template->classStmt->className;
            $code      = $renderer->render($template);

            if ($outputDir !== null) {
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }
                file_put_contents(rtrim($outputDir, '/') . '/' . $className . '.php', $code);
                $io->success("Generated: {$className}.php");
            } else {
                $io->writeln("<comment>=== {$className}.php ===</comment>");
                $io->writeln($code);
            }
        }

        if ($outputDir !== null) {
            $now     = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'UTC'));
            $command = $this->buildCommandLine($input, $inputFiles);
            $md      = $this->renderGeneratedMd($format, $now->format('Y-m-d H:i:s T'), $command);
            file_put_contents(rtrim($outputDir, '/') . '/GENERATED.md', $md);
            $io->success('Generated: GENERATED.md');
        }

        return Command::SUCCESS;
    }

    /** @param string[] $inputFiles */
    private function buildCommandLine(InputInterface $input, array $inputFiles): string
    {
        $parts = ['bin/console tree:generate'];
        foreach ($inputFiles as $file) {
            $parts[] = escapeshellarg($file);
        }

        $grammar = $input->getOption('grammar');
        if ($grammar !== null) {
            $parts[] = '--grammar=' . escapeshellarg($grammar);
        }

        $outputDir = $input->getOption('output');
        if ($outputDir !== null) {
            $parts[] = '--output=' . escapeshellarg($outputDir);
        }

        $namespace = $input->getOption('namespace');
        if ($namespace !== null) {
            $parts[] = '--namespace=' . escapeshellarg($namespace);
        }

        return implode(' ', $parts);
    }

    private function renderGeneratedMd(string $format, string $generatedAt, string $command): string
    {
        return <<<MD
        # {$format}

        This directory is auto-generated. Do not edit the files manually.
        Any changes will be overwritten the next time the generator is run.

        ## Command

        ```bash
        {$command}
        ```

        ## Generation info

        | Field     | Value |
        |-----------|-------|
        | Generated | {$generatedAt} |

        MD . "\n";
    }
}
