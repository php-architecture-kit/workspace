<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Shared\Hash;

use Closure;
use ReflectionException;
use ReflectionFunction;

trait HashClosure
{
    protected function hashClosure(Closure $closure): string
    {
        try {
            $reflection = new ReflectionFunction($closure);

            $parts = [
                $reflection->getFileName() ?: 'runtime',
                (string) $reflection->getStartLine(),
                (string) $reflection->getEndLine(),
            ];

            // Include static variables if any (captures)
            $staticVars = $reflection->getStaticVariables();
            if (!empty($staticVars)) {
                $varHashes = [];
                foreach ($staticVars as $key => $value) {
                    if (is_object($value)) {
                        $varHashes[] = $key . ':' . get_class($value) . ':' . spl_object_hash($value);
                    } else {
                        $varHashes[] = $key . ':' . md5(serialize($value));
                    }
                }
                $parts[] = md5(implode('|', $varHashes));
            }

            return implode(':', $parts);
        } catch (ReflectionException) {
            // Fallback for runtime-generated closures
            return spl_object_hash($closure);
        }
    }
}
