<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property RawRegionAttribute $trailingWs
 * @property RawRegionAttribute $leadingWs
 */
class TrailingTriviaNode extends Node {}
