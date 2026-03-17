<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization;

use PhpArchitecture\Parser\Grammar\Grammar;

class TokenizationContextCompiler
{
    public function compile(
        Grammar $grammar,
        bool $applyBofEof = true,
        int $chunkSize = 8192,
        int $safeMargin = 1024,
    ): Tokenization {
        $rootName = $grammar->getRootRegion()->name;
        $context = new Tokenization($rootName, $applyBofEof, $chunkSize, $safeMargin);

        return $context;
    }

    
}
