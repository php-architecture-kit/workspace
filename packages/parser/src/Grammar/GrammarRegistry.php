<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

interface GrammarRegistry
{
    public function get(string $name, ?string $variant = null): Grammar;
}
