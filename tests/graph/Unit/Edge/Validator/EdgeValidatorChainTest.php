<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Edge\Validator;

use PhpArchitecture\Graph\Edge\DirectedEdge;
use PhpArchitecture\Graph\Edge\DirectedEdgeInterface;
use PhpArchitecture\Graph\Edge\UndirectedEdgeInterface;
use PhpArchitecture\Graph\Edge\Validator\EdgeValidatorChain;
use PhpArchitecture\Graph\Edge\Validator\EdgeValidatorInterface;
use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\Vertex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SpyValidator implements EdgeValidatorInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(private string $name)
    {
    }

    public function validate(DirectedEdgeInterface|UndirectedEdgeInterface $edge, Graph $graph): void
    {
        $this->calls[] = $this->name;
    }
}

class EdgeValidatorChainTest extends TestCase
{
    #[Test]
    public function validateCallsAllValidatorsInOrder(): void
    {
        $u = new Vertex();
        $v = new Vertex();
        $edge = new DirectedEdge($u, $v);
        $graph = new Graph();

        $first = new SpyValidator('first');
        $second = new SpyValidator('second');

        $chain = new EdgeValidatorChain([$first]);
        $chain->addValidator($second);

        $chain->validate($edge, $graph);

        $this->assertSame(['first'], $first->calls);
        $this->assertSame(['second'], $second->calls);
    }
}
