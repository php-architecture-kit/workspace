<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Technical\TaggedRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

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
                $region->addRule(Rule::choice($tag, $options, type: NodeType::Tag)->priority(in_array($tag, ['-', '-l', '-t']) ? -9999 : -999));
            }
        }
    }

    /** @return array<string,string[]> */
    private function getTagsMap(Region $region): array
    {
        $tagMap = array_merge_recursive(
            $this->getTagNestedRegionMap($region),
            $this->getTagRuleMap($region),
        );

        foreach ($tagMap as $tag => $options) {
            usort($options, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
            $tagMap[$tag] = array_map(static fn(array $option): string => $option['name'], $options);
        }

        return array_map(
            'array_unique',
            $tagMap,
        );
    }

    /** @return array<string,array{name:string,priority:int}[]> */
    private function getTagRuleMap(Region $region): array
    {
        $output = [];

        foreach ($region->rules as $rule) {
            foreach ($rule->tags as $tag) {
                if (NodeType::tryFrom($tag) === null) {
                    $output[$tag][] = ['name' => $rule->name, 'priority' => $rule->priority];
                }
            }
        }

        return $output;
    }

    /** @return array<string,array{name:string,priority:int}[]> */
    private function getTagNestedRegionMap(Region $region): array
    {
        $nestedRegions = $this->getAllNestedRegions($region);
        $output = [];

        foreach ($nestedRegions as $nestedRegion) {
            foreach ($nestedRegion->tags as $tag) {
                if (NodeType::tryFrom($tag) === null) {
                    // A tag normally resolves to "this region, in any possible form"
                    // (its own name). withPossibleNamesForTag() narrows that to a
                    // specific subset of withPossibleNames() when the tag is meant to
                    // pick out only some of the region's runtime-renamed forms — see
                    // Whitespace.php's '-l'/'-t' for why that distinction matters.
                    $names = $nestedRegion->config->possibleNamesByTag[$tag] ?? [$nestedRegion->name];

                    $prioritizedNames = array_map(static fn(string $name): array => ['name' => $name, 'priority' => $nestedRegion->config->priority], $names);

                    $output[$tag] = array_merge($output[$tag] ?? [], $prioritizedNames);
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
                static fn(EventSubscriber $subscriber): bool => $subscriber->listener instanceof StartRegionEventListener,
            ),
        ));
    }
}
