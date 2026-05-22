<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Model\Regex;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\RegexRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Token;

#[Group('unit')]
final class CallbackRuleTest extends TestCase
{
    #[Test]
    public function shouldSetAllPropertiesThroughConstructor(): void
    {
        $callback = fn(Rule $rule, Token $token) => new RegexRule('~\Gtest~u');
        $triggerRule = 'trigger';
        $listenInRegions = ['region1', 'region2'];

        $callbackRule = new CallbackRule($callback, $triggerRule, $listenInRegions);

        self::assertSame($callback, $callbackRule->callback);
        self::assertSame($triggerRule, $callbackRule->triggerRule);
        self::assertSame($listenInRegions, $callbackRule->listenInRegions);
    }

    #[Test]
    public function shouldInvokeCallbackWhenToTokenRule(): void
    {
        $invoked = false;
        $callback = function(Rule $rule, Token $token) use (&$invoked): RegexRule {
            $invoked = true;
            return new RegexRule('~\Gtest~u');
        };

        $callbackRule = new CallbackRule($callback, 'trigger', ['region']);
        $rule = new Rule('test', RuleType::Token, new RegexRule('~\Gtest~u'));
        $token = Token::default('trigger', 'value', 0, 5);

        $callbackRule->toTokenRule($rule, $token);

        self::assertTrue($invoked);
    }

    #[Test]
    public function shouldReturnRuleWithExpressionTypeWhenToTokenRule(): void
    {
        $callback = fn(Rule $rule, Token $token) => new RegexRule('~\Gtest~u');
        $callbackRule = new CallbackRule($callback, 'trigger', ['region']);
        
        $rule = new Rule('test', RuleType::Token, new RegexRule('~\Gtest~u'));
        $token = Token::default('trigger', 'value', 0, 5);

        $result = $callbackRule->toTokenRule($rule, $token);

        self::assertInstanceOf(Rule::class, $result);
        self::assertSame(RuleType::Expression, $result->type);
    }

    #[Test]
    public function shouldThrowExceptionWhenCallbackDoesNotReturnRegexRule(): void
    {
        $callback = fn(Rule $rule, Token $token) => 'not a RegexRule';
        $callbackRule = new CallbackRule($callback, 'trigger', ['region']);
        
        $rule = new Rule('test', RuleType::Token, new RegexRule('~\Gtest~u'));
        $token = Token::default('trigger', 'value', 0, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Callback must return RegexRule');

        $callbackRule->toTokenRule($rule, $token);
    }
}
