<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model;

final readonly class CompiledGrammar
{
    /**
     * @param array<string,CompiledRegion> $regions
     */
    public function __construct(
        public string $name,
        public ?string $variant,
        public bool $requireBofEof,
        public string $rootRegionName,
        public array $regions,
    ) {}
}
