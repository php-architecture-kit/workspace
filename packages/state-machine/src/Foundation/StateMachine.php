<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine;

use PhpArchitecture\Graph\Edge\EdgeInterface;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\StateMachine\Foundation\Config\StateMachineConfig;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Config\Exception\NoTransitionStrategyException;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\InvalidNodeHandlerException;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\NodeNotFoundException;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use Psr\Container\ContainerInterface;
use Throwable;

abstract class StateMachine
{
    protected readonly Graph $graph;

    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly StateMachineConfig $config = new StateMachineConfig(),
        ?Graph $graph = null,
    ) {
        $this->graph = $graph ?? new Graph($this->config->toGraphConfig());
    }

    protected function addNode(NodeInterface $node): static
    {
        $this->graph->vertexStore->addVertex($node);

        return $this;
    }

    public function addTransition(NodeId $from, NodeId $to, ?TransitionCondition $condition = null): static
    {
        $this->graph->edgeStore->addEdge(Transition::create($from, $to, $condition));

        return $this;
    }

    public function execute(Execution $execution): ExecutionStatus
    {
        $plans = $this->config->pointersSelectionStrategy->select($execution->pointers);

        $madeProgress = false;
        foreach ($plans as $plan) {
            $stepBefore = $plan->pointer->currentStep;
            $this->handlePointerOnPath($plan->pointer, $execution, $plan->maxSteps);

            $pointerRemoved = !isset($execution->pointers->pointers[$plan->pointer->id->toString()]);
            if ($pointerRemoved || $plan->pointer->currentStep > $stepBefore) {
                $madeProgress = true;
            }
        }

        if (empty($execution->pointers->pointers)) {
            return ExecutionStatus::Completed;
        }

        return $madeProgress ? ExecutionStatus::Running : ExecutionStatus::Suspended;
    }

    protected function handlePointerOnPath(Pointer $pointer, Execution $execution, int $maxSteps): void
    {
        for ($i = 0; $i < $maxSteps; $i++) {
            if (!isset($execution->pointers->pointers[$pointer->id->toString()])) {
                break;
            }

            $stepBeforeHandling = $pointer->currentStep;
            $result = $this->handlePointerOnNode($pointer, $execution);

            if ($pointer->currentStep === $stepBeforeHandling) {
                break;
            }

            if ($result === NodeHandlerResult::Suspended) {
                break;
            }
        }
    }

    protected function handlePointerOnNode(Pointer $pointer, Execution $execution): NodeHandlerResult
    {
        $node = $this->getNode($pointer->nodeId);
        $handler = $this->container->get($node->handlerClass());

        if (!$handler instanceof NodeHandlerInterface) {
            throw new InvalidNodeHandlerException(
                "Handler for node '{$pointer->nodeId}' must implement NodeHandlerInterface, got " . get_class($handler) . ".",
            );
        }

        $handlerResult = $handler->handle(
            new NodeHandlerContext($execution->id, $node, $pointer, $execution->states),
        );

        if ($handlerResult === NodeHandlerResult::Suspended) {
            return $handlerResult;
        }

        $this->transitionToNextNodes($pointer, $execution);
        return $handlerResult;
    }

    protected function transitionToNextNodes(Pointer $pointer, Execution $execution): TransitionSelectionOutput
    {
        $node = $this->getNode($pointer->nodeId);
        $outgoing = $this->getOutgoingTransitions($node->id());

        $transitionSelection = $node->transitionStrategy()->select($pointer, $execution->states, $outgoing);

        foreach ($this->config->transitionStrategies as $strategy) {
            if ($strategy->supports($transitionSelection)) {
                $strategy->transitionToNextNodes($execution, $pointer, $transitionSelection);
                return $transitionSelection;
            }
        }

        throw new NoTransitionStrategyException(
            "No TransitionStrategy supports the current transition output for node '{$pointer->nodeId}'. Check StateMachineConfig::transitionStrategies.",
        );
    }

    protected function getNode(NodeId $id): NodeInterface
    {
        try {
            return $this->graph->vertexStore->getVertex($id);
        } catch (Throwable $e) {
            throw new NodeNotFoundException("Node '{$id}' not found in the graph.", previous: $e);
        }
    }

    /**
     * @return Transition[]
     */
    protected function getOutgoingTransitions(NodeId $id): array
    {
        return $this->graph->edgeStore->getIncidentEdges(
            $id,
            static fn(EdgeInterface $edge): bool => $edge->u()->equals($id),
        );
    }
}
