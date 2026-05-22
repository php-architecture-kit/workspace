<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition\Service;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Cardinality;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Service\SequenceExtender\SequenceExtender;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('func')]
final class SequenceExtenderTest extends TestCase
{
    #[Test]
    public function shouldAddNodeBeforeMatchingNodeWhenUsingAddPrev(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->addPrev('?ws*');

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(4, $result->nodes);
        $this->assertSame('token', $result->nodes[0]->toString());
        $this->assertSame('ws*', $result->nodes[1]->toString());
        $this->assertSame('separator', $result->nodes[2]->toString());
        $this->assertSame('value', $result->nodes[3]->toString());
    }

    #[Test]
    public function shouldAddNodeAfterMatchingNodeWhenUsingAddNext(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->addNext('?ws*');

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(4, $result->nodes);
        $this->assertSame('token', $result->nodes[0]->toString());
        $this->assertSame('separator', $result->nodes[1]->toString());
        $this->assertSame('ws*', $result->nodes[2]->toString());
        $this->assertSame('value', $result->nodes[3]->toString());
    }

    #[Test]
    public function shouldNotAddNodeWhenMatcherReturnsFalse(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('nonexistent', $node->alternatives))
            ->addNext('?ws*');

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(3, $result->nodes);
        $this->assertSame('token', $result->nodes[0]->toString());
        $this->assertSame('separator', $result->nodes[1]->toString());
        $this->assertSame('value', $result->nodes[2]->toString());
    }

    #[Test]
    public function shouldAddNodeOnlyWhenContextMatcherPasses(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->addNext('?ws*')
            ->which(fn($contextNode) => in_array('value', $contextNode->alternatives));

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(4, $result->nodes);
        $this->assertSame('separator', $result->nodes[1]->toString());
        $this->assertSame('ws*', $result->nodes[2]->toString());
    }

    #[Test]
    public function shouldNotAddNodeWhenContextMatcherFails(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->addNext('?ws*')
            ->which(fn($contextNode) => in_array('nonexistent', $contextNode->alternatives));

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(3, $result->nodes);
    }

    #[Test]
    public function shouldAddMultipleNodesWhenMultipleRulesMatch(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node, $index, $nodes) => $index < count($nodes) - 1)
            ->addNext('?ws*');

        $sequence = SequenceRule::fromString('a b c');
        $result = $extender->extend($sequence);

        $this->assertCount(5, $result->nodes);
        $this->assertSame('a', $result->nodes[0]->toString());
        $this->assertSame('ws*', $result->nodes[1]->toString());
        $this->assertSame('b', $result->nodes[2]->toString());
        $this->assertSame('ws*', $result->nodes[3]->toString());
        $this->assertSame('c', $result->nodes[4]->toString());
    }

    #[Test]
    public function shouldModifyNodeWhenUsingModify(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('token', $node->alternatives))
            ->modify(fn($node, $context) => new SequenceNode(
                ['modified'],
                $node->cardinality,
                $node->isLookahead,
                $node->isLookbehind,
                $node->anchorName,
                $node->tags,
            ));

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(3, $result->nodes);
        $this->assertSame('modified', $result->nodes[0]->toString());
        $this->assertSame('separator', $result->nodes[1]->toString());
        $this->assertSame('value', $result->nodes[2]->toString());
    }

    #[Test]
    public function shouldNotModifyNodeWhenMatcherReturnsFalse(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('nonexistent', $node->alternatives))
            ->modify(fn($node) => new SequenceNode(['modified'], Cardinality::ExactlyOne));

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(3, $result->nodes);
        $this->assertSame('token', $result->nodes[0]->toString());
    }

    #[Test]
    public function shouldModifyNodeOnlyWhenContextMatcherPasses(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->modify(fn($node) => new SequenceNode(['modified'], Cardinality::ExactlyOne))
            ->which(fn($prevNode) => $prevNode !== null && in_array('token', $prevNode->alternatives));

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(3, $result->nodes);
        $this->assertSame('token', $result->nodes[0]->toString());
        $this->assertSame('modified', $result->nodes[1]->toString());
        $this->assertSame('value', $result->nodes[2]->toString());
    }

    #[Test]
    public function shouldPassCorrectContextToModifyCallback(): void
    {
        $capturedContext = [];

        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->modify(function ($node, $context) use (&$capturedContext) {
                $capturedContext = $context;
                return $node;
            });

        $sequence = SequenceRule::fromString('token separator value');
        $extender->extend($sequence);

        $this->assertIsArray($capturedContext);
        $this->assertArrayHasKey('prev', $capturedContext);
        $this->assertArrayHasKey('current', $capturedContext);
        $this->assertArrayHasKey('next', $capturedContext);

        $this->assertNotNull($capturedContext['prev']);
        $this->assertNotNull($capturedContext['current']);
        $this->assertNotNull($capturedContext['next']);

        $this->assertSame('token', $capturedContext['prev']->toString());
        $this->assertSame('separator', $capturedContext['current']->toString());
        $this->assertSame('value', $capturedContext['next']->toString());
    }

    #[Test]
    public function shouldRemoveNodeWhenUsingRemove(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->remove();

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(2, $result->nodes);
        $this->assertSame('token', $result->nodes[0]->toString());
        $this->assertSame('value', $result->nodes[1]->toString());
    }

    #[Test]
    public function shouldNotRemoveNodeWhenMatcherReturnsFalse(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('nonexistent', $node->alternatives))
            ->remove();

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(3, $result->nodes);
    }

    #[Test]
    public function shouldRemoveNodeOnlyWhenContextMatcherPasses(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->remove()
            ->which(fn($prevNode) => $prevNode !== null && in_array('token', $prevNode->alternatives));

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(2, $result->nodes);
        $this->assertSame('token', $result->nodes[0]->toString());
        $this->assertSame('value', $result->nodes[1]->toString());
    }

    #[Test]
    public function shouldApplyMultipleRulesInOrder(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('a', $node->alternatives))
            ->addNext('ws1')
            ->always()
            ->when(fn($node) => in_array('b', $node->alternatives))
            ->addNext('ws2')
            ->always();

        $sequence = SequenceRule::fromString('a b c');
        $result = $extender->extend($sequence);

        $this->assertCount(5, $result->nodes);
        $this->assertSame('a', $result->nodes[0]->toString());
        $this->assertSame('ws1', $result->nodes[1]->toString());
        $this->assertSame('b', $result->nodes[2]->toString());
        $this->assertSame('ws2', $result->nodes[3]->toString());
        $this->assertSame('c', $result->nodes[4]->toString());
    }

    #[Test]
    public function shouldHandleEdgeCasesWhenAddingToFirstNode(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node, $index) => $index === 0)
            ->addPrev('?ws*')
            ->which(fn($contextNode) => $contextNode === null);

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(4, $result->nodes);
        $this->assertSame('ws*', $result->nodes[0]->toString());
        $this->assertSame('token', $result->nodes[1]->toString());
    }

    #[Test]
    public function shouldHandleEdgeCasesWhenAddingToLastNode(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node, $index, $nodes) => $index === count($nodes) - 1)
            ->addNext('?ws*')
            ->which(fn($contextNode) => $contextNode === null);

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(4, $result->nodes);
        $this->assertSame('value', $result->nodes[2]->toString());
        $this->assertSame('ws*', $result->nodes[3]->toString());
    }

    #[Test]
    public function shouldNotModifyOriginalSequenceRule(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->addNext('?ws*');

        $sequence = SequenceRule::fromString('token separator value');
        $originalNodeCount = count($sequence->nodes);

        $result = $extender->extend($sequence);

        $this->assertCount($originalNodeCount, $sequence->nodes);
        $this->assertNotSame($sequence, $result);
        $this->assertCount(4, $result->nodes);
    }

    #[Test]
    public function shouldHandleComplexScenarioWithMultipleOperations(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node, $index, $nodes) => $index < count($nodes) - 1)
            ->addNext('?ws*')
            ->always()
            ->when(fn($node) => in_array('temp', $node->alternatives))
            ->remove()
            ->always()
            ->when(fn($node) => in_array('token', $node->alternatives))
            ->modify(fn($node) => new SequenceNode(['modified-token'], Cardinality::ExactlyOne))
            ->always();

        $sequence = SequenceRule::fromString('token temp separator value');
        $result = $extender->extend($sequence);

        $this->assertSame('modified-token', $result->nodes[0]->toString());

        $hasTemp = false;
        foreach ($result->nodes as $node) {
            if (in_array('temp', $node->alternatives)) {
                $hasTemp = true;
                break;
            }
        }
        $this->assertFalse($hasTemp);

        $wsCount = 0;
        foreach ($result->nodes as $node) {
            if (in_array('ws', $node->alternatives)) {
                $wsCount++;
            }
        }
        $this->assertGreaterThan(0, $wsCount);
    }

    #[Test]
    public function shouldHandleContextMatcherWithPrevPosition(): void
    {
        $extender = new SequenceExtender();
        $extender
            ->when(fn($node) => in_array('separator', $node->alternatives))
            ->addPrev('leading-ws')
            ->which(fn($prevNode) => $prevNode !== null && in_array('token', $prevNode->alternatives));

        $sequence = SequenceRule::fromString('token separator value');
        $result = $extender->extend($sequence);

        $this->assertCount(4, $result->nodes);
        $this->assertSame('token', $result->nodes[0]->toString());
        $this->assertSame('leading-ws', $result->nodes[1]->toString());
        $this->assertSame('separator', $result->nodes[2]->toString());
    }

    #[Test]
    public function shouldUseCallableForDynamicNodeCreation(): void
    {
        $counter = 0;

        $extender = new SequenceExtender();
        $extender
            ->when(fn($node, $index, $nodes) => $index < count($nodes) - 1)
            ->addNext(function ($node, $context) use (&$counter) {
                $counter++;
                return SequenceNode::fromString("ws{$counter}");
            });

        $sequence = SequenceRule::fromString('a b c');
        $result = $extender->extend($sequence);

        $this->assertCount(5, $result->nodes);
        $this->assertSame('ws1', $result->nodes[1]->toString());
        $this->assertSame('ws2', $result->nodes[3]->toString());
    }
}
