<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\LazyOperators\Application;

final readonly class EvaluationResult
{
    /**
     * @param string[] $inputNames
     */
    public function __construct(
        public string $display,
        public string $type,
        public array $inputNames,
    ) {
    }

    /**
     * @param string[] $inputNames
     */
    public static function of(mixed $value, array $inputNames): self
    {
        $display = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return new self(
            display: $display === false ? var_export($value, true) : $display,
            type: get_debug_type($value),
            inputNames: $inputNames,
        );
    }
}
