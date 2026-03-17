<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar;

use PhpArchitecture\Parser\Grammar as GrammarInterface;
use PhpArchitecture\Parser\Service\Contract\MemberGrammarInterface;
use PhpArchitecture\Parser\Service\Contract\TokenGrammarInterface;

class Grammar implements GrammarInterface, TokenGrammarInterface, MemberGrammarInterface
{
    public function __construct(
        public readonly string $name,
        public readonly Region $region,
        public readonly ?string $variant = null,
    ) {}
}
