<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\RegexRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Technical\TaggedRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Technical\TechnicalTokenRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RuleTest extends TestCase
{
    // --- Rule::token() ---

    #[Test]
    public function shouldCreateTokenRuleWithCorrectType(): void
    {
        $rule = Rule::token('myToken', '[');

        self::assertSame(RuleType::Token, $rule->type);
    }

    #[Test]
    public function shouldCreateTokenRuleWithRegexRuleDefinition(): void
    {
        $rule = Rule::token('myToken', '[');

        self::assertInstanceOf(RegexRule::class, $rule->definition);
    }

    // --- Rule::keyword() ---

    #[Test]
    public function shouldCreateKeywordRuleWithCorrectType(): void
    {
        $rule = Rule::keyword('null');

        self::assertSame(RuleType::Keyword, $rule->type);
    }

    #[Test]
    public function shouldCreateKeywordRuleWithRegexRuleDefinition(): void
    {
        $rule = Rule::keyword('null');

        self::assertInstanceOf(RegexRule::class, $rule->definition);
    }

    #[Test]
    public function shouldUseKeywordAsNameByDefault(): void
    {
        $rule = Rule::keyword('null');

        self::assertSame('null', $rule->name);
    }

    // --- Rule::expr() ---

    #[Test]
    public function shouldCreateExpressionRuleWithCorrectType(): void
    {
        $rule = Rule::expr('digits', '[0-9]+');

        self::assertSame(RuleType::Expression, $rule->type);
    }

    #[Test]
    public function shouldCreateExpressionRuleWithRegexRuleDefinition(): void
    {
        $rule = Rule::expr('digits', '[0-9]+');

        self::assertInstanceOf(RegexRule::class, $rule->definition);
    }

    // --- Rule::dynamic() ---

    #[Test]
    public function shouldCreateDynamicTokenRuleWithCorrectType(): void
    {
        $rule = Rule::dynamic('dyn', fn($r, $t) => RegexRule::fromString('[a-z]+'), 'trigger');

        self::assertSame(RuleType::DynamicToken, $rule->type);
    }

    #[Test]
    public function shouldCreateDynamicTokenRuleWithCallbackRuleDefinition(): void
    {
        $rule = Rule::dynamic('dyn', fn($r, $t) => RegexRule::fromString('[a-z]+'), 'trigger');

        self::assertInstanceOf(CallbackRule::class, $rule->definition);
    }

    #[Test]
    public function shouldPassTriggerRuleNameToCallbackRule(): void
    {
        $rule = Rule::dynamic('dyn', fn($r, $t) => RegexRule::fromString('[a-z]+'), 'myTrigger');

        self::assertSame('myTrigger', $rule->definition->triggerRule);
    }

    // --- Rule::technical() ---

    #[Test]
    public function shouldCreateTechnicalRuleWithTokenType(): void
    {
        $rule = Rule::technical('bof');

        self::assertSame(RuleType::Token, $rule->type);
    }

    #[Test]
    public function shouldCreateTechnicalRuleWithTechnicalTokenRuleDefinition(): void
    {
        $rule = Rule::technical('eof');

        self::assertInstanceOf(TechnicalTokenRule::class, $rule->definition);
    }

    // --- Rule::seq() ---

    #[Test]
    public function shouldCreateSequenceRuleWithCorrectType(): void
    {
        $rule = Rule::seq('mySeq', 'ruleA ruleB');

        self::assertSame(RuleType::Sequence, $rule->type);
    }

    #[Test]
    public function shouldCreateSequenceRuleWithSequenceRuleDefinition(): void
    {
        $rule = Rule::seq('mySeq', 'ruleA ruleB');

        self::assertInstanceOf(SequenceRule::class, $rule->definition);
    }

    // --- Rule::choice() ---

    #[Test]
    public function shouldCreateChoiceRuleWithCorrectType(): void
    {
        $rule = Rule::choice('myChoice', ['optA', 'optB']);

        self::assertSame(RuleType::Choice, $rule->type);
    }

    #[Test]
    public function shouldCreateChoiceRuleWithSequenceRuleDefinition(): void
    {
        $rule = Rule::choice('myChoice', ['optA', 'optB']);

        self::assertInstanceOf(SequenceRule::class, $rule->definition);
    }

    #[Test]
    public function shouldStoreInlineRuleObjectsInInheritedRuleDefs(): void
    {
        $inlineRule = Rule::token('inline', 'x');
        $rule = Rule::choice('myChoice', [$inlineRule, 'other']);

        self::assertContains($inlineRule, $rule->inheritedRuleDefs);
    }

    // --- Rule::taggedWith() ---

    #[Test]
    public function shouldCreateTaggedRuleWithCorrectType(): void
    {
        $rule = Rule::taggedWith('myTag');

        self::assertSame(RuleType::Tag, $rule->type);
    }

    #[Test]
    public function shouldCreateTaggedRuleWithTaggedRuleDefinition(): void
    {
        $rule = Rule::taggedWith('myTag');

        self::assertInstanceOf(TaggedRule::class, $rule->definition);
    }

    #[Test]
    public function shouldPassTagNameToTaggedRuleDefinition(): void
    {
        $rule = Rule::taggedWith('myTag');

        self::assertSame('myTag', $rule->definition->tag);
    }

    // --- Tags ---

    #[Test]
    public function shouldSetTagsPassedToFactoryMethod(): void
    {
        $rule = Rule::token('t', 'x', tags: ['value', 'item']);

        self::assertTrue($rule->hasTag('value'));
        self::assertTrue($rule->hasTag('item'));
    }

    #[Test]
    public function shouldAddTagViaAddTag(): void
    {
        $rule = Rule::token('t', 'x');

        $rule->addTag('foo');

        self::assertTrue($rule->hasTag('foo'));
    }

    // --- Configuration ---

    #[Test]
    public function shouldSetNodeTypePassedToFactoryMethod(): void
    {
        $rule = Rule::token('t', 'x', type: NodeType::Structure);

        self::assertSame(NodeType::Structure, $rule->nodeType);
    }

    #[Test]
    public function shouldSetNodeTypeViaSetNodeType(): void
    {
        $rule = Rule::token('t', 'x');

        $rule->setNodeType(NodeType::Raw);

        self::assertSame(NodeType::Raw, $rule->nodeType);
    }

    #[Test]
    public function shouldSetPriorityViaPriorityMethod(): void
    {
        $rule = Rule::token('t', 'x');

        $rule->priority(10);

        self::assertSame(10, $rule->priority);
    }

    #[Test]
    public function shouldHaveZeroPriorityByDefault(): void
    {
        $rule = Rule::token('t', 'x');

        self::assertSame(0, $rule->priority);
    }

    #[Test]
    public function shouldAddEventSubscriberViaOnEvent(): void
    {
        $rule = Rule::token('t', 'x');

        $rule->onEvent(TokenAddedEvent::class, function () {});

        self::assertCount(1, $rule->eventSubscribers);
    }
}
