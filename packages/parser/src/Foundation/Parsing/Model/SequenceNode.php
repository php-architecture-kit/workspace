<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeAttributeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Sequence\SequenceCarrier;

/**
 * A node whose body is an ordered, grammar-validated slot sequence (Table 2:
 * Sequence/Node). Shares the validity-cursor / content-classification / unit
 * machinery with {@see Attribute\SequenceAttribute} via {@see SequenceCarrier}
 * (composition, since an attribute cannot inherit a node base).
 *
 * The carrier's cursor-advancing append is aliased to carrierAppend and is unused on
 * the parse path: addAttribute keeps AbstractNode's plain append (the matcher already
 * produces a valid, ordered slot list, so parsing stays byte-identical). The unit
 * API (withValidSequence/addUnit/removeUnit/getUnit*) is the edit/read surface.
 *
 * Distinct namespace/responsibility from the grammar-definition and matching-layer
 * `SequenceNode` classes.
 */
class SequenceNode extends AbstractNode
{
    use SequenceCarrier {
        addAttribute as private carrierAppend;
    }

    public function addAttribute(NodeAttributeInterface $attribute, Placement $placement = Placement::After, int $offset = -1): self
    {
        return parent::addAttribute($attribute, $placement, $offset);
    }

    protected function attributesOwner(): ?NodeInterface
    {
        return $this;
    }
}
