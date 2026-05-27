<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Config\RegionConfig;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Config\RegionConfigApi;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\Standard\AddInheritedRuleMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\Standard\AddTaggedRuleRegionRefMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\Standard\TriviaSequenceNamingMiddleware;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsTrait;
use RuntimeException;

class Region
{
    use RegionConfigApi;
    use MetaTrait;
    use TagsTrait;

    public const META_ORIGIN = 'grammar.origin';
    public const META_REMOVED_RULES = 'grammar.removed_rules';
    public const META_REMOVED_REGIONS = 'grammar.removed_regions';

    public const NONE = 0;
    public const RULES = 1;
    public const REGIONS = 2;
    public const MIDDLEWARES = 4;
    public const EVENT_SUBSCRIBERS = 8;

    public const MERGE_DEFAULT_SCOPE = self::RULES | self::REGIONS | self::EVENT_SUBSCRIBERS;
    public const MERGE_DEFAULT_MIDDLEWARES = self::RULES | self::REGIONS | self::EVENT_SUBSCRIBERS;
    public const MERGE_DEFAULT_OVERRIDE = true;

    /** @var array<string,Rule> */
    public private(set) array $rules = [];

    /** @var array<string,Region> */
    public private(set) array $regions = [];

    /** @var array<string,array<string,GrammarMiddleware>> */
    public private(set) array $middlewares = [];

    /** @var array<string,EventSubscriber> */
    public private(set) array $eventSubscribers = [];

    public function __construct(
        public readonly string $name,
        RegionConfig $config = new RegionConfig(),
    ) {
        $this->config = $config;

        $this->add(
            new AddInheritedRuleMiddleware($this),
            new AddTaggedRuleRegionRefMiddleware($this),
            new TriviaSequenceNamingMiddleware(),
        );
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

    public function addEventSubscriber(EventSubscriber $eventSubscriber, bool $applyMiddlewares = true): self
    {
        if ($applyMiddlewares) {
            foreach ($this->middlewares[GrammarMiddleware::ADD_EVENT_SUBSCRIBER] ?? [] as $middleware) {
                $eventSubscriber = $middleware->handle($eventSubscriber);
            }
        }

        $this->eventSubscribers[$eventSubscriber->hash()] = $eventSubscriber;

        return $this;
    }

    public function addMiddleware(GrammarMiddleware $newMiddleware, bool $applyMiddlewares = true): self
    {
        if ($applyMiddlewares) {
            foreach ($this->middlewares[GrammarMiddleware::ADD_MIDDLEWARE] ?? [] as $middleware) {
                $newMiddleware = $middleware->handle($newMiddleware);
            }
        }

        $this->middlewares[$newMiddleware->method()][$newMiddleware->hash()] = $newMiddleware;
        uasort(
            $this->middlewares[$newMiddleware->method()],
            static fn(GrammarMiddleware $a, GrammarMiddleware $b) => $a->priority() <=> $b->priority(),
        );

        return $this;
    }

    public function addRegion(Region $region, bool $applyMiddlewares = true): self
    {
        if ($applyMiddlewares) {
            foreach ($this->middlewares[GrammarMiddleware::ADD_REGION] ?? [] as $middleware) {
                $region = $middleware->handle($region);
            }
        }

        $this->regions[$region->name] = $region;

        return $this;
    }

    public function addRule(Rule $rule, bool $applyMiddlewares = true): self
    {
        if ($applyMiddlewares) {
            foreach ($this->middlewares[GrammarMiddleware::ADD_RULE] ?? [] as $middleware) {
                $rule = $middleware->handle($rule);
            }
        }

        $this->rules[$rule->name] = $rule;

        return $this;
    }

    public function removeRule(string $name, ?GrammarOrigin $removedBy = null): self
    {
        unset($this->rules[$name]);
        if ($removedBy !== null) {
            $removed = $this->getMeta(self::META_REMOVED_RULES, []);
            $removed[$name] = $removedBy;
            $this->setMeta(self::META_REMOVED_RULES, $removed);
        }

        return $this;
    }

    public function removeRegion(string $name, ?GrammarOrigin $removedBy = null): self
    {
        unset($this->regions[$name]);
        if ($removedBy !== null) {
            $removed = $this->getMeta(self::META_REMOVED_REGIONS, []);
            $removed[$name] = $removedBy;
            $this->setMeta(self::META_REMOVED_REGIONS, $removed);
        }

        return $this;
    }

    public function merge(
        Region $source,
        int $scope = self::MERGE_DEFAULT_SCOPE,
        int $applyMiddlewares = self::MERGE_DEFAULT_MIDDLEWARES,
        bool $overrideSource = self::MERGE_DEFAULT_OVERRIDE,
    ): self {
        foreach (
            [
                self::RULES => ['rules', 'addRule', false],
                self::REGIONS => ['regions', 'addRegion', false],
                self::EVENT_SUBSCRIBERS => ['eventSubscribers', 'addEventSubscriber', false],
                self::MIDDLEWARES => ['middlewares', 'addMiddleware', true],
            ] as $scopeFlag => $asset
        ) {
            $store = $asset[0];
            $method = $asset[1];
            $nested = $asset[2];

            if (($scope & $scopeFlag) === $scopeFlag) {
                $applyTargetMiddlewares = ($applyMiddlewares & $scopeFlag) === $scopeFlag;
                foreach ($source->$store as $key => $item) {
                    if ($nested) {
                        foreach ($item as $nestedKey => $nestedItem) {
                            if ($overrideSource && isset($this->$store[$key][$nestedKey])) {
                                continue;
                            }

                            $this->$method($nestedItem, $applyTargetMiddlewares);
                        }

                        continue;
                    }

                    if ($overrideSource && isset($this->$store[$key])) {
                        continue;
                    }
                    $this->$method($item, $applyTargetMiddlewares);
                }
            }
        }

        return $this;
    }
}
