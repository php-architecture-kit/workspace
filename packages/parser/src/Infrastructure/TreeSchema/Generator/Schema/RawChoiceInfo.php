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
        public readonly bool $hasOpener = false,
        public readonly ?string $openerContent = null,
        public readonly ?string $closerContent = null,
        /**
         * The variant's fixed content, when its rule is a Rule::keyword()/Rule::token()
         * with a Defaults literal (e.g. "false", "NaN") — content never actually varies
         * for these, so the generated factory can fall back to it when none is passed.
         * Null for variable-content variants (e.g. number, doubleQuotedString).
         */
        public readonly ?string $literalContent = null,
    ) {}
}
