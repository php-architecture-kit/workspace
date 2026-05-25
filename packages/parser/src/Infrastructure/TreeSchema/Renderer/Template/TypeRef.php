<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Renderer\Template;

final class TypeRef implements \Stringable
{
    /** @param TypeRef[] $params */
    public function __construct(
        public readonly string $fqcn,
        public readonly array $params = [],
    ) {}

    public function __toString(): string
    {
        return $this->fqcn;
    }

    public function toShortString(): string
    {
        $short = self::shortName($this->fqcn);
        if (empty($this->params)) {
            return $short;
        }
        return $short . '<' . implode('|', array_map(fn(TypeRef $p) => $p->toShortString(), $this->params)) . '>';
    }

    /** @return string[] */
    public function allFqcns(): array
    {
        return array_merge(
            [$this->fqcn],
            ...array_map(fn(TypeRef $p) => $p->allFqcns(), $this->params) ?: [[]],
        );
    }

    public function merge(self $other): self
    {
        $merged = $this->params;
        foreach ($other->params as $incoming) {
            $found = false;
            foreach ($merged as &$existing) {
                if ($existing->fqcn === $incoming->fqcn) {
                    $existing = $existing->merge($incoming);
                    $found    = true;
                    break;
                }
            }
            if (!$found) {
                $merged[] = $incoming;
            }
        }
        return new self($this->fqcn, $merged);
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }
}
