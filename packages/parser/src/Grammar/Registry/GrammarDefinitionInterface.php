<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Registry;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;

interface GrammarDefinitionInterface
{
    public function grammar(): Grammar;
}
