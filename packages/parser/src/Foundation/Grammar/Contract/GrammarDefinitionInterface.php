<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Contract;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;

interface GrammarDefinitionInterface
{
    public function grammar(): Grammar;
}
