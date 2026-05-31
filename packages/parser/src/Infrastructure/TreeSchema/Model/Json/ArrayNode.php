<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupedAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property StructureAttribute $beginArray
 * @property GroupAttribute<TrailingWsNode|LeadingWsNode> $trivia0
 * @property GroupAttribute<TrailingWsNode|LeadingWsNode> $trivia1
 * @property StructureAttribute $endArray
 * @property GroupedAttribute<ChoiceAttribute<PrimitiveNode|ObjectNode|ArrayNode>|StructureAttribute|TrailingWsNode|LeadingWsNode> $items
 */
class ArrayNode extends Node {}
