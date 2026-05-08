<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Grammar;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\RegexRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Technical\TaggedRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Technical\TechnicalTokenRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\EventSubscriberViewData;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\GrammarViewData;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\MiddlewareViewData;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\RegionViewData;
use PhpArchitecture\Parser\Presentation\View\Grammar\DTO\RuleViewData;
use Closure;

final class GrammarViewFactory
{
    public function fromGrammar(Grammar $grammar): GrammarViewData
    {
        return new GrammarViewData(
            name: $grammar->name,
            variant: $grammar->variant,
            rootRegionName: $grammar->rootRegion->name,
            requireBofEof: $grammar->requireBofEof,
            totalRegions: count($grammar->getAllRegions()),
            globalRegion: $this->fromRegion($grammar->global),
        );
    }

    public function fromRegion(Region $region): RegionViewData
    {
        $config = $region->config;

        $openerRule = null;
        $openerEvent = null;
        if ($config->opener !== null) {
            $openerEvent = $this->shortClass($config->opener->eventClassName);
            $listener = $config->opener->listener;
            if (is_object($listener) && property_exists($listener, 'rule')) {
                $rule = $listener->rule;
                $openerRule = is_object($rule) && property_exists($rule, 'name') ? $rule->name : 'unknown';
            } else {
                $openerRule = 'unknown';
            }
        }

        $closerRule = null;
        $closerEvent = null;
        if ($config->closer !== null) {
            $closerEvent = $this->shortClass($config->closer->eventClassName);
            $listener = $config->closer->listener;
            if (is_object($listener) && property_exists($listener, 'rule')) {
                $rule = $listener->rule;
                $closerRule = is_object($rule) && property_exists($rule, 'name') ? $rule->name : 'unknown';
            } else {
                $closerRule = 'unknown';
            }
        }

        $innerGrammar = null;
        $innerGrammarRetokenize = false;
        if ($config->innerGrammar !== null) {
            $innerGrammar = $config->innerGrammar->name;
            $innerGrammarRetokenize = $config->retokenizeWithInnerGrammar === true;
        }

        $inheritanceParts = [];
        if ($config->inheritanceFromGlobal !== Region::NONE) {
            $inheritanceParts[] = 'Global: ' . $this->formatScope($config->inheritanceFromGlobal);
        }
        if ($config->inheritanceFromAncestor !== Region::NONE) {
            $inheritanceParts[] = 'Ancestor: ' . $this->formatScope($config->inheritanceFromAncestor);
        }

        $middlewares = [];
        foreach ($region->middlewares as $method => $list) {
            foreach ($list as $middleware) {
                $middlewares[] = new MiddlewareViewData(
                    hookName: $method,
                    shortClassName: $this->shortClass(get_class($middleware)),
                    priority: $middleware->priority(),
                );
            }
        }

        return new RegionViewData(
            name: $region->name,
            nodeType: $region->config->nodeType->name,
            rootSequence: $config->rootSequence?->toString(),
            opener: $openerRule,
            openerEvent: $openerEvent,
            closer: $closerRule,
            closerEvent: $closerEvent,
            innerGrammar: $innerGrammar,
            innerGrammarRetokenize: $innerGrammarRetokenize,
            inheritanceInfo: implode(', ', $inheritanceParts),
            tags: $region->getAllTags(),
            rules: array_values(array_map($this->fromRule(...), $region->rules)),
            middlewares: $middlewares,
            eventSubscribers: array_values(array_map($this->fromEventSubscriber(...), $region->eventSubscribers)),
            nestedRegions: array_values(array_map($this->fromRegion(...), $region->regions)),
        );
    }

    private function fromRule(Rule $rule): RuleViewData
    {
        $sequence = match (true) {
            $rule->definition instanceof SequenceRule    => $rule->definition->toString(),
            $rule->definition instanceof RegexRule       => addcslashes($rule->definition->regex, "\0..\37"),
            $rule->definition instanceof TaggedRule      => 'tag:' . $rule->definition->tag,
            $rule->definition instanceof TechnicalTokenRule => 'technical:' . $rule->definition->name,
            default                                      => null,
        };

        $subscribers = array_values(array_map(
            fn(EventSubscriber $s) => $this->shortClass($s->eventClassName) . ' → ' . $this->resolveListenerName($s),
            $rule->eventSubscribers,
        ));

        return new RuleViewData(
            name: $rule->name,
            type: $rule->type->name,
            nodeType: $rule->nodeType?->name,
            priority: $rule->priority,
            tags: $rule->getAllTags(),
            sequenceDefinition: $sequence,
            eventSubscribers: $subscribers,
        );
    }

    private function fromEventSubscriber(EventSubscriber $subscriber): EventSubscriberViewData
    {
        return new EventSubscriberViewData(
            eventShortName: $this->shortClass($subscriber->eventClassName),
            listenerShortName: $this->resolveListenerName($subscriber),
            priority: $subscriber->priority,
            onlyForRule: $subscriber->onlyForRuleName,
        );
    }

    private function resolveListenerName(EventSubscriber $subscriber): string
    {
        $listener = $subscriber->listener;
        if ($listener instanceof Closure) {
            return 'Closure';
        }
        return $this->shortClass(get_class($listener));
    }

    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }

    private function formatScope(int $scope): string
    {
        $parts = [];
        if (($scope & Region::RULES) === Region::RULES) {
            $parts[] = 'Rules';
        }
        if (($scope & Region::REGIONS) === Region::REGIONS) {
            $parts[] = 'Regions';
        }
        if (($scope & Region::MIDDLEWARES) === Region::MIDDLEWARES) {
            $parts[] = 'Middlewares';
        }
        if (($scope & Region::EVENT_SUBSCRIBERS) === Region::EVENT_SUBSCRIBERS) {
            $parts[] = 'EventSubscribers';
        }
        return implode('+', $parts);
    }
}
