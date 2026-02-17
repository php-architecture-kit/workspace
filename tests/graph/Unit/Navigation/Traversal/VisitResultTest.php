<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Navigation\Traversal;

use PhpArchitecture\Graph\Navigation\Traversal\VisitAction;
use PhpArchitecture\Graph\Navigation\Traversal\VisitResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VisitResultTest extends TestCase
{
    #[Test]
    public function constructorStoresAction(): void
    {
        $result = new VisitResult(VisitAction::StopAtCurrentEntity);

        $this->assertSame(VisitAction::StopAtCurrentEntity, $result->action);
    }
}
