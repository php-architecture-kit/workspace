<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Creation\DefaultsDefinition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Regex\RegexRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\ParsedTree\Context\ContextStack;

/**
 * Resolves a rule's fixed literal text from the grammar Definition's own
 * Defaults (Rule::token/Rule::keyword attach one — see Rule.php). This is the
 * only legitimate source of a default/fixed value for generated code: a
 * CompiledGrammar carries no Defaults at all, and a parsed sample's matched
 * text is incidental to that one input, not a fact about the grammar.
 *
 * Returns null whenever no such Defaults exist (e.g. the rule is a variable
 * Rule::expr()) — callers must not fall back to guessing from sample output.
 */
final class GrammarLiteralResolver
{
    public function __construct(
        private readonly Grammar $grammar,
    ) {}

    public function regionByName(string $name): ?Region
    {
        foreach ($this->allRegions() as $region) {
            if ($region->name === $name) {
                return $region;
            }
        }

        return null;
    }

    public function ruleByName(string $name): ?Rule
    {
        foreach ($this->allRegions() as $region) {
            if (isset($region->rules[$name])) {
                return $region->rules[$name];
            }
        }

        return null;
    }

    /**
     * Every region in this grammar, plus (recursively) every region of any inner
     * grammar attached via retokenizedByInnerGrammar() — a region's body in that case
     * lives entirely in a separate Grammar object getAllRegions() does not see.
     *
     * @return Region[]
     */
    private function allRegions(): array
    {
        return $this->collectRegions($this->grammar);
    }

    /** @return Region[] */
    private function collectRegions(Grammar $grammar): array
    {
        // array_merge() on these name-keyed maps would collide and silently drop the
        // outer grammar's region whenever an inner grammar has one with the same name
        // (every Grammar has its own "global", for instance) — spread by value instead.
        $regions = $grammar->getAllRegions();
        $all = array_values($regions);

        foreach ($regions as $region) {
            if ($region->config->innerGrammar !== null) {
                $all = [...$all, ...$this->collectRegions($region->config->innerGrammar)];
            }
        }

        return $all;
    }

    /**
     * Resolves $name as a rule first; when no rule has that exact name, $name may be
     * an anchor (e.g. `?asterisk[openingAsterisk]`) that renamed a real rule reference
     * — StructureAttribute keeps only the anchor, not the original rule name, so the
     * grammar's own sequence definitions are the only place left to recover it from.
     */
    public function literalForRule(string $name): ?string
    {
        $rule = $this->ruleByName($name);
        if ($rule !== null) {
            return $this->literalFromRule($rule);
        }

        $aliasedRuleName = $this->ruleNameForAnchor($name);

        return $aliasedRuleName !== null ? $this->literalForRule($aliasedRuleName) : null;
    }

    /**
     * Searches every sequence definition in the grammar (region root sequences and
     * Rule::seq() rule bodies) for a slot anchored as $anchor, returning the single
     * rule name it refers to — or null when not found or genuinely ambiguous (a real
     * union of more than one alternative has no one rule name to resolve).
     */
    private function ruleNameForAnchor(string $anchor): ?string
    {
        $matchesAnchor = static fn(SequenceNode $node): bool => $node->anchorName === $anchor;

        foreach ($this->allRegions() as $region) {
            $rootSequence = $region->config->rootSequence;
            if ($rootSequence !== null) {
                $found = $rootSequence->getAllSequenceNodes($matchesAnchor);
                if (isset($found[0]) && count($found[0]->alternatives) === 1) {
                    return $found[0]->alternatives[0];
                }
            }

            foreach ($region->rules as $rule) {
                if (!$rule->definition instanceof SequenceRule) {
                    continue;
                }
                $found = $rule->definition->getAllSequenceNodes($matchesAnchor);
                if (isset($found[0]) && count($found[0]->alternatives) === 1) {
                    return $found[0]->alternatives[0];
                }
            }
        }

        return null;
    }

    public function opener(Region $region): ?string
    {
        $listener = $region->config->opener?->listener;

        return $listener instanceof StartRegionEventListener
            ? $this->literalFromRule($listener->rule)
            : null;
    }

    public function closer(Region $region): ?string
    {
        $listener = $region->config->closer?->listener;

        return $listener instanceof EndRegionEventListener
            ? $this->literalFromRule($listener->rule)
            : null;
    }

    private function literalFromRule(Rule $rule): ?string
    {
        $definition = $rule->definition;
        if (!$definition instanceof RegexRule || $definition->defaults === null) {
            return null;
        }

        $factory = $definition->defaults->factoryFor(DefaultsDefinition::DEFAULT_STYLE);
        if ($factory === null) {
            return null;
        }

        return $factory(new ContextStack(), DefaultsDefinition::DEFAULT_STYLE);
    }
}
