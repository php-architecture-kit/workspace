<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\ParseTree\DTO;

final class ParseTreeViewData
{
    public function __construct(
        public readonly ParseNodeViewData $root,
        public readonly string $rawContent,
    ) {}
}
