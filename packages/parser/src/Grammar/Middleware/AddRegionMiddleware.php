<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Middleware;

use Closure;
use PhpArchitecture\Parser\Grammar\Region;

final class AddRegionMiddleware implements GrammarMiddleware
{
    public function __construct(
        private Closure $callback,
        private int $priority = 0,
    ) {}

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

    public function priority(): int
    {
        return $this->priority;
    }
}
