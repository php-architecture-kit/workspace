<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Strategy;

interface CompilerStrategyInterface 
{
    public function execute(mixed $input): mixed;
}
