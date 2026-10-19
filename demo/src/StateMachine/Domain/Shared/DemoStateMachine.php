<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Domain\Shared;

use Closure;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\Condition\LabeledCondition;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\Condition\Route;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\Condition\When;
use PhpArchitecture\Demo\StateMachine\Domain\Shared\Step\StepNode;
use PhpArchitecture\StateMachine\Foundation\Config\StateMachineConfig;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\Default\AllPointersStepStrategy;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * A demo workflow built from StepNodes and the package's components.
 *
 * Besides the graph itself it remembers how to draw it: every node of a component
 * (ports, internal passthrough nodes) is shown as one box named after the component.
 *
 * Pointers advance one step per execute() call (AllPointersStepStrategy), so the runner
 * can record the path a pointer took within one request.
 */
abstract class DemoStateMachine extends StateMachine
{
    /** @var array<string,string> node id => display id */
    private array $display = [];

    /** @var array<string,array{label:string,kind:string}> display id => box */
    private array $boxes = [];

    /** @var array<string,list<string>> "{box}>{target node id}" => labels of component outputs */
    private array $outputLabels = [];

    public function __construct(
        ContainerInterface $handlers,
        protected readonly ClockInterface $clock,
        TaskBusInterface $taskBus,
    ) {
        parent::__construct(
            $handlers,
            config: new StateMachineConfig(pointersSelectionStrategy: new AllPointersStepStrategy()),
            taskBus: $taskBus,
        );

        $this->define();
    }

    abstract protected function define(): void;

    /** Places the first pointer and the initial business state of a new Execution. */
    abstract public function start(Execution $execution): void;

    /** @param null|Closure(World): void $effect */
    protected function step(string $name, string $label, ?Closure $effect = null): StepNode
    {
        $node = new StepNode($name, $label, $effect ?? static function (World $world): void {
        });
        $this->addNode($node);
        $this->display[$node->id->toString()] = $name;
        $this->boxes[$name] = ['label' => $label, 'kind' => 'step'];

        return $node;
    }

    protected function then(StepNode $from, StepNode $to): void
    {
        $this->addTransition($from->id, $to->id);
    }

    /** @param Closure(World): bool $predicate */
    protected function when(StepNode $from, StepNode $to, Closure $predicate, string $label): void
    {
        $this->addTransition($from->id, $to->id, new When($predicate, $label));
    }

    /**
     * @param Closure(World): ?string                    $decide
     * @param array<string,array{0:StepNode,1:string}>    $branches branch => [target, label]
     */
    protected function routes(StepNode $from, Closure $decide, array $branches): void
    {
        foreach ($branches as $branch => [$target, $label]) {
            $this->addTransition($from->id, $target->id, new Route($decide, $branch, $label));
        }
    }

    /**
     * Connects the component's ports to steps and adds it to the graph.
     *
     * @param array<string,StepNode>                      $inputs  input port => step leading into the component
     * @param array<string,array{0:StepNode,1:string}>    $outputs output port => [step, label]
     */
    protected function place(Definition $component, string $box, string $label, array $inputs, array $outputs): void
    {
        foreach ($inputs as $port => $node) {
            $component->input->{$port}->attach($node->id);
        }

        foreach ($outputs as $port => [$node, $outputLabel]) {
            $component->output->{$port}->attach($node->id);
            $this->outputLabels["{$box}>{$node->id->toString()}"][] = $outputLabel;
        }

        $before = array_keys($this->getNodes());
        $this->addDefinition($component);

        foreach (array_diff(array_keys($this->getNodes()), $before) as $id) {
            $this->display[(string) $id] = $box;
        }

        $this->boxes[$box] = ['label' => $label, 'kind' => (new ReflectionClass($component))->getShortName()];
    }

    public function nodeName(NodeId $id): string
    {
        return $this->getNode($id)->name();
    }

    /**
     * Replaces node ids inside a name (components key their technical state by node id)
     * with node names, e.g. "<uuid>.expiresAt" -> "state-machine.await.doors.close_button.expiresAt".
     */
    public function readable(string $text): string
    {
        return (string) preg_replace_callback(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/',
            fn (array $match): string => ($this->getNodes()[$match[0]] ?? null)?->name() ?? $match[0],
            $text,
        );
    }

    /** Box (step or component) the node is drawn in. */
    public function displayOf(NodeId $id): string
    {
        return $this->display[$id->toString()] ?? $this->nodeName($id);
    }

    /** @return array<string,array{label:string,kind:string}> */
    public function boxes(): array
    {
        return $this->boxes;
    }

    /**
     * Transitions between boxes; transitions inside a component are hidden.
     *
     * @return list<array{from:string,to:string,label:string}>
     */
    public function connections(): array
    {
        $connections = [];
        foreach ($this->getTransitions() as $edge) {
            $from = $this->displayOf($edge->input);
            $to = $this->displayOf($edge->output);
            if ($from === $to) {
                continue;
            }

            $labels = $edge->condition instanceof LabeledCondition
                ? [$edge->condition->label()]
                : ($this->outputLabels["{$from}>{$edge->output->toString()}"] ?? []);

            $key = "{$from}>{$to}";
            $connections[$key] ??= ['from' => $from, 'to' => $to, 'labels' => []];
            $connections[$key]['labels'] = array_values(array_unique([...$connections[$key]['labels'], ...$labels]));
        }

        return array_values(array_map(
            static fn (array $connection): array => [
                'from' => $connection['from'],
                'to' => $connection['to'],
                'label' => implode(' / ', $connection['labels']),
            ],
            $connections,
        ));
    }
}
