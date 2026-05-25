<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\ChoiceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property RawRegionAttribute $identifier
 * @property GroupAttribute $leadingTrivia
 * @property StructureAttribute $colon
 * @property GroupAttribute<InlineWsNode> $trailingTrivia
 * @property ChoiceAttribute<PrimitiveNode|ArrayNode|ObjectNode> $value
 */
class MemberNode extends Node {}
