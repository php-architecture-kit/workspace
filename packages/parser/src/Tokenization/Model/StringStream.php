<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Model;

final class StringStream
{
    private string $buffer = '';
    private int $currentPosition = 0;
    private int $bufferStartPosition = 0;
    private int $totalLength;
    private bool $endReached = false;

    /**
     * @param string|callable():string $source String content or callable that returns string chunks
     * @param int $bufferSize Size of the buffer to keep in memory
     */
    public function __construct(
        private mixed $source,
        public readonly int $bufferSize = 1048576 # 1MB
    ) {
        if (is_string($this->source)) {
            $this->totalLength = strlen($this->source);
            $this->endReached = true;
        } else {
            $this->totalLength = -1;
        }

        $this->loadInitialBuffer();
    }

    public function advance(int $steps = 1): void
    {
        $this->currentPosition += $steps;
    }

    public function position(): int
    {
        return $this->currentPosition;
    }

    public function totalLength(): int
    {
        return $this->totalLength;
    }

    /**
     * Get a chunk of data starting from current position.
     * This method is designed for the Lexer to work with chunks.
     * 
     * @param int $chunkSize Size of chunk to read
     * @return string The chunk data
     */
    public function getChunk(int $chunkSize): string
    {
        if (is_string($this->source)) {
            $available = strlen($this->source) - $this->currentPosition;
            $actualChunkSize = min($chunkSize, $available);

            if ($actualChunkSize <= 0) {
                return '';
            }

            return substr($this->source, $this->currentPosition, $actualChunkSize);
        }

        $this->ensureBufferLoaded($this->currentPosition);

        $relativePosition = $this->currentPosition - $this->bufferStartPosition;
        $availableLength = strlen($this->buffer) - $relativePosition;
        $actualChunkSize = min($chunkSize, $availableLength);

        if ($actualChunkSize <= 0) {
            return '';
        }

        return substr($this->buffer, $relativePosition, $actualChunkSize);
    }

    public function isEof(): bool
    {
        if ($this->totalLength >= 0) {
            return $this->currentPosition >= $this->totalLength;
        }

        $this->ensureBufferLoaded($this->currentPosition);
        return $this->endReached && $this->currentPosition >= $this->bufferStartPosition + strlen($this->buffer);
    }

    private function loadInitialBuffer(): void
    {
        if (is_string($this->source)) {
            $this->buffer = substr($this->source, 0, $this->bufferSize);
        } else {
            $this->loadMoreData();
        }
    }

    private function ensureBufferLoaded(int $position): void
    {
        if (
            $position >= $this->bufferStartPosition &&
            $position < $this->bufferStartPosition + strlen($this->buffer)
        ) {
            return;
        }

        if (is_string($this->source)) {
            if ($this->bufferStartPosition === 0 && strlen($this->buffer) === 0) {
                $this->buffer = substr($this->source, 0, min(strlen($this->source), $this->bufferSize));
            } else if ($position >= $this->bufferStartPosition + strlen($this->buffer)) {
                $this->bufferStartPosition = max(0, $position - (int)($this->bufferSize / 2));
                $this->buffer = substr($this->source, $this->bufferStartPosition, $this->bufferSize);
            }
        } else {
            while (!$this->endReached && $position >= $this->bufferStartPosition + strlen($this->buffer)) {
                $this->loadMoreData();
            }

            if (strlen($this->buffer) > $this->bufferSize * 2) {
                $this->cleanupBuffer($position);
            }
        }
    }

    private function loadMoreData(): void
    {
        if ($this->endReached) {
            return;
        }

        $source = $this->source;
        $newData = $source();

        if ($newData === '' || $newData === false) {
            $this->endReached = true;
            return;
        }

        $this->buffer .= $newData;
    }

    private function cleanupBuffer(int $currentPosition): void
    {
        $keepFrom = max(0, $currentPosition - $this->bufferSize);

        if ($keepFrom > $this->bufferStartPosition) {
            $removeCount = $keepFrom - $this->bufferStartPosition;
            $this->buffer = substr($this->buffer, $removeCount);
            $this->bufferStartPosition = $keepFrom;
        }
    }
}
