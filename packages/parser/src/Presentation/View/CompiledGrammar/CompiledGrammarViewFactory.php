<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\CompiledGrammar;

use Closure;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledEventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledRegion;
use PhpArchitecture\Parser\Foundation\Matching\Model\NestedSequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\Sequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceNode;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Pattern;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\CompiledEventSubscriberViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\CompiledGrammarViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\CompiledRegionViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\PatternViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\SequenceNodeViewData;
use PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO\SequenceViewData;

final class CompiledGrammarViewFactory
{
    public function fromCompiledGrammar(CompiledGrammar $grammar): CompiledGrammarViewData
    {
        $regions = [];
        foreach ($grammar->regions as $name => $region) {
            $regions[$name] = $this->fromRegion($region);
        }

        return new CompiledGrammarViewData(
            name: $grammar->name,
            variant: $grammar->variant,
            rootRegionName: $grammar->rootRegionName,
            requireBofEof: $grammar->requireBofEof,
            regions: $regions,
        );
    }

    public function fromRegion(CompiledRegion $region): CompiledRegionViewData
    {
        $patterns = [];
        foreach ($region->patternLibrary->patterns as $name => $pattern) {
            $patterns[] = $this->fromPattern($pattern);
        }

        $sequences = [];
        $rootSeq = $region->sequenceLibrary->rootSequence;
        if ($rootSeq !== null) {
            $sequences[] = $this->fromSequence($rootSeq, isRoot: true);
        }
        foreach ($region->sequenceLibrary->sequences as $sequence) {
            $sequences[] = $this->fromSequence($sequence, isRoot: false);
        }

        $subscribers = array_values(array_map(
            $this->fromEventSubscriber(...),
            $region->eventSubscribers,
        ));

        return new CompiledRegionViewData(
            name: $region->name,
            tags: $this->filterDomainTags($region->tags),
            patterns: $patterns,
            sequences: $sequences,
            eventSubscribers: $subscribers,
        );
    }

    private function fromPattern(Pattern $pattern): PatternViewData
    {
        return new PatternViewData(
            name: $pattern->name,
            pattern: addcslashes($pattern->pattern, "\0..\37"),
            priority: $pattern->priority,
            tags: $pattern->tags,
        );
    }

    private function fromSequence(Sequence $sequence, bool $isRoot): SequenceViewData
    {
        $nodeType = $sequence->hasMeta('nodeType') ? $sequence->getMeta('nodeType')?->name : null;

        return new SequenceViewData(
            name: $sequence->name,
            isRoot: $isRoot,
            priority: $sequence->priority,
            nodeType: $nodeType,
            tags: $this->filterDomainTags($sequence->tags),
            nodes: array_map($this->fromSequenceNode(...), $sequence->nodes),
        );
    }

    private function fromSequenceNode(SequenceNode|NestedSequence $node): SequenceNodeViewData
    {
        if ($node instanceof SequenceNode) {
            $nodeType = null;
            $otherTags = [];
            foreach ($node->tags as $tag) {
                if (str_starts_with($tag, 'NodeType.')) {
                    $nodeType = substr($tag, 9);
                } else {
                    $otherTags[] = $tag;
                }
            }

            return new SequenceNodeViewData(
                type: SequenceNodeViewData::TYPE_SIMPLE,
                min: $node->min,
                max: $node->max,
                isLookahead: $node->isLookahead,
                isLookbehind: $node->isLookbehind,
                alternatives: $node->alternatives,
                tags: $otherTags,
                nodeType: $nodeType,
                nestedAlternatives: [],
            );
        }

        $nestedAlternatives = [];
        foreach ($node->alternativeSequences as $altNodes) {
            $nestedAlternatives[] = array_map($this->fromSequenceNode(...), $altNodes);
        }

        return new SequenceNodeViewData(
            type: SequenceNodeViewData::TYPE_NESTED,
            min: $node->min,
            max: $node->max,
            isLookahead: $node->isLookahead,
            isLookbehind: $node->isLookbehind,
            alternatives: [],
            tags: $node->tags,
            nodeType: null,
            nestedAlternatives: $nestedAlternatives,
        );
    }

    private function fromEventSubscriber(CompiledEventSubscriber $sub): CompiledEventSubscriberViewData
    {
        $listenerClass = get_class($sub->listener);
        $shortListener = $this->shortClass($listenerClass);
        $details       = $this->resolveListenerDetails($sub);

        return new CompiledEventSubscriberViewData(
            eventShortName: $this->shortClass($sub->eventClassName),
            listenerShortName: $shortListener,
            details: $details,
            priority: $sub->priority,
            onlyForRule: $sub->onlyForRuleName,
        );
    }

    private function resolveListenerDetails(CompiledEventSubscriber $sub): string
    {
        $listener  = $sub->listener;
        $className = get_class($listener);

        if (str_ends_with($className, 'StartRegionEventListener')) {
            if (property_exists($listener, 'region') && property_exists($listener, 'rule')) {
                $regionName = $listener->region->name ?? 'unknown';
                $ruleName   = $listener->rule->name ?? 'unknown';
                return "region:{$regionName}, rule:{$ruleName}";
            }
        }

        if (str_ends_with($className, 'EndRegionEventListener')) {
            if (property_exists($listener, 'rule')) {
                $parts = ['rule:' . ($listener->rule->name ?? 'unknown')];
                if (property_exists($listener, 'negated') && $listener->negated) {
                    $parts[] = 'negated';
                }
                if (property_exists($listener, 'allowedForTokenWhichStartedRegion') && $listener->allowedForTokenWhichStartedRegion) {
                    $parts[] = 'allowStartToken';
                }
                if (property_exists($listener, 'callLastTokenRemoval') && !$listener->callLastTokenRemoval) {
                    $parts[] = 'removeToken:false';
                }
                return implode(', ', $parts);
            }
        }

        return $sub->onlyForRuleName !== null ? "rule:{$sub->onlyForRuleName}" : 'all';
    }

    /**
     * Removes internal NodeType.* meta-tags — they are not domain tags.
     * @param string[] $tags
     * @return string[]
     */
    private function filterDomainTags(array $tags): array
    {
        return array_values(array_filter(
            $tags,
            static fn(string $t) => !str_starts_with($t, 'NodeType.'),
        ));
    }

    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }
}
