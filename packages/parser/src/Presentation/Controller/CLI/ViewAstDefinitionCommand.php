<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\Controller\CLI;

use PhpArchitecture\Parser\Foundation\AST\Definition\Compiler\DefinitionSource;
use PhpArchitecture\Parser\Foundation\AST\Definition\Compiler\NodeDefinitionCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Infrastructure\Grammar\Registry\InMemoryGrammarRegistry;
use PhpArchitecture\Parser\Presentation\Controller\CLI\Support\GrammarSelector;
use PhpArchitecture\Parser\Presentation\View\AstDefinition\AstDefinitionRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ViewAstDefinitionCommand extends Command
{
    public function __construct(
        private readonly InMemoryGrammarRegistry $registry = new InMemoryGrammarRegistry(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('parser:ast:definition')
            ->setDescription('Display AST node definitions from grammar as a tree')
            ->addArgument('grammar-class', InputArgument::OPTIONAL, 'FQCN of the grammar definition (interactive choice if omitted)')
            ->addOption('max-depth', 'd', InputOption::VALUE_OPTIONAL, 'Maximum nesting depth to display', 10)
            ->addOption('show-closure-body', null, InputOption::VALUE_NONE, 'Show closure body implementation')
            ->addOption('show-references', null, InputOption::VALUE_NONE, 'Show reference links between definitions')
            ->addOption('compiled', 'c', InputOption::VALUE_NONE, 'Show compiled NodeDefinitions instead of raw Definitions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $selector = new GrammarSelector($this->registry);
        $definition = $selector->resolve($input->getArgument('grammar-class'), $io);
        if ($definition === null) {
            return Command::FAILURE;
        }

        $grammar = $definition->grammar();

        // Collect all Definitions from Rules and Regions as DefinitionSource
        $allSources = [];

        // From RegionConfig
        foreach ($grammar->getAllRegions() as $region) {
            if ($region->config->definition !== null) {
                $allSources[] = new DefinitionSource(
                    definition: $region->config->definition,
                    sourceName: $region->name,
                    rootSequence: $region->config->rootSequence,
                );
            }
        }

        // From Global
        foreach ($grammar->global->rules as $rule) {
            if ($rule->astDefinition !== null) {
                $rootSequence = $rule->definition instanceof SequenceRule
                    ? $rule->definition
                    : null;
                $allSources[] = new DefinitionSource(
                    definition: $rule->astDefinition,
                    sourceName: $rule->name,
                    rootSequence: $rootSequence,
                );
            }
        }

        if (empty($allSources)) {
            $io->warning('No AST node definitions found in this grammar.');
            $io->note('Use asAstNode() on Rules or Regions to define AST nodes.');
            return Command::SUCCESS;
        }

        $projectRoot = $this->findProjectRoot();
        $renderer = new AstDefinitionRenderer(
            output: $output,
            showClosureBody: (bool) $input->getOption('show-closure-body'),
            maxDepth: (int) $input->getOption('max-depth'),
            projectRootDir: $projectRoot,
        );

        $isCompiled = (bool) $input->getOption('compiled');

        if ($isCompiled) {
            $compiler = new NodeDefinitionCompiler();
            $compiled = $compiler->compile($allSources);

            $io->section('Compiled AST Node Definitions for: ' . get_class($definition));
            $output->writeln('<fg=gray>Found ' . count($allSources) . ' raw source(s), compiled to ' . count($compiled) . ' unique NodeDefinition(s)</>');
            $output->writeln('');

            foreach ($compiled as $name => $nodeDef) {
                $renderer->render($nodeDef, $name, 'compiled');
                $output->writeln('');
            }
        } else {
            $io->section('Raw AST Node Definitions for: ' . get_class($definition));
            $output->writeln('<fg=gray>Found ' . count($allSources) . ' definition(s) from Grammar</>');
            $output->writeln('<fg=gray>Use --compiled to see compiled NodeDefinitions</>');
            $output->writeln('');

            foreach ($allSources as $source) {
                $renderer->render($source->definition, $source->definition->name, 'Rule "' . $source->sourceName . '"');
                $output->writeln('');
            }
        }

        $this->renderHints($output, get_class($definition));

        return Command::SUCCESS;
    }

    private function renderHints(OutputInterface $output, string $definitionClass): void
    {
        $g = '"' . str_replace('\\', '\\\\', $definitionClass) . '"';
        $cmd = 'bin/console parser:ast:definition';

        $output->writeln('');
        $output->writeln('<fg=gray>────────────────────────────────────────────────────────────</>');
        $output->writeln('<fg=gray> View modifiers:</>');
        $output->writeln('');
        $output->writeln("  <fg=cyan>-d, --max-depth=N</>      limit nesting depth");
        $output->writeln("  <fg=cyan>-c, --compiled</>        show compiled NodeDefinitions");
        $output->writeln("  <fg=cyan>--show-closure-body</>   show closure implementation");
        $output->writeln("  <fg=cyan>--show-references</>     show cross-references between nodes");
        $output->writeln('');
        $output->writeln('<fg=gray> Examples:</>');
        $output->writeln('');
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</>");
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</> <fg=cyan>-c</>");
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</> <fg=cyan>-c --show-closure-body -d 2</>");
        $output->writeln('<fg=gray>────────────────────────────────────────────────────────────</>');
    }

    private function findProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }
        return getcwd() ?: '/home/patryk_baszak/development/github/php-architecture-kit/workspace';
    }
}
