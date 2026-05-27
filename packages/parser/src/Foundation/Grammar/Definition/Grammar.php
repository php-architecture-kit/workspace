<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition;

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
            $this->global->getRegionsRecursively(),
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

    /**
     * Stamps origin on all unstamped rules and regions. Already-stamped items are skipped unless
     * $overwriteExisting is true. Pass region names in $forceRegions to force-update the origin
     * on regions that were modified (but not replaced) by this grammar level — their existing rules
     * keep their original origin, only the region wrapper itself is re-stamped.
     *
     * @param string[] $forceRegions
     */
    public function stampOrigin(GrammarOrigin $origin, bool $overwriteExisting = false, array $forceRegions = []): self
    {
        foreach ($this->getAllRegions() as $region) {
            $forceThisRegion = $overwriteExisting || in_array($region->name, $forceRegions, true);
            if ($forceThisRegion || !$region->hasMeta(Region::META_ORIGIN)) {
                $region->setMeta(Region::META_ORIGIN, $origin);
            }
            foreach ($region->rules as $rule) {
                if ($overwriteExisting || !$rule->hasMeta(Rule::META_ORIGIN)) {
                    $rule->setMeta(Rule::META_ORIGIN, $origin);
                }
            }
        }

        return $this;
    }
}
