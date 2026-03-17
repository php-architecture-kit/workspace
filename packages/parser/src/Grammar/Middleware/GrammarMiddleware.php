<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Middleware;

interface GrammarMiddleware
{
    public const ADD_RULE = 'addRule';
    public const ADD_REGION = 'addRegion';
    public const ADD_MIDDLEWARE = 'addMiddleware';
    public const ADD_EVENT_SUBSCRIBER = 'addEventSubscriber';

    public function handle(object $object): object;
    public function method(): string;
    public function priority(): int;
}
