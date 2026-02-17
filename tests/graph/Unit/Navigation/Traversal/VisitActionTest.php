<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Navigation\Traversal;

use PhpArchitecture\Graph\Navigation\Traversal\VisitAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VisitActionTest extends TestCase
{
    #[Test]
    public function casesExposeExpectedActionsInOrder(): void
    {
        $cases = array_map(
            static fn(VisitAction $action): string => $action->name,
            VisitAction::cases(),
        );

        $this->assertSame(['Continue', 'StopImmediately', 'StopAtCurrentEntity'], $cases);
    }
}
