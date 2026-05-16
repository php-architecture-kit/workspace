<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property StructureAttribute $begin-array
 * @property GroupAttribute<TrailingWsNode|LeadingWsNode> $ws
 * @property OptionalAttribute<ItemsNode> $items
 * @property StructureAttribute $end-array
 */
class ArrayNode extends Node {}
