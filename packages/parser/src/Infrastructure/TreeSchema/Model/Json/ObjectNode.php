<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property StructureAttribute $begin-object
 * @property GroupAttribute<TrailingWsNode|LeadingWsNode> $ws
 * @property OptionalAttribute<MembersNode> $members
 * @property StructureAttribute $end-object
 */
class ObjectNode extends Node {}
