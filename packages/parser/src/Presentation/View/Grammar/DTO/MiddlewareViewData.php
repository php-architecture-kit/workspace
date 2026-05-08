<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Grammar\DTO;

final class MiddlewareViewData
{
    public function __construct(
        public readonly string $hookName,
        public readonly string $shortClassName,
        public readonly int $priority,
    ) {}
}
