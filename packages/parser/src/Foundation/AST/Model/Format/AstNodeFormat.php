<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Model\Format;

class AstNodeFormat
{
    public function __construct(
        public private(set) string $variantName,
        public private(set) array $values = []
    ) {}
}
