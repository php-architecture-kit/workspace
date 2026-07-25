<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\Controller\CLI;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Parser;
use PhpArchitecture\Parser\Foundation\Parsing\Context\DefaultParsingContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Infrastructure\Grammar\Registry\InMemoryGrammarRegistry;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\FacadeSchemaGenerator;
use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\GrammarPath;
use PhpArchitecture\Parser\Presentation\Controller\CLI\Support\GrammarSelector;
use DateTimeImmutable;
use DateTimeZone;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GenerateTreeSchemaCommand extends Command
{
    private const DEFAULT_BASE_NAMESPACE = 'PhpArchitecture\\Parser\\Infrastructure\\Grammar\\ParsedTree';

    public function __construct(
        private readonly InMemoryGrammarRegistry $registry = new InMemoryGrammarRegistry(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('parser:tree:generate')
            ->setDescription('Generate Tree Schema based on input file and grammar definition')
            ->addArgument('input-file', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Path(s) to input file(s) or director(ies) (recursive) to parse as source of truth about tree schema')
            ->addOption('grammar', 'g', InputOption::VALUE_OPTIONAL, 'FQCN of the root grammar definition (interactive if omitted)')
            ->addOption('emit', 'e', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Carve-out grammar(s) to also generate, as "format/variant" (e.g. technical/whitespace). Repeatable.')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Base output dir; each grammar is written to {base}/{Format}/{Variant} (default: stdout)')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'Base namespace; each grammar gets {base}\\{Format}\\{Variant} (default: ' . self::DEFAULT_BASE_NAMESPACE . ')');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $baseDir   = $input->getOption('output');

        $selector   = new GrammarSelector($this->registry);
        $definition = $selector->resolve($input->getOption('grammar'), $io);
        if ($definition === null) {
            return Command::FAILURE;
        }

        $inputFiles = $this->collectInputFiles($input->getArgument('input-file'), $io);
        if ($inputFiles === null) {
            return Command::FAILURE;
        }

        $emitTargets = $this->normalizeEmitTargets($input->getOption('emit'));

        $grammarDefinition = $definition->grammar();
        $compiledGrammar   = (new GrammarCompiler())->compile($grammarDefinition);
        $baseNamespace     = $input->getOption('namespace') ?? self::DEFAULT_BASE_NAMESPACE;

        // Parse source files without nodeClassMap — facade classes may not exist yet.
        $grammarForParsing = new CompiledGrammar(
            $compiledGrammar->name,
            $compiledGrammar->variant,
            $compiledGrammar->requireBofEof,
            $compiledGrammar->rootRegionName,
            $compiledGrammar->regions,
            $compiledGrammar->contextInitializers,
            $compiledGrammar->formatters,
            [], // nodeClassMap stripped — parse with bare shape nodes
            $compiledGrammar->globalRegionName,
        );

        $parser    = new Parser();
        $parsedTrees = [];
        foreach ($inputFiles as $inputFile) {
            $parsedTrees[] = $parser->parse(
                new StringStream(file_get_contents($inputFile)),
                new DefaultParsingContext($grammarForParsing),
            );
        }

        $generator   = new FacadeSchemaGenerator();
        $filesByNs   = $generator->generate($parsedTrees, $compiledGrammar, $grammarDefinition, $baseNamespace, $emitTargets);

        // Warn for emit targets that matched no collected node.
        foreach ($emitTargets as $target) {
            [$fmt, $variant] = array_pad(explode('/', $target, 2), 2, '');
            $ns = GrammarPath::namespaceFor($baseNamespace, $fmt, $variant === '' ? null : $variant);
            if (!isset($filesByNs[$ns])) {
                $io->warning("Emit target '{$target}' produced no classes (no matching nodes in the parsed trees).");
            }
        }

        $command = $this->buildCommandLine($input, $inputFiles);
        $now     = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'UTC'));

        foreach ($filesByNs as $ns => $files) {
            if ($baseDir === null) {
                foreach ($files as $filename => $code) {
                    $io->writeln("<comment>=== {$ns}\\{$filename} ===</comment>");
                    $io->writeln($code);
                }
                continue;
            }

            $dir = GrammarPath::dirFor($baseDir, $baseNamespace, $ns);
            if (is_dir($dir)) {
                foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
                    unlink($file);
                }
            } else {
                mkdir($dir, 0755, true);
            }

            foreach ($files as $filename => $code) {
                file_put_contents(rtrim($dir, '/') . '/' . $filename, $code);
                $io->success("Generated: {$dir}/{$filename}");
            }

            $md = $this->renderGeneratedMd($ns, $now->format('Y-m-d H:i:s T'), $command);
            file_put_contents(rtrim($dir, '/') . '/GENERATED.md', $md);
        }

        $snippet = $this->buildNodeClassMapSnippet($generator->getLastSchemas());
        $io->section('Add to your Grammar definition class grammar() method:');
        $io->writeln($snippet);

        return Command::SUCCESS;
    }

    /**
     * Expands input paths into a flat, deduplicated, sorted list of files. Directories
     * are walked recursively (all files). Returns null (after an error) on a missing path.
     *
     * @param string[] $paths
     * @return string[]|null
     */
    private function collectInputFiles(array $paths, SymfonyStyle $io): ?array
    {
        $files = [];
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                $io->error("Input path '{$path}' does not exist.");
                return null;
            }

            if (is_dir($path)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                );
                foreach ($iterator as $entry) {
                    if ($entry->isFile()) {
                        $files[$entry->getPathname()] = true;
                    }
                }
                continue;
            }

            $files[$path] = true;
        }

        $files = array_keys($files);
        sort($files);

        if (empty($files)) {
            $io->error('No input files found.');
            return null;
        }

        return $files;
    }

    /**
     * Normalizes emit targets to "format/variant" (empty variant kept as trailing slash),
     * matching the keys the router compares against.
     *
     * @param string[] $raw
     * @return string[]
     */
    private function normalizeEmitTargets(array $raw): array
    {
        $targets = [];
        foreach ($raw as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if (!str_contains($value, '/')) {
                $value .= '/';
            }
            $targets[$value] = true;
        }
        return array_keys($targets);
    }

    /**
     * @param \PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\NodeSchema[] $schemas
     */
    private function buildNodeClassMapSnippet(array $schemas): string
    {
        $entries = [];
        foreach ($schemas as $schema) {
            if (!$schema->shouldGenerate || $schema->targetNamespace === null) {
                continue;
            }
            $fqcn = $schema->targetNamespace . '\\' . $schema->className;
            $entries[] = sprintf("    '%s' => \\%s::class,", $schema->nodeName, $fqcn);
        }

        return implode("\n", [
            '$grammar->nodeClassMap = array_merge($grammar->nodeClassMap, [',
            implode("\n", $entries),
            ']);',
        ]);
    }

    /** @param string[] $inputFiles */
    private function buildCommandLine(InputInterface $input, array $inputFiles): string
    {
        $parts = ['bin/console parser:tree:generate'];
        foreach ($inputFiles as $file) {
            $parts[] = escapeshellarg($file);
        }

        $grammar = $input->getOption('grammar');
        if ($grammar !== null) {
            $parts[] = '--grammar=' . escapeshellarg($grammar);
        }

        foreach ($input->getOption('emit') as $emit) {
            $parts[] = '--emit=' . escapeshellarg($emit);
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

    private function renderGeneratedMd(string $title, string $generatedAt, string $command): string
    {
        return <<<MD
        # {$title}

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
