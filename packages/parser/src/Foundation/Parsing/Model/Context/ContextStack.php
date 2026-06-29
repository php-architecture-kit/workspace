<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Context;

use ArrayObject;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Defaults;

/**
 * Each node has its own context stack which contains references to ascendant contexts
 */
class ContextStack
{
    public const STYLE = 'style';

    /**
     * Tree-global indent unit (one level's whitespace). Same string value as
     * Infrastructure\...\Whitespace::CONTEXT_INDENT_UNIT, which writes it; kept here
     * so Foundation does not depend on Infrastructure.
     */
    public const INDENT_UNIT = 'indentUnit';

    /**
     * @param NodeContext[] $stack
     * @param ArrayObject<string,mixed> $treeContext
     */
    public function __construct(
        public readonly array $stack = [],
        public ArrayObject $treeContext = new ArrayObject([self::STYLE => Defaults::DEFAULT_STYLE]),
    ) {}

    public function push(NodeContext $context): static
    {
        return new static([...$this->stack, $context], $this->treeContext);
    }

    /**
     * The NodeContext of the node this stack belongs to (top of the ancestry path).
     */
    public function current(): ?NodeContext
    {
        if ($this->stack === []) {
            return null;
        }

        return $this->stack[array_key_last($this->stack)];
    }

    /**
     * Newline-driven indentation level: the number of *broken* strict ancestors.
     * Excludes the current node itself — a node's own line break indents its
     * children, not the node. Structural nesting alone never contributes; only
     * regions the reformat walk marked breaksLine do.
     *
     * @see docs/refactor/20-context-and-indentation.md
     */
    public function indentationLevel(): int
    {
        $level = 0;
        $last = array_key_last($this->stack);
        foreach ($this->stack as $key => $context) {
            if ($key === $last) {
                continue;
            }
            if ($context->breaksLine) {
                $level++;
            }
        }

        return $level;
    }

    /**
     * One indentation level's whitespace string (e.g. "    " or "\t"), or "" when
     * indentation is not active for the current style.
     */
    public function indentUnit(): string
    {
        $unit = $this->treeContext[self::INDENT_UNIT] ?? '';

        return is_string($unit) ? $unit : '';
    }

    /**
     * Whether the current node begins a line (a newline precedes it), so its
     * managed leading whitespace should be indented rather than empty.
     */
    public function beginsLine(): bool
    {
        return $this->current()?->beginsLine ?? false;
    }
}
