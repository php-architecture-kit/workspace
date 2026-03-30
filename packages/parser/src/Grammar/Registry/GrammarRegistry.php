<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Registry;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;

interface GrammarRegistry
{
    public function get(string $name, ?string $variant = null): Grammar;
}
