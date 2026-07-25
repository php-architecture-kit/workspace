<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\ParsedTree\Context;

/**
 * Newline-driven formatting state for a single node, set by the (future) reformat
 * walk:
 * - breaksLine: this region is laid across multiple lines, so its children
 *   indent one level deeper (counted by ContextStack::indentationLevel()).
 * - beginsLine: a newline precedes this node, so its managed leading
 *   whitespace is indented (`indentUnit × level`) rather than empty.
 */
class FormattingContext
{
    public bool $breaksLine = false;
    public bool $beginsLine = false;
}
