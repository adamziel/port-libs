<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class SequentialChunkedReader
{
    public const ERR_FILE_CLOSED = 'file already closed';
    public const ERR_INVALID_SEEK = 'invalid seek position';

    private ?object $reader = null;
    private int $offset = -1;
    private int $chunkOffset = 0;
    private int $chunkSize;
    private readonly int $initialChunkSize;
    private readonly int $maxChunkSize;
    private bool $customChunkSize = false;
    private bool $closed = false;

    public function __construct(
        private readonly MemoryProvider $provider,
        private readonly string $path,
        int $initialChunkSize,
        int $maxChunkSize,
    ) {
        if ($initialChunkSize <= 0) {
            $initialChunkSize = -1;
        }
        if ($maxChunkSize !== -1 && $maxChunkSize < $initialChunkSize) {
            $maxChunkSize = $initialChunkSize;
        }

        $this->chunkSize = $initialChunkSize;
        $this->initialChunkSize = $initialChunkSize;
        $this->maxChunkSize = $maxChunkSize;

        $this->provider->info($this->path);
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
            $chunkEnd = $this->chunkOffset + $this->chunkSize;
            if ($this->chunkSize > 0 && $this->offset === $chunkEnd) {
                $this->chunkOffset = $this->offset;
                if ($this->customChunkSize) {
                    $this->customChunkSize = false;
                    $this->chunkSize = $this->initialChunkSize;
                } else {
                    $this->chunkSize *= 2;
                    if ($this->maxChunkSize !== -1 && $this->chunkSize > $this->maxChunkSize) {
                        $this->chunkSize = $this->maxChunkSize;
                    }
                }
                $chunkEnd = $this->chunkOffset + $this->chunkSize;
                $this->openRange();
            } elseif ($this->offset === -1) {
                $this->openRange();
                $chunkEnd = $this->chunkOffset + $this->chunkSize;
            }

            $remaining = $length - strlen($bytes);
            $readLength = $remaining;
            if ($this->chunkSize > 0) {
                $chunkRest = $chunkEnd - $this->offset;
                if ($chunkRest <= 0) {
                    continue;
                }
                $readLength = min($readLength, $chunkRest);
            }

            $chunk = $this->reader()->read($readLength);
            if (!is_string($chunk)) {
                throw new \UnexpectedValueException('Reader read() must return a string');
            }
            if ($chunk === '') {
                break;
            }

            $bytes .= $chunk;
            $this->offset += strlen($chunk);
            if (strlen($chunk) < $readLength) {
                break;
            }
        }

        return $bytes;
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        return $this->rangeSeek($offset, $whence, -1);
    }

    public function rangeSeek(int $offset, int $whence = SEEK_SET, int $length = -1): int
    {
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        $size = $this->provider->info($this->path)->size;
        $current = $this->offset;
        if ($whence === SEEK_SET) {
            $current = 0;
        } elseif ($whence === SEEK_END) {
            if ($size < 0) {
                throw new \RuntimeException(self::ERR_INVALID_SEEK);
            }
            $current = $size;
        } elseif ($whence !== SEEK_CUR) {
            throw new \RuntimeException(self::ERR_INVALID_SEEK);
        }

        $this->chunkOffset = $current + $offset;
        $this->offset = -1;
        if ($length > 0) {
            $this->customChunkSize = true;
            $this->chunkSize = $length;
        } else {
            $this->customChunkSize = false;
            $this->chunkSize = $this->initialChunkSize;
        }

        if ($this->chunkOffset < 0 || $this->chunkOffset >= $size) {
            $this->chunkOffset = 0;
            throw new \RuntimeException(self::ERR_INVALID_SEEK);
        }

        return $this->chunkOffset;
    }

    public function open(): self
    {
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }
        if ($this->reader !== null && $this->offset !== -1) {
            return $this;
        }

        $this->openRange();

        return $this;
    }

    public function close(): void
    {
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        $this->closed = true;
        $this->resetReader(null, 0);
    }

    private function openRange(): void
    {
        if ($this->closed) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        $offset = $this->chunkOffset;
        $length = $this->chunkSize;
        if ($this->reader !== null && method_exists($this->reader, 'rangeSeek')) {
            try {
                $position = $this->reader->rangeSeek($offset, SEEK_SET, $length);
                if ($position === $offset) {
                    $this->offset = $offset;

                    return;
                }
            } catch (\Throwable) {
            }
        }

        $reader = $length <= 0
            ? $this->provider->openReader($this->path, $offset, null)
            : $this->provider->openReader($this->path, $offset, $length);
        $this->resetReader($reader, $offset);
    }

    private function resetReader(?object $reader, int $offset): void
    {
        if ($this->reader !== null && method_exists($this->reader, 'close')) {
            $this->reader->close();
        }

        $this->reader = $reader;
        $this->offset = $offset;
    }

    private function reader(): object
    {
        if ($this->reader === null) {
            throw new \RuntimeException(self::ERR_FILE_CLOSED);
        }

        return $this->reader;
    }
}
