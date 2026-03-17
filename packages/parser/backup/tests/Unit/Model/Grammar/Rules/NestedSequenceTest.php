<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Parser\Unit\Model\Grammar\Rules;

use InvalidArgumentException;
use PhpArchitecture\Parser\Model\Grammar\Rules\Cardinality;
use PhpArchitecture\Parser\Model\Grammar\Rules\NestedSequence;
use PhpArchitecture\Parser\Model\Grammar\Rules\SequenceNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NestedSequenceTest extends TestCase
{
    #[Test]
    #[DataProvider('provideValidNestedSequencesFromFile')]
    public function fromStringCreatesValidInstanceForAllValidNestedSequences(string $sequence): void
    {
        $nested = NestedSequence::fromString($sequence);
        $this->assertInstanceOf(NestedSequence::class, $nested);
        $this->assertSame($sequence, $nested->toString());
    }

    #[Test]
    public function basicNestedSequence(): void
    {
        $nested = NestedSequence::fromString('(token)');
        $this->assertCount(1, $nested->alternativeSequences);
        $this->assertSame(Cardinality::ExactlyOne, $nested->cardinality);
        $this->assertSame([], $nested->tags);
    }

    #[Test]
    public function nestedSequenceWithTags(): void
    {
        $nested = NestedSequence::fromString('(token)/s');
        $this->assertSame(['s'], $nested->tags);
    }

    #[Test]
    public function nestedSequenceUnion(): void
    {
        $nested = NestedSequence::fromString('(token)|(member)');
        $this->assertCount(2, $nested->alternativeSequences);
    }

    #[Test]
    public function throwsExceptionForBothLookaheadAndLookbehind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NestedSequence::fromString('><(token)');
    }

    public static function provideValidNestedSequencesFromFile(): array
    {
        $filePath = __DIR__ . '/../../../../valid_sequences.txt';
        $content = file_get_contents($filePath);
        $lines = array_filter(
            array_map('trim', explode("\n", $content)),
            fn($line) => $line !== '' && str_contains($line, '(') && !str_contains($line, ' ')
        );

        $data = [];
        foreach ($lines as $line) {
            $data[$line] = [$line];
        }
        return $data;
    }
}
