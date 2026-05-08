<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Tokenization\DTO;

final class TokenViewData
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly string $raw,
        public readonly bool $isUnknown,
        public readonly array $tags,
        public readonly TokenPositionViewData $position,
    ) {}
}
