<?php

declare(strict_types=1);

namespace PhpArchitecture\Technical;

use InvalidArgumentException;
use Throwable;

final class Assert
{
    /**
     * @param mixed[] $items
     * @param class-string $class
     * @param class-string<Throwable> $exceptionClass
     * @throws Throwable
     */
    public static function eachInstanceOf(
        array $items,
        string $class,
        string $exceptionClass = InvalidArgumentException::class,
        int $displayLimit = 5
    ): void {
        if (!is_subclass_of($exceptionClass, Throwable::class)) {
            throw new InvalidArgumentException("Exception class must implement Throwable.");
        }

        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class $class does not exist.");
        }

        $invalidItems = [];
        foreach ($items as $index => $item) {
            if (!$item instanceof $class) {
                $invalidItems[$index] = $item;
            }
        }

        if (!empty($invalidItems)) {
            $mapped = [];
            foreach ($invalidItems as $key => $instance) {
                $mapped[] = "{$key} => " . (is_object($instance) ? $instance::class : gettype($instance));
            }

            throw new $exceptionClass(
                'Invalid instances provided. Expected `' . $class . '`, received `'
                    . implode('`, `', array_slice($mapped, 0, $displayLimit))
                    . (count($invalidItems) > $displayLimit ? '` and ' . (count($invalidItems) - $displayLimit) . ' more.' : '`.'),
            );
        }
    }

    /**
     * @param mixed[] $items
     * @param class-string<Throwable> $exceptionClass
     * @throws Throwable
     */
    public static function eachString(
        array $items,
        string $exceptionClass = InvalidArgumentException::class,
        int $displayLimit = 5
    ): void {
        if (!is_subclass_of($exceptionClass, Throwable::class)) {
            throw new InvalidArgumentException("Exception class must implement Throwable.");
        }

        $invalidItems = [];
        foreach ($items as $index => $item) {
            if (!is_string($item)) {
                $invalidItems[$index] = $item;
            }
        }

        if (!empty($invalidItems)) {
            $mapped = [];
            foreach ($invalidItems as $key => $item) {
                $mapped[] = "{$key} => " . (is_object($item) ? $item::class : gettype($item));
            }

            throw new $exceptionClass(
                'Invalid items provided. Expected `string`, received `'
                    . implode('`, `', array_slice($mapped, 0, $displayLimit))
                    . (count($invalidItems) > $displayLimit ? '` and ' . (count($invalidItems) - $displayLimit) . ' more.' : '`.'),
            );
        }
    }
}
