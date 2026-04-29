<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition;

use InvalidArgumentException;

class Definition
{
    /** @var Rule[] */
    public private(set) array $inheritedRuleDefs = [];

    /** @var Region[] */
    public private(set) array $inheritedRegDefs = [];

    /** @param (Rule|Region)[] */
    public function __construct(
        public private(set) string $name,
        array $inheritedDefs = [],
    ) {
        foreach ($inheritedDefs as $inheritedDef) {
            if ($inheritedDef instanceof Rule) {
                $this->inheritedRuleDefs[] = $inheritedDef;
            } elseif ($inheritedDef instanceof Region) {
                $this->inheritedRegDefs[] = $inheritedDef;
            } else {
                throw new InvalidArgumentException('Inherited definition must be an instance of Rule or Region');
            }
        }
    }
}
