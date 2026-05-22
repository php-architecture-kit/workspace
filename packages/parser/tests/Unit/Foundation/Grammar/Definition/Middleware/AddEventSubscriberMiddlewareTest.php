<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Grammar\Definition\Middleware;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddEventSubscriberMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AddEventSubscriberMiddlewareTest extends TestCase
{
    #[Test]
    public function shouldCreateInstanceViaFromCallable(): void
    {
        $middleware = AddEventSubscriberMiddleware::fromCallable(fn(EventSubscriber $s) => $s);

        self::assertInstanceOf(AddEventSubscriberMiddleware::class, $middleware);
    }

    #[Test]
    public function shouldReturnAddEventSubscriberMethodIdentifier(): void
    {
        $middleware = AddEventSubscriberMiddleware::fromCallable(fn(EventSubscriber $s) => $s);

        self::assertSame(GrammarMiddleware::ADD_EVENT_SUBSCRIBER, $middleware->method());
    }

    #[Test]
    public function shouldApplyCallbackTransformationOnHandle(): void
    {
        $middleware = AddEventSubscriberMiddleware::fromCallable(
            fn(EventSubscriber $s) => $s->onlyForRuleName('myRule'),
        );
        $subscriber = EventSubscriber::on(TokenAddedEvent::class, function () {});

        $result = $middleware->handle($subscriber);

        self::assertSame('myRule', $result->onlyForRuleName);
    }

    #[Test]
    public function shouldHaveZeroPriorityByDefault(): void
    {
        $middleware = AddEventSubscriberMiddleware::fromCallable(fn(EventSubscriber $s) => $s);

        self::assertSame(0, $middleware->priority());
    }

    #[Test]
    public function shouldRespectPriorityPassedToFromCallable(): void
    {
        $middleware = AddEventSubscriberMiddleware::fromCallable(fn(EventSubscriber $s) => $s, 7);

        self::assertSame(7, $middleware->priority());
    }

    #[Test]
    public function shouldReturnNonEmptyHashString(): void
    {
        $middleware = AddEventSubscriberMiddleware::fromCallable(fn(EventSubscriber $s) => $s);

        self::assertNotEmpty($middleware->hash());
    }
}
