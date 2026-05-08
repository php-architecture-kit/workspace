<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Grammar\DTO;

final class RuleViewData
{
    /**
     * @param string[] $tags
     * @param string[] $eventSubscribers  short event→listener pairs
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $nodeType,
        public readonly int $priority,
        public readonly array $tags,
        public readonly ?string $sequenceDefinition,
        public readonly array $eventSubscribers,
    ) {}
}
