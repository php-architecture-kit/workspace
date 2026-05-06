<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node;

use PhpArchitecture\Graph\Vertex\VertexInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;

interface NodeInterface extends VertexInterface
{
    public function id(): NodeId;

    /** @return class-string */
    public function handlerClass(): string;

    /** @return string[] */
    public function tags(): array;

    public function transitionStrategy(): TransitionSelectionStrategy;
}
