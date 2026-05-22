<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Middleware;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AddRuleMiddlewareTest extends TestCase
{
    #[Test]
    public function shouldCreateInstanceViaFromCallable(): void
    {
        $middleware = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r);

        self::assertInstanceOf(AddRuleMiddleware::class, $middleware);
    }

    #[Test]
    public function shouldReturnAddRuleMethodIdentifier(): void
    {
        $middleware = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r);

        self::assertSame(GrammarMiddleware::ADD_RULE, $middleware->method());
    }

    #[Test]
    public function shouldApplyCallbackTransformationOnHandle(): void
    {
        $middleware = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r->priority(42));
        $rule = Rule::token('test', 'x');

        $result = $middleware->handle($rule);

        self::assertSame(42, $result->priority);
    }

    #[Test]
    public function shouldHaveZeroPriorityByDefault(): void
    {
        $middleware = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r);

        self::assertSame(0, $middleware->priority());
    }

    #[Test]
    public function shouldRespectPriorityPassedToFromCallable(): void
    {
        $middleware = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r, 5);

        self::assertSame(5, $middleware->priority());
    }

    #[Test]
    public function shouldReturnNonEmptyHashString(): void
    {
        $middleware = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r);

        self::assertNotEmpty($middleware->hash());
    }
}
