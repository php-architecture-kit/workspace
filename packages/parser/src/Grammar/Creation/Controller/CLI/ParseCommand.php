<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Creation\Controller\CLI;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Registry\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Parser;
use PhpArchitecture\Parser\Parsing\Context\DefaultParsingContext;
use PhpArchitecture\Parser\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Parsing\Model\Node;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeAttributeInterface;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeInterface;
use PhpArchitecture\Parser\Tokenization\Context\TokenizationContextCompiler;
use PhpArchitecture\Parser\Tokenization\Lexer;
use PhpArchitecture\Parser\Tokenization\Model\StringStream;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ParseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('parser:parse')
            ->setDescription('Parse input using specified grammar')
            ->addArgument('grammar-class', InputArgument::REQUIRED, 'Fully qualified class name of the grammar definition')
            ->addArgument('input-file', InputArgument::REQUIRED, 'Path to input file to parse')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (default: stdout)')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: tree, json, simple', 'tree')
            ->addOption('max-depth', 'd', InputOption::VALUE_OPTIONAL, 'Maximum depth to display', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $grammarClass = $input->getArgument('grammar-class');
        $inputFile = $input->getArgument('input-file');
        $outputFile = $input->getOption('output');
        $format = $input->getOption('format');
        $maxDepth = $input->getOption('max-depth') ? (int)$input->getOption('max-depth') : null;

        if (!class_exists($grammarClass)) {
            $io->error("Grammar class '{$grammarClass}' does not exist.");
            return Command::FAILURE;
        }

        if (!is_subclass_of($grammarClass, GrammarDefinitionInterface::class)) {
            $io->error("Class '{$grammarClass}' must implement GrammarDefinitionInterface.");
            return Command::FAILURE;
        }

        if (!file_exists($inputFile)) {
            $io->error("Input file '{$inputFile}' does not exist.");
            return Command::FAILURE;
        }

        $grammarDefinition = new $grammarClass();
        $grammar = $grammarDefinition->grammar();

        $compiler = new GrammarCompiler();
        $compiledGrammar = $compiler->compile($grammar);

        $parsingContext = new DefaultParsingContext($compiledGrammar);
        $parser = new Parser();

        $parseTree = $parser->parse(new StringStream(file_get_contents($inputFile)), $parsingContext);

        $formattedOutput = match ($format) {
            'json' => $this->formatJson($parseTree),
            'simple' => $this->formatSimple($parseTree),
            default => $this->formatTree($parseTree, $maxDepth),
        };

        if ($outputFile) {
            file_put_contents($outputFile, $formattedOutput);
            $io->success("Parse output saved to: {$outputFile}");
        } else {
            $output->write($formattedOutput);
        }

        return Command::SUCCESS;
    }

    private function formatTree(mixed $node, ?int $maxDepth, int $currentDepth = 0, bool $isLast = true): string
    {
        if ($maxDepth !== null && $currentDepth > $maxDepth) {
            return '';
        }

        $result = '';
        $indent = str_repeat('  ', $currentDepth);
        $prefix = $currentDepth === 0 ? '' : ($isLast ? '└─ ' : '├─ ');

        if ($node instanceof OptionalAttribute) {
            $metaInfo = !empty($node->meta) ? ' [meta: ' . json_encode($node->meta) . ']' : '';
            $tagsInfo = !empty($node->tags) ? ' [tags: ' . implode(', ', $node->tags) . ']' : '';
            $presentInfo = $node->node !== null ? 'present' : 'absent';
            $result .= $indent . $prefix . "OptionalAttribute: {$node->name} ({$presentInfo}){$metaInfo}{$tagsInfo}\n";
            if ($node->node !== null) {
                $result .= $this->formatTree($node->node, $maxDepth, $currentDepth + 1, true);
            }
        } elseif ($node instanceof NodeAttribute) {
            $metaInfo = !empty($node->meta) ? ' [meta: ' . json_encode($node->meta) . ']' : '';
            $tagsInfo = !empty($node->tags) ? ' [tags: ' . implode(', ', $node->tags) . ']' : '';
            $result .= $indent . $prefix . "NodeAttribute: {$node->name}{$metaInfo}{$tagsInfo}\n";
            $result .= $this->formatTree($node->node, $maxDepth, $currentDepth + 1, true);
        } elseif ($node instanceof GroupAttribute) {
            $metaInfo = !empty($node->meta) ? ' [meta: ' . json_encode($node->meta) . ']' : '';
            $tagsInfo = !empty($node->tags) ? ' [tags: ' . implode(', ', $node->tags) . ']' : '';
            $result .= $indent . $prefix . "GroupAttribute: {$node->name} (count: " . count($node->nodes) . "){$metaInfo}{$tagsInfo}\n";
            $childCount = count($node->nodes);
            foreach ($node->nodes as $index => $child) {
                $result .= $this->formatTree($child, $maxDepth, $currentDepth + 1, $index === $childCount - 1);
            }
        } elseif ($node instanceof RawRegionAttribute) {
            $displayValue = strlen($node->content) > 50 ? substr($node->content, 0, 50) . '...' : $node->content;
            $metaInfo = !empty($node->meta) ? ' [meta: ' . json_encode($node->meta) . ']' : '';
            $tagsInfo = !empty($node->tags) ? ' [tags: ' . implode(', ', $node->tags) . ']' : '';
            $result .= $indent . $prefix . "RawRegionAttribute: {$node->name} = " . json_encode($displayValue) . "{$metaInfo}{$tagsInfo}\n";
        } elseif ($node instanceof RawContentAttribute) {
            $displayValue = strlen($node->content) > 50 ? substr($node->content, 0, 50) . '...' : $node->content;
            $metaInfo = !empty($node->meta) ? ' [meta: ' . json_encode($node->meta) . ']' : '';
            $tagsInfo = !empty($node->tags) ? ' [tags: ' . implode(', ', $node->tags) . ']' : '';
            $result .= $indent . $prefix . "RawContentAttribute: {$node->name} = " . json_encode($displayValue) . "{$metaInfo}{$tagsInfo}\n";
        } elseif ($node instanceof StructureAttribute) {
            $displayValue = strlen($node->content) > 50 ? substr($node->content, 0, 50) . '...' : $node->content;
            $metaInfo = !empty($node->meta) ? ' [meta: ' . json_encode($node->meta) . ']' : '';
            $tagsInfo = !empty($node->tags) ? ' [tags: ' . implode(', ', $node->tags) . ']' : '';
            $presentInfo = $node->present ? 'present' : 'absent';
            $result .= $indent . $prefix . "StructureAttribute: {$node->name} ({$presentInfo}) = " . json_encode($displayValue) . "{$metaInfo}{$tagsInfo}\n";
        } elseif ($node instanceof Node) {
            $metaInfo = !empty($node->meta) ? ' [meta: ' . json_encode($node->meta) . ']' : '';
            $tagsInfo = !empty($node->tags) ? ' [tags: ' . implode(', ', $node->tags) . ']' : '';
            $result .= $indent . $prefix . "Node: {$node->name}{$metaInfo}{$tagsInfo}\n";
            $childCount = count($node->attributes);
            foreach ($node->attributes as $index => $child) {
                $result .= $this->formatTree($child, $maxDepth, $currentDepth + 1, $index === $childCount - 1);
            }
        }

        return $result;
    }

    private function formatJson(NodeInterface $node): string
    {
        $data = $this->nodeToArray($node);
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function formatSimple(NodeInterface $node): string
    {
        return $node->__toString();
    }

    private function nodeToArray(mixed $node): array
    {
        $baseData = [
            'name' => $node->name ?? 'unknown',
        ];

        if (method_exists($node, 'getMeta') && !empty($node->meta)) {
            $baseData['meta'] = $node->meta;
        }

        if (method_exists($node, 'getTags') && !empty($node->tags)) {
            $baseData['tags'] = $node->tags;
        }

        if ($node instanceof Node) {
            $baseData['type'] = 'Node';
            $children = [];
            foreach ($node->attributes as $child) {
                $children[] = $this->nodeToArray($child);
            }
            if (count($children) > 0) {
                $baseData['attributes'] = $children;
            }
        } elseif ($node instanceof NodeAttribute) {
            $baseData['type'] = 'NodeAttribute';
            $baseData['node'] = $this->nodeToArray($node->node);
        } elseif ($node instanceof GroupAttribute) {
            $baseData['type'] = 'GroupAttribute';
            $nodes = [];
            foreach ($node->nodes as $child) {
                $nodes[] = $this->nodeToArray($child);
            }
            $baseData['nodes'] = $nodes;
        } elseif ($node instanceof OptionalAttribute) {
            $baseData['type'] = 'OptionalAttribute';
            $baseData['present'] = $node->node !== null;
            if ($node->node !== null) {
                $baseData['node'] = $this->nodeToArray($node->node);
            }
        } elseif ($node instanceof RawContentAttribute) {
            $baseData['type'] = 'RawContentAttribute';
            $baseData['content'] = $node->content;
        } elseif ($node instanceof StructureAttribute) {
            $baseData['type'] = 'StructureAttribute';
            $baseData['present'] = $node->present;
            $baseData['content'] = $node->content;
        }

        return $baseData;
    }
}
