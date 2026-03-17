<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

use PhpArchitecture\Parser\Grammar\Middleware\GrammarMiddleware;

class Region
{
    public const NAME_GLOBAL = 'global';

    /** @var array<string,Rule> */
    private array $rules = [];

    /** @var array<string,Region> */
    private array $regions = [];

    /** @var array<string,GrammarMiddleware[]> */
    private array $middlewares = [];

    /** @var EventSubscriber[] */
    private array $eventSubscribers = [];

    public function __construct(
        public readonly string $name,
        // public readonly RegionConfig $config,
    ) {}

    public function add(EventSubscriber|GrammarMiddleware|Region|Rule ...$items): self
    {
        foreach ($items as $item) {
            if ($item instanceof GrammarMiddleware) {
                $this->addMiddleware($item);
            } elseif ($item instanceof Region) {
                $this->addRegion($item);
            } elseif ($item instanceof Rule) {
                $this->addRule($item);
            } elseif ($item instanceof EventSubscriber) {
                $this->addEventSubscriber($item);
            }
        }

        return $this;
    }

    private function addEventSubscriber(EventSubscriber $eventSubscriber): void
    {
        foreach ($this->middlewares[GrammarMiddleware::ADD_EVENT_SUBSCRIBER] ?? [] as $middleware) {
            $eventSubscriber = $middleware->handle($eventSubscriber);
        }

        $this->eventSubscribers[] = $eventSubscriber;
    }

    private function addMiddleware(GrammarMiddleware $newMiddleware): void
    {
        foreach ($this->middlewares[GrammarMiddleware::ADD_MIDDLEWARE] ?? [] as $middleware) {
            $newMiddleware = $middleware->handle($newMiddleware);
        }

        $this->middlewares[$newMiddleware->method()][] = $newMiddleware;
        usort(
            $this->middlewares[$newMiddleware->method()],
            static fn(GrammarMiddleware $a, GrammarMiddleware $b) => $a->priority() <=> $b->priority()
        );
    }

    private function addRegion(Region $region): void
    {
        foreach ($this->middlewares[GrammarMiddleware::ADD_REGION] ?? [] as $middleware) {
            $region = $middleware->handle($region);
        }

        $this->regions[$region->name] = $region;
    }

    private function addRule(Rule $rule): void
    {
        foreach ($this->middlewares[GrammarMiddleware::ADD_RULE] ?? [] as $middleware) {
            $rule = $middleware->handle($rule);
        }

        $this->rules[$rule->name] = $rule;
    }
}
