<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

/**
 * Shared convention for mapping a grammar's (format, variant) to its facade
 * namespace and output directory under a configurable base. Used by both the
 * schema router (targetNamespace) and the CLI command (output dir), so the
 * namespace a class declares and the path it is written to never drift apart.
 */
final class GrammarPath
{
    /**
     * PascalCases one path segment (format or variant), stripping non-alphanumerics
     * and prefixing a leading digit with `Ver` so the result is a valid identifier.
     */
    public static function pascalPart(string $value): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? $value;
        if ($clean === '') {
            return $value;
        }
        $result = ucfirst($clean);
        if (ctype_digit($result[0])) {
            $result = 'Ver' . $result;
        }
        return $result;
    }

    /** `{baseNamespace}\{Format}\{Variant}` (Variant segment omitted when null/empty). */
    public static function namespaceFor(string $baseNamespace, string $format, ?string $variant): string
    {
        $ns = rtrim($baseNamespace, '\\') . '\\' . self::pascalPart($format);
        if ($variant !== null && $variant !== '') {
            $ns .= '\\' . self::pascalPart($variant);
        }
        return $ns;
    }

    /**
     * The directory for a target namespace relative to the base: strips the base
     * namespace prefix and turns `\` into `/`, joined onto the base directory.
     */
    public static function dirFor(string $baseDir, string $baseNamespace, string $targetNamespace): string
    {
        $relative = ltrim(substr($targetNamespace, strlen(rtrim($baseNamespace, '\\'))), '\\');
        $relativePath = str_replace('\\', '/', $relative);
        $base = rtrim($baseDir, '/');
        return $relativePath === '' ? $base : $base . '/' . $relativePath;
    }
}
