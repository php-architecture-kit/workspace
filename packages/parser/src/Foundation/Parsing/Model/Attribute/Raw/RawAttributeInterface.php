<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Tags\TagsInterface;

interface RawAttributeInterface extends NodeAttributeInterface, MetaInterface, TagsInterface
{
    public const DEFAULT_NAME = 'raw';
}
