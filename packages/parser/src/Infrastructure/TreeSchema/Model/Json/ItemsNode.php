<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property NodeAttribute<ValueNode> $value
 * @property GroupAttribute<TrailingWsNode|LeadingWsNode> $ws
 * @property StructureAttribute $value-separator
 */
class ItemsNode extends Node {}
