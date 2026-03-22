<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\GrammarRegistry;

use PhpArchitecture\Parser\Grammar\Grammar;

interface GrammarFactoryInterface
{
    public function grammar(): Grammar;
}
