<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Internal;

use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;

final class WorkingRegion
{
    /** @var array<string, Rule> */
    public array $rules = [];
    
    /** @var EventSubscriber[] */
    public array $eventSubscribers = [];
    
    /** @var array<string, mixed> */
    public array $metadata = [];

    public function __construct(
        public readonly Region $source,
        public readonly ?string $parentName,
    ) {
        $this->rules = $source->rules;
        $this->eventSubscribers = $source->eventSubscribers;
    }

    public function addRule(Rule $rule): void
    {
        $this->rules[$rule->name] = $rule;
    }

    public function addEventSubscriber(EventSubscriber $subscriber): void
    {
        $this->eventSubscribers[] = $subscriber;
    }

    public function setMeta(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function hasMeta(string $key): bool
    {
        return isset($this->metadata[$key]);
    }
}
