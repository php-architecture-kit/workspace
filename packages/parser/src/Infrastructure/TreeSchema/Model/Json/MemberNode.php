<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property RawRegionAttribute $string
 * @property GroupAttribute<InlineWsNode> $ws
 * @property StructureAttribute $name-separator
 * @property NodeAttribute<ValueNode> $value
 */
class MemberNode extends Node {}
