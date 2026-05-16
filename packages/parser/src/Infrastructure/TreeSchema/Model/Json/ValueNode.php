<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property NodeAttribute<PrimitiveNode|ArrayNode|ObjectNode> $array|object|primitive
 */
class ValueNode extends Node {}
