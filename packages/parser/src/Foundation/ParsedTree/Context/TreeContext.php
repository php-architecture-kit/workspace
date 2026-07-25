<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\ParsedTree\Context;

use ArrayAccess;

class TreeContext implements ArrayAccess
{
    /**
     * Tree-global indent unit (one level's whitespace). Same string value as
     * Infrastructure\...\Whitespace::CONTEXT_INDENT_UNIT, which writes it; kept here
     * so Foundation does not depend on Infrastructure.
     */
    public const INDENT_UNIT = 'indentUnit';

    /** @var array<string,mixed> */
    public array $context = [];

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->context);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->context[$offset] ?? null;
    }

    public function offsetSet(
        mixed $offset,
        mixed $value
    ): void {
        $this->context[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->context[$offset]);
    }
}
