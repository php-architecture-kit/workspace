<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Grammar\DTO;

final class GrammarViewData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $variant,
        public readonly string $rootRegionName,
        public readonly bool $requireBofEof,
        public readonly int $totalRegions,
        public readonly RegionViewData $globalRegion,
    ) {}
}
