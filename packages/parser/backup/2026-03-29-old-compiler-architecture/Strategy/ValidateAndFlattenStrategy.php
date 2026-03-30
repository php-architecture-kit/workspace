<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Strategy;

use PhpArchitecture\Parser\Grammar\Compiled\Internal\WorkingRegion;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use RuntimeException;

final class ValidateAndFlattenStrategy implements CompilerStrategyInterface
{
    /**
     * @param Grammar $input
     * @return array<string, WorkingRegion>
     */
    public function execute(mixed $input): array
    {
        $allRegions = $input->getAllRegions();
        $workingRegions = [];
        $rootRegionName = isset($input->rootRegion) ? $input->rootRegion->name : 'global';

        foreach ($allRegions as $name => $region) {
            $parentName = $this->findParentName($name, $allRegions);
            $working = new WorkingRegion($region, $parentName);
            
            if ($name === $rootRegionName) {
                $working->metadata['isRoot'] = true;
            }
            
            $workingRegions[$name] = $working;
        }

        return $workingRegions;
    }

    /**
     * @param array<string, \PhpArchitecture\Parser\Grammar\Definition\Region> $allRegions
     */
    private function findParentName(string $regionName, array $allRegions): ?string
    {
        if ($regionName === 'global') {
            return null;
        }

        foreach ($allRegions as $name => $region) {
            if (isset($region->regions[$regionName])) {
                return $name;
            }
        }

        return null;
    }
}
