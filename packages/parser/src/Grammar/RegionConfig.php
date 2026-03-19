<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

class RegionConfig
{
    public function __construct(
        // open
        public string|Rule|null $openRule = null, # Region::applyOpenRule
        public bool $includeOpenRule = false, # Region::applyOpenRule
        public bool $includeOpenRuleMatch = true, # Region::applyOpenRule
        public bool $closeAfterOpenRuleMatch = false, # Region::applyOpenRule

        // inside
        public bool $includeAncestorRules = true, # Region::applyIncludeAncestorRules
        public bool $includeAncestorEventSubscribers = true, # Region::applyIncludeAncestorEventSubscribers
        public bool $includeGlobalRules = true, # Region::applyIncludeGlobalRules
        public bool $includeGlobalEventSubscribers = true, # Region::applyIncludeGlobalEventSubscribers

        // inside grammar
        public ?Grammar $insideGrammar = null, # Region::applyInsideGrammar
        public bool $confirmMixOfRegionRulesAndInsideGrammarRules = false, # Region::applyInsideGrammar
        public bool $retokenizeWithInsideGrammar = true, # Region::applyInsideGrammar

        // close
        public string|Rule|null $closeRule = null, # Region::applyCloseRule
        public bool $includeCloseRuleMatch = true, # Region::applyCloseRule
        public bool $closeWhenCloseRuleNotMatch = false, // negation of closeRule, Region::applyCloseRule, EndRegionEventListener
    ) {}

    public function assertValid(): void
    {
        if ($this->openRule !== null && $this->closeRule instanceof Rule) {
            if (!$this->openRule->type->isSamePurpose($this->closeRule->type)) {
                throw new \InvalidArgumentException('Open and close rules must have the same purpose');
            }
        }

        if ($this->openRule !== null && !$this->includeOpenRuleMatch && $this->closeAfterOpenRuleMatch) {
            throw new \InvalidArgumentException('Cannot close after open rule match when open rule match is not included');
        }
    }
}
