<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\LazyOperators\Application;

use PhpArchitecture\Demo\LazyOperators\Application\Exception\MissingInputException;
use PhpArchitecture\Demo\LazyOperators\Application\Exception\PlaygroundFailure;
use PhpArchitecture\LazyOperators\Application\Builder\Exception\InvalidExpressionDefinitionException;
use PhpArchitecture\LazyOperators\Application\Builder\ExpressionBuilder;
use Throwable;

/**
 * Builds a definition with the lazy-operators ExpressionBuilder, binds its ports and invokes it.
 * Inputs are definitions too (usually a single literal node), built by the same builder.
 */
final class ExpressionPlayground
{
    public function __construct(
        private readonly ExpressionBuilder $builder,
    ) {
    }

    /**
     * @param array<mixed> $definition
     * @param array<mixed> $inputs input name => definition
     *
     * @throws PlaygroundFailure
     */
    public function evaluate(array $definition, array $inputs): EvaluationResult
    {
        try {
            $tree = $this->builder->build($definition);
        } catch (InvalidExpressionDefinitionException $exception) {
            throw new PlaygroundFailure(PlaygroundFailure::STAGE_DEFINITION, $exception);
        }

        foreach ($tree->inputNames() as $name) {
            if (!is_array($inputs[$name] ?? null)) {
                throw new PlaygroundFailure(PlaygroundFailure::STAGE_INPUT, new MissingInputException($name), $name);
            }

            try {
                $tree->bind($name, $this->builder->build($inputs[$name]));
            } catch (InvalidExpressionDefinitionException $exception) {
                throw new PlaygroundFailure(PlaygroundFailure::STAGE_INPUT, $exception, $name);
            }
        }

        try {
            $value = $tree();
        } catch (Throwable $exception) {
            throw new PlaygroundFailure(PlaygroundFailure::STAGE_EVALUATION, $exception);
        }

        return EvaluationResult::of($value, $tree->inputNames());
    }
}
