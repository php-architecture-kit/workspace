<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;

interface CompilerExtensionInterface
{
    /**
     * Apply extension to the mutable Definition\Grammar.
     * Extensions can modify rules, regions, event subscribers, etc.
     * 
     * @param Grammar $grammar Mutable cloned grammar
     */
    public function apply(Grammar $grammar): void;

    /**
     * Execution priority. Lower values execute first.
     * Allows controlling order of extensions.
     */
    public function priority(): int;
}
