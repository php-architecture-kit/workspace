<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegionTest extends TestCase
{
    #[Test]
    public function shouldSetNameThroughConstructor(): void
    {
        $region = new Region('myRegion');

        self::assertSame('myRegion', $region->name);
    }

    #[Test]
    public function shouldAddRuleToRulesCollectionKeyedByName(): void
    {
        $region = new Region('myRegion');
        $rule = Rule::token('myToken', 'x');

        $region->add($rule);

        self::assertArrayHasKey('myToken', $region->rules);
        self::assertInstanceOf(Rule::class, $region->rules['myToken']);
    }

    #[Test]
    public function shouldAddNestedRegionToRegionsCollectionKeyedByName(): void
    {
        $region = new Region('parent');
        $child = new Region('child');

        $region->add($child);

        self::assertArrayHasKey('child', $region->regions);
        self::assertSame($child, $region->regions['child']);
    }

    #[Test]
    public function shouldAddEventSubscriberToEventSubscribersCollection(): void
    {
        $region = new Region('myRegion');
        $subscriber = EventSubscriber::on(TokenAddedEvent::class, function () {});

        $initialCount = count($region->eventSubscribers);
        $region->add($subscriber);

        self::assertCount($initialCount + 1, $region->eventSubscribers);
    }

    #[Test]
    public function shouldAddMiddlewareToMiddlewaresCollection(): void
    {
        $region = new Region('myRegion');
        $middleware = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r);

        $initialCount = count($region->middlewares[GrammarMiddleware::ADD_RULE] ?? []);
        $region->add($middleware);

        self::assertCount($initialCount + 1, $region->middlewares[GrammarMiddleware::ADD_RULE]);
    }

    #[Test]
    public function shouldReturnAllNestedRegionsRecursively(): void
    {
        $root = new Region('root');
        $child = new Region('child');
        $grandchild = new Region('grandchild');

        $child->add($grandchild);
        $root->add($child);

        $all = $root->getRegionsRecursively();

        self::assertArrayHasKey('child', $all);
        self::assertArrayHasKey('grandchild', $all);
    }

    // --- Tags ---

    #[Test]
    public function shouldAddTagViaAddTag(): void
    {
        $region = new Region('myRegion');

        $region->addTag('foo');

        self::assertTrue($region->hasTag('foo'));
    }

    #[Test]
    public function shouldReturnSelfFromAddTag(): void
    {
        $region = new Region('myRegion');

        self::assertSame($region, $region->addTag('foo'));
    }

    #[Test]
    public function shouldRemoveTagViaRemoveTag(): void
    {
        $region = new Region('myRegion');
        $region->addTag('foo');

        $region->removeTag('foo');

        self::assertFalse($region->hasTag('foo'));
    }

    #[Test]
    public function shouldReplaceAllTagsViaReplaceTags(): void
    {
        $region = new Region('myRegion');
        $region->addTag('old');

        $region->replaceTags(['new']);

        self::assertSame(['new'], $region->getAllTags());
    }

    #[Test]
    public function shouldReturnEmptyArrayAfterClearTags(): void
    {
        $region = new Region('myRegion');
        $region->addTag('a', 'b');

        $region->clearTags();

        self::assertSame([], $region->getAllTags());
    }

    // --- RegionConfig ---

    #[Test]
    public function shouldSetOpenerViaOpenWith(): void
    {
        $region = new Region('myRegion');
        $rule = Rule::token('open', '{');

        $region->openWith($rule);

        self::assertNotNull($region->config->opener);
    }

    #[Test]
    public function shouldSetCloserViaCloseWith(): void
    {
        $region = new Region('myRegion');
        $rule = Rule::token('close', '}');

        $region->closeWith($rule);

        self::assertNotNull($region->config->closer);
    }

    #[Test]
    public function shouldSetRootSequenceViaWithRootSequence(): void
    {
        $region = new Region('myRegion');

        $region->withRootSequence('ruleA ruleB');

        self::assertInstanceOf(SequenceRule::class, $region->config->rootSequence);
    }

    #[Test]
    public function shouldClearRootSequenceWhenPassingFalseToWithRootSequence(): void
    {
        $region = new Region('myRegion');
        $region->withRootSequence('ruleA ruleB');

        $region->withRootSequence(false);

        self::assertNull($region->config->rootSequence);
    }

    #[Test]
    public function shouldSetNodeTypeViaSetNodeType(): void
    {
        $region = new Region('myRegion');

        $region->setNodeType(NodeType::Raw);

        self::assertSame(NodeType::Raw, $region->config->nodeType);
    }

    #[Test]
    public function shouldSetInheritanceFromGlobalFlagViaEnableInheritanceFromGlobal(): void
    {
        $region = new Region('myRegion');

        $region->enableInheritanceFromGlobal(Region::RULES);

        self::assertSame(Region::RULES, $region->config->inheritanceFromGlobal & Region::RULES);
    }

    #[Test]
    public function shouldClearInheritanceFromGlobalFlagViaDisableInheritanceFromGlobal(): void
    {
        $region = new Region('myRegion');
        $region->enableInheritanceFromGlobal(Region::RULES | Region::REGIONS);

        $region->disableInheritanceFromGlobal(Region::RULES);

        self::assertSame(Region::NONE, $region->config->inheritanceFromGlobal & Region::RULES);
        self::assertSame(Region::REGIONS, $region->config->inheritanceFromGlobal & Region::REGIONS);
    }

    #[Test]
    public function shouldSetInheritanceFromAncestorFlagViaEnableInheritanceFromAncestor(): void
    {
        $region = new Region('myRegion');

        $region->enableInheritanceFromAncestor(Region::RULES);

        self::assertSame(Region::RULES, $region->config->inheritanceFromAncestor & Region::RULES);
    }

    #[Test]
    public function shouldSetInnerGrammarAndRetokenizeFlagViaRetokenizedByInnerGrammar(): void
    {
        $region = new Region('myRegion');
        $inner = new Grammar('inner');

        $region->retokenizedByInnerGrammar($inner);

        self::assertSame($inner, $region->config->innerGrammar);
        self::assertTrue($region->config->retokenizeWithInnerGrammar);
    }

    #[Test]
    public function shouldSetInnerGrammarAndMergeFlagViaWithMergedInnerGrammar(): void
    {
        $region = new Region('myRegion');
        $inner = new Grammar('inner');

        $region->withMergedInnerGrammar($inner);

        self::assertSame($inner, $region->config->innerGrammar);
        self::assertFalse($region->config->retokenizeWithInnerGrammar);
    }
}
