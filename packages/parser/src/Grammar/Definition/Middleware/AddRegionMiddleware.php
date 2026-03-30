<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Middleware;

use PhpArchitecture\Parser\Grammar\Definition\Region;

class AddRegionMiddleware extends AbstractMiddleware
{
    /**
     * @param Region $region
     * @return Region
     */
    public function handle(object $region): object
    {
        return ($this->callback)($region);
    }

    public function method(): string
    {
        return self::ADD_REGION;
    }
}
