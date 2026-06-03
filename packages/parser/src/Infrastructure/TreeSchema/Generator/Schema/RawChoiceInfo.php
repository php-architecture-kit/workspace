<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema;

/**
 * Describes one case of a ChoiceAttribute whose choices are raw attributes
 * (RawContentAttribute / RawRegionAttribute). Used to generate the enum and
 * the setPrimitive-style method.
 */
final class RawChoiceInfo
{
    public function __construct(
        public readonly string $caseName,
        public readonly string $tokenName,
        public readonly bool $isKeyword,
        public readonly ?string $keywordContent = null,
        public readonly bool $hasOpener = false,
        public readonly ?string $openerContent = null,
        public readonly ?string $closerContent = null,
    ) {}

    public function requiresContent(): bool
    {
        return !$this->isKeyword;
    }
}
