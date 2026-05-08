<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\DynamicTokenInitEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenAddedEvent;
use RuntimeException;

class DynamicTokenCompiler implements GrammarPrecompilerInterface
{
    public function precompileGrammar(Grammar $grammar): void
    {
        $regions = $grammar->getAllRegions();

        foreach ($regions as $region) {
            foreach ($region->rules as $rule) {
                if ($rule->type !== RuleType::DynamicToken) {
                    continue;
                }

                /** @var CallbackRule $callbackDefinition */
                $callbackDefinition = $rule->definition;
                $eventSubscriber = EventSubscriber::on(
                    TokenAddedEvent::class,
                    new DynamicTokenInitEventListener(
                        $callbackDefinition->triggerRule,
                        $rule,
                        $region,
                    ),
                );
                $eventSubscriberHash = $eventSubscriber->hash();

                foreach ($callbackDefinition->listenInRegions as $regionName) {
                    $listenerRegions = match ($regionName) {
                        CallbackRule::GLOBAL_REGION => [$grammar->global],
                        CallbackRule::PARENT_REGION => array_filter($regions, static fn(Region $r): bool => in_array($region, $r->regions)),
                        CallbackRule::ROOT_REGION => [$grammar->rootRegion],
                        CallbackRule::SAME_REGION => [$this],
                        CallbackRule::LISTEN_IN_ALL_REGIONS => $regions,
                        default => isset($regions[$regionName]) ? [$regions[$regionName]] : [],
                    };

                    foreach ($listenerRegions as $region) {
                        if ($region === null) {
                            throw new RuntimeException("Region `{$regionName}` not found for rule `{$rule->name}`");
                        }

                        if (!array_key_exists($eventSubscriberHash, $region->eventSubscribers)) {
                            $region->add($eventSubscriber);
                        }
                    }
                }
            }
        }
    }
}
