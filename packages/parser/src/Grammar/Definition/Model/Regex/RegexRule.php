<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Model\Regex;

use PhpArchitecture\Parser\Grammar\Definition\Model\RuleDefinition;

final class RegexRule implements RuleDefinition
{
    public function __construct(
        public readonly string $regex,
    ) {}

    public static function fromString(
        string $string,
        bool $caseSensitive = true
    ): self {
        $flags = $caseSensitive ? 'u' : 'ui';

        return new self(
            '~\G' . $string . '~' . $flags,
        );
    }
}
