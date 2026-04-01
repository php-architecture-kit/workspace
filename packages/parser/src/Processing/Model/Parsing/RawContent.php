<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Parsing;

class RawContent implements NodeInterface
{
    public function __construct(
        public string $name,
        public string $content
    ) {}
}
