<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Unit\Foundation\Tokenization\Model;

use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StringStreamTest extends TestCase
{
    #[Test]
    public function createsStreamFromString(): void
    {
        $content = 'Hello World';
        $stream = new StringStream($content);

        $this->assertSame(0, $stream->position());
        $this->assertSame(11, $stream->totalLength());
        $this->assertFalse($stream->isEof());
    }

    #[Test]
    public function createsStreamFromCallable(): void
    {
        $chunks = ['Hello', ' ', 'World'];
        $index = 0;
        $callable = function () use (&$chunks, &$index): string {
            if ($index >= count($chunks)) {
                return '';
            }
            return $chunks[$index++];
        };

        $stream = new StringStream($callable);

        $this->assertSame(0, $stream->position());
        $this->assertSame(-1, $stream->totalLength());
        $this->assertFalse($stream->isEof());
    }

    #[Test]
    public function advanceMovesPositionForward(): void
    {
        $stream = new StringStream('Hello World');

        $stream->advance(5);
        $this->assertSame(5, $stream->position());

        $stream->advance(3);
        $this->assertSame(8, $stream->position());

        $stream->advance();
        $this->assertSame(9, $stream->position());
    }

    #[Test]
    public function getChunkReturnsCorrectDataFromStringSource(): void
    {
        $stream = new StringStream('Hello World');

        $chunk = $stream->getChunk(5);
        $this->assertSame('Hello', $chunk);

        $stream->advance(6);
        $chunk = $stream->getChunk(5);
        $this->assertSame('World', $chunk);
    }

    #[Test]
    public function getChunkReturnsEmptyStringAtEof(): void
    {
        $stream = new StringStream('Hello');
        $stream->advance(5);

        $chunk = $stream->getChunk(10);
        $this->assertSame('', $chunk);
    }

    #[Test]
    public function getChunkReturnsRemainingContentWhenRequestedSizeExceedsAvailable(): void
    {
        $stream = new StringStream('Hello World');
        $stream->advance(6);

        $chunk = $stream->getChunk(100);
        $this->assertSame('World', $chunk);
    }

    #[Test]
    public function isEofReturnsTrueWhenPositionAtEnd(): void
    {
        $stream = new StringStream('Hello');

        $this->assertFalse($stream->isEof());

        $stream->advance(5);
        $this->assertTrue($stream->isEof());
    }

    #[Test]
    public function isEofReturnsTrueWhenPositionBeyondEnd(): void
    {
        $stream = new StringStream('Hello');
        $stream->advance(10);

        $this->assertTrue($stream->isEof());
    }

    #[Test]
    public function iteratingThroughEntireStringPreservesAllContent(): void
    {
        $original = 'The quick brown fox jumps over the lazy dog';
        $stream = new StringStream($original);

        $collected = '';
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk(5);
            $collected .= $chunk;
            $stream->advance(strlen($chunk));
        }

        $this->assertSame($original, $collected);
    }

    #[Test]
    public function iteratingWithVariableChunkSizesPreservesContent(): void
    {
        $original = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $stream = new StringStream($original);

        $chunkSizes = [3, 7, 2, 10, 5, 1, 100];
        $collected = '';

        foreach ($chunkSizes as $size) {
            if ($stream->isEof()) {
                break;
            }
            $chunk = $stream->getChunk($size);
            $collected .= $chunk;
            $stream->advance(strlen($chunk));
        }

        $this->assertSame($original, $collected);
    }

    #[Test]
    public function callableSourceWithSingleChunkPreservesContent(): void
    {
        $content = 'Single chunk content';
        $callable = function () use (&$content): string {
            $result = $content;
            $content = '';
            return $result;
        };

        $stream = new StringStream($callable);

        $collected = '';
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk(5);
            $collected .= $chunk;
            $stream->advance(strlen($chunk));
        }

        $this->assertSame('Single chunk content', $collected);
    }

    #[Test]
    public function callableSourceWithMultipleChunksPreservesContent(): void
    {
        $chunks = ['First', 'Second', 'Third'];
        $index = 0;
        $callable = function () use (&$chunks, &$index): string {
            if ($index >= count($chunks)) {
                return '';
            }
            return $chunks[$index++];
        };

        $stream = new StringStream($callable);

        $collected = '';
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk(3);
            $collected .= $chunk;
            $stream->advance(strlen($chunk));
        }

        $this->assertSame('FirstSecondThird', $collected);
    }

    #[Test]
    public function callableSourceReturningFalseMarksEnd(): void
    {
        $called = false;
        $callable = function () use (&$called) {
            if ($called) {
                return false;
            }
            $called = true;
            return 'Data';
        };

        $stream = new StringStream($callable);

        $chunk = $stream->getChunk(10);
        $this->assertSame('Data', $chunk);

        $stream->advance(4);
        $this->assertTrue($stream->isEof());
    }

    #[Test]
    public function largeContentWithSmallBufferPreservesAllData(): void
    {
        $original = str_repeat('ABCDEFGHIJ', 1000);
        $stream = new StringStream($original, bufferSize: 100);

        $collected = '';
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk(10);
            $collected .= $chunk;
            $stream->advance(strlen($chunk));
        }

        $this->assertSame($original, $collected);
        $this->assertSame(strlen($original), strlen($collected));
    }

    #[Test]
    public function randomAccessPatternWithStringSource(): void
    {
        $content = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $stream = new StringStream($content);

        $stream->advance(10);
        $this->assertSame('KLMNO', $stream->getChunk(5));

        $stream->advance(5);
        $this->assertSame('PQRST', $stream->getChunk(5));

        $stream->advance(5);
        $this->assertSame('UVWXYZ', $stream->getChunk(10));
    }

    #[Test]
    public function emptyStringSourceMarksEof(): void
    {
        $stream = new StringStream('');

        $this->assertTrue($stream->isEof());
        $this->assertSame(0, $stream->totalLength());
        $this->assertSame('', $stream->getChunk(10));
    }

    #[Test]
    public function emptyCallableSourceMarksEof(): void
    {
        $callable = fn() => '';
        $stream = new StringStream($callable);

        $this->assertTrue($stream->isEof());
        $this->assertSame('', $stream->getChunk(10));
    }

    #[Test]
    public function singleCharacterIterationPreservesContent(): void
    {
        $original = 'ABC';
        $stream = new StringStream($original);

        $collected = '';
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk(1);
            $collected .= $chunk;
            $stream->advance(1);
        }

        $this->assertSame($original, $collected);
    }

    #[Test]
    public function noDataLossWithExactBufferSizeChunks(): void
    {
        $bufferSize = 50;
        $original = str_repeat('X', $bufferSize * 3);
        $stream = new StringStream($original, bufferSize: $bufferSize);

        $collected = '';
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk($bufferSize);
            $collected .= $chunk;
            $stream->advance(strlen($chunk));
        }

        $this->assertSame($original, $collected);
    }

    #[Test]
    public function callableWithVaryingChunkSizesPreservesContent(): void
    {
        $chunks = ['A', 'BB', 'CCC', 'DDDD', 'EEEEE'];
        $index = 0;
        $callable = function () use (&$chunks, &$index): string {
            if ($index >= count($chunks)) {
                return '';
            }
            return $chunks[$index++];
        };

        $stream = new StringStream($callable, bufferSize: 10);

        $collected = '';
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk(2);
            $collected .= $chunk;
            $stream->advance(strlen($chunk));
        }

        $this->assertSame('ABBCCCDDDDEEEEE', $collected);
        $this->assertSame(15, strlen($collected));
    }

    #[Test]
    public function positionTrackingDuringIteration(): void
    {
        $stream = new StringStream('0123456789');

        $this->assertSame(0, $stream->position());

        $stream->getChunk(3);
        $stream->advance(3);
        $this->assertSame(3, $stream->position());

        $stream->getChunk(5);
        $stream->advance(5);
        $this->assertSame(8, $stream->position());

        $stream->getChunk(2);
        $stream->advance(2);
        $this->assertSame(10, $stream->position());
    }

    #[Test]
    public function getChunkWithoutAdvanceReturnsSameData(): void
    {
        $stream = new StringStream('Hello World');

        $chunk1 = $stream->getChunk(5);
        $chunk2 = $stream->getChunk(5);

        $this->assertSame('Hello', $chunk1);
        $this->assertSame('Hello', $chunk2);
        $this->assertSame(0, $stream->position());
    }

    #[Test]
    public function advanceWithoutGetChunkMovesPosition(): void
    {
        $stream = new StringStream('ABCDEFGH');

        $stream->advance(3);
        $chunk = $stream->getChunk(3);

        $this->assertSame('DEF', $chunk);
    }

    #[Test]
    public function unicodeContentPreservation(): void
    {
        $original = 'Zażółć gęślą jaźń 🚀 ąćęłńóśźż';
        $stream = new StringStream($original);

        $collected = '';
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk(10);
            $collected .= $chunk;
            $stream->advance(strlen($chunk));
        }

        $this->assertSame($original, $collected);
    }

    #[Test]
    public function largeCallableStreamWithBufferCleanup(): void
    {
        $chunkCount = 100;
        $chunkSize = 1000;
        $index = 0;

        $callable = function () use (&$index, $chunkCount, $chunkSize): string {
            if ($index >= $chunkCount) {
                return '';
            }
            $index++;
            return str_repeat(chr(65 + ($index % 26)), $chunkSize);
        };

        $stream = new StringStream($callable, bufferSize: 5000);

        $totalRead = 0;
        while (!$stream->isEof()) {
            $chunk = $stream->getChunk(500);
            $totalRead += strlen($chunk);
            $stream->advance(strlen($chunk));
        }

        $this->assertSame($chunkCount * $chunkSize, $totalRead);
    }

    #[Test]
    public function zeroChunkSizeReturnsEmptyString(): void
    {
        $stream = new StringStream('Hello World');

        $chunk = $stream->getChunk(0);
        $this->assertSame('', $chunk);
    }

    #[Test]
    public function negativeChunkSizeReturnsEmptyString(): void
    {
        $stream = new StringStream('Hello World');

        $chunk = $stream->getChunk(-5);
        $this->assertSame('', $chunk);
    }

    #[Test]
    public function bufferSizeIsAccessible(): void
    {
        $stream = new StringStream('test', bufferSize: 2048);

        $this->assertSame(2048, $stream->bufferSize);
    }

    #[Test]
    public function defaultBufferSizeIs1MB(): void
    {
        $stream = new StringStream('test');

        $this->assertSame(1048576, $stream->bufferSize);
    }
}
