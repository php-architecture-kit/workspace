<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parser;

use PhpArchitecture\Parser\Grammar\GrammarRegistry;

class Parser
{
    public function __construct(
        private readonly GrammarRegistry $grammarRegistry,
    ) {}
}
