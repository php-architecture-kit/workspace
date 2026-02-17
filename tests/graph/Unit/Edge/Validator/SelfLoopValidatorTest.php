<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Edge\Validator;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\Exception\SelfLoopException;
use PhpArchitecture\Graph\Edge\Validator\SelfLoopValidator;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SelfLoopValidatorTest extends TestCase
{
    #[Test]
    public function validateThrowsExceptionForSelfLoop(): void
    {
        $vertex = new Vertex();
        $validator = new SelfLoopValidator();

        $this->expectException(SelfLoopException::class);

        $validator->validate(new DirectedEdge($vertex, $vertex), new Graph());
    }
}
