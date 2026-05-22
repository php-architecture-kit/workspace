<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GrammarTest extends TestCase
{
    #[Test]
    public function shouldSetNameThroughConstructor(): void
    {
        $grammar = new Grammar('myGrammar');

        self::assertSame('myGrammar', $grammar->name);
    }

    #[Test]
    public function shouldSetVariantThroughConstructor(): void
    {
        $grammar = new Grammar('myGrammar', 'v1');

        self::assertSame('v1', $grammar->variant);
    }

    #[Test]
    public function shouldHaveNullVariantByDefault(): void
    {
        $grammar = new Grammar('myGrammar');

        self::assertNull($grammar->variant);
    }

    #[Test]
    public function shouldRequireBofEofByDefault(): void
    {
        $grammar = new Grammar('myGrammar');

        self::assertTrue($grammar->requireBofEof);
    }

    #[Test]
    public function shouldAllowDisablingBofEofRequirement(): void
    {
        $grammar = new Grammar('myGrammar');
        $grammar->requireBofEof = false;

        self::assertFalse($grammar->requireBofEof);
    }

    #[Test]
    public function shouldCreateGlobalRegionThroughConstructor(): void
    {
        $grammar = new Grammar('myGrammar');

        self::assertInstanceOf(Region::class, $grammar->global);
    }

    #[Test]
    public function shouldSetRootRegionToGlobalByDefault(): void
    {
        $grammar = new Grammar('myGrammar');

        self::assertSame($grammar->global, $grammar->rootRegion);
    }

    #[Test]
    public function shouldReturnGlobalRegionInGetAllRegions(): void
    {
        $grammar = new Grammar('myGrammar');

        self::assertArrayHasKey('global', $grammar->getAllRegions());
    }

    #[Test]
    public function shouldReturnNestedRegionsInGetAllRegions(): void
    {
        $grammar = new Grammar('myGrammar');
        $nested = new Region('nested');
        $grammar->global->add($nested);

        $all = $grammar->getAllRegions();

        self::assertArrayHasKey('nested', $all);
    }

    #[Test]
    public function shouldChangeRootRegionViaSetRootRegion(): void
    {
        $grammar = new Grammar('myGrammar');
        $region = new Region('sub');
        $grammar->global->add($region);

        $grammar->setRootRegion($region);

        self::assertSame($region, $grammar->rootRegion);
    }

    #[Test]
    public function shouldThrowWhenSetRootRegionReceivesRegionNotInGrammar(): void
    {
        $grammar = new Grammar('myGrammar');
        $foreign = new Region('foreign');

        $this->expectException(InvalidArgumentException::class);

        $grammar->setRootRegion($foreign);
    }

    #[Test]
    public function shouldThrowWhenSetRootRegionReceivesDifferentInstanceWithSameName(): void
    {
        $grammar = new Grammar('myGrammar');
        $grammar->global->add(new Region('sub'));
        $differentInstance = new Region('sub');

        $this->expectException(InvalidArgumentException::class);

        $grammar->setRootRegion($differentInstance);
    }
}
