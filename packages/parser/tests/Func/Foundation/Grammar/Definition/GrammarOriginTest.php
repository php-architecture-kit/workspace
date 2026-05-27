<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Foundation\Grammar\Definition;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\Json5;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonC;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;
use PhpArchitecture\Parser\Tests\Func\Grammar\GrammarTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('func')]
final class GrammarOriginTest extends GrammarTestCase
{
    #[Test]
    public function shouldStampWhitespaceRulesWithTechnicalOrigin(): void
    {
        $grammar = (new Whitespace())->grammar();

        $this->assertGrammarParsing(
            string: ' ',
            grammar: $grammar,
            assertDefinedGrammarValid: function (Grammar $grammar, self $test): void {
                $origin = $grammar->global->rules['space']->getMeta(Rule::META_ORIGIN);

                $test->assertInstanceOf(GrammarOrigin::class, $origin);
                $test->assertSame('technical', $origin->format);
                $test->assertSame('whitespace', $origin->variant);
            },
            assertCompiledGrammarValid: function (CompiledGrammar $compiled, self $test): void {
                $origin = $compiled->regions['global']->getMeta(Region::META_ORIGIN);

                $test->assertInstanceOf(GrammarOrigin::class, $origin);
                $test->assertSame('technical', $origin->format);
                $test->assertSame('whitespace', $origin->variant);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldStampJsonRfc8259RulesAndPreserveWhitespaceOrigin(): void
    {
        $grammar = (new JsonRfc8259())->grammar();

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertDefinedGrammarValid: function (Grammar $grammar, self $test): void {
                $arrayRegion = $grammar->getAllRegions()['array'];

                $regionOrigin = $arrayRegion->getMeta(Region::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $regionOrigin);
                $test->assertSame('json', $regionOrigin->format);
                $test->assertSame('rfc8259', $regionOrigin->variant);

                $commaRuleOrigin = $arrayRegion->rules['comma']->getMeta(Rule::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $commaRuleOrigin);
                $test->assertSame('json', $commaRuleOrigin->format);
                $test->assertSame('rfc8259', $commaRuleOrigin->variant);

                $spaceOrigin = $grammar->global->rules['space']->getMeta(Rule::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $spaceOrigin);
                $test->assertSame('technical', $spaceOrigin->format);
                $test->assertSame('whitespace', $spaceOrigin->variant);
            },
            assertCompiledGrammarValid: function (CompiledGrammar $compiled, self $test): void {
                $origin = $compiled->regions['array']->getMeta(Region::META_ORIGIN);

                $test->assertInstanceOf(GrammarOrigin::class, $origin);
                $test->assertSame('rfc8259', $origin->variant);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldStampJsonCCommentRegionsWithCOriginAndPreserveRfc8259Origin(): void
    {
        $grammar = (new JsonC())->grammar();

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertDefinedGrammarValid: function (Grammar $grammar, self $test): void {
                $regions = $grammar->getAllRegions();

                $lineCommentOrigin = $regions['lineComment']->getMeta(Region::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $lineCommentOrigin);
                $test->assertSame('json', $lineCommentOrigin->format);
                $test->assertSame('c', $lineCommentOrigin->variant);

                $blockCommentOrigin = $regions['blockComment']->getMeta(Region::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $blockCommentOrigin);
                $test->assertSame('c', $blockCommentOrigin->variant);

                $arrayOrigin = $regions['array']->getMeta(Region::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $arrayOrigin);
                $test->assertSame('rfc8259', $arrayOrigin->variant);
            },
            assertCompiledGrammarValid: function (CompiledGrammar $compiled, self $test): void {
                $test->assertSame('c', $compiled->regions['lineComment']->getMeta(Region::META_ORIGIN)?->variant);
                $test->assertSame('rfc8259', $compiled->regions['array']->getMeta(Region::META_ORIGIN)?->variant);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldStampJson5OverriddenRuleWithJson5OriginAndPreserveParentOrigins(): void
    {
        $grammar = (new Json5())->grammar();

        $this->assertGrammarParsing(
            string: 'null',
            grammar: $grammar,
            assertDefinedGrammarValid: function (Grammar $grammar, self $test): void {
                $regions = $grammar->getAllRegions();

                // replaced rule → json/5
                $primitiveOrigin = $grammar->global->rules['primitive']->getMeta(Rule::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $primitiveOrigin);
                $test->assertSame('5', $primitiveOrigin->variant);

                // new region → json/5
                $singleQuotedOrigin = $regions['singleQuotedString']->getMeta(Region::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $singleQuotedOrigin);
                $test->assertSame('5', $singleQuotedOrigin->variant);

                // untouched region → rfc8259
                $lineCommentOrigin = $regions['lineComment']->getMeta(Region::META_ORIGIN);
                $test->assertInstanceOf(GrammarOrigin::class, $lineCommentOrigin);
                $test->assertSame('c', $lineCommentOrigin->variant);

                // modified regions → json/5 (forceRegions)
                $test->assertSame('5', $regions['number']->getMeta(Region::META_ORIGIN)?->variant);
                $test->assertSame('5', $regions['object']->getMeta(Region::META_ORIGIN)?->variant);
                $test->assertSame('5', $regions['array']->getMeta(Region::META_ORIGIN)?->variant);

                // pre-existing rules inside modified regions keep parent origin
                $digitOrigin = $regions['number']->rules['digit']->getMeta(Rule::META_ORIGIN);
                $test->assertSame('rfc8259', $digitOrigin?->variant);

                // new rules added to modified regions → json/5
                $fracOrigin = $regions['number']->rules['frac']->getMeta(Rule::META_ORIGIN);
                $test->assertSame('5', $fracOrigin?->variant);
            },
            assertCompiledGrammarValid: function (CompiledGrammar $compiled, self $test): void {
                $test->assertSame('5', $compiled->regions['singleQuotedString']->getMeta(Region::META_ORIGIN)?->variant);
                $test->assertSame('5', $compiled->regions['number']->getMeta(Region::META_ORIGIN)?->variant);
                $test->assertSame('5', $compiled->regions['array']->getMeta(Region::META_ORIGIN)?->variant);
                $test->assertSame('c', $compiled->regions['lineComment']->getMeta(Region::META_ORIGIN)?->variant);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldTrackRemovedRuleWithOriginInDefinitionAndCompiledGrammar(): void
    {
        $grammar = new Grammar('test');
        $grammar->global->add(
            Rule::token('comma', ','),
            Rule::token('dot', '.'),
        );
        $grammar->global->withRootSequence('dot');

        $grammar->global->removeRule('comma', new GrammarOrigin('json', '5'));

        $removedRules = $grammar->global->getMeta(Region::META_REMOVED_RULES);
        $this->assertArrayNotHasKey('comma', $grammar->global->rules);
        $this->assertArrayHasKey('comma', $removedRules);
        $this->assertSame('json', $removedRules['comma']->format);
        $this->assertSame('5', $removedRules['comma']->variant);

        $this->assertGrammarParsing(
            string: '.',
            grammar: $grammar,
            assertCompiledGrammarValid: function (CompiledGrammar $compiled, self $test): void {
                $removedRules = $compiled->regions['global']->getMeta(Region::META_REMOVED_RULES);

                $test->assertArrayHasKey('comma', $removedRules);
                $test->assertSame('json', $removedRules['comma']->format);
                $test->assertSame('5', $removedRules['comma']->variant);
            },
            requireBofEof: false,
        );
    }

    #[Test]
    public function shouldTrackRemovedRegionWithOriginInDefinitionAndCompiledGrammar(): void
    {
        $grammar = new Grammar('test');
        $grammar->global->add(
            Rule::token('dot', '.'),
            Rule::token('open', '[')
                ->startRegion('inner')
                ->add(Rule::token('close', ']')->closeRegion()),
        );
        $grammar->global->withRootSequence('dot');

        $grammar->global->removeRegion('inner', new GrammarOrigin('json', 'c'));

        $removedRegions = $grammar->global->getMeta(Region::META_REMOVED_REGIONS);
        $this->assertArrayNotHasKey('inner', $grammar->global->regions);
        $this->assertArrayHasKey('inner', $removedRegions);
        $this->assertSame('c', $removedRegions['inner']->variant);

        $this->assertGrammarParsing(
            string: '.',
            grammar: $grammar,
            assertCompiledGrammarValid: function (CompiledGrammar $compiled, self $test): void {
                $removedRegions = $compiled->regions['global']->getMeta(Region::META_REMOVED_REGIONS);

                $test->assertArrayHasKey('inner', $removedRegions);
                $test->assertSame('c', $removedRegions['inner']->variant);
            },
            requireBofEof: false,
        );
    }
}
