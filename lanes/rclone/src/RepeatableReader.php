<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class RepeatableReader
{
    public const ERR_INVALID_WHENCE = 'fs.RepeatableReader.Seek: invalid whence';
    public const ERR_NEGATIVE_POSITION = 'fs.RepeatableReader.Seek: negative position';
    public const ERR_OFFSET_UNAVAILABLE = 'fs.RepeatableReader.Seek: offset is unavailable';

    private object $reader;
    private int $position = 0;
    private string $cache = '';
    private ?int $sourceLimit = null;
    private int $sourceRead = 0;

    public function __construct(mixed $reader, ?int $sourceLimit = null)
    {
        $this->reader = self::reader($reader);
        $this->sourceLimit = $sourceLimit;
    }

    public static function sized(mixed $reader, int $size): self
    {
        return new self($reader);
    }

    public static function limit(mixed $reader, int $size): self
    {
        return new self($reader, $size);
    }

    public static function buffer(mixed $reader, string $buffer): self
    {
        return new self($reader);
    }

    public static function limitBuffer(mixed $reader, string $buffer, int $size): self
    {
        return new self($reader, $size);
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $cacheLength = strlen($this->cache);
        if ($this->position === $cacheLength) {
            $readLength = $this->limitedReadLength($length);
            if ($readLength <= 0) {
                return '';
            }

            $chunk = $this->reader->read($readLength);
            if (!is_string($chunk)) {
                throw new \UnexpectedValueException('Reader read() must return a string');
            }
            if ($chunk !== '') {
                if ($this->sourceLimit !== null) {
                    $remaining = $this->sourceLimit - $this->sourceRead;
                    if ($remaining <= 0) {
                        return '';
                    }
                    if (strlen($chunk) > $remaining) {
                        $chunk = substr($chunk, 0, $remaining);
                    }
                }

                $this->cache .= $chunk;
                $chunkLength = strlen($chunk);
                $this->position += $chunkLength;
                $this->sourceRead += $chunkLength;
            }

            return $chunk;
        }

        $chunk = substr($this->cache, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        $cacheLength = strlen($this->cache);
        $absolute = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => $cacheLength + $offset,
            default => throw new \InvalidArgumentException(self::ERR_INVALID_WHENCE),
        };

        if ($absolute < 0) {
            throw new \RuntimeException(self::ERR_NEGATIVE_POSITION);
        }
        if ($absolute > $cacheLength) {
            throw new \RuntimeException(self::ERR_OFFSET_UNAVAILABLE);
        }

        $this->position = $absolute;

        return $absolute;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function cacheLength(): int
    {
        return strlen($this->cache);
    }

    private function limitedReadLength(int $length): int
    {
        if ($this->sourceLimit === null) {
            return $length;
        }

        return min($length, max(0, $this->sourceLimit - $this->sourceRead));
    }

    private static function reader(mixed $reader): object
    {
        if (is_string($reader)) {
            return new class($reader) {
                private int $offset = 0;

                public function __construct(private readonly string $bytes)
                {
                }

                public function read(int $length): string
                {
                    if ($length <= 0 || $this->offset >= strlen($this->bytes)) {
                        return '';
                    }

                    $chunk = substr($this->bytes, $this->offset, $length);
                    $this->offset += strlen($chunk);

                    return $chunk;
                }
            };
        }

        if (is_resource($reader)) {
            return new class($reader) {
                /** @param resource $stream */
                public function __construct(private $stream)
                {
                }

                public function read(int $length): string
                {
                    if ($length <= 0 || feof($this->stream)) {
                        return '';
                    }

                    $chunk = fread($this->stream, $length);
                    if ($chunk === false) {
                        throw new \RuntimeException('Failed to read stream');
                    }

                    return $chunk;
                }
            };
        }

        if (is_object($reader) && method_exists($reader, 'read')) {
            return $reader;
        }

        throw new \InvalidArgumentException('Reader must be a string, stream resource, or object with a read method');
    }
}
