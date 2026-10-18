<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\LazyOperators\Application\Exception;

use RuntimeException;
use Throwable;

/**
 * Tells which stage of building and running a definition failed, and keeps the exception the
 * lazy-operators package (or PHP) threw there as the cause.
 */
final class PlaygroundFailure extends RuntimeException
{
    public const STAGE_DEFINITION = 'definition';
    public const STAGE_INPUT = 'input';
    public const STAGE_EVALUATION = 'evaluation';

    public function __construct(
        public readonly string $stage,
        public readonly Throwable $cause,
        public readonly ?string $input = null,
    ) {
        parent::__construct($cause->getMessage(), 0, $cause);
    }
}
