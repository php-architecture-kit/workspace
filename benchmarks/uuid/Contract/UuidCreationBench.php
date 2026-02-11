<?php

declare(strict_types=1);

namespace Benchmarks\PhpArchitecture\Uuid\Contract;

use PhpArchitecture\Uuid\Uuid;
use PhpBench\Attributes as Bench;

#[Bench\Warmup(1)]
#[Bench\Revs(1000)]
#[Bench\Iterations(10)]
abstract class UuidCreationBench
{
    protected const VALID_UUID = 'df516cba-fb13-4f45-8335-00252f1b87e2';
    protected const INVALID_UUID = 'df516cba-fb13-4f45-8335-00252f1b87g2'; // contains invalid character 'g'
    protected const VALID_BINARY = "\xdf\x51\x6c\xba\xfb\x13\x4f\x45\x83\x35\x00\x25\x2f\x1b\x87\xe2"; // 16 bytes
    protected const INVALID_BINARY = "\xdf\x51\x6c\xba\xfb\x13\x4f\x45\x83\x35\x00\x25\x2f\x1b\x87"; // 15 bytes (invalid)

    protected const NAMESPACE = Uuid::NAMESPACE_URL;
    protected const NAME = 'https://example.com';

    abstract public function benchfromStringWithoutValidation(array $params = []): string;

    abstract public function benchfromStringWithValidation(array $params = []): string;

    abstract public function benchfromStringWithValidationException(array $params = []): string;

    abstract public function benchfromBinaryWithoutValidation(array $params = []): string;

    abstract public function benchfromBinaryWithValidation(array $params = []): string;

    abstract public function benchfromBinaryWithValidationException(array $params = []): string;

    abstract public function benchUuidV1CreationWithoutParameters(array $params = []): string;
    
    abstract public function benchUuidV1CreationWithRFC9562Parameters(array $params = []): string;

    abstract public function benchUuidV3Creation(array $params = []): string;

    abstract public function benchUuidV4Creation(array $params = []): string;
    
    abstract public function benchUuidV5Creation(array $params = []): string;
    
    abstract public function benchUuidV6CreationWithoutParameters(array $params = []): string;
    
    abstract public function benchUuidV6CreationWithRFC9562Parameters(array $params = []): string;
    
    abstract public function benchUuidV7Creation(array $params = []): string;
    
    abstract public function benchUuidV8Creation(array $params = []): string;
}
