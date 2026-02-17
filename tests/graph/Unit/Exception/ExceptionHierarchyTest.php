<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Graph\Unit\Exception;

use PhpArchitecture\Graph\Edge\Exception\CyclicEdgeException;
use PhpArchitecture\Graph\Edge\Exception\EdgeAlreadyExistsException;
use PhpArchitecture\Graph\Edge\Exception\EdgeNotFoundException;
use PhpArchitecture\Graph\Edge\Exception\EdgeStoreException;
use PhpArchitecture\Graph\Edge\Exception\MissingEdgeWeightStoreException;
use PhpArchitecture\Graph\Edge\Exception\MultiEdgeException;
use PhpArchitecture\Graph\Edge\Exception\SelfLoopException;
use PhpArchitecture\Graph\EdgeWeight\Exception\EdgeWeightStoreException;
use PhpArchitecture\Graph\EdgeWeight\Exception\EdgeWeightsAlreadyExistsException;
use PhpArchitecture\Graph\EdgeWeight\Exception\EdgeWeightsNotFoundException;
use PhpArchitecture\Graph\EdgeWeight\Exception\WeightNotFoundException;
use PhpArchitecture\Graph\Exception\GraphException;
use PhpArchitecture\Graph\Exception\GraphGarbageCollectedException;
use PhpArchitecture\Graph\Exception\GraphNotSetException;
use PhpArchitecture\Graph\Vertex\Exception\VertexAlreadyExistsException;
use PhpArchitecture\Graph\Vertex\Exception\VertexNotFoundException;
use PhpArchitecture\Graph\Vertex\Exception\VertexNotInGraphException;
use PhpArchitecture\Graph\Vertex\Exception\VertexStoreException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function graphExceptionExtendsBaseException(): void
    {
        $this->assertInstanceOf(\Exception::class, new GraphException('test'));
    }

    #[Test]
    #[DataProvider('exceptionClassProvider')]
    public function allPackageExceptionsExtendGraphException(string $exceptionClass): void
    {
        $exception = new $exceptionClass('test');

        $this->assertInstanceOf(GraphException::class, $exception);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function exceptionClassProvider(): array
    {
        return [
            'GraphNotSetException' => [GraphNotSetException::class],
            'GraphGarbageCollectedException' => [GraphGarbageCollectedException::class],
            'EdgeStoreException' => [EdgeStoreException::class],
            'EdgeAlreadyExistsException' => [EdgeAlreadyExistsException::class],
            'EdgeNotFoundException' => [EdgeNotFoundException::class],
            'MissingEdgeWeightStoreException' => [MissingEdgeWeightStoreException::class],
            'MultiEdgeException' => [MultiEdgeException::class],
            'SelfLoopException' => [SelfLoopException::class],
            'CyclicEdgeException' => [CyclicEdgeException::class],
            'VertexStoreException' => [VertexStoreException::class],
            'VertexAlreadyExistsException' => [VertexAlreadyExistsException::class],
            'VertexNotFoundException' => [VertexNotFoundException::class],
            'VertexNotInGraphException' => [VertexNotInGraphException::class],
            'EdgeWeightStoreException' => [EdgeWeightStoreException::class],
            'EdgeWeightsAlreadyExistsException' => [EdgeWeightsAlreadyExistsException::class],
            'EdgeWeightsNotFoundException' => [EdgeWeightsNotFoundException::class],
            'WeightNotFoundException' => [WeightNotFoundException::class],
        ];
    }
}
