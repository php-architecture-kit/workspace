<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

use PhpArchitecture\Parser\Grammar\Definition\Regex\CallbackRule;
use PhpArchitecture\Parser\Grammar\Definition\RuleType;
use PhpArchitecture\Parser\Grammar\EventListener\Tokenization\DynamicTokenInitEventListener;
use PhpArchitecture\Parser\Grammar\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Grammar\EventListener\Tokenization\RetokenizeRegionEventListener;
use PhpArchitecture\Parser\Grammar\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Grammar\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Tokenization\Event\TokenAddedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenMatchedEvent;
use PhpArchitecture\Parser\Tokenization\Event\TokenRegionEndedEvent;
use RuntimeException;

class Region
{
    /** @var array<string,Rule> */
    public private(set) array $rules = [];

    /** @var array<string,Region> */
    public private(set) array $regions = [];

    /** @var array<string,GrammarMiddleware[]> */
    public private(set) array $middlewares = [];

    /** @var EventSubscriber[] */
    public private(set) array $eventSubscribers = [];

    public function __construct(
        public readonly string $name,
        public readonly RegionConfig $config = new RegionConfig(),
    ) {}

    public function openWith(string|Rule $openRule): self
    {
        $this->config->openRule = $openRule;
        return $this;
    }

    public function includeOpenRule(bool $include = true): self
    {
        $this->config->includeOpenRule = $include;
        return $this;
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

    public function includeGlobalRules(bool $include = true): self
    {
        $this->config->includeAncestorRules = $include;
        return $this;
    }

    public function includeGlobalEventSubscribers(bool $include = true): self
    {
        $this->config->includeAncestorEventSubscribers = $include;
        return $this;
    }

    public function withInsideGrammar(Grammar $grammar): self
    {
        $this->config->insideGrammar = $grammar;
        return $this;
    }

    public function confirmMixOfRegionRulesAndInsideGrammarRules(bool $confirm = true): self
    {
        $this->config->confirmMixOfRegionRulesAndInsideGrammarRules = $confirm;
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

    public function compileTopDownRecursively(Grammar $grammar, Region $ancestor): void
    {
        $this->config->assertValid();

        $this->applyIncludeAncestorRules($ancestor);
        $this->applyIncludeAncestorEventSubscribers($ancestor);
        $this->applyIncludeGlobalRules($grammar->global);
        $this->applyIncludeGlobalEventSubscribers($grammar->global);

        $this->applyDynamicTokens($grammar, $ancestor);

        $this->applyOpenRule($ancestor, true);
        $this->applyCloseRule(true);

        foreach ($this->regions as $region) {
            $region->compileRecursively($grammar->global, $this);
        }
    }

    public function compileDownTopRecursively(Region $ancestor): void
    {
        foreach ($this->regions as $region) {
            $region->compileDownTopRecursively($this);
        }

        $this->applyInsideGrammar(false);
        $this->applyOpenRule($ancestor, false);
        $this->applyCloseRule(false);
    }

    /**
     * @return array<string,Region>
     */
    public function getRegionsRecursively(): array
    {
        $regions = $this->regions;
        foreach ($this->regions as $region) {
            $innerRegions = $region->getRegionsRecursively();
            if (array_intersect_key($regions, $innerRegions) !== []) {
                throw new RuntimeException("Grammar region '{$this->name}' has duplicated region names: " . implode(', ', array_keys(array_intersect_key($regions, $innerRegions))));
            }
            $regions = array_merge($regions, $innerRegions);
        }

        return $regions;
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

    private function applyOpenRule(Region $ancestor, bool $isTopDown): void
    {
        $openRule = $this->config->openRule;
        if ($openRule === null) {
            return;
        }

        if (!$isTopDown) {
            if ($openRule instanceof Rule) {
                if ($this->config->includeOpenRule) {
                    $this->addRule($openRule);
                }

                $ancestor->add($openRule);
            }

            return;
        }

        if (is_string($openRule)) {
            if (!isset($ancestor->rules[$openRule])) {
                throw new RuntimeException("Missing rule declaration, required by region `{$this->name}` open rule.");
            }

            $openRule = $ancestor->rules[$openRule];
            $this->config->openRule = $openRule;
        }

        $ancestor->add(
            EventSubscriber::on(
                $this->config->includeOpenRuleMatch ? TokenMatchedEvent::class : TokenAddedEvent::class,
                new StartRegionEventListener($this, $openRule)
            )
        );

        if ($this->config->closeAfterOpenRuleMatch) {
            $this->addEventSubscriber(
                EventSubscriber::on(
                    TokenAddedEvent::class,
                    new EndRegionEventListener($openRule)
                )
            );
        }
    }

    private function applyIncludeAncestorRules(Region $ancestor): void
    {
        if (!$this->config->includeAncestorRules) {
            return;
        }

        foreach ($ancestor->rules as $rule) {
            if (isset($this->rules[$rule->name])) {
                continue;
            }

            $this->rules[$rule->name] = $rule;
        }
    }

    private function applyIncludeAncestorEventSubscribers(Region $ancestor): void
    {
        if (!$this->config->includeAncestorEventSubscribers) {
            return;
        }

        foreach ($ancestor->eventSubscribers as $eventSubscriber) {
            $this->eventSubscribers[] = $eventSubscriber;
        }
    }

    private function applyIncludeGlobalRules(Region $global): void
    {
        if (!$this->config->includeGlobalRules) {
            return;
        }

        foreach ($global->rules as $rule) {
            if (isset($this->rules[$rule->name])) {
                continue;
            }

            $this->rules[$rule->name] = $rule;
        }
    }

    private function applyIncludeGlobalEventSubscribers(Region $global): void
    {
        if (!$this->config->includeGlobalEventSubscribers) {
            return;
        }

        foreach ($global->eventSubscribers as $eventSubscriber) {
            $this->eventSubscribers[] = $eventSubscriber;
        }
    }

    private function applyInsideGrammar(bool $isTopDown): void
    {
        if ($this->config->insideGrammar === null) {
            return;
        }

        if ($isTopDown) {
            return;
        }

        $grammar = $this->config->insideGrammar;
        $grammar->compile();

        if ($this->config->retokenizeWithInsideGrammar) {
            $this->addEventSubscriber(
                EventSubscriber::on(
                    TokenRegionEndedEvent::class,
                    new RetokenizeRegionEventListener($this)
                )
            );
        } else {
            if ((!empty($this->rules) || !empty($this->regions)) && !$this->config->confirmMixOfRegionRulesAndInsideGrammarRules) {
                throw new RuntimeException('Your grammar configuration is going to mix inside grammar with the new rules. You have to confirm that you want to do this by setting confirmMixOfRegionRulesAndInsideGrammarRules to true in the region config.');
            }

            $this->rules = array_merge(
                $grammar->global->rules,
                $this->rules
            );

            $this->regions = array_merge(
                $grammar->global->regions,
                $this->regions
            );

            $this->eventSubscribers = array_merge(
                $grammar->global->eventSubscribers,
                $this->eventSubscribers
            );
        }
    }

    private function applyCloseRule(bool $isTopDown): void
    {
        $closeRule = $this->config->closeRule;
        if ($closeRule === null) {
            return;
        }

        if (!$isTopDown) {
            if ($closeRule instanceof Rule) {
                $this->addRule($closeRule);
            }

            return;
        }

        if (is_string($closeRule)) {
            if (!isset($this->rules[$closeRule])) {
                throw new RuntimeException("Missing rule declaration, required by region `{$this->name}` close rule.");
            }

            $closeRule = $this->rules[$closeRule];
            $this->config->closeRule = $closeRule;
        }

        $this->add(
            EventSubscriber::on(
                $this->config->includeCloseRuleMatch ? TokenAddedEvent::class : TokenMatchedEvent::class,
                new EndRegionEventListener($closeRule, $this->config->closeWhenCloseRuleNotMatch)
            )
        );
    }

    private function applyDynamicTokens(Grammar $grammar, Region $ancestor): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($this->rules as $ruleName => $rule) {
            if ($rule->type !== RuleType::DynamicToken) {
                continue;
            }

            /** @var CallbackRule $callbackDefinition */
            $callbackDefinition = $rule->definition;
            foreach ($callbackDefinition->listenInRegions as $regionName) {
                $targetRegion = match ($regionName) {
                    CallbackRule::GLOBAL_REGION => [$grammar->global],
                    CallbackRule::PARENT_REGION => [$ancestor],
                    CallbackRule::ROOT_REGION => [$grammar->rootRegion],
                    CallbackRule::SAME_REGION => [$this],
                    CallbackRule::LISTEN_IN_ALL_REGIONS => $allRegions,
                    default => isset($allRegions[$regionName]) ? [$allRegions[$regionName]] : [],
                };

                foreach ($targetRegion as $region) {
                    if ($region === null) {
                        throw new RuntimeException("Region `{$regionName}` not found for rule `{$ruleName}`");
                    }

                    $region->add(
                        EventSubscriber::on(
                            TokenAddedEvent::class,
                            new DynamicTokenInitEventListener($callbackDefinition->triggerRule, $rule, $region)
                        )
                    );
                }
            }
        }
    }
}
