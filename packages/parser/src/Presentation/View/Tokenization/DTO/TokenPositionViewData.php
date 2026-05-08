<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Tokenization\DTO;

final class TokenPositionViewData
{
    public function __construct(
        public readonly int $startAbs,
        public readonly int $endAbs,
        public readonly ?int $startRow,
        public readonly ?int $startCol,
        public readonly ?int $endRow,
        public readonly ?int $endCol,
    ) {}

    public function hasRowCol(): bool
    {
        return $this->startRow !== null;
    }
}
