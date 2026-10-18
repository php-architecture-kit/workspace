<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Uuid\Application;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PhpArchitecture\Clock\FrozenClock;
use PhpArchitecture\Demo\Uuid\Application\Exception\InvalidFormInputException;
use PhpArchitecture\Uuid\Foundation\Exception\UuidException;
use PhpArchitecture\Uuid\Foundation\Provider\UuidProviderRegistry;
use PhpArchitecture\Uuid\Foundation\Uuid;
use Psr\Clock\ClockInterface;
use ReflectionClass;

/**
 * Calls Uuid factory methods with values coming from the demo forms.
 *
 * Only converts form strings to argument types; validation of the values is left to the
 * uuid package, so its exceptions reach the UI unchanged.
 */
final class UuidPlayground
{
    public const METHODS = ['new', 'v1', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8', 'nil', 'max'];

    public const NAMESPACES = [
        'DNS' => Uuid::NAMESPACE_DNS,
        'URL' => Uuid::NAMESPACE_URL,
        'OID' => Uuid::NAMESPACE_OID,
        'X500' => Uuid::NAMESPACE_X500,
    ];

    /**
     * @param array<string, mixed> $input
     *
     * @throws InvalidFormInputException
     * @throws UuidException
     */
    public function generate(string $method, array $input): UuidDetails
    {
        $uuid = match ($method) {
            'new' => Uuid::new(),
            'v1' => Uuid::v1($this->clock($input), $this->clockSequence($input), $this->text($input, 'nodeIdentifier')),
            'v3' => Uuid::v3($this->namespace($input), (string) ($input['name'] ?? '')),
            'v4' => Uuid::v4(),
            'v5' => Uuid::v5($this->namespace($input), (string) ($input['name'] ?? '')),
            'v6' => Uuid::v6($this->clock($input), $this->clockSequence($input), $this->text($input, 'nodeIdentifier')),
            'v7' => Uuid::v7($this->clock($input)),
            'v8' => Uuid::v8($this->customData($input)),
            'nil' => Uuid::nil(),
            'max' => Uuid::max(),
            default => throw new InvalidFormInputException('method', sprintf('Unknown method "%s".', $method)),
        };

        return UuidDetails::of($uuid, sprintf('Uuid::%s()', $method), $this->providerFor($method));
    }

    /**
     * @throws UuidException
     */
    public function inspect(string $value): UuidDetails
    {
        return UuidDetails::of(Uuid::fromString($value), 'Uuid::fromString()', $this->providerFor('validate'));
    }

    private function providerFor(string $method): ?string
    {
        $providerMethod = match ($method) {
            'new' => 'v4',
            'nil', 'max' => null,
            default => $method,
        };

        if ($providerMethod === null) {
            return null;
        }

        return (new ReflectionClass(UuidProviderRegistry::getBestProviderForMethod($providerMethod)))->getShortName();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function text(array $input, string $field): ?string
    {
        $value = trim((string) ($input[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function clock(array $input): ?ClockInterface
    {
        $time = $this->text($input, 'time');

        if ($time === null) {
            return null;
        }

        try {
            return FrozenClock::at(new DateTimeImmutable($time, new DateTimeZone('UTC')));
        } catch (Exception) {
            throw new InvalidFormInputException('time', sprintf('"%s" is not a valid date and time.', $time));
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    private function clockSequence(array $input): ?int
    {
        $value = $this->text($input, 'clockSequence');

        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidFormInputException('clockSequence', sprintf('"%s" is not an integer.', $value));
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function namespace(array $input): Uuid
    {
        return Uuid::fromString((string) ($input['namespace'] ?? ''));
    }

    /**
     * The form takes hex, Uuid::v8() takes raw bytes.
     *
     * @param array<string, mixed> $input
     */
    private function customData(array $input): string
    {
        $hex = $this->text($input, 'customData') ?? '';

        if (strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            throw new InvalidFormInputException('customData', 'Custom data must be a hexadecimal string with an even number of characters.');
        }

        return (string) hex2bin($hex);
    }
}
