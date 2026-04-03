<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Parsing\Model;

class RegionRawContent extends RawContent
{
    public const REGION_INCLUDES_STRUCTURE_OPENER_KEY = 'structureOpenerIncluded';
    public const REGION_INCLUDES_STRUCTURE_CLOSER_KEY = 'structureCloserIncluded';

    public function __construct(
        string $name,
        string $content,
        public ?bool $isStructureOpenerPresent,
        public ?bool $isStructureCloserPresent,
    ) {
        parent::__construct($name, $content);
    }
}
