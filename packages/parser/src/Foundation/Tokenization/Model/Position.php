<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Model;

final class Position
{
    public const KEY_START = 'startPosition';
    public const KEY_END = 'endPosition';

    public function __construct(
        public readonly int $row,
        public readonly int $column,
    ) {}
}
