<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition;

use InvalidArgumentException;

class Grammar
{
    public readonly Region $global;
    public private(set) Region $rootRegion;
    public bool $requireBofEof = true;

    public function __construct(
        public readonly string $name,
        public readonly ?string $variant = null,
        string $globalRegionName = 'global',
    ) {
        $this->global = (new Region($globalRegionName));
        $this->rootRegion = $this->global;
    }

    /**
     * @return array<string,Region>
     */
    public function getAllRegions(): array
    {
        $output = [$this->global->name => $this->global];

        return array_merge(
            $output,
            $this->global->getRegionsRecursively()
        );
    }

    public function setRootRegion(Region $region): void
    {
        $allRegions = $this->getAllRegions();
        if (!isset($allRegions[$region->name])) {
            throw new InvalidArgumentException("Region {$region->name} not found in grammar {$this->name}");
        }

        if ($region !== $allRegions[$region->name]) {
            throw new InvalidArgumentException("Region {$region->name} is not the same instance as the one in the grammar {$this->name}");
        }

        $this->rootRegion = $region;
    }
}
