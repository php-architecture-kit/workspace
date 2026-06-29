<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;

final class CursorLevel
{
    public function __construct(
        public readonly NestedSequence $nestedSequence,
        public readonly ?int $activeAlternative,
        public readonly int $positionInAlternative,
        public readonly int $completedIterations,
    ) {}
}
