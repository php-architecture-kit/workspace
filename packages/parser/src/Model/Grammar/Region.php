<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar;

class Region
{
    /** @var EventSubscriber[] */
    public array $eventSubscribers = [];

    /** @var array<string,Rule> */
    public array $rules = [];

    public function __construct(
        public readonly string $name,
        public readonly RegionConfig $config,
    ) {}

    public function add(Rule|EventSubscriber ...$items): self
    {
        foreach ($items as $item) {
            if ($item instanceof Rule) {
                $this->rules[$item->name] = $item;
            } elseif ($item instanceof EventSubscriber) {
                $this->eventSubscribers[] = $item;
            }
        }

        return $this;
    }
}
