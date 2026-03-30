<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Definition;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GrammarTest extends TestCase
{
    #[Test]
    public function shouldCreateGrammarWithNameAndVariant(): void
    {
        $grammar = new Grammar('json', 'rfc8259');

        $this->assertSame('json', $grammar->name);
        $this->assertSame('rfc8259', $grammar->variant);
        $this->assertInstanceOf(Region::class, $grammar->global);
        $this->assertSame('global', $grammar->global->name);
    }

    #[Test]
    public function shouldAddRulesToGlobalRegion(): void
    {
        $grammar = new Grammar('test', 'v1');
        $grammar->global->add(
            Rule::token('space', ' '),
            Rule::token('word', '[a-z]+')
        );

        $this->assertCount(2, $grammar->global->rules);
        $this->assertSame('space', $grammar->global->rules[0]->name);
        $this->assertSame('word', $grammar->global->rules[1]->name);
    }

    #[Test]
    public function shouldAddNestedRegionsToGlobalRegion(): void
    {
        $grammar = new Grammar('test', 'v1');
        
        $stringRegion = new Region('string');
        $stringRegion->add(Rule::token('char', '[a-z]'));
        
        $grammar->global->add($stringRegion);

        $this->assertCount(1, $grammar->global->regions);
        $this->assertArrayHasKey('string', $grammar->global->regions);
        $this->assertSame($stringRegion, $grammar->global->regions['string']);
    }

    #[Test]
    public function shouldGetAllRegionsRecursively(): void
    {
        $grammar = new Grammar('test', 'v1');
        
        $level1 = new Region('level1');
        $level2 = new Region('level2');
        
        $level1->add($level2);
        $grammar->global->add($level1);

        $allRegions = $grammar->getAllRegions();

        $this->assertCount(3, $allRegions);
        $this->assertArrayHasKey('global', $allRegions);
        $this->assertArrayHasKey('level1', $allRegions);
        $this->assertArrayHasKey('level2', $allRegions);
    }

    #[Test]
    public function shouldSetAndGetRootRegion(): void
    {
        $grammar = new Grammar('test', 'v1');
        
        $customRoot = new Region('custom_root');
        $grammar->global->add($customRoot);
        $grammar->setRootRegion($customRoot);

        $this->assertSame($customRoot, $grammar->rootRegion);
    }

    #[Test]
    public function shouldMergeRegionsWithRulesAndEventSubscribers(): void
    {
        $grammar = new Grammar('test', 'v1');
        
        $source = new Region('source');
        $source->add(
            Rule::token('token1', 'x'),
            Rule::token('token2', 'y')
        );
        
        $target = new Region('target');
        $target->add(Rule::token('token3', 'z'));
        
        $target->merge($source, Region::RULES);

        $this->assertCount(3, $target->rules);
    }

    #[Test]
    public function shouldPreserveRegionMetadataAndTags(): void
    {
        $grammar = new Grammar('test', 'v1');
        
        $grammar->global->setMeta('custom_key', 'custom_value');
        $grammar->global->addTag('tag1', 'tag2');

        $this->assertSame('custom_value', $grammar->global->getMeta('custom_key'));
        $this->assertTrue($grammar->global->hasTag('tag1'));
        $this->assertTrue($grammar->global->hasTag('tag2'));
    }
}
