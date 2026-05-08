<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;

class InRuleDeclaredEventSubscribersCompiler implements RegionPrecompilerInterface
{
    public function precompileRegion(Region $region): void
    {
        foreach ($region->rules as $rule) {
            $eventSubscribers = $rule->eventSubscribers;
            $region->add(...$eventSubscribers);
        }
    }
}
