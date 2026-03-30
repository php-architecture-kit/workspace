<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Model;

final readonly class CompiledGrammar
{
    /**
     * @param array<string,CompiledRegion> $regions
     */
    public function __construct(
        public string $name,
        public ?string $variant,
        public bool $requireBofEof,
        public array $regions,
    ) {}
}
