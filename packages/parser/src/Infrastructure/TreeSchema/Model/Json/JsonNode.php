<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property GroupAttribute<LeadingWsNode|TrailingWsNode> $ws
 * @property NodeAttribute<ValueNode> $value
 */
class JsonNode extends Node {}
