<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar;

class Grammar
{
    public private(set) Region $rootRegion;
    public private(set) bool $compiled = false;
    public bool $requireBofEof = true;

    public function __construct(
        public readonly string $name,
        public readonly Region $global,
        public readonly ?string $variant = null,
    ) {}

    public function compile(): void
    {
        if ($this->compiled) {
            return;
        }

        $this->global->compileDownTopRecursively($this->global);
        $this->global->compileTopDownRecursively($this, $this->global);
        $this->compiled = true;
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
        $this->rootRegion = $region;
    }
}
