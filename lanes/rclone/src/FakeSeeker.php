<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class FakeSeeker
{
    public const ERR_AFTER_READING = "FakeSeeker: can't seek after reading";
    public const ERR_INVALID_WHENCE = 'FakeSeeker: invalid whence';
    public const ERR_NEGATIVE_POSITION = 'FakeSeeker: negative position';
    public const ERR_NOT_AT_START = "FakeSeeker: not at start: can't read";
    public const ERR_EOF = 'EOF';

    private object $reader;
    private int $offset = 0;
    private bool $read = false;
    private ?\Throwable $readError = null;

    public function __construct(mixed $reader, private readonly int $length)
    {
        $this->reader = self::reader($reader);
    }

    public static function wrap(mixed $reader, int $length): object
    {
        if (is_object($reader) && method_exists($reader, 'read') && method_exists($reader, 'seek')) {
            return $reader;
        }

        return new self($reader, $length);
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        if ($this->readError !== null) {
            throw $this->readError;
        }
        if (!$this->read && $this->offset !== 0) {
            throw new \RuntimeException(self::ERR_NOT_AT_START);
        }

        try {
            $chunk = $this->reader->read($length);
        } catch (\Throwable $throwable) {
            $this->readError = $throwable;
            throw $throwable;
        }

        if (!is_string($chunk)) {
            $this->readError = new \UnexpectedValueException('Reader read() must return a string');
            throw $this->readError;
        }
        if ($chunk !== '') {
            $this->read = true;
            return $chunk;
        }

        $this->readError = new \RuntimeException(self::ERR_EOF);

        return '';
    }

    public function seek(int $offset, int $whence = SEEK_SET): int
    {
        if ($this->readError !== null) {
            throw $this->readError;
        }
        if ($this->read) {
            throw new \RuntimeException(sprintf("FakeSeeker: can't Seek(%d, %d) after reading", $offset, $whence));
        }

        $absolute = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->offset + $offset,
            SEEK_END => $this->length + $offset,
            default => throw new \InvalidArgumentException(self::ERR_INVALID_WHENCE),
        };

        if ($absolute < 0) {
            throw new \RuntimeException(self::ERR_NEGATIVE_POSITION);
        }

        $this->offset = $absolute;

        return $absolute;
    }

    public function tell(): int
    {
        return $this->offset;
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
