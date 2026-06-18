<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

/**
 * A node whose body is an ordered, grammar-validated slot sequence (Table 2:
 * Sequence/Node). The validity-cursor / content classification / unit machinery
 * (shared with {@see Attribute\SequenceAttribute} via the SequenceCarrier trait)
 * is layered in later refactor stages.
 *
 * Distinct namespace/responsibility from the grammar-definition and matching-layer
 * `SequenceNode` classes.
 */
class SequenceNode extends AbstractNode
{
}
