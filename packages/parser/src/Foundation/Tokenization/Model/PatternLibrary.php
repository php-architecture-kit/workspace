<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Model;

final class PatternLibrary
{
    /** @var array<string,Pattern> */
    public array $patterns = [];

    /** @var array<string,int> */
    public array $patternIndexMap = [];

    /** 
     * @param Pattern[] $patterns
     */
    public function __construct(
        array $patterns,
    ) {
        $this->compilePatterns($patterns);
    }

    public function addPattern(Pattern $pattern): void
    {
        $this->patterns[$pattern->name] = $pattern;
        $this->compilePatterns(array_values($this->patterns));
    }

    /** @param Pattern[] $patterns */
    private function compilePatterns(array $patterns): void
    {
        $index = 0;
        usort($patterns, static fn($a, $b) => $b->priority - $a->priority);

        foreach ($patterns as $pattern) {
            $this->patterns[$pattern->name] = $pattern;
            $this->patternIndexMap[$pattern->name] = $index++;
        }
    }
}
