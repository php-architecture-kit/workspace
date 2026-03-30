<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Grammar\Definition\Region;

interface RegionPrecompilerInterface
{
    public function precompileRegion(Region $region): void;
}
