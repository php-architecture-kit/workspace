<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;

class NodeTypeToTagCompiler implements RegionPrecompilerInterface
{
    public function precompileRegion(Region $region): void
    {
        foreach ($region->rules as $rule) {
            $nodeType = $rule->nodeType;
            if ($nodeType !== null) {
                $rule->addTag($nodeType->value);
            }
        }

        $region->addTag(
            $region->config->nodeType->value,
        );
    }
}
