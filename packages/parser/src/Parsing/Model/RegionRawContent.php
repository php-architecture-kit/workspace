<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model;

class RegionRawContent extends RawContent
{
    public const REGION_INCLUDES_STRUCTURE_OPENER_KEY = 'structureOpenerIncluded';
    public const REGION_INCLUDES_STRUCTURE_CLOSER_KEY = 'structureCloserIncluded';

    /**
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        string $name,
        string $content,
        public ?Structure $opener,
        public ?Structure $closer,
        array $meta = [],
        array $tags = [],
    ) {
        parent::__construct($name, $content, $meta, $tags);
    }

    public function __toString(): string
    {
        return implode(
            '',
            [
                $this->opener?->__toString() ?? '',
                $this->content,
                $this->closer?->__toString() ?? '',
            ]
        );
    }
}
