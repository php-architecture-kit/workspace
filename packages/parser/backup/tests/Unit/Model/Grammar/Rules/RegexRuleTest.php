<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Parser\Unit\Model\Grammar\Rules;

use PhpArchitecture\Parser\Model\Grammar\Rules\RegexRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RegexRuleTest extends TestCase
{
    #[Test]
    public function constructorSetsRegexProperty(): void
    {
        $regex = '~\G[a-z]+~u';
        $rule = new RegexRule($regex);

        $this->assertSame($regex, $rule->regex);
    }

    #[Test]
    public function fromStringCreatesRuleWithCaseSensitiveFlag(): void
    {
        $pattern = '[a-zA-Z]+';
        $rule = RegexRule::fromString($pattern);

        $this->assertSame('~\G[a-zA-Z]+~u', $rule->regex);
    }

    #[Test]
    public function fromStringCreatesRuleWithCaseInsensitiveFlag(): void
    {
        $pattern = '[a-z]+';
        $rule = RegexRule::fromString($pattern, caseSensitive: false);

        $this->assertSame('~\G[a-z]+~ui', $rule->regex);
    }

    #[Test]
    public function fromStringDefaultsToCaseSensitive(): void
    {
        $pattern = 'test';
        $rule = RegexRule::fromString($pattern);

        $this->assertStringEndsWith('~u', $rule->regex);
        $this->assertStringNotContainsString('~ui', $rule->regex);
    }

    #[Test]
    public function fromStringAddsAnchorAtBeginning(): void
    {
        $pattern = 'pattern';
        $rule = RegexRule::fromString($pattern);

        $this->assertStringStartsWith('~\G', $rule->regex);
    }

    #[Test]
    public function fromStringWrapsPatternInDelimiters(): void
    {
        $pattern = 'test';
        $rule = RegexRule::fromString($pattern);

        $this->assertStringStartsWith('~', $rule->regex);
        $this->assertStringEndsWith('~u', $rule->regex);
    }

    #[Test]
    public function fromStringHandlesComplexPattern(): void
    {
        $pattern = '(?:[0-9]+|[a-z]+)';
        $rule = RegexRule::fromString($pattern);

        $this->assertSame('~\G(?:[0-9]+|[a-z]+)~u', $rule->regex);
    }

    #[Test]
    public function fromStringHandlesEmptyPattern(): void
    {
        $pattern = '';
        $rule = RegexRule::fromString($pattern);

        $this->assertSame('~\G~u', $rule->regex);
    }

    #[Test]
    public function fromStringWithSpecialCharacters(): void
    {
        $pattern = '\d+\.\d+';
        $rule = RegexRule::fromString($pattern);

        $this->assertSame('~\G\d+\.\d+~u', $rule->regex);
    }

    #[Test]
    public function caseSensitiveFlagAffectsRegex(): void
    {
        $pattern = 'ABC';
        
        $caseSensitive = RegexRule::fromString($pattern, caseSensitive: true);
        $caseInsensitive = RegexRule::fromString($pattern, caseSensitive: false);

        $this->assertSame('~\GABC~u', $caseSensitive->regex);
        $this->assertSame('~\GABC~ui', $caseInsensitive->regex);
    }

    #[Test]
    public function multipleInstancesAreIndependent(): void
    {
        $rule1 = RegexRule::fromString('pattern1');
        $rule2 = RegexRule::fromString('pattern2');

        $this->assertNotSame($rule1->regex, $rule2->regex);
        $this->assertSame('~\Gpattern1~u', $rule1->regex);
        $this->assertSame('~\Gpattern2~u', $rule2->regex);
    }
}
