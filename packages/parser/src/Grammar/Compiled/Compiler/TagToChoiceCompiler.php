<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Grammar\Definition\Model\Technical\TaggedRule;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Grammar\Definition\Service\SequenceExtender\SequenceExtender;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;

class TagToChoiceCompiler implements GrammarCompilerInterface
{
    public function compileGrammar(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            $this->compileRegion($region);
        }
    }

    public function compileRegion(Region $region): void
    {
        $tagsMap = $this->getTagsMap($region);

        foreach ($tagsMap as $tag => $options) {
            if (!isset($region->rules[$tag]) || $region->rules[$tag]->definition instanceof TaggedRule) {
                $region->addRule(Rule::choice($tag, $options, type: NodeType::Node));
            }
        }
    }

    /** @return array<string,string[]> */
    private function getTagsMap(Region $region): array
    {
        /** @return array<string,string[]> */
        return array_map(
            'array_unique',
            array_merge_recursive(
                $this->getTagNestedRegionMap($region),
                $this->getTagRuleMap($region),
            )
        );
    }

    /** @return array<string,string[]> */
    private function getTagRuleMap(Region $region): array
    {
        $output = [];

        foreach ($region->rules as $rule) {
            foreach ($rule->tags as $tag) {
                if (NodeType::tryFrom($tag) === null) {
                    $output[$tag][] = $rule->name;
                }
            }
        }

        return $output;
    }

    /** @return array<string,string[]> */
    private function getTagNestedRegionMap(Region $region): array
    {
        $nestedRegions = $this->getAllNestedRegions($region);
        $output = [];

        foreach ($nestedRegions as $nestedRegion) {
            foreach ($nestedRegion->tags as $tag) {
                if (NodeType::tryFrom($tag) === null) {
                    $output[$tag][] = $nestedRegion->name;
                }
            }
        }

        return $output;
    }

    /** @return Region[] */
    private function getAllNestedRegions(Region $region): array
    {
        return array_values(array_map(
            static function (EventSubscriber $subscriber): Region {
                assert($subscriber->listener instanceof StartRegionEventListener);
                return $subscriber->listener->region;
            },
            array_filter(
                $region->eventSubscribers,
                static fn(EventSubscriber $subscriber): bool => $subscriber->listener instanceof StartRegionEventListener
            )
        ));
    }
}
