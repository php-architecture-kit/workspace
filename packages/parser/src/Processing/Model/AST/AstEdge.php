<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Model\Ast;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Parser\Processing\Model\Ast\Identity\EdgeId;
use PhpArchitecture\Parser\Processing\Model\Ast\Identity\NodeId;

/**
 * @property EdgeId $id
 * @property NodeId $tail
 * @property NodeId $head
 */
class AstEdge extends DirectedEdge
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        AstNode $parent,
        AstNode $child,
        ?EdgeId $id = null,
        public array $metadata = [],
    ) {
        $this->tail = $parent->id();
        $this->head = $child->id();
        $this->id = $id ?? EdgeId::v4();
    }

    public function id(): EdgeId
    {
        return $this->id;
    }

    public function parent(): NodeId
    {
        return $this->tail;
    }

    public function child(): NodeId
    {
        return $this->head;
    }
}
