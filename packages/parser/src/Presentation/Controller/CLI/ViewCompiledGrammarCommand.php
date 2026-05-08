<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\Controller\CLI;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Infrastructure\Grammar\Registry\InMemoryGrammarRegistry;
use PhpArchitecture\Parser\Presentation\Controller\CLI\Support\GrammarSelector;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\CompiledGrammarConsoleRenderer;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\CompiledGrammarViewFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ViewCompiledGrammarCommand extends Command
{
    public function __construct(
        private readonly InMemoryGrammarRegistry $registry = new InMemoryGrammarRegistry(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('parser:grammar:compiled')
            ->setDescription('Display compiled grammar structure')
            ->addArgument('grammar-class', InputArgument::OPTIONAL, 'FQCN of the grammar definition (interactive choice if omitted)')
            ->addOption('region', 'r', InputOption::VALUE_OPTIONAL, 'Show only a specific region by name')
            ->addOption('show-patterns', null, InputOption::VALUE_NONE, 'Show pattern library details')
            ->addOption('show-sequences', null, InputOption::VALUE_NONE, 'Show sequence library details')
            ->addOption('show-event-subscribers', null, InputOption::VALUE_NONE, 'Show event subscribers')
            ->addOption('show-tags', null, InputOption::VALUE_NONE, 'Show tags on regions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io           = new SymfonyStyle($input, $output);
        $regionFilter = $input->getOption('region');

        $selector   = new GrammarSelector($this->registry);
        $definition = $selector->resolve($input->getArgument('grammar-class'), $io);
        if ($definition === null) {
            return Command::FAILURE;
        }

        $compiledGrammar = (new GrammarCompiler())->compile($definition->grammar());
        $factory         = new CompiledGrammarViewFactory();
        $viewData        = $factory->fromCompiledGrammar($compiledGrammar);

        $renderer = new CompiledGrammarConsoleRenderer(
            io: $io,
            output: $output,
            showPatterns: (bool) $input->getOption('show-patterns'),
            showSequences: (bool) $input->getOption('show-sequences'),
            showEventSubscribers: (bool) $input->getOption('show-event-subscribers'),
            showTags: (bool) $input->getOption('show-tags'),
        );

        if ($regionFilter !== null) {
            if (!isset($viewData->regions[$regionFilter])) {
                $io->error("Region '{$regionFilter}' not found in compiled grammar.");
                return Command::FAILURE;
            }
            $renderer->renderSingle($viewData->regions[$regionFilter]);
            return Command::SUCCESS;
        }

        $renderer->render($viewData);

        $this->renderHints($output, get_class($definition));

        return Command::SUCCESS;
    }

    private function renderHints(OutputInterface $output, string $definitionClass): void
    {
        $g   = '"' . str_replace('\\', '\\\\', $definitionClass) . '"';
        $cmd = 'bin/console parser:grammar:compiled';

        $output->writeln('');
        $output->writeln('<fg=gray>────────────────────────────────────────────────────────────</>');
        $output->writeln('<fg=gray> View modifiers:</>');
        $output->writeln('');
        $output->writeln("  <fg=cyan>--show-patterns</>       show pattern library (regex per region)");
        $output->writeln("  <fg=cyan>--show-sequences</>      show sequence library with node breakdown");
        $output->writeln("  <fg=cyan>--show-event-subscribers</>  show compiled event subscribers");
        $output->writeln("  <fg=cyan>--show-tags</>           show tags on regions");
        $output->writeln("  <fg=cyan>-r, --region=NAME</>    focus on a single region");
        $output->writeln('');
        $output->writeln('<fg=gray> Examples:</>');
        $output->writeln('');
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</> <fg=cyan>--show-patterns --show-sequences</>");
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</> <fg=cyan>--show-event-subscribers --show-tags</>");
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</> <fg=cyan>--region=object --show-sequences</>");
        $output->writeln('<fg=gray>────────────────────────────────────────────────────────────</>');
    }
}
