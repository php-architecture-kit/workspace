<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\ParseTree\DTO;

final class ParseNodeViewData
{
    public const TYPE_NODE              = 'Node';
    public const TYPE_NODE_ATTR         = 'NodeAttribute';
    public const TYPE_GROUP_ATTR        = 'GroupAttribute';
    public const TYPE_OPTIONAL_ATTR     = 'OptionalAttribute';
    public const TYPE_RAW_CONTENT_ATTR  = 'RawContentAttribute';
    public const TYPE_RAW_REGION_ATTR   = 'RawRegionAttribute';
    public const TYPE_STRUCTURE_ATTR    = 'StructureAttribute';

    /**
     * @param string[]             $tags
     * @param array<string,scalar> $meta   serializable meta only
     * @param ParseNodeViewData[]  $children
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly array $tags,
        public readonly array $meta,
        public readonly array $children,
        public readonly ?string $content     = null,
        public readonly ?bool   $present     = null,
        public readonly ?int    $childCount  = null,
    ) {}
}
