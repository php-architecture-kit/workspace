<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\Controller\CLI;

use PhpArchitecture\Parser\Infrastructure\Grammar\Registry\InMemoryGrammarRegistry;
use PhpArchitecture\Parser\Presentation\Controller\CLI\Support\GrammarSelector;
use PhpArchitecture\Parser\Presentation\View\Grammar\GrammarConsoleRenderer;
use PhpArchitecture\Parser\Presentation\View\Grammar\GrammarViewFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ViewGrammarCommand extends Command
{
    public function __construct(
        private readonly InMemoryGrammarRegistry $registry = new InMemoryGrammarRegistry(),
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->setName('parser:grammar:view')
            ->setDescription('Display grammar definition structure as a tree')
            ->addArgument('grammar-class', InputArgument::OPTIONAL, 'FQCN of the grammar definition (interactive choice if omitted)')
            ->addOption('region', 'r', InputOption::VALUE_OPTIONAL, 'Show only a specific region by name')
            ->addOption('show-rules', null, InputOption::VALUE_NONE, 'Show detailed rule definitions')
            ->addOption('hide-middlewares', null, InputOption::VALUE_NONE, 'Hide middleware information')
            ->addOption('hide-event-subscribers', null, InputOption::VALUE_NONE, 'Hide event subscriber information')
            ->addOption('show-tags', null, InputOption::VALUE_NONE, 'Show tags on regions and rules')
            ->addOption('max-depth', 'd', InputOption::VALUE_OPTIONAL, 'Maximum nesting depth to display');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io           = new SymfonyStyle($input, $output);
        $regionFilter = $input->getOption('region');
        $maxDepthOpt  = $input->getOption('max-depth');
        $maxDepth     = $maxDepthOpt !== null ? (int) $maxDepthOpt : PHP_INT_MAX;

        $selector   = new GrammarSelector($this->registry);
        $definition = $selector->resolve($input->getArgument('grammar-class'), $io);
        if ($definition === null) {
            return Command::FAILURE;
        }

        $grammar = $definition->grammar();
        $factory = new GrammarViewFactory();

        $renderer = new GrammarConsoleRenderer(
            io: $io,
            output: $output,
            showRules: (bool) $input->getOption('show-rules'),
            showMiddlewares: !(bool) $input->getOption('hide-middlewares'),
            showEventSubscribers: !(bool) $input->getOption('hide-event-subscribers'),
            showTags: (bool) $input->getOption('show-tags'),
            maxDepth: $maxDepth,
        );

        if ($regionFilter !== null) {
            $allRegions = $grammar->getAllRegions();
            if (!isset($allRegions[$regionFilter])) {
                $io->error("Region '{$regionFilter}' not found in grammar.");
                return Command::FAILURE;
            }
            $renderer->renderSingle($factory->fromRegion($allRegions[$regionFilter]));
            return Command::SUCCESS;
        }

        $renderer->render($factory->fromGrammar($grammar));

        $this->renderHints($output, get_class($definition));

        return Command::SUCCESS;
    }

    private function renderHints(OutputInterface $output, string $definitionClass): void
    {
        $g = '"' . str_replace('\\', '\\\\', $definitionClass) . '"';
        $cmd = 'bin/console parser:grammar:view';

        $output->writeln('');
        $output->writeln('<fg=gray>────────────────────────────────────────────────────────────</>');
        $output->writeln('<fg=gray> View modifiers:</>');
        $output->writeln('');
        $output->writeln("  <fg=cyan>--show-rules</>          show detailed rule definitions");
        $output->writeln("  <fg=cyan>--show-tags</>           show tags on regions and rules");
        $output->writeln("  <fg=cyan>--hide-middlewares</>    hide middleware information");
        $output->writeln("  <fg=cyan>--hide-event-subscribers</>  hide event subscribers");
        $output->writeln("  <fg=cyan>-d, --max-depth=N</>    limit nesting depth (e.g. <fg=white>-d 2</>)");
        $output->writeln("  <fg=cyan>-r, --region=NAME</>    focus on a single region");
        $output->writeln('');
        $output->writeln('<fg=gray> Examples:</>');
        $output->writeln('');
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</> <fg=cyan>--show-rules --show-tags</>");
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</> <fg=cyan>-d 2 --hide-middlewares</>");
        $output->writeln("  <fg=gray>{$cmd}</> <fg=yellow>{$g}</> <fg=cyan>--region=object --show-rules</>");
        $output->writeln('<fg=gray>────────────────────────────────────────────────────────────</>');
    }
}
