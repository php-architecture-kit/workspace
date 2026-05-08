<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\CompiledGrammar\DTO;

final class CompiledGrammarViewData
{
    /**
     * @param CompiledRegionViewData[] $regions  keyed by region name
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $variant,
        public readonly string $rootRegionName,
        public readonly bool $requireBofEof,
        public readonly array $regions,
    ) {}
}
