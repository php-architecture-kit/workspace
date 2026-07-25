<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Service;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Creation\DefaultsDefinition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\NestedSequence;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Sequence\SequenceNode;

/**
 * Attaches structural Defaults onto the sequence nodes a `withDefaults()` builder
 * targets, by selector. A selector matches a node by its anchor name, or — when
 * the node has no anchor — by one of its alternative names (e.g. `comma`, or the
 * raw trivia token `-l`/`-t`/`-` before TriviaSequenceNamingMiddleware renames it).
 */
final class StructuralDefaultsBinder
{
    /**
     * @param array<NestedSequence|SequenceNode> $nodes
     */
    public static function bind(array $nodes, string $selector, DefaultsDefinition $defaults): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof SequenceNode) {
                $matches = $node->anchorName === $selector
                    || ($node->anchorName === null && in_array($selector, $node->alternatives, true));
                if ($matches) {
                    $node->defaults = $defaults;
                }
            } elseif ($node instanceof NestedSequence) {
                foreach ($node->alternativeSequences as $alternative) {
                    self::bind($alternative, $selector, $defaults);
                }
            }
        }
    }
}
