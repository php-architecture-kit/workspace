<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\AST\Model;

use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\Parser\Foundation\AST\Model\Identity\NodeId;

/**
 * @property NodeId $id
 */
class AstNode extends Vertex
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        ?NodeId $id = null,
        public array $metadata = [],
    ) {
        $this->id = $id ?? NodeId::v4();
    }

    public function id(): NodeId
    {
        return $this->id;
    }
}
