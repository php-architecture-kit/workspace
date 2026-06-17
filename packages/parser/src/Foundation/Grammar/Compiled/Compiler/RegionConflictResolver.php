<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Shared\Hash\RegionHasher;
use RuntimeException;

/**
 * Resolves region name conflicts when content from one grammar is folded into another:
 * a same-named region is safe to skip (reuse the existing one) if structurally identical,
 * otherwise it's a real conflict and compilation must fail loudly instead of silently
 * picking one side.
 *
 * Works generically over either definition-level `Region`s or compiled `CompiledRegion`s —
 * RegionHasher dispatches the structural export appropriately for either. Comparing two
 * `CompiledRegion`s (rather than their `Region` definitions) matters specifically when the
 * two sides went through their grammar's compilation pipelines independently: only the fully
 * compiled artifacts are guaranteed to be at the same pipeline stage (e.g. regions synthesized
 * by RegionOpenerCloserCompiler/TagToChoiceCompiler only exist post-compile, not at precompile).
 *
 * @template T of object
 */
final class RegionConflictResolver
{
    public function __construct(
        private readonly RegionHasher $hasher = new RegionHasher(),
    ) {}

    /**
     * @param array<string,T> $candidateRegions Regions being considered for merge, keyed by name
     * @param array<string,T> $existingRegions Regions already present in the merge target, keyed by name
     * @return string[] Names from $candidateRegions that are identical to an existing region and can be skipped
     */
    public function resolveExclusions(array $candidateRegions, array $existingRegions, string $targetDescription): array
    {
        $exclude = [];
        foreach ($candidateRegions as $name => $candidateRegion) {
            if (!isset($existingRegions[$name])) {
                continue;
            }

            if ($this->hasher->hash($candidateRegion) === $this->hasher->hash($existingRegions[$name])) {
                $exclude[] = $name;
            } else {
                $candidateDesc = $this->hasher->describe($candidateRegion);
                $existingDesc  = $this->hasher->describe($existingRegions[$name]);
                throw new RuntimeException(
                    "Region '{$name}' conflicts with an existing region of the same name " .
                        "but different structure. Cannot merge into {$targetDescription}.\n\n" .
                        $this->diff($candidateDesc, $existingDesc),
                );
            }
        }
        return $exclude;
    }

    private function diff(string $a, string $b): string
    {
        $linesA = explode("\n", $a);
        $linesB = explode("\n", $b);
        $output = [];

        $maxLines = max(count($linesA), count($linesB));
        $shown    = 0;
        for ($i = 0; $i < $maxLines && $shown < 20; $i++) {
            $lineA = $linesA[$i] ?? '<missing>';
            $lineB = $linesB[$i] ?? '<missing>';
            if ($lineA !== $lineB) {
                $output[] = "line {$i}:";
                $output[] = "  - {$lineA}";
                $output[] = "  + {$lineB}";
                $shown++;
            }
        }

        if ($shown === 0) {
            return "(no line-level differences found — may differ in whitespace or ordering)";
        }

        $remaining = max(0, $maxLines - $i);
        if ($remaining > 0) {
            $output[] = "... ({$remaining} more lines not checked)";
        }

        return implode("\n", $output);
    }
}
