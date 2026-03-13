<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar;

use PhpArchitecture\Parser\Grammar;

class RegionConfig
{
    public function __construct(
        // open
        public ?Rule $openRule = null,
        public bool $includeOpenRuleMatch = false,

        // inside
        public bool $includeAncestorRules = true,
        public bool $includeAncestorEventSubscribers = true,

        // inside grammar
        public ?Grammar $insideGrammar = null,
        public bool $retokenizeWithInsideGrammar = false,

        // close
        public ?Rule $closeRule = null,
        public bool $includeCloseRuleMatch = false,
        public bool $closeAfterOpenRuleMatch = false,
        public bool $closeWhenCloseRuleNotMatch = false, // negation of closeRule
    ) {}

    public function assertValid(): void
    {
        if ($this->openRule !== null && $this->closeRule !== null) {
            if (!$this->openRule->type->isSamePurpose($this->closeRule->type)) {
                throw new \InvalidArgumentException('Open and close rules must have the same purpose');
            }
        }
    }
}
