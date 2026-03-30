<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\E2e\Json\Tokenization;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Processing\Model\Tokenization\TokenRegion;
use PhpArchitecture\Parser\Tokenization\Context\TokenizationContextCompiler;
use PhpArchitecture\Parser\Tokenization\Lexer;
use PhpArchitecture\Parser\Tokenization\Model\StringStream;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('e2e')]
class JsonRfc8259Test extends TestCase
{
    #[Test]
    public function shouldTokenizeJsonRfc8259(): void
    {
        $definitionGrammar = (new JsonRfc8259())->grammar();
        
        $grammarCompiler = new GrammarCompiler();
        $compiledGrammar = $grammarCompiler->compile($definitionGrammar);
        
        $contextCompiler = new TokenizationContextCompiler();
        $context = $contextCompiler->compile($compiledGrammar, applyRowColTracking: false);
        
        $lexer = new Lexer($context);
        $output = $lexer->process(
            new StringStream(file_get_contents(__DIR__ . '/../../../Data/Json/rfc8259/testfile_1.json'))
        );

        // Create detailed output file
        $outputFile = __DIR__ . '/../../../output/json_tokenization_output.txt';
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $fp = fopen($outputFile, 'w');
        
        // Collect all tokens recursively
        $allTokens = [];
        $tokenStats = [];
        
        $collectTokens = function(TokenRegion $region, int $depth = 0) use (&$collectTokens, &$allTokens, &$tokenStats) {
            foreach ($region->stream->tokens as $item) {
                if ($item instanceof Token) {
                    $allTokens[] = ['token' => $item, 'depth' => $depth, 'region' => $region->name];
                    $tokenStats[$item->name] = ($tokenStats[$item->name] ?? 0) + 1;
                } elseif ($item instanceof TokenRegion) {
                    $allTokens[] = ['region_start' => $item, 'depth' => $depth, 'region' => $item->name];
                    $collectTokens($item, $depth + 1);
                    $allTokens[] = ['region_end' => $item, 'depth' => $depth, 'region' => $item->name];
                }
            }
        };
        
        $collectTokens($output);
        
        fwrite($fp, str_repeat("=", 120) . "\n");
        fwrite($fp, "JSON TOKENIZATION OUTPUT - " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, str_repeat("=", 120) . "\n\n");
        
        fwrite($fp, "Source file: testfile_1.json\n");
        fwrite($fp, "Root region: {$output->name}\n");
        fwrite($fp, "Total tokens: " . count(array_filter($allTokens, fn($item) => isset($item['token']))) . "\n");
        fwrite($fp, "Total regions: " . count(array_filter($allTokens, fn($item) => isset($item['region_start']))) . "\n\n");
        
        fwrite($fp, str_repeat("-", 120) . "\n");
        fwrite($fp, "TOKEN STATISTICS\n");
        fwrite($fp, str_repeat("-", 120) . "\n");
        arsort($tokenStats);
        $totalTokens = array_sum($tokenStats);
        foreach ($tokenStats as $name => $count) {
            $percentage = ($count / $totalTokens) * 100;
            fwrite($fp, sprintf("  %-30s: %5d tokens (%5.2f%%)\n", $name, $count, $percentage));
        }
        
        fwrite($fp, "\n" . str_repeat("=", 120) . "\n");
        fwrite($fp, "FULL TOKEN LIST WITH NESTED REGIONS\n");
        fwrite($fp, str_repeat("=", 120) . "\n\n");
        
        $tokenIndex = 0;
        foreach ($allTokens as $item) {
            if (isset($item['region_start'])) {
                $region = $item['region_start'];
                $indent = str_repeat('  ', $item['depth']);
                fwrite($fp, sprintf(
                    "\n%s╔═══ REGION START: %-20s ═══╗\n",
                    $indent,
                    $region->name
                ));
            } elseif (isset($item['region_end'])) {
                $region = $item['region_end'];
                $indent = str_repeat('  ', $item['depth']);
                fwrite($fp, sprintf(
                    "%s╚═══ REGION END: %-20s ═══╝\n\n",
                    $indent,
                    $region->name
                ));
            } elseif (isset($item['token'])) {
                $token = $item['token'];
                $tokenIndex++;
                $raw = $token->raw ?? '';
                $displayValue = strlen($raw) > 50 ? substr($raw, 0, 50) . '...' : $raw;
                
                $icon = match($token->name) {
                    'bof' => '🏁',
                    'eof' => '🏁',
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
                
                $indent = str_repeat('  ', $item['depth']);
                
                fwrite($fp, sprintf(
                    "%s%s %5d. %-25s | %-50s | pos: %5d-%-5d | region: %s\n",
                    $indent,
                    $icon,
                    $tokenIndex,
                    $token->name,
                    json_encode($displayValue),
                    $token->startPosition,
                    $token->endPosition,
                    $item['region']
                ));
            }
        }
        
        fwrite($fp, "\n" . str_repeat("=", 120) . "\n");
        fwrite($fp, "END OF TOKENIZATION OUTPUT\n");
        fwrite($fp, str_repeat("=", 120) . "\n");
        
        fclose($fp);
        
        echo "\n✅ Tokenization output saved to: $outputFile\n";
        echo "Total tokens: " . count(array_filter($allTokens, fn($item) => isset($item['token']))) . "\n";
        echo "Total regions: " . count(array_filter($allTokens, fn($item) => isset($item['region_start']))) . "\n\n";

        $this->assertGreaterThan(0, count($output->stream->tokens), 'Should tokenize JSON file and produce tokens');
        $this->assertSame('json', $output->name);
    }
}
