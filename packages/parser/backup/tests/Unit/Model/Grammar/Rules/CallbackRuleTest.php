<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Parser\Unit\Model\Grammar\Rules;

use InvalidArgumentException;
use PhpArchitecture\Parser\Model\Grammar\Rule;
use PhpArchitecture\Parser\Model\Grammar\Rules\CallbackRule;
use PhpArchitecture\Parser\Model\Grammar\Rules\RegexRule;
use PhpArchitecture\Parser\Model\Grammar\Rules\SequenceRule;
use PhpArchitecture\Parser\Model\Grammar\RuleType;
use PhpArchitecture\Parser\Model\Token\TokenInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CallbackRuleTest extends TestCase
{
    #[Test]
    public function constructorSetsCallbackProperty(): void
    {
        $callback = fn() => new RegexRule('test');
        $rule = new CallbackRule($callback);

        $this->assertSame($callback, $rule->callback);
    }

    #[Test]
    public function toTokenRuleExecutesCallbackAndReturnsRule(): void
    {
        $expectedRegex = '~\Gtest~u';
        $callback = fn(Rule $rule, TokenInterface $trigger) => new RegexRule($expectedRegex);
        
        $callbackRule = new CallbackRule($callback);
        $inputRule = new Rule('testRule', RuleType::Sequence, SequenceRule::fromString('token'));
        $token = $this->createMock(TokenInterface::class);

        $result = $callbackRule->toTokenRule($inputRule, $token);

        $this->assertInstanceOf(Rule::class, $result);
        $this->assertSame('testRule', $result->name);
        $this->assertSame(RuleType::Expression, $result->type);
        $this->assertInstanceOf(RegexRule::class, $result->definition);
        $this->assertSame($expectedRegex, $result->definition->regex);
    }

    #[Test]
    public function toTokenRulePassesCorrectParametersToCallback(): void
    {
        $capturedRule = null;
        $capturedToken = null;
        
        $callback = function(Rule $rule, TokenInterface $trigger) use (&$capturedRule, &$capturedToken) {
            $capturedRule = $rule;
            $capturedToken = $trigger;
            return new RegexRule('test');
        };
        
        $callbackRule = new CallbackRule($callback);
        $inputRule = new Rule('myRule', RuleType::Sequence, SequenceRule::fromString('token'));
        $token = $this->createMock(TokenInterface::class);

        $callbackRule->toTokenRule($inputRule, $token);

        $this->assertSame($inputRule, $capturedRule);
        $this->assertSame($token, $capturedToken);
    }

    #[Test]
    public function toTokenRuleThrowsExceptionWhenCallbackReturnsNonRegexRule(): void
    {
        $callback = fn(Rule $rule, TokenInterface $trigger) => 'not a RegexRule';
        
        $callbackRule = new CallbackRule($callback);
        $inputRule = new Rule('testRule', RuleType::Sequence, SequenceRule::fromString('token'));
        $token = $this->createMock(TokenInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Callback must return RegexRule');
        
        $callbackRule->toTokenRule($inputRule, $token);
    }

    #[Test]
    public function toTokenRuleThrowsExceptionWhenCallbackReturnsNull(): void
    {
        $callback = fn(Rule $rule, TokenInterface $trigger) => null;
        
        $callbackRule = new CallbackRule($callback);
        $inputRule = new Rule('testRule', RuleType::Sequence, SequenceRule::fromString('token'));
        $token = $this->createMock(TokenInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Callback must return RegexRule');
        
        $callbackRule->toTokenRule($inputRule, $token);
    }

    #[Test]
    public function toTokenRuleThrowsExceptionWhenCallbackReturnsWrongObject(): void
    {
        $callback = fn(Rule $rule, TokenInterface $trigger) => new SequenceRule([]);
        
        $callbackRule = new CallbackRule($callback);
        $inputRule = new Rule('testRule', RuleType::Sequence, SequenceRule::fromString('token'));
        $token = $this->createMock(TokenInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Callback must return RegexRule');
        
        $callbackRule->toTokenRule($inputRule, $token);
    }

    #[Test]
    public function toTokenRuleCreatesRuleWithExpressionType(): void
    {
        $callback = fn(Rule $rule, TokenInterface $trigger) => new RegexRule('pattern');
        
        $callbackRule = new CallbackRule($callback);
        $inputRule = new Rule('testRule', RuleType::Sequence, SequenceRule::fromString('token'));
        $token = $this->createMock(TokenInterface::class);

        $result = $callbackRule->toTokenRule($inputRule, $token);

        $this->assertSame(RuleType::Expression, $result->type);
    }

    #[Test]
    public function toTokenRulePreservesOriginalRuleName(): void
    {
        $callback = fn(Rule $rule, TokenInterface $trigger) => new RegexRule('pattern');
        
        $callbackRule = new CallbackRule($callback);
        $originalName = 'originalRuleName';
        $inputRule = new Rule($originalName, RuleType::Sequence, SequenceRule::fromString('token'));
        $token = $this->createMock(TokenInterface::class);

        $result = $callbackRule->toTokenRule($inputRule, $token);

        $this->assertSame($originalName, $result->name);
    }

    #[Test]
    public function callbackCanAccessTokenProperties(): void
    {
        $tokenValue = 'expectedValue';
        $callback = function(Rule $rule, TokenInterface $trigger) use ($tokenValue) {
            $pattern = $trigger->raw() === $tokenValue ? 'match' : 'nomatch';
            return new RegexRule($pattern);
        };
        
        $callbackRule = new CallbackRule($callback);
        $inputRule = new Rule('testRule', RuleType::Sequence, SequenceRule::fromString('token'));
        
        $token = $this->createMock(TokenInterface::class);
        $token->method('raw')->willReturn($tokenValue);

        $result = $callbackRule->toTokenRule($inputRule, $token);

        $this->assertSame('match', $result->definition->regex);
    }

    #[Test]
    public function multipleCallsWithDifferentTokensProduceDifferentResults(): void
    {
        $callback = function(Rule $rule, TokenInterface $trigger) {
            return new RegexRule('pattern_' . $trigger->raw());
        };
        
        $callbackRule = new CallbackRule($callback);
        $inputRule = new Rule('testRule', RuleType::Sequence, SequenceRule::fromString('token'));
        
        $token1 = $this->createMock(TokenInterface::class);
        $token1->method('raw')->willReturn('first');
        
        $token2 = $this->createMock(TokenInterface::class);
        $token2->method('raw')->willReturn('second');

        $result1 = $callbackRule->toTokenRule($inputRule, $token1);
        $result2 = $callbackRule->toTokenRule($inputRule, $token2);

        $this->assertSame('pattern_first', $result1->definition->regex);
        $this->assertSame('pattern_second', $result2->definition->regex);
    }
}
