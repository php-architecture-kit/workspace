<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Creation\Controller\CLI;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Registry\GrammarDefinitionInterface;
use PhpArchitecture\Parser\Parser;
use PhpArchitecture\Parser\Parsing\Context\DefaultParsingContext;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
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

    private function formatTree(Token|TokenRegion $node, ?int $maxDepth, int $currentDepth = 0): string
    {
        if ($maxDepth !== null && $currentDepth > $maxDepth) {
            return '';
        }

        $result = '';
        $indent = str_repeat('  ', $currentDepth);

        if ($node instanceof Token) {
            $displayValue = strlen($node->raw ?? '') > 50 ? substr($node->raw ?? '', 0, 50) . '...' : ($node->raw ?? '');
            $result .= $indent . "├─ Token: {$node->name} = " . json_encode($displayValue) . "\n";
        } elseif ($node instanceof TokenRegion) {
            $result .= $indent . "├─ Region: {$node->name}\n";
            foreach ($node->stream->tokens as $child) {
                $result .= $this->formatTree($child, $maxDepth, $currentDepth + 1);
            }
        }

        return $result;
    }

    private function formatJson(Token|TokenRegion $node): string
    {
        $data = $this->nodeToArray($node);
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function formatSimple(Token|TokenRegion $node): string
    {
        $result = '';

        $traverse = function (Token|TokenRegion $node) use (&$traverse, &$result) {
            if ($node instanceof Token) {
                $result .= $node->raw ?? '';
            } elseif ($node instanceof TokenRegion) {
                foreach ($node->stream->tokens as $child) {
                    $traverse($child);
                }
            }
        };

        $traverse($node);

        return $result;
    }

    private function nodeToArray(Token|TokenRegion $node): array
    {
        if ($node instanceof Token) {
            return [
                'type' => 'Token',
                'name' => $node->name,
                'value' => $node->raw ?? '',
                'position' => [
                    'start' => $node->startPosition,
                    'end' => $node->endPosition,
                ],
            ];
        }

        $data = [
            'type' => 'Region',
            'name' => $node->name,
        ];

        $children = [];
        foreach ($node->stream->tokens as $child) {
            $children[] = $this->nodeToArray($child);
        }

        if (count($children) > 0) {
            $data['children'] = $children;
        }

        return $data;
    }
}
