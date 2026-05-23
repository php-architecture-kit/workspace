<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property StructureAttribute $beginObject
 * @property GroupAttribute<TrailingWsNode|LeadingWsNode> $leadingTrivia
 * @property GroupAttribute<MemberNode|LeadingTriviaNode|CommaNode|TrailingTriviaNode> $member
 * @property GroupAttribute<TrailingWsNode|LeadingWsNode> $trailingTrivia
 * @property StructureAttribute $endObject
 */
class ObjectNode extends Node {}
