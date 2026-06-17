<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Ref;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\RuleDefinition;

final class RefRuleDefinition implements RuleDefinition
{
    public function __construct(public readonly string $refName) {}
}
