<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\PrattReparseRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Pratt\PrattRoleDefinition;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenizationFinishedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Pratt\PrattGrammarDefinition;

class PrattEventListenerCompiler implements GrammarCompilerInterface
{
    public function compileGrammar(Grammar $grammar): void
    {
        foreach ($grammar->getAllRegions() as $region) {
            if ($region->config->prattGroupedRegionName === null) {
                continue;
            }

            $roles = [];

            foreach ($region->rules as $rule) {
                if ($rule->prattRole !== null) {
                    $roles[$rule->name] = $rule->prattRole;
                }
            }

            foreach ($region->regions as $subRegion) {
                if ($subRegion->config->isPrattAtom) {
                    $roles[$subRegion->name] = PrattRoleDefinition::atom();
                }
            }

            $listener = new PrattReparseRegionEventListener(
                $region->name,
                $region->config->prattGroupedRegionName,
                new PrattGrammarDefinition($roles),
            );

            $region->addEventSubscriber(
                EventSubscriber::on(TokenRegionEndedEvent::class, $listener),
            );

            // Root region is never closed, so TokenRegionEndedEvent never fires for it.
            // Subscribe to TokenizationFinishedEvent which fires through the root dispatcher at end.
            if ($grammar->rootRegion->name === $region->name) {
                $region->addEventSubscriber(
                    EventSubscriber::on(TokenizationFinishedEvent::class, $listener),
                );
            }
        }
    }
}
