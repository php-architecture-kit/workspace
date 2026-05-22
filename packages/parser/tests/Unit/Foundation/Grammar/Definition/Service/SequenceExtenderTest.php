<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Service;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Service\SequenceExtender\SequenceExtender;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Service\SequenceExtender\SequenceExtenderRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Service\SequenceExtender\SequenceExtenderRuleContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use LogicException;

#[Group('unit')]
final class SequenceExtenderTest extends TestCase
{
    #[Test]
    public function shouldReturnSequenceExtenderRuleWhenCallingWhen(): void
    {
        $extender = new SequenceExtender();
        
        $result = $extender->when(fn() => true);
        
        $this->assertInstanceOf(SequenceExtenderRule::class, $result);
    }

    #[Test]
    public function shouldAddRuleToInternalArrayWhenCallingAddRule(): void
    {
        $extender = new SequenceExtender();
        $matcher = fn($node) => true;
        $callback = fn($node) => $node;
        
        $extender->addRule($matcher, 'add', 'next', $callback, null);
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertCount(1, $rules);
        $this->assertSame('add', $rules[0]['action']);
        $this->assertSame('next', $rules[0]['position']);
        $this->assertNull($rules[0]['contextMatcher']);
    }

    #[Test]
    public function shouldReturnSelfWhenCallingAddRule(): void
    {
        $extender = new SequenceExtender();
        
        $result = $extender->addRule(
            fn() => true,
            'add',
            'next',
            fn($node) => $node,
        );
        
        $this->assertSame($extender, $result);
    }

    #[Test]
    public function shouldRegisterAddPrevRuleWhenCallingAddPrev(): void
    {
        $extender = new SequenceExtender();
        
        $extender->when(fn($node) => true)->addPrev('?ws*')->always();
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertCount(1, $rules);
        $this->assertSame('add', $rules[0]['action']);
        $this->assertSame('prev', $rules[0]['position']);
    }

    #[Test]
    public function shouldRegisterAddNextRuleWhenCallingAddNext(): void
    {
        $extender = new SequenceExtender();
        
        $extender->when(fn($node) => true)->addNext('?ws*')->always();
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rulesProperty->setAccessible(true);
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertCount(1, $rules);
        $this->assertSame('add', $rules[0]['action']);
        $this->assertSame('next', $rules[0]['position']);
    }

    #[Test]
    public function shouldRegisterModifyRuleWhenCallingModify(): void
    {
        $extender = new SequenceExtender();
        
        $extender->when(fn($node) => true)->modify(fn($node) => $node)->always();
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertCount(1, $rules);
        $this->assertSame('modify', $rules[0]['action']);
        $this->assertSame('exact', $rules[0]['position']);
    }

    #[Test]
    public function shouldRegisterRemoveRuleWhenCallingRemove(): void
    {
        $extender = new SequenceExtender();
        
        $extender->when(fn($node) => true)->remove()->always();
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertCount(1, $rules);
        $this->assertSame('remove', $rules[0]['action']);
        $this->assertSame('exact', $rules[0]['position']);
    }

    #[Test]
    public function shouldNormalizeStringToSequenceNodeWhenCallingAddPrev(): void
    {
        $extender = new SequenceExtender();
        
        $extender->when(fn($node) => true)->addPrev('?ws*');
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertIsCallable($rules[0]['callback']);
        
        $result = ($rules[0]['callback'])(SequenceNode::fromString('test'), []);
        $this->assertInstanceOf(SequenceNode::class, $result);
        $this->assertSame('ws*', $result->toString());
    }

    #[Test]
    public function shouldAcceptSequenceNodeDirectlyWhenCallingAddNext(): void
    {
        $extender = new SequenceExtender();
        $node = SequenceNode::fromString('?ws*');
        
        $extender->when(fn($n) => true)->addNext($node);
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $result = ($rules[0]['callback'])(SequenceNode::fromString('test'), []);
        $this->assertSame($node, $result);
    }

    #[Test]
    public function shouldAcceptCallableWhenCallingAddNext(): void
    {
        $extender = new SequenceExtender();
        $customCallback = fn($node, $context) => SequenceNode::fromString('custom');
        
        $extender->when(fn($n) => true)->addNext($customCallback);
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $result = ($rules[0]['callback'])(SequenceNode::fromString('test'), []);
        $this->assertInstanceOf(SequenceNode::class, $result);
        $this->assertSame('custom', $result->toString());
    }

    #[Test]
    public function shouldRegisterRuleWithContextMatcherWhenCallingWhich(): void
    {
        $extender = new SequenceExtender();
        $contextMatcher = fn($contextNode) => true;
        
        $extender
            ->when(fn($node) => true)
            ->addNext('?ws*')
            ->which($contextMatcher);
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertCount(1, $rules);
        $this->assertNotNull($rules[0]['contextMatcher']);
    }

    #[Test]
    public function shouldReturnExtenderWhenCallingWhich(): void
    {
        $extender = new SequenceExtender();
        
        $result = $extender
            ->when(fn($node) => true)
            ->addNext('?ws*')
            ->which(fn($contextNode) => true);
        
        $this->assertSame($extender, $result);
    }

    #[Test]
    public function shouldThrowExceptionWhenCallingWhichAfterRegistration(): void
    {
        $extender = new SequenceExtender();
        
        $ruleContext = $extender->when(fn($node) => true)->addNext('?ws*');
        $ruleContext->always();
        
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Rule already registered');
        
        $ruleContext->which(fn($contextNode) => true);
    }

    #[Test]
    public function shouldRegisterRuleWithoutContextMatcherWhenAutoRegistering(): void
    {
        $extender = new SequenceExtender();
        
        $extender->when(fn($node) => true)->addNext('?ws*')->always();
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertCount(1, $rules);
        $this->assertNull($rules[0]['contextMatcher']);
    }

    #[Test]
    public function shouldChainMultipleRules(): void
    {
        $extender = new SequenceExtender();
        
        $result = $extender
            ->when(fn($node) => in_array('a', $node->alternatives))
            ->addNext('?ws*')
            ->always()
            ->when(fn($node) => in_array('b', $node->alternatives))
            ->remove()
            ->always();
        
        $this->assertSame($extender, $result);
        
        $reflection = new ReflectionClass($extender);
        $rulesProperty = $reflection->getProperty('rules');
        $rules = $rulesProperty->getValue($extender);
        
        $this->assertCount(2, $rules);
    }
}
