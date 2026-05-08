<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Contract;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;

interface GrammarRegistry
{
    public function get(string $name, ?string $variant = null): Grammar;
}
