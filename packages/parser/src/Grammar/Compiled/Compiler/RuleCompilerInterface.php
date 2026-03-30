<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Grammar\Definition\Rule;

interface RuleCompilerInterface
{
    public function supports(object $object): bool;

    public function compileRule(Rule $rule): object;
}
