<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Ast\Format;

class AstNodeFormat
{
    public function __construct(
        public private(set) string $variantName,
        public private(set) array $values = []
    ) {}
}
