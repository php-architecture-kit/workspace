<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Grammar\Definition\Model\Technical;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PhpArchitecture\Parser\Grammar\Definition\Model\Technical\TaggedRule;

#[Group('unit')]
final class TaggedRuleTest extends TestCase
{
    #[Test]
    public function shouldSetTagThroughConstructor(): void
    {
        $tag = 'testTag';
        $rule = new TaggedRule($tag);

        self::assertSame($tag, $rule->tag);
    }
}
