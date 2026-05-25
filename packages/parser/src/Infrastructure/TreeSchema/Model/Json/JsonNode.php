<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property GroupAttribute<LeadingWsNode|TrailingWsNode> $leadingTrivia
 * @property ChoiceAttribute<ObjectNode> $value
 * @property GroupAttribute<TrailingWsNode> $trailingTrivia
 */
class JsonNode extends Node {}
