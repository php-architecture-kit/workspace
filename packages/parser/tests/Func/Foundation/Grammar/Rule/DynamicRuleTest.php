<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Rule;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\RegexRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\TokenRegion;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class DynamicRuleTest extends GrammarTestCase
{
    #[Test]
    public function shouldMatchDynamicTokenAfterTrigger(): void
    {
        // Dynamic rule is not available until the trigger rule fires.
        // After 'open' ('"') is tokenized, the dynamic 'content' pattern [^"]+ is injected
        // into the region, allowing it to match the string inside the quotes.
        $grammar = new Grammar('dynamic-test');
        $grammar->global->add(Rule::token('open', '{'));
        $grammar->global->add(
            Rule::dynamic(
                'content',
                fn(Rule $r, Token $t) => RegexRule::fromString('[^}]+'),
                'open',
                ['global'],
            ),
        );
        $grammar->global->add(Rule::token('close', '}'));

        $this->assertGrammarParsing(
            string: '{hello}',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $tokens = $tokenRegion->stream->tokens;

                $test->assertCount(3, $tokens);
                $test->assertSame('open', $tokens[0]->name);
                $test->assertSame('content', $tokens[1]->name);
                $test->assertSame('hello', $tokens[1]->raw);
                $test->assertSame('close', $tokens[2]->name);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldDynamicCallbackReceiveTriggerTokenContent(): void
    {
        // The dynamic rule callback receives the trigger token.
        // Here the trigger is a digit; its numeric value determines how many
        // characters the dynamic 'fixed' pattern must match.
        $grammar = new Grammar('dynamic-test');
        $grammar->global->add(Rule::expr('count', '\d'));
        $grammar->global->add(
            Rule::dynamic(
                'fixed',
                fn(Rule $r, Token $trigger) => RegexRule::fromString('.{' . (int) $trigger->raw . '}'),
                'count',
                ['global'],
            ),
        );

        $this->assertGrammarParsing(
            string: '3abc',
            grammar: $grammar,
            assertTokenizationResultValid: function (TokenRegion $tokenRegion, self $test): void {
                $tokens = $tokenRegion->stream->tokens;

                $test->assertCount(2, $tokens);
                $test->assertSame('count', $tokens[0]->name);
                $test->assertSame('fixed', $tokens[1]->name);
                $test->assertSame('abc', $tokens[1]->raw);
            },
            requireBofEof: false,
        );
    }
}
