<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition;

use PhpArchitecture\Graph\Edge\EdgeType;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Exception\InvalidTransitionException;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\Identity\TransitionId;
use PhpArchitecture\Technical\Assert;

class Transition implements TransitionInterface
{
    /**
     * @param string[] $tags
     */
    protected function __construct(
        public readonly TransitionId $id,
        public readonly NodeId $from,
        public readonly NodeId $to,
        public readonly ?TransitionCondition $condition,
        public readonly array $tags,
    ) {
        Assert::eachString($this->tags, InvalidTransitionException::class);
    }

    /**
     * @param string[] $tags
     */
    public static function create(
        NodeId $from,
        NodeId $to,
        ?TransitionCondition $condition = null,
        array $tags = [],
    ): static {
        return new static(
            TransitionId::new(),
            $from,
            $to,
            $condition,
            $tags,
        );
    }

    /**
     * @param string[] $tags
     */
    public static function recreate(
        TransitionId $id,
        NodeId $from,
        NodeId $to,
        ?TransitionCondition $condition,
        array $tags,
    ): static {
        return new static(
            $id,
            $from,
            $to,
            $condition,
            $tags,
        );
    }

    public function id(): TransitionId
    {
        return $this->id;
    }

    public function u(): NodeId
    {
        return $this->from;
    }

    public function v(): NodeId
    {
        return $this->to;
    }

    /** @return string[] */
    public function tags(): array
    {
        return $this->tags;
    }

    public function type(): EdgeType
    {
        return EdgeType::Directed;
    }
}
