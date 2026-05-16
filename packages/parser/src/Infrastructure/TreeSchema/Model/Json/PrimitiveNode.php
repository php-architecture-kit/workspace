<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Model\Json;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Node;

/**
 * @property RawRegionAttribute $string
 * @property RawRegionAttribute $number
 * @property RawContentAttribute $true
 * @property RawContentAttribute $false
 * @property RawContentAttribute $null
 */
class PrimitiveNode extends Node {}
