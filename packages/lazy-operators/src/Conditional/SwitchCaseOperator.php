<?php

declare(strict_types=1);

namespace PhpArchitecture\LazyOperators\Conditional;

use PhpArchitecture\LazyOperators\Conditional\Exception\NoMatchedCaseException;
use PhpArchitecture\LazyOperators\Expression;

class SwitchCaseOperator implements Expression
{
    /**
     * @param CaseOfSwitchCase[] $cases
     */
    public function __construct(
        private readonly Expression $condition,
        private readonly array $cases,
        private readonly ?Expression $default = null,
    ) {}

    public function __invoke(): mixed
    {
        $conditionValue = ($this->condition)();
        foreach ($this->cases as $case) {
            if (($case->condition)() === $conditionValue) {
                return ($case->value)();
            }
        }

        return $this->default
            ? ($this->default)()
            : throw new NoMatchedCaseException();
    }
}
