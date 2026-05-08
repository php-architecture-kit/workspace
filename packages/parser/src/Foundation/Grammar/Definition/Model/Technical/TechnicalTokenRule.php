<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Technical;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\RuleDefinition;

final class TechnicalTokenRule implements RuleDefinition
{
    public const BOF = 'bof';
    public const EOF = 'eof';
    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $name,
    ) {
        if (!in_array($name, [self::BOF, self::EOF, self::UNKNOWN])) {
            throw new InvalidArgumentException(__CLASS__ . " must represents only predefined technical tokens: bof, eof, unknown.");
        }
    }
}
