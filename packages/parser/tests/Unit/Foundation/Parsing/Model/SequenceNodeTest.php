<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Parsing\Model;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Cardinality;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode as GrammarSequenceNode;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;

#[Group('unit')]
final class SequenceNodeTest extends TestCase
{
    private static function attr(string $name): NodeAttributeInterface
    {
        return new class($name) implements NodeAttributeInterface {
            public function __construct(private readonly string $name) {}
            public function getName(): string { return $this->name; }
            public function withParent(NodeInterface $parent): static { return $this; }
            public function __toString(): string { return $this->name; }
        };
    }

    /** member comma member */
    private static function memberCommaMember(): NestedSequence
    {
        return new NestedSequence([[
            new GrammarSequenceNode(['member'], Cardinality::ExactlyOne),
            new GrammarSequenceNode(['comma'], Cardinality::ExactlyOne),
            new GrammarSequenceNode(['member'], Cardinality::ExactlyOne),
        ]], Cardinality::ExactlyOne);
    }

    #[Test]
    public function isASequenceCarrier_addUnitAutoInsertsStructural(): void
    {
        $node = new SequenceNode('members', NodeOrigin::Sequence, [], null);
        $node->withValidSequence(self::memberCommaMember(), [
            'comma' => static fn(): NodeAttributeInterface => self::attr('comma'),
        ]);

        $node->addUnit(self::attr('member'));
        $node->addUnit(self::attr('member')); // expects comma next -> auto-inserted

        $this->assertSame(2, $node->getUnitCount());
        $this->assertSame('membercommamember', (string) $node);
    }

    #[Test]
    public function parsePathAppendIsUnchanged(): void
    {
        // addAttribute keeps AbstractNode's plain append (no cursor), so the parse
        // path is unaffected by the carrier.
        $node = new SequenceNode('members', NodeOrigin::Sequence, [], null);
        $node->addAttribute(self::attr('comma'));
        $node->addAttribute(self::attr('member'));

        $this->assertSame('commamember', (string) $node);
        $this->assertCount(2, $node->getAttributes());
    }
}
