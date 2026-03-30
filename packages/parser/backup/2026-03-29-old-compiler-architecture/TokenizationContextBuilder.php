<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization;

use PhpArchitecture\Parser\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Grammar\Compiled\Model\Region;
use PhpArchitecture\Parser\Grammar\Compiled\Model\Rule;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Pattern;
use PhpArchitecture\Parser\Processing\Model\Tokenization\PatternLibrary;
use PhpArchitecture\Parser\Tokenization\Context\DefaultTokenizationContext;
use PhpArchitecture\Parser\Tokenization\Extension\IdentifyRowsAndColumns;

final class TokenizationContextBuilder
{
    public function build(CompiledGrammar $grammar, bool $applyRowColTracking = true): TokenizationContext
    {
        $patternLibraries = [];
        $rootRegionName = $this->findRootRegionName($grammar);

        foreach ($grammar->regions as $regionName => $region) {
            $patternLibraries[$regionName] = $this->buildPatternLibrary($region);
        }

        $context = new DefaultTokenizationContext(
            rootName: $rootRegionName,
            regionToPatternLibraryMap: $patternLibraries,
            regionToEventDispatcherMap: [],
        );

        foreach ($grammar->regions as $regionName => $region) {
            $this->registerEventSubscribers($context, $regionName, $region);
        }

        if ($applyRowColTracking) {
            (new IdentifyRowsAndColumns())->extend($context);
        }

        return $context;
    }

    private function buildPatternLibrary(Region $region): PatternLibrary
    {
        $patterns = [];

        foreach ($region->rules as $rule) {
            $pattern = $this->createPatternFromRule($rule);
            if ($pattern !== null) {
                $patterns[] = $pattern;
            }
        }

        return new PatternLibrary($patterns);
    }

    private function createPatternFromRule(Rule $rule): ?Pattern
    {
        return match ($rule->type) {
            RuleType::Token, RuleType::Keyword, RuleType::Expression => new Pattern(
                name: $rule->name,
                pattern: $rule->definition->regex,
                priority: $rule->priority
            ),
            RuleType::DynamicToken, RuleType::Tag => null,
        };
    }

    private function registerEventSubscribers(
        TokenizationContext $context,
        string $regionName,
        Region $region
    ): void {
        foreach ($region->eventSubscribers as $subscriber) {
            $context->registerEventListener(
                $subscriber->listener,
                $subscriber->eventClassName,
                $subscriber->onlyForRuleName,
                $regionName
            );
        }
    }

    private function findRootRegionName(CompiledGrammar $grammar): string
    {
        foreach ($grammar->regions as $regionName => $region) {
            if ($region->metadata['isRoot'] ?? false) {
                return $regionName;
            }
        }

        return 'global';
    }
}
