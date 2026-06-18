<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

/**
 * A node whose body is exactly one attribute (Table 2: Token/*, Region/Raw,
 * Region/Structure, Sequence/Raw, Sequence/Structure). The single-attribute
 * invariant is enforced incrementally in later refactor stages.
 */
class LeafNode extends AbstractNode
{
}
