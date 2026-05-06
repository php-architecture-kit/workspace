<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer;

use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;

class Pointer
{
    protected function __construct(
        public readonly ExecutionId $executionId,
        public readonly PointerId $id,
        public readonly ?PointerId $parentId,
        public protected(set) NodeId $nodeId,
        public protected(set) int $currentStep,
    ) {}

    public static function create(
        ExecutionId $executionId,
        NodeId $nodeId,
    ): self {
        return new self(
            $executionId,
            PointerId::new(),
            null,
            $nodeId,
            0,
        );
    }

    public function fork(): Pointer
    {
        return new Pointer(
            $this->executionId,
            PointerId::new(),
            $this->id,
            $this->nodeId,
            $this->currentStep,
        );
    }

    public function step(NodeId $nodeId): void
    {
        $this->nodeId = $nodeId;
        $this->currentStep++;
    }
}
