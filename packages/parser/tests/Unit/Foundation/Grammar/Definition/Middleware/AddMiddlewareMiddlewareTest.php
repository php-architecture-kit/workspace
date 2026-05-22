<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Middleware;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddMiddlewareMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AddMiddlewareMiddlewareTest extends TestCase
{
    #[Test]
    public function shouldCreateInstanceViaFromCallable(): void
    {
        $middleware = AddMiddlewareMiddleware::fromCallable(fn(GrammarMiddleware $m) => $m);

        self::assertInstanceOf(AddMiddlewareMiddleware::class, $middleware);
    }

    #[Test]
    public function shouldReturnAddMiddlewareMethodIdentifier(): void
    {
        $middleware = AddMiddlewareMiddleware::fromCallable(fn(GrammarMiddleware $m) => $m);

        self::assertSame(GrammarMiddleware::ADD_MIDDLEWARE, $middleware->method());
    }

    #[Test]
    public function shouldApplyCallbackTransformationOnHandle(): void
    {
        $replacement = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r->priority(99));
        $middleware = AddMiddlewareMiddleware::fromCallable(fn(GrammarMiddleware $m) => $replacement);
        $input = AddRuleMiddleware::fromCallable(fn(Rule $r) => $r);

        $result = $middleware->handle($input);

        self::assertSame($replacement, $result);
    }

    #[Test]
    public function shouldHaveZeroPriorityByDefault(): void
    {
        $middleware = AddMiddlewareMiddleware::fromCallable(fn(GrammarMiddleware $m) => $m);

        self::assertSame(0, $middleware->priority());
    }

    #[Test]
    public function shouldRespectPriorityPassedToFromCallable(): void
    {
        $middleware = AddMiddlewareMiddleware::fromCallable(fn(GrammarMiddleware $m) => $m, 2);

        self::assertSame(2, $middleware->priority());
    }

    #[Test]
    public function shouldReturnNonEmptyHashString(): void
    {
        $middleware = AddMiddlewareMiddleware::fromCallable(fn(GrammarMiddleware $m) => $m);

        self::assertNotEmpty($middleware->hash());
    }
}
