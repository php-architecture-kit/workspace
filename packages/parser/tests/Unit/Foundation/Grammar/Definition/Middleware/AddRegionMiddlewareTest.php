<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Middleware;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddRegionMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AddRegionMiddlewareTest extends TestCase
{
    #[Test]
    public function shouldCreateInstanceViaFromCallable(): void
    {
        $middleware = AddRegionMiddleware::fromCallable(fn(Region $r) => $r);

        self::assertInstanceOf(AddRegionMiddleware::class, $middleware);
    }

    #[Test]
    public function shouldReturnAddRegionMethodIdentifier(): void
    {
        $middleware = AddRegionMiddleware::fromCallable(fn(Region $r) => $r);

        self::assertSame(GrammarMiddleware::ADD_REGION, $middleware->method());
    }

    #[Test]
    public function shouldApplyCallbackTransformationOnHandle(): void
    {
        $middleware = AddRegionMiddleware::fromCallable(fn(Region $r) => $r->addTag('transformed'));
        $region = new Region('test');

        $result = $middleware->handle($region);

        self::assertTrue($result->hasTag('transformed'));
    }

    #[Test]
    public function shouldHaveZeroPriorityByDefault(): void
    {
        $middleware = AddRegionMiddleware::fromCallable(fn(Region $r) => $r);

        self::assertSame(0, $middleware->priority());
    }

    #[Test]
    public function shouldRespectPriorityPassedToFromCallable(): void
    {
        $middleware = AddRegionMiddleware::fromCallable(fn(Region $r) => $r, 3);

        self::assertSame(3, $middleware->priority());
    }

    #[Test]
    public function shouldReturnNonEmptyHashString(): void
    {
        $middleware = AddRegionMiddleware::fromCallable(fn(Region $r) => $r);

        self::assertNotEmpty($middleware->hash());
    }
}
