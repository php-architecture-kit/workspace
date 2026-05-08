<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;

interface GrammarPrecompilerInterface
{
    public function precompileGrammar(Grammar $grammar): void;
}
