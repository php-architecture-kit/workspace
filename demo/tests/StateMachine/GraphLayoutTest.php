<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Tests\StateMachine;

use PhpArchitecture\Demo\StateMachine\Application\ScenarioRunner;
use PhpArchitecture\Demo\StateMachine\Presentation\View\GraphLayout;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\DeferredTaskBus;
use PhpArchitecture\Demo\StateMachine\Application\SimulationClock;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GraphLayoutTest extends KernelTestCase
{
    public function testEveryBoxHasItsOwnCell(): void
    {
        $runner = self::getContainer()->get(ScenarioRunner::class);
        $handlers = $this->createStub(ContainerInterface::class);
        $layout = new GraphLayout();

        foreach ($runner->scenarios() as $id => $scenario) {
            $machine = $scenario->machine($handlers, new SimulationClock(new \DateTimeImmutable()), new DeferredTaskBus());
            $positions = $layout->of($id);

            self::assertSame([], array_values(array_diff(array_keys($machine->boxes()), array_keys($positions))), "{$id}: boxes without a position");
            self::assertSame([], array_values(array_diff(array_keys($positions), array_keys($machine->boxes()))), "{$id}: positions of unknown boxes");

            $cells = array_map(static fn (array $position): string => implode(',', $position), $positions);
            self::assertSame(array_values(array_unique($cells)), array_values($cells), "{$id}: two boxes share a cell");
        }
    }
}
