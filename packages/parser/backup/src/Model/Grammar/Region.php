<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar;

use PhpArchitecture\Parser\Event\EventDispatcher;
use PhpArchitecture\Parser\Grammar;
use PhpArchitecture\Parser\Model\Grammar\Event\RegionAddedEvent;
use PhpArchitecture\Parser\Model\Grammar\Event\RuleAddedEvent;

class Region
{
    /** @var EventSubscriber[] */
    public array $eventSubscribers = [];

    /** @var array<string,Rule> */
    public array $rules = [];

    /** @var array<string,Region> */
    public array $regions = [];

    private EventDispatcher $eventDispatcher;

    public function __construct(
        public readonly string $name,
        public readonly RegionConfig $config,
    ) {
        $this->eventDispatcher = new EventDispatcher([
            RegionAddedEvent::class,
            RuleAddedEvent::class,
        ]);
    }

    public function add(EventSubscriber|Region|Rule ...$items): self
    {
        foreach ($items as $item) {
            if ($item instanceof Rule) {
                $this->addRule($item);
            } elseif ($item instanceof Region) {
                $this->addRegion($item);
            } elseif ($item instanceof EventSubscriber) {
                $this->addEventSubscriber($item);
            }
        }

        return $this;
    }

    /**
     * Register compile-time event subscriber for grammar events.
     * @param EventSubscriber\EventSubscriber $eventSubscriber Must subscribe to GrammarEventInterface events only
     */
    public function on(EventSubscriber\EventSubscriber $eventSubscriber): self
    {
        $eventName = $eventSubscriber->eventName();
        
        if (!is_subclass_of($eventName, Event\GrammarEventInterface::class)) {
            throw new \InvalidArgumentException(
                "Event subscriber must subscribe to GrammarEventInterface events. Got: {$eventName}"
            );
        }
        
        $this->eventDispatcher->registerEventSubscriber($eventSubscriber);
        return $this;
    }

    private function addRule(Rule $rule): void
    {
        $this->rules[$rule->name] = $rule;
        $this->eventDispatcher->dispatch(new RuleAddedEvent($rule));

        foreach ($rule->getMeta(Rule::META_ADDED_RULES) ?? [] as $addedRule) {
            $this->rules[$addedRule->name] = $addedRule;
            $this->eventDispatcher->dispatch(new RuleAddedEvent($addedRule));
        }
    }

    private function addRegion(Region $region): void
    {
        $this->regions[$region->name] = $region;
        $this->eventDispatcher->dispatch(new RegionAddedEvent($region));

        $addedRule = $region->config->openRule;
        if ($addedRule !== null) {
            $this->rules[$addedRule->name] = $addedRule;
            $this->eventDispatcher->dispatch(new RuleAddedEvent($addedRule));
        }
    }

    private function addEventSubscriber(EventSubscriber $eventSubscriber): void
    {
        $this->eventSubscribers[] = $eventSubscriber;
    }

    public function includeOpenRuleMatch(bool $include = true): self
    {
        $this->config->includeOpenRuleMatch = $include;
        return $this;
    }

    public function includeAncestorRules(bool $include = true): self
    {
        $this->config->includeAncestorRules = $include;
        return $this;
    }

    public function includeAncestorEventSubscribers(bool $include = true): self
    {
        $this->config->includeAncestorEventSubscribers = $include;
        return $this;
    }

    public function insideGrammar(Grammar $grammar): self
    {
        $this->config->insideGrammar = $grammar;
        return $this;
    }

    public function retokenizeWithInsideGrammar(bool $retokenize = true): self
    {
        $this->config->retokenizeWithInsideGrammar = $retokenize;
        return $this;
    }

    public function closeWith(string|Rule $closeRule): self
    {
        $this->config->closeRule = $closeRule;
        return $this;
    }

    public function includeCloseRuleMatch(bool $include = true): self
    {
        $this->config->includeCloseRuleMatch = $include;
        return $this;
    }

    public function closeAfterOpenRuleMatch(bool $close = true): self
    {
        $this->config->closeAfterOpenRuleMatch = $close;
        return $this;
    }

    public function closeWhenCloseRuleNotMatch(bool $close = true): self
    {
        $this->config->closeWhenCloseRuleNotMatch = $close;
        return $this;
    }
}
