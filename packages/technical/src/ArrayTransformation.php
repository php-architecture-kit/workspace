<?php

declare(strict_types=1);

namespace PhpArchitecture\Technical;

final class ArrayTransformation
{
    /**
     * @param mixed[] $items
     * @param callable(mixed):string $key
     * @return array<string,mixed>
     */
    public static function indexBy(
        array $items,
        callable $key,
    ): array {
        $output = [];
        foreach ($items as $item) {
            $output[$key($item)] = $item;
        }
        
        return $output;
    }
}
