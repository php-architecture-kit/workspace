<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;

interface GrammarPrecompilerInterface
{
    public function precompileGrammar(Grammar $grammar): void;
}
