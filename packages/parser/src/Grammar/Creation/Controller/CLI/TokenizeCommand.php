<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Creation\Controller\CLI;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Registry\GrammarDefinitionInterface;
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

final class TokenizeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('parser:tokenize')
            ->setDescription('Tokenize input using specified grammar')
            ->addArgument('grammar-class', InputArgument::REQUIRED, 'Fully qualified class name of the grammar definition')
            ->addArgument('input-file', InputArgument::REQUIRED, 'Path to input file to tokenize')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (default: stdout)')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format: simple, detailed, stats', 'detailed')
            ->addOption('no-row-col', null, InputOption::VALUE_NONE, 'Disable row/column tracking');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $grammarClass = $input->getArgument('grammar-class');
        $inputFile = $input->getArgument('input-file');
        $outputFile = $input->getOption('output');
        $format = $input->getOption('format');
        $applyRowColTracking = !$input->getOption('no-row-col');

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
        
        $contextCompiler = new TokenizationContextCompiler();
        $context = $contextCompiler->compile($compiledGrammar, $applyRowColTracking);
        
        $lexer = new Lexer($context);
        $tokenizedOutput = $lexer->process(new StringStream(file_get_contents($inputFile)));

        $formattedOutput = match($format) {
            'simple' => $this->formatSimple($tokenizedOutput),
            'stats' => $this->formatStats($tokenizedOutput),
            default => $this->formatDetailed($tokenizedOutput),
        };

        if ($outputFile) {
            file_put_contents($outputFile, $formattedOutput);
            $io->success("Tokenization output saved to: {$outputFile}");
        } else {
            $output->write($formattedOutput);
        }

        return Command::SUCCESS;
    }

    private function formatSimple(TokenRegion $output): string
    {
        $result = '';
        $allTokens = $this->collectTokens($output);
        
        foreach ($allTokens as $item) {
            if (isset($item['token'])) {
                $token = $item['token'];
                $result .= "{$token->name}\n";
            }
        }
        
        return $result;
    }

    private function formatStats(TokenRegion $output): string
    {
        $allTokens = $this->collectTokens($output);
        $tokenStats = [];
        $regionStats = [];
        
        foreach ($allTokens as $item) {
            if (isset($item['token'])) {
                $tokenStats[$item['token']->name] = ($tokenStats[$item['token']->name] ?? 0) + 1;
            } elseif (isset($item['region_start'])) {
                $regionStats[$item['region_start']->name] = ($regionStats[$item['region_start']->name] ?? 0) + 1;
            }
        }
        
        arsort($tokenStats);
        arsort($regionStats);
        
        $totalTokens = array_sum($tokenStats);
        $totalRegions = array_sum($regionStats);
        
        $result = str_repeat("=", 120) . "\n";
        $result .= "TOKENIZATION STATISTICS\n";
        $result .= str_repeat("=", 120) . "\n\n";
        $result .= "Total Tokens: {$totalTokens}\n";
        $result .= "Total Regions: {$totalRegions}\n\n";
        
        $result .= str_repeat("-", 120) . "\n";
        $result .= "TOKEN DISTRIBUTION\n";
        $result .= str_repeat("-", 120) . "\n";
        foreach ($tokenStats as $name => $count) {
            $percentage = ($count / $totalTokens) * 100;
            $result .= sprintf("  %-30s: %5d tokens (%5.2f%%)\n", $name, $count, $percentage);
        }
        
        $result .= "\n" . str_repeat("-", 120) . "\n";
        $result .= "REGION DISTRIBUTION\n";
        $result .= str_repeat("-", 120) . "\n";
        foreach ($regionStats as $name => $count) {
            $result .= sprintf("  %-30s: %5d occurrences\n", $name, $count);
        }
        
        return $result;
    }

    private function formatDetailed(TokenRegion $output): string
    {
        $allTokens = $this->collectTokens($output);
        $tokenStats = [];
        
        foreach ($allTokens as $item) {
            if (isset($item['token'])) {
                $tokenStats[$item['token']->name] = ($tokenStats[$item['token']->name] ?? 0) + 1;
            }
        }
        
        arsort($tokenStats);
        $totalTokens = array_sum($tokenStats);
        
        $result = str_repeat("=", 120) . "\n";
        $result .= "TOKENIZATION OUTPUT - " . date('Y-m-d H:i:s') . "\n";
        $result .= str_repeat("=", 120) . "\n\n";
        $result .= "Root region: {$output->name}\n";
        $result .= "Total tokens: {$totalTokens}\n";
        $result .= "Total regions: " . count(array_filter($allTokens, fn($item) => isset($item['region_start']))) . "\n\n";
        
        $result .= str_repeat("-", 120) . "\n";
        $result .= "TOKEN STATISTICS\n";
        $result .= str_repeat("-", 120) . "\n";
        foreach ($tokenStats as $name => $count) {
            $percentage = ($count / $totalTokens) * 100;
            $result .= sprintf("  %-30s: %5d tokens (%5.2f%%)\n", $name, $count, $percentage);
        }
        
        $result .= "\n" . str_repeat("=", 120) . "\n";
        $result .= "FULL TOKEN LIST WITH NESTED REGIONS\n";
        $result .= str_repeat("=", 120) . "\n\n";
        
        $tokenIndex = 0;
        foreach ($allTokens as $item) {
            if (isset($item['region_start'])) {
                $region = $item['region_start'];
                $indent = str_repeat('  ', $item['depth']);
                $result .= sprintf("\n%s╔═══ REGION START: %-20s ═══╗\n", $indent, $region->name);
            } elseif (isset($item['region_end'])) {
                $region = $item['region_end'];
                $indent = str_repeat('  ', $item['depth']);
                $result .= sprintf("%s╚═══ REGION END: %-20s ═══╝\n\n", $indent, $region->name);
            } elseif (isset($item['token'])) {
                $token = $item['token'];
                $tokenIndex++;
                $raw = $token->raw ?? '';
                $displayValue = strlen($raw) > 50 ? substr($raw, 0, 50) . '...' : $raw;
                
                $icon = $this->getTokenIcon($token->name);
                $indent = str_repeat('  ', $item['depth']);
                
                $result .= sprintf(
                    "%s%s %5d. %-25s | %-50s | pos: %5d-%-5d | region: %s\n",
                    $indent,
                    $icon,
                    $tokenIndex,
                    $token->name,
                    json_encode($displayValue),
                    $token->startPosition,
                    $token->endPosition,
                    $item['region']
                );
            }
        }
        
        $result .= "\n" . str_repeat("=", 120) . "\n";
        
        return $result;
    }

    private function collectTokens(TokenRegion $region): array
    {
        $allTokens = [];
        
        $collectRecursive = function(TokenRegion $region, int $depth = 0) use (&$collectRecursive, &$allTokens) {
            foreach ($region->stream->tokens as $item) {
                if ($item instanceof Token) {
                    $allTokens[] = ['token' => $item, 'depth' => $depth, 'region' => $region->name];
                } elseif ($item instanceof TokenRegion) {
                    $allTokens[] = ['region_start' => $item, 'depth' => $depth, 'region' => $item->name];
                    $collectRecursive($item, $depth + 1);
                    $allTokens[] = ['region_end' => $item, 'depth' => $depth, 'region' => $item->name];
                }
            }
        };
        
        $collectRecursive($region);
        
        return $allTokens;
    }

    private function getTokenIcon(string $tokenName): string
    {
        return match($tokenName) {
            'bof', 'eof' => '🏁',
            'begin-object' => '{',
            'end-object' => '}',
            'begin-array' => '[',
            'end-array' => ']',
            'double-quote' => '"',
            'unknown' => '·',
            'space' => '␣',
            'newline' => '↵',
            'true', 'false', 'null' => '✓',
            'digit1-9', 'zero' => '#',
            default => ' '
        };
    }
}
