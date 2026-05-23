<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ParallelChunkedReader
{
    public const BUFFER_SIZE = 1_048_576;
    public const ERR_FILE_CLOSED = 'file already closed';
    public const ERR_INVALID_SEEK = 'invalid seek position';
    public const ERR_UNKNOWN_SIZE = "parallel chunked reader: can't use multiple threads for unknown sized object";

    private int $offset = 0;
    private int $endStream = 0;
    private int $chunkSize;
    private bool $closed = false;

    /**
     * @var list<array{offset: int, size: int, position: int, bytes: string, reader: object}>
     */
    private array $streams = [];

    public function __construct(
        private readonly MemoryProvider $provider,
        private readonly string $path,
        int $chunkSize,
        private readonly int $nstreams,
    ) {
        $this->provider->info($this->path);
        $this->chunkSize = self::roundedChunkSize($chunkSize);
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        $bytes = '';
        while (strlen($bytes) < $length) {
            $this->openStreams();
            if ($this->streams === []) {
                break;
            }

            $remaining = $length - strlen($bytes);
            $chunk = $this->readCurrentStream($remaining);
            if ($chunk !== '') {
                $bytes .= $chunk;
                $this->offset += strlen($chunk);
            }

            if ($this->streamEof($this->streams[0])) {
                $this->popStream();
                continue;
            }

            if ($chunk === '') {
                break;
            }
        }

        return $bytes;
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        $size = $this->size();
        $currentOffset = $this->offset;
        if ($whence === SEEK_SET) {
            $currentOffset = 0;
        } elseif ($whence === SEEK_END) {
            $currentOffset = $size;
        }

        $newOffset = $currentOffset + $offset;
        if ($newOffset < 0 || $newOffset >= $size) {
            throw new \RuntimeException(self::ERR_INVALID_SEEK);
        }

        if ($newOffset === $this->offset) {
            return $this->offset;
        }

        $this->offset = $newOffset;
        while ($this->streams !== []) {
            $stream = $this->streams[0];
            if ($newOffset < $stream['offset'] + $stream['size']) {
                break;
            }
            $this->discardStream();
        }

        if ($this->streams === []) {
            $this->endStream = $this->offset;

            return $this->offset;
        }

        if ($newOffset < $this->streams[0]['offset']) {
            $this->discardStreams();
            $this->endStream = $this->offset;

            return $this->offset;
        }

        $this->streams[0]['position'] = $newOffset - $this->streams[0]['offset'];

        return $this->offset;
    }

    public function rangeSeek(int $offset, int $whence = SEEK_SET, int $length = -1): int
    {
        return $this->seek($offset, $whence);
    }

    public function open(): self
    {
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        $this->openStreams();

        return $this;
    }

    public function close(): void
    {
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        $this->closed = true;
        $this->popStreams();
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }

    private static function roundedChunkSize(int $chunkSize): int
    {
        if ($chunkSize <= 0) {
            return self::BUFFER_SIZE;
        }

        $rounded = self::BUFFER_SIZE * intdiv($chunkSize, self::BUFFER_SIZE);
        if ($rounded < $chunkSize) {
            $rounded += self::BUFFER_SIZE;
        }

        return max(self::BUFFER_SIZE, $rounded);
    }

    private function openStreams(): void
    {
        $size = $this->size();
        if ($this->endStream >= $size) {
            return;
        }

        $wantedStreams = max(1, $this->nstreams);
        for ($i = count($this->streams); $i < $wantedStreams; $i++) {
            $chunkSize = min($this->chunkSize, $size - $this->endStream);
            if ($chunkSize <= 0) {
                break;
            }

            try {
                $this->streams[] = $this->newStream($this->endStream, $chunkSize);
            } catch (\Throwable $throwable) {
                $this->closeOpenStreamsPreserving();

                throw $throwable;
            }
            $this->endStream += $chunkSize;
            if ($this->endStream >= $size) {
                break;
            }
        }
    }

    /**
     * @return array{offset: int, size: int, position: int, bytes: string, reader: object}
     */
    private function newStream(int $offset, int $size): array
    {
        try {
            $reader = $this->provider->openReader($this->path, $offset, $size);
        } catch (\Throwable $throwable) {
            throw new \RuntimeException(
                sprintf('parallel chunked reader: failed to open stream at %d size %d: %s', $offset, $size, $throwable->getMessage()),
                0,
                $throwable,
            );
        }

        $bytes = '';
        try {
            while (strlen($bytes) < $size) {
                $chunk = $reader->read($size - strlen($bytes));
                if ($chunk === '') {
                    break;
                }
                $bytes .= $chunk;
            }
        } catch (\Throwable $throwable) {
            $this->closeFailedStreamReader($reader);

            throw new \RuntimeException(
                sprintf('parallel chunked reader: failed to read stream at %d size %d: %s', $offset, $size, $throwable->getMessage()),
                0,
                $throwable,
            );
        }

        return [
            'offset' => $offset,
            'size' => $size,
            'position' => 0,
            'bytes' => $bytes,
            'reader' => $reader,
        ];
    }

    /**
     * @param array{offset: int, size: int, position: int, bytes: string, reader: object} $stream
     */
    private function streamEof(array $stream): bool
    {
        return $stream['position'] >= $stream['size'];
    }

    private function readCurrentStream(int $length): string
    {
        $available = $this->streams[0]['size'] - $this->streams[0]['position'];
        if ($available <= 0) {
            return '';
        }

        $chunk = substr($this->streams[0]['bytes'], $this->streams[0]['position'], min($length, $available));
        $this->streams[0]['position'] += strlen($chunk);

        return $chunk;
    }

    private function popStream(): void
    {
        if ($this->streams === []) {
            return;
        }

        $stream = array_shift($this->streams);
        if (method_exists($stream['reader'], 'close')) {
            try {
                $stream['reader']->close();
            } catch (\Throwable $throwable) {
                throw new \RuntimeException(
                    sprintf('parallel chunked reader: failed to read stream at %d size %d: %s', $stream['offset'], $stream['size'], $throwable->getMessage()),
                    0,
                    $throwable,
                );
            }
        }
    }

    private function popStreams(): void
    {
        $error = null;
        while ($this->streams !== []) {
            try {
                $this->popStream();
            } catch (\Throwable $throwable) {
                $error ??= $throwable;
            }
        }

        if ($error !== null) {
            throw $error;
        }
    }

    private function discardStream(): void
    {
        try {
            $this->popStream();
        } catch (\Throwable) {
        }
    }

    private function discardStreams(): void
    {
        try {
            $this->popStreams();
        } catch (\Throwable) {
        }
    }

    private function closeOpenStreamsPreserving(): void
    {
        try {
            $this->popStreams();
        } catch (\Throwable) {
        }
    }

    private function closeFailedStreamReader(object $reader): void
    {
        if (!method_exists($reader, 'close')) {
            return;
        }

        try {
            $reader->close();
        } catch (\Throwable) {
        }
    }

    private function size(): int
    {
        $size = $this->provider->info($this->path)->size;
        if ($size < 0) {
            throw new \RuntimeException(self::ERR_UNKNOWN_SIZE . sprintf(' "%s"', $this->path));
        }

        return $size;
    }
}
