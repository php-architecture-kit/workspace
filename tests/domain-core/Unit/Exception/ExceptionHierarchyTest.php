<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\DomainCore\Unit\Exception;

use DomainException;
use PhpArchitecture\DomainCore\Exception\DependencyStateException;
use PhpArchitecture\DomainCore\Exception\InsufficientPrivilegeException;
use PhpArchitecture\DomainCore\Exception\InvalidInputException;
use PhpArchitecture\DomainCore\Exception\InvalidStateCausedException;
use PhpArchitecture\DomainCore\Exception\InvalidStateToPerformActionException;
use PhpArchitecture\DomainCore\Exception\LegalRestrictionException;
use PhpArchitecture\DomainCore\Exception\PaymentStatusException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    #[DataProvider('exceptionClassProvider')]
    public function allExceptionsExtendDomainException(string $exceptionClass): void
    {
        $exception = new $exceptionClass('test');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    #[Test]
    #[DataProvider('exceptionClassProvider')]
    public function allExceptionsHaveCorrectMessage(string $exceptionClass): void
    {
        $message = 'Custom error message';
        $exception = new $exceptionClass($message);

        $this->assertSame($message, $exception->getMessage());
    }

    #[Test]
    #[DataProvider('exceptionClassProvider')]
    public function allExceptionsAcceptPreviousException(string $exceptionClass): void
    {
        $previous = new \Exception('Previous error');
        $exception = new $exceptionClass('test', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    #[DataProvider('exceptionClassProvider')]
    public function allExceptionsAcceptCode(string $exceptionClass): void
    {
        $code = 42;
        $exception = new $exceptionClass('test', $code);

        $this->assertSame($code, $exception->getCode());
    }

    #[Test]
    public function invalidInputExceptionForHttp400(): void
    {
        $exception = new InvalidInputException('Invalid email format');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    #[Test]
    public function paymentStatusExceptionForHttp402(): void
    {
        $exception = new PaymentStatusException('Payment required');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    #[Test]
    public function insufficientPrivilegeExceptionForHttp403(): void
    {
        $exception = new InsufficientPrivilegeException('Access denied');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    #[Test]
    public function invalidStateToPerformActionExceptionForHttp409(): void
    {
        $exception = new InvalidStateToPerformActionException('Order already cancelled');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    #[Test]
    public function invalidStateCausedExceptionForHttp422(): void
    {
        $exception = new InvalidStateCausedException('Invalid state transition');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    #[Test]
    public function dependencyStateExceptionForHttp424(): void
    {
        $exception = new DependencyStateException('Dependent resource not found');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    #[Test]
    public function legalRestrictionExceptionForHttp451(): void
    {
        $exception = new LegalRestrictionException('Content restricted in your region');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function exceptionClassProvider(): array
    {
        return [
            'InvalidInputException (400)' => [InvalidInputException::class],
            'PaymentStatusException (402)' => [PaymentStatusException::class],
            'InsufficientPrivilegeException (403)' => [InsufficientPrivilegeException::class],
            'InvalidStateToPerformActionException (409)' => [InvalidStateToPerformActionException::class],
            'InvalidStateCausedException (422)' => [InvalidStateCausedException::class],
            'DependencyStateException (424)' => [DependencyStateException::class],
            'LegalRestrictionException (451)' => [LegalRestrictionException::class],
        ];
    }
}
