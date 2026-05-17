<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\StructureAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property NodeAttribute<MemberNode> $member
 * @property GroupAttribute $leadingTrivia
 * @property StructureAttribute $valueSeparator
 * @property GroupAttribute<TrailingWsNode|LeadingWsNode> $trailingTrivia
 */
class MembersNode extends Node {}
