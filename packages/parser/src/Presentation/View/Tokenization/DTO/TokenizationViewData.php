<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Tokenization\DTO;

final class TokenizationViewData
{
    /**
     * @param TokenStreamItemViewData[] $items
     * @param array<string,int>         $tokenStats   name → count, sorted desc
     * @param array<string,int>         $regionStats  name → count, sorted desc
     * @param TokenViewData[]           $unknownTokens
     */
    public function __construct(
        public readonly string $rootRegionName,
        public readonly int $totalTokens,
        public readonly int $totalRegions,
        public readonly array $items,
        public readonly array $tokenStats,
        public readonly array $regionStats,
        public readonly array $unknownTokens,
    ) {}

    public function hasUnknownTokens(): bool
    {
        return count($this->unknownTokens) > 0;
    }
}
